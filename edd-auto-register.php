<?php
/*
Plugin Name: Easy Digital Downloads - Auto Register
Plugin URI: http://sumobi.com/shop/edd-auto-register/
Description: Automatically creates a WP user account at checkout, based on customer's email address.
Version: 1.3
Author: Andrew Munro and Pippin Williamson
Contributors: sumobi, mordauk
Author URI: http://sumobi.com/
Text Domain: edd-auto-register
Domain Path: languages
License: GPL-2.0+
License URI: http://www.opensource.org/licenses/gpl-license.php
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'EDD_Auto_Register' ) ) {

	final class EDD_Auto_Register {

		/**
		 * Holds the instance
		 *
		 * Ensures that only one instance of EDD Auto Register exists in memory at any one
		 * time and it also prevents needing to define globals all over the place.
		 *
		 * TL;DR This is a static property property that holds the singleton instance.
		 *
		 * @var object
		 * @static
		 * @since 1.0
		 */
		private static $instance;

		/**
		 * Main Instance
		 *
		 * Ensures that only one instance exists in memory at any one
		 * time. Also prevents needing to define globals all over the place.
		 *
		 * @since 1.0
		 *
		 */
		public static function get_instance() {
			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof EDD_Auto_Register ) ) {
				self::$instance = new EDD_Auto_Register;
				self::$instance->setup_globals();
				self::$instance->hooks();
			}

			return self::$instance;
		}

		/**
		 * Constructor Function
		 *
		 * @since 1.0
		 * @access private
		 */
		private function __construct() {
			self::$instance = $this;

		}

		/**
		 * Reset the instance of the class
		 *
		 * @since 1.0
		 * @access public
		 * @static
		 */
		public static function reset() {
			self::$instance = null;
		}

		/**
		 * Globals
		 *
		 * @since 1.0
		 *
		 * @return void
		 */
		private function setup_globals() {

			$this->version    = '1.3';

			// paths
			$this->file         = __FILE__;
			$this->basename     = apply_filters( 'edd_auto_register_plugin_basenname', plugin_basename( $this->file ) );
			$this->plugin_dir   = apply_filters( 'edd_auto_register_plugin_dir_path',  plugin_dir_path( $this->file ) );
			$this->plugin_url   = apply_filters( 'edd_auto_register_plugin_dir_url',   plugin_dir_url( $this->file ) );

		}

		/**
		 * Setup the default hooks and actions
		 *
		 * @since 1.0
		 *
		 * @return void
		 */
		private function hooks() {

			if ( ! class_exists( 'EDD_Customer' ) ) {
				add_action( 'admin_notices', array( $this, 'admin_notices' ) );
				return;
			}

			// plugin meta
			add_filter( 'plugin_row_meta', array( $this, 'plugin_meta' ), 10, 2 );

			// text domain
			add_action( 'after_setup_theme', array( $this, 'load_textdomain' ) );

			// add settings
			add_filter( 'edd_settings_extensions', array( $this, 'settings' ) );

			// can the customer checkout?
			add_filter( 'edd_can_checkout', array( $this, 'can_checkout' ) );

			// create user when purchase is created
			add_action( 'edd_insert_payment', array( $this, 'maybe_insert_user' ), 10, 2 );

			// stop EDD from sending new user notification, we want to customize this a bit
			remove_action( 'edd_insert_user', 'edd_new_user_notification', 10, 2 );

			// add our new email notifications
			add_action( 'edd_auto_register_insert_user', array( $this, 'email_notifications' ), 10, 3 );

			// Ensure registration form is never shown
			add_filter( 'edd_get_option_show_register_form', array( $this, 'remove_register_form' ), 10, 3 );

			// Force guest checkout to be enabled
			add_filter( 'edd_no_guest_checkout', '__return_false' );
			add_filter( 'edd_logged_in_only', '__return_false' );

			do_action( 'edd_auto_register_setup_actions' );
		}

		/**
		 * Admin notices
		 *
		 * @since 1.0
		 */
		public function admin_notices() {
			echo '<div class="error"><p>' . __( 'EDD Auto Register requires Easy Digital Downloads Version 2.3 or greater. Please update or install Easy Digital Downloads.', 'edd-auto-register' ) . '</p></div>';
		}


		/**
		 * Loads the plugin language files
		 *
		 * @access public
		 * @since 1.0
		 * @return void
		 */
		public function load_textdomain() {
			// Set filter for plugin's languages directory
			$lang_dir = dirname( plugin_basename( $this->file ) ) . '/languages/';
			$lang_dir = apply_filters( 'edd_auto_register_languages_directory', $lang_dir );

			// Traditional WordPress plugin locale filter
			$locale        = apply_filters( 'plugin_locale',  get_locale(), 'edd-auto-register' );
			$mofile        = sprintf( '%1$s-%2$s.mo', 'edd-auto-register', $locale );

			// Setup paths to current locale file
			$mofile_local  = $lang_dir . $mofile;
			$mofile_global = WP_LANG_DIR . '/edd-auto-register/' . $mofile;

			if ( file_exists( $mofile_global ) ) {
				// Look in global /wp-content/languages/edd-auto-register folder
				load_textdomain( 'edd-auto-register', $mofile_global );
			} elseif ( file_exists( $mofile_local ) ) {
				// Look in local /wp-content/plugins/edd-auto-register/languages/ folder
				load_textdomain( 'edd-auto-register', $mofile_local );
			} else {
				// Load the default language files
				load_plugin_textdomain( 'edd-auto-register', false, $lang_dir );
			}
		}


		/**
		 * Notifications
		 * Sends the user an email with their logins details and also sends the site admin an email notifying them of a signup
		 *
		 * @since 1.1
		 */
		public function email_notifications( $user_id = 0, $user_data = array() ) {
			global $edd_options;

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
			if ( empty( $user_data['user_pass'] ) ) {
				return;
			}

			// message
			$message = $this->get_email_body_content( $user_data['first_name'], sanitize_user( $user_data['user_login'], true ), $user_data['user_pass'] );

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
			if ( ! $user_email_disabled ) {
				$emails->send( $user_data['user_email'], $subject, $message );
			}

		}

		/**
		 * Email Template Body
		 *
		 * @since 1.0
		 * @return string $default_email_body Body of the email
		 */
		public function get_email_body_content( $first_name, $username, $password ) {

			// Email body
			$default_email_body = __( "Dear", "edd-auto-register" ) . ' ' . $first_name . ",\n\n";
			$default_email_body .= __( "Below are your login details:", "edd-auto-register" ) . "\n\n";
			$default_email_body .= __( "Your Username:", "edd-auto-register" ) . ' ' . $username . "\n\n";
			$default_email_body .= __( "Your Password:", "edd-auto-register" ) . ' ' . $password . "\n\n";
			$default_email_body .= __( "Login:", "edd-auto-register" ) . ' ' . wp_login_url() . "\r\n";

			$default_email_body = apply_filters( 'edd_auto_register_email_body', $default_email_body, $first_name, $username, $password );

			return $default_email_body;
		}

		/**
		 * Can checkout?
		 * Prevents the form from being displayed when User must be logged in (Guest Checkout disabled), but "Show Register / Login Form?" is not
		 *
		 * @since 1.0
		 */
		public function can_checkout( $can_checkout ) {
			global $edd_options;

			if ( edd_no_guest_checkout() && ! edd_get_option( 'show_register_form' ) && ! is_user_logged_in() ) {
				return false;
			}

			return $can_checkout;
		}

		/**
		 * Maybe create a user when payment is created
		 *
		 * @since 1.3
		 */
		public function maybe_insert_user( $payment_id, $payment_data ) {

			// User account already associated
			if ( $payment_data['user_info']['id'] > 0 ) {
				return;
			}

			// User account already exists
			if ( get_user_by( 'email', $payment_data['user_info']['email'] ) ) {
				return;
			}

			$user_name = sanitize_user( $payment_data['user_info']['email'] );

			// Username already exists
			if ( username_exists( $user_name ) ) {
				return;
			}

			// Okay we need to create a user and possibly log them in

			$user_args = apply_filters( 'edd_auto_register_insert_user_args', array(
				'user_login'      => $user_name,
				'user_pass'       => wp_generate_password( 32 ),
				'user_email'      => $payment_data['user_info']['email'],
				'first_name'      => $payment_data['user_info']['first_name'],
				'last_name'       => $payment_data['user_info']['last_name'],
				'user_registered' => date( 'Y-m-d H:i:s' ),
				'role'            => get_option( 'default_role' )
			), $payment_id, $payment_data );

			// Insert new user
			$user_id = wp_insert_user( $user_args );

			// Validate inserted user
			if ( is_wp_error( $user_id ) ) {
				return;
			}

			$payment_meta = edd_get_payment_meta( $payment_id );

			$payment_meta['user_info']['id'] = $user_id;

			edd_update_payment_meta( $payment_id, '_edd_payment_user_id', $user_id );
			edd_update_payment_meta( $payment_id, '_edd_payment_meta', $payment_meta );

			$customer = new EDD_Customer( $payment_data['user_info']['email'] );
			$customer->update( array( 'user_id' => $user_id ) );

			// Allow themes and plugins to hook
			do_action( 'edd_auto_register_insert_user', $user_id, $user_args, $payment_id );

			if( function_exists( 'did_action' ) && did_action( 'edd_purchase' ) ) {

				// Only log user in if processing checkout screen
				edd_log_user_in( $user_id, $user_args['user_login'], $user_args['user_pass'] );

			}

		}

		/**
		 * Settings
		 *
		 * @since 1.1
		 */
		public function settings( $settings ) {
			$edd_ar_settings = array(
				array(
					'id' => 'edd_auto_register_header',
					'name' => '<strong>' . __( 'Auto Register', 'edd-auto-register' ) . '</strong>',
					'type' => 'header',
				),
				array(
					'id' => 'edd_auto_register_disable_user_email',
					'name' => __( 'Disable User Email', 'edd-auto-register' ),
					'desc' => __( 'Disables the email sent to the user that contains login details', 'edd-auto-register' ),
					'type' => 'checkbox',
				),
				array(
					'id' => 'edd_auto_register_disable_admin_email',
					'name' => __( 'Disable Admin Notification', 'edd-auto-register' ),
					'desc' => __( 'Disables the new user registration email sent to the admin', 'edd-auto-register' ),
					'type' => 'checkbox',
				),
			);

			return array_merge( $settings, $edd_ar_settings );
		}

		/**
		 * Removes the registration form and changes it to a log in form
		 *
		 * @since 1.3
		 */
		public function remove_register_form( $value, $key, $default ) {

			if ( 'both' === $value || 'registration' === $value ) {
				$value = 'login';
			}

			return $value;
		}

		/**
		 * Modify plugin metalinks
		 *
		 * @access      public
		 * @since       1.0.0
		 * @param array   $links The current links array
		 * @param string  $file  A specific plugin table entry
		 * @return      array $links The modified links array
		 */
		public function plugin_meta( $links, $file ) {
			if ( $file == plugin_basename( __FILE__ ) ) {
				$plugins_link = array(
					'<a title="View more plugins for Easy Digital Downloads by Sumobi" href="https://easydigitaldownloads.com/blog/author/andrewmunro/?ref=166" target="_blank">' . __( 'Author\'s EDD plugins', 'edd-auto-register' ) . '</a>'
				);

				$links = array_merge( $links, $plugins_link );
			}

			return $links;
		}

	}
}

/**
 * Loads a single instance of EDD Auto Register
 *
 * This follows the PHP singleton design pattern.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * @example <?php $edd_auto_register = edd_auto_register(); ?>
 *
 * @since 1.0
 *
 * @see EDD_Auto_Register::get_instance()
 *
 * @return object Returns an instance of the EDD_Auto_Register class
 */
function edd_auto_register() {
	return EDD_Auto_Register::get_instance();
}

/**
 * Loads plugin after all the others have loaded and have registered their hooks and filters
 *
 * @since 1.0
 */
add_action( 'plugins_loaded', 'edd_auto_register', apply_filters( 'edd_auto_register_action_priority', 10 ) );
