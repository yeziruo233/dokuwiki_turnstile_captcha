<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;
use dokuwiki\Form\Form;

/**
 * DokuWiki Plugin turnstile (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author Ye Ziruo <i@yeziruo.com>
 */
class action_plugin_turnstile extends ActionPlugin
{
    /**
     * register
     * register hook function
     * @param EventHandler $controller event handle controller
     * @return void
     */
    public function register(EventHandler $controller): void
    {
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'handleInjectJavaScriptComponents', []);

        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handleActionProcess', []);
        $controller->register_hook('AUTH_LOGIN_CHECK', 'BEFORE', $this, 'handleLoginCheck', []);

        $controller->register_hook('FORM_LOGIN_OUTPUT', 'BEFORE', $this, 'handleInjectForm', []);
        $controller->register_hook('FORM_REGISTER_OUTPUT', 'BEFORE', $this, 'handleInjectForm', []);
        $controller->register_hook('FORM_RESENDPWD_OUTPUT', 'BEFORE', $this, 'handleInjectForm', []);
        if ($this->getConf('edit_protect')) {
            $controller->register_hook('FORM_EDIT_OUTPUT', 'BEFORE', $this, 'handleInjectForm', []);
        }
    }

    /**
     * needCheck
     * check action is need check
     * @param string $action action name
     * @return bool result
     */
    function needCheck(string $action): bool
    {
        global $INPUT;
        $method = $INPUT->server->str('REQUEST_METHOD');
        // skip GET,OPTIONS request
        if ($method === 'GET' || $method === 'OPTIONS') return false;
        switch ($action) {
            case 'register':
            case 'resendpwd':
                return true;
            case 'save':
                return $this->getConf('edit_protect');
            case 'login':
                // do nothing... handle by AUTH_LOGIN_CHECK.
            default:
                return false;
        }
    }

    /**
     * handleInjectJavaScriptComponents
     * inject javaScript components to mate element
     * @param Event $event Event object
     * @param mixed $param optional parameter passed when event was registered
     * @return void
     */
    public function handleInjectJavaScriptComponents(Event $event, mixed $param): void
    {
        $event->data["script"][] = [
            'type' => 'text/javascript',
            'src' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
        ];
    }

    /**
     * handleInjectForm
     * inject turnstile components to form
     * @param Event $event Event object
     * @param mixed $param optional parameter passed when event was registered
     * @return void
     */
    public function handleInjectForm(Event $event, mixed $param): void
    {
        /** @var Form|Doku_Form $form */
        $form = $event->data;
        $pos = $form->findPositionByAttribute('type', 'submit');
        if (!$pos) {
            return;
        }
        /** @var helper_plugin_turnstile $helper */
        $helper = plugin_load('helper', 'turnstile');
        $form->addHTML($helper->getHtml(), $pos);
    }

    /**
     * handleLoginCheck
     * handle login check
     * @param Event $event Event object
     * @param mixed $param optional parameter passed when event was registered
     * @return void
     */
    public function handleLoginCheck(Event $event, mixed $param): void
    {
        global $INPUT;
        if (!$INPUT->bool('u')) return;
        /** @var helper_plugin_turnstile $helper */
        $helper = plugin_load('helper', 'turnstile');
        if ($helper->siteVerify()) return;
        $event->data['silent'] = true;
        $event->result = false;
        $event->preventDefault();
        $event->stopPropagation();
    }

    /**
     * handleActionProcess
     * handle action process
     * @param Event $event Event object
     * @param mixed $param optional parameter passed when event was registered
     * @return void
     */
    public function handleActionProcess(Event $event, mixed $param): void
    {
        global $INPUT;
        $action = act_clean($event->data);
        if (!$this->needCheck($action)) return;
        /** @var helper_plugin_turnstile $helper */
        $helper = plugin_load('helper', 'turnstile');
        if (!$helper->siteVerify()) {
            if ($action === 'save') {
                $event->data = 'preview';
            } else {
                $INPUT->post->set('save', false);
            }
        }
    }
}
