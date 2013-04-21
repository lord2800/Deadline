<?php
namespace Deadline\Factory;

use Deadline\App,
	Deadline\IStorage;

class RouterFactory {
	private $instancefactory;
	public function __construct(InstanceFactory $instancefactory, IStorage $store) {
		$this->instancefactory = $instancefactory;
		$this->storage = $store;
	}
	public function get() {
		$routerType = ucfirst($this->storage->get('router', 'magic')) . 'Router';
		$router = $this->instancefactory->get($routerType, ['try' => 'Deadline\\Router']);

		App::$monitor->snapshot('Router initialized');
		return $router;
	}
}
