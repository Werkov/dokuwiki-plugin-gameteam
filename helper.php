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