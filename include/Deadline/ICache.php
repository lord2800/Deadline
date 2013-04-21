<?php
namespace Deadline;

interface ICache {
	function get($key);
	function set($key, $value, $expires);
	function update($key, $old, $new);
	function remove($key);
	function increment($key, $amount);
	function decrement($key, $amount);
}
