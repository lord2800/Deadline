<?php
namespace Deadline\Storage;

use \RuntimeException,
	Deadline\IStorage;

class Json implements IStorage {
	private $content;

	public function load(array $config) {
		$this->content = json_decode(file_get_contents($config['settings']), true);
		if($this->content === null) {
			throw new RuntimeException('Error loading storage from ' . $config['settings'] . ': ' . json_last_error());
		}
	}

	public function get($name, $default = null) {
		return isset($this->content[$name]) ? $this->content[$name] : $default;
	}

	public function set($name, $value) {
		$this->content[$name] = $value;
	}

	public function save($file) {
		file_put_contents($file, json_encode($this->content));
	}
}
