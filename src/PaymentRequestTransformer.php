<?php
/**
 * Payment request transformer
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2019 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WordPress\Pay\Gateways\Adyen
 */

namespace Pronamic\WordPress\Pay\Gateways\Adyen;

use Pronamic\WordPress\Pay\Address as Pay_Address;

/**
 * Payment request transformer
 *
 * @author  Remco Tolsma
 * @version 1.0.0
 * @since   1.0.0
 */
class PaymentRequestTransformer {
	/**
	 * Transform WordPress Pay payment to Adyen payment request.
	 *
	 * @param Payment                $payment WordPress Pay payment to convert.
	 * @param AbstractPaymentRequest $request Adyen payment request.
	 * @return AbstractPaymentRequest
	 */
	public static function transform( Payment $payment, AbstractPaymentRequest $request ) {
		// Channel.
		$request->set_channel( Channel::WEB );

		// Shopper.
		$request->set_shopper_statement( $payment->get_description() );

		if ( null !== $payment->get_customer() ) {
			$customer = $payment->get_customer();

			$request->set_shopper_ip( $customer->get_ip_address() );
			$request->set_shopper_locale( $customer->get_locale() );
			$request->set_shopper_reference( $customer->get_user_id() );
			$request->set_telephone_number( $customer->get_phone() );

			// Shopper name.
			if ( null !== $customer->get_name() ) {
				$shopper_name = new Name(
					$customer->get_name()->get_first_name(),
					$customer->get_name()->get_last_name(),
					GenderTransformer::transform( $customer->get_gender() )
				);

				$request->set_shopper_name( $shopper_name );
			}

			// Date of birth.
			if ( null !== $customer->get_birth_date() ) {
				$request->set_date_of_birth( $customer->get_birth_date()->format( 'YYYY-MM-DD' ) );
			}
		}

		// Billing address.
		$billing_address = $payment->get_billing_address();

		if ( null !== $billing_address ) {
			$address = AddressTransformer::transform( $billing_address );

			$request->set_billing_address( $address );
		}

		// Delivery address.
		$shipping_address = $payment->get_shipping_address();

		if ( null !== $shipping_address ) {
			$address = AddressTransformer::transform( $shipping_address );

			$request->set_delivery_address( $address );
		}

		// Lines.
		$lines = $payment->get_lines();

		if ( null !== $lines ) {
			$line_items = $request->new_items();

			$i = 1;

			foreach ( $lines as $line ) {
				// Description.
				$description = $line->get_description();

				// Use line item name as fallback for description.
				if ( null === $description ) {
					/* translators: %s: item index */
					$description = sprintf( __( 'Item %s', 'pronamic_ideal' ), $i ++ );

					if ( null !== $line->get_name() && '' !== $line->get_name() ) {
						$description = $line->get_name();
					}
				}

				$item = $line_items->new_item(
					$description,
					$line->get_quantity(),
					$line->get_total_amount()->get_including_tax()->get_minor_units()
				);

				$item->set_amount_excluding_tax( $line->get_total_amount()->get_excluding_tax()->get_minor_units() );

				$item->set_id( $line->get_id() );

				// Tax amount.
				$tax_amount = $line->get_unit_price()->get_tax_amount();

				if ( null !== $tax_amount ) {
					$item->set_tax_amount( $line->get_total_amount()->get_tax_amount()->get_minor_units() );
					$item->set_tax_percentage( (int) $line->get_total_amount()->get_tax_percentage() * 100 );
				}
			}
		}
	}
}
