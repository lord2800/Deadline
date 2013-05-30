<?php
namespace Deadline\View;

use Deadline\View;
use Deadline\Response;

class File extends View {
	private $contentType = 'application/octet-stream';
	private $fp = null;

	public function setContentType($contentType) {
		$this->contentType = $contentType;
	}

	public function getContentType() {
		return $this->contentType;
	}

	public function render(Response $response) {
		$output = fopen('php://output', 'w');
		$input = fopen($response->file, 'r');
		stream_copy_to_stream($input, $output, $response->size, $response->offset);
		fclose($input);
		fclose($output);
	}
}
