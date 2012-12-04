<?php
// AVOID a namespace here due to class loading
//namespace Deadline;

class RssView implements View {
	private $encoding;
	public function __construct($template, $base, $encoding, $reparse) {}
	public function output() {}
	public function getContentType() { return 'application/xml+rss; charset=' . $this->encoding; }
	public function setTemplate($template) {}
	public function setAll($vars) {}
}

?>