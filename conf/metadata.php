<?php
/**
 * Options for the gameteam plugin
 *
 * @author Michal Koutný <xm.koutny@gmail.com>
 */


$meta['mysql_host'] = array('string');
$meta['mysql_user'] = array('string');
$meta['mysql_password'] = array('password');
$meta['mysql_database'] = array('string');
$meta['show_payment'] = array('onoff');
$meta['show_upload'] = array('onoff');
$meta['superuser_login'] = array('string');
$meta['superuser_password'] = array('string');
$meta['teamfields'] = array('');
$meta['volume_id'] = array('numeric');
$meta['vs_prefix'] = array('string');
$meta['vs_length'] = array('numeric');