<?php
namespace Deadline;

interface IStorage {
	function load(array $config);
	function get($name, $default = null);
	function set($name, $value);
}
