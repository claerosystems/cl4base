<?php defined('SYSPATH') or die('No direct script access.');

return array(
	'max_failed_login_count' => 5, // the maximum number of times a user can attempt to login before having to enter a captcha
	'failed_login_captcha_display' => 5, // the number of times a user can fail to login per session before they need to enter a captcha before logging in

	'session_key' => 'cl4_login', // the key in the session where the information such as the number of login attempts and forced captcha are stored

	// enables the functionality to store and re-post the get and post variables when a user times out
	'enable_timeout_post' => FALSE,
	// the key where the timeout post, get and path values are stored on timeout
	'timeout_post_session_key' => 'timeout_post',

	// auth types for logging purposes
	// used in auth_type_id in auth_log
	'auth_type' => array(
		'logged_in' => 1,
		'logged_out' => 2,
		'invalid_password' => 3,
		'invalid_username_password' => 4,
		'unknown_error' => 5,
		'too_many_attempts' => 6,
		'verifying_human' => 7,
	),

	// accounts that cannot have their password reset
	'admin_accounts' => array(),
);