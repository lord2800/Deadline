<?php
require_once('autosave.php');
require_once('router.php');
require_once('request.php');

class RouterTest extends PHPUnit_Framework_TestCase {
	public function testLoad() {
		$router = new Deadline\Router();
		$router->load('testroutes.db');
		$this->assertEquals(0, $router->routeCount());
		return $router;
	}

	/**
	 * @depends testLoad
	 */
	public function testAdd($router) {
		$router->add('/test', array('controller' => 'test', 'method' => 'index'));
		$this->assertEquals(1, $router->routeCount());
		$router->add('/test/:id', array('controller' => 'test', 'method' => 'param'));
		$this->assertEquals(2, $router->routeCount());
		$router->add('/test/test', array('controller' => 'test', 'method' => 'test'));
		$this->assertEquals(3, $router->routeCount());
		$router->add('/test/complex/:id', array('controller' => 'test', 'method' => 'complex'));
		$this->assertEquals(4, $router->routeCount());
		$router->add('/test/optional/:?id', array('controller' => 'test', 'method' => 'optional'));
		$this->assertEquals(5, $router->routeCount());
		return $router;
	}

	/**
	 * @depends testAdd
	 */
	public function testFind($router) {
		$request = new Deadline\Request(array('PATH_INFO' => '/test/test'));
		$result = $router->find($request);
		$this->assertNotEquals(null, $result);
		list($handler, $args) = $result;
		$this->assertEquals('test', $handler['controller']);
		$this->assertEquals('test', $handler['method']);
		return $router;
	}

	/**
	 * @depends testAdd
	 */
	public function testFindShorter($router) {
		$request = new Deadline\Request(array('PATH_INFO' => '/test'));
		$result = $router->find($request);
		$this->assertNotEquals(null, $result);
		list($handler, $args) = $result;
		$this->assertEquals('test', $handler['controller']);
		$this->assertEquals('index', $handler['method']);
		return $router;
	}

	/**
	 * @depends testAdd
	 */
	public function testFailFind($router) {
		$request = new Deadline\Request(array('PATH_INFO' => '/test/fail/me'));
		$result = $router->find($request);
		$this->assertEquals(null, $result);
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
		$this->assertEquals('test', $handler['controller']);
		$this->assertEquals('complex', $handler['method']);
		$this->assertEquals('1', $args['id']);
		return $router;
	}

	/**
	 * @depends testAdd
	 */
	public function testFailComplexFind($router) {
		$request = new Deadline\Request(array('PATH_INFO' => '/test/fail/1'));
		$result = $router->find($request);
		$this->assertEquals(null, $result);
		return $router;
	}

	/**
	 * @depends testAdd
	 */
	public function testComplexOptionalFind($router) {
		$request = new Deadline\Request(array('PATH_INFO' => '/test/optional'));
		$result = $router->find($request);
		$this->assertNotEquals(null, $result);
		list($handler, $args) = $result;
		$this->assertEquals('test', $handler['controller']);
		$this->assertEquals('optional', $handler['method']);
		$this->assertEquals(null, $args['id']);

		$request = new Deadline\Request(array('PATH_INFO' => '/test/optional/1'));
		$result = $router->find($request);
		$this->assertNotEquals(null, $result);
		list($handler, $args) = $result;
		$this->assertEquals('test', $handler['controller']);
		$this->assertEquals('optional', $handler['method']);
		//var_dump($args);
		$this->assertEquals('1', $args['id']);
		return $router;
	}

	/**
	 * @depends testAdd
	 */
	public function testFailComplexOptionalFind($router) {
		$request = new Deadline\Request(array('PATH_INFO' => '/test/fail/complex/'));
		$result = $router->find($request);
		$this->assertEquals(null, $result);
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
		$this->assertEquals(4, $router->routeCount());
		$router->remove('/test/test');
		$this->assertEquals(3, $router->routeCount());
		$router->remove('/test/test');
		$this->assertEquals(3, $router->routeCount());
		$router->remove('/test/complex/:id');
		$this->assertEquals(2, $router->routeCount());
		return $router;
	}
}

?>