<?php

require_once('password.php');

class PasswordTest extends PHPUnit_Framework_TestCase {
	public function testBuild() {
		$password = new Deadline\Password();
		return $password;
	}
	/**
	 * @depends testBuild
	 */
	public function testHash($password) {
		$pass = '123abc';
		$salt = $password->gen_salt();
		$hash = $password->hash($pass, $salt);
		$this->assertEquals('$2y$07' . $salt . '\\$2U4GQgU/lOtM', $hash);
		return $password;
	}
	/**
	 * @depends testBuild
	 */
	public function testVerify($password) {
		$pass = '123abc';
		$hash = $password->hash($pass);
		$this->assertTrue($password->verify($pass, $hash));
	}
	/**
	 * @depends testBuild
	 */
	public function testRehash($password) {
		$pass = '123abc';
		$hash = $password->hash($pass);
		$this->assertTrue($password->need_rehash($hash, 'SHA512'));
	}
}