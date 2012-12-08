<?php
namespace Deadline;

class Storage {
	private $store;
	private $tainted = false;
	private $file = '';

	private static $instance;
	public static function current() { return static::$instance; }

	public function __construct() {
		static::$instance = $this;
		Autosave::register(array(&$this, 'autosave'));
	}
	public function autosave() { if($this->tainted && $this->file != '') { $this->save($this->file); } }

	public function load($file) {
		$this->store = array();
		if(file_exists($file)) {
			$contents = json_decode(file_get_contents($file), false, 512, JSON_BIGINT_AS_STRING);
			if(json_last_error() != JSON_ERROR_NONE) {
				throw new \Exception('JSON error #' . json_last_error());
			}
			foreach($contents as $key => $value) {
				$this->store[$key] = $value;
			}
			$this->tainted = false;
		}
		$this->file = $file;
	}
	public function save($file) {
		file_put_contents($file, json_encode($this->store, JSON_FORCE_OBJECT | JSON_PRETTY_PRINT));
		$this->tainted = false;
		$this->file = $file;
	}
	public function get($name, $default = null) {
		return array_key_exists($name, $this->store) ? $this->store[$name] : $default;
	}
	public function set($name, $value) {
		$this->store[$name] = $value;
		$this->tainted = true;
	}
	public function clear() {
		return count($this->store) == 0;
	}
}

?>
