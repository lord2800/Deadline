<?php
namespace Deadline;

use Deadline\App;

class Response {
	private $params = [], $downloadable = false;

	public function __set($name, $value) {
		$this->params[$name] = $value;
	}

	public function __get($name) {
		return isset($this->params[$name]) ? $this->params[$name] : null;
	}

	public function __isset($name) {
		return isset($this->params[$name]);
	}

	public function __unset($name) {
		unset($this->params[$name]);
	}

	public function __construct($params = []) {
		$this->params  = $params;
	}

	public function redirect($url) {
		$this->setHeader('location', $url, true);
	}

	public function setDownloadable($downloadable = true) {
		$this->downloadable = $downloadable;
	}

	public function getDownloadable() {
		return $this->downloadable;
	}

	public function getParams() {
		return $this->params;
	}

	public function setCookie($name, $value, \DateTime $expiry = null, $path = '/', $domain = false, $httpOnly = true, $secure = false) {
		// without a set value, set the cookie for +5 minutes
		if(empty($expiry)) $expiry = new \DateTime("+5 minutes");
		setcookie($name, $value, $expiry->getTimestamp(), $path, $domain, $secure, $httpOnly);
	}

	public function setHeader($name, $value, $replace = false) {
		$header = sprintf('%s: %s', str_replace(' ', '-', ucwords($name)), $value);
		header($header, $replace);
	}
}
