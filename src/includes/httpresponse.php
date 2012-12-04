<?php
namespace Deadline;

class HttpResponse extends Response {
	public $view;
	private $gzip, $cached = false, $baseUrl, $store, $pass;
	private $expiry, $modified, $cacheControl, $etag, $p3p, $fname, $lang = 'en', $headers = array();

	private static $instance;
	public static function current() { return static::$instance; }

	public function getCurrentUser() { return session_status() === PHP_SESSION_ACTIVE ? User::getCurrent() : null; }

	public function __construct(Storage $store, Request $request) {
		// figure out the kind of view to build based on the request
		if(static::$instance != null) {
			throw new \Exception('There can be only one!');
		}

		static::$instance = $this;
		$ext = pathinfo($request->path, PATHINFO_EXTENSION);
		if($ext == '') {
			$ext = 'html';
		}
		$class = ucwords($ext) . 'View';
		$base = dirname(substr($request->url, 0, strpos($request->url, $request->path)));

		User::init();

		$this->gzip = in_array('gzip', $request->encoding);
		$this->expiry = new \DateTime();
		$this->modified = new \DateTime();
		$this->setCacheControl('no-cache');
		$this->baseUrl = $base;
		$this->store = $store;
		$this->pass = new Password();

		$this->view = new $class($store->get('template'), $base, $request->charset[0], !$store->get('live'));
		$this->view->lang = $this->lang;
		$this->view->siteTitle = $store->get('siteTitle');
		$this->view->siteTagline = $store->get('siteTagline');
		$this->view->headerImage = $store->get('headerImage');
		$this->view->currentUser = $this->getCurrentUser();
		$this->view->currentUrl = $request->url;
		$this->view->hideSignonBox = false;
	}

	public function getPartial($encoding = 'UTF-8') {
		return new \PartialResponse($this->store->get('template'), $encoding, !$this->store->get('live'));
	}
	public function getPasswordInstance() {
		return $this->pass;
	}

	public function prepare(Request $request) {
		if($this->etag != null &&
			 $this->etag == $request->cache['etag'] ||
		   $this->modified == $request->cache['modified']) {
		   $this->cached = true;
		}
		if($this->p3p != null) {
			$this->setHeader('p3p', $this->p3p);
		}
		$this->setHeader('connection', 'keep-alive');
		$this->setHeader('content-language', $this->lang);
		$this->setHeader('content-type', $this->view->getContentType());
		$this->setHeader('last-modified', $this->modified->format(\DateTime::RFC1123));
		$this->setHeader('expires', $this->expiry->format(\DateTime::RFC1123));
		$this->setHeader('cache-control', $this->cacheControl);
	}
	public function output() {
		if($this->cached) {
			$this->setHeader('status', '304 Not Modified');
		} else {
			$content = $this->view->output();
			if($this->gzip) {
				$content = gzencode($content, 9);
				$this->setHeader('content-encoding', 'gzip');
			}
			$this->setHeader('content-md5', base64_encode(md5($content)));
			if($this->etag != null) {
				$this->setHeader('etag', $this->etag);
			}
			$this->setHeader('status', '200 OK');
		}

		$this->sendHeaders();
		if(!$this->cached) {
			echo $content;
		}
	}

	public function redirect($url) {
		$url = parse_url($url);
		if($url === false) {
			throw new \Exception("Malformed URL");
		}
		$base = parse_url($this->baseUrl);
		if(array_key_exists('scheme', $url) && array_key_exists('host', $url)) {
			// if we have a scheme and a host, we probably have a full url
			if(array_key_exists('query', $url)) $url['path'] .= '?' . $url['query'];
			$this->sendHeader('location', "{$url['scheme']}://{$url['host']}{$url['path']}");
			die();
		}

		// fill in the scheme and host if necessary
		if(!array_key_exists('scheme', $url)) $url['scheme'] = $base['scheme'];
		if(!array_key_exists('host', $url)) $url['host'] = $base['host'];
		if(!array_key_exists('path', $base)) $base['path'] = '';
		if(!array_key_exists('path', $url)) $url['path'] = '';
		if(array_key_exists('query', $url)) $url['path'] .= '?' . $url['query'];

		$this->sendHeader('location', "{$url['scheme']}://{$url['host']}{$base['path']}/index.php{$url['path']}");
		die();
	}
	private function sendHeader($name, $value) {
		$name = str_replace(' ', '-', ucwords(str_replace('-', ' ', $name)));
		header($name . ': ' . $value);
	}
	private function sendHeaders() {
		foreach($this->headers as $name => $value) {
			$this->sendHeader($name, $value);
		}
	}
	public function setHeader($name, $value, $replace = true) {
		if($replace || !array_key_exists($name, $this->headers)) {
			$this->headers[$name] = $value;
		}
	}
	public function setExpiryTime($time = 0) {
		if($time instanceof \DateTime) { $this->expiry = $time; }
		else if(is_numeric($time)) { $this->expiry = \DateTime::createFromFormat('U', $time); }
		else { $this->expiry = new \DateTime(); }
	}
	public function setCacheControl($type = 'public', $time = 3600, $revalidate = true) {
		if($type == 'no-cache') {
			$this->cacheControl = 'no-cache';
		} else {
			$this->cacheControl = "$type, max-age=$time, s-maxage=$time";
			if($revalidate) {
				$this->cacheControl .= ', must-revalidate, proxy-revalidate';
			}
		}
	}
	public function setP3PPolicy($policy) {
		$this->p3p = 'CP="' . $policy . '"';
	}
	public function setLanguage($lang) {
		$this->lang = $lang;
	}
	public function setEtag($etag) {
		$this->etag = $etag;
	}
	public function setFileDownload($name) {
		$this->fname = $name;
	}
	public function setModifiedTime($time = 0) {
		if($time instanceof \DateTime) { $this->modified = $time; }
		else if(is_numeric($time)) { $this->modified = \DateTime::createFromFormat('U', $time); }
		else { $this->modified = new \DateTime(); }
	}
}

?>