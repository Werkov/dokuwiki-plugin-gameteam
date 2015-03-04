<?php

/**
 * DokuWiki Plugin gameteam (Auth Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michal Koutný <xm.koutny@gmail.com>
 */
// must be run within Dokuwiki
if (!defined('DOKU_INC'))
    die();

class auth_plugin_gameteam extends DokuWiki_Auth_Plugin {

    const LOGIN_PLACEHOLDER = '__LOGIN__';
    const LOGIN_SEPARATOR = '_';
    const BCRYPT_COST = 10;
    const STATE_REGISTERED = '00';
    const STATE_PAID = '10';
    const STATE_CANCELLED = '90';

    /**
     * @var helper_plugin_gameteam
     */
    private $helper;
    private $volumeId;
    private $superuserLogin;
    private $superuserPassword;
    private $lastCreatedUser = null;

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct(); // for compatibility

        $this->cando['addUser'] = true; // can Users be created?
        $this->cando['delUser'] = false; // can Users be deleted?
        $this->cando['modLogin'] = false; // can login names be changed?
        $this->cando['modPass'] = true; // can passwords be changed?
        $this->cando['modName'] = false; // can real names be changed?
        $this->cando['modMail'] = true; // can emails be changed?
        $this->cando['modGroups'] = false; // can groups be changed?
        $this->cando['getUsers'] = false; // can a (filtered) list of users be retrieved?
        $this->cando['getUserCount'] = false; // can the number of users be retrieved?
        $this->cando['getGroups'] = false; // can a list of available groups be retrieved?
        $this->cando['external'] = false; // does the module do external auth checking?
        $this->cando['logout'] = true; // can the user logout again? (eg. not possible with HTTP auth)

        $this->helper = $this->loadHelper('gameteam');
        $this->success = true;

        $this->volumeId = $this->getConf('volume_id');
        $this->superuserLogin = $this->getConf('superuser_login');
        $this->superuserPassword = $this->getConf('superuser_password');
    }

    /**
     * Check user+password
     *
     * May be ommited if trustExternal is used.
     *
     * @param   string $user the user name
     * @param   string $pass the clear text password
     * @return  bool
     */
    public function checkPass($user, $pass) {
        if ($user === $this->superuserLogin) {
            $hash = $this->superuserPassword;
        } else {
            list($volumeId, $teamIdVolume) = $this->parseUsername($user);

            /* We allow only teams from current volume. */
            if ($volumeId != $this->volumeId) {
                return false;
            }

            $stmt = $this->helper->getConnection()->prepare('
            select password
            from team t
            where t.volume_id = :volume_id and t.team_id_volume = :team_id_volume');

            $stmt->bindValue('volume_id', $volumeId);
            $stmt->bindValue('team_id_volume', $teamIdVolume);

            $stmt->execute();
            $hash = $stmt->fetchColumn();
        }

        return $this->verify($pass, $hash);
    }

    /**
     * Return user info
     *
     * Returns info about the given user needs to contain
     * at least these fields:
     *
     * name string  full name of the user
     * mail string  email addres of the user
     * grps array   list of groups the user is in
     *
     * @param   string $user the user name
     * @return  array containing user data or false
     */
    public function getUserData($user) {
        if ($user === $this->superuserLogin) {
            return array(
                'name' => $this->superuserLogin,
                'mail' => '',
                'grps' => array('admin'),
            );
        }

        list($volumeId, $teamId) = $this->parseUsername($user);

        $stmt = $this->helper->getConnection()->prepare('
            select t.name, t.email
            from team t
            where t.team_id_volume = :team_id_volume and t.volume_id = :volume_id');

        $stmt->bindValue('team_id_volume', $teamId);
        $stmt->bindValue('volume_id', $volumeId);

        $stmt->execute();
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }
        return array(
            'name' => $row['name'],
            'mail' => $row['email'],
            'grps' => array(),
        );
    }

    /**
     * Create a new User [implement only where required/possible]
     *
     * Returns false if the user already exists, null when an error
     * occurred and true if everything went well.
     *
     * The new user HAS TO be added to the default group by this
     * function!
     *
     * Set addUser capability when implemented
     *
     * @param  string     $user
     * @param  string     $pass
     * @param  string     $name
     * @param  string     $mail
     * @param  null|array $grps
     * @return bool|null
     */
    public function createUser($user, $pass, $name, $mail, $grps = null, $additional = null) {
        $this->helper->getConnection()->beginTransaction();
        $res = $this->createTeam($mail, $pass, $name, $additional);

        if (!$res) {
            $this->helper->getConnection()->rollBack();
        } else {
            $this->helper->getConnection()->commit();
        }

        return $res;
    }

    /**
     * Modify user data [implement only where required/possible]
     *
     * Set the mod* capabilities according to the implemented features
     *
     * @param   string $user    nick of the user to be changed
     * @param   array  $changes array of field/value pairs to be changed (password will be clear text)
     * @return  bool
     */
    public function modifyUser($user, $changes, $additional = null) {
        $this->helper->getConnection()->beginTransaction();
        $res = $this->updateTeam($user, $changes, $additional);

        if (!$res) {
            $this->helper->getConnection()->rollBack();
        } else {
            $this->helper->getConnection()->commit();
        }

        return $res;
    }

    /**
     * Return case sensitivity of the backend
     *
     * When your backend is caseinsensitive (eg. you can login with USER and
     * user) then you need to overwrite this method and return false
     *
     * @return bool
     */
    public function isCaseSensitive() {
        return true;
    }

    /**
     * Sanitize a given username
     *
     * This function is applied to any user name that is given to
     * the backend and should also be applied to any user name within
     * the backend before returning it somewhere.
     *
     * This should be used to enforce username restrictions.
     *
     * @param string $user username
     * @return string the cleaned username
     */
    public function cleanUser($user) {
        if($user === self::LOGIN_PLACEHOLDER && $this->lastCreatedUser) {
            return $this->lastCreatedUser;
        } elseif (!($user === self::LOGIN_PLACEHOLDER || $user === '' || $user === $this->superuserLogin)) {
            if (strstr($user, self::LOGIN_SEPARATOR) === false) {
                return $this->volumeId . self::LOGIN_SEPARATOR . $user;
            }
        }
        return $user;
    }

    /**
     * Sanitize a given groupname
     *
     * This function is applied to any groupname that is given to
     * the backend and should also be applied to any groupname within
     * the backend before returning it somewhere.
     *
     * This should be used to enforce groupname restrictions.
     *
     * Groupnames are to be passed without a leading '@' here.
     *
     * @param  string $group groupname
     * @return string the cleaned groupname
     */
    public function cleanGroup($group) {
        return $group;
    }

    private function createTeam($email, $password, $name, $additional) {
        // check email uniqueness
        $stmt = $this->helper->getConnection()->prepare(
                'select count(1)
                 from `team`
                 where email = :email
                       and volume_id = :volume_id');
        $stmt->bindParam('email', $email);
        $stmt->bindParam('volume_id', $this->volumeId);
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            msg($this->getLang('existing_email'), -1);
            return false;
        }

        // check name uniqueness
        $stmt = $this->helper->getConnection()->prepare(
                'select count(1)
                 from `team`
                 where volume_id = :volume_id and name = :name');
        $stmt->bindParam('volume_id', $this->volumeId);
        $stmt->bindParam('name', $name);
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            msg($this->getLang('existing_team'), -1);
            return false;
        }

        $stmt = $this->helper->getConnection()->prepare(
                'select max(team_id_volume)
                 from `team`
                 where volume_id = :volume_id');
        $stmt->bindParam('volume_id', $this->volumeId);
        $stmt->execute();
        $teamIdVolume = $stmt->fetchColumn() + 1;

        // store team -- preprocess values
        $hash = $this->hash($password);
        list($teamData, $members) = self::splitMemberData($additional, array(
                    'volume_id' => $this->volumeId,
                    'name' => $name,
                    'email' => $email,
                    'password' => $hash,
                    'team_id_volume' => $teamIdVolume,
                    'state' => self::STATE_REGISTERED,
        ));

        // store team -- store team
        if (!$this->helper->insert('team', $teamData)) {
            msg('DB error', -1);
            return false;
        }
        $teamId = $this->helper->getConnection()->lastInsertId();

        // store team -- store members
        $memberIndicator = $this->getConf('member_indicator');
        foreach ($members as $memberData) {
            if (trim($memberData[$memberIndicator]) == '') {
                continue;
            }
            $memberData['team_id'] = $teamId;
            if (!$this->helper->insert('player', $memberData)) {
                return false;
            }
        }

        $this->lastCreatedUser = $this->volumeId . self::LOGIN_SEPARATOR . $teamIdVolume;
        return true;
    }

    private function updateTeam($user, $changes, $additional) {
        list($volumeId, $teamIdVolume) = $this->parseUsername($user);

        // login
        if (isset($changes['mail'])) {
            $mail = $changes['mail'];

            // check uniqueness
            $stmt = $this->helper->getConnection()->prepare(
                    'select count(1) from `team`
                     where email = :email
                           and not (volume_id = :volume_id
                                    and team_id_volume :team_id_volume)');
            $stmt->bindParam('email', $mail);
            $stmt->bindParam('volume_id', $volumeId);
            $stmt->bindParam('team_id_volume', $teamId);

            $stmt->execute();
            if ($stmt->fetchColumn() > 0) {
                msg($this->getLang('existing_email', -1));
                return false;
            }
            $loginData = array('email' => $mail);
        } else {
            $loginData = array();
        }

        // store login
        if (isset($changes['pass'])) {
            $loginData['password'] = $this->hash($changes['pass']);
        }

        $stmt = $this->helper->getConnection()->prepare(
                'select team_id from `team`
                 where volume_id = :volume_id and team_id_volume = :team_id_volume');
        $stmt->bindParam('volume_id', $volumeId);
        $stmt->bindParam('team_id_volume', $teamIdVolume);
        $stmt->execute();
        $teamId = $stmt->fetchColumn();

        // store team -- preprocess values
        list($teamData, $members) = self::splitMemberData($additional);
        $teamData = array_merge($teamData, $loginData);

        // store team -- store team
        if (!$this->helper->update('team', array('team_id' => $teamId), $teamData)) {
            return null;
        }

        // store team -- store members
        $this->helper->delete('player', array('team_id' => $teamId));
        $memberIndicator = $this->getConf('member_indicator');
        foreach ($members as $memberData) {
            if (trim($memberData[$memberIndicator]) == '') {
                continue;
            }
            $memberData['team_id'] = $teamId;
            if (!$this->helper->insert('player', $memberData)) {
                return null;
            }
        }

        return true;
    }

    private static function splitMemberData($data, $originalTeamData = array()) {
        $members = array();
        $teamData = $originalTeamData;

        foreach ($data as $key => $value) {
            $parts = split('_', $key);
            $mKey = $parts[count($parts) - 1];
            $pKey = implode('_', array_slice($parts, 0, -1));
            if (is_numeric($mKey)) {
                if (!isset($members[$mKey])) {
                    $members[$mKey] = array();
                }
                $members[$mKey][$pKey] = $value;
            } else {
                if ($pKey != '') {
                    $key = $pKey . '_' . $mKey;
                } else {
                    $key = $mKey;
                }
                $teamData[$key] = $value;
            }
        }

        return array($teamData, $members);
    }

    public function parseUsername($user) {
        if (strstr($user, self::LOGIN_SEPARATOR) !== false) {
            return explode(self::LOGIN_SEPARATOR, $user);
        } else {
            return array($this->volumeId, $user);
        }
    }

    /*
     * The password hashing functions are by David Grudl, originally part of
     * Nette Framework.
     * https://github.com/nette/security/blob/master/src/Security/Passwords.php
     */

    /**
     * Computes salted password hash.
     * @param string
     * @param array with cost (4-31), salt (22 chars)
     * @return string 60 chars long
     */
    private static function hash($password, array $options = NULL) {
        $cost = isset($options['cost']) ? (int) $options['cost'] : self::BCRYPT_COST;
        $salt = isset($options['salt']) ? (string) $options['salt'] : self::randomSalt(22);
        if (PHP_VERSION_ID < 50307) {
            throw new InvalidArgumentException(__METHOD__ . ' requires PHP >= 5.3.7.', -1);
        } elseif (($len = strlen($salt)) < 22) {
            throw new InvalidArgumentException("Salt must be 22 characters long, $len given.");
        } elseif ($cost < 4 || $cost > 31) {
            throw new InvalidArgumentException("Cost must be in range 4-31, $cost given.");
        }
        $hash = crypt($password, '$2y$' . ($cost < 10 ? 0 : '') . $cost . '$' . $salt);
        if (strlen($hash) < 60) {
            throw new InvalidArgumentException('Hash returned by crypt is invalid.');
        }
        return $hash;
    }

    /**
     * Verifies that a password matches a hash.
     * @return bool
     */
    private static function verify($password, $hash) {
        return preg_match('#^\$2y\$(?P<cost>\d\d)\$(?P<salt>.{22})#', $hash, $m)
                && $m['cost'] >= 4
                && $m['cost'] <= 31 && self::hash($password, $m) === $hash;
    }

    private static function randomSalt($length) {
        static $universum = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $max = strlen($universum);
        $result = '';
        for ($l = 0; $l < $length; ++$l) {
            $result .= $universum{rand(0, $max - 1)};
        }
        return $result;
    }

}

// vim:ts=4:sw=4:et:
