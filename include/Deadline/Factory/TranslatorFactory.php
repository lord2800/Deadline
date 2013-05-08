<?php
namespace Deadline\Factory;

use Psr\Log\LoggerInterface;

use Deadline\App,
	Deadline\Injector,
	Deadline\IStorage,
	Deadline\Translation\KeyValueTranslationService;

class TranslatorFactory {
	private $instance, $template, $injector;

	public function __construct(Injector $injector, IStorage $store, LoggerInterface $logger) {
		$logger->debug('Initializing translator');
		$this->template = $store->get('template', 'deadline');
		$this->injector = $injector;
	}
	public function get() {
		if(empty($this->instance)) {
			$this->instance = $this->injector->get('KeyValueTranslationService', ['try' => 'Deadline\\Translation']);
			$this->instance->setTemplate($this->template);
			App::$monitor->snapshot('Translator initialized');
		}
		return $this->instance;
	}
}
