<?php
namespace Deadline;

class Request extends Container {
	private $env;
	private $headers = array();

	public function __construct($env) {
		$this->env = $env;
		$this->init();
	}
	private function qualsort($a, $b) {
		return $a['quality'] == $b['quality'] ? 0 :
					$a['quality'] > $b['quality'] ? -1 : 1;
	}
	private function getenv($name) {
		$name = str_replace(' ', '_', strtoupper($name));
		return isset($this->env[$name]) ? $this->env[$name] : null;
	}
	private function getClientIP() {
		// unfortunately, we have to start with the least likely header and proceed down
		// otherwise, we could cut this work down quite a bit
		$headers = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR'
		);
		$ipFlags = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;
		foreach($headers as $key) {
			foreach(explode(',', $this->getenv($key)) as $ip) {
				$ip = filter_var(trim($ip), FILTER_VALIDATE_IP, $ipFlags);
				if($ip !== false) {
					return $ip;
				}
			}
		}
	}

	private function init() {
		// put together the basic properties
		$this->ssl = $this->getenv('https') == 'on' ? true : false;

		$this->host = $this->getenv('server name');
		$this->proto = $this->getenv('server protocol');
		$this->verb = $this->getenv('request method');
		$this->requestTime = \DateTime::createFromFormat('U', $this->getenv('request time'));

		$accept = explode(',', $this->getenv('http accept'));
		foreach($accept as &$value) {
			$quality = 1.0;
			$type = $value;
			if(strpos($value, ';') !== false) {
				list($type, $q) = explode(';', $value);
				$quality = floatval(substr($q, 2));
			}
			$value = array('type' => $type, 'quality' => $quality);
		}
		unset($value);
		usort($accept, array($this, 'qualsort'));
		$this->accept = $accept;
		unset($accept);

		$lang = explode(',', $this->getenv('http accept language'));
		foreach($lang as &$value) {
			$quality = 1.0;
			$l = $value;
			if(strpos($value, ';') !== false) {
				list($l, $q) = explode(';', $value);
				$quality = floatval(substr($q, 2));
			}
			$value = array('lang' => $l, 'quality' => $quality);
		}
		unset($value);
		usort($lang, array($this, 'qualsort'));
		$this->lang = $lang;
		unset($lang);

		$acceptEncoding = array_values(array_filter(explode(',', $this->getenv('http accept encoding'))));
		array_walk($acceptEncoding, function(&$k) { $k = trim($k); });
		if(empty($acceptEncoding)) $acceptEncoding[] = 'none';
		$this->encoding = $acceptEncoding;
		unset($acceptEncoding);

		$charset = array_values(array_filter(explode(',', $this->getenv('http accept charset'))));
		array_walk($charset, function(&$k) { $k = trim($k); });
		if(empty($charset)) $charset[] = 'UTF-8';
		$this->charset = $charset;
		unset($charset);

		$this->referrer = $this->getenv('http referer');
		$this->userAgent = $this->getenv('http user agent');
		$this->port = $this->getenv('server port');
		$this->uri = $this->getenv('request uri');
		$this->path = $this->getenv('path info');

		$query = array();
		parse_str($this->getenv('query string'), $query);
		$this->query = $query;
		unset($query);

		$this->input = array(
			'get' => new Container($_GET),
			'post' => new Container($_POST)
		);
		$ip = $this->getClientIP();
		$this->requester = array(
			'addr' => $ip,
			'host' => $this->getenv('remote host')
//			'resolved' => gethostbyaddr($ip)
		);
		$this->auth = array(
			'digest' => $this->getenv('php auth digest'),
			'user' => $this->getenv('php auth user'),
			'pass' => $this->getenv('php auth pw'),
			'type' => $this->getenv('auth type')
		);
		$modified = $this->getenv('http if modified since');
		$this->cache = array(
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
		$this->url = 'http';
		$this->url .= $this->ssl ? 's' : '';
		$this->url .= '://' . $this->host;
		if(($this->ssl && $this->port != '443') || ($this->port != '80'))
			$this->url .= ':' . $this->port;
		$this->url .= $this->uri;
		// clear the env, we don't need it now
		unset($this->env);
	}

	public function getHeader($name) {
		return isset($this->headers[$name]) ? $this->headers[$name] : null;
	}
}

?>