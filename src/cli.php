<?php

if(php_sapi_name() != 'cli') {
	die('Please execute this file from a console instead!');
}

require_once('autoload.php');

function run_shutdown() {}

$options = getopt('h', array(
	'help',
	'add-user',
	'user:',
	'pass:',
	'email:',
	'skelgen:',
	'name:',
	'install'
));

if(isset($options['skelgen'])) {
	// skeleton generator
	switch($options['skelgen']) {
		case 'template': break; // copy the default template into `name` directory
		case 'controller': break; // make a default controller (empty) named `name` in controllers/
	}
} else if(isset($options['add-user'])) {
	if(!isset($options['user']) || !isset($options['pass']) || !isset($options['email'])) {
		fwrite(STDERR, 'You must specify a username, password, and email when adding a user' . PHP_EOL);
		exit(1);
	}
	Deadline\User::register($options['user'], $options['user'], $options['email'], $options['pass']);
} else if(isset($options['install'])) {
	// run the installer script
} else if(isset($options['help']) || isset($options['h'])) {
	show_help($argv[0]);
} else {
	fwrite(STDERR, 'Unrecognized option, see below for options' . PHP_EOL);
	show_help($argv[0]);
}

function show_help($name) {
	$name = basename($name);
	$help = <<<END
Deadline ($name) version 1.0
Options:
    -h | --help         Help info (what you're seeing right now!)
    --install           Generate the database, write the necessary config
                        files, and do all of the necessary stuff to install

    --skelgen=<type>    Generate <type> skeleton. Specify the name with --name
                          Recognized types:
                            template - Generate a skeleton template based on
                                       the default template
                            controller - Generate a skeleton controller with
                                         no routes or methods.

    --add-user          Add a user to an existing install. If you specify this
                        option, you must also specify --user, --pass, and
                        --email

    --user=<value>      Specify the username for --add-user
    --pass=<value>      Specify the password for --add-user
    --email=<value>     Specify the email for --add-user

    --name=<value>      Specify the name for --skelgen

END;
	fwrite(STDOUT, $help);
}
