<?php
// AVOID a namespace here due to class loading
//namespace Deadline;

class HtmlView implements View {
	private $vars = array(), $phptal, $encoding, $template;
	public function __construct($template, $base, $encoding = 'UTF-8', $reparse = false) {
		$this->phptal = new tal();
		$this->phptal->setOutputMode(tal::HTML5)
					 ->setEncoding($encoding)
					 ->setForceReparse($reparse)
					 ->setPhpCodeDestination('deadline://cache')
					 ->setTemplateRepository('deadline://templates/' . $template)
					 ->addPreFilter(new CSSPreFilter())
					 ->addPreFilter(new JSPreFilter())
					 ->addPreFilter(new PHPTAL_PreFilter_StripComments())
					 ->addPreFilter(new PHPTAL_PreFilter_Normalize())
					 ->addPreFilter(new PHPTAL_PreFilter_Compress())
					 ->setPostFilter(new PostFilter($base, $template))
		;
		$this->encoding = $encoding;
		$this->template = $template;
	}
	public function output() { return $this->phptal->execute(); }
	public function getTemplate() { return $this->template; }
	public function getContentType() { return 'text/html; charset=' . $this->encoding; }
	public function setTemplate($file) { $this->phptal->setTemplate($file); }
	public function __set($name, $value) {
		if($name == 'template') { $this->setTemplate($value); }
		else { $this->phptal->$name = $this->vars[$name] = $value; }
	}
	public function __get($name) {
		return array_key_exists($name, $this->vars) ? $this->vars[$name] : null;
	}
}

// HACK: force the PHPTAL class to load to enable its' own autoloader
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
	public function __construct($base, $template) {
		$this->base = $base;
		$this->template = $template;
	}
	public function filter($code) {
		// replace our custom uri namespaces with the real deal
		$code = preg_replace_callback('/template:\/\/([^"]+)/Uim',
			function ($m) {
				return substr($this->base, 5) . '/templates/' . $this->template . '/' . $m[1];
			}, $code);
		$code = preg_replace_callback('/link:\/\/([^"]+)/Uim',
			function ($m) {
				return substr($this->base, 5) . '/index.php/' . $m[1];
			}, $code);
		return $code;
	}
}

?>