<?php
namespace Deadline;

use Deadline\Factory\InstanceFactory;

use Analog\Analog,
	Analog\Logger,
	Analog\Handler\File,
	Analog\Handler\Null,
	Analog\Handler\FirePHP,
	Analog\Handler\Stderr;

use Psr\Log\LoggerInterface;

use ExceptionGUI\ExceptionHandler;
use PHPBenchmark\Monitor;

use Http\Exception\Client\NotFound as HttpNotFound,
	Http\Exception\Server\NotImplemented as HttpNotImplemented;

final class App {
	private function __construct() {
		register_shutdown_function(function ($start) {
			$ms    = (microtime(true) - $start) * 1000;
			$sizes = ['B', 'KB', 'MB', 'GB'];
			$size  = 0;
			$count = memory_get_peak_usage();
			while($count > 1024) { $size++; $count /= 1024; }
			$this->logger->debug('Page built in ' . number_format($ms, 3) . 'ms, used ' . number_format($count, 3) . $sizes[$size] . ' memory');
		}, microtime(true));
		register_shutdown_function(function () { echo static::$monitor->output(); });
	}


	public function mode() { return $this->live() ? 'production' : 'debug'; }
	public function live() { return $this->store->get('live', false); }
	public function getBaseUrl() {
		$request = $this->instancefactory->get('Request');
		$ssl = $request->serverInput('https', 'string') === 'on';
		$port = $request->getHeader('server port');
		$url = 'http';
		$url .= $ssl == true ? 's' : '';
		$url .= '://' . $request->getHeader('host');
		if(($ssl && $port != '443') || $port != '80') {
			$url .= ':' . $port;
		}
		return $url;
	}

	private $store,
			$logger,
			$storefactory,
			$cachefactory,
			$modelfactory,
			$viewfactory,
			$controllerfactory,
			$instancefactory,
			$routerfactory,
			$translatorfactory;

	// TODO: move this elsewhere
	public static $monitor;

	public static function create(array $config = []) {
		$config = array_merge([
			'settings' => 'deadline://settings.json',
			'log' => 'firephp://dummy',
			'benchmark' => 'XDebug',
		], $config);

		ob_start();

		$app = new App();

		$monitorClass = 'Deadline\\' . (isset($config['benchmark']) ? $config['benchmark'] : 'Empty') . 'Benchmark';
		static::$monitor = new $monitorClass();
		static::$monitor->snapshot('First start');

		$app->logger = new Logger();
		// TODO this belongs somewhere else, but it's fine here for now
		$logtype = parse_url($config['log'], PHP_URL_SCHEME);
		$logpath = substr($config['log'], strlen($logtype) + 3);
		switch($logtype) {
			case 'file':     $app->logger->handler(File::init($logpath)); break;
			case 'deadline': $app->logger->handler(File::init('deadline://' . $logpath)); break;
			case 'firephp':  $app->logger->handler(FirePHP::init()); break;
			case 'stderr':   $app->logger->handler(Stderr::init()); break;
			case 'null':     $app->logger->handler(EmptyLogger::init()); break;
			default:         $app->logger->handler(Stderr::init()); break;
		}
		static::$monitor->snapshot('Log initialized');

		// hardcoded stream wrapper init--this is not user configurable, nor should it be
		// this class is also not really testable--inherently, it modifies php's functionality
		// and does so in untestable ways
		$app->logger->debug('Initializing stream wrapper');
		DeadlineStreamWrapper::init();

		$app->logger->debug('Creating instance factory');
		$app->instancefactory = new InstanceFactory();
		static::$monitor->snapshot('Instance factory created');
		$app->instancefactory->addDependent('app', $app);
		$app->instancefactory->addDependent('instancefactory', $app->instancefactory);
		$app->instancefactory->addDependent('logger', $app->logger);

		$app->logger->debug('Creating storage factory');
		$app->storefactory = $app->instancefactory->get('StorageFactory', ['try' => 'Deadline\\Factory']);
		static::$monitor->snapshot('Storage factory created');
		$app->store = $app->storefactory->get($config);
		$app->instancefactory->addDependent('store', $app->store);

		$app->logger->debug('Creating request');
		$request = $app->instancefactory->get('Request');
		static::$monitor->snapshot('Request created');
		$app->instancefactory->addDependent('request', $request);

		$app->logger->debug('Creating cache factory');
		$app->cachefactory = $app->instancefactory->get('CacheFactory', ['try' => 'Deadline\\Factory']);
		static::$monitor->snapshot('Cache factory created');
		$app->instancefactory->addDependent('cache', $app->cachefactory->get());

		$app->logger->debug('Creating translator factory');
		$app->translatorfactory = $app->instancefactory->get('TranslatorFactory', ['try' => 'Deadline\\Factory']);
		static::$monitor->snapshot('Translator factory created');
		$app->instancefactory->addDependent('translatorfactory', $app->translatorfactory);
		$app->instancefactory->addDependent('translator', $app->translatorfactory->get());

		$app->logger->debug('Creating model factory');
		$app->modelfactory = $app->instancefactory->get('ModelFactory', ['try' => 'Deadline\\Factory']);
		static::$monitor->snapshot('Model factory created');
		$app->instancefactory->addDependent('modelfactory', $app->modelfactory);

		$app->logger->debug('Creating view factory');
		$app->viewfactory = $app->instancefactory->get('ViewFactory', ['try' => 'Deadline\\Factory']);
		static::$monitor->snapshot('View factory created');
		$app->instancefactory->addDependent('viewfactory', $app->viewfactory);

		$app->logger->debug('Creating controller factory');
		$app->controllerfactory = $app->instancefactory->get('ControllerFactory', ['try' => 'Deadline\\Factory']);
		static::$monitor->snapshot('Controller factory created');
		$app->instancefactory->addDependent('controllerfactory', $app->controllerfactory);

		$app->logger->debug('Creating router factory');
		$app->routerfactory = $app->instancefactory->get('RouterFactory', ['try' => 'Deadline\\Factory']);
		static::$monitor->snapshot('Router factory created');
		$app->instancefactory->addDependent('routerfactory', $app->routerfactory);

		$app->logger->debug('We are in ' . $app->mode() . ' mode');

		$app->logger->debug('Setting up exception handler');
		$template = $app->instancefactory->get('UnhandledExceptionTemplate');

		$handler = new ExceptionHandler(!$app->live(), $template);
		$handler->register();

		static::$monitor->snapshot('App bootstrap complete');

		return $app;
	}

	public function serve() {
		$this->logger->debug('Creating router instance');
		$router = $this->routerfactory->get();
		$router->loadRoutes();

		$request = $this->instancefactory->get('Request');
		$this->logger->debug('Finding route for ' . $request->path);
		$route = $router->route($request);
		if($route === null) {
			throw new HttpNotFound('No route for ' . $request->path);
		}
		$this->logger->debug('Getting controller for route');
		$controller = $this->controllerfactory->get($route);
		$response   = null;
		static::$monitor->snapshot('Route determined');

		list($route, $args) = [$route->route, $route->args];
		if(method_exists($controller, 'setup')) {
			$this->logger->debug('Calling controller setup function');
			$controller->setup($request);
			static::$monitor->snapshot('Controller setup finished');
		}
		if(method_exists($controller, $route->method)) {
			$this->logger->debug('Calling routed method ' . $route->method);
			$response = call_user_func_array([$controller, $route->method], $args);
			static::$monitor->snapshot('Controller route finished');
		} else {
			throw new HttpNotFound('No handler for ' . $route->controller . '->' . $route->method);
		}
		if(method_exists($controller, 'shutdown')) {
			$this->logger->debug('Calling controller shutdown function');
			$controller->shutdown($request);
			static::$monitor->snapshot('Controller shutdown finished');
		}
		static::$monitor->snapshot('Controller finished');

		if($response !== null) {
			$this->configureDefaultResponseValues($request, $response);
			$this->logger->debug('Getting a view for the request');
			$view = $this->viewfactory->get($request, $response);
			static::$monitor->snapshot('View constructed');
			if($view !== null) {
				$this->logger->debug('Sending response');
				$view->render($response);
				static::$monitor->snapshot('Response rendered');
			} else {
				throw new HttpNotImplemented('View does not exist for this request');
			}
		} else {
			throw new HttpNotImplemented('No response available');
		}
	}

	private function configureDefaultResponseValues(Request $request, Response $response) {
		$this->logger->debug('Setting default response values (if nonexistent)');
		// TODO this seems like the wrong place for language settings
		if(!isset($response->lang)) {
			$parser = $this->instancefactory->get('QualityParser');
			// do we have a locale from a cookie?
			$locale = $request->cookieInput('lang', 'string');
			if(empty($locale)) {
				$this->logger->debug('Locale not found in a cookie, inferring from Accept-Language header');
				// nope, infer it from Accept-Language
				$locale = $parser->bestQuality($request->getHeader('Accept-Language'));
			}
			$locale = str_replace('-', '_', $locale);
			$this->logger->debug('Determined locale: ' . $locale);
			$response->lang = $locale;
			$response->setHeader('Content-Language', $locale);
			$response->setCookie('lang', $locale);
		}
		static::$monitor->snapshot('Configured default response values');
	}
}
