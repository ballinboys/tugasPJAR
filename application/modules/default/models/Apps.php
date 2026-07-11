<?php

class Default_Model_Apps
{
    public static function csrfGetToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function csrfField(): string
    {
        $csrf = htmlspecialchars(self::csrfGetToken(), ENT_QUOTES, 'UTF-8');
        return "<input type='hidden' name='csrf_token' value='$csrf'>";
    }

    public static function csrfValidateToken($token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if (!is_string($sessionToken) || $sessionToken === '' || !is_string($token)) {
            return false;
        }
        return hash_equals($sessionToken, $token);
    }

    public static function checkCsrf()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $token = $_POST['csrf_token'] ?? '';
        if (!self::csrfValidateToken($token)) {
            http_response_code(400);
            exit('Invalid CSRF token');
        }
    }

    public static function modalError($title, $message = null)
    {
        $_SESSION["modalError"] = $title;
        $_SESSION["modalErrorMessage"] = $message;
    }

    public static function modalAlert($title, $message = null)
    {
        $_SESSION["modalAlert"] = $title;
        $_SESSION["modalAlertMessage"] = $message;
    }

    public static function logger($message, $level = 'info', $label = null, $additionalInfo = null)
    {
        if (!Zend_Registry::isRegistered('logger')) {
            return;
        }

        $logger = Zend_Registry::get('logger');

        // Determine configured threshold (e.g. error|warn|info|debug). Default to 'info'.
        $threshold = strtolower(getenv('LOG_LEVEL') ?: (defined('LOG_LEVEL') ? LOG_LEVEL : 'info'));

        // Numeric severity (lower = more severe)
        $map = [
            'error' => 0,
            'warn' => 1,
            'info' => 2,
            'debug' => 3,
        ];

        $level = strtolower($level);
        if (!isset($map[$level])) {
            $level = 'info';
        }
        if (!isset($map[$threshold])) {
            $threshold = 'info';
        }

        // Skip if the message is more verbose than threshold
        if ($map[$level] > $map[$threshold]) {
            return;
        }

        if ($label) {
            $message = "[$label] $message";
        }

        if ($additionalInfo) {
            $message = "$message " . var_export($additionalInfo, true);
        }

        switch ($level) {
            case 'error':
                $logger->err("  $message");
                return;
            case 'debug':
                $logger->debug($message);
                return;
            case 'warn':
                $logger->warn(" $message");
                return;
            case 'info':
            default:
                $logger->info(" $message");
                return;
        }
    }

}
