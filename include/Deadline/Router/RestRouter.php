<?php
namespace Deadline\Router;

use \ReflectionMethod as RM;

use Psr\Log\LoggerInterface;

use Deadline\App,
	Deadline\Route,
	Deadline\Router,
	Deadline\ICache,
	Deadline\Request,
	Deadline\Factory\ControllerFactory;

class RestRouter extends Router {
	private $controllerfactory, $logger, $cache;
	private $supportedHttpVerbs = [
		'get',
		'head',
		'post',
		'put',
		'delete',
		'options',
		'connect'
	];
	public function __construct(ControllerFactory $controllerfactory, LoggerInterface $logger, ICache $cache) {
		$this->controllerfactory = $controllerfactory;
		$this->logger = $logger;
		$this->cache = $cache;
	}
	public function addHttpVerbs(array $verbs) {
		array_merge($this->supportedHttpVerbs, $verbs);
		$this->clearRoutes();
		$this->loadRoutes();
	}
	public function route(Request $request) {
		$route = parent::route($request);
		if($route !== null) {
			if(strtolower($request->verb) !== strtolower($route->route->verb)) return null;
		}
		return $route;
	}

	public function loadRoutes() {
		$routes = $this->cache->get('routes');
		if(!empty($routes)) {
			foreach($routes as $route) {
				if(!($route instanceof Route)) {
					$routes = [];
					break;
				}
			}
		}

		if(empty($routes)) {
			$this->logger->debug('Routes not cached, building routes');
			$routes = $this->getRoutes();
			$this->cache->set('routes', $routes);
		}

		foreach($routes as $route) {
			$this->addRoute($route);
		}
	}

	protected function getRoutes() {
		$routes = [];
		$controllers = $this->controllerfactory->getAll();
		foreach($controllers as $controller) {
			foreach($controller->getMethods(RM::IS_PUBLIC) as $routable) {
				if($routable->getDeclaringClass() != $controller) continue;
				if($routable->getName() == 'setup' || $routable->getName() == 'shutdown') continue;
				if($routable->isConstructor()) continue;

				$class = str_replace($controller->getNamespaceName() . '\\', '', $controller->getName());
				$base = strtolower(str_replace('Controller', '', $class));
				$method = strtolower($routable->getName());

				preg_match('#^(' . implode('|', array_map('preg_quote', $this->supportedHttpVerbs)) . ')(.*)$#', $method, $matches);
				if(empty($matches)) continue;

				$verb = $matches[1];
				$method = $matches[2];
				$route = '/' . $base . (!empty($method) ? '/' : '') . $method;

				$order = [];
				$required = [];
				$optional = [];
				$params = $routable->getParameters();
				for($i = 0, $len = count($params); $i < $len; $i++) {
					$parameter = $params[$i];
					$order[$parameter->getName()] = $parameter->getPosition();
					if($parameter->isOptional()) {
						$optional[] = ['name' => $parameter->getName(), 'default' => $parameter->getDefaultValue()];
						$route .= '/:?' . $parameter->getName();
					}
					else {
						$required[] = ['name' => $parameter->getName()];
						$route .= '/:' . $parameter->getName();
					}
				}
				$this->logger->debug('Adding route ' . $route . ' for ' . $class . '::' . $routable->getName());
				$routes[] = new RestRoute($verb, $route, $class, $routable->getName(), $order, $required, $optional);
			}
		}
		return $routes;
	}
}

class RestRoute extends Route {
	public $verb;
	public function __construct($verb, $route, $controller, $method, array $order, array $required, array $optional) {
		parent::__construct($route, $controller, $method, $order, $required, $optional);
		$this->verb = $verb;
	}
}
