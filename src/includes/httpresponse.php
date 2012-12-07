<?php
namespace Deadline;

class HttpResponse extends Response {
	public $view;
	private $gzip, $cached = false, $baseUrl, $store, $pass;
	private $expiry, $modified, $cacheControl, $etag, $p3p, $fname = null,
			$lang = 'en', $headers = array(), $range = array();

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
		if(!class_exists($class)) {
			$class = 'HtmlView';
		}
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
	public function getStorage() {
		return $this->store;
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
		$this->setHeader('content language', $this->lang);
		$this->setHeader('content type', $this->view->getContentType());
		$this->setHeader('last modified', $this->modified->format(\DateTime::RFC1123));
		$this->setHeader('expires', $this->expiry->format(\DateTime::RFC1123));
		$this->setHeader('cache control', $this->cacheControl);
		if($this->fname != null) {
			if(file_exists($this->fname)) {
				$f = new \finfo();
				$type = $f->file($this->fname, FILEINFO_MIME_TYPE);
				$this->setHeader('content type', $type);
				$this->setHeader('content disposition', 'attachment; filename=' . basename($this->fname));
				$this->setHeader('content transfer encoding', 'binary');
				$this->setHeader('content length', '' . filesize($this->fname));
				$this->setHeader('accept ranges', 'bytes');
				// disable the time limit for this request
				set_time_limit(0);
				$range = $request->getHeader('Range');
				if($range != null) {
					// calculate the range
					// range looks like: bytes=x-y
					$eq = strpos($range, '=');
					$dash = strpos($range, '-');
					$comma = strpos($range, ',');
					$comma = $comma === false ? strlen($range) : $comma;
					$start = (int)substr($range, $eq+1, $dash);
					$end = (int)substr($range, $dash, $comma);
					$flen = filesize($this->fname);
					$end = $end === 0 ? $flen - 1 : $end;
					if($start < 0 || $end > $flen) {
						$this->setHeader('status', '416 Requested Range Not Satisfiable');
						$this->setHeader('content range', 'bytes */' . $flen);
					} else {
						$len = $end - $start;
						$this->setHeader('status', '206 Partial Content');
						$this->setHeader('content range', 'bytes ' . $start . '-' . $end . '/' . $flen);
						$this->setHeader('content length', '' . $flen);
						$this->range = array('start' => $start, 'end' => $end, 'length' => $len);
					}
				} else {
					$len = filesize($this->fname);
					$this->setHeader('content range', 'bytes ' . '0-' . ($len - 1) . '/' . $len);
					$this->setHeader('content length', '' . $len);
					$this->range = array('start' => 0, 'end' => $len - 1, 'length' => $len);
				}
			}
		}
	}
	public function output() {
		if($this->cached) {
			$this->setHeader('status', '304 Not Modified');
		} else if($this->fname == null) {
			$content = $this->view->output();
			if($this->gzip) {
				$content = gzencode($content, 9);
				$this->setHeader('content encoding', 'gzip');
			}
			$this->setHeader('content md5', base64_encode(md5($content)));
			if($this->etag != null) {
				$this->setHeader('etag', $this->etag);
			}
			$this->setHeader('status', '200 OK');
		}

		$this->sendHeaders();
		if(!$this->cached && $this->fname == null) {
			echo $content;
		} else if($this->fname != null) {
			ob_end_clean();
			$file = fopen($this->fname, 'rb');
			$count = 0;
			$start = $this->range['start'];
			$end = $this->range['end'];
			$length = $this->range['length'];
			fseek($file, $start);
			while(!feof($file) && $count < $length) {
				print(fread($file, 8192));
				flush();
				$count += 8192;
			}
			fclose($file);
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
	protected function sendHeader($name, $value) {
		$name = str_replace(' ', '-', ucwords(str_replace('-', ' ', $name)));
		header($name . ': ' . $value);
	}
	protected function sendHeaders() {
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