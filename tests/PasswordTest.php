<?php

require_once('password.php');

class PasswordTest extends PHPUnit_Framework_TestCase {
	public function testBuild() {
		Deadline\Password::init();
	}
	/**
	 * @depends testBuild
	 */
	public function testHash() {
		$pass = '123abc';
		$salt = Deadline\Password::current()->gen_salt();
		$hash = Deadline\Password::current()->hash($pass, 7, $salt);
		$this->assertEquals('$2y$07' . $salt . '\\$2U4GQgU/lOtM', $hash);
	}
	/**
	 * @depends testBuild
	 */
	public function testVerify() {
		$pass = '123abc';
		$hash = Deadline\Password::current()->hash($pass);
		$this->assertTrue(Deadline\Password::current()->verify($pass, $hash));
	}
	/**
	 * @depends testBuild
	 */
	public function testFail() {
		$pass = '123abc';
		$hash = Deadline\Password::current()->hash($pass);
		$pass = 'abc123';
		$this->assertFalse(Deadline\Password::current()->verify($pass, $hash));
	}
	/**
	 * @depends testBuild
	 */
	public function testRehash() {
		$pass = '123abc';
		$hash = Deadline\Password::current()->hash($pass);
		$this->assertTrue(Deadline\Password::current()->need_rehash($hash, 'SHA512'));
	}
}