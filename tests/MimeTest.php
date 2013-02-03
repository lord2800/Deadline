<?php
require_once('mime.php');

class MimeTest extends PHPUnit_Framework_TestCase {
	public function testMapType() {
		$this->assertEquals(Deadline\Mime::type('css'), 'text/css');
	}
	public function testMapExt() {
		$this->assertEquals(Deadline\Mime::ext('text/css'), 'css');
	}
	public function testFailType() {
		$this->assertNotEquals(Deadline\Mime::type('txt'), 'text/css');
	}
	public function testFailExt() {
		$this->assertNotEquals(Deadline\Mime::ext('text/css'), 'txt');
	}
}
