<?php

class EDD_AR_Emails {

	function __construct() {

		// stop EDD from sending new user notification, we want to customize this a bit
		remove_action( 'edd_insert_user', 'edd_new_user_notification', 10, 2 );

		// add our new email notifications
		add_action( 'edd_auto_register_insert_user', array( $this, 'email_notifications' ), 10, 3 );

		// Add new email tags: {set_password_link} and {login_link}
		add_action( 'edd_add_email_tags', array( $this, 'add_email_tag' ), 100 );

	}

	/**
	 * Notifications
	 * Sends the user an email with their logins details and also sends the site admin an email notifying them of a signup
	 *
	 * @since 1.1
	 */
	public function email_notifications( $user_id = 0, $user_data = array() ) {

		$user = get_userdata( $user_id );

		$user_email_disabled  = edd_get_option( 'edd_auto_register_disable_user_email', '' );
		$admin_email_disabled = edd_get_option( 'edd_auto_register_disable_admin_email', '' );

		// The blogname option is escaped with esc_html on the way into the database in sanitize_option
		// we want to reverse this for the plain text arena of emails.
		$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

		$message  = sprintf( __( 'New user registration on your site %s:', 'edd-auto-register' ), $blogname ) . "\r\n\r\n";
		$message .= sprintf( __( 'Username: %s', 'edd-auto-register' ), $user->user_login ) . "\r\n\r\n";
		$message .= sprintf( __( 'E-mail: %s', 'edd-auto-register' ), $user->user_email ) . "\r\n";

		if ( ! $admin_email_disabled ) {
			@wp_mail( get_option( 'admin_email' ), sprintf( __( '[%s] New User Registration', 'edd-auto-register' ), $blogname ), $message );
		}

		// user registration
		if ( empty( $user_data['user_pass'] || ! $user_email_disabled ) ) {
			return;
		}

		// message
		$message = $this->get_email_body_content( $user, $user_data );

		// subject line
		$subject = apply_filters( 'edd_auto_register_email_subject', sprintf( __( '[%s] Your username and password', 'edd-auto-register' ), $blogname ) );

		// get from name and email from EDD options
		$from_name  = edd_get_option( 'from_name', get_bloginfo( 'name' ) );
		$from_email = edd_get_option( 'from_email', get_bloginfo( 'admin_email' ) );

		$headers = "From: " . stripslashes_deep( html_entity_decode( $from_name, ENT_COMPAT, 'UTF-8' ) ) . " <$from_email>\r\n";
		$headers .= "Reply-To: ". $from_email . "\r\n";
		$headers = apply_filters( 'edd_auto_register_headers', $headers );

		$emails = new EDD_Emails;

		$emails->__set( 'from_name', $from_name );
		$emails->__set( 'from_email', $from_email );
		$emails->__set( 'headers', $headers );

		// Email the user
		$emails->send( $user_data['user_email'], $subject, $message );

	}

	/**
	 * Email Template Body
	 *
	 * @since 1.0
	 * @return string $default_email_body Body of the email
	 */
	public function get_email_body_content( $user, $user_data ) {
		$key = get_password_reset_key( $user );

		// Email body
		$default_email_body = sprintf( __( 'Dear %s', 'edd-auto-register' ), $user_data['first_name'] ) . ",\n\n";
		$default_email_body .= __( 'Below are your login details:', 'edd-auto-register' ) . "\n\n";
		$default_email_body = sprintf( __( 'Your Username: %s', 'edd-auto-register' ), sanitize_user( $user_data['user_login'], true ) ) . "\r\n\r\n";
		$default_email_body .= __('To set your password, visit the following address:') . "\r\n\r\n";
		$default_email_body .= network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user_data['user_login'] ), 'login' ) . "\r\n\r\n";
		$default_email_body .= sprintf( __( 'Login: %s', 'edd-auto-register' ), wp_login_url() ) . "\r\n";

		$default_email_body = apply_filters( 'edd_auto_register_email_body', $default_email_body,  $user_data['first_name'], sanitize_user( $user_data['user_login'], true ), $user_data['user_pass'] );

		return $default_email_body;
	}

	/**
	 * Define new tags {set_password_link} and {login_link}
	 *
	 * @since 1.4
	 */
	public function add_email_tag() {

		edd_add_email_tag(
			'set_password_link',
			__( 'The password set link', 'edd-auto-register' ),
			array( $this, 'reset_password_link_tag' )
		);

		edd_add_email_tag(
			'login_link',
			__( 'The login link', 'edd-auto-register' ),
			array( $this, 'login_link_tag' )
		);

	}

	/**
	 * Check if it the first purchase
	 *
	 * @since 1.4
	 * @return bool If it is the first puchase then return true else false
	 */
	public function is_first_purchase() {
		$user = wp_get_current_user();
		$customer = new EDD_Customer( $user->ID, true );
		if( $customer->purchase_count < 2 ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Email tag callback for {set_password_link}
	 *
	 * @since 1.4
	 * @return string Return link to set the password
	 */
	public function reset_password_link_tag() {

		if( $this->is_first_purchase() ) {
			$user = wp_get_current_user();
			$key = get_password_reset_key( $user );
			return'<a href="' . network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user->user_login ), 'login' ) . '">' . __( 'Set your password', 'edd-auto-register' ) . '</a>';
		} else {
			return;
		}

	}

	/**
	 * Email tag callback for {login_link}
	 *
	 * @since 1.4
	 * @return string Return link to set the password
	 */
	public function login_link_tag() {

		if( $this->is_first_purchase() ) {
			return '<a href="' . wp_login_url() . '">' . __( 'Login', 'edd-auto-register' ) . '</a>';
		} else {
			return;
		}

	}

}
$edd_ar_emails = new EDD_AR_Emails;
