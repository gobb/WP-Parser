<?php

require dirname(__FILE__) . '/PHP-Parser/lib/PHPParser/Autoloader.php';
PHPParser_Autoloader::register();
require dirname(__FILE__) . '/library/WP/NodeVisitor.php';

$wp_dir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'trunk-git' . DIRECTORY_SEPARATOR . 'wp-includes';

function get_wp_files($directory) {
	$iterableFiles =  new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($directory)
    );
    $files = array();
	try {
		foreach( $iterableFiles as $file ) {
			if ($file->getExtension() !== 'php')
				continue;

			if ($file->getFilename() === 'class-wp-json-server.php')
				continue;

			$files[] = $file->getPathname();
		}
	}
	catch (UnexpectedValueException $e) {
		printf("Directory [%s] contained a directory we can not recurse into", $directory);
	}

	return $files;
}

function parse_files($files) {
	header('Content-Type: text/plain');

	$parser = new PHPParser_Parser(new PHPParser_Lexer);
	$repository = new QP_Repo_Functions;
	$filters = new QP_Repo_Filters;

	foreach ($files as $file) {
		$code = file_get_contents($file);
		$file = str_replace($wp_dir . DIRECTORY_SEPARATOR, '', $file);
		$file = str_replace(DIRECTORY_SEPARATOR, '/', $file);
		try {
			$stmts = $parser->parse($code);
		}
		catch (PHPParser_Error $e) {
			echo $file . "\n";
			echo $e->getMessage();
			die();
		}

		$traverser = new PHPParser_NodeTraverser;
		$visitor = new QP_NodeVisitor($file, $repository, $filters);
		$traverser->addVisitor($visitor);
		$stmts = $traverser->traverse($stmts);
	}

	$functions = array_filter($repository->functions, function ($details) {
		// Built-in function
		return !empty($details->file) || !empty($details->uses);
	});

	return array('functions' => $functions, 'filters' => $filters->filters);
}

$files = get_wp_files($wp_dir);
list($functions, $filters) = parse_files($files);

file_put_contents('output.json', json_encode($functions));
file_put_contents('filters.json', json_encode($filters));