<?php
class Default_IndexController extends Zend_Controller_Action
{
    public function indexAction()
    {
        $this->view->title = "Network Lab";

        $user = $_SESSION["user_web"] ?? null;
        $this->view->user = $user;
        $this->view->resetToken = null;

        if ($user) {
            $this->view->files = [];
            $this->view->media = [];
            try {
                $files = Default_Model_Api::get('/file/list', ['user_id' => $user['id'], 'category' => 'file']);
                $media = Default_Model_Api::get('/file/list', ['user_id' => $user['id'], 'category' => 'media']);
                $this->view->files = $files['body']['data'] ?? [];
                $this->view->media = $media['body']['data'] ?? [];
            } catch (Exception $e) {
                Default_Model_Apps::logger("ERROR LIST | {$e->getMessage()}", level: "error", label: "API");
                Default_Model_Apps::modalError("Backend tidak terjangkau", "Pastikan server API berjalan");
            }
            return;
        }

        // Reset link from the email lands here: /?token=...
        $token = (string) $this->getRequest()->getParam('token', '');
        if ($token !== '') {
            $valid = false;
            try {
                $res = Default_Model_Api::post('/auth/checkreset', ['token' => $token]);
                $valid = (bool) ($res['body']['data']['valid'] ?? false);
            } catch (Exception $e) {
                Default_Model_Apps::logger("ERROR CHECKRESET | {$e->getMessage()}", level: "error", label: "API");
            }

            if ($valid) {
                $this->view->resetToken = $token;
            } else {
                Default_Model_Apps::modalError("Tautan tidak berlaku", "Tautan reset salah, kedaluwarsa, atau sudah dipakai");
            }
        }
    }

    public function loginAction()
    {
        Default_Model_Apps::checkCsrf();

        try {
            $request = $this->getRequest();

            $email = strtolower(trim((string) $request->getPost("email")));
            $password = (string) $request->getPost("password");

            $res = Default_Model_Api::post('/auth/login', ['email' => $email, 'password' => $password]);

            if ($res['status'] === 200 && isset($res['body']['data']['id'])) {
                Zend_Session::regenerateId();
                $_SESSION['user_web'] = array(
                    'id' => (int) $res['body']['data']['id'],
                    'name' => $res['body']['data']['name'],
                    'email' => $res['body']['data']['email'],
                );
                Default_Model_Apps::logger("Login successful for user: $email", level: 'info', label: "LOGIN");
            } else {
                Default_Model_Apps::logger("Login failed for user: $email", level: 'info', label: "LOGIN");
                Default_Model_Apps::modalError("Login gagal", $res['body']['message'] ?? "Email atau password salah");
            }
        } catch (Exception $e) {
            Default_Model_Apps::logger("ERROR LOGIN | {$e->getMessage()}", level: "error", label: "LOGIN");
            Default_Model_Apps::modalError("Login gagal", "Backend tidak terjangkau, coba lagi");
        }

        $this->_helper->redirector('index', 'index', 'default');
    }

    public function logoutAction()
    {
        $email = $_SESSION['user_web']['email'] ?? 'guest';
        Default_Model_Apps::logger("User $email logged out", 'info', 'LOGOUT');
        unset($_SESSION["user_web"]);
        $this->_helper->redirector->gotoUrl('/');
    }
}
