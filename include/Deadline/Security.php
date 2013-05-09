<?php
namespace Deadline;

use Http\Exception\Client\Forbidden as HttpForbidden;

class Security {
	private $controller, $acl;
	public function __construct(IController $controller, Acl $acl) {
		$this->controller = $controller;
		$this->acl = $acl;
	}

	public function __call($method, $args) {
		$class = explode('\\', get_class($this->controller));
		$call = strtolower(end($class)) . '.' . $method;
		if($this->acl->hasPermission($call)) {
			return call_user_func_array([$this->controller, $method], $args);
		} else throw new HttpForbidden('You are not authorized to view ' . $call);
	}
}
