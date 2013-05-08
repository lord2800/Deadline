<?php
namespace Deadline\Factory;

use Deadline\App,
	Deadline\Injector,
	Deadline\IStorage;

class RouterFactory {
	private $injector;
	public function __construct(Injector $injector, IStorage $store) {
		$this->injector = $injector;
		$this->storage = $store;
	}
	public function get() {
		$routerType = ucfirst($this->storage->get('router', 'magic')) . 'Router';
		$router = $this->injector->get($routerType, ['try' => 'Deadline\\Router']);

		App::$monitor->snapshot('Router initialized');
		return $router;
	}
}
