<?php
class RateLimiter extends Zend_Controller_Plugin_Abstract
{
    protected $limit = 100; // max requests
    protected $windowSecs = 60;  // time window in seconds
    protected $storageDir = '/tmp/rate_limits';

    public function __construct()
    {
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {
        $identifier = $this->getClientIp(); // Or API key
        $window = floor(time() / $this->windowSecs);
        $file = "{$this->storageDir}/{$identifier}_{$window}.txt";

        $count = 0;

        // Load existing count
        if (file_exists($file)) {
            $count = (int) file_get_contents($file);
        }

        $count++;

        // Save updated count
        file_put_contents($file, $count, LOCK_EX);

        // Check limit
        if ($count > $this->limit) {
            $this->denyRequest();
        }

        // Optional: clean old files
        $this->cleanupOldFiles();
    }

    protected function denyRequest()
    {
        $response = [
            'responseCode' => '4292100',
            'responseMessage' => 'Too Many Requests'
        ];

        $this->getResponse()
            ->setHttpResponseCode(429)
            ->setHeader('Content-Type', 'application/json')
            ->setBody(json_encode($response))
            ->sendResponse();
        exit;
    }

    protected function cleanupOldFiles()
    {
        foreach (glob("{$this->storageDir}/*.txt") as $file) {
            // Delete files older than 2 windows
            if (filemtime($file) < time() - ($this->windowSecs * 2)) {
                @unlink($file);
            }
        }
    }

    protected function getClientIp(): string
    {
        // If behind a trusted proxy (set up in your infra)
        $trustedProxies = ['127.0.0.1', '::1', '192.168.0.1']; // adjust this list

        if (in_array($_SERVER['REMOTE_ADDR'], $trustedProxies, true)) {
            // Prefer X-Forwarded-For if provided by proxy
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                // Can be a comma-separated list → take the first real client IP
                $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                return trim($ips[0]);
            }
            if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
                return $_SERVER['HTTP_X_REAL_IP'];
            }
        }

        // Fallback: direct connection
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

}
