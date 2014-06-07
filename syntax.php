<?php

/**
 * DokuWiki Plugin gameteam (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michal Koutný <xm.koutny@gmail.com>
 */
// must be run within Dokuwiki
if (!defined('DOKU_INC'))
    die();

class syntax_plugin_gameteam extends DokuWiki_Syntax_Plugin {

    /**
     * @var helper_plugin_gameteam
     */
    private $helper;

    public function __construct() {
        $this->helper = $this->loadHelper('gameteam');
    }

    /**
     * @return string Syntax mode type
     */
    public function getType() {
        return 'substition';
    }

    /**
     * @return string Paragraph type
     */
    public function getPType() {
        return 'normal';
    }

    /**
     * @return int Sort order - Low numbers go before high numbers
     */
    public function getSort() {
        return 165;
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<gameteam>', $mode, 'plugin_gameteam');
    }

    /**
     * Handle matches of the gameteam syntax
     *
     * @param string $match The match of the syntax
     * @param int    $state The state of the handler
     * @param int    $pos The position in the document
     * @param Doku_Handler    $handler The handler
     * @return array Data for the renderer
     */
    public function handle($match, $state, $pos, Doku_Handler &$handler) {
        $data = array();

        return $data;
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string         $mode      Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer  $renderer  The renderer
     * @param array          $data      The data from the handler() function
     * @return bool If rendering was successful.
     */
    public function render($mode, Doku_Renderer &$renderer, $data) {
        if ($mode != 'xhtml')
            return false;

        $renderer->nocache();

        $stmt = $this->helper->getConnection()->prepare('select *
            from team
            where volume_id = :volume_id
            and state <> \'90\'
            order by name');
        $stmt->bindValue('volume_id', $this->getConf('volume_id'));
        $stmt->execute();
        $teams = $stmt->fetchAll();

        $stmt = $this->helper->getConnection()->prepare('select p.*
            from player p
            left join team t on t.team_id = p.team_id
            where t.volume_id = :volume_id');
        $stmt->bindValue('volume_id', $this->getConf('volume_id'));
        $stmt->execute();
        $players = $stmt->fetchAll();
        $playersInTeams = array();

        foreach ($teams as $team) {
            $playersInTeams[$team['team_id']] = array();
        }

        foreach ($players as $player) {
            $player['display_name'] = hsc($player['display_name']);
            $playersInTeams[$player['team_id']][] = $player;
        }

        if (count($teams)) {
            $renderer->doc .= '<p>Počet týmů: ' . count($teams) . '</p>';
            foreach ($teams as $team) {
                $renderer->doc .= '<h3>' . hsc($team['name']) . ' (' . $team['login_id'] . ')</h3>';
                $names = array_map(function($it) {
                            return $it['display_name'];
                        }, $playersInTeams[$team['team_id']]);

                $renderer->doc .= '<p>';
                $renderer->doc .= implode(', ', $names);
                $renderer->doc .= '</p>';
            }
        } else {
            $renderer->doc .= '<p>Žádné týmy.</p>';
        }

        return true;
    }

}

// vim:ts=4:sw=4:et:
