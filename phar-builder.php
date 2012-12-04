<?php

$root = dirname(__FILE__);
$source = $root . '/src';
$output = $root . '/build';

echo "Building phar from {$source}\n";
$opts = FilesystemIterator::NEW_CURRENT_AND_KEY | FilesystemIterator::SKIP_DOTS;

mkdir($output, 0755);
$phar = new Phar($output . '/deadline.phar', $opts, 'deadline.phar');
$phar->buildFromIterator(new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($source, $opts),
	RecursiveIteratorIterator::SELF_FIRST
), $source);

$stub = <<<END
<?php
Phar::mungServer(array('REQUEST_URI', 'PHP_SELF', 'SCRIPT_NAME', 'SCRIPT_FILENAME'));
Phar::webPhar('deadline.phar', 'index.php', 'index.php', array(), function() { return 'index.php'; });
__HALT_COMPILER();
END;

$phar->setStub($stub);
echo "Built {$output}/deadline.phar succesfully!\n";

?>
