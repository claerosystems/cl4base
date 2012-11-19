<?php defined('SYSPATH') or die('No direct script access.');

class Controller_CL4_Login extends Controller_Base {
	public $page = 'login';

	public function before() {
		parent::before();

		// if we are using cl4base, then we want to add the admin css
		if (method_exists($this, 'add_admin_css')) {
			$this->add_admin_css();
		}
	} // function before

	/**
	* Displays the login form and logs the user in or detects and invalid login (through Auth and Model_User)
	*
	* View: Login form.
	*/
	public function action_index() {
		try {
			// set the template title (see Controller_App for implementation)
			$this->template->page_title = 'Login';

			// get some variables from the request
			// get the user name from a get parameter or a cookie (if set)
			$username = CL4::get_param('username', Cookie::get('username'));
			$password = CL4::get_param('password');
			$timed_out = CL4::get_param('timed_out');
			// default to NULL when no redirect is received so it uses the default redirect
			$redirect = CL4::get_param('redirect');

			// If user already signed-in
			if (Auth::instance()->logged_in() === TRUE){
				// redirect to the default login location or the redirect location
				$this->login_success_redirect($redirect);
			}
		} catch (Exception $e) {
			Kohana_Exception::caught_handler($e);
			if ( ! CL4::is_dev()) $this->login_success_redirect();
		}

		try {
			$login_config = Kohana::$config->load('cl4login');

			// Get number of login attempts this session
			$attempts = Arr::path($this->session, $login_config['session_key'] . '.attempts', 0);
			$force_captcha = Arr::path($this->session, $login_config['session_key'] . '.force_captcha', FALSE);

			// If more than three login attempts, add a captcha to form
			$captcha_required = ($force_captcha || $attempts > $login_config['failed_login_captcha_display']);
			// Update number of login attempts
			++$attempts;
			$this->session[$login_config['session_key']]['attempts'] = $attempts;

			// load recaptcha
			// do this here because there are likely to be a lot of accesses to this action that will never make it to here
			// loading it here will save server time finding (searching) and loading recaptcha
			Kohana::load(Kohana::find_file('vendor/recaptcha', 'recaptchalib'));
		} catch (Exception $e) {
			Kohana_Exception::caught_handler($e);
			$this->action_404();
		}

		try {
			// $_POST is not empty
			if ( ! empty($_POST)) {
				$human_verified = FALSE;
				$captcha_received = FALSE;
				try {
					// If recaptcha was set and is required
					if ($captcha_required && isset($_POST['recaptcha_challenge_field']) && isset($_POST['recaptcha_response_field'])) {
						$captcha_received = TRUE;
						// Test if recaptcha is valid
						$resp = recaptcha_check_answer(RECAPTCHA_PRIVATE_KEY, $_SERVER['REMOTE_ADDR'], $_POST['recaptcha_challenge_field'], $_POST['recaptcha_response_field']);
						$human_verified = $resp->is_valid;
						Message::add('ReCAPTCHA valid: ' . ($human_verified ? 'Yes' : 'No'), Message::$debug);
					} // if
				} catch (Exception $e) {
					Kohana_Exception::caught_handler($e);
				}

				// if the captcha is required but we have not verified the human
				if ($captcha_required && ! $human_verified) {
					try {
						// increment the failed login count on the user
						$user = ORM::factory('User');
						$user->add_login_where($username)
							->find();

						// increment the login count and record the login attempt
						if ($user->loaded()) {
							$user->increment_failed_login();
						}

						$user->add_auth_log(Kohana::$config->load('cl4login.auth_type.too_many_attempts'), $username);
						Message::message('user', 'recaptcha_not_valid');
					} catch (ORM_Validation_Exception $e) {
						throw $e;
					} catch (Exception $e) {
						Kohana_Exception::caught_handler($e);
						Message::message('login', 'error');
					}

				// Check Auth and log the user in if their username and password is valid
				} else if (($login_messages = Auth::instance()->login($username, $password, FALSE, $human_verified)) === TRUE) {
					try {
						$user = Auth::instance()->get_user();
						// user has to update their profile or password
						if ($user->force_update_profile_flag || $user->force_update_password_flag) {
							// add a message for the user regarding updating their profile or password
							$message_path = $user->force_update_profile_flag ? 'update_profile' : 'update_password';
							Message::message('user', $message_path, array(), Message::$notice);

							// instead of redirecting them to the location they requested, redirect them to the profile page
							$redirect = Route::get('account')->uri(array('action' => 'profile'));
						} // if

						if ( ! empty($redirect) && is_string($redirect)) {
							// Redirect after a successful login, but check permissions first
							$redirect_request = Request::factory($redirect);
							$next_controller = 'Controller_' . $redirect_request->controller();
							$next_controller = new $next_controller($redirect_request, Response::factory());
							if (Auth::instance()->allowed($next_controller, $redirect_request->action())) {
								// they have permission to access the page, so redirect them there
								$this->login_success_redirect($redirect);
							} else {
								// they don't have permission to access the page, so just go to the default page
								$this->login_success_redirect();
							}
						} else {
							// redirect to the defualt location (by default this is user account)
							$this->login_success_redirect();
						}
					} catch (ORM_Validation_Exception $e) {
						throw $e;
					} catch (Exception $e) {
						Kohana_Exception::caught_handler($e);
						Message::message('login', 'error');
					}

				// If login failed (captcha and/or wrong credentials)
				} else {
					// force captcha may have changed within Auth::login()
					$force_captcha = Arr::path($this->session, $login_config['session_key'] . '.force_captcha', FALSE);
					if ( ! $captcha_required && $force_captcha) {
						$captcha_required = TRUE;
					}

					if ( ! empty($login_messages)) {
						foreach ($login_messages as $message_data) {
							list($message, $values) = $message_data;
							Message::message('user', $message, $values, Message::$error);
						}
					}

					// determine if we should be displaying a recaptcha message
					if ( ! $human_verified && $captcha_received) {
						Message::message('user', 'recaptcha_not_valid', array(), Message::$error);
					} else if ($captcha_required && ! $captcha_received) {
						Message::message('user', 'enter_recaptcha', array(), Message::$error);
					}
				} // if
			} // if $_POST
		} catch (ORM_Validation_Exception $e) {
			Kohana_Exception::caught_handler($e);
			Message::message('user', 'username.invalid');
		} catch (Exception $e) {
			Kohana_Exception::caught_handler($e);
		}

		if ( ! empty($timed_out)) {
			// they have come from the timeout page, so send them back there
			Request::current()->redirect(Route::get(Route::name(Request::current()->route()))->uri(array('action' => 'timedout')) . $this->get_redirect_query());
		}

		$this->template->body_html = View::factory('cl4/cl4login/login')
			->set('redirect', $redirect)
			->set('username', $username)
			->set('password', $password)
			->set('add_captcha', $captcha_required);
	} // function

	/**
	* Redirects the user the first page they should see after login
	* $redirect contains the page they may have requested before logging in and they should be redirected there
	* If $redirect is is NULL then the default redirect from the config will be used
	*
	* @param  string  $redirect  The path to redirect to
	* @return  void  never returns
	*/
	protected function login_success_redirect($redirect = NULL) {
		if ($redirect !== NULL) {
			Request::current()->redirect($redirect);
		} else {
			$auth_config = Kohana::$config->load('auth');
			Request::current()->redirect(Route::get($auth_config['default_login_redirect'])->uri($auth_config['default_login_redirect_params']));
		}
	} // function login_success_redirect

	/**
	* Log the user out and redirect to the login page.
	*/
	public function action_logout() {
		try {
			if (Auth::instance()->logout()) {
				Message::add(__(Kohana::message('user', 'username.logged_out')), Message::$notice);
			} // if

			// redirect to the user account and then the signin page if logout worked as expected
			$this->redirect(Route::get(Route::name(Request::current()->route()))->uri() . $this->get_redirect_query());
		} catch (Exception $e) {
			Kohana_Exception::caught_handler($e);
			Message::add(__(Kohana::message('user', 'username.not_logged_out')), Message::$error);

			if ( ! CL4::is_dev()) {
				// redirect them to the default page
				$auth_config = Kohana::$config->load('auth');
				Request::current()->redirect(Route::get($auth_config['default_login_redirect'])->uri($auth_config['default_login_redirect_params']));
			}
		} // try
	} // function action_logout

	/**
	* Display a page that displays the username and asks the user to enter the password
	* This is for when their session has timed out, but we don't want to make the login fully again
	* If the user has fully timed out, they will be logged out and returned to the login page
	*/
	public function action_timedout() {
		$user = Auth::instance()->get_user();

		$max_lifetime = Kohana::$config->load('auth.timed_out_max_lifetime');

		if ( ! $user || ($max_lifetime > 0 && Auth::instance()->timed_out($max_lifetime))) {
			// user is not logged in at all or they have reached the maximum amount of time we allow sometime to stay logged in, so redirect them to the login page
			Request::current()->redirect(Route::get(Route::name(Request::current()->route()))->uri(array('action' => 'logout')) . $this->get_redirect_query());
		}

		if (Kohana::$config->load('cl4login.enable_timeout_post') && ! empty($this->session[Kohana::$config->load('cl4login.timeout_post_session_key')])) {
			$redirect = Route::get('login')->uri(array('action' => 'timeoutpost'));
		} else {
			// need to decode the redirect as it will be encoded in the URL
			$redirect = CL4::get_param('redirect');
		}

		$this->template->page_title = 'Timed Out';
		$this->template->body_html = View::factory('cl4/cl4login/timed_out')
			->set('redirect', $redirect)
			->set('username', $user->username);

		$this->add_on_load_js('$(\'#password\').focus();');
	} // function action_timedout

	/**
	* Creates a form with all the fields from the GET and POST and then submits the form
	* to the page they were originally submitted to.
	*
	* @return  void
	*
	* @uses  Form::array_to_fields()
	*/
	public function action_timeoutpost() {
		// we want to redirect the user to the previous form, first creating the form and then submitting it with JS
		$session_key = Kohana::$config->load('cl4login.timeout_post_session_key');

		if ( ! Kohana::$config->load('cl4login.enable_timeout_post') || empty($this->session[$session_key])) {
			$this->login_success_redirect();
		}

		try {
			$timeout_post = $this->session[$session_key];

			$form_html = Form::open(URL::site($timeout_post['post_to']), array('id' => 'timeout_post')) . EOL;
			if ( ! empty($timeout_post['get'])) {
				$form_html .= Form::array_to_fields($timeout_post['get']);
			}
			if ( ! empty($timeout_post['post'])) {
				$form_html .= Form::array_to_fields($timeout_post['post']);
			}
			$form_html .= Form::close();

			$this->template->body_html = $form_html;
			$this->add_on_load_js('$(\'#timeout_post\').submit();');

			unset($this->session[$session_key]);
		} catch (Exception $e) {
			Kohana_Exception::caught_handler($e);
			if ( ! CL4::is_dev()) $this->login_success_redirect();
		}
	} // function action_timeoutpost

	/**
	* View: Access not allowed.
	*/
	public function action_noaccess() {
		// set the template title (see Controller_App for implementation)
		$this->template->title = 'Access not allowed';
		$view = $this->template->body_html = View::factory('cl4/cl4login/no_access')
			->set('referrer', CL4::get_param('referrer'));
	} // function action_noaccess

	/**
	* Returns the redirect value as a query string ready to use in a direct
	* The ? is added at the beginning of the string
	* An empty string is returned if there is no redirect parameter
	*
	* @return	string
	*/
	protected function get_redirect_query() {
		$redirect = urldecode(CL4::get_param('redirect'));

		if ( ! empty($redirect)) {
			return URL::array_to_query(array('redirect' => $redirect), '&');
		} else {
			return '';
		}
	} // function get_redirect_query

	/**
	* A basic implementation of the "Forgot password" functionality
	*/
	public function action_forgot() {
		try {
			Kohana::load(Kohana::find_file('vendor/recaptcha', 'recaptchalib'));

			$default_options = Kohana::$config->load('cl4login');

			// set the template page_title (see Controller_Base for implementation)
			$this->template->page_title = 'Forgot Password';

			if (isset($_POST['reset_username'])) {
				// If recaptcha is valid and is received
				$captcha_received = FALSE;
				if (isset($_POST['recaptcha_challenge_field']) && isset($_POST['recaptcha_response_field'])) {
					$captcha_received = TRUE;
					$resp = recaptcha_check_answer(RECAPTCHA_PRIVATE_KEY, $_SERVER['REMOTE_ADDR'], $_POST['recaptcha_challenge_field'], $_POST['recaptcha_response_field']);
				}

				$user = ORM::factory('User')->where('username', '=', $_POST['reset_username'])
					->where_active('user')
					->find();

				// Admin passwords cannot be reset by email
				if ($captcha_received && $resp->is_valid && $user->loaded() && ! in_array($user->username, $default_options['admin_accounts'])) {
					// send an email with the account reset token
					$user->set('reset_token', Text::random('alnum', 32))
						->is_valid()
						->save();

					try {
						$mail = new Mail();
						$mail->IsHTML();
						$mail->add_user($user->id);
						$mail->Subject = LONG_NAME . ' Password Reset';

						// build a link with action reset including their username and the reset token
						$url = URL::site(Route::get(Route::name(Request::current()->route()))->uri(array('action' => 'reset')) . '?' . http_build_query(array(
							'username' => $user->username,
							'reset_token' => $user->reset_token,
						)), Request::current()->protocol());

						$mail->Body = View::factory('cl4/cl4login/forgot_link')
							->set('app_name', LONG_NAME)
							->set('url', $url)
							->set('admin_email', ADMIN_EMAIL);

						$mail->Send();

						Message::add(__(Kohana::message('login', 'reset_link_sent')), Message::$notice);
					} catch (Exception $e) {
						Message::add(__(Kohana::message('login', 'forgot_send_error')), Message::$error);
						throw $e;
					}
				} else if (in_array($user->username, $default_options['admin_accounts'])) {
					Message::add(__(Kohana::message('login', 'reset_admin_account')), Message::$warning);

				} else {
					Message::add(__(Kohana::message('login', 'reset_not_found')), Message::$warning);
					if ( ! $captcha_received || ! $resp->is_valid) {
						Message::add(__(Kohana::message('user', 'recaptcha_not_valid')), Message::$warning);
					}
				}
			} // if post

			$this->template->body_html = View::factory('cl4/cl4login/forgot');
		} catch (Exception $e) {
			Kohana_Exception::caught_handler($e);
			Message::add(__(Kohana::message('login', 'reset_error')), Message::$error);
		}
	} // function

	/**
	* A basic version of "reset password" functionality.
	*
	* @todo consider changing this to not send the password, but instead allow them enter a new password right there; this might be more secure, but since we've sent them a link anyway, it's probably too late for security; the only thing is email is insecure (not HTTPS)
	*/
	function action_reset() {
		try {
			$default_options = Kohana::$config->load('cl4login');

			// set the template title (see Controller_Base for implementation)
			$this->template->page_title = 'Password Reset';

			$username = CL4::get_param('username');
			if ($username !== null) $username = trim($username);
			$reset_token = CL4::get_param('reset_token');

			// make sure that the reset_token has exactly 32 characters (not doing that would allow resets with token length 0)
			// also make sure we aren't trying to reset the password for an admin
			if ( ! empty($username) && ! empty($reset_token) && strlen($reset_token) == 32) {
				$user = ORM::factory('User')
					->where('username', '=', $_REQUEST['username'])
					->and_where('reset_token', '=', $_REQUEST['reset_token'])
					->where_active('user')
					->find();

				// admin passwords cannot be reset by email
				if (is_numeric($user->id) && ! in_array($user->username, $default_options['admin_accounts'])) {
					try {
						$password = cl4_Auth::generate_password();
						$user->values(array(
								'password' => $password,
								// reset the failed login count
								'failed_login_count' => 0,
								// send the user to the password update page
								'force_update_password_flag' => 1,
							))
							->is_valid()
							->save();
					} catch (Exception $e) {
						Message::add(__(Kohana::message('login', 'password_email_error')), Message::$error);
						throw $e;
					}

					try {
						$mail = new Mail();
						$mail->IsHTML();
						$mail->add_user($user->id);
						$mail->Subject = LONG_NAME . ' New Password';

						// provide a link to the user including their username
						$url = URL::site(Route::get(Route::name(Request::current()->route()))->uri() . '?' . http_build_query(array('username' => $user->username)), Request::current()->protocol());

						$mail->Body = View::factory('cl4/cl4login/forgot_reset')
							->set('app_name', LONG_NAME)
							->set('username', $user->username)
							->set('password', $password)
							->set('url', $url)
							->set('admin_email', ADMIN_EMAIL);

						$mail->Send();

						Message::add(__(Kohana::message('login', 'password_emailed')), Message::$notice);

					} catch (Exception $e) {
						Message::add(__(Kohana::message('login', 'password_email_error')), Message::$error);
						throw $e;
					}

					Request::current()->redirect(Route::get(Route::name(Request::current()->route()))->uri());

				} else {
					Message::add(__(Kohana::message('login', 'password_email_username_not_found')), Message::$error);
					Request::current()->redirect(Route::get(Route::name(Request::current()->route()))->uri(array('action' => 'forgot')));
				}

			} else {
				Message::add(__(Kohana::message('login', 'password_email_partial')), Message::$error);
				Request::current()->redirect(Route::get(Route::name(Request::current()->route()))->uri(array('action' => 'forgot')));
			}
		} catch (Exception $e) {
			Kohana_Exception::caught_handler($e);
			Message::add(__(Kohana::message('login', 'reset_error')), Message::$error);
		}
	} // function

		/**
	 * Registers a new user.
	 *//*
	public function action_register() {

		// see if the user is already logged in
		// todo: do something smarter here, like ask if they want to register a new user?
		if ($this->auth->logged_in()) {
			claero::flash_set('message', 'You already have an account.');
			$this->request->redirect($this->redirectUrl);
		}

		$this->redirectPage = 'register';

		if (Request::$method == 'POST') {
            // try to create a new user with the supplied credentials
            try {

                // check the recaptcha string to make sure it was entered properly
                require_once(ABS_ROOT . '/lib/recaptcha/recaptchalib.php');
                $resp = recaptcha_check_answer(RECAPTCHA_PRIVATE_KEY, $_SERVER['REMOTE_ADDR'], $_POST['recaptcha_challenge_field'], $_POST['recaptcha_response_field']);
                if (!$resp->is_valid) {
                    claero::flash_set('message', __('The reCAPTCHA text did not match up, please try again.'));
                    Fire::log('The reCAPTCHA text did not match up, please try again.');
                } else {

                    // try to create the new user
                    $newUser = Jelly::factory('user')
                         ->set(array(
                            'active_flag' => 0, // already defaulted in database
                            'date_created' => date('Y-m-d H:i:s'),
                            'email' => Security::xss_clean(Arr::get($_POST, 'email', '')),
                            'password' => Security::xss_clean(Arr::get($_POST, 'password', '')),
                            'password_confirm' => Security::xss_clean(Arr::get($_POST, 'password_confirm', '')),
                            'first_name' => Security::xss_clean(Arr::get($_POST, 'first_name', '')),
                            'middle_name' => Security::xss_clean(Arr::get($_POST, 'middle_name', '')),
                            'last_name' => Security::xss_clean(Arr::get($_POST, 'last_name', '')),
                            'company' => Security::xss_clean(Arr::get($_POST, 'company', '')),
                            'province_id' => Security::xss_clean(Arr::get($_POST, 'province_id', '')),
                            'work_phone' => Security::xss_clean(Arr::get($_POST, 'work_phone', '')),
                            'mobile_phone' => Security::xss_clean(Arr::get($_POST, 'mobile_phone', '')),
                         ));
                    if ($newUser->save()) {
                        claero::flash_set('message', __("Your account was created successfully."));
                        $this->redirectPage = 'index';
                    } // if
                    //Fire::log('looks like it worked?');
                } // if

            } catch (Validate_Exception $e) {
                claero::flash_set('message', __("A validation error occurred, please correct your information and try again."));
                Fire::log('A validation exception occurred: ');
                Fire::log($e->array);

            } catch (Exception $e) {
                Fire::log('Some other exception occured');
                Fire::log($e);
                $this->template->body_html .= 'Could not create user. Error: "' . Kohana_Exception::text($e) . '"';
                claero::flash_set('message', 'An error occurred during registration, please try again later.');

            } // try
        } else {
            // invalid request type for registration
            Fire::log('invalid request type for registration');
		} // if

        // Redirect to login
        //$this->request->redirect($this->redirectUrl);
        fire::log('here we are');


        $this->provinceId = Security::xss_clean(Arr::get($_POST, 'province_id', ''));

	} // function action_register
*/
} // class Controller_CL4_Login