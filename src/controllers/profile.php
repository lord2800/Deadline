<?php

use Deadline\User;

class Profile {
	public function signin($request, $args, $response) {
		if($request->verb == 'GET') {
			if(User::current() != null) {
				$response->redirect('/home');
			} else {
				$view = $response->getView();
				$view->template = 'profile/signon.tal';
				$view->title = 'Sign in';
				$view->hideSignonBox = true;
				return $view;
			}
		} else if($request->verb == 'POST') {
			$post = $request->input['post'];
			if(User::identify($post['login'], $post['pass'])) {
				$response->redirect($post['returnUrl']);
			} else {
				$view = $response->getView();
				$view->template = 'profile/signon.tal';
				$view->title = 'Sign in';
				$view->failed = true;
				$view->onsigninpage = true;
				return $view;
			}
		}
	}
	public function signout($request, $args, $response) {
		if($request->verb == 'GET') {
			$view = $response->getView();
			$view->template = 'confirm.tal';
			$view->title = 'Sign out';
			$view->action = 'sign out';
			return $view;
		} else if($request->verb == 'POST') {
			session_destroy();
			$response->redirect('/home');
		}
	}
	public function signup($request, $args, $response) {
		if($request->verb == 'GET') {
			$view = $response->getView();
			$view->template = 'profile/signup.tal';
			$view->title = 'Sign up';
			return $view;
		} else if($request->verb == 'POST') {
			$post = $request->input['post'];
			$user = User::register($post['username'], $post['display'], $post['email'], $post['password']);
			$response->redirect('/user/' . $user);
		}
	}
	public function edit($request, $args, $response) {
		if($request->verb == 'GET') {
			$user = User::find($args['profile'], true);
			$view = $response->getView();
			$view->template = 'profile/edit.tal';
			$view->title = $user->displayName . ' > User profile';
			$view->user = $user;
			return $view;
		} else if($request->verb == 'POST') {
		}
	}
	public function role($request, $args, $response) {
		$view = $response->getView();
		$view->template = 'profile/roles.tal';
		$view->title = 'Users in ' . $args['role'];
		$view->users = User::getRole($args['role'], false)->sharedUser;
		$view->role = $args['role'];
		return $view;
	}
}
