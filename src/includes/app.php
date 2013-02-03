<?php
namespace Deadline;

use R;
use Analog;

class App {
	private static $app;
	public static function init($settings = 'deadline://settings.json') {
		Analog::log('App startup; settings file: ' . $settings, Analog::DEBUG);
		register_shutdown_function(function ($start) {
			$ms = (microtime(true) - $start) * 1000;
			$sizes = array('', 'K', 'M', 'G', 'T');
			$size = 0;
			$count = memory_get_peak_usage();
			while($count > 1024) { $size++; $count /= 1024; }
			Analog::log('Page built in ' . number_format($ms, 3) . 'ms, used '
						. number_format($count, 3) . $sizes[$size] . 'B memory', Analog::DEBUG);
		}, microtime(true));
		date_default_timezone_set('UTC');

		$app = new App();
		static::$app = $app;

		$app->storage = new Storage();
		$app->router = new Router();
		$app->request = new Request($_SERVER);
		$app->response = new HttpResponse($app->request);

		$app->storage->load($settings);
		$app->cache = new Cache($app->storage->get('cache', (object)array('type' => 'apc', 'dsn' => '')));
		$app->router->load($app->storage->get('routeFile', 'deadline://routes.json'));


		$db = array(
			'live' => array('dsn' => 'sqlite::memory:', 'user' => null, 'pass' => null),
			'production' => array('dsn' => 'sqlite::memory:', 'user' => null, 'pass' => null)
		);
		$db = $app->storage->get('database', (object)$db);
		$mode = App::live() ? 'live' : 'production';
		R::setup($db->$mode->dsn, $db->$mode->user, $db->$mode->pass);
		R::freeze(App::live());

		$security = $app->storage->get('security', (object)array('costFactor'=>7));
		User::init($security->costFactor);

		error_reporting(-1);
		if(App::live()) {
			set_exception_handler(function ($e) use($app) { $app->shutdown(); die(); });
		}
		register_shutdown_function(function () use($app) { $app->shutdown(); });

		return static::$app;
	}
	public static function current() { return static::$app; }

	public static function live()     { return static::store()->get('live', false); }
	public static function request()  { return static::current()->request; }
	public static function response() { return static::current()->response; }
	public static function store()    { return static::current()->storage; }
	public static function router()   { return static::current()->router; }
	public static function cache()    { return static::current()->cache; }

	private $storage, $router, $cache, $request, $response;
	private function __construct() {}

	public function run() {
		list($handler, $args) = $this->router->find($this->request);

		if($handler == null) {
			// try serving it as a static file instead
			$handler = new Container(array('controller' => 'StaticFile', 'method' => 'file'));
		}
		$class = $handler->controller;
		$method = $handler->method;
		$view = null;

		try {
			$controller = new $class();
			if(method_exists($controller, 'setup')) { $controller->setup(); }
			if(method_exists($controller, $method)) { $view = $controller->$method($args); }
			else { throw new Exception('No route for controller ' . $class . '::' . $method); }
			if(method_exists($controller, 'finish')) { $controller->finish($view); }
		} catch(AuthorizationException $e) {
			Analog::log($e->getMessage());
			$view = $response->getView('error');
			$view->setCode(403);
		} catch(Exception $e) {
			Analog::log($e->getMessage());
			$view = $response->getView('error');
			$view->setCode(500);
		}

		$this->response->prepare($view);
		if(App::live()) { ob_end_clean(); }
		if($view != null) { $this->response->output($view); }
	}

	private function shutdown($e = null) {
		Autosave::save();

		$e = $e or error_get_last();
		if($e != null) {
			Analog::log(json_encode($e));
			if($this->live()) {
				// display a hardcoded 'oops' page
			} else {
				// display diagnostic page
			}
		}
	}
}
