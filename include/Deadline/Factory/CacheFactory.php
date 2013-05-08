<?php
namespace Deadline\Factory;

use Psr\Log\LoggerInterface;

use Deadline\App,
	Deadline\Injector,
	Deadline\IStorage;

class CacheFactory {
	private $logger, $injector, $store;

	public function __construct(LoggerInterface $logger, Injector $injector, IStorage $store) {
		$this->logger = $logger;
		$this->injector = $injector;
		$this->store = $store;
	}

	public function get() {
		$this->logger->debug('Initializing cache');
		$type = $this->store->get('cache', 'apc');
		$class = ucfirst($type);

		$instance = $this->injector->get($class, ['try' => 'Deadline\\Cache']);

		App::$monitor->snapshot('Cache initialized');
		return $instance;
	}
}