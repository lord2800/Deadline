<?php
namespace Deadline;

use Analog;

class Cache {
	private $instance;

	public function __construct($cacheinfo) {
		$type = __NAMESPACE__ . '\\' . ucfirst($cacheinfo->type);
		Analog::log('Creating a cache of type ' . $type . ', dsn: ' . $cacheinfo->dsn, Analog::DEBUG);
		$this->instance = new $type($cacheinfo->dsn);
	}
	public function add($key, $value, $expires = 0) { return $this->instance->add($key, $value, $expires); }
	public function update($key, $old, $new) { return $this->instance->update($key, $old, $new); }
	public function increment($key, $step = 1) { return $this->instance->increment($key, $step); }
	public function decrement($key, $step = 1) { return $this->instance->decrement($key, $step); }
	public function fetch($key) { return $this->instance->fetch($key); }
	public function remove($key) { return $this->instance->remove($key); }
	public function getLastError() { return $this->instance->getLastError(); }
}

interface CacheType {
	function __construct($dsn);
	function add($key, $value, $expires = 0);
	function update($key, $old, $new);
	function increment($key, $step);
	function decrement($key, $step);
	function fetch($key);
	function remove($key);
	function getLastError();
}

class Apc implements CacheType {
	public function __construct($dsn) {} // no need to init for apc--local-based only
	function add($key, $value, $expires = 0) { return apc_add($key, $value, $expires); }
	function update($key, $old, $new) { return apc_cas($key, $old, $new); }
	function increment($key, $step) { return apc_inc($key, $step); }
	function decrement($key, $step) { return apc_dec($key, $step); }
	function fetch($key) { return apc_exists($key) ? apc_fetch($key) : null; }
	function remove($key) { return apc_delete($key); }
	function getLastError() { return 0; }
}

class Memcache implements CacheType {
	// TODO support memcache and memcached modules
	private $mc = null, $casTokens = array();
	public function __construct($dsn) {
		$this->mc = new Memcached('deadline_cache');

		if(strpos(';', $dsn) !== false) {
			$servers = array();
			$parts = explode(';', $dsn);
			foreach($parts as $part) {
				list($s, $weight) = explode('@', $part);
				list($server, $port) = explode(':', $s);
				$servers[] = array($server, (int)$port, (int)$weight);
			}
			$this->mc->addServers($servers);
		} else {
			list($server, $port) = explode(':', $dsn);
			$this->mc->addServer($server, (int)$port);
		}
	}
	function add($key, $value, $expires = 0) {
		return $this->mc->add($key, $value, $expires !== 0 ? time() + $expires : 0);
	}
	function update($key, $old, $new) { return $this->mc->cas($this->casTokens[$key], $key, $new); }
	function increment($key, $step) { return $this->mc->increment($key, $step); }
	function decrement($key, $step) { return $this->mc->decrement($key, $step); }
	function fetch($key) {
		$value = $this->mc->get($key, null, $token);
		$this->casTokens[$key] = $token;
		if($this->getLastError() !== Memcached::RES_NOTFOUND) return $value;
		return null;
	}
	function remove($key) { return $this->mc->delete($key); }
	function getLastError() { return $this->mc->getResultCode(); }
}
