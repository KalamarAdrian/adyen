<?php
/**
 * Gateway
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2019 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WordPress\Pay\Gateways\Adyen
 */

namespace Pronamic\WordPress\Pay\Gateways\Adyen;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Core\Statuses as Core_Statuses;
use Pronamic\WordPress\Pay\Core\PaymentMethods;
use Pronamic\WordPress\Pay\Core\Util;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Plugin;

/**
 * Gateway
 *
 * @author  Remco Tolsma
 * @version 1.0.0
 * @since   1.0.0
 * @link    https://github.com/adyenpayments/php/blob/master/generatepaymentform.php
 */
class Gateway extends Core_Gateway {
	/**
	 * Slug of this gateway.
	 *
	 * @var string
	 */
	const SLUG = 'adyen';

	/**
	 * Web SDK version.
	 *
	 * @link https://docs.adyen.com/developers/checkout/web-sdk/release-notes-web-sdk
	 *
	 * @var string
	 */
	const SDK_VERSION = '1.9.2';

	/**
	 * Client.
	 *
	 * @var Client
	 */
	protected $client;

	/**
	 * Constructs and initializes an Adyen gateway.
	 *
	 * @param Config $config Config.
	 */
	public function __construct( Config $config ) {
		parent::__construct( $config );

		$this->set_method( self::METHOD_HTTP_REDIRECT );
		$this->set_slug( self::SLUG );

		$this->client = new Client( $config );
	}

	/**
	 * Get supported payment methods
	 *
	 * @see Core_Gateway::get_supported_payment_methods()
	 */
	public function get_supported_payment_methods() {
		return array(
			PaymentMethods::BANCONTACT,
			PaymentMethods::CREDIT_CARD,
			PaymentMethods::DIRECT_DEBIT,
			PaymentMethods::GIROPAY,
			PaymentMethods::IDEAL,
			PaymentMethods::MAESTRO,
			PaymentMethods::SOFORT,
		);
	}

	/**
	 * Start.
	 *
	 * @param Payment $payment Payment.
	 *
	 * @see Plugin::start()
	 */
	public function start( Payment $payment ) {
		// Amount.
		$amount = AmountTransformer::transform( $payment->get_total_amount() );

		// Payment method. Take leap of faith for unknown payment methods.
		$payment_method_type = PaymentMethodType::transform( $payment->get_method() );

		// Country.
		$locale = get_locale();

		if ( null !== $payment->get_customer() ) {
			$locale = $payment->get_customer()->get_locale();
		}

		$locale = explode( '_', $locale );

		$country_code = strtoupper( substr( $locale[1], 0, 2 ) );

		// Create payment or payment session request.
		switch ( $payment->get_method() ) {
			case PaymentMethods::IDEAL:
			case PaymentMethods::SOFORT:
				$payment_method = new PaymentMethod( $payment_method_type );

				switch ( $payment->get_method() ) {
					case PaymentMethods::IDEAL:
						$payment_method->issuer = $payment->get_issuer();

						break;
				}

				// API integration.
				$payment_request = new PaymentRequest(
					$amount,
					$this->config->get_merchant_account(),
					$payment->get_id(),
					$payment->get_return_url(),
					$payment_method
				);

				$payment_request->set_country_code( $country_code );

				$payment_request = PaymentRequestTransformer::transform( $payment, $payment_request );

				$payment_response = $this->client->create_payment( $payment_request );

				$payment->set_transaction_id( $payment_response->get_psp_reference() );

				$redirect = $payment_response->get_redirect();

				if ( null !== $redirect ) {
					$payment->set_action_url( $redirect->get_url() );
				}

				break;
			default:
				// Web SDK integration.
				$payment_session_request = new PaymentSessionRequest(
					$amount,
					$this->config->get_merchant_account(),
					$payment->get_id(),
					$payment->get_return_url(),
					$country_code
				);

				$payment_session_request = PaymentRequestTransformer::transform( $payment, $payment_session_request );

				$payment_session_request->set_origin( home_url() );
				$payment_session_request->set_sdk_version( self::SDK_VERSION );

				if ( null !== $payment_method_type ) {
					$payment_session_request->set_allowed_payment_methods( array( $payment_method_type ) );
				}

				$payment_session_response = $this->client->create_payment_session( $payment_session_request );

				$payment->set_action_url( $payment->get_pay_redirect_url() );

				$payment->set_meta( 'adyen_sdk_version', self::SDK_VERSION );
				$payment->set_meta( 'adyen_payment_session', $payment_session_response->get_payment_session() );
		}
	}

	/**
	 * Payment redirect.
	 *
	 * @param Payment $payment Payment.
	 *
	 * @return void
	 */
	public function payment_redirect( Payment $payment ) {
		$sdk_version     = $payment->get_meta( 'adyen_sdk_version' );
		$payment_session = $payment->get_meta( 'adyen_payment_session' );

		if ( empty( $sdk_version ) || empty( $payment_session ) ) {
			return;
		}

		$url = sprintf(
			'https://checkoutshopper-%s.adyen.com/checkoutshopper/assets/js/sdk/checkoutSDK.%s.min.js',
			( self::MODE_TEST === $payment->get_mode() ? 'test' : 'live' ),
			$sdk_version
		);

		wp_register_script(
			'pronamic-pay-adyen-checkout',
			$url,
			array(),
			$sdk_version,
			false
		);

		wp_localize_script(
			'pronamic-pay-adyen-checkout',
			'pronamicPayAdyenCheckout',
			array(
				'paymentSession' => $payment_session,
				'configObject'   => array(
					'context' => ( self::MODE_TEST === $payment->get_mode() ? 'test' : 'live' ),
				),
			)
		);

		// No cache.
		Util::no_cache();

		require __DIR__ . '/../views/checkout.php';

		exit;
	}

	/**
	 * Update status of the specified payment.
	 *
	 * @param Payment $payment Payment.
	 *
	 * @return void
	 */
	public function update_status( Payment $payment ) {
		// Process payload on return.
		if ( ! filter_has_var( INPUT_GET, 'payload' ) ) {
			return;
		}

		$status = null;

		$payload = filter_input( INPUT_GET, 'payload', FILTER_SANITIZE_STRING );

		switch ( $payment->get_method() ) {
			case PaymentMethods::IDEAL:
			case PaymentMethods::SOFORT:
				$result = $this->client->get_payment_details( $payload );

				break;
			default:
				$result = $this->client->get_payment_result( $payload );
		}

		if ( $result ) {
			$status = ResultCode::transform( $result->resultCode );

			$psp_reference = $result->pspReference;
		}

		// Handle errors.
		if ( empty( $status ) ) {
			$payment->set_status( Core_Statuses::FAILURE );

			$this->error = $this->client->get_error();

			return;
		}

		// Update status.
		$payment->set_status( $status );

		// Update transaction ID.
		if ( isset( $psp_reference ) ) {
			$payment->set_transaction_id( $psp_reference );
		}
	}

	/**
	 * Get available payment methods.
	 *
	 * @see Core_Gateway::get_available_payment_methods()
	 */
	public function get_available_payment_methods() {
		$payment_methods = array();

		// Get active payment methods for Adyen account.
		$methods = $this->client->get_payment_methods();

		if ( ! $methods ) {
			$this->error = $this->client->get_error();

			return $payment_methods;
		}

		// Transform to WordPress payment methods.
		foreach ( $methods as $method => $details ) {
			$payment_method = PaymentMethodType::transform_gateway_method( $method );

			if ( $payment_method ) {
				$payment_methods[] = $payment_method;
			}
		}

		$payment_methods = array_unique( $payment_methods );

		return $payment_methods;
	}

	/**
	 * Get issuers.
	 *
	 * @see Pronamic_WP_Pay_Gateway::get_issuers()
	 */
	public function get_issuers() {
		$groups = array();

		$payment_method = PaymentMethodType::transform( PaymentMethods::IDEAL );

		$result = $this->client->get_issuers( $payment_method );

		if ( ! $result ) {
			$this->error = $this->client->get_error();

			return $groups;
		}

		$groups[] = array(
			'options' => $result,
		);

		return $groups;
	}
}
