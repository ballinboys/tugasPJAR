<?php

class Default_FileController extends Zend_Controller_Action
{
    public function uploadAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->_helper->redirector('index', 'index', 'default');
        }

        Default_Model_Apps::checkCsrf();

        $file = $_FILES['file'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Default_Model_Apps::modalError("Upload gagal", "File tidak diterima");
            return $this->_helper->redirector('index', 'index', 'default');
        }

        try {
            $res = Default_Model_Api::upload('/file/upload', $file, [
                'user_id' => $_SESSION['user_web']['id'],
            ]);

            if ($res['status'] === 200) {
                Default_Model_Apps::modalAlert("Upload berhasil", "File tersimpan");
            } else {
                Default_Model_Apps::modalError("Upload gagal", $res['body']['message'] ?? "Terjadi kesalahan");
            }
        } catch (Exception $e) {
            Default_Model_Apps::logger("ERROR FILE_UPLOAD | {$e->getMessage()}", level: "error", label: "FILE_UPLOAD");
            Default_Model_Apps::modalError("Upload gagal", "Backend tidak terjangkau");
        }

        $this->_helper->redirector('index', 'index', 'default');
    }

    public function downloadAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        $id = (int) $this->getRequest()->getParam('id');
        $userId = (int) $_SESSION['user_web']['id'];

        // Release the session lock so the download doesn't block other requests
        session_write_close();

        while (ob_get_level()) {
            ob_end_clean();
        }

        Default_Model_Api::proxy("/file/download/id/$id?user_id=$userId");
    }
}
