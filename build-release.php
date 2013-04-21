<?php

use FilesystemIterator as FS,
	RecursiveDirectoryIterator as Directory,
	RecursiveIteratorIterator as Recursive,
	FilterIterator as Filter;

class DeadlineFilter extends Filter {
	public function accept() {
		$current = $this->current();
		$dirs = [
			'#test(s|ing)?#i',
			'#node_modules#i',
			'#docs?#i',
			'#bin#i',
			'#examples?#i',
			'#\.git#i',
			'#demo#i',
			'#build#i',
			'#cache#'
		];
		$files = [
			'#\.gitignore#i',
			'#deadline.sublime-project#i',
			'#deadline.sublime-workspace#i',
			'#package.json#i',
			'#composer.json#i',
			'#composer.lock#i',
			'#phpunit .xml#i',
			'#\.travis(-cub)?.yml#i',
			'#readme(.*)?#i',
			'#test-dist.ini#i',
			'#changelog(.*)?#i',
			'#Jakefile.js#i',
			'#Makefile#i',
			'#build.xml#i',
			'#stub.php#i',
			'#build-release.php#i',
		];
		$result = false;
		foreach($dirs as $dir) {
			if($result === true) continue;
			$result = !!preg_match($dir, dirname($current));
		}
		if($result !== true) {
			foreach($files as $file) {
				if($result === true) continue;
				$result = !!preg_match($file, basename($current));
			}
		}
		return !$result;
	}
}

function relative($from, $to) {
	$from     = explode('/', $from);
	$to       = explode('/', $to);
	$relPath  = $to;

	foreach($from as $depth => $dir) {
		if($dir === $to[$depth]) {
			array_shift($relPath);
		} else {
			$remaining = count($from) - $depth;
			if($remaining > 1) {
				$padLength = (count($relPath) + $remaining - 1) * -1;
				$relPath = array_pad($relPath, $padLength, '..');
				break;
			} else {
				$relPath[0] = './' . $relPath[0];
			}
		}
	}
	return implode('/', $relPath);
}

function buildPhar() {
	$phar = new Phar('build/deadline.phar');
	$phar->setAlias('deadline.phar');
	$phar->buildFromIterator(new DeadlineFilter(new Recursive(new Directory(__DIR__, FS::SKIP_DOTS))), __DIR__);
	$phar->setSignatureAlgorithm(Phar::SHA512);
	$phar->setStub(file_get_contents('stub.php'));
}

function buildDir() {
	$it = new DeadlineFilter(new Recursive(new Directory(__DIR__, FS::SKIP_DOTS)));
	$out = __DIR__ . '/build/release/';
	foreach($it as $file) {
		if(!is_dir($out . relative(__DIR__, $file->getPath()))) {
			mkdir($out . relative(__DIR__, $file->getPath()), 0777, true);
		}
		copy($file->getRealPath(), $out . relative(__DIR__, $file->getRealPath()));
	}
}

switch(isset($argv[1]) ? $argv[1] : '') {
	case 'phar': buildPhar(); break;
	case 'dir': buildDir(); break;
	default: echo "You must provide a type (phar or dir).", PHP_EOL; break;
}
