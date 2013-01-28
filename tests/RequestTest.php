<?php
require_once('autosave.php');
require_once('container.php');
require_once('request.php');

class RequestTest extends PHPUnit_Framework_TestCase {
	public function testBuild() {
		$request = new Deadline\Request(array(
			'HTTPS' => 'off',
			'SERVER_NAME' => 'lord2800.me',
			'SERVER_PROTOCOL' => 'HTTP/1.1',
			'REQUEST_METHOD' => 'GET',
			'REQUEST_TIME' => '1353542108',
			'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'HTTP_ACCEPT_CHARSET' => 'iso-8859-1,*,utf-8',
			'HTTP_ACCEPT_LANGUAGE' => 'en',
			'HTTP_REFERER' => 'http://lord2800.me/index.html',
			'HTTP_USER_AGENT' => 'Test/1.0',
			'SERVER_PORT' => '80',
			'REQUEST_URI' => '/test/page.php?var1=true&var2=xdf',
			'PATH_INFO' => '/test/page.php',
			'QUERY_STRING' => 'var1=true&var2=xdf',
			'HTTP_X_REQUESTED_BY' => 'json',
			'HTTP_IF_MODIFIED_SINCE' => 'Thu, 22 Nov 2012 15:40:32 -0800',
			'HTTP_IF_NONE_MATCH' => 'abc123'
		));
		return $request;
	}

	/**
	 * @depends testBuild
	 */
	public function testSSL($request) {
		$this->assertFalse($request->ssl);
	}

	/**
	 * @depends testBuild
	 */
	public function testHost($request) {
		$this->assertEquals($request->host, 'lord2800.me');
	}
	/**
	 * @depends testBuild
	 */
	public function testProto($request) {
		$this->assertEquals($request->proto, 'HTTP/1.1');
	}
	/**
	 * @depends testBuild
	 */
	public function testVerb($request) {
		$this->assertEquals($request->verb, 'GET');
	}
	/**
	 * @depends testBuild
	 */
	public function testTime($request) {
		$time = \DateTime::createFromFormat('U', '1353542108');
		$this->assertEquals($request->requestTime, $time);
	}
	/**
	 * @depends testBuild
	 */
	public function testCharset($request) {
		$this->assertEquals($request->charset, array('iso-8859-1','*','utf-8'));
	}
	/**
	 * @depends testBuild
	 */
	public function testLang($request) {
		$this->assertEquals($request->lang, array(array('lang' => 'en', 'quality' => '1')));
	}
	/**
	 * @depends testBuild
	 */
	public function testAccept($request) {
		$this->assertEquals($request->accept, array(
			array('type' => 'text/html', 'quality' => '1'),
			array('type' => 'application/xhtml+xml', 'quality' => '1'),
			array('type' => 'application/xml', 'quality' => '0.9'),
			array('type' => '*/*', 'quality' => '0.8'),
		));
	}
	/**
	 * @depends testBuild
	 */
	public function testReferrer($request) {
		$this->assertEquals($request->referrer, 'http://lord2800.me/index.html');
	}
	/**
	 * @depends testBuild
	 */
	public function testUserAgent($request) {
		$this->assertEquals($request->userAgent, 'Test/1.0');
	}
	/**
	 * @depends testBuild
	 */
	public function testPort($request) {
		$this->assertEquals($request->port, '80');
	}
	/**
	 * @depends testBuild
	 */
	public function testUri($request) {
		$this->assertEquals($request->uri, '/test/page.php?var1=true&var2=xdf');
	}
	/**
	 * @depends testBuild
	 */
	public function testPath($request) {
		$this->assertEquals($request->path, '/test/page.php');
	}
	/**
	 * @depends testBuild
	 */
	public function testCacheEtag($request) {
		$this->assertEquals($request->cache['etag'], 'abc123');
	}
	/**
	 * @depends testBuild
	 */
	public function testCacheModified($request) {
		$time = new \DateTime('Thu, 22 Nov 2012 15:40:32 -0800');
		$this->assertEquals($request->cache['modified'], $time);
	}
	/**
	 * @depends testBuild
	 */
	public function testQuery($request) {
		$this->assertEquals($request->query, array('var1'=>'true', 'var2'=>'xdf'));
	}
	/**
	 * @depends testBuild
	 */
	public function testHeader($request) {
		$this->assertEquals($request->getHeader('X-Requested-By'), 'json');
	}
	/**
	 * @depends testBuild
	 */
	public function testUrl($request) {
		$this->assertEquals($request->url, 'http://lord2800.me/test/page.php?var1=true&var2=xdf');
	}
}

?>
