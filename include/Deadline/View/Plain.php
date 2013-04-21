<?php
namespace Deadline\View;

use Deadline\View;
use Deadline\Response;

class Plain extends View {
	public function getContentType() { return 'text/plain'; }

	public function render(Response $response) {
		var_dump($response->getParams());
	}
}
