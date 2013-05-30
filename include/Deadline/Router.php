<?php
namespace Deadline;

use \JsonSerializable;

use Deadline\App;

use Psr\Log\LoggerInterface;

abstract class Router {
	protected $routes = [];

	public function route(Request $request) {
		$uri = $request->path;
		foreach($this->routes as $route) {
			$match = $route->match($uri);
			if($match !== false) return new RouteMatch($route, $match);
		}
		return null;
	}
	public function addRoute(Route $route) {
		$this->routes[] = $route;
	}
	public function clearRoutes() {
		$this->routes = [];
	}
	public abstract function loadRoutes();
}
