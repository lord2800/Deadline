<?php
namespace Deadline;

use Deadline\Factory\RouterFactory,
	Deadline\Factory\ViewFactory,
	Deadline\Factory\ControllerFactory;

use Psr\Log\LoggerInterface;

use Http\Exception\Client\NotFound as HttpNotFound,
	Http\Exception\Server\NotImplemented as HttpNotImplemented;

class Dispatcher {
	private $routerfactory, $viewfactory, $controllerfactory, $logger, $store, $request, $acl;
	public function __construct(RouterFactory $routerfactory, ViewFactory $viewfactory, ControllerFactory $controllerfactory,
								Request $request, LoggerInterface $logger, IStorage $store, Acl $acl) {
		$this->routerfactory = $routerfactory;
		$this->viewfactory = $viewfactory;
		$this->controllerfactory = $controllerfactory;
		$this->logger = $logger;
		$this->store = $store;
		$this->request = $request;
		$this->acl = $acl;
	}

	public final function dispatch() {
		$this->logger->debug('Creating router instance');
		$router = $this->routerfactory->get();
		$router->loadRoutes();

		$this->logger->debug('Finding route for ' . $this->request->verb . ' ' . $this->request->path);
		$route = $router->route($this->request);
		if($route === null) {
			// try again with the default route
			$defaultRoute = $this->store->get('default_route', '/');
			$this->logger->debug('Specified route not found, rerouting to ' . $defaultRoute);
			$this->request->path = $defaultRoute;
			$route = $router->route($this->request);
		}
		// still not found? throw an exception
		if($route === null) {
			throw new HttpNotFound('No route for ' . $this->request->path);
		}

		$this->logger->debug('Getting controller for route');
		$controller = $this->controllerfactory->get($route);
		// don't need the injector for Security, because we're going to manually pass it the params it wants
		$container = new Security($controller, $this->acl);
		$response = null;
		App::$monitor->snapshot('Route determined');

		list($route, $args) = [$route->route, $route->args];
		if(method_exists($controller, 'setup')) {
			$this->logger->debug('Calling controller setup function');
			$controller->setup($this->request);
			App::$monitor->snapshot('Controller setup finished');
		}
		if(method_exists($controller, $route->method)) {
			$this->logger->debug('Calling routed method ' . $route->method);
			$response = call_user_func_array([$container, $route->method], $args);
			App::$monitor->snapshot('Controller route finished');
		} else {
			throw new HttpNotFound('No handler for ' . $route->controller . '->' . $route->method);
		}
		if(method_exists($controller, 'shutdown')) {
			$this->logger->debug('Calling controller shutdown function');
			$controller->shutdown($this->request);
			App::$monitor->snapshot('Controller shutdown finished');
		}
		App::$monitor->snapshot('Controller finished');

		if($response !== null) {
			$this->configureDefaultResponseValues($response);
			$this->logger->debug('Getting a view for the request');
			$view = $this->viewfactory->get($this->request, $response);

			App::$monitor->snapshot('View constructed');
			if($view !== null) {
				$this->logger->debug('Sending response');
				$view->render($response);
				App::$monitor->snapshot('Response rendered');
			} else {
				throw new HttpNotImplemented('View does not exist for this request');
			}
		} else {
			throw new HttpNotImplemented('No response available');
		}
	}

	private function configureDefaultResponseValues(Response $response) {
		$this->logger->debug('Setting default response values (if nonexistent)');
		// TODO this seems like the wrong place for language settings
		// do we have a locale from a cookie?
		$locale = $this->request->cookieInput('lang', 'string');
		if(empty($locale)) {
			$this->logger->debug('Locale not found in a cookie, inferring from Accept-Language header');
			// nope, infer it from Accept-Language
			//$parser = $this->injector->get('QualityParser');
			$locale = str_replace('-', '_', QualityParser::bestQuality($this->request->getHeader('Accept-Language')));
			$this->logger->debug('Determined locale: ' . $locale);
			$response->setHeader('Content-Language', $locale);
			$response->setCookie('lang', $locale);
		}
		App::$monitor->snapshot('Configured default response values');
	}
}
