=== EDD Auto Register ===
Contributors: sumobi
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=EFUPMPEZPGW7L
Tags: easy digital downloads, digital downloads, e-downloads, edd, sumobi, purchase, auto, register, registration, e-commerce
Requires at least: 3.3
Tested up to: 3.9 alpha
Stable tag: 1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Automatically creates a WP user account at checkout, based on customer's email address.

== Description ==

This plugin now requires [Easy Digital Downloads](http://wordpress.org/extend/plugins/easy-digital-downloads/ "Easy Digital Downloads") v1.9 or greater. 

[View the live demo](http://edd-auto-register.sumobithemes.com/downloads/test-download/ "Live Demo")

Once activated, EDD Auto Register will create a WordPress user account for your customer at checkout, without the need for the customer to enter any additional information. This eliminates the need for the default EDD registration form, and drastically reduces the time it takes your customers to complete their purchase.

There are various filters available for developers, see the FAQ tab for more information.

If EDD's "Disable Guest Checkout" is enabled, the plugin loads it's own error message that makes more sense to the plugin. Also, if Edd's "Show Register / Login Form?" is enabled, the plugin will load it's own simple version of the login form.

**More add-ons for Easy Digital Downloads**

You can find more add-ons (both free and commercial) from [Easy Digital Downloads' website](https://easydigitaldownloads.com/extensions/?ref=166 "Easy Digital Downloads")

**Free theme for Easy Digital Downloads**

[http://sumobi.com/shop/shop-front/](http://sumobi.com/shop/shop-front/ "Shop Front")

Shop Front was designed to be simple, responsive and lightweight. It has only the bare essentials, making it the perfect starting point for your next digital e-commerce store. It’s also easily extensible with a growing collection of add-ons to enhance the functionality and styling.

**Stay up to date**

*Become a fan on Facebook* 
[http://www.facebook.com/sumobicom](http://www.facebook.com/sumobicom "Facebook")

*Follow me on Twitter* 
[http://twitter.com/sumobi_](http://twitter.com/sumobi_ "Twitter")

== Installation ==

1. Unpack the entire contents of this plugin zip file into your `wp-content/plugins/` folder locally
1. Upload to your site
1. Navigate to `wp-admin/plugins.php` on your site (your WP Admin plugin page)
1. Activate this plugin
1. That's it! user accounts will automatically be created for your customers when they purchase your product for the first time and their login details will be emailed to them

OR you can just install it with WordPress by going to Plugins >> Add New >> and type this plugin's name


== Frequently Asked Questions ==

= How can I modify some of the key aspects of the plugin? =

There are filters available to modify the behaviour of the plugin, see the list below:

1. edd_auto_register_email_subject
1. edd_auto_register_headers
1. edd_auto_register_insert_user_args
1. edd_auto_register_email_body
1. edd_auto_register_error_must_login
1. edd_auto_register_login_form

= Can you provide a filter example of how to change the email's subject? =

Add the following to your child theme's functions.php

    function my_child_theme_edd_auto_register_email_subject( $subject ) {

        // enter your new subject below
	    $subject = 'Here are your new login details';

	    return $subject;

    }
    add_filter( 'edd_auto_register_email_subject', 'my_child_theme_edd_auto_register_email_subject' );


= Can you provide a filter example of how to change the email's body? =

Add the following to your child theme's functions.php

	function my_child_theme_edd_auto_register_email_body( $default_email_body, $first_name, $username, $password ) {

		// Modify accordingly
		$default_email_body = __( "Dear", "edd-auto-register" ) . ' ' . $first_name . ",\n\n";
		$default_email_body .= __( "Below are your login details:", "edd-auto-register" ) . "\n\n";
		$default_email_body .= __( "Your Username:", "edd-auto-register" ) . ' ' . $username . "\n\n";
		$default_email_body .= __( "Your Password:", "edd-auto-register" ) . ' ' . $password . "\n\n";
		$default_email_body .= __( "Login:", "edd-auto-register" ) . ' ' . wp_login_url() . "\r\n";

		return $default_email_body;

	}
	add_filter( 'edd_auto_register_email_body', 'my_child_theme_edd_auto_register_email_body', 10, 4 );

= How can I disable the email from sending to the customer? =

There's an option under downloads &rarr; settings &rarr; extensions

== Screenshots ==

1. The standard purchase form which will create a user account from the customer's Email Address
1. The plugin's simple login form when both "Disable Guest Checkout" and "Show Register / Login Form?" are enabled
1. The error message that shows when "Disable Guest Checkout" is enabled, but "Show Register / Login Form?" is not

== Upgrade Notice ==

= 1.1 =
Requires EDD 1.9 or greater. Will not work with older versions. User account creation now closely mimics that of EDD meaning a user account will be created no matter what payment gateway is used.

== Changelog ==

= 1.1 =
* New: User account creation now closely mimics that of EDD core meaning a user account will be created no matter what payment gateway is used
* New: "Lost Password?" link added to "login to purchase" form
* New: Setting to disable the admin notification
* New: Setting to disable the user notification
* New: edd_auto_register_insert_user_args filter. This can be used to do things such as modify the default role of the user when they are created
* Tweak: If a user who previously had an account returns to make a purchase it will no longer display "Email Address already in use". Instead it will be treated as a guest purchase
* Tweak: Email sent to user now includes login URL
* Tweak: Major code overhaul
* Tweak: New user email no longer uses the default EDD receipt template so it's not styled like a receipt if you have a custom template.

= 1.0.2 =
* New: Adding custom translations is now easier by adding them to the wp-content/languages/edd-auto-register folder
* New: Spanish and Catalan translations. Thanks to Joan Boluda!
* Fix: Undefined index errors when form was submitted without email address
* Fix: Text strings not being translated properly in registration email

= 1.0.1 =
* Fixed filter names for error messages

= 1.0 =
* Initial release