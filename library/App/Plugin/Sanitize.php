<?php //testing, experimental, safe to delete
class App_Plugin_Sanitize extends Zend_Controller_Plugin_Abstract
{
    public function routeStartup(Zend_Controller_Request_Abstract $request)
    {
        foreach ($_GET as $key => $value) {
            $sanitized = $this->sanitize($value);

            Default_Model_Apps::logger(
                sprintf("Sanitize param '%s' from [%s] to [%s]", $key, $this->formatForLog($value), $this->formatForLog($sanitized)),
                null,
                "SANITIZE PARAM PLUGIN"
            );

            $_GET[$key] = $sanitized;
        }

        $params = $request->getParams();
        foreach ($params as $key => $value) {
            if (isset($_GET[$key])) {
                $request->setParam($key, $_GET[$key]);
            }
        }
    }

    private function sanitize($value)
    {
        if (is_array($value)) {
            return array_map([$this, 'sanitize'], $value);
        }

        $clean = strip_tags($value);        // remove HTML tags
        $clean = trim($clean);              // remove whitespace
        $clean = preg_replace('/[^a-zA-Z0-9\s]/', '', $clean); // remove unwanted chars
        $clean = mb_substr($clean, 0, 50, 'UTF-8'); // trim to 50 chars

        $valid = preg_match('/^[a-zA-Z0-9\s]+$/', $value);
        if (!$valid) {
            return '0';
        }

        return $clean;
    }

    private function formatForLog($value)
    {
        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }
}