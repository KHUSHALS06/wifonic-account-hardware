<?php

$db_host = 'localhost';
$db_user = 'account';
$db_pass = 'accountmk2026';
$db_name = 'accounthardware';

$conn = mysql_connect(
    $db_host,
    $db_user,
    $db_pass
);

if (!$conn) {
    die('Database connection failed: ' . mysql_error());
}

if (!mysql_select_db($db_name, $conn)) {
    die('Database selection failed: ' . mysql_error());
}

mysql_query("SET NAMES utf8", $conn);

?>
