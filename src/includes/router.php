<?php
namespace Deadline;

class Router {
	private $routes = array();
	private $tainted = false;
	private $file = '';
	private static $cacheFile = 'deadline://cache/routes.cache';

	private function sortRoutes() {
			uksort($this->routes, function ($a, $b) {
			// TODO this is wrong, it should be counting the number of "hard" parameters
			// before a "soft" parameter, and sorting by that number instead
			$partsa = array_values(array_filter(explode('/', $a)));
			$i = count($partsa)-1;
			while($partsa[$i--][0] != ':' && $i > -1);

			$partsb = array_values(array_filter(explode('/', $b)));
			$j = count($partsb)-1;
			while($partsb[$j--][0] != ':' && $j > -1);
			return $i == $j ? 0 : $i > $j ? -1 : 1;
		});
	}

	public function __construct() {
		Autosave::register(array(&$this, 'autosave'));
	}
	public function autosave() {
		// if the user saved the routes to a file before and the routes are tainted, save it there again
		if($this->tainted && $this->file != '') {
			$this->save($this->file);
		}
		// if it's tainted, always save it to the cache
		if($this->tainted) {
			$this->save(static::$cacheFile);
		}
	}

	public function load($file) {
		if(file_exists($file)) {
			$routes = json_decode(file_get_contents($file));
			foreach($routes as $route => $handler) {
				$this->routes[$route] = $handler;
			}
			$this->sortRoutes();
			$this->tainted = false;
		}
		$this->file = $file;
	}
	public function save($file) {
		file_put_contents($file, json_encode($this->routes, 80 /* equivalent to JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES, used for compat with 5.3 */));
		$this->tainted = false;
		$this->file = $file;
	}

	public function add($routes, $handler) {
		if(is_array($routes)) {
			// we're binding a bunch of routes
			foreach($routes as $route => $handler) {
				if($handler['controller'] != '' && $handler['method'] != '') {
					$this->routes[$route] = $handler;
					$this->tainted = true;
				} else {
					throw new \Exception("Route '$routes' is in an invalid format");
				}
			}
		} else {
			// we're binding a single route
			if($handler['controller'] != '' && $handler['method'] != '') {
				$this->routes[$routes] = $handler;
				$this->tainted = true;
			} else {
				throw new \Exception("Route '$routes' is in an invalid format");
			}
		}
		$this->sortRoutes();
	}
	public function remove($routes) {
		if(is_array($routes)) {
			// we're removing a bunch of routes
			foreach($routes as $route) {
				if(array_key_exists($route, $this->routes)) {
					unset($this->routes[$route]);
					$this->tainted = true;
				}
			}
		} else {
			if(array_key_exists($routes, $this->routes)) {
				unset($this->routes[$routes]);
				$this->tainted = true;
			}
		}
		$this->sortRoutes();
	}
	public function routeCount() { return count($this->routes); }

	public function find(Request $request) {
		echo '<pre>';
		var_dump($this->routes);
		echo '</pre>';
		die();
		// pick apart the url, find the route from that
		$pathinfo = pathinfo($request->path);
		$path = $pathinfo['dirname'] . '/' . $pathinfo['filename'];
		$urlparts = array_values(array_filter(explode('/', $path)));
		foreach($this->routes as $route => $handler) {
			$routeparts = array_values(array_filter(explode('/', $route)));

			$partcount = count($routeparts);
			$urlcount = count($urlparts);
			foreach($routeparts as $part) {
				if($part[0] == ':' && $part[1] == '?') {
					$partcount--;
				}
			}
			if($partcount > $urlcount) {
				continue;
			}

			$args = new Container();
			foreach($urlparts as $i => $part) {
				$component = ($i < count($routeparts) ? $routeparts[$i] : null);
				// first, does the component match the part?
				if($component != $part && $component[0] != ':') {
					continue 2;
				}
				if($component == $part) {
					continue;
				}
				if($component[0] == ':') {
					// it's a variable
					// is it optional?
					if($component[1] == '?') {
						// it's optional, capture it
						$args[substr($component, 2)] = $part;
					} else {
						// it's not optional, fail the match if the component is null
						if($component == null) {
							continue 2;
						} else {
							$args[substr($component, 1)] = $part;
						}
					}
				}
			}

			// we matched! hooray!
			return array($handler, $args);
		}
		return null;
	}
}

?>