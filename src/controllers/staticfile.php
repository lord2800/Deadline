<?php

class StaticFile {
	private $disallow;
	public function __construct() {
		if(file_exists('deadline://disallow.json')) {
			$disallow = json_decode(file_get_contents('deadline://disallow.json'), true);
			if($disallow === null) $disallow = array();
		} else {
			$disallow = array();
		}
		$this->disallow = array_merge(array(
			// default types to not allow access to
			'php',
			'json',
			'tal',
			'cache'
		), $disallow);
	}
	public function file($request, $args, $response) {
		// $request->path already contains a leading /
		$path = 'deadline:/' . $request->path;
		$ext = pathinfo($path, PATHINFO_EXTENSION);
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
