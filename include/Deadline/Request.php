<?php
namespace Deadline;

class Request {
	private static $sanitizeTypes = [
		'special_chars' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
		'string'        => FILTER_SANITIZE_STRING,
		'float'         => FILTER_SANITIZE_NUMBER_FLOAT,
		'int'           => FILTER_SANITIZE_NUMBER_INT,
		'email'         => FILTER_SANITIZE_EMAIL,
		'url'           => FILTER_SANITIZE_URL,
		'array'         => FILTER_UNSAFE_RAW
	];
	private static $validateTypes = [
		'float' => FILTER_VALIDATE_FLOAT,
		'int'   => FILTER_VALIDATE_INT,
		'bool'  => FILTER_VALIDATE_BOOLEAN,
		'email' => FILTER_VALIDATE_EMAIL,
		'url'   => FILTER_VALIDATE_URL,
		'ip'    => FILTER_VALIDATE_IP,
		'regex' => FILTER_VALIDATE_REGEXP,
		'array' => FILTER_CALLBACK
	];

	protected $body = null, $rewrittenPath = null;

	protected function input($type, $name, $validate, array $validation, $sanitize, array $sanitization) {
		$sanitization['flags'] = (isset($sanitization['flags']) ? $sanitization['flags'] : 0) | FILTER_NULL_ON_FAILURE;
		$validation['flags'] = (isset($validation['flags']) ? $validation['flags'] : 0) | FILTER_NULL_ON_FAILURE;
		if($validate === 'array') {
			$sanitize = 'array';
			$validation = ['options' => function ($value) { return is_array($value); }];
		}

		$sanitized = filter_input($type, $name, static::$sanitizeTypes[$sanitize], $sanitization);
		return $validate != 'string' ? filter_var($sanitized, static::$validateTypes[$validate], $validation) : $sanitized;
	}

	public function getInput($name, $validateType, array $validateOptions = [], $sanitizeType = 'string', array $sanitizeOptions = []) {
		return $this->input(INPUT_GET, $name, $validateType, $validateOptions, $sanitizeType, $sanitizeOptions);
	}

	public function postInput($name, $validateType, array $validateOptions = [], $sanitizeType = 'string', array $sanitizeOptions = []) {
		return $this->input(INPUT_POST, $name, $validateType, $validateOptions, $sanitizeType, $sanitizeOptions);
	}

	public function cookieInput($name, $validateType, array $validateOptions = [], $sanitizeType = 'string', array $sanitizeOptions = []) {
		return $this->input(INPUT_COOKIE, $name, $validateType, $validateOptions, $sanitizeType, $sanitizeOptions);
	}

	public function serverInput($name, $validateType, array $validateOptions = [], $sanitizeType = 'string', array $sanitizeOptions = []) {
		return $this->input(INPUT_SERVER, $name, $validateType, $validateOptions, $sanitizeType, $sanitizeOptions);
	}

	public function getHeader($name, $validateType = 'string', array $validateOptions = [], $sanitizeType = 'string', array $sanitizeOptions = []) {
		$key = 'HTTP_' . strtoupper(str_replace(' ', '_', str_replace('-', '_', $name)));

		return $this->input(INPUT_SERVER, $key, $validateType, $validateOptions, $sanitizeType, $sanitizeOptions);
	}

	public function __get($name) {
		switch($name) {
			case 'path': return $this->rewrittenPath ?: parse_url($this->uri, PHP_URL_PATH); break;
			case 'uri': return $this->serverInput('REQUEST_URI', 'string'); break;
			case 'verb': return $this->serverInput('REQUEST_METHOD', 'string'); break;
			case 'rawBody': return $this->body ?: ($this->body = file_get_contents('php://input')); break;
			case 'jsonBody': return json_decode($this->body); break;
		}
	}
	public function __set($name, $value) {
		if($name === 'path') { $this->rewrittenPath = $value; }
	}
}
