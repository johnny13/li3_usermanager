<?php

/**
 * @copyright Copyright 2012, Djordje Kovacevic (http://djordjekovacevic.com)
 * @license   http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_usermanager\controllers;

use li3_usermanager\extensions\controllers\AccessController;
use li3_usermanager\models\Users;
use li3_usermanager\models\UserGroups;
use li3_usermanager\models\UserActivations;

class ManageUsersController extends AccessController {

	protected $_viewAs = 'backend-component';

	protected function _init() {
		parent::_init();
		$this->response->cache(false);
	}

	/**
	 * List all users
	 */
	public function backend_index() {
		$users = Users::all(array('with' => 'UserGroups'));
		return compact('users');
	}

	/**
	 * Add new user (instantly activated)
	 */
	public function backend_add() {
		$user = null;
		if ($this->request->data) {
			$user = Users::create($this->request->data);
			if ($user->save()) {
				return $this->redirect(array(
					'li3_usermanager.ManageUsers::index', 'backend' => true
				));
			}
		}
		return compact('user');
	}

	/**
	 * Promote user to other user group
	 */
	public function backend_promote() {
		if ($id = $this->request->params['id']) {
			$groups = array();
			$manageUsers = array('li3_usermanager.ManageUsers::index', 'backend' => true);
			foreach (UserGroups::all() as $group) {
				$groups[$group->id] = $group->slug;
			}
			$user = Users::first(array('conditions' => compact('id')));
			if ($this->request->data) {
				$user->user_group_id = $this->request->data['user_group_id'];
				if ($user->save()) {
					return $this->redirect($manageUsers);
				}
			}
			if ($user) {
				return compact('user', 'groups');
			}
		}
		return $this->redirect($manageUsers);
	}

	/**
	 * Activate user
	 */
	public function backend_activate() {
		$success = false;
		$user = null;
		if ($id = $this->request->params['id']) {
			$user = Users::first(array('conditions' => compact('id')));
			if ($user && !$user->active) {
				$user->active = 1;
				$success = $user->save();
				if ($success) {
					$activation = UserActivations::first(array(
						'conditions' => array('user_id' => $id)
					));
					if ($activation) {
						$activation->delete();
					}
				}
			}
		}
		return compact('success', 'user');
	}

	/**
	 * @return array
	 */
	public function backend_deactivate() {
		$success = false;
		$user = null;
		if ($id = $this->request->params['id']) {
			$user = Users::first(array('conditions' => compact('id')));
			if ($user && $user->active) {
				$user->active = 0;
				$success = $user->save();
			}
		}
		return compact('success', 'user');
	}

	/**
	 * Destroy user with all related data if not in `root` group
	 */
	public function backend_destroy() {
		$destroyed = false;
		$user = null;
		if ($id = $this->request->params['id']) {
			$user = Users::first(array(
				'conditions' => array('Users.id' => $id),
				'with' => array('AboutUsers', 'PasswordResets', 'UserActivations')
			));
			if ($user) {
				if ($user->about_user->user_id) {
					$user->about_user->delete();
				}
				if ($user->user_activation->user_id) {
					$user->user_activation->delete();
				}
				foreach($user->password_resets as $password_reset) {
					if ($password_reset->user_id) {
						$password_reset->delete();
					}
				}
				$destroyed = $user->delete();
			};
		}
		return compact('destroyed', 'user');
	}

}

?>