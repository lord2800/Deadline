<?php

class Blog {
	private static $blogBean = 'blogpost';
	private static $commentBean = 'blogcomment';
	private static $countPerPage = 10;

	public function index($request, $args, $response) {
		$page = array_key_exists('page', $args) ? $args['page'] : 1;
		$posts = R::find(static::$blogBean, 'mode=? ORDER BY published DESC LIMIT ? OFFSET ?',
			array('published', static::$countPerPage, $page-1)
		);
		R::preload($posts, array('user'));

		$view = $response->getView($request);
		$view->template = 'blog/index.tal';
		$view->title = 'Recent entries';
		$view->posts = $posts;
		$view->nextPage = $page+1;
		$view->prevPage = $page-1;
		$view->pageCount = ceil(R::count(static::$blogBean) / static::$countPerPage);

		$response->setCacheControl();
		$response->setExpiryTime(new \DateTime('+15 minutes'));
		return $view;
	}

	public function entry($request, $args, $response) {
		$entry = R::load(static::$blogBean, (int)$args['id']);
		if($entry->isEmpty() || $entry->mode != 'published') $entry = null;

		$view = $response->getView($request);
		$view->template = 'blog/entry.tal';
		$view->post = $entry;

		$response->setCacheControl();
		if($entry != null) {
			R::preload(array($entry), array('user'));
			$response->setModifiedTime($entry->published);
			$response->setEtag(md5($entry->author . $entry->published));
		}
		return $view;
	}

	public function edit($request, $args, $response) {
		if($request->verb == 'GET') {
			$id = $args['id'];
			$view = $response->getView($request);
			if($id != null) {
				$post = R::load(static::$blogBean, $id);
				$view->post = $post;
				$view->title = $post->isEmpty() ? 'Create a new post' : 'Edit ' . $post->title;
			} else {
				$view->title = 'Create a new post';
			}
			$view->template = 'blog/edit.tal';
			$response->setCacheControl();
			return $view;
		} else if($request->verb == 'POST') {
			$id = $request->input['post']['id'];
			$post = R::load(static::$blogBean, $id);

			$post->title = $request->input['post']['posttitle'];
			$post->user = $response->getCurrentUser();
			$post->published = R::isoDateTime();
			$post->content = $request->input['post']['postcontent'];
			$post->cachedHtml = markdown::parse($post->content);
			$post->tags = $request->input['post']['posttags'];
			$post->mode = $request->input['post']['mode'] == 'save' ? 'saved' : 'published';
			$id = R::store($post);
			$response->redirect('/blog/entry/' . $id);
		}
	}

	public function publish($request, $args, $response) {
		if($request->verb == 'GET') {
			$view = $response->getView($request);
			$view->template = 'confirm.tal';
			$view->title = 'Publish post?';
			$view->action = 'publish this post';
			$view->data = $args['id'];
			$view->returnUrl = array_key_exists('return', $args) ? $args['return'] : '';
			$response->setCacheControl('no-cache');
			return $view;
		} else if($request->verb == 'POST') {
			$id = (int)$request->input['post']['data'];
			$result = $request->input['post']['result'];
			$return = $request->input['post']['returnUrl'];
			if($return == '') {
				$return = '/blog/entry/' . $id;
			}
			if($result == 'confirm') {
				$post = R::load(static::$blogBean, $id);
				if(!$post->isEmpty()) {
					$post->mode = 'published';
					R::store($post);
				} else {
					throw new \Exception('Failed to find entry ' . $id);
				}
			}
			$response->redirect($return);
		}
	}

	public function unpublish($request, $args, $response) {
		if($request->verb == 'GET') {
			$view = $response->getView($request);
			$view->template = 'confirm.tal';
			$view->title = 'Unpublish post?';
			$view->action = 'unpublish this post';
			$view->data = $args['id'];
			$view->returnUrl = $args['return'];
			$response->setCacheControl('no-cache');
			return $view;
		} else if($request->verb == 'POST') {
			$id = (int)$request->input['post']['data'];
			$result = $request->input['post']['result'];
			$return = $request->input['post']['returnUrl'];
			if($return == '') {
				$return = '/blog/edit/' . $id;
			}
			if($result == 'confirm') {
				$post = R::load(static::$blogBean, $id);
				if(!$post->isEmpty()) {
					$post->mode = 'saved';
					R::store($post);
				} else {
					throw new \Exception('Failed to find entry ' . $id);
				}
			}
			$response->redirect($return);
		}
	}

	public function delete($request, $args, $response) {
		if($request->verb == 'GET') {
			$view = $response->getView($request);
			$view->template = 'confirm.tal';
			$view->title = 'Please confirm';
			$view->action = 'delete this post';
			$view->data = $args['id'];
			$view->returnUrl = $args['return'];
			$response->setCacheControl('no-cache');
			return $view;
		} else if($request->verb == 'POST') {
			$id = (int)$request->input['post']['data'];
			$result = $request->input['post']['result'];
			$return = $request->input['post']['returnUrl'];
			if($return == '') {
				$return = '/blog/index';
			}
			if($result == 'confirm') {
				$entry = R::load(static::$blogBean, $id);
				if(!$entry->isEmpty()) {
					R::trash($entry);
				} else {
					throw new \Exception('Failed to find entry ' . $id);
				}
				$response->redirect($return);
			} else if($result == 'deny') {
				$response->redirect('/blog/entry/' . $id);
			}
		}
	}

	public function adminMenu() {
		return array(
			'New Post' => 'link://blog/edit',
			'View All Posts' => '/blog/index'
		);
	}
	public function adminWidget($fragment) {
		$posts = R::find(static::$blogBean, '1 ORDER BY published DESC LIMIT 3');
		$entries = array();
		foreach($posts as $post) {
			$entry = new stdClass();
			$entry->id = $post->id;
			$entry->published = $post->mode == 'published';
			$entry->title = $post->title;
			$entries[] = $entry;
		}

		$fragment->view->template = 'blog/widget.tal';
		$fragment->view->posts = $entries;
	}
}

?>