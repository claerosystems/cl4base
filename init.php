<?php defined('SYSPATH') or die('No direct script access.');

$routes = Kohana::$config->load('cl4.routes');

if ($routes['login']) {
	// login page
	Route::set('login', 'login(/<action>)', array('action' => '[a-z_]{0,}',))
		->defaults(array(
			'controller' => 'Login',
			'action' => NULL,
	));
}

if ($routes['account']) {
	// account: profile, change password, forgot, register
	Route::set('account', 'account(/<action>)', array('action' => '[a-z_]{0,}',))
	->defaults(array(
		'controller' => 'Account',
		'action' => 'index',
	));
}

if ($routes['cl4admin']) {
	// claero admin
	// Most cases: /dbadmin/user/edit/2
	// Special case for download: /dbadmin/demo/download/2/public_filename
	// Special case for add_multiple: /dbadmin/demo/add_mulitple/5 (where 5 is the number of records to add)
	Route::set('cl4admin', 'dbadmin(/<model>(/<action>(/<id>(/<column_name>))))', array(
		'model' => '[a-zA-Z0-9_]{0,}',
		'action' => '[a-z_]+',
		'id' => '\d+',
		'column_name' => '[a-z_]+')
	)->defaults(array(
		'controller' => 'CL4Admin',
		'model' => NULL, // this is the default object that will be displayed when accessing cl4admin (dbadmin) without a model
		'action' => 'index',
		'id' => NULL,
		'column_name' => NULL,
	));
}