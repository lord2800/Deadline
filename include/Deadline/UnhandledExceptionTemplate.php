<?php
namespace Deadline;

use Deadline\Request,
	Deadline\Factory\ViewFactory;

use ExceptionGUI\Parser\ExceptionParsed;
use ExceptionGUI\Templating\TemplateEngineInterface;

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
		$response->exception = $exceptionParsed;
		$response->template  = 'exception.tal';
		$response->lang		 = 'en';

		$view = $this->viewfactory->get($this->request, $response);
		$view->render($response);
	}
}
