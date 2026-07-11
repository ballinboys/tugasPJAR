<?php

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
    protected function _initSession()
    {
        // Harden session cookie so it cannot be sent cross-site
        // if (PHP_VERSION_ID >= 70300) {
        //     $cookieParams = session_get_cookie_params();
        //     // Adjust domain if you have a specific domain (e.g., '.example.com')
        //     session_set_cookie_params([
        //         'lifetime' => 0,
        //         'path' => $cookieParams['path'] ?? '/',
        //         'domain' => $cookieParams['domain'] ?? '',
        //         'secure' => true,       // ensure site served over HTTPS
        //         'httponly' => true,     // not accessible via JS
        //         'samesite' => 'Strict', // prevents most CSRF via cookies
        //     ]);
        // }

        Zend_Session::start();
        $_SESSION["activityId"] = bin2hex(random_bytes(16));

        if (function_exists('header_remove')) {
            header_remove("X-Powered-By");
        } else {
            header("X-Powered-By:");
        }
    }

    protected function _initLogger()
    {
        date_default_timezone_set('Asia/Jakarta');
        $date = date('M-d-Y');

        $activityLogs = APPLICATION_PATH . "/logs";
        $logfileFormat = "$date.log";

        if (!is_dir($activityLogs)) {
            mkdir($activityLogs, 0755, true);
        }

        $logPath = "$activityLogs/$logfileFormat";
        if (!file_exists($logPath)) {
            $handleLog = fopen($logPath, 'w');
            if (!$handleLog) {
                throw new Exception("Cannot open file: $logPath");
            }
            fclose($handleLog);
            // chmod($logPath, 0644);
            chmod($logPath, 0777);
        }

        $log_writer_stream = new Zend_Log_Writer_Stream($logPath);

        $log_format = '%timestamp% %priorityName%: %message%' . PHP_EOL;
        $zend_log_formatter = new Zend_Log_Formatter_Simple($log_format);

        $log_writer_stream->setFormatter($zend_log_formatter);

        $logger = new Zend_Log($log_writer_stream);
        $logger->setTimestampFormat("H:i:s");

        Zend_Registry::set('logger', $logger);

        $requestParam = json_encode($_REQUEST);
        $activityId = $_SESSION["activityId"];
        $ip = $_SERVER["REMOTE_ADDR"] ?? 'null';
        $url = $_SERVER["REQUEST_URI"] ?? 'null';
        $brandId = $_SESSION["brandId"] ?? 'null';
        $internalAccountId = $_SESSION["internalAccountId"] ?? 'null';

        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $rawBody = '';

        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            // Don’t try to log giant multipart bodies
            if (stripos($contentType, 'multipart/form-data') === false) {
                $rawBody = file_get_contents('php://input'); // raw string
                if (stripos($contentType, 'application/json') !== false) {
                    $decoded = json_decode($rawBody, true);
                    if (is_array($decoded)) {
                        // Optional: mask sensitive keys
                        $sensitive = ['password', 'token', 'authorization'];
                        foreach ($sensitive as $k) {
                            if (isset($decoded[$k]))
                                $decoded[$k] = '***';
                        }

                        // Remove csrf field
                        $sensitive = ['csrf_token'];
                        foreach ($sensitive as $k) {

                            if (isset($decoded[$k]))
                                unset($decoded[$k]);
                        }

                        $rawBody = json_encode($decoded);
                    }
                }
                // Truncate to avoid log bloat
                if (strlen($rawBody) > 4000) {
                    $rawBody = substr($rawBody, 0, 4000) . '...<truncated>';
                }
            } else {
                $rawBody = '[multipart/form-data omitted]';
            }
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? '';

        $requestParam = json_encode($_REQUEST);
        switch ($method) {
            case "POST":
            case "PUT":
            case "PATCH":
                $message = "[REQUEST] | METHOD=$method | URL: '$url' | PARAM=$requestParam | BODY=$rawBody | IP='$ip' | ACTIVITY_ID='$activityId'";
                break;
            case "GET":
                $message = "[REQUEST] | METHOD=$method | URL: '$url' | PARAM=$requestParam | IP='$ip' | ACTIVITY_ID='$activityId'";
                break;
            default:
                $message = "[REQUEST] | METHOD=$method | URL: '$url' | PARAM=$requestParam | BODY=$rawBody | IP='$ip' | ACTIVITY_ID='$activityId'";
                break;
        }
        $logger->info((string) $message);
    }

    protected function _initFolders()
    {
        $tmpFolder = APPLICATION_PATH . "/tmp";
        if (!is_dir($tmpFolder)) {
            mkdir($tmpFolder, 0755, true);
        }
    }

    protected function _initEarlyExit()
    {
        // Filter .well-known requests
        $url = $_SERVER["REQUEST_URI"] ?? 'null';
        if (str_contains($url, ".well-known")) {
            $response = Zend_Controller_Front::getInstance()->getResponse();
            $response->setHttpResponseCode(404)->setBody('Not Found');
            $response->sendResponse();
            exit;
        }
    }

    protected function _initAutoload()
    {
        return new Zend_Application_Module_Autoloader(
            array(
                'namespace' => '',
                'basePath' => APPLICATION_PATH,
            )
        );
    }

    protected function _initLayout()
    {
        Zend_Layout::startMvc();

        $front = Zend_Controller_Front::getInstance();
        $moduleDirs = glob(APPLICATION_PATH . '/modules/*', GLOB_ONLYDIR);

        foreach ($moduleDirs as $moduleDir) {
            $moduleName = basename($moduleDir);
            $front->addControllerDirectory("$moduleDir/controllers", $moduleName);
        }

        $front->registerPlugin(
            new Zend_Controller_Plugin_ErrorHandler(
                array(
                    'module' => 'default',
                    'controller' => 'error',
                    'action' => 'error',
                )
            )
        );

        // $view = Zend_Layout::getMvcInstance()->getView();
        // $view->addScriptPath(APPLICATION_PATH . '/modules/partials/');
    }

    protected function _initSecurityNonce()
    {
        $nonce = base64_encode(random_bytes(16));
        $_SESSION["cspNonce"] = $nonce;
    }

    protected function _initPlugins()
    {
        $logger = Zend_Registry::get("logger");
        $internalAccountId = $_SESSION["internal_account_id"] ?? 'null';
        $ip = $_SERVER["REMOTE_ADDR"] ?? 'null';
        $url = $_SERVER["REQUEST_URI"] ?? 'null';
        $activityId = $_SESSION["activityId"] ?? 'null';

        require_once 'SanitizeParam.php';
        require_once 'EnvSetup.php';
        require_once 'AccessControl.php';
        require_once 'LayoutSetup.php';
        require_once 'RateLimiter.php';

        $front = Zend_Controller_Front::getInstance();
        $front->registerPlugin(new RateLimiter());
        $front->registerPlugin(new SanitizeParam());
        $front->registerPlugin(new EnvSetup());
        $front->registerPlugin(new AccessControl());
        $front->registerPlugin(new LayoutSetup());
    }

    protected function _initViewHelpers()
    {
        $view = Zend_Layout::getMvcInstance()->getView();
        $view->addHelperPath('Zend/View/Helper', 'Zend_View_Helper');
    }
}
