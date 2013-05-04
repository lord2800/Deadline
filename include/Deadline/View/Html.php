<?php
namespace Deadline\View;

use Psr\Log\LoggerInterface;

use Deadline\App,
	Deadline\View,
	Deadline\Request,
	Deadline\Response,
	Deadline\IFilter,
	Deadline\IStorage,
	Deadline\ITranslationService,
	Deadline\ProjectStreamWrapper;

use \DOMElement;

use \PHPTAL,
	\PHPTAL_Filter,
	\PHPTAL_PreFilter,
	\PHPTAL_PreFilter_StripComments,
	\PHPTAL_PreFilter_Normalize,
	\PHPTAL_PreFilter_Compress,
	\PHPTAL_TranslationService;

use Analog\Analog;

class Html extends View {
	public $translator = null, $store, $app, $filters;
	public function __construct(ITranslationService $translator, IStorage $store, App $app) {
		$this->translator = $translator;
		$this->store = $store;
		$this->app = $app;
	}
	public function getContentType() { return 'text/html'; }

	public function setFilters(array $filters) { $this->filters = $filters; }

	public function render(Response $response) {
		$template = $this->store->get('template', 'deadline');

		$this->translator->setLanguage($response->locale);

		array_unshift($this->filters, new UrlPostFilter($this->app->getBaseUrl(), $template));
		$phptal = new PHPTAL();
		$phptal->setOutputMode(PHPTAL::HTML5)
			->setEncoding('UTF-8')
			->setForceReparse(!$this->store->get('live', false))
			->setPhpCodeDestination(ProjectStreamWrapper::getProjectName() . '://cache')
			->setTemplateRepository(ProjectStreamWrapper::resolve(ProjectStreamWrapper::getProjectName() . '://public/templates/' . $template))
			->addPreFilter(new PHPTAL_PreFilter_StripComments())
			->addPreFilter(new PHPTAL_PreFilter_Normalize())
			->addPreFilter(new PHPTAL_PreFilter_Compress())
			->setPostFilter(new PostFilterChain($this->filters))
			->setTranslator(new KeyValueTranslationService($this->translator))
			->setTemplate($response->template);

		foreach($response->getParams() as $key => $value) {
			$phptal->set($key, $value);
		}
		echo $phptal->execute();
	}
}

class PostFilterChain implements PHPTAL_Filter {
	private $filters = [];
	public function __construct(array $filters) { $this->filters = $filters; }
	public function filter($code) { foreach($this->filters as $filter) $code = $filter->filter($code); return $code; }
}

class IFilterPostFilter implements PHPTAL_Filter {
	private $filter;
	public function __construct(IFilter $filter) { $this->filter = $filter; }
	public function filter($code) {
		if($this->filter != null) $this->filter->filter($el);
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
