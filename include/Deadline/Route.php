<?php
namespace Deadline;

use \JsonSerializable;

class Route implements JsonSerializable {
	public $controller, $method;
	private $route, $required = [], $optional = [], $order = [];

	public function __construct($route, $controller, $method, array $order, array $required, array $optional) {
		$this->route = $route;
		$this->controller = $controller;
		$this->method = $method;
		$this->order = $order;
		$this->required = $required;
		$this->optional = $optional;
	}
	public function jsonSerialize() {
		return ['route' => $this->route, 'controller' => $this->controller, 'method' => $this->method,
				'order' => $this->order, 'required' => $this->required, 'optional' => $this->optional];
	}
	public function match($uri) {
		$route = $this->route;
		$variables = array_merge($this->optional, []);
		for($i = 0, $j = 0, $len = min(strlen($uri), strlen($route)); $i < $len && $j < $len; $i++, $j++) {
			// it doesn't match, fail
			if($uri[$i] !== $route[$j] && $route[$j] !== ':') {
				return false;
			}

			if($route[$j] === ':') {
				$this->matchVariable($i, $j, $route);
			}
		}
		foreach($this->required as $key) {
			$index = $this->order[$key['name']];
			if(!isset($variables[$index])) {
				return false;
			}
		}
		return $variables;
	}
	private function matchVariable(&$i, &$j, $route) {
		// adjust for optional parameters
		$add = $route[$j + 1] !== '?' ? 1 : 2;

		// it's a variable, capture it
		$pos  = stripos($route, '/', $j + $add);
		$pos  = $pos === false ? strlen($route) : $pos;
		$name = substr($route, $j + $add, $pos);
		$j    = $pos + 1;

		$pos  = stripos($uri, '/', $i);
		$pos  = $pos === false ? strlen($uri) : $pos;
		$val  = substr($uri, $i, $pos);
		$i    = $pos + 1;
		$variables[$order[$name]] = $val;
	}
}
