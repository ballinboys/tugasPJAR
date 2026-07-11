<?php

class AccessControl extends Zend_Controller_Plugin_Abstract
{
    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {
        $currMod = $request->getModuleName();
        $currCtrl = $request->getControllerName();
        $currAct = $request->getActionName();

        Default_Model_Apps::logger("$currMod | $currCtrl | $currAct", level: 'debug', label: "ACCESS_CONTROL");

        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');

        $url = $_SERVER["REQUEST_URI"];

        if ($currCtrl == "error") {
            return;
        }

        if (str_contains($url, "/.well-known")) {
            return;
        }

        if (str_contains($url, "/index/logout")) {
            return;
        }

        if ($currMod == "default" && $currCtrl == "index") {
            return;
        }

        // Register / forgot / reset must be reachable while logged out
        if ($currMod == "default" && $currCtrl == "auth") {
            return;
        }

        $userWeb = $_SESSION["user_web"];
        if (!$userWeb) {
            $redirector->gotoUrl("/");
        }
    }
}
