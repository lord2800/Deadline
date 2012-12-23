<?php
namespace Deadline;

abstract class Response {
	public function __construct() {}

	public function getRequest() {}
	public function getView($type = null) {}
	public function getFragment($encoding = 'UTF-8') {}
	public function prepare(Request $request, \View $view = null) {}
	public function output(\View $view) {}
	public function redirect($url) {}
	protected function sendHeader($name, $value) {}
	protected function sendHeaders() {}
	public function setHeader($name, $value, $replace = true) {}
	public function setCacheControl($type = 'public', $time = 3600) {}
	public function setP3PPolicy($policy) {}
	public function setLanguage($lang) {}
	public function setEtag($etag) {}
	public function setFileDownload($name) {}
	public function setModifiedTime($time = 0) {}
}
