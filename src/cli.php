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
	'template-lint:',
	'ext:'
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
	$lint = new PHPTAL_Lint();
	$lint->skipUnknownModifiers(true);
	$lint->scan('deadline://templates/' . $options['template-lint']);
	$lint->displayErrors();
	echo PHP_EOL;
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

class PHPTAL_Lint
{
    private $ignore_pattern = '/^\.|\.(?i:php|inc|jpe?g|gif|png|mo|po|txt|orig|rej|xsl|xsd|sh|in|ini|conf|css|js|py|pdf|swf|csv|ico|jar|htc)$|^Makefile|^[A-Z]+$/';
    private $accept_pattern = '/\.(?:xml|[px]?html|zpt|phptal|tal|tpl)$/i';
    private $skipUnknownModifiers = false;

    public $errors = array();
    public $warnings = array();
    public $ignored = array();
    public $skipped = 0, $skipped_filenames = array();
    public $checked = 0;

    function skipUnknownModifiers($bool)
    {
        $this->skipUnknownModifiers = $bool;
    }

    function acceptExtensions(array $ext) {
        $this->accept_pattern = '/\.(?:' . implode('|', $ext) . ')$/i';
    }

    protected function reportProgress($symbol)
    {
        echo $symbol;
    }

    function scan($path)
    {
        foreach (new DirectoryIterator($path) as $entry) {
            $filename = $entry->getFilename();

            if ($filename === '.' || $filename === '..') {
                continue;
            }

            if (preg_match($this->ignore_pattern, $filename)) {
                $this->skipped++;
                continue;
            }

            if ($entry->isDir()) {
                $this->reportProgress('.');
                $this->scan($path . DIRECTORY_SEPARATOR . $filename);
                continue;
            }

            if (! preg_match($this->accept_pattern, $filename)) {
                $this->skipped++;
                $this->skipped_filenames[$filename] = true;
                continue;
            }

            $result = $this->testFile($path . DIRECTORY_SEPARATOR . $filename);

            if (self::TEST_OK == $result) {
                $this->reportProgress('.');
            } else if (self::TEST_ERROR == $result) {
                $this->reportProgress('E');
            } else if (self::TEST_SKIPPED == $result) {
                $this->reportProgress('S');
            }
        }
    }

    const TEST_OK = 1;
    const TEST_ERROR = 2;
    const TEST_SKIPPED = 3;

    /**
     * @return int - one of TEST_* constants
     */
    function testFile($fullpath)
    {
        try {
            $this->checked ++;
            $phptal = new PHPTAL($fullpath);
            $phptal->setForceReparse(true);
            $phptal->prepare();
            return self::TEST_OK;
        }
        catch(PHPTAL_UnknownModifierException $e) {
            if ($this->skipUnknownModifiers && is_callable(array($e, 'getModifierName'))) {
                $this->warnings[] = array(dirname($fullpath), basename($fullpath), "Unknown expression modifier: ".$e->getModifierName()." (use -i to include your custom modifier functions)", $e->getLine());
                return self::TEST_SKIPPED;
            }
            $log_exception = $e;
        }
        catch(Exception $e) {
            $log_exception = $e;
        }

        // Takes exception from either of the two catch blocks above
        $this->errors[] = array(dirname($fullpath) , basename($fullpath) , $log_exception->getMessage() , $log_exception->getLine());
        return self::TEST_ERROR;
    }

    function displayErrors()
    {
        $last_dir = '.';
        foreach ($this->errors as $errinfo) {
            if ($errinfo[0] !== $last_dir) {
                echo "In ", $errinfo[0], ":\n";
                $last_dir = $errinfo[0];
            }
            echo $errinfo[1], ": ", $errinfo[2], ' (line ', $errinfo[3], ')';
            echo "\n";
        }
    }
}
