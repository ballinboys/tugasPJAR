<?php

class LayoutSetup extends Zend_Controller_Plugin_Abstract
{
    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {
        $module = $request->getModuleName();
        $layoutPath = APPLICATION_PATH . '/modules/' . $module . '/layouts/scripts/';

        if (is_dir($layoutPath)) {
            Zend_Layout::getMvcInstance()->setLayoutPath($layoutPath);
            Zend_Layout::getMvcInstance()->setLayout('layout');
        } else {
            $defaultLayoutPath = APPLICATION_PATH . '/modules/default/layouts/scripts';
            Zend_Layout::getMvcInstance()->setLayoutPath($defaultLayoutPath);
            Zend_Layout::getMvcInstance()->setLayout('default');
        }
    }
}
