<?php
namespace Deadline;

use R;

class User extends \RedBean_SimpleModel {
	private static $userBean = 'user';
	private static $roleBean = 'role';

	private static $cost = 7, $algo = 'Blowfish';
	public static function current() {
		$id = array_key_exists('id', $_SESSION) ? $_SESSION['id'] : null;
		$user = R::load(static::$userBean, $id);
		return $user;
	}
	public static function init($algo = 'Blowfish', $cost = 7) {
		Password::init($algo);
		static::$algo = $algo;
		static::$cost = $cost;

		session_cache_limiter('');
		$params = session_get_cookie_params();
		session_set_cookie_params(0, $params['path'], $params['domain'], $params['secure'], true);
		session_start();

		// hack to force the session gc to not trigger for this session
		$_SESSION['hack_forceupdate'] = time();
	}
	public static function identify($name, $pass, $remember = false) {
		/*$last = 0;
		$now = time();
		if(array_key_exists('last_attempt', $_SESSION)) {
			$last = $_SESSION['last_attempt'];
		}
		$diff = $now - $last;

		if($diff > 30) {
			$_SESSION['failed'] = 0;
		}

		if($_SESSION['failed'] >= 3) {
			return false;
		}*/

		$user = static::find($name);
		if($user == null) {
			return false;
		}

		if(Password::current()->verify($pass, $user->pass)) {
			if(Password::current()->need_rehash($user->pass, static::$algo, static::$cost)) {
				$user->pass = Password::current()->hash($pass, static::$cost);
			}
			$_SESSION['id'] = $user->id;
			$params = session_get_cookie_params();
			$lifetime = $remember ? 4147200 : 0;
			session_set_cookie_params($lifetime, $params['path'], $params['domain'], $params['secure'], true);
			session_regenerate_id(true);
			return true;
		} else {
			/*if(array_key_exists('failed', $_SESSION)) {
				$_SESSION['failed']++;
			} else {
				$_SESSION['failed'] = 1;
			}
			$_SESSION['last_attempt'] = time();*/
			return false;
		}
	}
	public static function register($username, $display, $email, $pass) {
		$user = R::dispense(static::$userBean);
		$user->username = $username;
		$user->displayName = $display;
		$user->email = $email;
		$user->pass = Password::current()->hash($pass, static::$cost);
		$user->created = R::isoDateTime();
		$role = static::getRole('user');
		$user->sharedRole = array($role);
		return R::store($user);
	}
	public static function find($name, $displayAlso = false) {
		$user = null;
		if(is_numeric($name)) {
			$user = R::load(static::$userBean, $name);
		} else if(is_string($name)) {
			$user = R::findOne(static::$userBean, 'username = ?', array($name));
			if($user == null && $displayAlso) {
				$user = R::findOne(static::$userBean, 'displayName = ?', array($name));
			}
		}
		return $user;
	}

	public static function getRole($role, $make = true) {
		$r = R::findOne(static::$roleBean, 'name = ?', array($role));
		if(!$r && $make) {
			$r = R::dispense(static::$roleBean);
			$r->name = $role;
		}
		return $r;
	}

	public function getRoles() {
		$roleList = $this->bean->sharedRole;
		$roles = array();
		foreach($roleList as $role) {
			$roles[] = $role->name;
		}
		return $roles;
	}
	public function checkRole($role) {
		return in_array($role, $this->getRoles());
	}
	public function addRole($role) {
		if(is_string($role)) {
			$roleName = $role;
			$role = static::getRole($role);
		} else {
			$roleName = $role->name;
		}
		if(!$this->checkRole($roleName)) {
			$this->bean->sharedRole[] = $role;
			R::store($this->bean);
		}
	}
	public function removeRole($role) {
		if(is_string($role)) {
			$role = static::getRole($role);
		} else {
			$roleName = $role->name;
		}
		if($this->checkRole($roleName)) {
			$this->bean->sharedRole = array_filter($this->bean->sharedRole, function ($r) use($role) { return $role->name == $r->name; });
			R::store($this);
		}
	}
}