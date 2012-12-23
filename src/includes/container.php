<?php
namespace Deadline;

class Container implements \ArrayAccess {
	private $vars = array();
	public function __construct($array = array()) { foreach($array as $key => $entry) $this->vars[$key] = $entry; }
	public function __get($name) { return isset($this->vars[$name]) ? $this->vars[$name] : null; }
	public function __set($name, $value) { $this->vars[$name] = $value; }
	public function __call($name, $args) { if(is_callable($this, $name)) call_user_func_array(array($this, $name), $args); }
	public function offsetExists($offset) { return array_key_exists($offset, $this->vars); }
	public function offsetGet($offset) { return $this->$offset; }
	public function offsetSet($offset, $value) { $this->$offset = $value; }
	public function offsetUnset($offset) { if($this->offsetExists($offset)) unset($this->vars[$offset]); }
	public function isEmpty() { return empty($this->vars); }
}
