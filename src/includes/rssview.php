<?php
// AVOID a namespace here due to class loading
//namespace Deadline;

class RssView implements View {
	private $encoding, $vars = array();
	public function __construct($encoding, $reparse) {}
	public function prepare(Deadline\Response $response) {
		$response->setHeader('content type', 'application/xml+rss; charset=' . $this->encoding);
	}
	public function output() {}
	public function getTemplate() { return null; }
	public function setTemplate($template) {}
	public function __set($name, $value) { $this->vars[$name] = $value; }
	public function __get($name) { return array_key_exists($name, $this->vars) ? $this->vars[$name] : null; }
}

?>