<?php
namespace Deadline;

use Psr\Log\LoggerInterface;

use \ReflectionClass;

class DatabaseHandle {
	private $handles = [], $factories = [], $logger;
	public function __construct(App $app, IStorage $store, LoggerInterface $logger) {
		$this->logger = $logger;
		$handles = $store->get('connection_settings')[$app->mode()];
		foreach($handles as $name => $settings) {
			$type = '\\' . ltrim($settings['type'], '\\');
			$args = $settings['arguments'];

			$this->factories[$name] = function () use ($name, $type, $args, $logger) {
				$logger->debug('Creating instance for ' . $name);
				$class = new ReflectionClass($type);
				$this->handles[$name] = $class->newInstanceArgs($args);
				// special case: make PDO throw exceptions
				// TODO figure out a better way to do this
				if($type === '\\PDO') {
					$this->handles[$name]->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
				}
			};
		}
	}

	public function get($name) {
		if(!isset($this->handles[$name]) && isset($this->factories[$name])) {
			$this->factories[$name]();
		}
		$this->logger->debug('Getting instance for ' . $name);
		return isset($this->handles[$name]) ? $this->handles[$name] : null;
	}
}
