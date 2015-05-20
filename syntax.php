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
        $this->Lexer->addSpecialPattern('<gameteam\b.*?>', $mode, 'plugin_gameteam');
        $this->Lexer->addSpecialPattern('<puzzles\b.*?>.+?</puzzles>', $mode, 'plugin_gameteam');
        $this->Lexer->addSpecialPattern('<kachnupload>', $mode, 'plugin_gameteam');
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

        if (substr($match, 0, 9) === '<gameteam') {
            $data['type'] = 'gameteam';
            $parameterString = substr($match, 10, -1);
            $data['parameters'] = $this->parseParameters($parameterString, array('volume_id' => null));
        } else if ($match == '<kachnupload>') {
            $data['type'] = 'kachnupload';
        } else {
            $data['type'] = 'puzzles';
            list($parameterString, $additionalString) = preg_split('/>/u', substr($match, 9, -10), 2);
            $data['parameters'] = $this->parseParameters($parameterString, array(
                'root' => null,
                'volume_id' => null,
                    ));
            $data['additional'] = $additionalString;
        }

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



        if ($data['type'] == 'gameteam') {
            $renderer->nocache();
            $this->renderTeams($renderer, $data['parameters']);
        } else if ($data['type'] == 'kachnupload') {
            $renderer->nocache();
            $this->renderUpload($renderer);
        } else if ($data['type'] == 'puzzles') {
            $this->renderPuzzles($renderer, $data['additional'], $data['parameters']);
        } else {
            return false;
        }

        return true;
    }

    private function renderTeams(Doku_Renderer &$renderer, $parameters) {
        $stmt = $this->helper->getConnection()->prepare('select *
            from team
            where volume_id = :volume_id
            and state <> :state
            order by state desc, team_id_volume ASC');
        $stmt->bindValue('volume_id', $parameters['volume_id']);
        $stmt->bindValue('state', auth_plugin_gameteam::STATE_CANCELLED);
        $stmt->execute();
        $teams = $stmt->fetchAll();

        $stmt = $this->helper->getConnection()->prepare('select p.*
            from player p
            left join team t on t.team_id = p.team_id
            where t.volume_id = :volume_id');
        $stmt->bindValue('volume_id', $parameters['volume_id']);
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
                $renderer->doc .= '<div class="team-item">';
                $renderer->doc .= '<h3>' . hsc($team['name']) . '';
                $renderer->doc .= '<span class="team-info' .
                        ($team['state'] == auth_plugin_gameteam::STATE_PAID ? ' paid' : '') . '">' .
                        $team['team_id_volume'] . '</span></h3>';
                $names = array_map(function($it) {
                            return $it['display_name'];
                        }, $playersInTeams[$team['team_id']]);

                $renderer->doc .= '<p>';
                $renderer->doc .= implode(', ', $names);
                $renderer->doc .= '</p>';
                $renderer->doc .= '</div>';
            }
        } else {
            $renderer->doc .= '<p>Žádné týmy.</p>';
        }
    }

    private function renderUpload(Doku_Renderer &$renderer) {
        if (!$this->getConf('show_upload')) {
            return;
        }
        $user = $_SERVER['REMOTE_USER'];

        $text = rawLocale('kachnupload');
        $text = str_replace('@USER@', $user, $text);
        $text = str_replace('@YEAR@', $this->getConf('upload_year'), $text);

        $renderer->doc .= p_render('xhtml', p_get_instructions($text), $info);
    }

    private function renderPuzzles(Doku_Renderer &$renderer, $additional, $parameters) {
        $stmt = $this->helper->getConnection()->prepare('select *
            from team
            where volume_id = :volume_id
            and state <> :state
            order by name');
        $stmt->bindValue('volume_id', $parameters['volume_id']);
        $stmt->bindValue('state', auth_plugin_gameteam::STATE_CANCELLED);
        $stmt->execute();
        $teams = $stmt->fetchAll();

        $teamFiles = array();
        search($teamFiles, mediaFN($parameters['root']), function(&$data, $base, $file, $type, $lvl, $opts) {
                    if ($type == 'd') {
                        return false;
                    }
                    $fileId = pathID($file, true);
                    if ($fileId != cleanID($fileId)) {
                        return false; // skip non-valid files
                    }

                    $filename = basename($file);
                    list($teamIdVolume, $other) = explode('-', $filename, 2);
                    $data[$teamIdVolume] = $opts['root'] . ':' . $fileId;
                    return true;
                }, array('root' => $parameters['root']));

        $code = '';
        $first = true;
        foreach ($teams as $team) {
            if ($first) {
                $first = false;
            } else {
                $code .= "\n";
            }
            $teamIdVolume = $team['team_id_volume'];
            $fileId = isset($teamFiles[$teamIdVolume]) ? $teamFiles[$teamIdVolume] : null;
            if ($fileId) {
                $code .= '  * {{' . $fileId . '|' . hsc($team['name']) . '}}';
            } else {
                $code .= '  * ' . hsc($team['name']);
            }
        }

        $code .= $additional;

        $renderer->doc .= p_render('xhtml', p_get_instructions($code), $info);
    }

    private function parseParameters($parameterString, $default) {
        //----- default parameter settings
        $params = $default;

        //----- parse parameteres into name="value" pairs
        preg_match_all("/(\w+?)=\"(.*?)\"/", $parameterString, $regexMatches, PREG_SET_ORDER);

        for ($i = 0; $i < count($regexMatches); $i++) {
            $name = strtolower($regexMatches[$i][1]);  // first subpattern: name of attribute in lowercase
            $value = $regexMatches[$i][2];              // second subpattern is value

            $found = false;
            foreach ($params as $paramName => $default) {
                if (strcmp($name, $paramName) == 0) {
                    $params[$name] = trim($value);
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                msg(sprintf('Bad param %s.', $name), -1);
            }
        }
        return $params;
    }

}

// vim:ts=4:sw=4:et:
