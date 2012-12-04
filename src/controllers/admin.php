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
			$fragment = $response->getPartial();
			if(method_exists($instance, 'adminWidget')) {
				$instance->adminWidget($fragment);
				$widgets[] = array('name' => $controller, 'content' => $fragment->output());
			}
		}
		return $widgets;
	}

	public function index($request, $args, $response) {
		$response->view->template = 'admin/index.tal';
		$response->view->widgets = $this->getWidgets($response);
	}

	public function page($request, $args, $response) {
		$controller = ucfirst(strtolower($args['controller']));
		$method = $args['method'];
		$instance = new $controller();
		$fragment = $response->getPartial();
		$instance->$method($request, new Deadline\Container(), $fragment);
		$response->view->template = 'admin/page.tal';
		$response->view->content = $fragment->output();
	}

	public function finish($response) {
		$response->view->controllers = $this->getMenus();
		$response->view->hideSignonBox = true;
	}
}

?>