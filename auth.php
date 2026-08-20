<?php

if (session_id() == '') {
    session_start();
}

require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

function require_login()
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}

function has_role($role)
{
    if (!isset($_SESSION['role_name'])) {
        return false;
    }

    return $_SESSION['role_name'] == $role;
}

function require_roles($roles)
{
    require_login();

    if (!in_array($_SESSION['role_name'], $roles)) {
        header('HTTP/1.1 403 Forbidden');
        echo 'Access denied.';
        exit;
    }
}

?>
