<?php
namespace Deadline;

class Cache {
	private $server, $type;

	public function __construct(Storage $store) {
		$type = $store->get('cache-type');
		$server = new $type($store->get('cache-server'));
	}
	public function add($key, $value, $expires = 0) {}
	// find returns null, get throws an exception
	public function find($key) {}
	public function get($key) {}
	public function remove($key) {}
}

class Apc {
	public function __construct($dsn) {} // no init for apc--local-based only
}

class Memcache {
	public function __construct($dsn) {
	}
}

?>