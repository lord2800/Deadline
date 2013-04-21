<?php
namespace Deadline\Cache;

use Deadline\ICache;

class Memcache implements ICache {
	private $mc = null, $casTokens = array();
	public function __construct(App $app, IStorage $store) {
		$default = [
			'debug' => ['dsn' => 'localhost:11211'],
			'production' => ['dsn' => 'localhost:11211']
		];
		$dsn = $store->get('memcache', $default)[$app->mode]['dsn'];

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
	function get($key) {
		$value = $this->mc->get($key, null, $token);
		$this->casTokens[$key] = $token;
		return ($this->mc->getResultCode() !== Memcached::RES_NOTFOUND) ? $value : null;
	}
	function set($key, $value, $expires = 0) { return $this->mc->add($key, $value, $expires !== 0 ? time() + $expires : 0); }
	function update($key, $old, $new) { return $this->mc->cas($this->casTokens[$key], $key, $new); }
	function remove($key) { return $this->mc->delete($key); }
	function increment($key, $step) { return $this->mc->increment($key, $step); }
	function decrement($key, $step) { return $this->mc->decrement($key, $step); }
}
