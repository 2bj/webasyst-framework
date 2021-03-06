<?php

class webasystForgotPasswordAction extends waForgotPasswordAction
{
    public function execute()
    {
        if ($this->layout) {
            if (waRequest::get('key')) {
                $this->layout->assign('dialog_class', 'newpassword');
            } else {
                $this->layout->assign('dialog_style', 'min-height: 130px; height: 160px');
            }
        }
        $this->template = wa()->getAppPath('templates/actions/forgot/ForgotPassword.html', 'webasyst');
        parent::execute();
    }

    protected function getResetPasswordUrl($hash)
    {
        if (wa()->getEnv() == 'backend') {
            return wa()->getRootUrl(true).wa()->getConfig()->getBackendUrl(false).'/?forgotpassword&key='.$hash;
        } else {
            return wa()->getRootUrl(true).wa()->getConfig()->getRequestUrl(true, true).'?forgotpassword&key='.$hash;
        }
    }
}
