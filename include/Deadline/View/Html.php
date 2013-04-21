<?php
namespace Deadline\View;

use Deadline\App,
	Deadline\View,
	Deadline\Request,
	Deadline\Response,
	Deadline\IStorage,
	Deadline\ITranslationService,
	Deadline\ProjectStreamWrapper;

use \PHPTAL;
use \PHPTAL_Filter;
use \PHPTAL_PreFilter_StripComments;
use \PHPTAL_PreFilter_Normalize;
use \PHPTAL_PreFilter_Compress;
use \PHPTAL_TranslationService;

use Analog\Analog;

class Html extends View {
	public $translator = null, $store, $app;
	public function __construct(ITranslationService $translator, IStorage $store, App $app) {
		$this->translator = $translator;
		$this->store = $store;
		$this->app = $app;
	}
	public function getContentType() { return 'text/html'; }

	public function render(Response $response) {
		$template = $this->store->get('template', 'deadline');

		$this->translator->setLanguage($response->locale);

		$phptal = new PHPTAL();
		$phptal->setOutputMode(PHPTAL::HTML5)
			->setEncoding('UTF-8')
			->setForceReparse(!$this->store->get('live', false))
			->setPhpCodeDestination(ProjectStreamWrapper::getProjectName() . '://cache')
			->setTemplateRepository(DeadlineStreamWrapper::resolve(ProjectStreamWrapper::getProjectName() . '://public/templates/' . $template))
			->addPreFilter(new PHPTAL_PreFilter_StripComments())
			->addPreFilter(new PHPTAL_PreFilter_Normalize())
			->addPreFilter(new PHPTAL_PreFilter_Compress())
			->setPostFilter(new UrlPostFilter($this->app->getBaseUrl(), $template))
			->setTranslator(new KeyValueTranslationService($this->translator))
			->setTemplate($response->template);

		foreach($response->getParams() as $key => $value) {
			$phptal->set($key, $value);
		}
		echo $phptal->execute();
	}
}

class UrlPostFilter implements PHPTAL_Filter {
	private $template, $base;
	private $templateRegex = '/template:\/\/([^"]+)/Uim',
		$linkRegex = '/link:\/\/([^"]+)/Uim';

	public function __construct($base, $template) {
		$parsed     = parse_url($base);
		$this->base = '//' . $parsed['host'];
		if(array_key_exists('path', $parsed)) $this->base .= dirname($parsed['path']);
		$this->template = $template;
	}

	public function filter($code) {
		// replace our custom uri namespaces with the real deal
		$code = preg_replace_callback($this->templateRegex,
			function ($m) {
				return $this->base . '/templates/' . $this->template . '/' . $m[1];
			},
			preg_replace_callback($this->linkRegex,
				function ($m) {
					return $this->base . '/' . $m[1];
				}, $code));

		return $code;
	}
}

class KeyValueTranslationService implements PHPTAL_TranslationService {
	private $translator;
	private $lang = 'en_US', $encoding, $domain = 'default', $data = [], $template, $vars = [];

	public function __construct(ITranslationService $translator) {
		$this->translator = $translator;
	}

	function setEncoding($encoding) { $this->encoding = $encoding; }
	function useDomain($domain) { return $this->translator->setDomain($domain); }
	function setVar($key, $value_escaped) { $this->vars[$key] = $value_escaped; }

	function setLanguage() {
		foreach(func_get_args() as $lang) {
			if($this->translator->setLanguage($lang)) {
				$this->lang = $lang;
			}
		}

		return $this->lang;
	}

	function translate($key, $htmlencode = true) {
		$value = $this->translator->translate($key);

		if($htmlencode) {
			$value = htmlspecialchars($value, ENT_QUOTES, $this->encoding);
		}
		while(preg_match('/\${(.*?)\}/sm', $value, $m)) {
			list($src, $var) = $m;
			if(!array_key_exists($var, $this->vars)) {
				throw new PHPTAL_VariableNotFoundException('Interpolation error. Translation uses ${' . $var . '}, which is not defined in the template (via i18n:name)');
			}
			$value = str_replace($src, $this->vars[$var], $value);
		}

		return $value;
	}
}
