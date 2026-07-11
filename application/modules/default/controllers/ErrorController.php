<?php

class Default_ErrorController extends Zend_Controller_Action
{
    public function errorAction()
    {
        $errors = $this->_getParam('error_handler');

        if (str_contains($errors->exception, ".well-known")) {
            $this->getResponse()->setHttpResponseCode($errors->exception->getCode());
            $this->_helper->json($errors->exception);
        }

        $logger = Zend_Registry::get("logger");
        $logger->err($errors->exception);

        $this->_helper->layout->setLayout('error');
        $this->view->message = $errors->exception;

        switch ($errors->type) {
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ROUTE:
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER:
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION:
                $this->getResponse()->setHttpResponseCode(404);
                $this->view->title = "404";
                $this->renderScript('error/404.phtml');
                break;
            default:
                $this->getResponse()->setHttpResponseCode(500);
                $this->view->title = "General Error";
                $this->renderScript('error/error-general.phtml');
                break;
        }
    }
}
