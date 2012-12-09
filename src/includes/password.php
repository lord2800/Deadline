<?php
namespace Deadline;

class Password {
	private $algos;
	private $algo;

	private static $instance;
	public static function current() { return static::$instance; }
	public static function init($algo = 'Blowfish') {
		static::$instance = new Password($algo);
	}

	public function gen_salt($algo = '') {
		// Normally, mt_rand is not secure enough for hashing algoritms. However, in this
		// case, we're not using it for its' hashing properties, but instead its' statistical
		// randomness--the area it does excel in.
		if($algo == '') {
			$algo = $this->algos[$this->algo];
		} else {
			$algo = $this->algos[$algo];
		}
		$chars = $algo['salt']['charset'];
		$charlen = strlen($chars)-1;
		$salt = '';
		$len = $algo['salt']['length'];
		if($len == 0) {
			$len = mt_rand(1, 22);
		}
		for($i = 0; $i < $len; $i++) {
			$salt .= $chars[mt_rand(0, $charlen)];
		}
		return $salt;
	}
	public function hash($pass, $cost = 7, $salt = '') {
		$algo = $this->algos[$this->algo];
		// TODO this check is broken for the SHA variants, they define their salt length as 0
		if($algo['salt']['length'] != 0 && strlen($salt) != $algo['salt']['length']) {
			$salt = $this->gen_salt();
		}
		if($cost > $algo['cost']['maximum'] || $cost < $algo['cost']['minimum']) {
			$cost = 7;
		}
		$salt = sprintf($algo['format'], ($cost * $algo['cost']['factor']), $salt);
		// prepend the info about the pass so we can pull it apart later
		return $salt . '\\' . crypt($pass, $salt);
	}
	public function verify($pass, $existing) {
		// take off the info prefix for comparing against crypt() output
		$existing = substr($existing, strpos($existing, '\\')+1);
		$attempt = crypt($pass, $existing);
		return $attempt == $existing;
	}
	public function need_rehash($hash, $algo = '', $cost = 7, $salt = '') {
		if($algo == '') {
			$algo = $this->algo;
		}
		// TODO this is broken for the SHA variants, it won't correctly detect the cost factor
		$current = array(
			'algo' => substr($hash, 0, 4),
			'cost' => (int)substr($hash, 4, 2),
			'salt' => substr($hash, 6, 22)
		);
		return $current['algo'] != $this->algos[$algo]['tag'] ||
			   ($salt != '' && $current['salt'] != $salt) ||
			   $current['cost'] != $cost;
	}
	private function __construct($algo) {
		$this->algo = $algo;
		$this->algos = array(
			'Blowfish' => array(
				'tag' => '$2y$',
				'format' => '$2y$%02d%s',
				'salt' => array(
					'charset' => './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789',
					'length' => 22
				),
				'cost' => array(
					'factor' => 1,
					'minimum' => 4,
					'maximum' => 31
				)
			),
			'SHA256' => array(
				'tag' => '$5$',
				'format' => '$5$rounds=%d$%s$',
				'salt' => array(
					'charset' => './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789',
					'length' => 0
				),
				'cost' => array(
					'factor' => 1000,
					'minimum' => 4,
					'maximum' => 999999
				)
			),
			'SHA512' => array(
				'tag' => '$6$',
				'format' => '$6$rounds=%d$%s$',
				'salt' => array(
					'charset' => './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789',
					'length' => 0
				),
				'cost' => array(
					'factor' => 1000,
					'minimum' => 4,
					'maximum' => 999999
				)
			)
		);
		if(!array_key_exists($algo, $this->algos)) {
			throw new \LogicException('Invalid algorithm!');
		}
	}
}

?>