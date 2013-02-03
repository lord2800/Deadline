<?php
namespace Deadline;

use Analog;

class Autosave {
	private static $handlers = array();
	public static function register($handler, $tag = '') {
		if(is_callable($handler)) {
			Analog::log('Registering autosave handler for ' . $tag, Analog::DEBUG);
			if(empty($tag)) $tag = uniqid();
			static::$handlers[$tag] = $handler;
		}
	}
	public static function save() {
		foreach(static::$handlers as $tag => $handler) {
			Analog::log('Calling autosave handler ' . $tag, Analog::DEBUG);
			try { $handler(); } catch(Exception $e) {}
		}
	}
}
