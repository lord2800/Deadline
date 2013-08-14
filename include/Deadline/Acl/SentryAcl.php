<?php
namespace Deadline\Acl;

use Deadline\Acl,
	Deadline\IStorage,
	Deadline\DatabaseHandle;

use Cartalyst\Sentry\Users\Eloquent\Provider as UserProvider,
	Cartalyst\Sentry\Groups\Eloquent\Provider as GroupProvider,
	Cartalyst\Sentry\Throttling\Eloquent\Provider as ThrottleProvider,
	Cartalyst\Sentry\Facades\Native\Sentry as NativeSentry,
	Cartalyst\Sentry\Sentry,
	Cartalyst\Sentry\Hashing\NativeHasher,
	Cartalyst\Sentry\Sessions\NativeSession,
	Cartalyst\Sentry\Cookies\NativeCookie,
	Cartalyst\Sentry\Users\UserNotFoundException;

class SentryAcl extends Acl {
	private $sentry;

	private function randomPassword($length = 42) {
		$pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		return substr(str_shuffle(str_repeat($pool, 5)), 0, $length);
	}

	public function __construct(DatabaseHandle $dbh, IStorage $store) {
		$pdo = $dbh->get($store->get('acl_database', 'primary'));
		NativeSentry::setupDatabaseResolver($pdo);
		$hasher = new NativeHasher();
		$userProvider = new UserProvider($hasher);
		$groupProvider = new GroupProvider;
		$throttleProvider = new ThrottleProvider($userProvider);
		$session = new NativeSession();
		$cookie = new NativeCookie();
		$this->sentry = new Sentry($userProvider, $groupProvider, $throttleProvider, $session, $cookie);

		if(!$this->authed()) {
			try {
				$anonymous = $this->sentry->getUserProvider()->findByLogin('anonymous@example.com');
			} catch(UserNotFoundException $e) {
				$permissions = $store->get('anonymousPermissions', []);
				$anonymous = $this->sentry->register([
					'email' => 'anonymous@example.com',
					'password' => password_hash($this->randomPassword(), PASSWORD_DEFAULT),
					'permissions' => $permissions
				], true);
			}

			$this->sentry->login($anonymous, false);
		}
	}
	public function authed() {
		return $this->sentry->check() && $this->getUserId() != $this->sentry->getUserProvider()->findByLogin('anonymous@example.com')->getId();
	}
	public function hasPermission($call) { return $this->sentry->getUser()->hasAccess($call); }
	public function getUserId() { return $this->sentry->getUser()->getId(); }
	public function activate($code) { return $this->sentry->attemptActivation($code); }
	public function resetPassword($code, $newPassword) { return $this->sentry->attemptResetPassword($code, $newPassword); }
	public function register(array $params, $activate = false) {
		$this->sentry->register($params);
		if($activate) {
			return $this->activate($this->sentry->getUser()->getActivationCode());
		}
		return $code;
	}
	public function login($credentials) {
		try {
			return $this->sentry->authenticate(['login' => $credentials['login'], 'password' => $credentials['password']], $credentials['remember']);
		} catch(\Exception $e) {
			return $e;
		}
	}
	public function logout() {
		return $this->sentry->logout();
	}
}
