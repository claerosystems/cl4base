<?php defined('SYSPATH') or die('No direct script access.');

class Controller_cl4_Base extends Controller_Template {
	public $template = 'cl4/base/base'; // this is the default template file
	public $allowed_languages = array('en-ca', 'fr-ca'); // set allowed languages
	public $page = NULL;
	public $section = NULL;
	public $locale = NULL; // the locale string, eg. 'en-ca' or 'fr-ca'
	public $language = NULL; // the two-letter language code, eg. 'en' or 'fr'
	public $this_page = NULL;

	protected $user; // currently logged-in user
	protected $logged_in = FALSE; // whether user is logged in
	protected $session = NULL;

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

		// set up the controller properties
		if (isset($_SERVER['SERVER_NAME']) && $_SERVER['REQUEST_URI']) {
			$this->this_page = $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
		}

		// only do language detection if there are allowed languages
		if ( ! empty($this->allowed_languages) && count($this->allowed_languages) > 1) {
			$language_selection = TRUE;
			// set locale and language and save in a cookie
			// see if we have a locale cookie to use
			$default_locale = Cookie::get('language', 'en-ca');
			$this->locale = Request::instance()->param('lang', $default_locale);
			if ( ! in_array($this->locale, $this->allowed_languages)) {
				$this->locale = $this->allowed_languages[0];
			} // if
			i18n::lang($this->locale);
			Cookie::set('language', $this->locale);
			$this->language = substr(i18n::lang(), 0, 2);

			// create the language switch link and set the locale
			if ($this->locale == 'fr-ca') {
				// french, set the date
				setlocale(LC_TIME, 'fr_CA.utf8');
				// create the switch lanuage link
				$language_switch_link = '<a href="/' . Request::instance()->uri(array('lang' => 'en-ca')) . '">EN</a> / FR';
				$date_input_options = "            format: 'dddd dd, mmmm yyyy'" . EOL;
			} else {
				// english, set the date
				setlocale(LC_TIME, 'en_CA.utf8');
				// create the switch lanuage link
				$language_switch_link = 'EN / <a href="/' . Request::instance()->uri(array('lang' => 'fr-ca')) . '">FR</a>';
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

			$this->template->url_root = URL_ROOT;
			$this->template->this_page = $this->this_page;
			$this->template->page_section = $this->section;
			$this->template->page_name = ( ! empty($this->page) ? $this->page : $this->request->controller);

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
			$this->template->message = '';
			$this->template->body_html = '';
		} // if
	} // function

	public function get_session() {
		$this->session =& Session::instance()->as_array();
	}

	/**
	* Checks if the user is logged in and if they have permissions to the current action
	* If the user is not logged in, then they are redirected to the timed out page or login page
	* If the user is logged in, but not allowed, then they are sent to the no access page
	* If they are logged in and have access, then it will updat the time stamp in the session
	*
	* @return  Controller_Base
	*/
	public function check_login() {
		// record if they are logged in and set the template variable
		$this->logged_in = Auth::instance()->logged_in();

		// ***** Authentication *****
		// check to see if they are allowed to access the action
		if ( ! Auth::instance()->controller_allowed($this, Request::instance()->action)) {
			if ($this->logged_in) {
				// user is logged in but not allowed to access the page/action
				Request::instance()->redirect('login/noaccess' . URL::array_to_query(array('referrer' => Request::instance()->uri()), '&'));
			} else {
				if (Auth::instance()->timed_out()) {
					// display password page because the sesion has timeout
					Request::instance()->redirect('login/timedout' . URL::array_to_query(array('redirect' => Request::instance()->uri()), '&'));
				} else {
					// just not logged in, so redirect them to the login with a redirect parameter back to the current page
					Request::instance()->redirect('login' . URL::array_to_query(array('redirect' => Request::instance()->uri()), '&'));
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
	* Sets the page title to an empty string
	*
	* @return  Controller_Base
	*/
	public function set_template_page_title() {
		if (empty($this->template->page_title)) $this->template->page_title = '';
	} // function set_template_page_title

	/**
	* Sets up the template meta tags var, adding keys with empty values for description, keywords and author
	* Adds viewport with value of width=device-width, initial-scale=1.0
	*
	* @return  Controller_Base
	*/
	public function set_template_meta() {
		// an array of meta tags where the key is the name and value is the content
		if (empty($this->template->meta_tags)) $this->template->meta_tags = array();
		if ( ! isset($this->template->meta_tags['description'])) $this->template->meta_tags['description'] = '';
		if ( ! isset($this->template->meta_tags['keywords'])) $this->template->meta_tags['keywords'] = '';
		if ( ! isset($this->template->meta_tags['author'])) $this->template->meta_tags['author'] = '';
		if ( ! isset($this->template->meta_tags['viewport'])) $this->template->meta_tags['viewport'] = 'width=device-width, initial-scale=1.0';

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
		if ( ! isset($this->template->scripts['jquery'])) $this->template->scripts['jquery'] = '//ajax.googleapis.com/ajax/libs/jquery/1.4.4/jquery.min.js';
		if ( ! isset($this->template->scripts['jquery_ui'])) $this->template->scripts['jquery_ui'] = '//ajax.googleapis.com/ajax/libs/jqueryui/1.8.6/jquery-ui.min.js';
		if ( ! isset($this->template->scripts['cl4'])) $this->template->scripts['cl4'] = 'cl4/js/cl4.js';
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
			'css/reset.css' => 'screen',
			'//ajax.googleapis.com/ajax/libs/jqueryui/1.8.6/themes/pepper-grinder/jquery-ui.css' => 'screen',
			'cl4/css/cl4.css' => 'screen',
			'css/base.css' => 'screen',
		);

		return $this;
	} // function add_template_styles

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

			// set up the css depending on the browser type, these files override trialto.css
			switch (BROWSER_TYPE) {
				case 'mobile_safari':
					//$styles['css/iphone.css'] = 'screen';
					break;
				case 'mobile_default':
					//$styles['css/mobile.css'] = 'screen';
					break;
				case 'pc_default':
				default:
					break;
			} // switch

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
		Request::instance()->status = 404;
		$this->template->body_html = View::factory('pages/' . $locale . '/404')
			->set('message', Request::$messages[404]);
	} // function action_404
} // class Controller_Base