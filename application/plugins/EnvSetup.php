<?php

class EnvSetup extends Zend_Controller_Plugin_Abstract
{
    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {
        try {
            $controller = $request->getControllerName();
            if ($controller != "error") {
                $path = APPLICATION_PATH . "/../.env";
                if (!file_exists($path)) {
                    throw new Exception(".env file not found at path: $path");
                }

                $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    // Skip comments
                    if (str_starts_with(trim($line), '#')) {
                        continue;
                    }

                    [$name, $value] = explode('=', $line, 2);
                    $name = trim($name);
                    $value = trim($value);

                    // Remove surrounding quotes
                    $value = trim($value, "\"'");

                    // Set environment variables
                    putenv("$name=$value");
                    define($name, $value);
                }
            }
        } catch (Exception $e) {
            $error = new ArrayObject([
                'exception' => $e,
                'type' => Zend_Controller_Plugin_ErrorHandler::EXCEPTION_OTHER,
                'request' => clone $request
            ], ArrayObject::ARRAY_AS_PROPS);

            $request->setModuleName('default')
                ->setControllerName('error')
                ->setActionName('error')
                ->setParam('error_handler', $error)
                ->setDispatched(false);
        }
    }
}
