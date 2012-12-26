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
	'install',
	'template-lint:'
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
	$dsn = prompt('Enter your database connection string');
	$dbuser = prompt('Enter your database username');
	$dbpass = prompt('Enter your database password');
	$user = prompt('Enter your admin username');
	$display = prompt('Enter the display name for the admin');
	$email = prompt('Enter the email for the admin');
	$pass = prompt('Enter the password for the admin');

	$settings = array(
		'dsn' => $dsn,
		'dbuser' => $dbuser,
		'dbpass' => $dbpass,
		'user' => $user,
		'displayName' => $display,
		'email' => $email,
		'pass' => $pass,
	);
	var_dump($settings); die();
	Install::cliInstall($settings);
	fwrite(STDOUT, 'Done! You are now installed and ready to go.');
} else if(isset($options['template-lint'])) {
	// TODO implement template lint
}
} else if(isset($options['help']) || isset($options['h'])) {
	show_help($argv[0]);
} else {
	fwrite(STDERR, 'Unrecognized option, see below for options' . PHP_EOL);
	show_help($argv[0]);
}

function prompt($prompt) {
	if(function_exists('readline')) {
		return readline($prompt . ": ");
	} else {
		fwrite(STDOUT, $prompt . ": ");
		return trim(fgets(STDIN));
	}
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
