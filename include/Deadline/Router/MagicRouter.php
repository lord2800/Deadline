<?php
namespace Deadline\Router;

use \ReflectionMethod as RM;

use Psr\Log\LoggerInterface;

use Deadline\App,
	Deadline\Route,
	Deadline\Router,
	Deadline\ICache,
	Deadline\Factory\ControllerFactory;

class MagicRouter extends Router {
	private $controllerfactory, $logger, $cache;
	public function __construct(ControllerFactory $controllerfactory, LoggerInterface $logger, ICache $cache) {
		$this->controllerfactory = $controllerfactory;
		$this->logger = $logger;
		$this->cache = $cache;
	}

	public function loadRoutes() {
		$routes = $this->cache->get('routes');
		foreach($routes as $route) {
			if(!($route instanceof Route)) {
				$routes = [];
				break;
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
				$route = '/' . $base . '/' . $method;

				$order = [];
				$required = [];
				$optional = [];
				$params = $routable->getParameters();
				for($i = 0, $len = count($params); $i < $len; $i++) {
					$parameter = $params[$i];
					$order[$parameter->getName()] = $i;
					if($parameter->isOptional()) $optional[] = ['name' => $parameter->getName(), 'default' => $parameter->getDefaultValue()];
					else $required[] = ['name' => $parameter->getName()];
				}
				$this->logger->debug('Adding route ' . $route . ' for ' . $class . '::' . $routable->getName());
				$routes[] = new Route($route, $class, $routable->getName(), $order, $required, $optional);
			}
		}
		return $routes;
	}
}
