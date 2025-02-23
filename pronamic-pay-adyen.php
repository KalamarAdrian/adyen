<?php
/**
 * Plugin Name: Pronamic Pay Adyen Add-On
 * Plugin URI: https://www.pronamic.eu/plugins/pronamic-pay-adyen/
 * Description: Extend the Pronamic Pay plugin with the Adyen gateway to receive payments with Adyen through a variety of WordPress plugins.
 *
 * Version: 2.0.2
 * Requires at least: 4.7
 *
 * Author: Pronamic
 * Author URI: https://www.pronamic.eu/
 *
 * Text Domain: pronamic-pay-adyen
 * Domain Path: /languages/
 *
 * License: GPL-3.0-or-later
 *
 * Depends: wp-pay/core
 *
 * GitHub URI: https://github.com/wp-pay-gateways/adyen
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2021 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WordPress\Pay\Gateways\Adyen
 */

add_filter(
	'pronamic_pay_gateways',
	function( $gateways ) {
		$gateways[] = new \Pronamic\WordPress\Pay\Gateways\Adyen\Integration();

		return $gateways;
	}
);
