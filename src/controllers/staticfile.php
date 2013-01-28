<?php

use Deadline\App;

class StaticFile {
	private $disallow;
	public function __construct() {
		$disallow = array();
		if(file_exists('deadline://disallow.json')) {
			$disallow = json_decode(file_get_contents('deadline://disallow.json'), true);
		}
		$this->disallow = array_merge(array(
			// default types to not allow access to
			'php',
			'json',
			'tal',
			'cache'
		), $disallow);
	}
	public function file($args) {
		// $request->path already contains a leading /
		$path = 'deadline:/' . App::request()->path;
		$ext = pathinfo($path, PATHINFO_EXTENSION);
		$response = App::response();
		if(in_array($ext, $this->disallow)) {
			$view = $response->getView('error');
			$view->setCode(404);
			return $view;
		}
		if(file_exists($path) && !is_dir($path)) {
			$response->setModifiedTime(filemtime($path));
			$response->setEtag(md5_file($path));
			$response->setCacheControl('public', 315360000);
			$view = $response->getView('file');
			$view->setFile($path);
			return $view;
		} else {
			$view = $response->getView('error');
			$view->setCode(404);
			return $view;
		}
	}
}
