<?php
namespace Deadline;

class Router {
	private $routes = array(), $meta = array();
	private $tainted = false;
	private $file = '';
	private static $cacheFile = 'deadline://cache/routes.cache';

	private function sortRoutes() {
		uksort($this->routes, function ($a, $b) {
			$ameta = $this->meta[$a];
			$bmeta = $this->meta[$b];

			if($ameta['length'] == $bmeta['length']) {
				if($ameta['count'] > $bmeta['count']) {
					return 1;
				} else if($ameta['count'] < $bmeta['count']) {
					return -1;
				}
				return 0;
			}
			if($ameta['length'] > $bmeta['length']) return -1;
			else return 1;
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

	private function addRoute($route, $handler) {
		$this->routes[$route] = $handler;

		$parts = array_values(array_filter(explode('/', $route)));
		$len = count($parts);
		for($i = 0; $i < $len && $parts[$i][0] != ':'; $i++);

		$this->meta[$route] = array(
			'parts' => $parts,
			'count' => $len,
			'length' => $i
		);
	}

	public function load($file) {
		if(file_exists($file)) {
			$routes = json_decode(file_get_contents($file));
			foreach($routes as $route => $handler) {
				$this->addRoute($route, $handler);
			}
			$this->sortRoutes();
			$this->tainted = false;
		}
		$this->file = $file;
	}
	public function save($file) {
		/* equivalent to JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES, used for compat with 5.3 */
		file_put_contents($file, json_encode($this->routes, 80));
		$this->tainted = false;
		$this->file = $file;
	}

	public function add($routes, $handler) {
		if(is_array($routes)) {
			// we're binding a bunch of routes
			foreach($routes as $route => $handler) {
				if($handler['controller'] != '' && $handler['method'] != '') {
					$this->addRoute($route, $handler);
					$this->tainted = true;
				} else {
					throw new \Exception("Route '$routes' is in an invalid format");
				}
			}
		} else {
			// we're binding a single route
			if($handler['controller'] != '' && $handler['method'] != '') {
				$this->addRoute($routes, $handler);
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
		// pick apart the url, find the route from that
		$pathinfo = pathinfo($request->path);
		$path = $pathinfo['dirname'] . '/' . $pathinfo['filename'];
		$urlparts = array_values(array_filter(explode('/', $path)));
		foreach($this->routes as $route => $handler) {
			$routeparts = $this->meta[$route]['parts'];

			$partcount = $this->meta[$route]['count'];
			$urlcount = count($urlparts);
			// remove optional parameters from the count
			foreach($routeparts as $part) {
				if($part[0] == ':' && $part[1] == '?') {
					$partcount--;
				}
			}
			// if the required parts of the route are longer than the url, it can't possibly match
			if($partcount > $urlcount) {
				continue;
			}

			$args = new Container();
			foreach($routeparts as $i => $component) {
				$part = ($i < count($urlparts) ? $urlparts[$i] : null);
				// if the component doesn't match the part and the component isn't a variable...
				if($component != $part && $component[0] != ':') {
					continue 2;
				}
				// does the component match the part?
				if($component == $part) {
					continue;
				}
				// it's a variable
				if($component[0] == ':') {
					// is it optional?
					if($component[1] == '?') {
						// it's optional, capture it
						$args[substr($component, 2)] = $part;
					} else {
						// it's not optional, fail the match if the part is null
						if($part == null) {
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
