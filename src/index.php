<?php
namespace Deadline;

use R;

ob_start();

require_once('fix-realpath.php');
require_once('autoload.php');

function main() {
	$store = new Storage();
	$store->load('deadline://settings.json');
	$router = new Router();
	$router->load('deadline://routes.json');

	$request = new Request($_SERVER);
	$response = new HttpResponse($request);
	list($handler, $args) = $router->find($request);

	if($handler == null) {
		// try serving it as a static file instead
		$handler = new Container(array('controller' => 'StaticFile', 'method' => 'file'));
	}


	$db = $store->get('database');
	R::setup($db->dsn, $db->user, $db->pass);
	R::freeze($store->get('live'));

	$security = $store->get('security');
	User::init($security->algo, $security->costFactor);


	if($store->get('live')) {
		set_exception_handler(function ($e) use($request, $response) {
			\Error::error500($request, $response);
		});
	}

	$class = $handler->controller;
	$method = $handler->method;

	$controller = new $class();
	if(method_exists($controller, 'setup')) {
		$controller->setup($request, $args);
	}
	$view = $controller->$method($request, $args, $response);
	if(method_exists($controller, 'finish')) {
		$controller->finish($view, $response);
	}

	$response->prepare($request, $view);
	if($store->get('live')) {
		ob_end_clean();
	}
	if($view != null) {
		$response->output($view);
	}
}

function run_shutdown() {
	// did we have an exception?
	$e = error_get_last();
	if($e != null) {
		// are we on a live site?
		if(false) {
			// display a hardcoded 'oops' page
		} else {
			// display diagnostic page
		}
	}
}

main();

?>
