<?php

require_once('deadline://vendor/analog/analog/lib/Analog.php');


Analog::handler(Analog\Handler\File::init(Deadline\Storage::current()->get('logfile', 'deadline://error.log')));
