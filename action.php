<?php
/**
 * DokuWiki Plugin gameteam (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michal Koutný <xm.koutny@gmail.com>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class action_plugin_gameteam extends DokuWiki_Action_Plugin {

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {

       $controller->register_hook('HTML_LOGINFORM_OUTPUT', 'FIXME', $this, 'handle_html_loginform_output');
       $controller->register_hook('HTML_REGISTERFORM_OUTPUT', 'FIXME', $this, 'handle_html_registerform_output');
       $controller->register_hook('HTML_RESENDPWDFORM_OUTPUT', 'FIXME', $this, 'handle_html_resendpwdform_output');
       $controller->register_hook('HTML_UPDATEPROFILEFORM_OUTPUT', 'FIXME', $this, 'handle_html_updateprofileform_output');
   
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
    }

    public function handle_html_registerform_output(Doku_Event &$event, $param) {
    }

    public function handle_html_resendpwdform_output(Doku_Event &$event, $param) {
    }

    public function handle_html_updateprofileform_output(Doku_Event &$event, $param) {
    }

}

// vim:ts=4:sw=4:et:
