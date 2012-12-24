<?php

require_once('autoload.php');

use Deadline\Storage;
use Deadline\User;

class Install {
	private function configureSettings($dsn, $user, $pass, $title, $tagline, $image) {
		$store = new Storage();
		$store->set('database', (object)array('dsn' => $dsn, 'user' => $user, 'pass' => $pass));
		$store->set('live', false);
		$store->set('template', 'deadline');
		$store->set('siteTitle', $title);
		$store->set('siteTagline', $tagline);
		$store->set('headerImage', $image);
		R::setup($dsn, $user, $pass);
	}

	private function addUser($name, $display, $email, $pass) {
		User::register($name, $display, $email, $pass);
	}

	public function webInstall($request, $args, $response) {
		if($request->verb == 'GET') {
			$view = $response->getView();
			$view->template = 'install.tal';
			$view->hideSignonBox = true;
			return $view;
		} else if($request->verb == 'POST') {
			$settings = $request->input['post']['settings'];
			static::cliInstall($settings);
			$response->redirect('/');
		}
	}

	public static function cliInstall($settings) {
		$this->configureSettings($settings['dsn'], $settings['dbuser'], $settings['dbpass'], $settings['title'], $settings['tagline'], $settings['header']);
		$this->addUser($settings['user'], $settings['displayName'], $settings['email'], $settings['pass']);
		$router = new Router();
		$router->load('deadline://routes.json');
		$router->remove('/install');
		$router->save('deadline://routes.json');
	}
}
