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
	Http\Exception\AbstractException as HttpException;

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

	public function getDispatcher() { return $this->dispatcher; }
	public final function serve() {
		$request = $this->injector->get('Request');
		try {
			$this->tryRoute($request);
		} catch(HttpNotFound $e) {
			// try again with the default route
			$defaultRoute = $this->store->get('default_route', '/');
			$this->logger->debug('Specified route not found, rerouting to ' . $defaultRoute);
			$request->path = $defaultRoute;
			$this->tryRoute($request);
		}
	}

	private function tryRoute(Request $request) {
		$response = $this->getDispatcher()->dispatch($request);
		if($response === null) {
			throw new HttpNotImplemented('No response');
		}

		$this->logger->debug('Getting a view for the request');
		$view = $this->viewfactory->get($request, $response);
		static::$monitor->snapshot('View constructed');
		if($view !== null) {
			$this->logger->debug('Sending response');
			$view->render($response);
			static::$monitor->snapshot('Response rendered');
		} else {
			throw new HttpNotImplemented('View not found for response ' . print_r($response, true));
		}
	}

	protected $store,
			  $logger,
			  $acl,
			  $storefactory,
			  $cachefactory,
			  $modelfactory,
			  $viewfactory,
			  $controllerfactory,
			  $injector,
			  $routerfactory,
			  $translatorfactory,
			  $dispatcher;

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

		$app->logger->debug('Setting up exception handler');
		$template = $app->injector->get('UnhandledExceptionTemplate');

		$app->logger->debug('Generating database handles lazily');
		$dbh = $app->injector->get('DatabaseHandle');
		static::$monitor->snapshot('Generated database handles');
		$app->injector->provide('dbh', $dbh);

		// TODO AclFactory (do I really need it? my gut says no)
		$app->logger->debug('Creating ACL handler');
		$app->acl = $app->injector->get('SentryAcl', ['try' => 'Deadline\\Acl']);
		static::$monitor->snapshot('ACL handler created');
		$app->injector->provide('acl', $app->acl);

		$app->logger->debug('Creating dispatcher');
		$app->dispatcher = $app->injector->get('Dispatcher');
		static::$monitor->snapshot('Dispatcher created');
		$app->injector->provide('dispatcher', $app->dispatcher);

		$handler = new ExceptionHandler(!$app->live(), $template);
		$handler->register();

		$app->logger->debug('We are in ' . $app->mode() . ' mode');

		$app->bootstrap();

		static::$monitor->snapshot('App bootstrap complete');

		return $app;
	}
}
