<?php

$root = __DIR__;
$source = $root . '/src';
$output = $root . '/build';

$mode = isset($argv[1]) ? $argv[1] : 'file';
if($mode != 'file' && $mode != 'phar') {
	die("Invalid mode, must be either 'file' or 'phar'");
}

echo "Building output from {$source} as {$mode}\n";
$mode .= '_output';

class DeadlineFilter extends FilterIterator {
	public function accept() {
		$c = basename($this->current()->getRealPath());
		$d = dirname($this->current()->getRealPath());
		return !is_dir($this->current()->getRealPath()) &&
				// folders
				stripos($d, 'cache') === false &&
				stripos($d, 'test') === false &&
				stripos($d, 'example') === false &&
				stripos($d, 'doc') === false &&
				stripos($d, 'license') === false &&
				stripos($d, '.git') === false &&
				// files
				stripos($c, '.git') === false &&
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

$opts = FilesystemIterator::NEW_CURRENT_AND_KEY | FilesystemIterator::SKIP_DOTS;
$iterator = new DeadlineFilter(
	new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($source, $opts),
		RecursiveIteratorIterator::SELF_FIRST
	)
);

$mode($iterator);

function phar_output($iterator) {
	global $output, $source, $opts;
	$file = $output . '/deadline_' . time() . '.phar';
	$phar = new Phar($file, $opts, 'deadline.phar');
	$phar->buildFromIterator($iterator, $source);

	$web = "<?php
	Phar::mungServer(array('REQUEST_URI', 'PHP_SELF', 'SCRIPT_NAME', 'SCRIPT_FILENAME'));
	Phar::webPhar('deadline.phar', 'index.php', 'index.php', array(), function() { return 'index.php'; });";

	$cli = "<?php
	require_once 'cli.php';";

	$webname = uniqid() . '_web.php';
	$phar[$webname] = $web;
	//$cliname = uniqid() . '_cli.php';
	//$phar[$cliname] = $cli;

	$phar->setDefaultStub($webname);

	/*$phar->setStub("<?php
	Phar::mapPhar();
	if(php_sapi_name() === 'cli') {
		require_once('phar://deadline.phar/$cliname');
	} else {
		require_once('phar://deadline.phar/$webname');
	}
	__HALT_COMPILER();");*/

	echo "Built {$file} succesfully!\n";
}

function file_output($iterator) {
	global $output, $source;
	$len = strlen($source);
	$base = $output . '/deadline_' . time();
	mkdir($base, 0755);
	foreach($iterator as $file) {
		$path = $base . substr($file->getRealPath(), $len);
		if(!is_dir(dirname($path))) {
			mkdir(dirname($path), 0755, true);
		}
		copy($file->getRealPath(), $path);
	}

	echo "Built {$base} successfully!\n";
}