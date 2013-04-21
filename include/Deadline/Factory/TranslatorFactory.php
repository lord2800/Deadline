<?php
namespace Deadline\Factory;

use Psr\Log\LoggerInterface;

use Deadline\App,
	Deadline\IStorage,
	Deadline\Translation\KeyValueTranslationService;

class TranslatorFactory {
	private $instance, $template, $instancefactory;

	public function __construct(InstanceFactory $instancefactory, IStorage $store, LoggerInterface $logger) {
		$logger->debug('Initializing translator');
		$this->template = $store->get('template', 'deadline');
		$this->instancefactory = $instancefactory;
	}
	public function get() {
		if(empty($this->instance)) {
			$this->instance = $this->instancefactory->get('KeyValueTranslationService', ['try' => 'Deadline\\Translation']);
			$this->instance->setTemplate($this->template);
			App::$monitor->snapshot('Translator initialized');
		}
		return $this->instance;
	}
}
