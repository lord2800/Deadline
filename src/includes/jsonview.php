<?php
// AVOID a namespace here due to class loading
//namespace Deadline;

class JsonView implements View {
	private $encoding, $vars = array(), $pretty = false;
	public function __construct($encoding, $reparse) {
		$this->pretty = $reparse;
		if(strcasecmp($encoding, 'UTF-8') !== 0) {
			throw new \LogicException('JSON views are only supported with UTF-8 encoding!"');
		}
		$this->encoding = $encoding;
	}
	public function prepare(Deadline\Response $response) {
		$response->setHeader('content type', 'application/json; charset=' . $this->encoding);
	}
	public function hasOutput() { return true; }
	public function output() {
		$options = JSON_FORCE_OBJECT | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_TAG;
		// PHP < 5.4 doesn't have JSON_PRETTY_PRINT :(
		//if($this->pretty) $options |= JSON_PRETTY_PRINT;
		return json_encode($this->vars, $options);
	}
	public function getTemplate() { return null; }
	public function setTemplate($template) {}
	public function __set($name, $value) { $this->vars[$name] = $value; }
	public function __get($name) { return array_key_exists($name, $this->vars) ? $this->vars[$name] : null; }
}
