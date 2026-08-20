<?php

session_start();

require_once 'config.php';
require_once 'auth.php';
require_once 'audit.php';

require_login();

$username = $_SESSION['username'];
$role     = $_SESSION['role_name'];
$user_id  = (int)$_SESSION['user_id'];

$added = array();
$skipped = array();


/*
 * =========================================================
 * COLLECT REQUESTED DEVICE IDS
 * =========================================================
 *
 * Accepts either:
 *   send_device.php?id=5
 *   send_device.php?ids=5,8,12
 */

$requested_ids = array();

if (
    isset($_GET['id']) &&
    ctype_digit($_GET['id'])
) {

    $requested_ids[] = (int)$_GET['id'];

} elseif (isset($_GET['ids'])) {

    $parts = explode(',', $_GET['ids']);

    foreach ($parts as $part) {

        $part = trim($part);

        if (ctype_digit($part)) {

            $requested_ids[] = (int)$part;
        }
    }
}

$requested_ids = array_unique($requested_ids);


/*
 * =========================================================
 * ADD EACH DEVICE TO THE SEND CART
 * =========================================================
 */

if (count($requested_ids) == 0) {

    $skipped[] = array(
        'name' => '—',
        'reason' => 'No device was selected.'
    );

} else {

    foreach ($requested_ids as $device_id) {

        $query = "
            SELECT *
            FROM devices
            WHERE id = $device_id
            AND status = 'Available'
            LIMIT 1
        ";

        $result = mysql_query(
            $query,
            $conn
        );

        if (
            !$result ||
            mysql_num_rows($result) == 0
        ) {

            $skipped[] = array(
                'name' => 'Device #' . $device_id,
                'reason' => 'Not available in inventory.'
            );

            continue;
        }

        $device = mysql_fetch_assoc($result);

        $sn_safe = mysql_real_escape_string(
            $device['sn'],
            $conn
        );


        /*
         * Skip if already in this user's send cart.
         */

        $check_query = "
            SELECT id
            FROM send_cart
            WHERE sn = '$sn_safe'
            AND created_by = $user_id
            LIMIT 1
        ";

        $check_result = mysql_query(
            $check_query,
            $conn
        );

        if (
            $check_result &&
            mysql_num_rows($check_result) > 0
        ) {

            $skipped[] = array(
                'name' => $device['name'],
                'reason' => 'Already in your send cart.'
            );

            continue;
        }


        $name_safe = mysql_real_escape_string(
            $device['name'],
            $conn
        );

        $mac_safe = mysql_real_escape_string(
            $device['mac'],
            $conn
        );

        $source_safe = mysql_real_escape_string(
            $device['source'],
            $conn
        );

        $quantity = (int)$device['quantity'];


        $insert_query = "
            INSERT INTO send_cart
            (
                device_id,
                source,
                name,
                sn,
                mac,
                quantity,
                created_by,
                created_at
            )
            VALUES
            (
                $device_id,
                '$source_safe',
                '$name_safe',
                '$sn_safe',
                '$mac_safe',
                $quantity,
                $user_id,
                NOW()
            )
        ";

        $insert_result = mysql_query(
            $insert_query,
            $conn
        );

        if (!$insert_result) {

            $skipped[] = array(
                'name' => $device['name'],
                'reason' => 'Could not be added (database error).'
            );

            continue;
        }


        audit_log_action(
            'ADD_TO_SEND_CART',
            $device_id,
            $device['sn'],
            'Device added to send cart',
            '',
            json_encode($device)
        );


        $added[] = $device['name'];
    }
}


/*
 * =========================================================
 * IF EVERYTHING WAS ADDED CLEANLY, GO STRAIGHT TO THE CART
 * =========================================================
 */

if (
    count($added) > 0 &&
    count($skipped) == 0
) {

    header('Location: send.php');
    exit;
}

?>
<!DOCTYPE html>
<html>

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Send Device - Wifonic Hardware</title>

<style>

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
    min-height: 100%;
}

body {

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background:
        linear-gradient(
            135deg,
            #74ebd5,
            #acb6e5
        );

    min-height: 100vh;

    padding: 40px 20px;

    color: #333;
}


.page {

    max-width: 700px;

    margin: 0 auto;
}


.glass-card {

    background:
        rgba(255, 255, 255, 0.28);

    border:
        1px solid
        rgba(255, 255, 255, 0.45);

    box-shadow:
        0 20px 50px
        rgba(0, 0, 0, 0.15);

    backdrop-filter:
        blur(18px);

    -webkit-backdrop-filter:
        blur(18px);

    border-radius: 20px;

    padding: 35px;
}


.title {

    font-size: 24px;

    font-weight: bold;

    color: #ffffff;

    margin-bottom: 20px;
}


.result-list {

    list-style: none;

    margin: 0 0 20px;

    padding: 0;
}


.result-item {

    padding: 12px 14px;

    border-radius: 10px;

    margin-bottom: 8px;

    font-size: 14px;
}


.result-item.ok {

    background:
        rgba(0, 180, 100, 0.18);

    color: #ffffff;
}


.result-item.fail {

    background:
        rgba(255, 80, 80, 0.20);

    color: #ffffff;
}


.result-item .reason {

    display: block;

    font-size: 12px;

    opacity: 0.85;

    margin-top: 3px;
}


.actions {

    display: flex;

    gap: 12px;

    flex-wrap: wrap;
}


.button {

    text-decoration: none;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    border: none;

    cursor: pointer;

    border-radius: 10px;

    padding: 12px 22px;

    font-size: 14px;

    font-weight: bold;
}


.cancel {

    background:
        rgba(255,255,255,0.30);

    color: #ffffff;
}


.submit {

    background:
        linear-gradient(
            to right,
            #74ebd5,
            #acb6e5
        );

    color: #ffffff;
}

</style>

</head>

<body>

<div class="page">

    <div class="glass-card">

        <div class="title">
            Send Cart Update
        </div>

        <ul class="result-list">

            <?php foreach ($added as $name) { ?>

                <li class="result-item ok">
                    <?php
                    echo htmlspecialchars(
                        $name,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                    — added to send cart
                </li>

            <?php } ?>

            <?php foreach ($skipped as $item) { ?>

                <li class="result-item fail">
                    <?php
                    echo htmlspecialchars(
                        $item['name'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                    <span class="reason">
                        <?php
                        echo htmlspecialchars(
                            $item['reason'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </span>
                </li>

            <?php } ?>

        </ul>

        <div class="actions">

            <a
                href="inventory.php"
                class="button cancel"
            >
                Back to Inventory
            </a>

            <a
                href="send.php"
                class="button submit"
            >
                Go to Send Cart
            </a>

        </div>

    </div>

</div>

</body>

</html>
