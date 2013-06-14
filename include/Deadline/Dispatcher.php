<?php
namespace Deadline;

use Deadline\Factory\RouterFactory,
	Deadline\Factory\ViewFactory,
	Deadline\Factory\ControllerFactory;

use Psr\Log\LoggerInterface;

use Http\Exception\Server\NotImplemented as HttpNotImplemented;

class Dispatcher {
	private $routerfactory, $controllerfactory, $logger, $acl;
	public function __construct(RouterFactory $routerfactory, ControllerFactory $controllerfactory, LoggerInterface $logger, Acl $acl) {
		$this->routerfactory = $routerfactory;
		$this->controllerfactory = $controllerfactory;
		$this->logger = $logger;
		$this->acl = $acl;
	}

	public final function dispatch(Request $request) {
		$router = $this->routerfactory->get();
		$router->loadRoutes();

		$this->logger->debug('Finding route for ' . $request->verb . ' ' . $request->path);
		$route = $router->route($request);
		if($route === null) {
			throw new RouteNotFoundException('No route for ' . $request->path);
		}
		$this->logger->debug('Found route ' . $route->route->controller . '::' . $route->route->method);

		$this->logger->debug('Getting controller for route');
		$controller = $this->controllerfactory->get($route);
		// don't need the injector for Security, because we're going to manually pass it the params it wants
		$container = new Security($controller, $this->acl);
		$response = null;
		App::$monitor->snapshot('Route determined');

		list($route, $args) = [$route->route, $route->args];
		if(method_exists($controller, 'setup')) {
			$this->logger->debug('Calling controller setup function');
			$controller->setup($request);
			App::$monitor->snapshot('Controller setup finished');
		}
		if(method_exists($controller, $route->method)) {
			$this->logger->debug('Calling routed method ' . $route->method);
			$response = call_user_func_array([$container, $route->method], $args);
			App::$monitor->snapshot('Controller route finished');
		} else {
			throw new HttpNotImplemented('No handler for ' . $route->controller . '->' . $route->method);
		}
		if(method_exists($controller, 'shutdown')) {
			$this->logger->debug('Calling controller shutdown function');
			$controller->shutdown($request);
			App::$monitor->snapshot('Controller shutdown finished');
		}
		App::$monitor->snapshot('Controller finished');

		if($response !== null) {
			$this->configureDefaultResponseValues($request, $response);
		}
		return $response;
	}

	private function configureDefaultResponseValues(Request $request, Response $response) {
		$this->logger->debug('Setting default response values (if nonexistent)');
		// TODO this seems like the wrong place for language settings
		// do we have a locale from a cookie?
		$locale = $request->cookieInput('lang', 'string');
		if(empty($locale)) {
			$this->logger->debug('Locale not found in a cookie, inferring from Accept-Language header');
			// nope, infer it from Accept-Language
			//$parser = $this->injector->get('QualityParser');
			$locale = str_replace('-', '_', QualityParser::bestQuality($request->getHeader('Accept-Language')));
			$this->logger->debug('Determined locale: ' . $locale);
			$response->setHeader('Content-Language', $locale);
			$response->setCookie('lang', $locale);
		}
		App::$monitor->snapshot('Configured default response values');
	}
}
