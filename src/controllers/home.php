<?php

use Deadline\App;

class Home {
	public function start($args) {
		$view = App::response()->getView('html');
		$view->template = 'index.tal';
		$view->title = 'Home';
		return $view;
	}
}
