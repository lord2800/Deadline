<?php

$root = __DIR__;
$source = $root . '/src';
$output = $root . '/build';

echo "Building phar from {$source}\n";
$opts = FilesystemIterator::NEW_CURRENT_AND_KEY | FilesystemIterator::SKIP_DOTS;

class DeadlineFilter extends FilterIterator {
	public function accept() {
		$c = $this->current()->getRealPath();
		return !is_dir($c) && 
				// folders
				stripos($c, 'cache') === false &&
				stripos($c, 'test') === false &&
				stripos($c, 'example') === false &&
				stripos($c, 'doc') === false &&
				stripos($c, 'license') === false &&
				stripos($c, '.git') === false &&
				// files
				stripos($c, '.travis.yml') === false &&
				stripos($c, '.travis-cub.yml') === false &&
				stripos($c, 'composer.json') === false &&
				stripos($c, 'composer.lock') === false &&
				stripos($c, 'phpunit.xml') === false &&
				stripos($c, 'copying') === false &&
				stripos($c, 'makefile') === false &&
				stripos($c, 'build.xml') === false &&
				stripos($c, 'readme') === false
		;
	}
}

if(!is_dir($output)) {
	mkdir($output, 0755);
}
$phar = new Phar($output . '/deadline.phar', $opts, 'deadline.phar');
$phar->buildFromIterator(
//foreach(
	new DeadlineFilter(
		new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($source, $opts),
			RecursiveIteratorIterator::SELF_FIRST
		)
	)
, $source);
//as $file) echo $file . PHP_EOL;
$web = <<<END
<?php
Phar::mungServer(array('REQUEST_URI', 'PHP_SELF', 'SCRIPT_NAME', 'SCRIPT_FILENAME'));
Phar::webPhar('deadline.phar', 'index.php', 'index.php', array(), function() { return 'index.php'; });
__HALT_COMPILER();
END;

$cli = <<<END
<?php
require_once 'cli.php';
__HALT_COMPILER();
END;

$webname = uniqid() . '_web.php';
$cliname = uniqid() . '_cli.php';
$phar[$webname] = $web;
$phar[$cliname] = $cli;

$phar->setDefaultStub($cliname, $webname);
echo "Built {$output}/deadline.phar succesfully!\n";
