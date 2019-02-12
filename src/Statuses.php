<?php
/**
 * Statuses
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2019 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WordPress\Pay\Gateways\Adyen
 */

namespace Pronamic\WordPress\Pay\Gateways\Adyen;

use Pronamic\WordPress\Pay\Core\Statuses as Core_Statuses;

/**
 * Title: Adyen statuses constants.
 * Description:
 * Copyright: 2005-2019 Pronamic
 * Company: Pronamic
 *
 * @author  Reüel van der Steege
 * @version 1.0.0
 * @since   1.0.0
 *
 * @link https://docs.adyen.com/developers/checkout/payment-result-codes
 */
class Statuses {
	/**
	 * Authorized.
	 *
	 * The payment was successfully authorised.
	 *
	 * @var string
	 */
	const AUTHORIZED = 'Authorised';

	/**
	 * Cancelled.
	 *
	 * The payment was cancelled (by either the shopper or
	 * your own system) before processing was completed.
	 *
	 * @var string
	 */
	const CANCELLED = 'Cancelled';

	/**
	 * Error.
	 *
	 * There was an error when the payment was being processed.
	 *
	 * @var string
	 */
	const ERROR = 'Error';

	/**
	 * Pending.
	 *
	 * It's not possible to obtain the final status of the payment at this time.
	 * This is common for payments with an asynchronous flow, such as Boleto, iDEAL, or Klarna.
	 *
	 * @var string
	 */
	const PENDING = 'Pending';

	/**
	 * Received.
	 *
	 * This is part of the standard payment flow for methods such as SEPA Direct Debit,
	 * where it can take some time before the final status of the payment is known.
	 *
	 * @var string
	 */
	const RECEIVED = 'Received';

	/**
	 * Redirect shopper.
	 *
	 * The shopper needs to be redirected to an external web page or app to complete the payment.
	 *
	 * @var string
	 */
	const REDIRECT_SHOPPER = 'redirectShopper';

	/**
	 * Refused.
	 *
	 * The payment was refused.
	 *
	 * @var string
	 */
	const REFUSED = 'Refused';

	/**
	 * Transform an Mollie state to an more global status.
	 *
	 * @param string $status Mollie status.
	 *
	 * @return string|null Pay status.
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::PENDING:
			case self::RECEIVED:
			case self::REDIRECT_SHOPPER:
				return Core_Statuses::OPEN;

			case self::CANCELLED:
				return Core_Statuses::CANCELLED;

			case self::ERROR:
			case self::REFUSED:
				return Core_Statuses::FAILURE;

			case self::AUTHORIZED:
				return Core_Statuses::SUCCESS;
		}

		return null;
	}
}