<?php
/*
Plugin Name: WP Expire Passwords
Plugin URI: http://wordpress.org/plugins/wp-expire-passwords/
Description: This plugin allows you to set passwords to expire every X amount of days and to expire all user passwords.
Version: 1.1.1
Author: Rob DiVincenzo <rob.divincenzo@gmail.com>
Author URI: divi-designs.com
*/

if( get_option('days_until_expired') == '' ) {
	update_option('days_until_expired', 90 );
}

$days_until_expired = abs( (int) get_option('days_until_expired') );

// Register the EP settings
function EP_settings(){
	register_setting('EP_group_settings','days_until_expired');
}
// Initialize EP settings
add_action('admin_init','EP_settings');

// Initialize admin menu
function EP_admin_actions() {
	global $EP_options_page;
	$EP_options_page = add_options_page('Expire Password Settings', 'Expire Password Settings','manage_options', __FILE__, 'EP_admin_menu');
}
// Call to initialize admin menu
add_action('admin_menu', 'EP_admin_actions');

function EP_admin_menu(){
	global $days_until_expired;
?>
	<div class="wrap">
		<?php screen_icon();?>
		<h2>Expire Password Settings</h2>
		<form method="post" action="options.php">
			<?php settings_fields('EP_group_settings');?>
			<div>
				Automatically expire passwords after <input name="days_until_expired" size="1" maxlength="4" value="<?php echo $days_until_expired; ?>"/> days (default is 90 days)
			</div>
			<div>
				<input type="submit" class="button-primary" value="Update Settings"/>
			</div>
		</form>
		<br />
		<form id="expire_passwords_form" method="post" action="">
			<div>
				<input type="submit" class="button-primary" value="Expire Non-Super Admin Passwords"/>
			</div>
		</form>
		<div id="EP_response"></div>
	</div>
<?php 
}

// Reset password expiration lock
function EP_reset_password_expire( $user, $new_pass = false ) {
	// If the user_id is passed, get the user object
	if( is_numeric( $user ) ){
		// Get the user
		$user = get_userdata( $user );
	}

	// if new_pass is not passed, set the proper post since it is a password update and not password reset
	if( $new_pass === false ) {
		$new_pass = $_POST['pass1'];
	}

	// If the new password is the same as the old, do not reset password expiration
	if( $user && wp_check_password( $new_pass, $user->user_pass, $user->ID ) ) {
		return;
	} else {
		// Reset the password expiration
		EP_set_password_expire( $user );
	}
}

// Call to reset password expire when user edit's their own profile
add_action( 'personal_options_update', 'EP_reset_password_expire');

// Call to reset password expire when any other user edit's the profile password
add_action( 'edit_user_profile_update', 'EP_reset_password_expire');

// Call to reset password expire when any other user edit's the profile password
add_action( 'password_reset', 'EP_reset_password_expire');

// Set the password expire field
function EP_set_password_expire( $user ) {
	update_user_meta( $user->ID, 'EP_password_expires', time() );
}

// Check the password expire
function EP_check_password_expire( $user, $username, $password ) {
	global $days_until_expired;

	$password_expires = get_user_meta( $user->ID, 'EP_password_expires', true );
	// If the expires field is not set, set it.
	if ( empty( $password_expires ) ) {
		EP_set_password_expire( $user );
	} else {
		if ( ( ( ( time() - $password_expires )>= 60 * 60 * 24 * $days_until_expired  ) || ( $password_expires == 'manual_expire' ) ) && !is_super_admin( $user->ID ) ) {
			$user = new WP_Error( 'authentication_failed', sprintf( __( '<strong>ERROR</strong>: Password expired. You must <a href="%s">reset your password</a> to a new unique password to lift this lock.', 'EP_password_expires' ), site_url( 'wp-login.php?action=lostpassword', 'login' ) ) );
		}
	}
	return $user;
}
// Call to check password expiration on login
add_filter( 'authenticate', 'EP_check_password_expire', 30, 3 );

// Load scripts
function EP_load_scripts( $hook ){
	global $EP_options_page;

	if( $hook != $EP_options_page )
		return;

	wp_enqueue_script('ep-ajax', plugin_dir_url(__FILE__) . 'js/ep-ajax.js', array('jquery'));
}
// Call to load scripts
add_action('admin_enqueue_scripts','EP_load_scripts');

// Expire all user passwords
function EP_expire_users_passwords(){
	$wp_users = get_users('fields=ID');
	if( !empty( $wp_users) ){
		foreach($wp_users as $user=>$id){
			update_user_meta( $id, 'EP_password_expires', 'manual_expire');
		}
		echo '<p>User passwords have been expired.</p>';
	} else {
		echo '<p>There are no users.</p>';
	}
	die();
}
// Call to process ajax with the expire users function
add_action('wp_ajax_ep_expire_users','EP_expire_users_passwords');
?>