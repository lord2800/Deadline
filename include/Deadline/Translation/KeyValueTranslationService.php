<?php
namespace Deadline\Translation;

use Psr\Log\LoggerInterface;

use Deadline\App,
	Deadline\ITranslationService;

class KeyValueTranslationService implements ITranslationService {
	private $lang = 'en_US', $domain = 'default', $encoding, $logger, $data = [], $template, $vars = [];

	public function __construct(LoggerInterface $logger) {
		$this->logger = $logger;
	}

	function setTemplate($template) {
		$this->template = $template;
		$this->setLanguage($this->lang);
	}

	function setLanguage($lang) {
		$file = 'deadline://public/templates/' . $this->template . '/i18n/' . $lang . '.json';
		if(file_exists($file)) {
			$this->logger->debug('Setting translation language to ' . $lang);
			$this->lang = $lang;
			$this->data = json_decode(file_get_contents($file), true);
			return true;
		}
		return false;
	}

	function setDomain($domain) {
		$old = $this->domain;
		$this->domain = $domain;

		return $old;
	}
	function translate($key) { return $this->data[$this->domain][$key]; }
}
