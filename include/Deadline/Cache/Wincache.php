<?php
namespace Deadline\Cache;

use Deadline\ICache;

class Wincache implements ICache {
	function get($key) { return wincache_ucache_exists($key) ? wincache_ucache_get($key) : null; }
	function set($key, $value, $expires = 0) { return wincache_ucache_add($key, $value, $expires); }
	function update($key, $old, $new) { return wincache_ucache_cas($key, $old, $new); }
	function remove($key) { return wincache_ucache_delete($key); }
	function increment($key, $step) { return wincache_ucache_inc($key, $step); }
	function decrement($key, $step) { return wincache_ucache_dec($key, $step); }
}
