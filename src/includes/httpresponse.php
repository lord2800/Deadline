<?php
namespace Deadline;

use Analog;

class HttpResponse extends Response {
	private $gzip, $cached = false, $baseUrl;
	private $modified, $cacheControl = null, $etag, $p3p,
			$lang = 'en', $headers = array(), $range = array();

	private static $instance;

	public function __construct(Request $request) {
		if(static::$instance != null) {
			throw new \LogicException('There can be only one!');
		}

		static::$instance = $this;
		$path = strpos($request->url, $request->path, 6);
		$base = substr($request->url, 0, $path === false ? strlen($request->url)-1 : $path);

		$this->gzip = in_array('gzip', $request->encoding) && function_exists('gzencode');
		$this->modified = null;
		$this->baseUrl = $base;
		$this->setCacheControl('no-cache');
	}

	public function getBaseUrl() { return $this->baseUrl; }
	public function getView($type = null) {
		$request = App::request();
		if($type == null) {
			$ext = pathinfo($request->path, PATHINFO_EXTENSION);
			if($ext == '') {
				$ext = 'html';
			}
			$class = ucwords($ext) . 'View';
		} else {
			$class = ucwords($type) . 'View';
		}

		$view = new $class($request->charset[0], App::live());
		$view->lang = $this->lang;
		$view->siteTitle = App::store()->get('siteTitle');
		$view->siteTagline = App::store()->get('siteTagline');
		$view->headerImage = App::store()->get('headerImage');
		$view->currentUser = User::current();
		$view->currentUrl = $request->url;
		$view->hideSignonBox = false;
		return $view;
	}

	public function prepare(\View $view = null) {
		$request = App::request();
		if($this->etag != null && $this->etag == $request->cache['etag'] ||
		   $this->modified != null && $this->modified == $request->cache['modified']) {
		   $this->cached = true;
		}
		if($this->p3p != null) {
			$this->setHeader('p3p', $this->p3p);
		}
		if($this->modified != null) {
			$this->setHeader('last modified', $this->modified->format(\DateTime::RFC1123));
		}
		if($this->cacheControl != null) {
			$this->setHeader('cache control', $this->cacheControl);
		}
		$this->setHeader('content language', $this->lang);
		$this->setHeader('connection', 'keep-alive');
		if($view != null) {
			$view->prepare($this);
		}
	}
	public function output(\View $view) {
		if($this->cached) {
			$this->setHeader('status', '304 Not Modified');
			$this->sendHeaders();
		} else {
			// keep 5mb in memory, otherwise dump to a temp file
			$fp = fopen('php://temp/maxmemory:5242880', 'r+');
			$out = fopen('php://output', 'w+');
			if($this->gzip && Mime::compress($this->headers['content type'])) {
				Analog::log('Would use GZip encoding, but it\'s broke', Analog::DEBUG);
				//$out = fopen('compress.zlib://php://output', 'w+');
				//stream_filter_append($fp, 'zlib.deflate', STREAM_FILTER_READ, App::store()->get('compressionLevel', 6));
				//$this->setHeader('content encoding', 'gzip');
			}

			$view->output($fp);
			$size = ftell($fp);
			$this->setHeader('content length', $size);
			rewind($fp);

			if($this->etag != null) {
				$this->setHeader('etag', '"' . $this->etag . '"');
			}
			$this->setHeader('status', '200 OK');

			Analog::log('Serving page as ' . $this->headers['content type'] . ' (' . $size . ' bytes)', Analog::DEBUG);
			$this->sendHeaders();
			stream_copy_to_stream($fp, $out);
		}
	}

	public function redirect($url) {
		$url = parse_url($url);
		if($url === false) {
			throw new \Exception("Malformed URL");
		}

		$base = parse_url($this->baseUrl);
		$redirect = '';

		if(array_key_exists('scheme', $url) && array_key_exists('host', $url)) {
			// if we have a scheme and a host, we probably have a full url
			if(array_key_exists('query', $url)) $url['path'] .= '?' . $url['query'];
			$redirect = "{$url['scheme']}://{$url['host']}{$url['path']}";
		} else {
			// fill in the scheme and host if necessary
			if(!array_key_exists('scheme', $url)) $url['scheme'] = $base['scheme'];
			if(!array_key_exists('host', $url)) $url['host'] = $base['host'];
			if(!array_key_exists('path', $base)) $base['path'] = '';
			if(!array_key_exists('path', $url)) $url['path'] = '';
			if(array_key_exists('query', $url)) $url['path'] .= '?' . $url['query'];

			$redirect = "{$url['scheme']}://{$url['host']}{$base['path']}/{$url['path']}";
		}

		$this->sendHeader('location', $redirect);
		die();
	}
	protected function sendHeader($name, $value) {
		$name = str_replace(' ', '-', ucwords(str_replace('-', ' ', $name)));
		header($name . ': ' . $value);
	}
	protected function sendHeaders() {
		foreach($this->headers as $name => $value) {
			$this->sendHeader($name, $value);
		}
	}
	public function setHeader($name, $value, $replace = false) {
		if($replace || !array_key_exists($name, $this->headers)) {
			$this->headers[$name] = $value;
		}
	}

	public function setCacheControl($type = 'public', $time = 3600) {
		if($type == 'no-cache') {
			$this->cacheControl = 'no-cache';
		} else {
			$this->cacheControl = "$type; max-age=$time";
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
	public function setModifiedTime($time = 0) {
		if($time instanceof \DateTime) { $this->modified = $time; }
		else if(is_numeric($time)) { $this->modified = \DateTime::createFromFormat('U', $time); }
		else { $this->modified = new \DateTime(); }
	}
}
