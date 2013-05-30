<?php
namespace Deadline\Factory;

use Psr\Log\LoggerInterface;

use Deadline\App,
	Deadline\Injector,
	Deadline\IStorage;

class ModelFactory {
	private $ns, $injector, $logger, $store;

	public function __construct(App $app, LoggerInterface $logger, IStorage $store, Injector $injector) {
		$this->logger = $logger;
		$this->logger->debug('Initializing model');

		$this->ns = $store->get('model_namespace', 'Deadline\\Model\\');
		$this->injector = $injector;
		$this->store = $store;

		App::$monitor->snapshot('Model initialized');
	}

	public function get($name) {
		$this->logger->debug('Loading model ' . $name);
		$type = $this->injector->get($name, ['try' => $this->ns]);

		return $type;
	}
}