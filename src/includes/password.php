<?php
namespace Deadline;

if(!function_exists('password_hash')) {
	require_once(__DIR__ . '/../vendor/ircmaxell/password-compat/lib/password.php');
}

class Password {
	public static function hash($pass, $cost = null, $salt = null) {
		$opts = array();
		if($cost !== null) $opts['cost'] = $cost;
		if($salt !== null) $opts['salt'] = $salt;
		return password_hash($pass, PASSWORD_DEFAULT, $opts);
	}
	public static function verify($pass, $existing) {
		return password_verify($pass, $existing);
	}
	public static function need_rehash($hash, $cost = 7, $salt = '') {
		return password_needs_rehash($hash, PASSWORD_DEFAULT, array('cost' => $cost, 'salt' => $salt));
	}
}
