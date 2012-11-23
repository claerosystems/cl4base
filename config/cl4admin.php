<?php defined('SYSPATH') or die('No direct script access.');

return array(
	// default options for cl4admin controller
	'default_list_options' => array(
		'sort_by_column' => NULL, // orm defaults to primary key
		'sort_by_order' => NULL, // orm defaults to DESC
		'page' => 1,
		'search' => NULL,
	),
	'session_key' => 'cl4_admin',
	// default database group to use when a specific model is not loaded, or if the model does not specify a db
	'db_group' => NULL,
	/**
	* Model list to be used in cl4admin
	* An array of model names (keys) and display names (values)
	* Set the display name to an empty value to disable it (NULL, FALSE, etc)
	* The first one will be used as the default or when there is an attempt to access one that doesn't exist
	* The list will be sorted by the display name (value) before being displayed
	*/
	'model_list' => array(
		// model name => display name
		'User_Admin' => 'User',
		'Auth_Log' => 'Auth Log',
		'Group' => 'Group',
		'Group_Permission' => 'Group - Permission',
		'Permission' => 'Permission',
		'User_Group' => 'User - Group',
	),
	'default_model' => NULL, // used to determine which model to display by default; if set to null, the default will be the model in model_list
	// an array of actions that shouldn't be used in permission checking (because it saves on a lot of extra permissions)
	'action_to_permission' => array(
		'cancel' => 'index',
		'cancel_search' => 'index',
		'download' => 'index',
		'add_multiple' => 'add',
		'edit_multiple' => 'edit',
		'create' => 'model_create',
	),
);