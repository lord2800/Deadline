<?php
namespace Deadline;

use R;

require_once('fix-realpath.php');
require_once('autoload.php');

function main() {
	$router = new Router();
	$router->load('deadline://routes.json');

	$request = new Request($_SERVER);
	$response = new HttpResponse($request);
	list($handler, $args) = $router->find($request);

	if($handler == null) {
		// maybe it's a file?
		$path = 'deadline://' . substr($request->path, 1);
		if(file_exists($path)) {
			// serve the file instead
			// TODO replace this nonsense with something more appropriate, like a FileView or something
			$mimes = array('js' => 'text/javascript', 'css' => 'text/css', 'jpg' => 'image/jpeg', 'png' => 'image/png');
			$ext = pathinfo($path, PATHINFO_EXTENSION);
			if(!array_key_exists($ext, $mimes)) die('Mime not found for ' . $ext);
			header('Status: 200 OK');
			header('Content-Type: ' . $mimes[$ext]);
			readfile($path);
			die();
		} else {
			\Error::error404($request, $response);
		}
	} else {
		$store = new Storage();
		$store->load('deadline://settings.json');

		if($store->get('live')) {
			ob_start();
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

		$response->prepare($request);
		if($store->get('live')) {
			ob_end_clean();
		}
		if($view != null) {
			$response->output($view);
		}
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
