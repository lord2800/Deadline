<?php

class home {
	public function index($request, $args, $response) {
		$response->setCacheControl();
		$response->setEtag('123abc');
		$response->setModifiedTime(1353628608);

		$view = $response->getView($request);
		$view->template = 'index.tal';
		$view->pagetitle = 'Deadline > Index';
		$view->title = 'Deadline CMS';
		$view->content = 'success!';
		$this->dumpVars($view, $request, $args, $response);
		return $view;
	}
	public function test($request, $args, $response) {
		$view = $response->getView($request);
		$view->template = 'index.tal';
		$view->pagetitle = 'Deadline > Index';
		$view->title = 'Deadline CMS';
		$view->content = 'ID: ' . $args['id'];
		$this->dumpVars($view, $request, $args, $response);
		return $view;
	}
	public function dumpVars($view, $request, $args, $response) {
		ob_start();
		echo "Request object:<br />";
		var_dump($request);
		echo "Request arguments:<br />";
		var_dump($args);
		echo "Response object:<br />";
		var_dump($response);
		echo "Session variables:<br />";
		var_dump($_SESSION);
		echo "Server variables:<br />";
		var_dump($_SERVER);
		$view->variables = ob_get_contents();
		ob_end_clean();
	}
}

?>