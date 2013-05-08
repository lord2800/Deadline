<?php
namespace Deadline;

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

class App {
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


	public final function mode() { return $this->live() ? 'production' : 'debug'; }
	public final function live() { return $this->store->get('live', false); }
	public function bootstrap() {}
	public function getBaseUrl() {
		$request = $this->injector->get('Request');
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

	protected $store,
			  $logger,
			  $storefactory,
			  $cachefactory,
			  $modelfactory,
			  $viewfactory,
			  $controllerfactory,
			  $injector,
			  $routerfactory,
			  $translatorfactory;

	// TODO: move this elsewhere
	public static $monitor;

	public static final function create(array $config = []) {
		$config = array_merge([
			'settings' => '',
			'log' => 'firephp://dummy',
			'benchmark' => 'XDebug',
			'project_name' => 'project'
		], $config);
		$config['settings'] = empty($config['settings']) ? $config['project_name'] . '://settings.json' : $config['settings'];

		ob_start();

		$app = new static();

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
		ProjectStreamWrapper::init($config['project_name']);

		$app->logger->debug('Creating instance factory');
		$app->injector = new Injector();
		static::$monitor->snapshot('Dependency injector created');
		$app->injector->provide('app', $app);
		$app->injector->provide('injector', $app->injector);
		$app->injector->provide('logger', $app->logger);

		$app->logger->debug('Creating storage factory');
		$app->storefactory = $app->injector->get('StorageFactory', ['try' => 'Deadline\\Factory']);
		static::$monitor->snapshot('Storage factory created');
		$app->store = $app->storefactory->get($config);
		$app->injector->provide('store', $app->store);

		$app->logger->debug('Creating request');
		$request = $app->injector->get('Request');
		static::$monitor->snapshot('Request created');
		$app->injector->provide('request', $request);

		$app->logger->debug('Creating cache factory');
		$app->cachefactory = $app->injector->get('CacheFactory', ['try' => 'Deadline\\Factory']);
		static::$monitor->snapshot('Cache factory created');
		$app->injector->provide('cache', $app->cachefactory->get());

		$app->logger->debug('Creating translator factory');
		$app->translatorfactory = $app->injector->get('TranslatorFactory', ['try' => 'Deadline\\Factory']);
		static::$monitor->snapshot('Translator factory created');
		$app->injector->provide('translatorfactory', $app->translatorfactory);
		$app->injector->provide('translator', $app->translatorfactory->get());

		$app->logger->debug('Creating model factory');
		$app->modelfactory = $app->injector->get('ModelFactory', ['try' => 'Deadline\\Factory']);
		static::$monitor->snapshot('Model factory created');
		$app->injector->provide('modelfactory', $app->modelfactory);

		$app->logger->debug('Creating view factory');
		$app->viewfactory = $app->injector->get('ViewFactory', ['try' => 'Deadline\\Factory']);
		static::$monitor->snapshot('View factory created');
		$app->injector->provide('viewfactory', $app->viewfactory);

		$app->logger->debug('Creating controller factory');
		$app->controllerfactory = $app->injector->get('ControllerFactory', ['try' => 'Deadline\\Factory']);
		static::$monitor->snapshot('Controller factory created');
		$app->injector->provide('controllerfactory', $app->controllerfactory);

		$app->logger->debug('Creating router factory');
		$app->routerfactory = $app->injector->get('RouterFactory', ['try' => 'Deadline\\Factory']);
		static::$monitor->snapshot('Router factory created');
		$app->injector->provide('routerfactory', $app->routerfactory);

		$app->logger->debug('We are in ' . $app->mode() . ' mode');

		$app->logger->debug('Setting up exception handler');
		$template = $app->injector->get('UnhandledExceptionTemplate');

		$handler = new ExceptionHandler(!$app->live(), $template);
		$handler->register();

		$app->bootstrap();

		static::$monitor->snapshot('App bootstrap complete');

		return $app;
	}

	public final function serve() {
		$this->logger->debug('Creating router instance');
		$router = $this->routerfactory->get();
		$router->loadRoutes();

		$request = $this->injector->get('Request');
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
		// do we have a locale from a cookie?
		$locale = $request->cookieInput('lang', 'string');
		if(empty($locale)) {
			$this->logger->debug('Locale not found in a cookie, inferring from Accept-Language header');
			// nope, infer it from Accept-Language
			$parser = $this->injector->get('QualityParser');
			$locale = str_replace('-', '_', $parser->bestQuality($request->getHeader('Accept-Language')));
			$this->logger->debug('Determined locale: ' . $locale);
			$response->setHeader('Content-Language', $locale);
			$response->setCookie('lang', $locale);
		}
		static::$monitor->snapshot('Configured default response values');
	}
}
