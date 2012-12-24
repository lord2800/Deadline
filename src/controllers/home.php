<?php

class Home {
	public function start($request, $args, $response) {
		$view = $response->getView('html');
		$view->template = 'index.tal';
		$view->title = 'Home';
		return $view;
	}
}
