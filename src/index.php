<?php
namespace Deadline;

use R;
use Analog;

ob_start();

require_once('fix-realpath.php');
require_once('autoload.php');

function main() {
	$store = new Storage();
	$store->load('deadline://settings.json');
	$router = new Router();
	$router->load('deadline://routes.json');

	error_reporting($store->get('live', false) ? 0 : -1);

	$request = new Request($_SERVER);
	$response = new HttpResponse($request);
	list($handler, $args) = $router->find($request);

	if($handler == null) {
		// try serving it as a static file instead
		$handler = new Container(array('controller' => 'StaticFile', 'method' => 'file'));
	}

	$db = $store->get('database', (object)array('dsn'=>'sqlite::memory:','user'=>null,'pass'=>null));
	R::setup($db->dsn, $db->user, $db->pass);
	R::freeze($store->get('live', false));

	$security = $store->get('security', (object)array('algo'=>'Blowfish', 'costFactor'=>7));
	User::init($security->algo, $security->costFactor);

	if($store->get('live', false)) {
		set_exception_handler(function ($e) { run_shutdown(); });
	}

	$class = $handler->controller;
	$method = $handler->method;
	$view = null;

	try {
		$controller = new $class();
		if(method_exists($controller, 'setup')) {
			$controller->setup($request, $args);
		}
		$view = $controller->$method($request, $args, $response);
		if(method_exists($controller, 'finish')) {
			$controller->finish($response, $view);
		}
	} catch(AuthorizationException $e) {
		$view = $response->getView('error');
		$view->setCode(403);
	} catch(Exception $e) {
		$view = $response->getView('error');
		$view->setCode(500);
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
		// write the error to a log
		Analog::log(json_encode($e));
		// are we on a live site?
		if(Storage::current()->get('live', false)) {
			// display a hardcoded 'oops' page
		} else {
			// display diagnostic page
		}
	}
}

main();
