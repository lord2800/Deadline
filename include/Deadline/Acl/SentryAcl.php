<?php
namespace Deadline\Acl;

use \PDO;

use Deadline\App,
	Deadline\Acl,
	Deadline\IStorage,
	Deadline\DeadlineStreamWrapper;

use Cartalyst\Sentry\Users\Eloquent\Provider as UserProvider,
	Cartalyst\Sentry\Groups\Eloquent\Provider as GroupProvider,
	Cartalyst\Sentry\Throttling\Eloquent\Provider as ThrottlingProvider,
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

	public function __construct(App $app, IStorage $store) {
		$default = [
			'debug'      => ['dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=test', 'user' => 'root', 'pass' => ''],
			'debug'      => ['dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=test', 'user' => 'root', 'pass' => '']
		];
		$settings = $store->get('connection_settings', $default)[$app->mode()];

		$pdo = new PDO($settings['dsn'], $settings['user'], $settings['pass']);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		NativeSentry::setupDatabaseResolver($pdo);
		$hasher = new NativeHasher();
		$userProvider = new UserProvider($hasher);
		$groupProvider = new GroupProvider;
		$throttleProvider = new ThrottlingProvider($userProvider);
		$session = new NativeSession();
		$cookie = new NativeCookie();
		$this->sentry = new Sentry($userProvider, $groupProvider, $throttleProvider, $session, $cookie);

		if(!$this->sentry->check()) {
			try {
				$anonymous = $this->sentry->getUserProvider()->findByLogin('anonymous@example.com');
			} catch(UserNotFoundException $e) {
				$anonymous = $this->sentry->register([
					'email' => 'anonymous@example.com',
					'password' => password_hash($this->randomPassword(), PASSWORD_DEFAULT),
					'permissions' => $store->get('anonymousPermissions', [])
				], true);
			}

			$this->sentry->login($anonymous, false);
		}
	}
	public function hasPermission($call) {
		return $this->sentry->getUser()->hasAccess($call);
	}
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
