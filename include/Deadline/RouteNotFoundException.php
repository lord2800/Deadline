<?php
namespace Deadline;

use Http\Exception\Client\NotFound as HttpNotFound;

class RouteNotFoundException extends HttpNotFound {}
