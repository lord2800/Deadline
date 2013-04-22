<?php
namespace Deadline\Factory;

use \FilesystemIterator as FS;
use \RecursiveDirectoryIterator as DirectoryIterator;
use \RecursiveIteratorIterator as Iterator;

use \ReflectionClass;

use Psr\Log\LoggerInterface;

use Deadline\App,
	Deadline\IStorage,
	Deadline\RouteMatch,
	Deadline\ProjectStreamWrapper,
	Deadline\DeadlineStreamWrapper;

class ControllerFactory {
	private $ns, $instancefactory, $logger;

	public function __construct(LoggerInterface $logger, IStorage $store, InstanceFactory $instancefactory) {
		$this->ns              = $store->get('controller_namespace', 'Deadline\\Controller');
		$this->logger          = $logger;
		$this->instancefactory = $instancefactory;
	}

	private function getNamespaceFromFile($file) {
		$tokens = token_get_all(file_get_contents($file));
		$i = 0;
		for($count = count($tokens); $i < $count; $i++) {
			if($tokens[$i][0] == 381) break;
		}
		$j = $i+1;
		for($count = count($tokens); $j < $count; $j++) {
			if($tokens[$j][0] == ';') break;
		}
		return array_reduce(array_slice($tokens, $i+2, $j - ($i+2)), function (&$result, $item) { $result .= $item[1]; return $result; }, '');
	}

	public function getAll() {
		$this->logger->debug('Scanning for controllers');
		$controllers = [];
		$oldClasses = get_declared_classes();
		// load all available controllers
		// find the builtin and external controllers
		// TODO this is a huge hack--figure out a better way!
		$files = new Iterator(new DirectoryIterator('deadline://include/Deadline/Controller', FS::SKIP_DOTS | FS::CURRENT_AS_FILEINFO | FS::KEY_AS_PATHNAME));
		foreach($files as $file) {
			if(pathinfo($file, PATHINFO_EXTENSION) === 'php') {
				if(!in_array(DeadlineStreamWrapper::resolve($file), get_included_files())) {
					$class = $this->getNamespaceFromFile($file) . '\\' . basename($file, '.php');
					class_exists($class, true);
				}
			}
		}
		$controllerPath = ProjectStreamWrapper::getProjectName() . '://Controller';
		if(is_dir($controllerPath)) {
			$files = new Iterator(new DirectoryIterator($controllerPath, FS::SKIP_DOTS | FS::CURRENT_AS_FILEINFO | FS::KEY_AS_PATHNAME));
			foreach($files as $file) {
				if(pathinfo($file, PATHINFO_EXTENSION) === 'php') {
					if(!in_array(ProjectStreamWrapper::resolve($file), get_included_files())) {
						$class = $this->getNamespaceFromFile($file) . '\\' . basename($file, '.php');
						class_exists($class, true);
					}
				}
			}
		}

		$classes = array_filter(array_diff(get_declared_classes(), $oldClasses), function ($class) {
			return in_array('Deadline\\IController', class_implements($class));
		});
		foreach($classes as $class) {
			$this->logger->debug('Found controller ' . $class);
			$controllers[] = new ReflectionClass($class);
		}
		return $controllers;
	}

	public function get(RouteMatch $route) {
		$instance = $this->instancefactory->get($route->route->controller, ['try' => $this->ns]);

		App::$monitor->snapshot('Controller initialized');
		return $instance;
	}
}