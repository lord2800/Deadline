<?php

$root = __DIR__;
$source = $root . '/src';
$output = $root . '/build';

echo "Building phar from {$source}\n";
$opts = FilesystemIterator::NEW_CURRENT_AND_KEY | FilesystemIterator::SKIP_DOTS;

if(!is_dir($output)) {
	mkdir($output, 0755);
}
$phar = new Phar($output . '/deadline.phar', $opts, 'deadline.phar');
$phar->buildFromIterator(new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($source, $opts),
	RecursiveIteratorIterator::SELF_FIRST
), $source);

$web = <<<END
<?php
Phar::mungServer(array('REQUEST_URI', 'PHP_SELF', 'SCRIPT_NAME', 'SCRIPT_FILENAME'));
Phar::webPhar('deadline.phar', 'index.php', 'index.php', array(), function() { return 'index.php'; });
__HALT_COMPILER();
END;

$cli = <<<END
<?php
echo "Hello, cli";
__HALT_COMPILER();
END;

$webname = uniqid() . '_web.php';
$cliname = uniqid() . '_cli.php';
$phar[$webname] = $web;
$phar[$cliname] = $cli;

// TODO use createDefaultStub with a cli interface as well as a web interface, and make the current
// stub into the web interface
$phar->setDefaultStub($cliname, $webname);
echo "Built {$output}/deadline.phar succesfully!\n";

?>
