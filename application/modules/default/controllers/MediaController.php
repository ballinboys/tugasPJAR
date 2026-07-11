<?php

class Default_MediaController extends Zend_Controller_Action
{
    public function uploadAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->_helper->redirector('index', 'index', 'default');
        }

        Default_Model_Apps::checkCsrf();

        $file = $_FILES['media'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Default_Model_Apps::modalError("Upload gagal", "File tidak diterima");
            return $this->_helper->redirector('index', 'index', 'default');
        }

        try {
            $res = Default_Model_Api::upload('/media/upload', $file, [
                'user_id' => $_SESSION['user_web']['id'],
            ]);

            if ($res['status'] === 200) {
                Default_Model_Apps::modalAlert("Upload berhasil", "Media tersimpan");
            } else {
                Default_Model_Apps::modalError("Upload gagal", $res['body']['message'] ?? "Terjadi kesalahan");
            }
        } catch (Exception $e) {
            Default_Model_Apps::logger("ERROR MEDIA_UPLOAD | {$e->getMessage()}", level: "error", label: "MEDIA_UPLOAD");
            Default_Model_Apps::modalError("Upload gagal", "Backend tidak terjangkau");
        }

        $this->_helper->redirector('index', 'index', 'default');
    }

    /**
     * Relays the backend's Range-aware stream to the browser: the Range
     * header goes upstream, the 206/Content-Range response comes back
     * through, so seeking works across both processes.
     */
    public function streamAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        $id = (int) $this->getRequest()->getParam('id');
        $userId = (int) $_SESSION['user_web']['id'];

        $extraHeaders = [];
        $range = $this->getRequest()->getHeader('Range');
        if ($range) {
            $extraHeaders[] = 'Range: ' . $range;
        }

        // A playing video holds the request open; without this every other
        // request from the same session would block on the session lock.
        session_write_close();

        while (ob_get_level()) {
            ob_end_clean();
        }

        Default_Model_Api::proxy("/media/stream/id/$id?user_id=$userId", $extraHeaders);
    }
}
