<?php

use FilesystemIterator as FS;
use Deadline\Storage;

class Video {
	public function videos($request, $args, $response) {
		$path = Storage::current()->get('videoPath');
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FS::NEW_CURRENT_AND_KEY | FS::SKIP_DOTS));

		$view = $response->getView();
		$view->template = 'video/list.tal';

		$videos = array();
		foreach($files as $file) {
			$videos[] = array(
				'url' => rawurlencode(substr($file->getPathname(), strlen($path)+1)),
				'name' => $file->getFilename()
			);
		}
		usort($videos, function($a, $b) { return strnatcmp($a['name'], $b['name']); });

		$view->videos = $videos;
		return $view;
	}
	public function show($request, $args, $response) {
		$file = $request->input['get']['f'];
		$path = Storage::current()->get('videoPath');
		$path = $path . '/' . rawurldecode($file);
		if(file_exists($path)) {
			$f = new \finfo();
			$mime = $f->file($path, FILEINFO_MIME_TYPE);
			$video = array(
				'source' => 'link://video/file/?f=' . rawurlencode($file),
				'type' => substr($mime, strpos($mime, '/')+1)
			);

			$view = $response->getView();
			$view->template = 'video/view.tal';
			$view->videos = array($video);
			return $view;
		}
	}
	public function file($request, $args, $response) {
		$file = $request->input['get']['f'];
		$path = Storage::current()->get('videoPath');
		$view = new FileView();
		$view->setFile($path . '/' . rawurldecode($file), true);
		$response->setEtag(md5_file($path . '/' . rawurldecode($file)));
		$response->setCacheControl('public', 315360000);
		return $view;
	}
}

?>