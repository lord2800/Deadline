<?php
require_once('analog.php');
require_once('autosave.php');
require_once('storage.php');

class StorageTest extends PHPUnit_Framework_TestCase {
	public function testLoad() {
		$store = new Deadline\Storage();
		$store->load('testdata.db');
		$this->assertTrue($store->clear());
		return $store;
	}

	/**
	 * @depends testLoad
	 */
	public function testSet($store) {
		$store->set('test', true);
		$this->assertFalse($store->clear());
		return $store;
	}

	/**
	 * @depends testSet
	 */
	public function testGet($store) {
		$this->assertTrue($store->get('test'));
		return $store;
	}

	/**
	 * @depends testGet
	 */
	public function testSave($store) {
		$store->save('testdata.db');
		$this->assertFileExists('testdata.db');
		unlink('testdata.db');
		$this->assertFileNotExists('testdata.db');
	}
}
