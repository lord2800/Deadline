<?php
namespace Deadline;

abstract class PathStreamWrapper {
	private $res = null;
	private $type = null;

	protected static function initInternal($name) { stream_wrapper_register($name, get_called_class()); }
	public function __construct() {}
	public function __destruct() { $this->close(); }

	protected abstract function join($path);
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
		$abspath    = $this->join($path);
		$this->res  = opendir($abspath);
		$this->type = 'dir';
		return is_resource($this->res);
	}

	public function dir_readdir() { return readdir($this->res); }

	public function dir_rewinddir() {
		if(is_resource($this->res)) { rewinddir($this->res); }
		return $this->res != null;
	}

	public function mkdir($path, $mode, $options) {
		// we can only make directories in the real path
		return mkdir($this->join($path, true), $mode, $this->hasflag($options, STREAM_MKDIR_RECURSIVE));
	}

	public function rename($from, $to) { return rename($this->join($from), self::esolve($to, true)); }
	public function rmdir($path, $options) { return rmdir($this->join($path, true)); }
	public function stream_cast($cast_as) { return $this->res; }
	public function stream_close() { $this->close(); }
	public function stream_eof() { return feof($this->res); }
	public function stream_flush() { return fflush($this->res); }
	public function stream_lock($operation) { return flock($this->res, $operation); }

	public function stream_metadata($path, $option, $var) {
		$abspath = $this->join($path);
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
		$abspath    = $this->join($path);
		$this->res  = fopen($abspath, $mode);
		$this->type = 'file';
		if($this->hasflag($options, STREAM_USE_PATH)) {
			$opened_path = $abspath;
		}

		return is_resource($this->res);
	}

	public function stream_read($count) { return fread($this->res, $count); }
	public function stream_seek($offset, $whence = SEEK_SET) { return fseek($this->res, $offset, $whence); }

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

	public function stream_stat() { return fstat($this->res); }
	public function stream_tell() { return ftell($this->res); }
	public function stream_truncate($new_size) { return ftruncate($this->res, $new_size); }
	public function stream_write($data) { return fwrite($this->res, $data); }
	public function unlink($path) { return unlink($this->join($path)); }

	public function url_stat($path, $flags) {
		$abspath = $this->join($path);
		if($this->hasflag($flags, STREAM_URL_STAT_LINK)) {
			return file_exists($abspath) ? lstat($abspath) : false;
		} else {
			return file_exists($abspath) ? stat($abspath) : false;
		}
	}
}
