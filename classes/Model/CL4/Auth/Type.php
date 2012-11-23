<?php defined('SYSPATH') or die('No direct access allowed.');
/**
 * Default permission
 */
class Model_CL4_Auth_Type extends ORM {
	protected $_table_names_plural = FALSE;
	protected $_table_name = 'auth_type';
	public $_table_name_display = 'Auth Type';

	protected $_belongs_to = array(
		'auth_log' => array(
			'model' => 'auth_log'
		)
	);

	// sorting
	protected $_sorting = array(
		'display_order' => 'ASC',
		'name' => 'ASC',
	);

	// column definitions
	protected $_table_columns = array(
		/**
		* see http://v3.kohanaphp.com/guide/api/Database_MySQL#list_columns for all possible column attributes
		* see the modules/cl4/config/cl4orm.php for a full list of cl4-specific options and documentation on what the options do
		*/
		'id' => array(
			'field_type' => 'Hidden',
			'list_flag' => FALSE,
			'edit_flag' => TRUE,
			'search_flag' => FALSE,
			'view_flag' => FALSE,
			'is_nullable' => FALSE,
		),
		'name' => array(
			'field_type' => 'Text',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'is_nullable' => FALSE,
			'field_attributes' => array(
				'maxlength' => 30,
			),
		),
		'display_order' => array(
			'field_type' => 'Text',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'is_nullable' => FALSE,
			'field_attributes' => array(
				'maxlength' => 6,
				'size' => 6,
			),
		),
	);

	/**
	 * @var array $_display_order The order to display columns in, if different from as listed in $_table_columns.
	 * Columns not listed here will be added beneath these columns, in the order they are listed in $_table_columns.
	 */
	protected $_display_order = array(
		10 => 'id',
		20 => 'name',
		30 => 'display_order',
	);

	/**
	 * Labels for columns
	 *
	 * @return  array
	 */
	public function labels() {
		return array(
			'id' => 'ID',
			'name' => 'Name',
			'display_order' => 'Display Order',
		);
	}
}