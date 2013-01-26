<?php defined('SYSPATH') or die ('No direct script access.');

/**
 * Public controller for private/admin pages.
 */
class Controller_CL4_Private extends Controller_Base {
	/**
	 * Controls access for the whole controller.
	 * If the entire controller REQUIRES that the user be logged in, set this to TRUE.
	 * If some or all of the controller DOES NOT need to be logged in, set to this FALSE; to control which actions require authentication or a specific permission, us the $secure_actions array.
	 * By default, all Private Controllers are auth required.
	 */
	public $auth_required = TRUE;

	/**
	 * Called before the action.
	 * Does everything else in the parent before()'s and also adds the admin CSS.
	 */
	public function before() {
		parent::before();

		if ($this->auto_render) {
			$this->add_style('private', 'css/private.css');
		}
	} // function before
}