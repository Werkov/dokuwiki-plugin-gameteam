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
     * @var helper_plugin_gameteam
     */
    private $helper;

    public function __construct() {
        $this->helper = $this->loadHelper('gameteam');
    }

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
        $this->modify_user_form($form);
    }

    public function handle_html_resendpwdform_output(Doku_Event &$event, $param) {
        $form = $event->data;
        $pos = $form->findElementByAttribute('name', 'login');
        $el = & $form->getElementAt($pos);
        $el['_text'] = $this->getLang('teamno');
    }

    public function handle_html_updateprofileform_output(Doku_Event &$event, $param) {
        $user = $_SERVER['REMOTE_USER'];
        list($volumeId, $teamIdVolume) = plugin_load('auth', 'gameteam')->parseUsername($user);
        $form = $event->data;
        $team = $this->helper->select('team', array(
            'team_id_volume' => $teamIdVolume,
            'volume_id' => $volumeId,
        ));
        $l = $this->getConf('vs_length');
        $m = pow(10, $l);
        $vs = str_pad($teamIdVolume % $m, $l, '0', STR_PAD_LEFT);


        $info = array(
            'Tým' => $team['name'],
            'Účet' => $this->getConf('account'),
            'Variabilní symbol' => $this->getConf('vs_prefix') . $vs,
        );

        if ($team['state'] == auth_plugin_gameteam::STATE_PAID) {
            $info['Stav'] = 'Zaplaceno';
        } else if ($team['state'] == auth_plugin_gameteam::STATE_REGISTERED) {
            $info['Stav'] = 'Nezaplaceno';
        }
        $elInfo = '<h2>Informace o platbě</h2><ul>';
        foreach ($info as $name => $value) {
            $elInfo .= '<li>' . $name . ': <strong>' . hsc($value) . '</strong></li>';
        }
        $elInfo .= '</ul>';
        $elInfo .= '<h2>Úprava informací</h2>';

        if ($this->getConf('show_payment')) {
            $form->insertElement(0, $elInfo);
        }

        $this->modify_user_form($form);
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
            case 'modify':
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

    private function modify_user_form(Doku_Form $form) {
        global $INPUT;

        $pos = $form->findElementByAttribute('name', 'login');
        $form->replaceElement($pos, null);
        $form->addHidden('login', auth_plugin_gameteam::LOGIN_PLACEHOLDER);

        $pos = $form->findElementByAttribute('name', 'fullname');
        $el = & $form->getElementAt($pos);
        $el['_text'] = $this->getLang('teamname');

        $pos = $form->findElementByAttribute('name', 'email');
        $el = & $form->getElementAt($pos);
        $el['_text'] = $this->getLang('contactmail');

        // submit button
        $pos = $form->findElementByAttribute('type', 'submit');
        $submit = $form->getElementAt($pos);
        $form->replaceElement($pos, null);

        // reset button
        $pos = $form->findElementByAttribute('type', 'reset');
        if ($pos) {
            $reset = $form->getElementAt($pos);
            $form->replaceElement($pos, null);
        }

        // load team info
        $loginId = $_SERVER['REMOTE_USER'];
        $teamInfo = array();
        if ($loginId) {
            $teamInfo = $this->loadTeamInfo($loginId);
        }

        // custom fields
        $form->startFieldset($this->getLang('teaminfo'));

        $fieldspec = json_decode($this->getConf('teamfields'), true);
        $defaultSpec = array(
            'default' => null,
            'type' => 'text',
        );

        foreach ($fieldspec as $name => $spec) {
            $spec = array_merge($defaultSpec, $spec);
            if (array_key_exists($name, $teamInfo)) {
                $spec['default'] = $teamInfo[$name];
            }

            $value = $INPUT->post->str($name, $spec['default'], true);
            switch ($spec['type']) {
                case 'bool':
                    $field = form_makeCheckboxField($name, $value, $spec['label'], '', 'block');
                    break;
                default:
                    $field = form_makeTextField($name, $value, $spec['label'], '', 'block');
                    break;
            }
            $form->addElement($field);
        }

        $form->endFieldset();

        $form->addElement($submit);
        $form->addElement($reset);
    }

    private function loadTeamInfo($loginId) {
        $connection = $this->helper->getConnection();
        $stmt = $connection->prepare('select * from team where volume_id = :volume_id and login_id = :login_id');
        $stmt->bindValue('volume_id', $this->getConf('volume_id'));
        $stmt->bindValue('login_id', $loginId);
        $stmt->execute();

        $teamInfo = $stmt->fetch();
        if (!$teamInfo) {
            $teamInfo = array();
        }

        $memberInfo = array();
        $i = 0;
        $stmt = $connection->prepare('select * from player where team_id = :team_id order by player_id');
        $stmt->bindValue('team_id', $teamInfo['team_id']);
        $stmt->execute();
        while ($row = $stmt->fetch()) {
            ++$i;
            foreach ($row as $column => $value) {
                $memberInfo[$column . '_' . $i] = $value;
            }
        }

        return array_merge($memberInfo, $teamInfo);
    }

}

// vim:ts=4:sw=4:et:
