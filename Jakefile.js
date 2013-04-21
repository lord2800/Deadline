/*
*/

task('default', ['help']);
task('clean', ['clean:all']);
task('lint', ['lint:all']);
task('scrutinize', [], function () {
	jake.exec('scrutinizer run ' + __dirname);
});

task('help', [], function () {
	var help = [
		'=== Deadline Build System Commands ===',
		'Commands:',
		'  build:',
		'    phar        Build a complete PHAR with the current code',
		'    dir         Build a complete directory with the current code',
		'  clean:',
		'    all         Clean all intermediate code',
		'    cache       Clean the cache/ directory',
		'  lint:',
		'    all         Lint all files',
		'    php         Lint PHP files with `php -l`',
		'    js          Lint JS files with jshint',
		'    css         Lint CSS files with CSSLint',
		'    tal         Lint PHPTAL templates using PHPTAL\'s linter',
		'  minify:',
		'    all         Minify all files',
		'    js          Minify JS files with uglify2',
		'    css         Minify CSS files with cleancss'
	];
	help.forEach(jake.logger.log);
});

namespace('build', function () {
	desc('Builds a release-worthy PHAR');
	task('phar', ['build/deadline.phar']);

	desc('Builds a release-worthy directory of content');
	task('dir', ['release']);

	directory('build');

	desc('Builds a release-worthy PHAR');
	task('build/deadline.phar', ['lint:all', 'clean:all', 'build:build'], function () {
		jake.exec(['php -dphar.readonly=0 build-release.php phar'], function () {}, execOpts);
	});

	desc('Builds a release-worthy directory of content');
	task('dir', ['lint:all', 'clean:all', 'build:build'], function () {
		jake.mkdirP('build');
		jake.exec(['php build-release.php dir'], function () {}, execOpts);
	});
});

namespace('clean', function () {
	desc('Clean all caches and old build output');
	task('all', ['cache', 'build']);

	desc('Clean the cache directory for bundling');
	task('cache', [], function () {
		jake.rmRf('cache');
		jake.mkdirP('cache');
	});

	desc('Clean the build results');
	task('build', [], function () {
		jake.rmRf('build');
	});
});

namespace('lint', function () {
	desc('Lint all non-minified files');
	task('all', ['php', 'js', 'css', 'tal']);

	desc('Lint all PHP files');
	task('php', [], function () {
		withAllFiles(__dirname, function (f) {
			jake.exec(['php -l ' + f], function () {}, execOpts);
		}, function(f) {
			return path.extname(f) === '.php' && !contains(f, 'vendor') && fileMatcher(f);
		});
	});

	desc('Lint all non-minified JS files');
	task('js', [], function () {
		withAllFiles(__dirname, function (f) {
			jake.exec(['node ' + __dirname + '/node_modules/jshint/bin/jshint ' + f], function () {}, execOpts);
		}, function (f) {
			return path.extname(f) === '.js' && !contains(f, 'vendor') && !contains(f, '.min.js') && fileMatcher(f);
		});
	});

	desc('Lint all non-minified CSS files');
	task('css', [], function () {
		withAllFiles(__dirname, function (f) {
			jake.exec(['node ' + __dirname + '/node_modules/csslint/cli.js ' + f], function () {}, execOpts);
		}, function (f) {
			return path.extname(f) === '.css' && !contains(f, 'vendor') && !contains(f, '.min.css') && fileMatcher(f);
		});
	});

	desc('Lint all TAL templates');
	task('tal', [], function () {
		withAllFiles(__dirname, function (f) {
			jake.exec(['php vendor/phptal/phptal/tools/phptal_lint.php ' + f], function () {}, execOpts);
		}, function(f) {
			return path.extname(f) === '.tal' && !contains(f, 'vendor') && fileMatcher(f);
		});
	});
});

namespace('minify', function () {
	task('all', ['js', 'css']);

	desc('Minify all JS files for <template>');
	task('js', [], function (template) {
		var output = path.join(__dirname, 'public', 'templates', 'site.min.js');
		var files = getAllFiles(path.join(__dirname, 'public', 'templates', template),
					function (f) { return path.extname(f) === '.js' && !contains(f, '.min.js') && defaultMatcher(f); });

		if(files.length > 0) {
			fs.writeFile(output, jsminify(files).code);
			console.log('Wrote minified JS to ' + output);
		} else {
			console.log('No JS files found to minify');
		}
	});

	desc('Minify all CSS files for <template>');
	task('css', [], function (template) {
		var output = path.join(__dirname, 'public', 'templates', 'site.min.css');
		var files = getAllFiles(path.join(__dirname, 'public', 'templates', template),
					function (f) { return path.extname(f) === '.css' && !contains(f, '.min.css') && defaultMatcher(f); });
		var content = '';
		var total = files.length;
		for (var i = files.length - 1; i >= 0; i--) {
			fs.readFile(files[i], function (err, data) {
				total--;
				content += data;
				if(total === 0) {
					fs.writeFile(output, cssminify(content));
					console.log('Wrote minified CSS to ' + output);
				}
			});
		}
	});
});

task('watch', [], function () {
	watch.watchTree(__dirname, {ignoreDotFiles: true}, function (f, curr, prev) {
		if (typeof f == "object" && prev === null && curr === null) {
			// Finished walking the tree
		} else if (prev === null) {
			// f is a new file
		} else if (curr.nlink === 0) {
			// f was removed
		} else {
			// f was changed
		}
});

/* Declarations--these are hoisted to the top, but it's ugly like that */

var path = require('path'),
	fs = require('fs'),
	jsminify = require('uglify-js').minify,
	cssminify = require('clean-css').process,
	watch = require('watch');

var execOpts = {printStdout: !jake.program.opts.quiet, printStderr: !jake.program.opts.quiet, breakOnError: false};

function defaultMatcher(f) {
	var fname = path.basename(f);
	var dir = path.dirname(f);
	return !(dir.match(/test(s|ing)?/i) ||
			 dir.match(/node_modules/i) ||
			 dir.match(/docs?/i) ||
			 dir.match(/bin/i) ||
			 dir.match(/examples?/i) ||
			 dir.match(/\.git/i) ||
			 dir.match(/demo/i) ||
			 dir.match(/build/i) ||
			 fname.match(/deadline.sublime-project/i) ||
			 fname.match(/deadline.sublime-workspace/i) ||
			 fname.match(/package.json/i) ||
			 fname.match(/composer.json/i) ||
			 fname.match(/composer.lock/i) ||
			 fname.match(/\.gitignore/i) ||
			 fname.match(/phpunit.xml/i) ||
			 fname.match(/\.travis(-cub)?.yml/i) ||
			 fname.match(/readme(.*)?/i) ||
			 fname.match(/test-dist.ini/i) ||
			 fname.match(/changelog(.*)?/i) ||
			 fname.match(/Jakefile.js/i) ||
			 fname.match(/Makefile/i) ||
			 fname.match(/build.xml/i) ||
			 fname.match(/build-release.php/i) ||
			 fname.match(/stub.php/i)
	);
}
function fileMatcher(f) { return defaultMatcher(f) && fs.statSync(f).isFile(); }
function dirMatcher(f) { return defaultMatcher(f) && fs.statSync(f).isDirectory(); }
function basedir(f) { return path.basename(path.dirname(f)); }
function contains(f, e) { return f.indexOf(e) !== -1; }

function getAllFiles(dir, match) {
	match = match || fileMatcher;
	return jake.readdirR(dir).filter(match);
}
function withAllFiles(dir, cb, match) {
	match = match || fileMatcher;
	getAllFiles(dir, match).forEach(cb);
}
function getAllDirs(dir, match) {
	match = match || dirMatcher;
	return jake.readdirR(dir).filter(match);
}
function withAllDirs(dir, cb, match) {
	match = match || dirMatcher;
	getAllDirs(dir, match).forEach(cb);
}
