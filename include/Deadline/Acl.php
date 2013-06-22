<?php
namespace Deadline;

abstract class Acl {
	public function getUserId() {
		return 0;
	}
	public function register(array $params) {
		return false;
	}
	public function activate($code) {
		return false;
	}
	public function resetPassword($code, $newPassword) {
		return false;
	}
	public function hasPermission($call) {
		return false;
	}
}
