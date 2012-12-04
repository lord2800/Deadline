<?php
namespace Deadline;

abstract class Response {
	public function __construct() {}

	public function getFragment($encoding = 'UTF-8') {}
	public function prepare(Request $request) {}
	public function output() {}
	public function redirect($url) {}
	private function sendHeader($name, $value) {}
	private function sendHeaders() {}
	public function setHeader($name, $value, $replace = true) {}
	public function setExpiryTime($time = 0) {}
	public function setCacheControl($type = 'public', $time = 3600, $revalidate = true) {}
	public function setP3PPolicy($policy) {}
	public function setLanguage($lang) {}
	public function setEtag($etag) {}
	public function setFileDownload($name) {}
	public function setModifiedTime($time = 0) {}
}

?>