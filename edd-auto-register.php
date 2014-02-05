<?php
/*
Plugin Name: Easy Digital Downloads - Auto Register
Plugin URI: http://sumobi.com/shop/edd-auto-register/
Description: Automatically creates a WP user account at checkout, based on customer's email address.
Version: 1.1
Author: Andrew Munro, Sumobi
Author URI: http://sumobi.com/
License: GPL-2.0+
License URI: http://www.opensource.org/licenses/gpl-license.php
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'EDD_Auto_Register' ) ) {

	final class EDD_Auto_Register {

		/**
		 * Holds the instance
		 *
		 * Ensures that only one instance of EDD Wish Lists exists in memory at any one
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

			$this->version    = '1.1';

			// paths
			$this->file         = __FILE__;
			$this->basename     = apply_filters( 'edd_auto_register_plugin_basenname', plugin_basename( $this->file ) );
			$this->plugin_dir   = apply_filters( 'edd_auto_register_plugin_dir_path',  plugin_dir_path( $this->file ) );
			$this->plugin_url   = apply_filters( 'edd_auto_register_plugin_dir_url',   plugin_dir_url ( $this->file ) );

		}

		/**
		 * Setup the default hooks and actions
		 *
		 * @since 1.0
		 *
		 * @return void
		 */
		private function hooks() {
			
		//	add_action( 'admin_init', array( $this, 'activation' ), 1 );

			// text domain
			add_action( 'after_setup_theme', array( $this, 'load_textdomain' ) );

			// template redirect actions
			add_action( 'template_redirect', array( $this, 'template_redirect' ) );

			// add settings
			// This will come in future version
			add_filter( 'edd_settings_extensions', array( $this, 'settings' ) );

			// can the customer checkout?
			add_filter( 'edd_can_checkout', array( $this, 'can_checkout' ) );

			// modify the purchase data before gateway
			add_filter( 'edd_purchase_data_before_gateway', array( $this, 'purchase_data_before_gateway' ), 10, 2 );

			// adds hidden input that flags the form and tells it to use registration
			add_action( 'edd_purchase_form_user_info', array( $this, 'add_needs_to_register_flag' ) );

			// modify user args
			add_filter( 'edd_insert_user_args', array( $this, 'insert_user_args' ), 10, 2 );

			// filter user data
			add_filter( 'edd_insert_user_data', array( $this, 'filter_user_data' ), 10, 2 );
			
			// show error before purchase form
			add_action( 'edd_before_purchase_form', array( $this, 'edd_error_must_log_in' ) );

			// stop EDD from sending new user notification, we want to customize this a bit
			remove_action( 'edd_insert_user', 'edd_new_user_notification', 10, 2 );
			// add our new email notifications
			add_action( 'edd_insert_user', array( $this, 'email_notifications' ), 10, 2 );

			do_action( 'edd_auto_register_setup_actions' );
		}

		/**
		 * Activation function fires when the plugin is activated.
		 *
		 * This function is fired when the activation hook is called by WordPress,
		 * it flushes the rewrite rules and disables the plugin if EDD isn't active
		 * and throws an error.
		 *
		 * @since 1.1
		 * @access public
		 *
		 * @return void
		 */
		public function activation() {
			if ( ! class_exists( 'Easy_Digital_Downloads' ) ) {
				// is this plugin active?
				if ( is_plugin_active( $this->basename ) ) {
					// deactivate the plugin
			 		deactivate_plugins( $this->basename );
			 		// unset activation notice
			 		unset( $_GET[ 'activate' ] );
			 		// display notice
			 		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
				}

			}
		}

		/**
		 * Admin notices
		 *
		 * @since 1.0
		*/
		public function admin_notices() {
			$edd_plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/easy-digital-downloads/easy-digital-downloads.php', false, false );

			if ( ! is_plugin_active('easy-digital-downloads/easy-digital-downloads.php') ) {
				echo '<div class="error"><p>' . sprintf( __( 'You must install %sEasy Digital Downloads%s to use the EDD Auto Register Add-On.', 'edd-auto-register' ), '<a href="http://easydigitaldownloads.com" title="Easy Digital Downloads" target="_blank">', '</a>' ) . '</p></div>';
			}

			if ( $edd_plugin_data['Version'] < '1.9' ) {
				echo '<div class="error"><p>' . __( 'The EDD Auto Register Add-On requires at least Easy Digital Downloads Version 1.9. Please update Easy Digital Downloads.', 'edd-auto-register' ) . '</p></div>';
			}
		}

		/**
		 * Template redirect functions
		 *
		 * @since 1.1
		*/
		public function template_redirect() {
			global $edd_options;

			// settings -> misc -> disable guest checkout
			$disable_guest_checkout = edd_no_guest_checkout();

			// settings -> misc -> show register/login form?
			$show_register_login_form = isset( $edd_options['show_register_form'] );

			// if user is not logged in
			if ( ! is_user_logged_in() ) {

				// set error if email already exists
				// commented out while I rethink how this will work
				// will need to either use ajax to switch the form or consider putting more hooks in EDD core.
			//	add_action( 'edd_checkout_error_checks', array( $this, 'set_error' ), 10, 2 );

				// settings -> misc -> disable guest checkout
				// settings -> misc -> show register/login form?
				if ( $disable_guest_checkout && $show_register_login_form ) {
					// add login form
					add_action( 'edd_purchase_form_register_fields', array( $this, 'login_form' ) );

					// remove joint registration/purchase form
					remove_action( 'edd_purchase_form_register_fields', 'edd_get_register_fields' );
				}
				// settings -> misc -> disable guest checkout
				elseif ( $disable_guest_checkout ) {
					 remove_action( 'edd_payment_mode_select', 'edd_payment_mode_select' );
				}
				// settings -> misc -> show register/login form?
				elseif ( $show_register_login_form ) {
					// remove standard registration form as it will interfer with plugin
					remove_action( 'edd_purchase_form_register_fields', 'edd_get_register_fields' );

					// add standard purchase fields back
					add_action( 'edd_purchase_form_register_fields', 'edd_user_info_fields' );
				}
			}
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
		 * Set need_new_user flag to true
		 *
		 * @since 1.1
		*/
		public function purchase_data_before_gateway( $purchase_data, $valid_data ) {
			$valid_data['need_new_user'] = true;

			return $purchase_data;
		}

		/**
		 * Modify user args to create our new user
		 *
		 * @since 1.1
		*/
		public function insert_user_args( $user_args, $user_data ) {
			// generate random password
			$password = wp_generate_password( 12, false );
			// set username login to be email. WordPress will strip +'s from email
			$user_args['user_login'] = isset( $user_data['user_email'] ) ? $user_data['user_email'] : null;

			// set user pass
			$user_args['user_pass'] = $password;

			// set nickname
			$user_args['nickname'] = $user_data['user_first'];

			return apply_filters( 'edd_auto_register_insert_user_args', $user_args );
		}

		/**
		 * Filter the user data so we can auto login
		 *
		 * @since 1.1
		*/
		public function filter_user_data( $user_data, $user_args ) {
			$user_data['user_login'] = $user_args['user_login'];
			$user_data['user_pass'] = $user_args['user_pass'];

			return $user_data;
		}

		/**
		 * Add hidden input which tells the form there needs to be a registration process
		 *
		 * @since 1.1
		*/
		public function add_needs_to_register_flag() { ?>
			<input type="hidden" name="edd-purchase-var" value="needs-to-register" />
		<?php }


		/**
		 * Notifications
		 * Sends the user an email with their logins details and also sends the site admin an email notifying them of a signup
		 *
		 * @since 1.1
		*/
		public function email_notifications( $user_id = 0, $user_data = array() ) {
			global $edd_options;

			$user = get_userdata( $user_id );

			$user_email_disabled = isset( $edd_options['edd_auto_register_disable_user_email'] ) ? $edd_options['edd_auto_register_disable_user_email'] : '';
			$admin_email_disabled = isset( $edd_options['edd_auto_register_disable_admin_email'] ) ? $edd_options['edd_auto_register_disable_admin_email'] : '';

			// The blogname option is escaped with esc_html on the way into the database in sanitize_option
			// we want to reverse this for the plain text arena of emails.
			$blogname = wp_specialchars_decode( get_option('blogname' ), ENT_QUOTES );

			$message  = sprintf( __( 'New user registration on your site %s:', 'edd-auto-register' ), $blogname ) . "\r\n\r\n";
			$message .= sprintf( __( 'Username: %s', 'edd-auto-register' ), $user->user_login ) . "\r\n\r\n";
			$message .= sprintf( __( 'E-mail: %s', 'edd-auto-register' ), $user->user_email ) . "\r\n";

			if ( ! $admin_email_disabled ) {
				@wp_mail( get_option( 'admin_email' ), sprintf( __( '[%s] New User Registration', 'edd-auto-register' ), $blogname ), $message );
			}
			
			// user registration
			if ( empty( $user_data['user_pass'] ) )
				return;

			// message
			$message = $this->get_email_body_content( $user_data['user_first'], $user_data['user_login'], $user_data['user_pass'] );

			// subject line
			$subject = apply_filters( 'edd_auto_register_email_subject', sprintf( __( '[%s] Your username and password', 'edd-auto-register' ), $blogname ) );

			// get from name and email from EDD options
			$from_name = isset( $edd_options['from_name'] ) ? $edd_options['from_name'] : get_bloginfo( 'name' );
			$from_email = isset( $edd_options['from_email'] ) ? $edd_options['from_email'] : get_option( 'admin_email' );

			$headers = "From: " . stripslashes_deep( html_entity_decode( $from_name, ENT_COMPAT, 'UTF-8' ) ) . " <$from_email>\r\n";
			$headers .= "Reply-To: ". $from_email . "\r\n";
			$headers = apply_filters( 'edd_auto_register_headers', $headers );

			// Email the user
			if ( ! $user_email_disabled ) {
				wp_mail( $user_data['user_email'], $subject, $message, $headers );
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
		 * Set error message
		 * Prevents form from processing if username already exists, or the email address is already registered against another user account
		 *
		 * @since 1.0
		 * @todo  remove from plugin, no longer needed
		*/
		public function set_error( $valid_data, $post_data ) {
			if ( edd_no_guest_checkout() )
				return;

			$email_address 	= isset( $valid_data['new_user_data']['user_email'] ) ? $valid_data['new_user_data']['user_email'] : '';
			$email_address 	= sanitize_user( $email_address, true );
			$user_id 		= username_exists( $email_address );

			// check to see if the username exists already and an email hasn't already been registered against username
			if ( $email_address && ( $user_id || email_exists( $valid_data['new_user_data']['user_email'] ) ) ) {
				edd_set_error( 'edd_auto_register_error_email_exists', apply_filters( 'edd_auto_register_error_email_exists', __( 'Email Address already in use', 'edd-auto-register' ) ) );
			}
			else {
				edd_unset_error( 'edd_auto_register_error_email_exists' );
			}
		}


		/**
		 * Error displayed when User must be logged in (Guest Checkout disabled) and there is no Register / Login Form enabled
		 *
		 * @since 1.0
		*/
		public function edd_error_must_log_in() {
			global $edd_options;

			// return if user is already logged in
			if ( is_user_logged_in() )
				return;

			if ( edd_no_guest_checkout() && !isset( $edd_options['show_register_form'] ) ) {
				edd_set_error( 'edd_auto_register_error_must_login', apply_filters( 'edd_auto_register_error_must_login', __( 'You must login to complete your purchase', 'edd-auto-register' ) ) );
			}
			else {
				edd_unset_error( 'edd_auto_register_error_must_login' );
			}

			edd_print_errors();
		}

		/**
		 * Can checkout?
		 * Prevents the form from being displayed when User must be logged in (Guest Checkout disabled), but "Show Register / Login Form?" is not
		 *
		 * @since 1.0
		*/
		public function can_checkout( $can_checkout ) {
			global $edd_options;
			
			if ( edd_no_guest_checkout() && !isset( $edd_options['show_register_form'] ) && ! is_user_logged_in() ) {
				return false;
			}

			return $can_checkout;
		}

		/**
		 * Gets the login fields for the login form on the checkout. This function hooks
		 * on the edd_purchase_form_login_fields to display the login form if a user already
		 * had an account.
		 *
		 * @since 1.0
		 * @return string
		 */
		public function login_form() {
			ob_start(); ?>
				<fieldset id="edd_login_fields">
					<span><legend><?php echo apply_filters( 'edd_auto_register_checkout_login_text', __( 'Login To Purchase', 'edd-auto-register' ) ); ?></legend></span>

					<?php do_action( 'edd_checkout_login_fields_before' ); ?>

					<p id="edd-user-login-wrap">
						<label class="edd-label" for="edd-username"><?php _e( 'Username', 'edd-auto-register' ); ?></label>
						<input class="<?php if(edd_no_guest_checkout()) { echo 'required '; } ?>edd-input" type="text" name="edd_user_login" id="edd_user_login" value="" placeholder="<?php _e( 'Your username', 'edd-auto-register' ); ?>"/>
					</p>

					<p id="edd-user-pass-wrap" class="edd_login_password">
						<label class="edd-label" for="edd-password"><?php _e( 'Password', 'edd-auto-register' ); ?></label>
						<input class="<?php if(edd_no_guest_checkout()) { echo 'required '; } ?>edd-input" type="password" name="edd_user_pass" id="edd_user_pass" placeholder="<?php _e( 'Your password', 'edd-auto-register' ); ?>"/>
						<input type="hidden" name="edd-purchase-var" value="needs-to-login"/>
					</p>

					<a href="<?php echo wp_lostpassword_url(); ?>" title="<?php _e( 'Lost Password?', 'edd-auto-register' ); ?>" target="_blank"><?php _e( 'Lost Password?', 'edd-auto-register' ); ?></a>

					<?php do_action( 'edd_checkout_login_fields_after' ); ?>

				</fieldset>

			<?php

			echo apply_filters( 'edd_auto_register_login_form', ob_get_clean() );
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
					'type' => 'header'
				),
				array(
					'id' => 'edd_auto_register_disable_user_email',
					'name' => __( 'Disable User Email', 'edd-auto-register' ),
					'desc' => __( 'Disables the email sent to the user that contains login details', 'edd-auto-register' ),
					'type' => 'checkbox'
				),
				array(
					'id' => 'edd_auto_register_disable_admin_email',
					'name' => __( 'Disable Admin Notification', 'edd-auto-register' ),
					'desc' => __( 'Disables the new user registration email sent to the admin', 'edd-auto-register' ),
					'type' => 'checkbox'
				),
			);

			return array_merge( $settings, $edd_ar_settings );
		}		
	}
}

/**
 * Loads a single instance of EDD Wish Lists
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