<?php

/**
 * HTTP client for the backend API. Every request carries Basic auth plus
 * an HMAC-SHA256 signature of "METHOD|PATH|TIMESTAMP" so the backend only
 * accepts requests from this frontend.
 */
class Default_Model_Api
{
    private static function base()
    {
        return rtrim((string) getenv('API_BASE_URL'), '/');
    }

    private static function authHeaders($method, $pathWithQuery)
    {
        $ts = time();
        $secret = (string) getenv('API_SECRET');
        $basic = base64_encode(getenv('API_KEY') . ':' . $secret);
        $sig = hash_hmac('sha256', strtoupper($method) . '|' . $pathWithQuery . '|' . $ts, $secret);

        return [
            'Authorization' => 'Basic ' . $basic,
            'X-Auth' => 'Basic ' . $basic, // fallback if the server strips Authorization
            'X-Timestamp' => (string) $ts,
            'X-Signature' => $sig,
        ];
    }

    /** @return array{status:int, body:array} */
    public static function get($path, array $query = [])
    {
        $pathWithQuery = $path . ($query ? '?' . http_build_query($query) : '');
        $client = new Zend_Http_Client(self::base() . $pathWithQuery, ['timeout' => 30]);
        $client->setHeaders(self::authHeaders('GET', $pathWithQuery));
        return self::send($client, 'GET');
    }

    /** @return array{status:int, body:array} */
    public static function post($path, array $data = [])
    {
        $client = new Zend_Http_Client(self::base() . $path, ['timeout' => 60]);
        $client->setHeaders(self::authHeaders('POST', $path));
        $client->setRawData(json_encode($data), 'application/json');
        return self::send($client, 'POST');
    }

    /**
     * Forwards a browser upload ($_FILES entry) to the backend as multipart.
     * @return array{status:int, body:array}
     */
    public static function upload($path, array $file, array $params = [])
    {
        $client = new Zend_Http_Client(self::base() . $path, ['timeout' => 300]);
        $client->setHeaders(self::authHeaders('POST', $path));
        // Pass the original client filename, not the PHP tmp name
        $client->setFileUpload($file['name'], 'file', file_get_contents($file['tmp_name']), $file['type'] ?: null);
        foreach ($params as $key => $value) {
            $client->setParameterPost($key, $value);
        }
        return self::send($client, 'POST');
    }

    private static function send(Zend_Http_Client $client, $method)
    {
        $response = $client->request($method);
        $body = json_decode((string) $response->getBody(), true);
        return ['status' => (int) $response->getStatus(), 'body' => is_array($body) ? $body : []];
    }

    /**
     * Relays a backend binary response (download/stream) to the browser in
     * 8KB chunks, forwarding the status line and content headers — including
     * 206 Partial Content and Content-Range, which is what makes seeking
     * work end to end. Caller must have cleared output buffers first.
     */
    public static function proxy($pathWithQuery, array $extraHeaders = [])
    {
        $headers = [];
        foreach (self::authHeaders('GET', $pathWithQuery) as $name => $value) {
            $headers[] = "$name: $value";
        }
        foreach ($extraHeaders as $header) {
            $headers[] = $header;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'follow_location' => 0,
                'timeout' => 300,
            ],
        ]);

        $fh = @fopen(self::base() . $pathWithQuery, 'rb', false, $context);
        if (!$fh) {
            header('HTTP/1.1 502 Bad Gateway');
            echo 'Backend tidak terjangkau';
            return;
        }

        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/#', $header)) {
                header($header);
            } elseif (preg_match('/^(Content-Type|Content-Length|Content-Range|Accept-Ranges|Content-Disposition):/i', $header)) {
                header($header);
            }
        }

        while (!feof($fh)) {
            echo fread($fh, 8192);
            flush();
        }
        fclose($fh);
    }
}
