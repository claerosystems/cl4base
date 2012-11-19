<?php defined('SYSPATH') or die('No direct script access.');

/**
* This controller handles the features of add, edit, delete, etc. of database records
*/
class Controller_Cl4_Cl4Admin extends Controller_Base {
	protected $db_group; // the default database config to use, needed for when a specific model is not loaded
	protected $model_name; // the name of the model currently being manipulated
	protected $model_display_name; // the fulll, friendly object name as specified in the options or the model itself
	/**
	* @var  ORM  The model we are working with
	*/
	protected $target_object; // the actual model object for $model_name

	protected $id;
	// stores the values in the session for the current model (by reference)
	protected $model_session;
	protected $page_offset = 1;
	protected $search;
	protected $sort_column;
	protected $sort_order;
	protected $session_key;

	public $page = 'cl4admin';

	// true means users must be logged in to access this controller
	public $auth_required = TRUE;
	// secure actions is false because there is special functionality for cl4admin (see check_perm())
	//public $secure_actions = FALSE; leaving value as default

	/**
	* Runs before the action
	* Calls parent::before()
	*/
	public function before() {
		$action = Request::current()->action();

		parent::before();

		// set up the default database group
		$this->db_group = Kohana::$config->load('cl4admin.db_group');

		// assign the name of the default session array
		$this->session_key = Kohana::$config->load('cl4admin.session_key');

		// set the information from the route/get/post parameters
		$this->model_name = Request::current()->param('model');
		$this->id = Request::current()->param('id');
		$page_offset = CL4::get_param('page');
		$sort_column = CL4::get_param('sort_by_column');
		$sort_order = CL4::get_param('sort_by_order');

		// get the model list from the config file
		$model_list = $this->get_model_list();
		// get the default model and check if it's set or use the first model in the model list
		$default_model = $this->get_default_model();
		if (empty($default_model)) {
			$default_model = key($model_list);
		}

		$last_model = isset($this->session[$this->session_key]['last_model']) ? $this->session[$this->session_key]['last_model'] : NULL;

		// check to see if we haven't been passed a model name
		if (empty($this->model_name)) {
			// is there a last model stored in the session? then use it
			if ( ! empty($last_model)) {
				$go_to_model = $last_model;
			// if not, go the the default model if it's set
			} else {
				$go_to_model = $default_model;
			}

			// if there is no new model to go to, redirect them to the no access page
			if (empty($go_to_model)) {
				Request::current()->redirect('login/noaccess' . URL::array_to_query(array('referrer' => Request::current()->uri()), '&'));
			}

			Request::current()->redirect('dbadmin/' . $go_to_model . '/index');
		} // if

		// check to see the user has permission to access this action
		// determine what action we should use to determine if they have permission
		// get config and then check to see if the current action is defined in the array, otherwise use the action
		$action_to_perm = Kohana::$config->load('cl4admin.action_to_permission');
		$perm_action = Arr::get($action_to_perm, $action, $action);
		if ( ! $this->check_perm($perm_action)) {
			// we can't use the default functionality of secure_actions because we have 2 possible permissions per action: global and per model
			if ($action != 'index') {
				Message::message('cl4admin', 'no_permission_action', NULL, Message::$error);
				$this->redirect_to_index();
			} else if ($this->model_name != $default_model && ! empty($default_model)) {
				Message::message('cl4admin', 'no_permission_item', NULL, Message::$error);
				Request::current()->redirect('dbadmin/' . $default_model . '/index');
			} else {
				Request::current()->redirect('login/noaccess' . URL::array_to_query(array('referrer' => Request::current()->uri()), '&'));
			}
		} // if

		// redirect the user to a different model as they one they selected isn't valid (not in array of models)
		if ( ! isset($model_list[$this->model_name]) && ((CL4::is_dev() && $action != 'create' && $action != 'model_create') || ! CL4::is_dev())) {
			Message::message('cl4admin', 'model_not_defined', array(':model_name' => $this->model_name), Message::$debug);
			Request::current()->redirect('dbadmin/' . $default_model . '/index');
		}

		// the first time to the page or first time for this model, so set all the defaults
		// or the action is cancel search or search
		// or we are looking at a new model
		if ( ! isset($this->session[$this->session_key][$this->model_name])) {
			// set all the defaults for this model/object
			$this->session[$this->session_key][$this->model_name] = Kohana::$config->load('cl4admin.default_list_options');
		}

		$this->model_session =& $this->session[$this->session_key][$this->model_name];

		// check to see if anything came in from the page parameters
		// if we did, then set it in the session for the current model
		if ($page_offset !== NULL) $this->model_session['page'] = $page_offset;
		if ($sort_column !== NULL) $this->model_session['sort_by_column'] = $sort_column;
		if ($sort_order !== NULL) $this->model_session['sort_by_order'] = $sort_order;

		// set the values in object from the values in the session
		$this->page_offset = $this->model_session['page'];
		$this->sort_column = $this->model_session['sort_by_column'];
		$this->sort_order = $this->model_session['sort_by_order'];
		$this->search = ( ! empty($this->model_session['search']) ? $this->model_session['search'] : NULL);

		$this->session[$this->session_key]['last_model'] = $this->model_name;

		$this->add_admin_css();
	} // function before

	/**
	* Adds the CSS for cl4admin
	*/
	protected function add_admin_css() {
		if ($this->auto_render) {
			$this->template->styles['css/admin.css'] = NULL;
			$this->template->styles['css/dbadmin.css'] = NULL;
		}
	} // function add_admin_css

	/**
	* Stores the current values for page, search and sorting in the session
	*/
	public function after() {
		$this->model_session['page'] = $this->page_offset;
		$this->model_session['sort_by_column'] = $this->sort_column;
		$this->model_session['sort_by_order'] = $this->sort_order;
		$this->model_session['search'] = $this->search;

		parent::after();
	} // function after

	/**
	* Load the model
	*
	* @param  string  $mode  The mode to load the model in (view, edit, add, search, etc)
	*/
	protected function load_model($mode = 'view') {
		try {
			$orm_options = array(
				'mode' => $mode,
				'db_group' => $this->db_group,
			);

			Message::message('cl4admin', 'using_model', array(':model_name' => $this->model_name, ':mode' => $mode, ':id' => $this->id), Message::$debug);

			$this->target_object = ORM::factory($this->model_name, $this->id, $orm_options);
			if ($this->auto_render) $this->template->page_title = $this->target_object->_table_name_display . ' Administration' . $this->template->page_title;

			// generate the friendly model name used to display to the user
			$this->model_display_name = ( ! empty($this->target_object->_table_name_display) ? $this->target_object->_table_name_display : CL4::underscores_to_words($this->model_name));

			Message::message('cl4admin', 'model_loaded', array(':model_name' => $this->model_name), Message::$debug);

		} catch (Exception $e) {
			// display the error message
			Kohana_Exception::caught_handler($e);
			Message::message('cl4admin', 'problem_loading_data', NULL, Message::$error);
			Message::message('cl4admin', 'problem_loading_model', array(':model_name' => $this->model_name), Message::$debug);

			// display the help view
			if (CL4::is_dev() && $e->getCode() == 3001) {
				Message::message('cl4admin', 'model_dne', array(':model_name' => $this->model_name), Message::$debug);
				if ($this->auto_render && $this->model_name != key($model_list)) {
					Request::current()->redirect('dbadmin/' . key($model_list) . '/model_create?' . http_build_query(array('table_name' => $this->model_name)));
				}
			} else {
				// redirect back to the page and display the error
				Request::current()->redirect('dbadmin/' . key($model_list) . '/index');
			} // if
		} // try
	} // function load_model

	/**
	* The default action
	* Just displays the editable list using display_editable_list()
	*/
	public function action_index() {
		$this->display_editable_list();
	}

	/**
	* Display the editable list of records for the selected object.
	*
	* @param  array  $override_options  A list of options that can be set to override default behaviours.
	*/
	public function display_editable_list($override_options = array()) {
		// display the object / table select
		$view_content = $this->display_model_select();

		// set up the admin options
		$options = array(
			'mode' => 'view',
			'sort_by_column' => $this->sort_column,
			'sort_by_order' => $this->sort_order,
			'page_offset' => $this->page_offset,
			'in_search' => ( ! empty($this->search) || ! empty($this->sort_column)),
			'editable_list_options' => array(
				'per_row_links' => array(
					'view' => TRUE,     // view button
					'edit' => $this->check_perm('edit'),     // edit button
					'delete' => $this->check_perm('delete'),   // delete button
					'add' => $this->check_perm('add'),      // add (duplicate) button
					'checkbox' => ($this->check_perm('edit') || $this->check_perm('export')), // checkbox
				),
				'top_bar_buttons' => array(
					'add' => $this->check_perm('add'),             // add (add new) button
					'add_multiple' => $this->check_perm('add'),    // add multiple button
					'edit' => $this->check_perm('edit'),            // edit (edit selected) button
					'export_selected' => $this->check_perm('export'), // export selected button
					'export_all' => $this->check_perm('export'),      // export all button
					'search' => $this->check_perm('search'),          // search button
				),
			),
		);
		$options = Arr::merge($options, $override_options);

		try {
			$orm_multiple = new MultiORM($this->model_name, $options);

			// there is a search so apply it
			if ( ! empty($this->search)) {
				$orm_multiple->set_search($this->search);
			}

			$view_content .= $orm_multiple->get_editable_list($options);
		} catch (Exception $e) {
			Kohana_Exception::caught_handler($e);
			$view_content .= Kohana::message('cl4admin', 'problem_preparing');
		}

		$this->add_admin_view('', $view_content);
	} // function display_editable_list

	/**
	* Adds the admin view to $this->template->body_html, setting the title and content
	*
	* @param  string  $title    The title to use in the view
	* @param  string  $content  The content to put in the content container in the view
	*/
	protected function add_admin_view($title, $content) {
		$this->template->body_html .= View::factory('cl4/cl4admin/admin')
			->bind('title', $title)
			->bind('content', $content);
	} // function add_admin_view

	/**
	* Returns the page title based on a message file, merged with the display name of the model
	* Used in conjunction with add_admin_view()
	*
	* @param  string   $message_path  The path as used by Kohana::message() to the location of the message
	* @param  mixed    $display_name
	* @return string   The title of the page
	*/
	protected function get_page_title_message($message_path, $display_name = NULL) {
		if ($display_name === NULL) {
			$display_name = $this->model_display_name;
		}

		return __(Kohana::message('cl4admin', $message_path), array(':display_name' => HTML::chars($display_name)));
	} // function get_page_title_message

	/**
	* Cancel the current action by redirecting back to the index action
	*/
	public function action_cancel() {
		// add a notice to be displayed
		Message::message('cl4admin', 'action_cancelled', NULL, Message::$notice);
		// redirect to the index
		$this->redirect_to_index();
	} // function

	/**
	* Display an add form or add (save) a new record
	*/
	public function action_add() {
		$this->load_model('add');

		if ( ! empty($_POST)) {
			$this->save_model();
		}

		try {
			$view_title = $this->get_page_title_message('adding_item');

			// display the edit form
			$form_options = array(
				'mode' => 'add',
			);
			if ( ! empty($this->id)) {
				// set the form action because the current url includes the id of the record which will cause an update, not an add
				$form_options['form_action'] = URL::site(Request::current()->route()->uri(array('model' => $this->model_name, 'action' => 'add'))) . URL::query();
			}

			$view_content = $this->target_object->get_form($form_options);

			$this->add_admin_view($view_title, $view_content);
		} catch (Exception $e) {
			Kohana_Exception::caught_handler($e);
			Message::message('cl4admin', 'error_preparing_add', NULL, Message::$error);
			if ( ! CL4::is_dev()) $this->redirect_to_index();
		}
	} // function action_add

	/**
	* Display an edit form for a record or update (save) an existing record
	*/
	public function action_edit() {
		$this->load_model('edit');

		if ( ! empty($_POST)) {
			$this->save_model();
		}

		try {
			$view_title = $this->get_page_title_message('editing_item');
			$view_content = $this->target_object->get_form(array(
				'mode' => 'edit',
			));
			$this->add_admin_view($view_title, $view_content);
		} catch (Exception $e) {
			Kohana_Exception::caught_handler($e);
			Message::message('cl4admin', 'error_preparing_edit', NULL, Message::$error);
			if ( ! CL4::is_dev()) $this->redirect_to_index();
		} // try
	} // function action_edit

	/**
	* Used by add and edit to save (insert or update) the record
	*/
	public function save_model() {
		try {
			// save the record
			$custom_save_method = 'save_model_' . $this->model_name;
			if (method_exists($this, $custom_save_method)) {
				$this->$custom_save_method();
			} else {
				$this->target_object->save_values()->save();
			}
			Message::message('cl4admin', 'item_saved', NULL, Message::$notice);
			$this->redirect_to_index();
		} catch (ORM_Validation_Exception $e) {
			Message::message('cl4admin', 'values_not_valid', array(
				':validation_errors' => Message::add_validation_errors($e, $this->model_name)
			), Message::$error);
		} catch (Exception $e) {
			Kohana_Exception::caught_handler($e);
			Message::message('cl4admin', 'problem_saving', NULL, Message::$error);
			if ( ! CL4::is_dev()) $this->redirect_to_index();
		} // try
	} // function save_model

	/**
	* Views the record in a similar fashion to an edit, but without actual input fields
	*/
	public function action_view() {
		try {
			if ( ! ($this->id > 0)) {
				throw new Kohana_Exception('No ID received for view');
			}

			$this->load_model('view');

			$this->add_admin_view(HTML::chars($this->model_display_name), $this->target_object->get_view());
		} catch (Exception $e) {
			Kohana_Exception::caught_handler($e);
			Message::message('cl4admin', 'error_viewing', NULL, Message::$error);
			if ( ! CL4::is_dev()) $this->redirect_to_index();
		}
	} // function

	/**
	* Add and save/insert multiple records
	*/
	public function action_add_multiple() {
		try {
			// Create a new MuliORM for this model
			$orm_multiple = MultiORM::factory($this->model_name, array('mode' => 'add'));

			// If form was submitted
			if ( ! empty($_POST)) {
				try {
					$orm_multiple->save_values()->save();
					Message::message('cl4admin', 'multiple_saved', array(':records_saved' => $orm_multiple->records_saved()), Message::$notice);
					$this->redirect_to_index();
				} catch (ORM_Validation_Exception $e) {
					$validation_exceptions = $orm_multiple->validation_exceptions();
					foreach ($validation_exceptions as $num => $exception) {
						Message::message('cl4admin', 'values_not_valid_multiple', array(
							':record_number' => ($num + 1),
							':validation_errors' => Message::add_validation_errors($exception)
						), Message::$error);
					}
				} catch (Exception $e) {
					Kohana_Exception::caught_handler($e);
					Message::message('cl4admin', 'error_saving', NULL, Message::$error);
				}
			} // if

			// Set view details
			$view_title = $this->get_page_title_message('multiple_add_item', $orm_multiple->_table_name_display);

			// The count for the number of records were adding is stored in the ID field
			$count = Request::current()->param('id');
			$view_content = $orm_multiple->get_add_multiple($count);

			// Add view to template
			$this->add_admin_view($view_title, $view_content);
		} catch (Exception $e) {
			Kohana_Exception::caught_handler($e);
			Message::message('cl4admin', 'error_preparing_add', NULL, Message::$error);
			if ( ! CL4::is_dev()) $this->redirect_to_index();
		}
	} // function action_add_multiple

	/**
	* Edit and save/update multiple records
	*/
	public function action_edit_multiple() {
		try {
			// set up the admin options
			$orm_multiple = MultiORM::factory($this->model_name, array('mode' => 'edit'));

			if (empty($_POST['ids'])) {
				$ids = NULL;

				try {
					$orm_multiple->save_values()->save();
					Message::message('cl4admin', 'multiple_saved', array(':records_saved' => $orm_multiple->records_saved()), Message::$notice);
					$this->redirect_to_index();
				} catch (ORM_Validation_Exception $e) {
					$validation_exceptions = $orm_multiple->validation_exceptions();
					foreach ($validation_exceptions as $num => $exception) {
						Message::message('cl4admin', 'values_not_valid_multiple', array(
							':record_number' => ($num + 1),
							':validation_errors' => Message::add_validation_errors($exception)
						), Message::$error);
					}
				} catch (Exception $e) {
					Kohana_Exception::caught_handler($e);
					Message::message('cl4admin', 'error_saving', NULL, Message::$error);
				}
			} else {
				$ids = $_POST['ids'];
			} // if

			$view_title = $this->get_page_title_message('multiple_edit_item', $orm_multiple->_table_name_display);
			$view_content = $orm_multiple->get_edit_multiple($ids);
			$this->add_admin_view($view_title, $view_content);
		} catch (Exception $e) {
			Kohana_Exception::caught_handler($e);
			Message::message('cl4admin', 'error_preparing_edit', NULL, Message::$error);
			if ( ! CL4::is_dev()) $this->redirect_to_index();
		}
	} // function action_edit_multiple

	/**
	* Delete a record with a confirm first
	*/
	public function action_delete() {
		try {
			if ( ! ($this->id > 0)) {
				Message::message('cl4admin', 'no_id', NULL, Message::$error);
				$this->redirect_to_index();
			} // if

			$this->load_model();

			if ( ! empty($_POST)) {
				// see if they want to delete the item
				if (strtolower($_POST['cl4_delete_confirm']) == 'yes') {
					try {
						if ($this->target_object->delete() == 0) {
							Message::message('cl4admin', 'no_item_deleted', NULL, Message::$error);
						} else {
							Message::message('cl4admin', 'item_deleted', array(':display_name' => HTML::chars($this->model_display_name)), Message::$notice);
							Message::message('cl4admin', 'record_id_deleted', array(':id' => $this->id), Message::$debug);
						} // if
					} catch (Exception $e) {
						Kohana_Exception::caught_handler($e);
						Message::message('cl4admin', 'error_deleting', NULL, Message::$error);
						if ( ! CL4::is_dev()) $this->redirect_to_index();
					}
				} else {
					Message::message('cl4admin', 'item_not_deleted', NULL, Message::$notice);
				}

				$this->redirect_to_index();

			} else {
				// the confirmation form goes in the messages
				Message::add(View::factory('cl4/cl4admin/confirm_delete', array(
					'object_name' => $this->model_display_name,
				)));

				$this->add_admin_view(HTML::chars($this->model_display_name), $this->target_object->get_view());
			}
		} catch (Exception $e) {
			Kohana_Exception::caught_handler($e);
			Message::message('cl4admin', 'error_preparing_delete', NULL, Message::$error);
			if ( ! CL4::is_dev()) $this->redirect_to_index();
		}
	} // function action_delete

	/**
	* Download a file attached to a record, private or public
	* Don't output the HTML header or footer (auto_render = FALSE)
	* Will display a message if there is a problem
	*/
	public function action_download() {
		$this->auto_render = FALSE;

		try {
			// get the target column
			$column_name = Request::current()->param('column_name');

			$this->load_model();

			// get the target table name
			$table_name = $this->target_object->table_name();

			// load the record
			if ( ! ($this->id > 0)) {
				throw new Kohana_Exception('No record ID was received, therefore no file could be downloaded');
			} // if

			// get the file name
			$filename = $this->target_object->$column_name;

			// check to see if the record has a filename
			if ( ! empty($filename)) {
				$this->target_object->send_file($column_name);

			} else if (empty($filename)) {
				echo Kohana::message('cl4admin', 'no_file');
				throw new Kohana_Exception('There is no file associated with the record');
			} // if
		} catch (Exception $e) {
			Kohana_Exception::caught_handler($e);
			echo Kohana::message('cl4admin', 'problem_downloading');
		}
	} // function download

	/**
	 * Exports the records, either all or checked using MultiORM.
	 * Generates either a PHPExcel file (if available) or CSV otherwise.
	 */
	public function action_export() {
		$this->auto_render = FALSE;

		try {
			$this->load_model('add');

			// set up the admin options
			$options = array(
				'mode' => 'view',
				'sort_by_column' => $this->sort_column,
				'sort_by_order' => $this->sort_order,
				'in_search' => ( ! empty($this->search) || ! empty($this->sort_column)),
			);

			$orm_multiple = new MultiORM($this->model_name, $options);

			// there is a search so apply it
			if ( ! empty($this->search)) {
				$orm_multiple->set_search($this->search);
			}

			if ( ! Arr::get($_REQUEST, 'export_all', FALSE)) {
				$ids = (array) Arr::get($_REQUEST, 'ids', array());
				if ( ! empty($ids)) {
					$orm_multiple->set_ids($ids);
				}
			}

			$export_result = $orm_multiple->get_export();

			$output_name = URL::title($this->model_display_name) . '-' . date('YmdHis');

			// is an XLSX file generated by PHPExcel
			if (get_class($export_result) == 'PHPExcel') {
				$temp_xls_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cl4admin_export-' . Auth::instance()->get_user()->id . '-' . date('YmdHis') . '.xlsx';
				$output = PHPExcel_IOFactory::createWriter($export_result, 'Excel2007');
				$output->save($temp_xls_file);

				$this->request->response()->send_file($temp_xls_file, $output_name . '.xlsx', array('delete' => TRUE));

			// is a CSV
			} else {
				$export_result->close_csv()
					->get_csv($output_name . '.csv');
			}
		} catch (Exception $e) {
			Kohana_Exception::caught_handler($e);
			echo Kohana::message('cl4admin', 'error_exporting');
		}
	} // function action_export

	/**
	* Prepares the search form
	*/
	public function action_search() {
		try {
			$this->load_model('search');

			if ( ! empty($_POST)) {
				// send the user back to page 1
				$this->page_offset = 1;
				// store the post (the search) in the session and the object
				$this->search = $this->model_session['search'] = $_POST;

				// redirect to the index page so the nav will work properly
				$this->redirect_to_index();

			} else {
				$view_title = $this->get_page_title_message('search');
				$view_content = $this->target_object->get_form(array(
					'mode' => 'search',
				));
				$this->add_admin_view($view_title, $view_content);
			}
		} catch (Exception $e) {
			Kohana_Exception::caught_handler($e);
			Message::message('cl4admin', 'error_preparing_search', NULL, Message::$error);
			if ( ! CL4::is_dev()) $this->redirect_to_index();
		}
	} // function

	/**
	* Clears the search from the session and redirects the user to the index page for the model
	*/
	public function action_cancel_search() {
		try {
			// reset the search and search in the session
			$this->model_session = Kohana::$config->load('cl4admin.default_list_options');

			$this->redirect_to_index();
		} catch (Exception $e) {
			Kohana_Exception::caught_handler($e);
			Message::message('cl4admin', 'error_clearing_search', NULL, Message::$error);
			if ( ! CL4::is_dev()) $this->redirect_to_index();
		}
	} // function action_cancel_search

	/**
	* Checks the permission based on action and the cl4admin controller
	* The 3 possible permissions are cl4admin/ * /[action] (no spaces around *) or cl4admin/[model name]/[action] or cl4admin/[model name]/ * (no spaces around *)
	*
	* @param 	string		$action		The action (permission) to check for; if left as NULL, the current action will be used
	* @param	string		$model_name	The model name to use in the check; if left as NULL, the current model will be used
	* @return 	bool
	*/
	public function check_perm($action = NULL, $model_name = NULL) {
		if ($action === NULL) {
			$action = Request::current()->action();
		}
		if ($model_name === NULL) {
			$model_name = $this->model_name;
		}

		$auth = Auth::instance();

		if ($action != 'model_create') {
			// check if the user has access to all the models or access to this specific model
			return ($auth->logged_in('cl4admin/*/' . $action) || $auth->logged_in('cl4admin/' . $model_name . '/' . $action) || $auth->logged_in('cl4admin/' . $model_name . '/*'));
		} else {
			return $auth->logged_in('cl4admin/model_create');
		}
	} // function

	/**
	* Creates a drop down of all model the available models as returned by get_model_list()
	* Returns the view cl4/cl4admin/header
	*
	* @return  string
	*/
	public function display_model_select() {
		// display the list of tables and the default table data
		try {
			$model_list = $this->get_model_list();
			asort($model_list);
			$model_select = Form::select('model', $model_list, $this->model_name, array('id' => 'cl4_model_select'));

			$return_html = View::factory('cl4/cl4admin/header', array(
				'model_select' => $model_select,
				'form_action' => URL::site(Request::current()->uri()) . URL::query(),
			));
		} catch (Exception $e) {
			Kohana_Exception::caught_handler($e);
			// return an empty string because there is no proper message that can be displayed
			$return_html = '';
		}

		return $return_html;
	} // function

	/**
	* grab the model list from the cl4admin config file
	*
	*/
	public function get_model_list() {
		$model_list = Kohana::$config->load('cl4admin.model_list');
		if ($model_list === NULL) $model_list = array();

		// remove any models that have name that are empty (NULL, FALSE, etc)
		// or that the user doesn't have permission to see the list of records (index)
		foreach ($model_list as $model => $name) {
			if (empty($name) || ! $this->check_perm('index', $model)) unset($model_list[$model]);
		}

		return $model_list;
	} // function

	/**
	* Gets the default model from the config file
	* Returns the model name
	*
	* @return string
	*/
	public function get_default_model() {
		return Kohana::$config->load('cl4admin.default_model');
	}

	/**
	* Redirects the user to the index for the current model based on the current route
	*/
	function redirect_to_index() {
		try {
			Request::current()->redirect('/' . Route::get(Route::name(Request::current()->route()))->uri(array('model' => $this->model_name, 'action' => 'index')));
		} catch (Exception $e) {
			Kohana_Exception::caught_handler($e);
		}
	} // function

	/*****************************
	* The following functions are for actions that should only be performed by a programmer
	******************************/

	/**
	* *** For programmer use ***
	* Generates the page with a table list, some JS and a textarea for the generated PHP for a model
	*/
	public function action_model_create() {
		try {
			$db_group = CL4::get_param('db_group', Database::$default);

			$this->template->body_html = View::factory('cl4/cl4admin/model_create')
				->set('table_name', CL4::get_param('table_name'))
				->set('db_group', $db_group)
				->bind('db_list', $db_list)
				->bind('table_list', $table_list);

			$table_list = Database::instance($db_group)->list_tables();
			$table_list = array_combine($table_list, $table_list);

			$db_list = array_keys((array) Kohana::$config->load('database'));
			$db_list = array_combine($db_list, $db_list);

			$this->template->scripts['model_create'] = 'cl4/js/model_create.js';
		} catch (Exception $e) {
			Kohana_Exception::caught_handler($e);
			Message::message('cl4admin', 'error_preparing_create', NULL, Message::$error);
		}
	} // function action_model_create

	/**
	* *** For programmer use ***
	* Runs ModelCreate::create_model(); adds what is returned to the the request->response and turns off auto render so we don't get the extra HTML from the template
	*/
	public function action_create() {
		try {
			// we don't want the template controller automatically adding all the html
			$this->auto_render = FALSE;

			$db_group = CL4::get_param('db_group', Database::$default);

			// generate a sample model file for the given table based on the database definition
			$this->response->body(ModelCreate::create_model($this->model_name, array(
					'db_group' => $db_group,
				)));
		} catch (Exception $e) {
			Kohana_Exception::caught_handler($e);
			echo Kohana::message('cl4admin', 'error_creating');
		}
	} // function
} // class