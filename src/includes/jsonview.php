<?php
// AVOID a namespace here due to class loading
//namespace Deadline;

class JsonView implements View {
	private $vars = array();
	private $pretty = false;
	private $encoding;
	public function __construct($template, $base, $encoding, $reparse) {
		$this->pretty = $reparse;
		if($encoding != 'UTF-8') {
			throw new \Exception('JSON views are only supported with UTF-8 encoding!"');
		}
		$this->encoding = $encoding;
	}
	public function output() {
		$options = JSON_FORCE_OBJECT | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_TAG;
		if($this->pretty) $options |= JSON_PRETTY_PRINT;
		return json_encode($this->vars, $options);
	}
	public function getContentType() { return 'application/json; charset=' . $this->encoding; }
	public function setTemplate($template) {}
	public function setAll($vars) { $this->vars = $vars; }
}

?>