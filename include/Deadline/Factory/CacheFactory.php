<?php
namespace Deadline\Factory;

use Psr\Log\LoggerInterface;

use Deadline\App,
	Deadline\IStorage;

class CacheFactory {
	private $logger, $instancefactory, $store;

	public function __construct(LoggerInterface $logger, InstanceFactory $instancefactory, IStorage $store) {
		$this->logger = $logger;
		$this->instancefactory = $instancefactory;
		$this->store = $store;
	}

	public function get() {
		$this->logger->debug('Initializing cache');
		$type = $this->store->get('cache', 'apc');
		$class = ucfirst($type);

		$instance = $this->instancefactory->get($class, ['try' => 'Deadline\\Cache']);

		App::$monitor->snapshot('Cache initialized');
		return $instance;
	}
}