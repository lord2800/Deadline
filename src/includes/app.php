<?php
namespace Deadline;

use R;
use Analog;

class App {
	private static $app;
	public static function init($settings = 'deadline://settings.json', $routes = 'deadline://routes.json') {
		Analog::log('App startup; settings file: ' . $settings . ', routes file: ' . $routes, Analog::DEBUG);
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
		$app->router->load($routes);

		$db = array(
			'live' => array('dsn' => 'sqlite::memory:', 'user' => null, 'pass' => null),
			'production' => array('dsn' => 'sqlite::memory:', 'user' => null, 'pass' => null)
		);
		$db = $app->storage->get('database', (object)$db);
		$mode = App::live() ? 'live' : 'production';
		R::setup($db->$mode->dsn, $db->$mode->user, $db->$mode->pass);
		R::freeze(App::live());

		$security = $app->storage->get('security', (object)array('algo'=>'Blowfish', 'costFactor'=>7));
		User::init($security->algo, $security->costFactor);

		error_reporting(-1);
		if(App::live()) {
			set_exception_handler(function ($e) use($app) { $app->shutdown(); });
		}
		register_shutdown_function(function () use($app) { $app->shutdown(); });

		return static::$app;
	}
	public static function current() { return static::$app; }

	public static function live() { return static::store()->get('live', false); }
	public static function request() { return static::current()->request; }
	public static function response() { return static::current()->response; }
	public static function store() { return static::current()->storage; }
	public static function router() { return static::current()->router; }

	private $storage, $router, $request, $response;
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
		$e = $e or error_get_last();
		if($e != null) {
			Analog::log(json_encode($e));
			if(App::live()) {
				// display a hardcoded 'oops' page
			} else {
				// display diagnostic page
			}
		}
	}
}
