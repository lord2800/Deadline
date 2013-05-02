<?php
namespace Deadline\Factory;

use \FilesystemIterator as FS;
use \RecursiveDirectoryIterator as DirectoryIterator;
use \RecursiveIteratorIterator as Iterator;

use \ReflectionClass;

use Bitworking\Mimeparse;

use Psr\Log\LoggerInterface;

use Deadline\App,
	Deadline\IStorage,
	Deadline\ICache,
	Deadline\IFilter,
	Deadline\Request,
	Deadline\Response,
	Deadline\ProjectStreamWrapper;

class ViewFactory {
	private $ns, $instancefactory, $logger, $cache, $store, $filters = [];

	public function __construct(LoggerInterface $logger, IStorage $store, InstanceFactory $instancefactory, ICache $cache) {
		$this->ns              = $store->get('view_namespace', 'Deadline\\View');
		$this->instancefactory = $instancefactory;
		$this->store           = $store;
		$this->logger          = $logger;
		$this->cache           = $cache;
	}

	public function addFilter(IFilter $filter) { $this->filters[] = $filter; }

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

	public function get(Request $request, Response $response) {
		$result = null;
		// does the response dictate a type (i.e. download)?
		if($response->getDownloadable()) {
			$view = $this->instancefactory->get('FileView', ['try' => $this->ns]);
		} else {
			$type = $this->getViewType($request->getHeader('accept'));
			$view = $this->instancefactory->get($type, ['try' => $this->ns]);
		}
		// set the content type in the response (but don't override it)
		$response->setHeader('content type', $view->getContentType());
		$view->setFilters($this->filters);

		App::$monitor->snapshot('View initialized');
		return $view;
	}

	private function getViewType($acceptHeader) {
		$supported = $this->cache->get('views');
		if($supported == null) {
			foreach($this->getAll() as $viewclass) {
				$supported[] = $viewclass->newInstanceWithoutConstructor()->getContentType();
			}
			$this->cache->set('views', $supported);
		}
		$accept = Mimeparse::bestMatch($supported, $acceptHeader);
		if($accept == null) {
			$accept = 'text/plain';
		}
		list($type, $subtype) = explode('/', $accept);
		return ucfirst($subtype);
	}

	public function getAll() {
		$views = [];
		$viewList = $this->store->get('views', ['Deadline\\View\\File', 'Deadline\\View\\Html', 'Deadline\\View\\Json', 'Deadline\\View\\Plain']);
		foreach($viewList as $view) {
			$views[] = new ReflectionClass($view);
		}
		/*
		$this->logger->debug('Scanning for views');
		$oldClasses = get_declared_classes();
		// load all available views
		// find the builtin and external views
		// TODO this is a huge hack--figure out a better way!
		$files = new Iterator(new DirectoryIterator('deadline://include/Deadline/View', FS::SKIP_DOTS | FS::CURRENT_AS_FILEINFO | FS::KEY_AS_PATHNAME));
		foreach($files as $file) {
			if(pathinfo($file, PATHINFO_EXTENSION) === 'php') {
				if(!in_array(\Deadline\DeadlineStreamWrapper::resolve($file), get_included_files())) {
					$class = $this->getNamespaceFromFile($file) . '\\' . basename($file, '.php');
					class_exists($class, true);
				}
			}
		}
		$viewPath = ProjectStreamWrapper::getProjectName() . '://View';
		if(is_dir($viewPath)) {
			$files = new Iterator(new DirectoryIterator($viewPath, FS::SKIP_DOTS | FS::CURRENT_AS_FILEINFO | FS::KEY_AS_PATHNAME));
			foreach($files as $file) {
				if(pathinfo($file, PATHINFO_EXTENSION) === 'php') {
					if(!in_array(\Deadline\DeadlineStreamWrapper::resolve($file), get_included_files())) {
						$class = $this->getNamespaceFromFile($file) . '\\' . basename($file, '.php');
						class_exists($class, true);
					}
				}
			}
		}

		$classes = array_filter(array_diff(get_declared_classes(), $oldClasses), function ($class) {
			return in_array('Deadline\\View', class_parents($class));
		});
		foreach($classes as $class) {
			$this->logger->debug('Found view ' . $class);
			$views[] = new ReflectionClass($class);
		}
		*/
		return $views;
	}
}
