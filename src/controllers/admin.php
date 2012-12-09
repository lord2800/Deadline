<?php

use FilesystemIterator as FS;

class Admin {
	private function findControllers() {
		// by convention, all controllers are in deadline://controllers, and any controllers not there
		// will simply not function for admin pages
		// TODO load the classes that file added and only use classes that inherit from Controller (so getMenus
		// and getWidgets work properly in all cases, and so we have proper class names)
		$result = array();
		$it = new RecursiveDirectoryIterator('deadline://controllers', FS::SKIP_DOTS | FS::CURRENT_AS_FILEINFO);
		foreach($it as $file) {
			if($file->getExtension() == 'php') {
				$result[] = $file->getBasename('.' . $file->getExtension());
			}
		}
		return $result;
	}
	private function getMenus() {
		$menus = array();
		// TODO: cache these results for speed
		foreach($this->findControllers() as $controller) {
			$controller = ucfirst(strtolower($controller));
			$instance = new $controller();
			if(method_exists($instance, 'adminMenu')) {
				$menu = $instance->adminMenu();
				foreach($menu as $key => $value) {
					if(parse_url($value, PHP_URL_SCHEME) != 'link') {
						$menu[$key] = 'link://admin/page/' . substr($value, strpos('/', $value)+1);
					}
				}
				$menus[$controller] = $menu;
			}
		}
		return $menus;
	}
	private function getWidgets($response) {
		$widgets = array();
		// TODO: cache these results for speed
		foreach($this->findControllers() as $controller) {
			$controller = ucfirst(strtolower($controller));
			$instance = new $controller();
			$fragment = new PartialHtmlView();
			if(method_exists($instance, 'adminWidget')) {
				$instance->adminWidget($fragment);
				$widgets[] = array('name' => $controller, 'content' => $fragment->output());
			}
		}
		return $widgets;
	}

	public function index($request, $args, $response) {
		$view = $response->getView();
		$view->template = 'admin/index.tal';
		$view->widgets = $this->getWidgets($response);
		return $view;
	}

	public function page($request, $args, $response) {
		$controller = ucfirst(strtolower($args['controller']));
		$method = $args['method'];
		$instance = new $controller();
		$view = $response->getView();
		$fragment = $response->getPartial();
		$instance->$method($request, new Deadline\Container(), $fragment);
		$view->template = 'admin/page.tal';
		$view->content = $fragment->output();
		return $view;
	}

	public function finish($view, $response) {
		$view->controllers = $this->getMenus();
		$view->hideSignonBox = true;
	}
}

?>