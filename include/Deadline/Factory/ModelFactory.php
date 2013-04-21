<?php
namespace Deadline\Factory;

use \RedBean_Facade as R;
use \PDO;

use Psr\Log\LoggerInterface;

use Deadline\App,
	Deadline\IStorage;

class ModelFactory {
	private $ns, $instancefactory, $logger, $store;

	public function __construct(App $app, LoggerInterface $logger, IStorage $store, InstanceFactory $instancefactory) {
		$this->logger = $logger;
		$this->logger->debug('Initializing model');

		$this->ns = $store->get('model_namespace', 'Deadline\\Model\\');
		$this->instancefactory = $instancefactory;
		$this->store = $store;

		App::$monitor->snapshot('Model initialized');
	}

	public function get($name) {
		$this->logger->debug('Loading model ' . $name);
		$type = $this->instancefactory->get($name, ['try' => $this->ns]);

		return $type;
	}
}