<?php
namespace Deadline\Factory;

use Psr\Log\LoggerInterface;

use Deadline\App,
	Deadline\Injector;

class StorageFactory {
	private $logger, $injector;

	public function __construct(LoggerInterface $logger, Injector $injector) {
		$this->logger = $logger;
		$this->injector = $injector;
	}

	public function get(array $config) {
		$this->logger->debug('Initializing storage');
		$type = pathinfo($config['settings'], PATHINFO_EXTENSION);
		$class = ucfirst($type);

		$instance = $this->injector->get($class, ['try' => 'Deadline\\Storage']);
		$instance->load($config);

		App::$monitor->snapshot('Storage initialized');
		return $instance;
	}
}