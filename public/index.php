<?php
/**
 * From https://github.com/daveh/php-mvc
 * Front controller
 *
 * PHP version 7.0
 */
/**
 * Composer
 */
//  require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Use Composer autoloader to automatically load library classes.
 */
try {
	if (!file_exists('../vendor/autoload.php')) {
		throw new Exception('Dependencies managed by Composer missing. Please run "php composer.phar install".');
	}
	require_once '../vendor/autoload.php';
} catch (Exception $e) {
	echo "Error: " . $e->getMessage();
	return;
}
define('APP_DIR', dirname(__DIR__));
define('UPLOAD_DIR', APP_DIR.'/upload');
function add_prefix(&$item1, $key, $prefix)
{
	$item1 = "$prefix:$item1";
}
/**
 * Error and Exception handling
 */
error_reporting(E_ALL);
set_error_handler('Core\Error::errorHandler');
set_exception_handler('Core\Error::exceptionHandler');


/**
 * Routing
 */
$router = new \Core\Router();
// Add the routes
$router->add('', ['controller' => 'Home', 'action' => 'index']);
$router->add('convert', ['controller' => 'Convert', 'action' => 'index']);
$router->add('convert/', ['controller' => 'Convert', 'action' => 'index']);
$router->add('{controller}/{action}');
$router->dispatch($_SERVER['QUERY_STRING']);