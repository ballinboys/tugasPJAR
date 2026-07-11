<?php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(0);

// Define path to application directory
defined('APPLICATION_PATH') || define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application'));

// Define application environment
defined('APPLICATION_ENV') || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

define('PUBLIC_PATH', dirname(__FILE__));

// Ensure library/ is on include_path
set_include_path(
    implode(
        PATH_SEPARATOR,
        array(
            realpath(APPLICATION_PATH . '/../library'),
            get_include_path(),
        )
    )
);

try {
    // Zend_Application
    require_once 'Zend/Application.php';
    // require_once 'vendor/autoload.php';

    // Create application, bootstrap, and run
    $application = new Zend_Application(
        APPLICATION_ENV,
        APPLICATION_PATH . '/configs/application.ini'
    );
    $application->bootstrap()->run();
} catch (\Throwable $th) {
    // Log the error
    error_log($th);
    echo $th->getMessage();
    echo $th->getTraceAsString();

    // Optional: render error page
    http_response_code(500);
    echo "Something went wrong. Please try again later.";
}
