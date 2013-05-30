<?php
namespace Deadline\View;

use Deadline\View;
use Deadline\Response;

class Json extends View {
	public function getContentType() { return 'application/json'; }

	public function render(Response $response) {
		$params = $response->getParams();
		$json = json_encode($params);
		echo $json;
	}
}
