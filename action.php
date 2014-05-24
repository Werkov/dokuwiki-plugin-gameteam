<?php

/**
 * DokuWiki Plugin gameteam (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michal Koutný <xm.koutn7y@gmail.com>
 */
// must be run within Dokuwiki
if (!defined('DOKU_INC'))
    die();

class action_plugin_gameteam extends DokuWiki_Action_Plugin {

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {

        $controller->register_hook('HTML_LOGINFORM_OUTPUT', 'BEFORE', $this, 'handle_html_loginform_output');
        $controller->register_hook('HTML_REGISTERFORM_OUTPUT', 'BEFORE', $this, 'handle_html_registerform_output');
        $controller->register_hook('HTML_RESENDPWDFORM_OUTPUT', 'BEFORE', $this, 'handle_html_resendpwdform_output');
        $controller->register_hook('HTML_UPDATEPROFILEFORM_OUTPUT', 'BEFORE', $this, 'handle_html_updateprofileform_output');
        $controller->register_hook('AUTH_USER_CHANGE', 'BEFORE', $this, 'handle_auth_user_change');
        $controller->register_hook('MAIL_MESSAGE_SEND', 'BEFORE', $this, 'handle_mail_message_send');
    }

    /**
     * [Custom event handler which performs action]
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
    public function handle_html_loginform_output(Doku_Event &$event, $param) {
        $form = $event->data;
        $pos = $form->findElementByAttribute('name', 'u');
        $el = & $form->getElementAt($pos);
        $el['_text'] = $this->getLang('teamno');
    }

    public function handle_html_registerform_output(Doku_Event &$event, $param) {
        $form = $event->data;

        $pos = $form->findElementByAttribute('name', 'login');
        $form->replaceElement($pos, null);
        $form->addHidden('login', auth_plugin_gameteam::LOGIN_PLACEHOLDER);

        $pos = $form->findElementByAttribute('name', 'fullname');
        $el = & $form->getElementAt($pos);
        $el['_text'] = $this->getLang('teamname');

        $pos = $form->findElementByAttribute('name', 'email');
        $el = & $form->getElementAt($pos);
        $el['_text'] = $this->getLang('contactmail');

        $pos = $form->findElementByAttribute('type', 'submit');
        $submit = $form->getElementAt($pos);
        $form->replaceElement($pos, null);

        // custom fields
        $form->startFieldset($this->getLang('teaminfo'));

        $fieldspec = json_decode($this->getConf('teamfields'), true);
        $defaultSpec = array(
            'default' => null,
            'type' => 'text',
        );

        foreach ($fieldspec as $name => $spec) {
            $spec = array_merge($defaultSpec, $spec);

            switch ($spec['type']) {
                case 'bool':
                    $field = form_makeCheckboxField($name, $spec['default'], $spec['label'], '', 'block');
                    break;
                default:
                    $field = form_makeTextField($name, '', $spec['label'], '', 'block');
                    break;
            }
            $form->addElement($field);
        }

        $form->endFieldset();

        $form->addElement($submit);
    }

    public function handle_html_resendpwdform_output(Doku_Event &$event, $param) {
        $form = $event->data;
        $pos = $form->findElementByAttribute('name', 'login');
        $el = & $form->getElementAt($pos);
        $el['_text'] = $this->getLang('teamno');
    }

    public function handle_html_updateprofileform_output(Doku_Event &$event, $param) {
        $user = $_SERVER['REMOTE_USER'];
        $vs = str_pad($user, 3, '0', STR_PAD_LEFT);
        $form = $event->data;

        $payment = sprintf('<p>Editace týmových informací ještě silně připomíná Dokuwiki, ale platební kontakt již uvádíme :-)</p>
    <ul>
    <li>Účet: <strong>%s</strong></li>
    <li>Variabilní symbol <strong>%s%s</strong></li>
    </ul>
', $this->getConf('account'), $this->getConf('vs_prefix'), $vs);

        $form->insertElement(0, $payment);
//        $form->startFieldset('Týmováci');
//        $form->addElement('AHOJ');
//        $form->endFieldset();
    }

    public function handle_auth_user_change(Doku_Event &$event, $param) {
        global $INPUT;
        $data = & $event->data;
        $type = $data['type'];

        $fields = array();
        foreach (json_decode($this->getConf('teamfields'), true) as $name => $fieldSpec) {
            $fields[$name] = $INPUT->post->str($name);
        }
        switch ($type) {
            case 'create':
                $data['params'][] = array(); // no groups
                $data['params'][] = $fields; // additional parameters
                break;
        }
    }

    public function handle_mail_message_send(Doku_Event &$event, $param) {
        $filename = metaFN('gameteam_mails', 'txt');
        $data = $event->data;
        $f = fopen($filename, 'a+');
        fwrite($f, "Email to " . $data['to'] . ", result: " . (int) $data['success'] . ".\n");
        fwrite($f, $data['body']);
        fwrite($f, "\n\n\n");
        fclose($f);
    }

}

// vim:ts=4:sw=4:et:
