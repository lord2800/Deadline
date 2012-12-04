<?php

class home {
	public function index($request, $args, $response) {
		$response->setCacheControl();
		$response->setEtag('123abc');
		$response->setModifiedTime(1353628608);
		$response->view->template = 'index.tal';
		$response->view->pagetitle = 'Deadline > Index';
		$response->view->title = 'Deadline CMS';
		$response->view->content = 'success!';
		$this->dumpVars($request, $args, $response);
	}
	public function test($request, $args, $response) {
		$response->view->template = 'index.tal';
		$response->view->pagetitle = 'Deadline > Index';
		$response->view->title = 'Deadline CMS';
		$response->view->content = 'ID: ' . $args['id'];
		$this->dumpVars($request, $args, $response);
	}
	public function dumpVars($request, $args, $response) {
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
		$response->view->variables = ob_get_contents();
		ob_end_clean();
	}
}

?>