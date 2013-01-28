<?php

ob_start();
require_once('autoload.php');
Deadline\App::init()->run();
