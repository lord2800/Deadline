<?php
// AVOID a namespace here due to class loading
//namespace Deadline;

use Deadline\Storage;
use Deadline\PathWrapper;

class PartialHtmlView implements View {
	private $vars = array(), $phptal, $encoding, $template;
	public function __construct($encoding = 'UTF-8', $reparse = false) {
		$template = App::store()->get('template', 'deadline');
		$this->phptal = new \PHPTAL();
		$this->phptal->setOutputMode(\PHPTAL::HTML5)
					 ->setEncoding($encoding)
					 ->setForceReparse($reparse)
					 // HACK: resolve these paths beforehand, because realpath() doesn't work with stream wrappers
					 ->setPhpCodeDestination(PathWrapper::resolve('deadline://cache'))
					 ->setTemplateRepository(PathWrapper::resolve('deadline://templates/') . $template)
					 ->addPreFilter(new PHPTAL_PreFilter_StripComments())
					 ->addPreFilter(new PHPTAL_PreFilter_Normalize())
					 ->addPreFilter(new PHPTAL_PreFilter_Compress())
					 ->setPostFilter(new FragmentFilter())
		;
		$this->encoding = $encoding;
		$this->template = $template;
	}
	public function prepare(Deadline\Response $response) {}
	public function hasOutput() { return true; }
	public function output() { return $this->phptal->execute(); }
	public function getTemplate() { return $this->template; }
	public function setTemplate($file) { $this->phptal->setTemplate($file); }
	public function __set($name, $value) {
		if($name == 'template') { $this->setTemplate($value); }
		else { $this->phptal->$name = $this->vars[$name] = $value; }
	}
	public function __get($name) {
		return array_key_exists($name, $this->vars) ? $this->vars[$name] : null;
	}
}

class FragmentFilter implements \PHPTAL_Filter {
	public function filter($code) {
		if(stripos($code, '<html') !== false) {
			$doc = new \DOMDocument();
			$doc->loadHTML($code);
			$xpath = new \DOMXPath($doc);
			$body = $xpath->query('//body');
			if($body->length > 0) {
				$code = str_replace('<body', '<div', $doc->saveHTML($body->item(0)));
			}
		}
		return $code;
	}
}
