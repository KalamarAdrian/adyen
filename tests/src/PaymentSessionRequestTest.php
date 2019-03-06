<?php
/**
 * Payment session request test
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2019 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WordPress\Pay\Gateways\Adyen
 */

namespace Pronamic\WordPress\Pay\Gateways\Adyen;

/**
 * Payment session request test
 *
 * @author  Remco Tolsma
 * @version 1.0.0
 * @since   1.0.0
 */
class PaymentSessionRequestTest extends \PHPUnit_Framework_TestCase {
	/**
	 * Test payment request.
	 */
	public function test_payment_request() {
		$json_file = __DIR__ . '/../json/payment-session-request.json';

		$amount = new Amount( 'EUR', 1000 );

		$payment_request = new PaymentSessionRequest(
			$amount,
			'YOUR_MERCHANT_ACCOUNT',
			'Your order number',
			'https://your-company.com/...',
			'NL'
		);

		$this->assertEquals( $amount, $payment_request->get_amount() );
		$this->assertEquals( 'YOUR_MERCHANT_ACCOUNT', $payment_request->get_merchant_account() );
		$this->assertEquals( 'Your order number', $payment_request->get_reference() );
		$this->assertEquals( 'https://your-company.com/...', $payment_request->get_return_url() );
		$this->assertEquals( 'NL', $payment_request->get_country_code() );

		$json_string = wp_json_encode( $payment_request->get_json(), JSON_PRETTY_PRINT );

		$this->assertJsonStringEqualsJsonFile( $json_file, $json_string );
	}
}