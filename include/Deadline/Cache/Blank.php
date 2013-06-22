<?php
namespace Deadline\Cache;

use Deadline\ICache;

class Blank implements ICache {
	public function get($key) { return null; }
	public function set($key, $value, $expires = 0) { return null; }
	public function update($key, $old, $new) { return null; }
	public function remove($key) { return null; }
	public function increment($key, $step) { return null; }
	public function decrement($key, $step) { return null; }
}
