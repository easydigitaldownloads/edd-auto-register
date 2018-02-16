<?php
/**
 * Integration functions to make Auto Register compatible with EDD Software Licenses
 *
 * @package     EDD\EDD_Auto_Register\Functions
 * @since       1.4
 */

// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

/**
 * Integrates EDD All Access with the EDD Software Licensing extension
 *
 * @since 1.4
 */
class EDD_Auto_Register_Software_Licensing {

	/**
	 * Get things started
	 *
	 * @since  1.4
	 * @return void
	 */
	public function __construct() {

		if ( ! class_exists( 'EDD_Software_Licensing' ) ) {
			return;
		}

		add_action( 'edd_auto_register_new_user_created', array( $this, 'assign_user_to_purchased_licenses' ), 10, 2 );
	}

	/**
	 * For a newly auto-registered user because of a payment, assign any licenses that were in the payment to that user
	 *
	 * @since       1.4
	 */
	public function assign_user_to_purchased_licenses( $new_user_id, $payment){

		$licenses = edd_software_licensing()->get_licenses_of_purchase( $payment->ID );

		if( $licenses ) {
			foreach ( $licenses as $license ) {

				update_post_meta( $license->ID, '_edd_sl_user_id', $new_user_id );

			}
		}

	}
}
