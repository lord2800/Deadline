<?php
namespace Deadline\Router;

class PlainRouter extends Router {
	private $store;
	public function __construct(IStorage $store) { $this->store = $store; }
	public function loadRoutes() {
		$routes = $this->store->get('routes');
		foreach($routes as $route) {
			$this->addRoute(new Route($route['route'], $route['controller'], $route['method'], $route['required'], $route['optional']));
		}
	}
	public function saveRoutes() {
		$this->store->set('routes', $this->routes);
	}
}
