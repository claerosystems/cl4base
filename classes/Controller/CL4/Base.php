<?php defined('SYSPATH') or die('No direct script access.');

/**
 * A default base Controller class.
 * Some of the functionality is required by cl4 and other modules.
 */
class Controller_CL4_Base extends Controller_Template {
	/**
	 * The template to use. The string is replaced with the View in before().
	 * @var  View
	 */
	public $template = 'base/template'; // this is the default template file

	/**
	 * The current page. Adds a class to the body, ie, "p_page_name".
	 * @var  string
	 */
	public $page;

	/**
	 * Controls access for the whole controller.
	 * If the entire controller REQUIRES that the user be logged in, set this to TRUE.
	 * If some or all of the controller DOES NOT need to be logged in, set to this FALSE; to control which actions require authentication or a specific permission, us the $secure_actions array.
	 */
	public $auth_required = FALSE;

	/**
	 * Controls access for separate actions
	 *
	 * Examples:
	 * not set (FALSE) => when $auth_required is TRUE, then it will be considered a secure action, but will only require that the user is logged in
	 *            when $auth_required is FALSE, then everyone will have access to the action
	 * 'list' => FALSE the list action does not require the user to be logged in (the following are all the same as FALSE: "", 0, "0", NULL, array() (empty array))
	 * 'profile' => TRUE allows any logged in user to access that action
	 * 'adminpanel' => 'admin' will only allow users with the permission admin to access action_adminpanel
	 * 'moderatorpanel' => array('login', 'moderator') will only allow users with the permissions login AND moderator to access action_moderatorpanel
	 */
	public $secure_actions = FALSE;

	/**
	 * An array of actions as found in the request that shouldn't use auto_render.
	 * @var  array
	 */
	protected $no_auto_render_actions = array();

	/**
	 * If the messages should be added to the template.
	 * @var  boolean
	 */
	protected $display_messages = TRUE;

	/**
	 * Array of scripts, keyed by name with value of array containing keys path, media and array of required styles before adding this style.
	 * Add to using add_style().
	 * @var  array
	 */
	protected $styles = array();

	/**
	 * Array of scripts, keyed by name with value of array containing keys path and array of required scripts before adding this script.
	 * Add to using add_script().
	 * @var  array
	 */
	protected $scripts = array();

	/**
	 * String of on load JavaScript.
	 * @var  array
	 */
	protected $on_load_js = '';

	/**
	 * Automatically executed before the controller action. Can be used to set
	 * class properties, do authorization checks, and execute other custom code.
	 * Disabled auto render if the action is in the no_auto_render_actions array.
	 * Checks to see if the site is currently unavailable and then throws a 503.
	 * Checks the login based on the auth_required and secure_actions properties.
	 * Initializes the template.
	 *
	 * @return  void
	 */
	public function before() {
		if (in_array($this->request->action(), $this->no_auto_render_actions)) {
			$this->auto_render = FALSE;
		}

		// if the site is unavailable, redirect the user to the unavailable page
		if (defined('UNAVAILABLE_FLAG') && UNAVAILABLE_FLAG) {
			throw HTTP_Exception(503, __('The site is currently unavailable.'));
		}

		parent::before();

		$this->check_login();

		// set up the default template values for the base template
		$this->initialize_template();
	} // function before

	/**
	 * Automatically executed after the controller action. Can be used to apply
	 * transformation to the request response, add extra output, and execute
	 * other custom code.
	 * Completes the setup of the template.
	 *
	 * @return  void
	 */
	public function after() {
		if ($this->auto_render) {
			// add a body class for page
			if ( ! empty($this->page)) {
				$this->template->body_class .= ' p_' . $this->page;
			}

			if ( ! empty($this->styles)) {
				$this->template->styles = $this->compile_styles();
			} // if

			if ( ! empty($this->scripts)) {
				$this->template->scripts = $this->compile_scripts();
			}

			$this->template->on_load_js = $this->on_load_js;

			// look for any status message and display
			if ($this->display_messages) {
				$this->template->message = Message::display();
			}

			if (CL4::is_dev()) {
				// this is so a session isn't started needlessly when in debug mode
				$this->template->session = Session::instance()->as_array();
			}
		} // if

		parent::after();
	} // function after

	/**
	 * Setup the default template values.
	 *
	 * @return void
	 */
	protected function initialize_template() {
		if ($this->auto_render) {
			// Initialize default values
			$this->template->logged_in = Auth::instance()->logged_in();
			if ($this->template->logged_in) {
				$this->template->user = Auth::instance()->get_user();
			}

			$this->add_template_styles()
				->add_template_js();

			// set some empty variables
			$this->template->page_title = '';
			$this->template->meta_tags = array();
			$this->template->body_class = '';
			$this->template->pre_message = '';
			$this->template->body_html = '';
		} // if
	}

	/**
	 * Checks if the user is logged in and if they have permissions to the current action
	 * If the user is not logged in, then they are redirected to the timed out page or login page
	 * If the user is logged in, but not allowed, then they are sent to the no access page
	 * If they are logged in and have access, then it will updat the timestamp in the session
	 * If c_ajax == 1, then a JSON string will be returned instead, using AJAX_Status and it's constants
	 *
	 * @return  Controller_Base
	 */
	public function check_login() {
		// ***** Authentication *****
		// check to see if they are allowed to access the action
		if ( ! Auth::instance()->controller_allowed($this, $this->request->action())) {
			$is_ajax = (bool) Arr::get($_REQUEST, 'c_ajax', FALSE);

			if (Auth::instance()->logged_in()) {
				// user is logged in but not allowed to access the page/action
				if ($is_ajax) {
					echo AJAX_Status::ajax(array(
						'status' => AJAX_Status::NOT_ALLOWED,
						'debug_msg' => 'Referrer: ' . $this->request->uri(),
					));
					exit;
				} else {
					$this->redirect(Route::get('login')->uri(array('action' => 'noaccess')) . $this->get_login_redirect_query());
				}
			} else {
				if (Auth::instance()->timed_out()) {
					if ($is_ajax) {
						echo AJAX_Status::ajax(array(
							'status' => AJAX_Status::TIMEDOUT,
						));
						exit;
					} else {
						// store the get and post if timeout post is enabled
						$this->process_timeout();

						// display password page because the sesion has timeout
						$this->redirect(Route::get('login')->uri(array('action' => 'timedout')) . $this->get_login_redirect_query());
					}
				} else {
					if ($is_ajax) {
						// just not logged in and is ajax so return a json array with the status of not logged in
						echo AJAX_Status::ajax(array(
							'status' => AJAX_Status::NOT_LOGGED_IN,
						));
						exit;
					} else {
						// just not logged in, so redirect them to the login with a redirect parameter back to the current page
						$this->redirect(Route::get('login')->uri() . $this->get_login_redirect_query());
					}
				}
			} // if
		} // if

		if (Auth::instance()->logged_in() && $this->auto_render) {
			// update the session auth timestamp
			Auth::instance()->update_timestamp();
		} // if

		return $this;
	} // function check_login

	/**
	 * Returns the query containing the redirect for the login controller.
	 * Used within the check_login() method to pass the redirect through the login action/controller.
	 * Bases the redirect on current URL/URI and the full get/query string.
	 *
	 * @return  string
	 */
	protected function get_login_redirect_query() {
		return URL::array_to_query(array('redirect' => $this->request->uri() . '?' . http_build_query($_GET)), '&');
	}

	/**
	 * If the login timeout post functionality is enabled, this will store the passed
	 * GET and POST in the session key for use in Controller_CL4_Login to re-post the data.
	 * If there is no get or post, this will unset the session key
	 *
	 * @return  void
	 */
	protected function process_timeout() {
		if (Kohana::$config->load('cl4login.enable_timeout_post')) {
			// store the post so we can post it again after the user enters their password
			$timeout_post_session_key = Kohana::$config->load('cl4login.timeout_post_session_key');
			$query = $this->request->query();
			$post = $this->request->post();
			if ( ! empty($query) || ! empty($post)) {
				Session::instance()->set($timeout_post_session_key, array(
					'post_to' => $this->request->uri(),
					'get' => $query,
					'post' => $post,
				));
			} else {
				Session::instance()->delete($timeout_post_session_key);
			}
		} // if
	} // function process_timeout

	/**
	 * Adds the base style, which is the compiled version of the SASS files.
	 *
	 * @return  Controller_Base
	 */
	public function add_template_styles() {
		$this->add_style('base', 'css/base.css');

		return $this;
	} // function add_template_styles

	/**
	 * Sets up the template script var, add's modernizr, jquery, jquery ui, cl4.js and base.js if they are not already set.
	 *
	 * @return  Controller_Base
	 */
	public function add_template_js() {
		$this->add_script('jquery', '//ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js')
			->add_script('jquery_ui', '//ajax.googleapis.com/ajax/libs/jqueryui/1.9.2/jquery-ui.min.js');
		if (DEBUG_FLAG) {
			$this->add_script('xm_debug', 'xm/js/debug.js');
		}
		$this->add_script('jquery_outside', 'js/jquery.outside.min.js')
			->add_script('cl4', 'cl4/js/cl4.js')
			->add_script('cl4_ajax', 'cl4/js/ajax.js')
			->add_script('base', 'js/base.js');

		return $this;
	} // function add_template_js

	/**
	 * Adds JavaScript to the template on_load_js var, including checking to see if there should be a line break before the addition.
	 *
	 * @param  string  $js  The javascript to add
	 * @return  Controller_Base
	 */
	public function add_on_load_js($js) {
		if ( ! empty($this->on_load_js)) {
			$this->on_load_js .= "\n";
		}
		$this->on_load_js .= $js;

		return $this;
	} // function add_on_load_js

	/**
	 * Adds a CSS file.
	 * If the name already exists, the file will not be added.
	 *
	 * @param  string  $name      The name of the CSS file.
	 * @param  string  $path      The path to the CSS file.
	 * @param  string  $media     The media type. NULL for all/none.
	 * @param  array   $required  Array of CSS file names that are required before this one can be loaded.
	 * @return  Controller_Base
	 */
	protected function add_style($name, $path, $media = NULL, $required = array()) {
		if ( ! isset($this->styles[$name])) {
			$this->styles[$name] = array(
				'path' => $path,
				'media' => $media,
				'required' => $required,
			);
		}

		return $this;
	}

	/**
	 * Adds a script file.
	 * If the name already exists, the file will not be added.
	 *
	 * @param  string  $name      The name of the script file.
	 * @param  string  $path      The path to the script file.
	 * @param  array   $required  Array of script file names that are required before this one can be loaded.
	 * @return  Controller_Base
	 */
	protected function add_script($name, $path, $required = array()) {
		if ( ! isset($this->scripts[$name])) {
			$this->scripts[$name] = array(
				'path' => $path,
				'required' => $required,
			);
		}

		return $this;
	}

	/**
	 * Compiles the array of styles with the key being the path and the value being the media type
	 * ordering the array based on the required scripts.
	 *
	 * @return  array
	 */
	protected function compile_styles() {
		$styles = $this->compile_style_script($this->styles);

		// create the array for use in the template
		$final_styles = array();
		foreach ($styles as $data) {
			$final_styles[$data['path']] = $data['media'];
		}

		return $final_styles;
	}

	/**
	 * Compiles the array of scripts where the key is the name and the value is the path
	 * ordering the array based on the required scripts.
	 *
	 * @return  array
	 */
	protected function compile_scripts() {
		$scripts = $this->compile_style_script($this->scripts);

		// create the array for use in the template
		$final_scripts = array();
		foreach ($scripts as $name => $data) {
			$final_scripts[$name] = $data['path'];
		}

		return $final_scripts;
	}

	/**
	 * Loops through the array, ordering it based on the required values.
	 * 'required' must be one of the keys in the value array.
	 *
	 * @param  array  $array  The array to order.
	 * @return  array
	 */
	protected function compile_style_script($array) {
		$final = array();

		foreach ($array as $_name => $_data) {
			if (empty($_data['required'])) {
				$final[$_name] = $_data;
				continue;
			}

			// store all the scripts and empty the array
			$_final = $final;
			$final = array();

			$needed = count($_data['required']);
			$found = 0;

			// loop through the list of scripts till we've found all the required ones
			foreach ($_final as $__name => $__data) {
				$final[$__name] = $__data;
				unset($_final[$__name]);

				if (in_array($__name, $_data['required'])) {
					++ $found;
					if ($found == $needed) {
						break;
					}
				}
			}

			// add the style sheet
			$final[$_name] = $_data;

			// add the remainder of the scripts
			foreach ($_final as $__name => $__data) {
				$final[$__name] = $__data;
			}
		}

		return $final;
	} // function compile_style_script
}