<?php
/**
 * Integration functions to make Auto Register compatible with EDD Recurring Payments
 *
 * @package     EDD\EDD_Auto_Register\Functions
 * @since       1.0.0
 */

// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

/**
 * Integrates EDD Auto Register with the EDD Software Licensing extension
 *
 * @since 1.4.0
 */
class EDD_Auto_Register_Recurring_Payments {

	/**
	 * Get things started
	 *
	 * @since  1.4
	 * @return void
	 */
	public function __construct() {

		if ( ! class_exists( 'EDD_Recurring' ) ) {
			return;
		}

		add_action( 'edd_recurring_pre_create_payment_profiles', array( $this, 'recurring_maybe_insert_user' ) );
	}

	/**
	 * Auto Register normally creates the user from an already-existing payment. For Recurring, because it
	 * checks for the user prior to creating the payment, we have to do it in a custom way here.
	 *
	 * @since 1.4
	 */
	public function recurring_maybe_insert_user( $subscription ) {

		edd_debug_log( 'EDDAR: recurring_maybe_insert_user running...' );

		if ( ! is_user_logged_in() ) {

			$user_id = edd_auto_register()->create_user( $subscription->purchase_data );

		} else {

			if( function_exists( 'did_action' ) && ! did_action( 'edd_create_payment' ) ) {

				// Don't use the current user ID when creating payments through Manual Purchases
				$user_id = get_current_user_id();

			}
		}

		// Validate inserted user
		if ( empty( $user_id ) || is_wp_error( $user_id ) ) {
			return;
		}

	}
}
