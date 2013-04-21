<?php
namespace Deadline;

class DeadlineStreamWrapper extends PathStreamWrapper {
	private static $base, $pharMode = false, $real = '';

	protected function join($path, $real = false) { return static::resolve($path, $real); }

	public static function resolve($path, $real = false) {
		if(static::$base == '') {
			throw new \Exception('Base path not set!');
		}
		$result = '';
		$path   = substr($path, 10);
		if($real) {
			// we're doing something with a real-world path, skip the test
			return static::$real . $path;
		}
		if(static::$pharMode && (file_exists(static::$real . $path) || is_dir(static::$real . $path))) {
			return static::$real . $path;
		} else {
			return static::$base . $path;
		}
	}

	public static function init() {
		$phar = \Phar::running();
		if(empty($phar)) {
			// we're not running in phar mode--we can safely use realpath() to determine the full path
			static::$real = static::$base = dirname(dirname(dirname(realpath(__FILE__))));
		} else {
			// we're running in phar mode, so we should try a phar-relative path if not a real path
			static::$pharMode = true;
			static::$base     = 'phar://deadline.phar/';
			static::$real     = dirname(\Phar::running(false)) . '/';
		}
		return parent::initInternal('deadline');
	}
}
