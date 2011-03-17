<?php defined('SYSPATH') or die('No direct script access.');

class Controller_cl4_Base extends Controller_Template {
	public $template = 'cl4/base/base'; // this is the default template file
	public $allowed_languages = array('en-ca'); // set allowed languages
	public $page;
	public $section;
	public $locale; // the locale string, eg. 'en-ca' or 'fr-ca'
	public $language; // the two-letter language code, eg. 'en' or 'fr'

	protected $user; // currently logged-in user
	protected $logged_in = FALSE; // whether user is logged in
	protected $session;

	// if set to false, the messages will not automatically be displayed (need to display them manually)
	protected $display_messages = TRUE;

	/**
	* Controls access for the whole controller
	* If the entire controller REQUIRES that the user be logged in, set this to TRUE
	* If some or all of the controller DOES NOT need to be logged in, set to this FALSE; to control which actions require authentication or a specific permission, us the $secure_actions array
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
	* Called before our action method
	*/
	public function before() {
		$this->get_session();

		parent::before();

		$this->check_login();

		// initialize the locale if there are allowed languages
		if ( ! empty($this->allowed_languages) && count($this->allowed_languages) > 1) {
			$language_selection = TRUE;
			try {
				// use the locale parameter from the route, if not set, then the cookie, if not set, then use the first locale in the list
				$this->locale = Request::current()->param('locale', Cookie::get('language', $this->allowed_languages[0]));
				// make sure the locale is valid
				if ( ! in_array($this->locale, $this->allowed_languages)) $this->locale = $this->allowed_languages[0];
				// set up the locale
				i18n::lang($this->locale);
				// try to remember the locale in a cookie
				Cookie::set('language', $this->locale, Date::MONTH);
			} catch (Exception $e) {
				// failed to set and/or store the locale
			}
			$this->language = substr(i18n::lang(), 0, 2);

			// create the language switch link and set the locale
			if ($this->locale == 'fr-ca') {
				// french, set the date
				setlocale(LC_TIME, 'fr_CA.utf8');
				// create the switch lanuage link
				$language_switch_link = '<a href="/' . Request::current()->uri(array('lang' => 'en-ca')) . '">EN</a> / FR';
				$date_input_options = "            format: 'dddd dd, mmmm yyyy'" . EOL;
			} else {
				// english, set the date
				setlocale(LC_TIME, 'en_CA.utf8');
				// create the switch lanuage link
				$language_switch_link = 'EN / <a href="/' . Request::current()->uri(array('lang' => 'fr-ca')) . '">FR</a>';
				$date_input_options = "            lang: 'fr', " . EOL; // defined in master js file, must execute before this does
				$date_input_options .= "            format: 'dddd mmmm dd, yyyy'" . EOL;
			} // if

		} else {
			// there are no or 1 language so no language selection
			$language_selection = FALSE;
		}

		// set up the default template values for the base template
		if ($this->auto_render === TRUE) {
			// Initialize default values
			$this->template->logged_in = $this->logged_in;
			$this->template->user = $this->user;

			$this->template->page_section = $this->section;
			$this->template->page_name = ( ! empty($this->page) ? $this->page : Request::current()->controller());

			$this->set_template_page_title();

			$this->set_template_meta();

			$this->add_template_styles();
			$this->add_template_js();

			if ($language_selection) {
				$this->template->language = $this->language;
				$this->template->language_options = $language_switch_link;
				$this->template->date_input_options = $date_input_options;
			}

			// set some empty variables
			$this->template->body_class = ''; // other classes are added to this with spaces
			$this->template->pre_message = '';
			$this->template->message = '';
			$this->template->body_html = '';
		} // if
	} // function before

	/**
	* Stores the session by reference in the $session
	*
	* @return  void
	*/
	public function get_session() {
		$this->session =& Session::instance()->as_array();
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
		// record if they are logged in and set the template variable
		$this->logged_in = Auth::instance()->logged_in();

		// ***** Authentication *****
		// check to see if they are allowed to access the action
		if ( ! Auth::instance()->controller_allowed($this, Request::current()->action())) {
			$is_ajax = (bool) cl4::get_param('c_ajax', FALSE);
			if ($this->logged_in) {
				// user is logged in but not allowed to access the page/action
				if ($is_ajax) {
					echo AJAX_Status::ajax(array(
						'status' => AJAX_Status::NOT_ALLOWED,
						'debug_msg' => 'Referrer: ' . Request::current()->uri(),
					));
					exit;
				} else {
					Request::current()->redirect(Route::get('login')->uri(array('action' => 'noaccess')) . URL::array_to_query(array('referrer' => Request::current()->uri()), '&'));
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
						Request::current()->redirect(Route::get('login')->uri(array('action' => 'timedout')) . URL::array_to_query(array('redirect' => Request::current()->uri()), '&'));
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
						Request::current()->redirect(Route::get('login')->uri() . URL::array_to_query(array('redirect' => Request::current()->uri()), '&'));
					}
				}
			} // if
		} // if

		if ($this->logged_in && $this->auto_render === TRUE) {
			// the user is logged in so set the user property so we have quick access to the user object
			$this->user = Auth::instance()->get_user();

			// update the session auth timestamp
			Auth::instance()->update_timestamp();
		} // if

		return $this;
	} // function check_login

	/**
	* If the login timeout post functionality is enabled, this will store the passed
	* GET and POST in the session key for use in Controller_cl4_Login to re-post the data.
	* If there is no get or post, this will unset the session key
	*
	* @return  void
	*/
	protected function process_timeout() {
		if (Kohana::config('cl4login.enable_timeout_post')) {
			// store the post so we can post it again after the user enters their password
			$timeout_post_session_key = Kohana::config('cl4login.timeout_post_session_key');
			if ( ! empty($_GET) || ! empty($_POST)) {
				$this->session[$timeout_post_session_key] = array(
					'post_to' => $this->request->uri,
					'get' => $_GET,
					'post' => $_POST,
				);
			} else if ( ! empty($this->session[$timeout_post_session_key])) {
				unset($this->session[$timeout_post_session_key]);
			}
		} // if
	} // function process_timeout

	/**
	* Sets the page title to an empty string
	*
	* @return  Controller_Base
	*/
	public function set_template_page_title() {
		if (empty($this->template->page_title)) $this->template->page_title = '';
	} // function set_template_page_title

	/**
	* Sets up the template meta tags var, adding keys with empty values for description, keywords, author and viewport
	*
	* @return  Controller_Base
	*/
	public function set_template_meta() {
		// an array of meta tags where the key is the name and value is the content
		if (empty($this->template->meta_tags)) $this->template->meta_tags = array();
		if ( ! isset($this->template->meta_tags['description'])) $this->template->meta_tags['description'] = '';
		if ( ! isset($this->template->meta_tags['keywords'])) $this->template->meta_tags['keywords'] = '';
		if ( ! isset($this->template->meta_tags['author'])) $this->template->meta_tags['author'] = '';
		if ( ! isset($this->template->meta_tags['viewport'])) $this->template->meta_tags['viewport'] = '';

		return $this;
	} // function set_template_meta

	/**
	* Sets up the template script var, add's modernizr, jquery, jquery ui, cl4.js and base.js if they are not already set
	*
	* @return  Controller_Base
	*/
	public function add_template_js() {
		if (empty($this->template->modernizr_path)) $this->template->modernizr_path = 'js/modernizr-1.6.min.js';

		if (empty($this->template->scripts)) $this->template->scripts = array();
		// add jquery js (for all pages, other js relies on it, so it has to be included first)
		if ( ! isset($this->template->scripts['jquery'])) $this->template->scripts['jquery'] = '//ajax.googleapis.com/ajax/libs/jquery/1.5.1/jquery.min.js';
		if ( ! isset($this->template->scripts['jquery_ui'])) $this->template->scripts['jquery_ui'] = '//ajax.googleapis.com/ajax/libs/jqueryui/1.8.10/jquery-ui.min.js';
		if ( ! isset($this->template->scripts['cl4'])) $this->template->scripts['cl4'] = 'cl4/js/cl4.js';
		if ( ! isset($this->template->scripts['cl4_ajax'])) $this->template->scripts['cl4_ajax'] = 'cl4/js/ajax.js';
		if ( ! isset($this->template->scripts['base'])) $this->template->scripts['base'] = 'js/base.js';

		if (empty($this->template->on_load_js)) $this->template->on_load_js = '';

		return $this;
	} // function add_template_js

	/**
	* Adds JavaScript to the template on_load_js var, including checking to see if there should be a line break before the addition
	*
	* @param  string  $js  The javascript to add
	* @return  Controller_Base
	*/
	public function add_on_load_js($js) {
		if ( ! empty($this->template->on_load_js)) {
			$this->template->on_load_js .= "\n";
		}
		$this->template->on_load_js .= $js;

		return $this;
	} // function add_on_load_js

	/**
	* Sets up and adds some styles, including reset.css, jquery ui, cl4.css and base.css
	*
	* @return  Controller_Base
	*/
	public function add_template_styles() {
		$this->template->styles = array(
			'css/reset.css' => NULL,
			'//ajax.googleapis.com/ajax/libs/jqueryui/1.8.10/themes/pepper-grinder/jquery-ui.css' => NULL,
			'cl4/css/cl4.css' => NULL,
			'css/base.css' => NULL,
		);

		return $this;
	} // function add_template_styles

	/**
	* Adds the CSS for cl4admin
	*/
	protected function add_public_css() {
		if ($this->auto_render) {
			$this->template->styles['css/public.css'] = NULL;
		}
	} // function add_admin_css

	/**
	* Adds the CSS for cl4admin
	*/
	protected function add_admin_css() {
		if ($this->auto_render) {
			$this->template->styles['css/admin.css'] = NULL;
		}
	} // function add_admin_css

	/**
	* Called after our action method
	*/
	public function after() {
		if ($this->auto_render === TRUE) {
			$this->template->body_class .= ' ' . i18n::lang();
			// apply body classes depending on the page and section
			if ( ! empty($this->page)) {
				$this->template->body_class .= ' p_' . $this->page;
			}
			if ( ! empty($this->section)) {
				$this->template->body_class .= ' s_' . $this->section;
			}

			// set up any language specific styles
			switch ($this->language) {
				case 'en':
					//$styles['css/base_en.css'] = 'screen';
					break;
				case 'fr':
					//$styles['css/base_fr.css'] = 'screen';
				break;
			} // switch

			// look for any status message and display
			if ($this->display_messages) {
				$this->template->message = Message::display();
			}

			if (cl4::is_dev()) {
				// this is so a session isn't started needlessly when in debug mode
				$this->template->session = $this->session;
			}
		} // if

		parent::after();
	} // function after

	/**
	* Returns a 404 error status and 404 page
	*/
	public function action_404() {
		$locale = (empty($this->locale) ? $this->allowed_languages[0] : $this->locale);

		// return a 404 because the page couldn't be found
		Request::current()->status = 404;
		$this->template->body_html = View::factory('pages/' . $locale . '/404')
			->set('message', Response::$messages[404]);
	} // function action_404
} // class Controller_Base
