<?php
// AVOID a namespace here due to class loading
//namespace Deadline;

use Deadline\Storage;

class HtmlView implements View {
	private $vars = array(), $encoding, $phptal, $template;
	public function __construct($encoding = 'UTF-8', $reparse = false) {
		$template = Storage::current()->get('template', 'deadline');
		$this->phptal = new tal();
		$this->phptal->setOutputMode(tal::HTML5)
			 ->setEncoding($encoding)
			 ->setForceReparse($reparse)
			 ->setPhpCodeDestination('deadline://cache')
			 ->setTemplateRepository('deadline://templates/' . $template)
			 ->addPreFilter(new PHPTAL_PreFilter_StripComments())
			 ->addPreFilter(new PHPTAL_PreFilter_Normalize())
			 ->addPreFilter(new PHPTAL_PreFilter_Compress())
		//	 ->addPreFilter(new CSSPreFilter())
		//	 ->addPreFilter(new JSPreFilter())
		;
		$this->encoding = $encoding;
		$this->template = $template;
	}
	public function prepare(Deadline\Response $response) {
		$response->setHeader('content type', 'text/html; charset=' . $this->encoding);
		$this->phptal->setPostFilter(new PostFilter($response->getBaseUrl(), $this->template));
	}
	public function output() { return $this->phptal->execute(); }
	public function getTemplate() { return $this->template; }
	public function setTemplate($file) { $this->phptal->setTemplate($file); }
	public function __set($name, $value) {
		if($name == 'template') { $this->setTemplate($value); }
		else { $this->phptal->$name = $this->vars[$name] = $value; }
	}
	public function __get($name) { return array_key_exists($name, $this->vars) ? $this->vars[$name] : null; }
}

// HACK: force the PHPTAL class to load to enable its' own autoloader for PHPTAL_* classes
class tal extends PHPTAL {}

class CSSPreFilter extends PHPTAL_PreFilter {
	public function filterDOM(PHPTAL_Dom_Element $element) {
		// TODO: find all <link> nodes, join them into a single file
		// then find all <style> elements, join them into a file
		// then find all inline styles and attach an id (if it doesn't have one) to apply that style
	}
}

class JSPreFilter extends PHPTAL_PreFilter {
	public function filterDOM(PHPTAL_Dom_Element $element) {
		// TODO: find all <script> elements with a src property, join them into a single file and minify it
		// then filter the rest of the <script> elements into a single file and minify it
	}
}

class PostFilter implements PHPTAL_Filter {
	private $template, $base;
	private $templateRegex = '/template:\/\/([^"]+)/Uim',
			$linkRegex     = '/link:\/\/([^"]+)/Uim';
	public function __construct($base, $template) {
		$parsed = parse_url($base);
		$this->base = '//' . $parsed['host'];
		if(array_key_exists('path', $parsed)) $this->base .= dirname($parsed['path']);
		$this->template = $template;
	}
	public function filter($code) {
		// replace our custom uri namespaces with the real deal
		$code = preg_replace_callback($this->templateRegex,
			function ($m) {
				return $this->base . '/templates/' . $this->template . '/' . $m[1];
			},
			preg_replace_callback($this->linkRegex,
				function ($m) {
					return $this->base . '/' . $m[1];
				}, $code));
		return $code;
	}
}

?>