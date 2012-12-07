<?php

if(extension_loaded('runkit')) {
	if(!function_exists('original_realpath')) {
		runkit_function_rename('realpath', 'original_realpath');
	}
	if(function_exists('realpath')) {
		runkit_function_remove('realpath');
	}
	function realpath($path) {
		return strpos($path, 'deadline://') === 0 ? $path : original_realpath($path);
	}
}
