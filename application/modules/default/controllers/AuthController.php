<?php

/**
 * POST handlers for the auth forms on the single page (/). Each action
 * forwards to the backend API and maps the response to the session-flash
 * modals, then redirects back to /.
 */
class Default_AuthController extends Zend_Controller_Action
{
    public function registerAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->_helper->redirector('index', 'index', 'default');
        }

        Default_Model_Apps::checkCsrf();

        try {
            $request = $this->getRequest();
            $res = Default_Model_Api::post('/auth/register', [
                'name' => trim((string) $request->getPost('name')),
                'email' => strtolower(trim((string) $request->getPost('email'))),
                'password' => (string) $request->getPost('password'),
                'password_confirm' => (string) $request->getPost('password_confirm'),
            ]);

            if ($res['status'] === 200) {
                Default_Model_Apps::modalAlert("Pendaftaran berhasil", "Silakan masuk dengan akun baru Anda");
            } else {
                Default_Model_Apps::modalError("Daftar gagal", $res['body']['message'] ?? "Terjadi kesalahan, coba lagi");
            }
        } catch (Exception $e) {
            Default_Model_Apps::logger("ERROR REGISTER | {$e->getMessage()}", level: "error", label: "REGISTER");
            Default_Model_Apps::modalError("Daftar gagal", "Backend tidak terjangkau, coba lagi");
        }

        $this->_helper->redirector('index', 'index', 'default');
    }

    public function forgotAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->_helper->redirector('index', 'index', 'default');
        }

        Default_Model_Apps::checkCsrf();

        try {
            $request = $this->getRequest();
            // Behind Cloudflare Tunnel getScheme() sees plain HTTP; trust the
            // proxy's X-Forwarded-Proto so the emailed link keeps https.
            $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $request->getScheme();
            $res = Default_Model_Api::post('/auth/forgot', [
                'email' => strtolower(trim((string) $request->getPost('email'))),
                // The backend emails a link back to this frontend
                'reset_url_base' => $scheme . '://' . $request->getHttpHost(),
            ]);

            if ($res['status'] === 200) {
                Default_Model_Apps::modalAlert("Permintaan diterima", "Jika email terdaftar, tautan reset sudah dikirim");
            } else {
                Default_Model_Apps::modalError("Gagal mengirim email", $res['body']['message'] ?? "Terjadi kesalahan, coba lagi");
            }
        } catch (Exception $e) {
            Default_Model_Apps::logger("ERROR FORGOT | {$e->getMessage()}", level: "error", label: "FORGOT");
            Default_Model_Apps::modalError("Gagal mengirim email", "Backend tidak terjangkau, coba lagi");
        }

        $this->_helper->redirector('index', 'index', 'default');
    }

    public function resetAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->_helper->redirector('index', 'index', 'default');
        }

        Default_Model_Apps::checkCsrf();

        $token = (string) $this->getRequest()->getPost('token', '');

        try {
            $request = $this->getRequest();
            $res = Default_Model_Api::post('/auth/reset', [
                'token' => $token,
                'password' => (string) $request->getPost('password'),
                'password_confirm' => (string) $request->getPost('password_confirm'),
            ]);

            if ($res['status'] === 200) {
                Default_Model_Apps::modalAlert("Password diperbarui", "Silakan masuk dengan password baru Anda");
            } else {
                Default_Model_Apps::modalError("Reset gagal", $res['body']['message'] ?? "Terjadi kesalahan, coba lagi");
                if ($res['status'] === 422) {
                    // Validation problem — token still valid, back to the form
                    return $this->_helper->redirector->gotoUrl('/?token=' . $token);
                }
            }
        } catch (Exception $e) {
            Default_Model_Apps::logger("ERROR RESET | {$e->getMessage()}", level: "error", label: "RESET");
            Default_Model_Apps::modalError("Reset gagal", "Backend tidak terjangkau, coba lagi");
        }

        $this->_helper->redirector('index', 'index', 'default');
    }

    /**
     * SMTP smoke test: GET /auth/mailtest while logged in asks the backend
     * to send one email. /auth/* is exempt from AccessControl, so this
     * action checks the session itself.
     */
    public function mailtestAction()
    {
        if (empty($_SESSION['user_web'])) {
            return $this->_helper->redirector->gotoUrl('/');
        }

        try {
            $res = Default_Model_Api::post('/auth/mailtest', []);

            if ($res['status'] === 200) {
                Default_Model_Apps::modalAlert("Email terkirim", "Cek inbox " . ($res['body']['data']['to'] ?? ''));
            } else {
                Default_Model_Apps::modalError("SMTP gagal", $res['body']['message'] ?? "Terjadi kesalahan");
            }
        } catch (Exception $e) {
            Default_Model_Apps::logger("ERROR MAILTEST | {$e->getMessage()}", level: "error", label: "MAILTEST");
            Default_Model_Apps::modalError("SMTP gagal", "Backend tidak terjangkau");
        }

        $this->_helper->redirector('index', 'index', 'default');
    }
}
