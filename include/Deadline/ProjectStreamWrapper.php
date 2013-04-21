<?php
namespace Deadline;

class ProjectStreamWrapper extends PathStreamWrapper {
	private static $base;
	private static $projectName = 'project';

	public static function getProjectName() { return static::$projectName; }

	protected function join($path, $real = false) { return static::resolve($path, $real); }

	public static function resolve($path, $real = false) {
		if(static::$base == '') {
			throw new \Exception('Base path not set!');
		}
		$result = '';
		$path   = substr($path, 10);
		return static::$base . $path;
	}

	public static function init($name = 'project') {
		static::$projectName = $name;
		static::$base = dirname(dirname(realpath($_SERVER['SCRIPT_FILENAME']))) . '/';
		return parent::initInternal($name);
	}
}
