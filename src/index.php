<?php
namespace Deadline;

use R;

if(array_key_exists('XDEBUG_DISABLE', $_GET)) {
	xdebug_disable();
}

require_once('fix-realpath.php');
require_once('autoload.php');

function main() {
	$store = new Storage();
	$store->load('deadline://settings.json');

	if($store->get('live')) {
		ob_start();
	}

	$db = $store->get('database');
	R::setup($db->dsn, $db->user, $db->pass);
	R::freeze($store->get('live'));

	$router = new Router();
	$router->load('deadline://routes.json');

	$request = new Request($_SERVER);
	$response = new HttpResponse($store, $request);

	list($handler, $args) = $router->find($request);
	if($handler == null) {
		\Error::error404($request, null, $response);
	} else {
		if($store->get('live')) {
			set_exception_handler(function ($e) use($request, $response) {
				\Error::error500($request, null, $response);
			});
		}

		$class = $handler->controller;
		$method = $handler->method;

		$controller = new $class();
		if(method_exists($controller, 'setup')) {
			$controller->setup($request, $args);
		}
		$controller->$method($request, $args, $response);
		if(method_exists($controller, 'finish')) {
			$controller->finish($response);
		}

		$response->prepare($request);
		if($store->get('live')) {
			ob_end_clean();
		}
		$response->output();
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
