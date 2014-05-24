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

    /**
     * @var helper_plugin_gameteam
     */
    private $helper;
    private $lastCreatedUser = null;

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct(); // for compatibility
        // FIXME set capabilities accordingly
        $this->cando['addUser'] = true; // can Users be created?
        //$this->cando['delUser']     = false; // can Users be deleted?
        //$this->cando['modLogin']    = false; // can login names be changed?
        $this->cando['modPass'] = true; // can passwords be changed?
        //$this->cando['modName']     = false; // can real names be changed?
        $this->cando['modMail'] = true; // can emails be changed?
        //$this->cando['modGroups']   = false; // can groups be changed?
        //$this->cando['getUsers']    = false; // can a (filtered) list of users be retrieved?
        //$this->cando['getUserCount']= false; // can the number of users be retrieved?
        //$this->cando['getGroups']   = false; // can a list of available groups be retrieved?
        //$this->cando['external']    = false; // does the module do external auth checking?
        //$this->cando['logout']      = true; // can the user logout again? (eg. not possible with HTTP auth)
        // FIXME intialize your auth system and set success to true, if successful
        $this->helper = $this->loadHelper('gameteam');
        $this->success = true;
    }

    /**
     * Log off the current user [ OPTIONAL ]
     */
    //public function logOff() {
    //}

    /**
     * Do all authentication [ OPTIONAL ]
     *
     * @param   string  $user    Username
     * @param   string  $pass    Cleartext Password
     * @param   bool    $sticky  Cookie should not expire
     * @return  bool             true on successful auth
     */
    //public function trustExternal($user, $pass, $sticky = false) {
    /* some example:

      global $USERINFO;
      global $conf;
      $sticky ? $sticky = true : $sticky = false; //sanity check

      // do the checking here

      // set the globals if authed
      $USERINFO['name'] = 'FIXME';
      $USERINFO['mail'] = 'FIXME';
      $USERINFO['grps'] = array('FIXME');
      $_SERVER['REMOTE_USER'] = $user;
      $_SESSION[DOKU_COOKIE]['auth']['user'] = $user;
      $_SESSION[DOKU_COOKIE]['auth']['pass'] = $pass;
      $_SESSION[DOKU_COOKIE]['auth']['info'] = $USERINFO;
      return true;

     */
    //}

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
        $stmt = $this->helper->getConnection()->prepare('
            select count(1)
            from login l 
            where (l.login_id = :login_id or l.login_name = :login_name) and l.password = :password');

        $stmt->bindValue('login_id', $user);
        $stmt->bindValue('login_name', $user);
        $stmt->bindValue('password', self::hashPassword($pass));

        $stmt->execute();

        return $stmt->fetchColumn() > 0;
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
        $stmt = $this->helper->getConnection()->prepare('
            select t.name, l.email
            from login l 
            left join team t on t.login_id = l.login_id and t.volume_id = :volume_id
            where l.login_id = :login_id or l.login_name = :login_name');

        $stmt->bindValue('login_id', $user);
        $stmt->bindValue('login_name', $user);
        $stmt->bindValue('volume_id', $this->getConf('volume_id'));

        $stmt->execute();
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }
        return array(
            'name' => $row['name'],
            'mail' => $row['email'],
            'grps' => ($user == 'Bazinga' ? array('admin') : array()),
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
        $res = $this->createLogin($user, $pass, $mail);

        if ($res !== false && $res !== null) {
            $this->lastCreatedUser = $res;
            $res = $this->createTeam($res, $name, $additional);
        }
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
    public function modifyUser($user, $changes) {
        // FIXME implement
        return false;
    }

    /**
     * Delete one or more users [implement only where required/possible]
     *
     * Set delUser capability when implemented
     *
     * @param   array  $users
     * @return  int    number of users deleted
     */
    //public function deleteUsers($users) {
    // FIXME implement
    //    return false;
    //}

    /**
     * Bulk retrieval of user data [implement only where required/possible]
     *
     * Set getUsers capability when implemented
     *
     * @param   int   $start     index of first user to be returned
     * @param   int   $limit     max number of users to be returned
     * @param   array $filter    array of field/pattern pairs, null for no filter
     * @return  array list of userinfo (refer getUserData for internal userinfo details)
     */
    //public function retrieveUsers($start = 0, $limit = -1, $filter = null) {
    // FIXME implement
    //    return array();
    //}

    /**
     * Return a count of the number of user which meet $filter criteria
     * [should be implemented whenever retrieveUsers is implemented]
     *
     * Set getUserCount capability when implemented
     *
     * @param  array $filter array of field/pattern pairs, empty array for no filter
     * @return int
     */
    //public function getUserCount($filter = array()) {
    // FIXME implement
    //    return 0;
    //}

    /**
     * Define a group [implement only where required/possible]
     *
     * Set addGroup capability when implemented
     *
     * @param   string $group
     * @return  bool
     */
    //public function addGroup($group) {
    // FIXME implement
    //    return false;
    //}

    /**
     * Retrieve groups [implement only where required/possible]
     *
     * Set getGroups capability when implemented
     *
     * @param   int $start
     * @param   int $limit
     * @return  array
     */
    //public function retrieveGroups($start = 0, $limit = 0) {
    // FIXME implement
    //    return array();
    //}

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
        if ($this->lastCreatedUser !== null && $user == self::LOGIN_PLACEHOLDER) {
            return $this->lastCreatedUser;
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

    /**
     * Check Session Cache validity [implement only where required/possible]
     *
     * DokuWiki caches user info in the user's session for the timespan defined
     * in $conf['auth_security_timeout'].
     *
     * This makes sure slow authentication backends do not slow down DokuWiki.
     * This also means that changes to the user database will not be reflected
     * on currently logged in users.
     *
     * To accommodate for this, the user manager plugin will touch a reference
     * file whenever a change is submitted. This function compares the filetime
     * of this reference file with the time stored in the session.
     *
     * This reference file mechanism does not reflect changes done directly in
     * the backend's database through other means than the user manager plugin.
     *
     * Fast backends might want to return always false, to force rechecks on
     * each page load. Others might want to use their own checking here. If
     * unsure, do not override.
     *
     * @param  string $user - The username
     * @return bool
     */
    //public function useSessionCache($user) {
    // FIXME implement
    //}

    private function createLogin($user, $pass, $mail) {
        // check uniqueness
        $stmt = $this->helper->getConnection()->prepare('select count(1) from `login` where email = :email');
        $stmt->bindParam('email', $mail);
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            msg($this->getLang('existing_email', -1));
            return false;
        }

        // store user
        if (!$this->helper->insert('login', array(
                    'email' => $mail,
                    'password' => self::hashPassword($pass)
                ))) {
            return null;
        }
        $e = $this->helper->getConnection()->errorCode();
        $err = $this->helper->getConnection()->errorInfo();
        return $this->helper->getConnection()->lastInsertId();
    }

    private function createTeam($loginId, $name, $additional) {
        $volumeId = $this->getConf('volume_id');

        // check uniqueness
        $stmt = $this->helper->getConnection()->prepare('select count(1) from `team` where volume_id = :volume_id and name = :name');
        $stmt->bindParam('volume_id', $volumeId);
        $stmt->bindParam('name', $name);
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            msg($this->getLang('existing_team', -1));
            return false;
        }

        $stmt = $this->helper->getConnection()->prepare('select count(1)+10 from `login` where login_id = :login_id');
        $stmt->bindParam('login_id', $loginId);
        $stmt->execute();
        $cunt = $stmt->fetchColumn();

        $stmt = $this->helper->getConnection()->prepare('select count(1)+10 from `volume` where volume_id = :volume_id');
        $stmt->bindParam('volume_id', $volumeId);
        $stmt->execute();
        $cunt2 = $stmt->fetchColumn();

        $stmt = $this->helper->getConnection()->prepare('select max(registration_order) from `team` where volume_id = :volume_id');
        $stmt->bindParam('volume_id', $volumeId);
        $stmt->execute();
        $registrationOrder = $stmt->fetchColumn() + 1;

        // store team -- preprocess values
        $members = array();
        $teamData = array(
            'volume_id' => $volumeId,
            'name' => $name,
            'login_id' => $loginId,
            'registration_order' => $registrationOrder,
            'state' => '00', // registered
        );
        foreach ($additional as $key => $value) {
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

        // store team -- store team
        if (!$this->helper->insert('team', $teamData)) {
            return null;
        }
        $teamId = $this->helper->getConnection()->lastInsertId();

        // store team -- store members
        foreach ($members as $memberData) {
            $memberData['team_id'] = $teamId;
            if (!$this->helper->insert('player', $memberData)) {
                return null;
            }
        }

        return true;
    }

    private static function hashPassword($password) {
        return sha1($password);
    }

}

// vim:ts=4:sw=4:et: