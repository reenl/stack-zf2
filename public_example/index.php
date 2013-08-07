<?php
error_reporting(-1);
ini_set('display_errors', 1);
/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */
chdir(dirname(__DIR__));

// Decline static file requests back to the PHP built-in webserver
if (php_sapi_name() === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

// Setup autoloading
require __DIR__.'/../vendor/autoload.php';

// Initialize Zend MVC
$zend = \Zend\Mvc\Application::init(require 'config/application.config.php');

// Define your stack
$kernel = new \Stack\ZendHttpKernel($zend);

// Run the application
$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$response = $kernel->handle($request);

// Send the response.
$response->send();
