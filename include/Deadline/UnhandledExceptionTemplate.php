<?php
namespace Deadline;

use Deadline\Request,
	Deadline\Factory\ViewFactory;

use ExceptionGUI\Parser\ExceptionParsed;
use ExceptionGUI\Templating\TemplateEngineInterface;

class JsonExceptionParsed implements \JsonSerializable {
	private $e;
	public function __construct(ExceptionParsed $e) {
		$this->e = $e;
	}
	public function jsonSerialize() {
		$stack = [];
		$tempStack = $this->e->getStack();
		if(!empty($tempStack)) {
			foreach($tempStack as $frame) {
				$stack[] = new self($frame);
			}
		}
		return [
			'class' => $this->e->getClass(),
			'code' => $this->e->getCode(),
			'message' => $this->e->getMessage(),
			'file' => $this->e->getFile(),
			'line' => $this->e->getLine(),
			'trace' => $this->e->getTrace(),
			'stack' => $stack,
			'linesAround' => $this->e->getLinesAround()
		];
	}
	public function __call($m, $a) { return call_user_func_array([$this->e, $m], $a); }
}

class UnhandledExceptionTemplate implements TemplateEngineInterface {
	private $viewfactory, $request;
	public function __construct(ViewFactory $viewfactory, Request $request) {
		$this->viewfactory = $viewfactory;
		$this->request = $request;
	}

	public function render(ExceptionParsed $exceptionParsed, $devMode) {
		// render the output directly
		$response            = new Response();
		$response->dev       = $devMode;
		$response->exception = new JsonExceptionParsed($exceptionParsed);
		$response->lang		 = 'en';
		$response->setTemplate('exception.tal');

		$view = $this->viewfactory->get($this->request, $response);
		$view->render($response);
	}
}
