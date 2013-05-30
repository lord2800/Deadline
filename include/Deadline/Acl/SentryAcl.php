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

		if(!$this->sentry->check()) {
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
	public function hasPermission($call) { return $this->sentry->getUser()->hasAccess($call); }
	public function getUser() { return $this->sentry->getUser(); }
	public function login($credentials) {
		try {
			return $this->sentry->authenticateAndRemember($credentials);
		} catch(\Exception $e) {
			return null;
		}
	}
	public function logout() {
		return $this->sentry->logout();
	}
}
