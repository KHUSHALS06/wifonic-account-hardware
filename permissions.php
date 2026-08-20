<?php

require_once 'auth.php';

function can($permission)
{
    if (!isset($_SESSION['role_name'])) {
        return false;
    }

    $role = $_SESSION['role_name'];

    $permissions = array(

        'admin' => array(
            'user.view',
            'user.create',
            'user.edit',
            'user.delete',

            'device.view',
            'device.search',
            'device.create',
            'device.edit',
            'device.delete',

            'device.price',
            'device.invoice',
            'device.documents',
            'device.images',

            'report.view'
        ),

        'manager' => array(
            'device.view',
            'device.search',
            'device.create',
            'device.edit',
            'device.delete',

            'device.price',
            'device.invoice',
            'device.documents',
            'device.images',

            'report.view'
        ),

        'change' => array(
            'device.view',
            'device.search',
            'device.create',
            'device.edit',
            'device.delete',

            'report.view'
        ),

        'viewer' => array(
            'device.view',
            'device.search'
        )

    );

    if (!isset($permissions[$role])) {
        return false;
    }

    return in_array(
        $permission,
        $permissions[$role]
    );
}

function require_permission($permission)
{
    if (!can($permission)) {
        header('HTTP/1.1 403 Forbidden');
        echo 'Access denied.';
        exit;
    }
}

?>
