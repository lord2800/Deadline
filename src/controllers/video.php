<?php

use FilesystemIterator as FS;

class Video {
	public function videos($request, $args, $response) {
		$path = $response->getStorage()->get('videoPath');
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FS::NEW_CURRENT_AND_KEY | FS::SKIP_DOTS));
		$response->view->template = 'video/list.tal';

		$videos = array();
		foreach($files as $file) {
			$videos[] = array(
				'url' => rawurlencode(substr($file->getPathname(), strlen($path)+1)),
				'name' => $file->getFilename()
			);
		}
		usort($videos, function($a, $b) { return strnatcmp($a['name'], $b['name']); });

		$response->view->videos = $videos;
	}
	public function show($request, $args, $response) {
		$file = $request->input['get']['f'];
		$path = $response->getStorage()->get('videoPath');
		$path = $path . '/' . rawurldecode($file);
		if(file_exists($path)) {
			$f = new \finfo();
			$mime = $f->file($path, FILEINFO_MIME_TYPE);
			$video = array(
				'source' => 'link://video/file/?f=' . rawurlencode($file),
				'type' => substr($mime, strpos($mime, '/')+1)
			);
			$response->view->template = 'video/view.tal';
			$response->view->videos = array($video);
		}
	}
	public function file($request, $args, $response) {
		$file = $request->input['get']['f'];
		$path = $response->getStorage()->get('videoPath');
		$response->setFileDownload($path . '/' . rawurldecode($file));
	}
}

?>