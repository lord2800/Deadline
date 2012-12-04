<?php

class Error {
	public static function error404($request, $args, $response) {
		$response->view->template = 'error404.tal';
		$response->view->pagetitle = 'Deadline > Not Found';
		$response->view->title = 'Oops.';
		$response->view->content = "I couldn't find anything at '{$request->path}'.";
		ob_start();
		var_dump($request);
		var_dump($args);
		var_dump($_SERVER);
		$response->view->variables = ob_get_contents();
		ob_end_clean();
		$response->prepare($request);
		$response->output();
	}
	public static function error500($request, $args, $response) {
		$response->view->template = 'error500.tal';
		$response->view->pagetitle = 'Deadline > Not Found';
		$response->view->title = '';
		$response->view->content = 'Hey, I just met you and this is crazy, but an error just happened, so retry maybe?';
		ob_start();
		var_dump($request);
		var_dump($args);
		var_dump($_SERVER);
		$response->view->variables = ob_get_contents();
		ob_end_clean();
		$response->prepare($request);
		$response->output();
	}
}

?>