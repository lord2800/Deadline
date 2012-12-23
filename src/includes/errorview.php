<?php

class ErrorView extends HtmlView {
	private $code = 400, $status;
	public function __construct($encoding = 'UTF-8', $reparse = true) {
		parent::__construct($encoding, $reparse);
		$this->status = array(
			'400' => 'Bad Request',
					 'Unauthorized',
			'403' => 'Forbidden',
					 'Not Found',
					 'Method Not Allowed',
			'409' => 'Conflict',
					 'Gone',
			'500' => 'Internal Server Error'
		);
	}
	public function setCode($code) { $this->code = $code; }
	public function prepare(Deadline\Response $response) {
		parent::setTemplate('error' . $this->code . '.tal');
		parent::prepare($response);
		$response->setHeader('status', $this->code . ' ' . $this->status[$this->code]);
		$this->title = 'Error ' . $this->code;
	}
	public function setTemplate($template) {}
	public function __set($name, $value) {
		if($name != 'template') parent::__set($name, $value);
	}
}
