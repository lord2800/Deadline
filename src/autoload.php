<?php

/*
This file does two things (and a caveat):
First, it sets up the deadline:// stream for usage within the project, instead of some hokey basepath detection scheme.
Second, it sets up the autoloader, so no file other than index.php has to require() a file.
The caveat is, this file must always reside at the base of the deadline path. If it's not, inclusion and everything else breaks.
*/

namespace Deadline;

use \FilesystemIterator as FS;

class PathWrapper {
	// TODO make it check $real if we're in phar mode first, then resort to inside the phar detection
	private static $base, $pharMode = false, $real = '';
	public static $scheme = 'deadline';
	private $res = null;
	private $type = null;

	public static function init() {
		$phar = \Phar::running();
		if(empty($phar)) {
			// we're not running in phar mode--we can safely use realpath() to determine the full path
			static::$real = static::$base = realpath(dirname(__FILE__));
		} else {
			// we're running in phar mode, so the path is always phar-relative
			static::$pharMode = true;
			static::$base = 'phar://deadline.phar/';
			static::$real = dirname(\Phar::running(false));
		}
	}
	public function __construct() {}
	public function __destruct() { $this->close(); }

	public static function resolve($path, $real = false) {
		if(static::$base == '') { throw new \Exception('Base path not set!'); }
		return ($real ? static::$real : static::$base) . substr($path, 10);
	}
	private function join($path) { return static::resolve($path); }
	private function hasflag($value, $flag) { return ($value & $flag) == $flag; }
	private function close() {
		if(is_resource($this->res)) {
			$actions = array('dir' => 'closedir', 'file' => 'fclose');
			$actions[$this->type]($this->res);
		}
	}

	public function dir_closedir() {
		$this->close();
		return is_resource($this->res);
	}
	public function dir_opendir($path, $options) {
		$this->close();
		$abspath = static::resolve($path);
		$this->res = opendir($abspath);
		$this->type = 'dir';
		return is_resource($this->res);
	}
	public function dir_readdir() {
		return readdir($this->res);
	}
	public function dir_rewinddir() {
		if(is_resource($this->res)) rewinddir($this->res);
		return $this->res != null;
	}
	public function mkdir($path, $mode, $options) {
		// we can only make directories in the real path
		return mkdir(static::resolve($path, true), $mode, $this->hasflag($options, STREAM_MKDIR_RECURSIVE));
	}
	public function rename($from, $to) {
		return rename(static::resolve($from), static::resolve($to, true));
	}
	public function rmdir($path, $options) {
		return rmdir(static::resolve($path, true));
	}
	public function stream_cast($cast_as) {
		return $this->res;
	}
	public function stream_close() {
		$this->close();
	}
	public function stream_eof() {
		return feof($this->res);
	}
	public function stream_flush() {
		return fflush($this->res);
	}
	public function stream_lock($operation) {
		return flock($this->res, $operation);
	}
	public function stream_metadata($path, $option, $var) {
		$abspath = static::resolve($path);
		switch($option) {
			case PHP_STREAM_META_TOUCH:
				list($time, $atime) = $var;
				return touch($abspath, $time, $atime);
				break;
			case PHP_STREAM_META_OWNER_NAME:
			case PHP_STREAM_META_OWNER:
				return chown($abspath, $var);
				break;
			case PHP_STREAM_META_GROUP_NAME:
			case PHP_STREAM_META_GROUP:
				return chgrp($abspath, $var);
				break;
			case PHP_STREAM_META_ACCESS:
				return chmod($abspath, $var);
				break;
		}
		return false;
	}
	public function stream_open($path, $mode, $options, &$opened_path) {
		$this->close();
		$abspath = static::resolve($path);
		$this->res = fopen($abspath, $mode);
		$this->type = 'file';
		if($this->hasflag($options, STREAM_USE_PATH))
			$opened_path = $abspath;
		return is_resource($this->res);
	}
	public function stream_read($count) {
		return fread($this->res, $count);
	}
	public function stream_seek($offset, $whence = SEEK_SET) {
		return fseek($this->res, $offset, $whence);
	}
	public function stream_set_option($option, $arg1, $arg2) {
		switch($option) {
			case STREAM_OPTION_BLOCKING:
				return stream_set_blocking($this->res, $arg1);
				break;
			case STREAM_OPTION_READ_TIMEOUT:
				return stream_set_timeout($this->res, $arg1, $arg2);
				break;
			case STREAM_OPTION_WRITE_BUFFER:
				return stream_set_write_buffer($this->res, $arg1 == STREAM_BUFFER_NONE ? 0 : $arg2);
				break;
		}
		return false;
	}
	public function stream_stat() {
		return fstat($this->res);
	}
	public function stream_tell() {
		return ftell($this->res);
	}
	public function stream_truncate($new_size) {
		return ftruncate($this->res, $new_size);
	}
	public function stream_write($data) {
		return fwrite($this->res, $data);
	}
	public function unlink($path) {
		return unlink(static::resolve($path));
	}
	public function url_stat($path, $flags) {
		$abspath = static::resolve($path);
		if($this->hasflag($flags, STREAM_URL_STAT_LINK)) {
			return file_exists($abspath) ? lstat($abspath) : false;
		}
		else return file_exists($abspath) ? stat($abspath) : false;
	}
}

PathWrapper::init();
stream_wrapper_register(PathWrapper::$scheme, __NAMESPACE__.'\PathWrapper');

class LibraryFilter extends \FilterIterator {
	public function accept() {
		$c = $this->current();
		return !$c->isDir() && $c->getExtension() == 'php';
	}
}

class IncludeIterator implements \Iterator {
	private $includes, $controllers, $current;
	public function __construct() {
		$this->includes = new LibraryFilter(new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator(PathWrapper::resolve('deadline://includes'), FS::NEW_CURRENT_AND_KEY | FS::SKIP_DOTS),
			\RecursiveIteratorIterator::SELF_FIRST));
		$this->controllers = new LibraryFilter(new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator(PathWrapper::resolve('deadline://controllers'), FS::NEW_CURRENT_AND_KEY | FS::SKIP_DOTS),
			\RecursiveIteratorIterator::SELF_FIRST));
	}
	public function current() { return $this->current == null ? null : $this->current->current(); }
	public function key() { return $this->current == null ? null : $this->current->key(); }
	public function next() {
		$this->current->next();
		if($this->includes->valid()) {
			$this->current = $this->includes;
		} else if($this->controllers->valid()) {
			$this->current = $this->controllers;
		}
	}
	public function rewind() { $this->current = $this->includes; $this->includes->rewind(); $this->controllers->rewind(); }
	public function valid() { return $this->includes->valid() || $this->controllers->valid(); }
}

class Autoload {
	private static $cache;
	private static $cacheFile = 'deadline://cache/autoload.cache';

	public static function init() {
		// populate the cache
		if(!is_dir(dirname(static::$cacheFile))) {
			mkdir(dirname(static::$cacheFile), 0700);
		}
		static::$cache = array();
		if(file_exists(static::$cacheFile)) {
			$cache = json_decode(file_get_contents(static::$cacheFile));
			foreach($cache as $class => $file) {
				static::$cache[$class] = $file;
			}
		}
	}
	public static function save() {
		file_put_contents(static::$cacheFile, json_encode(static::$cache, JSON_FORCE_OBJECT));
	}
	public static function load($name) {
		$file = null;
		// break apart the namespace into just the class name for search path purposes
		$parts = explode('\\', $name);
		// let PHPTAL handle its own includes
		// TODO: better handling here--this is a huge kludge
		if(strpos($parts[0], 'PHPTAL_') === 0) return;
		// let RedBean handle Model_ classes
		if(strpos($parts[0], 'Model_') === 0) return;

		if($parts[0] == 'Deadline') array_shift($parts); // Deadline includes are in the base path
		$fname = strtolower(implode('/', $parts)) . '.php';

		if(array_key_exists($fname, static::$cache)) {
			$file = static::$cache[$fname];
		} else {
			$file = static::oldSearch($fname);
		}

		if($file != null) {
			static::$cache[$fname] = $file;
			require_once($file);
		}
		// TODO figure out if I can throw an exception for a missing class here
	}
	// well, this is mildly depressing. I spend a few hours working up an iterator approach
	// to include searching, and it turns out to be no faster than scandir(). :(
	// maybe some day I'll work at it and make it smarter and faster.
	public static function newSearch($fname) {
		foreach(new IncludeIterator() as $f) {
			if($f->getFilename() == $fname) {
				$file = $f->getPathname();
			}
		}
		return $file;
	}
	public static function oldSearch($fname) {
		$file = static::search('deadline://includes/', $fname);
		if($file == null) $file = static::search('deadline://controllers/', $fname);
		return $file;
	}
	public static function search($path, $file) {
		if(file_exists($path . $file)) {
			return $path . $file;
		}
		$files = scandir($path);
		foreach($files as $dir) {
			if(!is_dir($path . $dir) || $dir == '.' || $dir == '..') continue;
			$result = static::search($path . $dir . '/', $file);
			if($result !== null) return $result;
		}
		return null;
	}
}

Autoload::init();
spl_autoload_register(__NAMESPACE__.'\Autoload::load');
Autosave::register(array('Deadline\Autoload', 'save'));

function on_shutdown() {
	run_shutdown();
	Autosave::save();
}

set_error_handler(function ($errno, $str, $file, $line, $context) { throw new \ErrorException($str, $errno, 0, $file, $line); });
register_shutdown_function(__NAMESPACE__.'\on_shutdown');

?>