<?php

// FILLME
$password = '1234';

define('DOKU_INC', 1);
class DokuWiki_Auth_Plugin {}
include "auth.php";

$hash = auth_plugin_gameteam::hash($password);

echo "$hash\n";
