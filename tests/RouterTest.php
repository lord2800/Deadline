<?php
require_once('autosave.php');
require_once('router.php');
require_once('request.php');

class RouterTest extends PHPUnit_Framework_TestCase {
	public function testLoad() {
		$router = new Deadline\Router();
		$router->load('testroutes.db');
		$this->assertEquals($router->routeCount(), 0);
		return $router;
	}

	/**
	 * @depends testLoad
	 */
	public function testAdd($router) {
		$router->add('/test', array('controller' => 'test', 'method' => 'index'));
		$this->assertEquals($router->routeCount(), 1);
		$router->add('/test/:id', array('controller' => 'test', 'method' => 'param'));
		$this->assertEquals($router->routeCount(), 2);
		$router->add('/test/test', array('controller' => 'test', 'method' => 'test'));
		$this->assertEquals($router->routeCount(), 3);
		$router->add('/test/complex/:id', array('controller' => 'test', 'method' => 'complex'));
		$this->assertEquals($router->routeCount(), 4);
		$router->add('/test/optional/:?id', array('controller' => 'test', 'method' => 'optional'));
		$this->assertEquals($router->routeCount(), 5);
		return $router;
	}

	/**
	 * @depends testAdd
	 */
	public function testFind($router) {
		$request = new Deadline\Request(array('PATH_INFO' => '/test/test'));
		$result = $router->find($request);
		$this->assertNotEquals($result, null);
		list($handler, $args) = $result;
		$this->assertEquals($handler['controller'], 'test');
		$this->assertEquals($handler['method'], 'test');
		return $router;
	}

	/**
	 * @depends testAdd
	 */
	public function testFindShorter($router) {
		$request = new Deadline\Request(array('PATH_INFO' => '/test'));
		$result = $router->find($request);
		$this->assertNotEquals($result, null);
		list($handler, $args) = $result;
		$this->assertEquals($handler['controller'], 'test');
		$this->assertEquals($handler['method'], 'index');
		return $router;
	}

	/**
	 * @depends testAdd
	 */
	public function testFailFind($router) {
		$request = new Deadline\Request(array('PATH_INFO' => '/test/fail/me'));
		$result = $router->find($request);
		$this->assertEquals($result, null);
		return $router;
	}

	/**
	 * @depends testAdd
	 */
	public function testComplexFind($router) {
		$request = new Deadline\Request(array('PATH_INFO' => '/test/complex/1'));
		$result = $router->find($request);
		$this->assertNotEquals($result, null);
		list($handler, $args) = $result;
		$this->assertEquals($handler['controller'], 'test');
		$this->assertEquals($handler['method'], 'complex');
		$this->assertEquals($args['id'], '1');
		return $router;
	}

	/**
	 * @depends testAdd
	 */
	public function testFailComplexFind($router) {
		$request = new Deadline\Request(array('PATH_INFO' => '/test/fail/1'));
		$result = $router->find($request);
		$this->assertEquals($result, null);
		return $router;
	}

	/**
	 * @depends testAdd
	 */
	public function testComplexOptionalFind($router) {
		$request = new Deadline\Request(array('PATH_INFO' => '/test/optional'));
		$result = $router->find($request);
		$this->assertNotEquals($result, null);
		list($handler, $args) = $result;
		$this->assertEquals($handler['controller'], 'test');
		$this->assertEquals($handler['method'], 'optional');
		$this->assertTrue($args->isEmpty());

		$request = new Deadline\Request(array('PATH_INFO' => '/test/optional/1'));
		$result = $router->find($request);
		$this->assertNotEquals($result, null);
		list($handler, $args) = $result;
		$this->assertEquals($handler['controller'], 'test');
		$this->assertEquals($handler['method'], 'optional');
		$this->assertEquals($args['id'], '1');
		return $router;
	}

	/**
	 * @depends testAdd
	 */
	public function testFailComplexOptionalFind($router) {
		$request = new Deadline\Request(array('PATH_INFO' => '/test/fail/complex/'));
		$result = $router->find($request);
		$this->assertEquals($result, null);
		return $router;
	}

	/**
	 * @depends testFind
	 * @depends testFindShorter
	 * @depends testFailFind
	 * @depends testComplexFind
	 * @depends testFailComplexFind
	 */
	public function testRemove($router) {
		$router->remove('/test/optional/:?id');
		$this->assertEquals($router->routeCount(), 4);
		$router->remove('/test/test');
		$this->assertEquals($router->routeCount(), 3);
		$router->remove('/test/test');
		$this->assertEquals($router->routeCount(), 3);
		$router->remove('/test/complex/:id');
		$this->assertEquals($router->routeCount(), 2);
		return $router;
	}
}

?>