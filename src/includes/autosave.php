<?php
namespace Deadline;

class Autosave {
	private static $handlers = array();
	public static function register($handler) {
		if(is_callable($handler)) {
			static::$handlers[] = $handler;
		}
	}
	public static function save() {
		foreach(static::$handlers as $handler) {
			$handler();
		}
	}
}

?>