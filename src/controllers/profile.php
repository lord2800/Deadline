<?php

use Deadline\User;

class Profile {
	public function signin($request, $args, $response) {
		if($request->verb == 'GET') {
			if($response->getCurrentUser() != null) {
				$response->redirect('/home');
			} else {
				$response->view->template = 'profile/signon.tal';
				$response->view->title = 'Sign in';
				$response->view->hideSignonBox = true;
			}
		} else if($request->verb == 'POST') {
			$post = $request->input['post'];
			if(User::identify($post['login'], $post['pass'])) {
				$response->redirect($post['returnUrl']);
			} else {
				$response->view->template = 'profile/signon.tal';
				$response->view->title = 'Sign in';
				$response->view->failed = true;
				$response->view->onsigninpage = true;
			}
		}
	}
	public function signout($request, $args, $response) {
		if($request->verb == 'GET') {
			$response->view->template = 'confirm.tal';
			$response->view->title = 'Sign out';
			$response->view->action = 'sign out';
		} else if($request->verb == 'POST') {
			session_destroy();
			$response->redirect('/home');
		}
	}
	public function signup($request, $args, $response) {
		if($request->verb == 'GET') {
			$response->view->template = 'profile/signup.tal';
			$response->view->title = 'Sign up';
		} else if($request->verb == 'POST') {
			$post = $request->input['post'];
			$user = User::register($post['username'], $post['display'], $post['email'], $post['password']);
			$response->redirect('/user/' . $user);
		}
	}
	public function edit($request, $args, $response) {
		if($request->verb == 'GET') {
			$user = User::find($args['profile'], true);
			$response->view->template = 'profile/edit.tal';
			$response->view->title = $user->displayName . ' > User profile';
			$response->view->user = $user;
		} else if($request->verb == 'POST') {
		}
	}
	public function role($request, $args, $response) {
		$response->view->template = 'profile/roles.tal';
		$response->view->title = 'Users in ' . $args['role'];
		$response->view->users = User::getRole($args['role'], false)->sharedUser;
		$response->view->role = $args['role'];
	}
}

?>