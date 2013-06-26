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
		// normalize the route by removing the trailing slash
		if($uri[strlen($uri)-1] == '/') $uri = substr($uri, 0, -1);
		$variables = [];
		foreach($this->optional as $var) {
			$variables[$var['name']] = $var['default'];
		}
		if(substr_count($route, '/') !== substr_count($uri, '/')) {
			// reject routes that don't have the same piece count as the uri
			return false;
		}
		for($i = 0, $j = 0, $len = min(strlen($uri), strlen($route)); $i < $len && $j < $len; $i++, $j++) {
			// it doesn't match, fail
			if($uri[$i] !== $route[$j] && $route[$j] !== ':') {
				return false;
			}

			if($route[$j] === ':') {
				$this->matchVariable($variables, $uri, $i, $j, $route);
			}
		}
		foreach($this->required as $key) {
			if(!isset($variables[$key['name']])) {
				return false;
			}
		}
		$result = [];
		foreach($variables as $name => $value) {
			$result[$this->order[$name]] = $value;
		}
		return $result;
	}
	private function matchVariable(&$variables, &$uri, &$i, &$j, $route) {
		// adjust for optional parameters
		$add  = $route[$j + 1] !== '?' ? 1 : 2;

		// it's a variable, capture it
		$pos  = stripos($route, '/', $j + $add);
		$pos  = $pos === false ? strlen($route) : $pos;
		$name = substr($route, $j + $add, $pos);
		$j    = $pos + 1;

		$pos  = stripos($uri, '/', $i);
		$pos  = $pos === false ? strlen($uri) : $pos;
		$val  = substr($uri, $i, $pos);
		$i    = $pos + 1;
		$variables[$name] = $val;
	}
}
