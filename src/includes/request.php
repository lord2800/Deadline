<?php
namespace Deadline;

// TODO: turn this into a subclass of Container
class Request {
	private $env;
	private $props = array();
	private $headers = array();

	public function __construct($env) {
		$this->env = $env;
		$this->init();
	}
	private function getenv($name) {
		$name = str_replace(' ', '_', strtoupper($name));
		return array_key_exists($name, $this->env) ? $this->env[$name] : null;
	}

	private function init() {
		// put together the basic properties
		$this->props['ssl'] = $this->getenv('https') == 'on' ? true : false;

		$this->props['host'] = $this->getenv('server name');
		$this->props['proto'] = $this->getenv('server protocol');
		$this->props['verb'] = $this->getenv('request method');
		$this->props['requestTime'] = \DateTime::createFromFormat('U', $this->getenv('request time'));

		$this->props['accept'] = explode(',', $this->getenv('http accept'));
		foreach($this->props['accept'] as &$value) {
			$quality = 1;
			$type = $value;
			if(strpos($value, ';') !== false) {
				list($type, $q) = explode(';', $value);
				$quality = substr($q, 2);
			}
			$value = array('type' => $type, 'quality' => $quality);
		}
		unset($value);
		usort($this->props['accept'], function ($a, $b) { return $a['quality'] - $b['quality']; });

		$acceptEncoding = array_values(array_filter(explode(',', $this->getenv('http accept encoding'))));
		array_walk($acceptEncoding, function(&$k) { $k = trim($k); });
		$this->props['encoding'] = $acceptEncoding;
		if(empty($this->props['encoding'])) $this->props['encoding'][] = 'none';

		$this->props['charset'] = array_values(array_filter(explode(',', $this->getenv('http accept charset'))));
		if(empty($this->props['charset'])) $this->props['charset'][] = 'UTF-8';

		$this->props['lang'] = explode(',', $this->getenv('http accept language'));
		foreach($this->props['lang'] as &$value) {
			$quality = 1;
			$lang = $value;
			if(strpos($value, ';') !== false) {
				list($lang, $q) = explode(';', $value);
				$quality = substr($q, 2);
			}
			$value = array('lang' => $lang, 'quality' => $quality);
		}
		unset($value);
		usort($this->props['accept'], function ($a, $b) { return $a['quality'] - $b['quality']; });

		$this->props['referrer'] = $this->getenv('http referer');
		$this->props['userAgent'] = $this->getenv('http user agent');
		$this->props['port'] = $this->getenv('server port');
		$this->props['uri'] = $this->getenv('request uri');
		$this->props['path'] = $this->getenv('path info');
		$this->props['query'] = array();
		parse_str($this->getenv('query string'), $this->props['query']);

		$this->props['input'] = array(
			'get' => new Container($_GET),
			'post' => new Container($_POST)
		);
		$this->props['requester'] = array(
			'addr' => $this->getenv('remote addr'),
			'host' => $this->getenv('remote host')
		);
		$this->props['auth'] = array(
			'digest' => $this->getenv('php auth digest'),
			'user' => $this->getenv('php auth user'),
			'pass' => $this->getenv('php auth pw'),
			'type' => $this->getenv('auth type')
		);
		$modified = $this->getenv('http if modified since');
		$this->props['cache'] = array(
			'etag' => $this->getenv('http if none match'),
			'modified' => $modified == null ? null : new \DateTime($modified)
		);

		// build the raw headers, just in case
		foreach($this->env as $key => $value) {
			if(strpos($key, 'HTTP_') === 0) {
				$key = ucwords(str_replace('_', ' ', strtolower(substr($key, 5))));
				$this->headers[str_replace(' ', '-', $key)] = $value;
			}
		}

		// build the full url now that we have all the constituent parts
		$this->props['url'] = 'http';
		$this->props['url'] .= $this->ssl ? 's' : '';
		$this->props['url'] .= '://' . $this->host;
		if(($this->ssl && $this->port != '443') || ($this->port != '80'))
			$this->props['url'] .= ':' . $this->port;
		$this->props['url'] .= $this->uri;
		// clear the env, we don't need it now
		unset($this->env);
	}

	public function __get($name) {
		return array_key_exists($name, $this->props) ? $this->props[$name] : null;
	}

	public function getHeader($name) {
		if(array_key_exists($name, $this->headers)) {
			return $this->headers[$name];
		} else return null;
	}
}

?>