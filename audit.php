<?php

require_once 'config.php';


/*
 * =========================================================
 * AUDIT LOG
 * =========================================================
 *
 * Usage:
 *
 * audit_log_action(
 *     'ADD_DEVICE',
 *     $device_id,
 *     $sn,
 *     'Device added to inventory',
 *     '',
 *     $new_data
 * );
 *
 */


function audit_log_action(
    $action,
    $device_id = null,
    $sn = null,
    $description = '',
    $old_data = '',
    $new_data = ''
) {

    global $conn;


    /*
     * User information
     */

    $user_id =
        isset($_SESSION['user_id'])
            ? (int)$_SESSION['user_id']
            : null;


    $username =
        isset($_SESSION['username'])
            ? $_SESSION['username']
            : null;


    /*
     * IP address
     */

    $ip_address =
        isset($_SERVER['REMOTE_ADDR'])
            ? $_SERVER['REMOTE_ADDR']
            : '';


    /*
     * Escape values
     */

    $action_safe =
        mysql_real_escape_string(
            $action,
            $conn
        );


    $description_safe =
        mysql_real_escape_string(
            $description,
            $conn
        );


    $old_data_safe =
        mysql_real_escape_string(
            $old_data,
            $conn
        );


    $new_data_safe =
        mysql_real_escape_string(
            $new_data,
            $conn
        );


    $username_safe =
        mysql_real_escape_string(
            $username,
            $conn
        );


    $sn_safe =
        mysql_real_escape_string(
            $sn,
            $conn
        );


    $ip_safe =
        mysql_real_escape_string(
            $ip_address,
            $conn
        );


    /*
     * NULL handling
     */

    if ($user_id === null) {

        $user_id_sql = 'NULL';

    } else {

        $user_id_sql = $user_id;
    }


    if ($device_id === null) {

        $device_id_sql = 'NULL';

    } else {

        $device_id_sql =
            (int)$device_id;
    }


    if ($sn === null || $sn === '') {

        $sn_sql = 'NULL';

    } else {

        $sn_sql =
            "'" . $sn_safe . "'";
    }


    if ($username === null || $username === '') {

        $username_sql = 'NULL';

    } else {

        $username_sql =
            "'" . $username_safe . "'";
    }


    /*
     * Insert audit record
     */

    $query = "
        INSERT INTO audit_log (
            user_id,
            username,
            action,
            device_id,
            sn,
            description,
            old_data,
            new_data,
            ip_address,
            created_at
        )
        VALUES (
            $user_id_sql,
            $username_sql,
            '$action_safe',
            $device_id_sql,
            $sn_sql,
            '$description_safe',
            '$old_data_safe',
            '$new_data_safe',
            '$ip_safe',
            NOW()
        )
    ";


    $result =
        mysql_query(
            $query,
            $conn
        );


    /*
     * Don't break the actual operation
     * if audit logging fails.
     */

    if (!$result) {

        error_log(
            'Audit log failed: ' .
            mysql_error($conn)
        );

        return false;
    }


    return true;
}

?>
