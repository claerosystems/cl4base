<?php defined('SYSPATH') or die ('No direct script access.');
/**
 * General functions that are used frequently throughout the entire website
 *
 * @package    Base
 * @author     Claero Systems
 * @copyright  (c) 2014 Claero Systems
 */
class CL4_Base {
	/**
	 * Attempte to convert the given phone number in to the standard cl4 format, which is x-xxx-xxx-xxxx-xx
	 * @param $phone_number
	 *
	 * @return string
	 */
	public static function convert_phone_to_cl4($phone_number) {
		$converted = '';

		// attempt to find the extension
		$phone_ext_parts = explode('x', $phone_number);
		if (isset($phone_ext_parts[1])) {
			$phone_ext = preg_replace("/\D/", "", $phone_ext_parts[1]);
			$phone_part_1 = preg_replace("/\D/", "-", $phone_ext_parts[0]);
		} else {
			$phone_ext = '';
			$phone_part_1 = preg_replace("/\D/", "-", $phone_number);
		}

		// replace all the duplicate dashes with a single and remove any trailing dashes
		$phone_part_1 = trim(str_replace('--', '-', $phone_part_1), '-');
		$phone_parts = explode('-', $phone_part_1);
		$phone_part_count = count($phone_parts);

		// we only have 345-3234
		if ($phone_part_count == 2) {
			$converted = '--' . $phone_part_1 . '-' . $phone_ext;
			// we have 323-434-5456
		} else if ($phone_part_count == 3) {
			$converted = '-' . $phone_part_1 . '-' . $phone_ext;
			// we have 1-455-545-6567
		} else if ($phone_part_count == 4) {
			$converted = $phone_part_1 . '-' . $phone_ext;
			// we have 1-432-434-5455-455
		} else if ($phone_part_count == 5) {
			$converted = $phone_part_1; // already has the extension in it
		} else {
			$converted = $phone_number;
		}

		return $converted;
	}

	/**
	 * copy a file to AWS, all status messages are in Message
	 *
	 * @param $source_file_path
	 * @param $target_file_path
	 */
	public static function copy_media_to_aws($source_file_path, $target_file_path) {
		$status = FALSE;
		// copy the file to AWS
		require_once(APPPATH . '/vendor/s3/S3.php');
		$s3 = new S3(AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY);
		// see if the file is already on AWS
		if ($s3->getObjectInfo(AWS_MEDIA_BUCKET, $target_file_path, FALSE)) {
			// the file already exists on AWS
			//Message::add("The file already exists on S3: " .  AWS_MEDIA_BUCKET . '/' . $target_file_path, Message::$debug);
		} else {
			if ($s3->putObjectFile($source_file_path, AWS_MEDIA_BUCKET, $target_file_path)) {
				Message::add("{$target_file_path} stored in cloud.", Message::$debug);
				$status = TRUE;
			} else {
				Message::add("An error occurred and the file was not uploaded to S3:  " . AWS_MEDIA_BUCKET . '/' . $target_file_path, Message::$error);
			}
		}
		return $status;
	}

	/**
	 * create a random strong password based on the given mask: eg. Base::create_password('CccCcc##!')
	 *  Mask Rules
	 *  # - digit
	 *  C - Caps Character (A-Z)
	 *  c - Small Character (a-z)
	 *  X - Mixed Case Character (a-zA-Z)
	 *  ! - Custom Extended Characters
	 */
	public static function create_password($mask) {
		// Mask Rules
		// # - digit
		// C - Caps Character (A-Z)
		// c - Small Character (a-z)
		// X - Mixed Case Character (a-zA-Z)
		// ! - Custom Extended Characters
		$extended_chars = '!@#$%^&*()';
		$length = strlen($mask);
		$pwd = '';
		for ($c=0;$c<$length;$c++) {
			$ch = $mask[$c];
			switch ($ch) {
				case '#':
					$p_char = rand(0,9);
					break;
				case 'C':
					$p_char = chr(rand(65,90));
					break;
				case 'c':
					$p_char = chr(rand(97,122));
					break;
				case 'X':
					do {
						$p_char = rand(65,122);
					} while ($p_char > 90 && $p_char < 97);
					$p_char = chr($p_char);
					break;
				case '!':
					$p_char = $extended_chars[rand(0,strlen($extended_chars)-1)];
					break;
			}
			$pwd .= $p_char;
		}
		return $pwd;
	}

	/**
	 * formats a description string based on max length, etc for standard look and feel
	 *
	 * @param    float       $amount                 the amount
	 * @param    boolean     $htmlFlag               whether to include the html tags
	 *
	 * @return   string  $return                 the HTML formatted string
	 */
	public static function format_description($description, $max_length, $html_flag = TRUE) {
		$return_string = '';

		if (strlen($description) > $max_length) {
			// truncate
			$return_string = '';
			$return_string .= ($html_flag) ? '<span class="description" title="' . htmlentities($description) . '">' : '';
			$return_string .= htmlentities(substr($description, 0, $max_length) . '...');
			$return_string .= ($html_flag) ? '</span>' : '';
		} else {
			$return_string = htmlentities($description);
		} // if

		return $return_string;
	}

	/**
	 * Prepare and send a PDF document to the browser using WKHTMLTOPDF
	 *
	 * @param $prefix            used to differentiate different pdf types (modules/report/etc.)
	 * @param $page_content      the HTML to be included in the PDF
	 * @param $footer_content    the HTML to be included at the bottom of each page of the PDF
	 * @param $output_filename   the name of the file to be output to the user (somthing.pdf)
	 */
	public static function generate_pdf($prefix, $page_content, $footer_content, $header_content, $output_filename) {
		// save the preview HTML in to a temp file that can be accessed by wkhtmltopdf
		$temp_file_path = tempnam(ABS_ROOT . '/html/wkhtmltopdf_tmp', $prefix . 'r_');
		$target_file_path = $temp_file_path . '.html';
		rename($temp_file_path, $target_file_path);
		file_put_contents($target_file_path, $page_content);

		// save the pdf header in to a temp file that can be accessed by wkhtmltopdf
		$temp_header_path = tempnam(ABS_ROOT . '/html/wkhtmltopdf_tmp', $prefix . 'h_');
		$target_header_path = $temp_header_path . '.html';
		rename($temp_header_path, $target_header_path);
		file_put_contents($target_header_path, $header_content);

		// save the pdf footer in to a temp file that can be accessed by wkhtmltopdf
		$temp_footer_path = tempnam(ABS_ROOT . '/html/wkhtmltopdf_tmp', $prefix . 'f_');
		$target_footer_path = $temp_footer_path . '.html';
		rename($temp_footer_path, $target_footer_path);
		file_put_contents($target_footer_path, $footer_content);

		// extract just the filename from the target paths
		$file_path_parts = explode('/', $target_file_path);
		$target_file_name = array_pop($file_path_parts);

		$header_path_parts = explode('/', $target_header_path);
		$target_header_name = array_pop($header_path_parts);

		$footer_path_parts = explode('/', $target_footer_path);
		$target_footer_name = array_pop($footer_path_parts);

		//echo file_get_contents(URL_ROOT . '/wkhtmltopdf_tmp/' . $target_file_name);exit;
		//echo file_get_contents(URL_ROOT . '/wkhtmltopdf_tmp/' . $target_footer_name);exit;
		//echo Debug::vars(URL_ROOT . '/wkhtmltopdf_tmp/' . $target_file_name, URL_ROOT . '/wkhtmltopdf_tmp/' . $target_footer_name, $output_filename);
		//exit;

		// create the PDF using WkHtmlToPDF
		require_once(ABS_ROOT . '/application/vendor/phphtmltopdf/WkHtmlToPdf.php');
		$pdf = new WkHtmlToPdf();
		$pdf->setOptions(array(
			//'tmp' => ABS_ROOT . '/html/wkhtmltopdf_tmp',
			'footer-html' => URL_ROOT . '/wkhtmltopdf_tmp/' . $target_footer_name,
			'header-html' => URL_ROOT . '/wkhtmltopdf_tmp/' . $target_header_name,
			//'header-line' => NULL,
			//'footer-line' => '1',
			'footer-spacing' => '2',
			'header-spacing' => '2',
			'margin-top' => '20',
			'margin-bottom' => '20',
			'margin-left' => '10',
			'margin-right' => '10',
		));
		$pdf->addPage(URL_ROOT . '/wkhtmltopdf_tmp/' . $target_file_name);

		//echo Debug::vars($pdf);

		$status = $pdf->send($output_filename);

		echo Debug::vars($status);
	}

	/**
	 * Return the gravatar link for the given email and size.
	 *
	 * @param $email
	 * @param $size
	 *
	 * @return string
	 */
	public static function get_gravatar($email, $size) {
		if (HTTP_PROTOCOL == 'https') {
			$base_link = "https://secure.gravatar.com/avatar/";
		} else {
			$base_link = "http://www.gravatar.com/avatar/";
		}

		return $base_link . md5(strtolower(trim($email))) . "?s=" . $size . '&d=mm'; //'&d=blank'; //'&d=identicon'; // "?d=" . urlencode(URL_ROOT . '/images/loading.gif')
	}

	/**
	 * return the full <img> tab to the image from the model, id, and column specified, this is normally on AWS
	 * if the image is not on AWS, create the image on AWS and return the path
	 *
	 * @param mixed $db_connection
	 * @param mixed $table_name
	 * @param mixed $column_name
	 * @param mixed $id
	 * @param mixed $options
	 */
	public static function get_image($model_name, $id, $column_name, $options = array()) {
		$img_tag = FALSE;
		$img_attributes = (isset($options['img_attributes'])) ? $options['img_attributes'] : array();
		$max_height = (isset($options['max_height'])) ? $options['max_height'] : 0;
		$max_width = (isset($options['max_width'])) ? $options['max_width'] : 0;

		if (in_array($model_name, Portfolio::get_allowed_models())) {
			//echo Debug::vars($model_name, $column_name, $id);exit;
			$target_model = ORM::factory($model_name, $id);
			if ($target_model->loaded() && ! empty($target_model->$column_name)) {
				switch($model_name) {
					case 'Global_Company_Client_View':
						$upload_root = GLOBAL_UPLOAD_ROOT;
						break;
					default:
						$upload_root = COMPANY_UPLOAD_ROOT;
						break;
				}

				$target_path = strtolower($model_name) . '/' . $column_name . '/' . $target_model->$column_name;
				$original_image_path = $upload_root . '/' . $target_path;
				if ( ! file_exists($original_image_path)) {
					return "<!-- no bottle shot found: \n\n" . $original_image_path . "\n\n -->";
					//return '<span class="ui-state-error ui-corner-all" style="padding:0.5em;">missing image</span>';
				}

				// set up path based on options
				if ($max_height > 0 || $max_width > 0) {
					$resize_source_path = strtolower($model_name) . '/' . $column_name . '/' . 'c_m' . $max_width . 'xm' . $max_height . '_' . $target_model->$column_name;
					$aws_source = REQUEST_COMPANY_URL . '/' . $resize_source_path;
					$local_source = $upload_root . '/' . $resize_source_path;
				} else {
					$aws_source = REQUEST_COMPANY_URL . '/' . $target_path;
					$local_source = $original_image_path;
				}

				// see if this already exists on AWS and just return the link
				$resize_flag = FALSE;
				if ( ! file_exists( AWS_MEDIA_URL . $aws_source)) {
					if ($max_height > 0 || $max_width > 0) {
						//try {
						$source_image = Image::factory($original_image_path);

						// do we need to resize?
						if ($max_height > 0 && $source_image->height > $max_height) {
							// too high
							$source_image->resize(NULL, $max_height);
							$resize_flag = TRUE;
						}
						if ($max_width > 0 && $source_image->width > $max_width) {
							// too wide
							$source_image->resize($max_width, NULL);
							$resize_flag = TRUE;
						}
						//} catch (Exception $e) {

						//	Kohana_Exception::handler($e, FALSE, TRUE);
						//	return FALSE;
						//}
					}

					if ($resize_flag) {
						// create the new image in a temporary location
						$source_image->sharpen(10);
						$source_image->save($local_source);
						//$img_attributes['width'] = $source_image->width;
						//$img_attributes['width'] = $source_image->height;
					} else {
						// just grab the original image, it was already within the  specified limits
						$aws_source = REQUEST_COMPANY_URL . '/' . $target_path;
						$local_source = $original_image_path;
					}
					// move the file to AWS if it is not already there
					Wine::copy_media_to_aws($local_source, $aws_source);
				}

				// return the file path on AWS
				// todo: double-check this?  how about catch above? send local as backup?
				$img_src = AWS_MEDIA_URL . $aws_source;

				$img_tag = HTML::image($img_src, $img_attributes);
			} else {
				// no image set
				//die('could not load model or empty value');
			}
		} else {
			die('Invalid model received for action_get_image(), if this is valid, please add `' . $model_name . '` to the list of acceptable models.');
		}
		return $img_tag;
	}

	public static function get_log($table_name, $table_id) {
		$table_log = array();
		//try {
		$global_db = Database::instance(GLOBAL_DATABASE);
		$table_log_query = DB::Select('log_date', 'log_title', 'log_details')
			->from('global_table_log')
			->where('table_name', '=', $table_name)
			->and_where('table_id', '=', $table_id)
			->order_by('log_date')
			->execute($global_db);
		if ($table_log_query === FALSE) {
			// query failed
		} else {
			$table_log = $table_log_query->as_array();
		}
		//} catch (Exception $e) {

		//}
		return $table_log;
	}

	/**
	 * Generate a table with login history records based on the filters.
	 *
	 * @param array $filter
	 *
	 * @return string
	 */
	public static function get_login_history($filter = array(), $host_name_lookup_flag = TRUE) {
		// get the login history
		$login_query = DB::select('l.*', 'u.first_name', 'u.last_name', 'u.username')
			->from(array('auth_log', 'l'))
			->join(array('user', 'u'))->on('u.id', '=', 'l.user_id');

		if ( ! empty($filter['user_id'])) $login_query->where('l.user_id', '=', $filter['user_id']);
		if ( ! empty($filter['from'])) $login_query->where('l.access_time', '>', $filter['from']);
		if ( ! empty($filter['to'])) $login_query->where('l.access_time', '<', $filter['to']);

		if ( ! empty($filter['order_by_column']) && ! empty($filter['order_by_direction'])) {
			$login_query->order_by($filter['order_by_column'], $filter['order_by_direction']);
		} else {
			$login_query->order_by('l.access_time', 'DESC');
		}

		if ( ! empty($filter['limit'])) {
			$login_query->limit($filter['limit']);
		} else {
			$login_query->limit(15);
		}

		//echo (string) $login_query; exit;

		$login_list = $login_query->execute()->as_array();

		// create the auth table for display
		$auth_table = new HTMLTable(array(
			'heading' => array(
				__('Access Time'),
				__('User'),
				__('Type'),
				__('IP Address Detected'),
				//__('Browser'),
			),
			'table_attributes' => array(
				'class' => 'responsive_table login_history',
				'data-role' => 'table',
				//'data-mode' => 'columntoggle',
				//'id' => 'login_history'
			),
			'populate_all_cols' => TRUE,
		));
		//$auth_table->set_th_attribute(0, 'data-priority', 1);
		//$auth_table->set_th_attribute(1, 'data-priority', 2);
		$auth_table->set_th_attribute(2, 'data-priority', 1);
		$auth_table->set_th_attribute(3, 'data-priority', 5);
		$auth_type_map = Kohana::$config->load('auth.auth_type_map');
		foreach($login_list as $login) {
			$ip = $login['ip_address'];
			if ($host_name_lookup_flag) $ip .= ' | ' . gethostbyaddr($login['ip_address']);
			$auth_table->add_row(array(
				$login['access_time'],
				$login['first_name'] . ' ' . $login['last_name'] . ' (' . $login['username'] . ')',
				$auth_type_map[$login['auth_type_id']],
				$ip,
				//$login->browser,
			));
		}

		return $auth_table->get_html();
	}

	/**
	 * Get the localized version of the given message based on i18n::lang()
	 *
	 * @param $file
	 * @param $path
	 *
	 * @return string
	 */
	public static function get_message($file, $path) {
		return Kohana::message(i18n::lang() . '/' . $file, $path, __('[message not found]'));
	}

	/**
	 * Check for a parameter with the given key in the request data, POST overrides Request ovverides GET
	 * in this case empty values are returned as they were found, in other words '' and zero will work (unlike CL4::get_param)
	 *
	 * @param  string  the key of the paramter
	 * @param  mixed  the default value
	 * @param  string  used for type casting, can be 'int', 'string' or 'array'
	 * @return  mixed  the value of the parameter, or $default, or null
	 */
	public static function get_param($key, $default = NULL, $type = NULL) {
		$value = $default;
		$some_unique_val = time() + rand();

		if (isset($_POST[$key])) {
			$value = $_POST[$key];
		} else if ($key == 'controller') {
			$value = Request::current()->controller();
		} else if ($key == 'action') {
			$value = Request::current()->action();
		} else if (Request::current()->param($key, $some_unique_val) != $some_unique_val) {
			$value = Request::current()->param($key);
		} else if (isset($_GET[$key])) {
			$value = $_GET[$key];
		}

		return CL4::clean_param($value, $type);
	} // function get_param

	/**
	 * (set and get) looks for a user parameter and uses the saved setting as default, and sets the saved setting if found
	 * POST overrides route parameter which overrides GET
	 *
	 * @param    string      $parameter_name     the name of the parameter
	 * @param    mixed       $default            (optional) the default value to set/return, uses timeportal conf defaults instead
	 */
	public static function get_smart_parameter($parameter_name, $default = NULL) {
		$session =& Session::instance()->as_array();
		$source = $session['auth_user'];

		$parameter_value = Base::get_param($parameter_name, NULL);
		if ($parameter_value !== NULL) {
			// save and return the new setting
			$source->setting($parameter_name, $parameter_value);
			//echo "<p>found and set parameter ($parameter_name, $parameter_value)</p>";
			return $parameter_value;
		} else {
			// try to use the saved setting if one exists, otherwise use the default
			$saved_value = $source->setting($parameter_name);
			if ( ! empty($saved_value)) {
				//echo "<p>found saved value ($parameter_name, $saved_value)</p>";
				return $saved_value;
			} else {
				// save and return the default setting (should only ever happen the first time this setting is requested for this user/company)
				// use the default from the conf file if one is not passed
				if (empty($default)) $default = Kohana::$config->load("base.user_setting_default.{$parameter_name}");
				$source->setting($parameter_name, $default);
				//echo "<p>set and return default value ($parameter_name, $default)</p>";
				return $default;
			}
		}
	}

	public static function get_url($route, $params = array()) {
		return URL_ROOT . '/' . Route::get($route)->uri($params);
	}
	/**
	 * return the localized view based on i18n::lang()
	 */
	public static function get_view($path, $template_data = array()) {
		return View::factory('themes' . '/' . APP_THEME . '/' . i18n::lang() . '/' . $path, $template_data);
	}

	/**
	 * Adds a message using Kohana::message(), prepends the path with i18n::lang() . '/' and includes data merge
	 * Saves doing this:
	 *     Message::add(Kohana::message($file, i18n::lang() . '/' . $path), $data), $level);
	 *
	 * @see  Kohana::message()
	 * @see  __()
	 *
	 * @param   string  $file   The message file name
	 * @param   string  $path   The key path to get
	 * @param   array   $data   Values to replace in the message during translation
	 * @param   int     $level  The message level
	 * @return  array   The current array of messages in the session
	 */
	public static function message($file, $path = NULL, $data = NULL, $level = NULL) {
		return Message::add(__(Kohana::message(i18n::lang() . '/' . $file, $path), $data), $level);
	}

	/**
	 * Attempt to remove the accents from the given string.
	 *
	 * @param $toClean
	 *
	 * @return string
	 */
	public static function remove_accents($toClean) {
		$normalizeChars = array(
			'Š'=>'S', 'š'=>'s', 'Ð'=>'Dj','Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A',
			'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I',
			'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U',
			'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss','à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a',
			'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i',
			'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u',
			'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y', 'ƒ'=>'f'
		);
		$normalizeHtmlChars = array(
			'&Aacute;'=>'A', '&Agrave;'=>'A', '&Acirc;'=>'A', '&Atilde;'=>'A', '&Aring;'=>'A', '&Auml;'=>'A', '&AElig;'=>'AE', '&Ccedil;'=>'C',
			'&Eacute;'=>'E', '&Egrave;'=>'E', '&Ecirc;'=>'E', '&Euml;'=>'E', '&Iacute;'=>'I', '&Igrave;'=>'I', '&Icirc;'=>'I', '&Iuml;'=>'I', '&ETH;'=>'Eth',
			'&Ntilde;'=>'N', '&Oacute;'=>'O', '&Ograve;'=>'O', '&Ocirc;'=>'O', '&Otilde;'=>'O', '&Ouml;'=>'O', '&Oslash;'=>'O',
			'&Uacute;'=>'U', '&Ugrave;'=>'U', '&Ucirc;'=>'U', '&Uuml;'=>'U', '&Yacute;'=>'Y',
			'&aacute;'=>'a', '&agrave;'=>'a', '&acirc;'=>'a', '&atilde;'=>'a', '&aring;'=>'a', '&auml;'=>'a', '&aelig;'=>'ae', '&ccedil;'=>'c',
			'&eacute;'=>'e', '&egrave;'=>'e', '&ecirc;'=>'e', '&euml;'=>'e', '&iacute;'=>'i', '&igrave;'=>'i', '&icirc;'=>'i', '&iuml;'=>'i', '&eth;'=>'eth',
			'&ntilde;'=>'n', '&oacute;'=>'o', '&ograve;'=>'o', '&ocirc;'=>'o', '&otilde;'=>'o', '&ouml;'=>'o', '&oslash;'=>'o',
			'&uacute;'=>'u', '&ugrave;'=>'u', '&ucirc;'=>'u', '&uuml;'=>'u', '&yacute;'=>'y',
			'&szlig;'=>'sz', '&thorn;'=>'thorn', '&yuml;'=>'y'
		);
		$toClean = str_replace('&', '-and-', $toClean);
		//$toClean = trim(preg_replace('/[^\w\d_ -]/si', '', $toClean)); // remove all illegal chars
		$toClean = str_replace(', ', '-', $toClean);
		$toClean = str_replace(' ', '_', $toClean);
		$toClean = str_replace('--', '-', $toClean);
		//$toClean = strstr($toClean, $normalizeChars);
		//$toClean = htmlentities($toClean);



		$a = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿŔŕ';
		$b = 'aaaaaaaceeeeiiiidnoooooouuuuybsaaaaaaaceeeeiiiidnoooooouuuyybyRr';
		$toClean = utf8_decode($toClean);
		$toClean = strtr($toClean, utf8_decode($a), $b);
		$toClean = strtolower($toClean);
		$toClean = utf8_encode($toClean);

		$toClean = trim(preg_replace('/[^\w\d_ -]/si', '', $toClean));

		return $toClean;
	}

	/**
	 * Returns the number of bytes from php.ini value shorthand notation, taken from http://php.net/manual/en/function.ini-get.php
	 * @param $size_str
	 *
	 * @return int
	 */
	public static function return_bytes ($size_str) {
		switch (substr ($size_str, -1))
		{
			case 'M': case 'm': return (int)$size_str * 1048576;
			case 'K': case 'k': return (int)$size_str * 1024;
			case 'G': case 'g': return (int)$size_str * 1073741824;
			default: return $size_str;
		}
	}

	public static function send_ajax($data) {
		echo json_encode($data);
		exit;
	}

	/**
	 * Send an email.
	 *
	 * @param       $from
	 * @param       $to
	 * @param       $subject
	 * @param       $message
	 * @param null  $attachment
	 * @param array $options
	 * @param       $options['send_from_email']
	 * @param       $options['send_from_name']
	 *
	 * @throws Exception
	 * @throws Mandrill_Error
	 */
	public static function send_email($to_email, $to_name, $subject, $html_content, $options = array()) {
		require_once(ABS_ROOT . '/application/vendor/mandrill/src/Mandrill.php');

		try {
			$mandrill = new Mandrill(MANDRILL_API_KEY);

			$from_email = ( ! empty($options['send_from_email'])) ? $options['send_from_email'] : DEFAULT_FROM_EMAIL;
			$from_name = ( ! empty($options['send_from_name'])) ? $options['send_from_name'] : DEFAULT_FROM_NAME;
			$text_content = ( ! empty($options['text_content'])) ? $options['text_content'] : '';

			$message = array(
				'html' => Base::get_view('email/header') . $html_content . Base::get_view('email/footer'),
				'text' => $text_content,
				'subject' => $subject,
				'from_email' => $from_email,
				'from_name' => $from_name,
				'to' => array(
					array(
						'email' => $to_email,
						'name' => $to_name,
						'type' => 'to'
					)
				),
				//'headers' => array('Reply-To' => $from_email),
				//'important' => false,
				'track_opens' => null,
				'track_clicks' => null,
				'auto_text' => (empty($text_content)) ? TRUE : FALSE,
				/*
				'auto_html' => null,
				'inline_css' => null,
				'url_strip_qs' => null,
				'preserve_recipients' => null,
				'view_content_link' => null,
				'bcc_address' => 'message.bcc_address@example.com',
				'tracking_domain' => null,
				'signing_domain' => null,
				'return_path_domain' => null,
				'merge' => true,
				'global_merge_vars' => array(
					array(
						'name' => 'merge1',
						'content' => 'merge1 content'
					)
				),
				'merge_vars' => array(
					array(
						'rcpt' => 'recipient.email@example.com',
						'vars' => array(
							array(
								'name' => 'merge2',
								'content' => 'merge2 content'
							)
						)
					)
				),
				'tags' => array('password-resets'),
				'subaccount' => 'customer-123',
				'google_analytics_domains' => array('example.com'),
				'google_analytics_campaign' => 'message.from_email@example.com',
				'metadata' => array('website' => 'www.example.com'),
				'recipient_metadata' => array(
					array(
						'rcpt' => 'recipient.email@example.com',
						'values' => array('user_id' => 123456)
					)
				),
				'attachments' => array(
					array(
						'type' => 'text/plain',
						'name' => 'myfile.txt',
						'content' => 'ZXhhbXBsZSBmaWxl'
					)
				),
				'images' => array(
					array(
						'type' => 'image/png',
						'name' => 'IMAGECID',
						'content' => 'ZXhhbXBsZSBmaWxl'
					)
				)
				*/
			);

			$result = $mandrill->messages->send($message);

			if ( ! empty($result[0]['status']) && $result[0]['status'] == 'sent') {
				Message::add('Email with subject "' . $subject . '" sent from ' . $from_email . ' to ' . $to_email, Message::$debug);
			} else {
				Base::message('base', 'email_error', array('%subject%' => $subject, '%from%' => $from_email, '%to' => $to_email), Message::$error);
			}

			/*
			Array
			(
				[0] => Array
					(
						[email] => recipient.email@example.com
						[status] => sent
						[reject_reason] => hard-bounce
						[_id] => abc123abc123abc123abc123abc123
					)

			)
			*/
		} catch(Mandrill_Error $e) {
			// Mandrill errors are thrown as exceptions
			echo 'A mandrill error occurred: ' . get_class($e) . ' - ' . $e->getMessage();
			// A mandrill error occurred: Mandrill_Unknown_Subaccount - No subaccount exists with the id 'customer-123'
			throw $e;
			return FALSE;
		}

		return ( ! empty($result[0]['status']) && $result[0]['status'] == 'sent') ? TRUE : FALSE;
	}

	/**
	 * this function performs all of the setup that takes place when a user logs in (or when they click on reload session)
	 */
	public static function set_environment($user) {
		$session = & Session::instance()->as_array();

		// add the remembered tab settings
		$session['tabs'] = array();
		//foreach (array('tabs', 'client_view_tabs', 'global_tabs') as $tab_name) {
		//	$session['tabs'][$tab_name] = $user->setting($tab_name);
		//}

		// add some other frequently used stuff to the session
		$remember_username = Cookie::get('username', NULL);
		$session['remember_device_username_flag'] = ($user->username == $remember_username) ? TRUE : FALSE;
		$authautologin = Cookie::get('authautologin', FALSE);
		$session['remember_device_login_flag'] = ($authautologin) ? TRUE : FALSE;
	}

	/**
	 * set the saved parameter with the given value
	 *
	 * @param        $parameter_name
	 * @param        $value
	 * @param string $type
	 */
	public static function set_smart_parameter($parameter_name, $value, $type = 'user') {$session =& Session::instance()->as_array();
		if ($type == 'user') {
			$source = $session['auth_user'];
		} else if ($type == 'company') {
			$source = Wine::company(); // todo: implement this
		}
		$source->setting($parameter_name, $value);
	}

	/*
	 * Update the generic table log table with an entry related to another table record
	 */
	public static function update_log($table_name, $table_id, $title, $details = NULL) {
		//try {
		$log_entry = ORM::factory('global_table_log');
		$log_entry->table_name = $table_name;
		$log_entry->table_id = $table_id;
		$log_entry->log_title = $title;
		$log_entry->log_details = $details;
		$log_entry->save();
		//} catch (Exception $e) {

		//}
	}
}