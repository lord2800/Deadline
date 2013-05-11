<?php
namespace Deadline;

abstract class Acl {
	public function hasPermission($call) {
		return false;
	}
}
