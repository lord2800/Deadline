<?php

class StaticFile {
	public function file($request, $args, $response) {
		// $request->path already contains a leading /
		$path = 'deadline:/' . $request->path;
		if(file_exists($path)) {
			$response->setModifiedTime(filemtime($path));
			$response->setEtag(md5_file($path));
			$response->setCacheControl('public', 315360000);
			$view = new FileView();
			$view->setFile($path);
			return $view;
		}
		return null;
	}
}

?>