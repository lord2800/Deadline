<?php
namespace Deadline\Factory;

use Psr\Log\LoggerInterface;

use Deadline\App;

class StorageFactory {
	private $logger, $instancefactory;

	public function __construct(LoggerInterface $logger, InstanceFactory $instancefactory) {
		$this->logger = $logger;
		$this->instancefactory = $instancefactory;
	}

	public function get(array $config) {
		$this->logger->debug('Initializing storage');
		$type = pathinfo($config['settings'], PATHINFO_EXTENSION);
		$class = ucfirst($type);

		$instance = $this->instancefactory->get($class, ['try' => 'Deadline\\Storage']);
		$instance->load($config);

		App::$monitor->snapshot('Storage initialized');
		return $instance;
	}
}