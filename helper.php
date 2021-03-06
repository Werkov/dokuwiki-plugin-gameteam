<?php

/**
 * DokuWiki Plugin gameteam (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michal Koutný <xm.koutny@gmail.com>
 */
// must be run within Dokuwiki
if (!defined('DOKU_INC'))
    die();

class helper_plugin_gameteam extends Dokuwiki_Plugin {

    const LOGIN_SEPARATOR = '_';

    /**
     * @var PDO
     */
    private $connection;

    public function __construct() {
        $this->connectToDatabase();
    }

    public function getConnection() {
        return $this->connection;
    }

    public function insert($table, $data) {
        $stmt = $this->getConnection()->prepare('insert into `' . $table . '` (' . implode(', ', array_keys($data)) . ') values(:' . implode(', :', array_keys($data)) . ')');

        foreach ($data as $key => $value) {
            $key = ':' . $key;
            $stmt->bindValue($key, $value);
        }
        $res = $stmt->execute();
        return $res;
    }

    public function update($table, $where, $data) {
        $pairs = array_map(function($it) {
                    return "`$it` = :$it";
                }, array_keys($data));
        $wherePairs = array_map(function($it) {
                    return "`$it` = :$it";
                }, array_keys($where));
        $stmt = $this->getConnection()->prepare('update `' . $table . '` set ' . implode(', ', $pairs) . ' where ' . implode(' AND ', $wherePairs));

        foreach ($data as $key => $value) {
            $key = ':' . $key;
            $stmt->bindValue($key, $value);
        }

        foreach ($where as $key => $value) {
            $key = ':' . $key;
            $stmt->bindValue($key, $value);
        }

        $res = $stmt->execute();
        return $res;
    }

    public function delete($table, $where) {
        $wherePairs = array_map(function($it) {
                    return "`$it` = :$it";
                }, array_keys($where));
        $stmt = $this->getConnection()->prepare('delete from `' . $table . '`  where ' . implode(' AND ', $wherePairs));

        foreach ($where as $key => $value) {
            $key = ':' . $key;
            $stmt->bindValue($key, $value);
        }

        $res = $stmt->execute();
        return $res;
    }

    public function select($table, $where, $fields = null) {
        $wherePairs = array_map(function($it) {
                    return "`$it` = :$it";
                }, array_keys($where));

        $single = false;
        if (is_array($fields)) {
            $select = '`' . implode('`, `', $fields) . `'`;
        } else if ($fields) {
            $select = "`$fields`";
            $single = true;
        } else {
            $select = '*';
        }
        $stmt = $this->getConnection()->prepare('select ' . $select . ' from `' . $table . '`  where ' . implode(' AND ', $wherePairs));

        foreach ($where as $key => $value) {
            $key = ':' . $key;
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        if ($single) {
            return $stmt->fetchColumn();
        } else {
            return $stmt->fetch();
        }
    }

    public function parseUsername($user, $defaultVolumeId) {
        if (strstr($user, self::LOGIN_SEPARATOR) !== false) {
            return explode(self::LOGIN_SEPARATOR, $user);
        } else {
            return array($defaultVolumeId, $user);
        }
    }

    public function decorateUsername($rawUser, $volumeId) {
        if (strstr($rawUser, self::LOGIN_SEPARATOR) === false) {
            return $volumeId . self::LOGIN_SEPARATOR . $rawUser;
        }
    }

    private function connectToDatabase() {
        $dsn = 'mysql:host=' . $this->getConf('mysql_host') . ';dbname=' . $this->getConf('mysql_database');
        $username = $this->getConf('mysql_user');
        $passwd = $this->getConf('mysql_password');
        $options = array(
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
        );
        try {
            $this->connection = new PDO($dsn, $username, $passwd, $options);
            return true;
        } catch (PDOException $e) {
            msg($e->getMessage(), -1);
            return false;
        }
    }

}

// vim:ts=4:sw=4:et: