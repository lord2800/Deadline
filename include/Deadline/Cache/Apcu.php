<?php
namespace Deadline\Cache;

use Deadline\ICache;

class Apcu implements ICache {
	public function get($key) { return apcu_exists($key) ? apcu_fetch($key) : null; }
	public function set($key, $value, $expires = 0) { return apcu_add($key, $value, $expires); }
	public function update($key, $old, $new) { return apcu_cas($key, $old, $new); }
	public function remove($key) { return apcu_delete($key); }
	public function increment($key, $step) { return apcu_inc($key, $step); }
	public function decrement($key, $step) { return apcu_dec($key, $step); }
}
