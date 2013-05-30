<?php
namespace Deadline\Controller;

use Deadline\IController,
	Deadline\Request,
	Deadline\Response;

class Hello implements IController {
	private $request;
	final function setup(Request $request) { $this->request = $request; }
	function index() {
		$name = $this->request->getInput('name', 'string');
		if(empty($name)) $name = 'Jeff';
		return (new Response(['name' => $name, 'lang' => $this->request->cookieInput('lang', 'string')]))->setTemplate('hello.tal');
	}
	function getIndex() { return $this->index(); }
}
