<?php
namespace Deadline;

use Deadline\App;

class Response {
	private $params = [], $downloadable = false, $type = null, $template = 'blank.html';

	public function __set($name, $value) { $this->params[$name] = $value; }
	public function __get($name) { return isset($this->params[$name]) ? $this->params[$name] : null; }
	public function __isset($name) { return isset($this->params[$name]); }
	public function __unset($name) { unset($this->params[$name]); }

	public function __construct($params = []) { $this->params  = $params; }

	public function redirect($url) {
		$this->setHeader('location', $url, true);
		return $this;
	}

	public function setType($type) {
		$this->type = $type;
		$this->setHeader('content type', $type, true);
		return $this;
	}

	public function getType() {
		return $this->type;
	}

	public function setTemplate($template) {
		$this->template = $template;
		return $this;
	}

	public function getTemplate() {
		return $this->template;
	}

	public function setDownloadable($downloadable = true) {
		$this->downloadable = $downloadable;
		return $this;
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
		return $this;
	}

	public function setHeader($name, $value, $replace = false) {
		$header = sprintf('%s: %s', str_replace(' ', '-', ucwords($name)), $value);
		header($header, $replace);
		return $this;
	}
}
