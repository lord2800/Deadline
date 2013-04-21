<?php
namespace Deadline\Cache;

use Deadline\ICache;

class Apc implements ICache {
	public function get($key) { return apc_exists($key) ? apc_fetch($key) : null; }
	public function set($key, $value, $expires = 0) { return apc_add($key, $value, $expires); }
	public function update($key, $old, $new) { return apc_cas($key, $old, $new); }
	public function remove($key) { return apc_delete($key); }
	public function increment($key, $step) { return apc_inc($key, $step); }
	public function decrement($key, $step) { return apc_dec($key, $step); }
}
