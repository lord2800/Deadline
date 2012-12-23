<?php

class Error {
	public static function error404($request, $response) {
		$view = $response->getView();
		$view->template = 'error404.tal';
		$view->pagetitle = 'Deadline > Not Found';
		$view->title = 'Oops.';
		$view->content = "I couldn't find anything at '{$request->path}'.";
		ob_start();
		var_dump($request);
		var_dump($_SERVER);
		$view->variables = ob_get_contents();
		ob_end_clean();
		$response->prepare($request);
		$response->output($view);
	}
	public static function error500($request, $response) {
		$view = $response->getView();
		$view->template = 'error500.tal';
		$view->pagetitle = 'Deadline > Not Found';
		$view->title = '';
		$view->content = 'Hey, I just met you and this is crazy, but an error just happened, so retry maybe?';
		ob_start();
		var_dump($request);
		var_dump($_SERVER);
		$view->variables = ob_get_contents();
		ob_end_clean();
		$response->prepare($request);
		$response->output($view);
	}
}
