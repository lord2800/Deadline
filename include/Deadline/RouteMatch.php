<?php
namespace Deadline;

class RouteMatch {
	public $route, $args;

	public function __construct(Route $route, array $args) {
		$this->route = $route;
		$this->args  = $args;
	}
}
