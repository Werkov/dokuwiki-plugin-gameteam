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

    /**
     *
     * @var helper_plugin_upload
     */
    private $upload_helper;

    public function __construct() {
        $this->helper = $this->loadHelper('gameteam');
        $this->upload_helper = $this->loadHelper('upload');
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
        $this->Lexer->addSpecialPattern('<puzzleinfo\b.*?>', $mode, 'plugin_gameteam');
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
        } else if (substr($match, 0, 11) === '<puzzleinfo') {
            $data['type'] = 'puzzleinfo';
            $parameterString = substr($match, 12, -1);
            $data['parameters'] = $this->parseParameters($parameterString, array(
                'volume_id' => null,
                'file_template' => null,
            ));
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
        } else if ($data['type'] == 'puzzleinfo') {
            $renderer->nocache();
            $this->renderPuzzleinfo($renderer, $data['parameters']);
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
            $stats = $this->teamStats($teams);
            $capacity = $this->getConf('capacity');
            $renderer->doc .= '<p>Počet týmů: ' . $stats['count'] .
                ', platících: ' . $stats['paid'] . '</p>';
            foreach ($teams as $team) {
                $renderer->doc .= '<div class="team-item' .
                        (--$capacity == 0 ? ' separator' : '') . '">';

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

    private function teamStats($teams) {
        $stats['paid'] = 0;
        $stats['count'] = 0;

        foreach ($teams as $team) {
            $stats['count'] += 1;
            if ($team['state'] == auth_plugin_gameteam::STATE_PAID) {
                $stats['paid'] += 1;
            }
        }

        return $stats;
    }

    private function renderUpload(Doku_Renderer &$renderer) {
        $user = $_SERVER['REMOTE_USER'];
        $template = rawLocale('kachnupload');

        $text = $this->expandFileTemplate($template, $this->getConf('upload_year'), $user);

        $renderer->doc .= p_render('xhtml', p_get_instructions($text), $info);
    }

    private function renderPuzzles(Doku_Renderer &$renderer, $additional, $parameters) {
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
        $teams = $this->getPuzzleTeams($parameters['volume_id'], auth_plugin_gameteam::STATE_PAID);
        foreach ($teams as $team) {
            if ($first) {
                $first = false;
            } else {
                $code .= "\n";
            }
            $teamIdVolume = $team['team_id_volume'];
            $fileId = isset($teamFiles[$teamIdVolume]) ? $teamFiles[$teamIdVolume] : null;
            if ($fileId) {
                $code .= '  * {{' . $fileId . '|' . self::sanitize($team['name'], true) . '}}';
            } else {
                $code .= '  * ' . self::sanitize($team['name']);
            }
        }

        $code .= $additional;

        $renderer->doc .= p_render('xhtml', p_get_instructions($code), $info);
    }

    private function renderPuzzleinfo(Doku_Renderer &$renderer, $parameters) {
        $template = $parameters['file_template'];
        $uploadYear = $this->getConf('upload_year');
        $volumeId = $parameters['volume_id'];

        $teams = $this->getPuzzleTeams($volumeId);
        $code = "^ ID ^ Tým ^ Šifra ^ Tajenka ^ Postup ^\n";
        foreach ($teams as $team) {
            $teamId = $team['team_id_volume'];
            $user = $this->helper->decorateUsername($teamId, $volumeId);
            $fileId = $this->expandFileTemplate($template, $uploadYear, $user);

            // authorization should be checked on the page with the table itself
            // but one more check here cannot do any wrong
            $auth = auth_quickaclcheck(getNS($fileId) . ':*');
            if ($auth < AUTH_READ) {
                continue;
            }
            $metadata = $this->upload_helper->get_metadata($fileId);
            $code .= '| ' . $teamId . ' ';
            $code .= '| ' . self::sanitize($team['name'], true) . ' ';
            if (!$metadata) {
                $code .= "| -- | -- | -- |\n";
            } else {
                $code .= '| {{' . $fileId . '|' . date('Y-m-d H:i:s', $metadata['timestamp']) . '}} ';
                $code .= "| " . self::sanitize($metadata['result']) . " ";
                $code .= "| " . self::sanitize($metadata['solution'], true) . " ";
                $code .= "|\n";
            }
        }

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

    private function getPuzzleTeams($volumeId, $filterState = null) {
        $stateCond = $filterState !== null ? " and state = :state2" : "";

        $stmt = $this->helper->getConnection()->prepare('select *
            from team
            where volume_id = :volume_id
            and state <> :state' . $stateCond . '
            order by name');
        $stmt->bindValue('volume_id', $volumeId);
        $stmt->bindValue('state', auth_plugin_gameteam::STATE_CANCELLED);
        if ($filterState !== null) {
            $stmt->bindValue('state2', $filterState);
        }
        $stmt->execute();
        $teams = $stmt->fetchAll();
        return $teams;
    }

    private function expandFileTemplate($template, $uploadYear, $user) {
        list($volumeId, $teamId) = $this->helper->parseUsername($user, null);
        $map = array(
            '@USER@' => $user,
            '@YEAR@' => $uploadYear,
            '@TEAMID@' => $teamId,
        );

        return str_replace(array_keys($map), array_values($map), $template);
    }

    private static function sanitize($text, $allow_syntax = false) {
        $text = preg_replace('/\r?\n/', '\\\\\ ', $text);
        if ($allow_syntax) {
            $text = preg_replace('/([<>])/', '<nowiki>$1</nowiki>', $text);
        } else {
            $text = "<nowiki>$text</nowiki>";
        }
        return $text;
    }

}

// vim:ts=4:sw=4:et:
