<?php

session_start();

require_once 'config.php';
require_once 'auth.php';
require_once 'audit.php';

require_login();

$username = $_SESSION['username'];
$role     = $_SESSION['role_name'];
$user_id  = (int)$_SESSION['user_id'];

$error = '';
$devices = array();

$added = array();
$skipped = array();

$showing_results = false;


/*
 * =========================================================
 * COLLECT REQUESTED DEVICE IDS
 * =========================================================
 *
 * Accepts either:
 *   sell_device.php?id=5
 *   sell_device.php?ids=5,8,12
 */

function collect_ids_from_get()
{
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

    return array_unique($requested_ids);
}

function device_display_label($device)
{
    $label = trim(
        (isset($device['brand']) ? $device['brand'] : '') .
        ' ' .
        (isset($device['model']) ? $device['model'] : '')
    );

    if ($label != '') {
        return $label;
    }

    return $device['name'];
}


/*
 * =========================================================
 * STEP 1 (GET): SHOW A PRICE FOR EACH SELECTED DEVICE
 * =========================================================
 */

if ($_SERVER['REQUEST_METHOD'] != 'POST') {

    $requested_ids = collect_ids_from_get();

    if (count($requested_ids) == 0) {

        $error = 'No device was selected.';

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
                $result &&
                mysql_num_rows($result) > 0
            ) {

                $devices[] = mysql_fetch_assoc($result);
            }
        }

        if (count($devices) == 0) {

            $error = 'None of the selected devices are available in inventory.';
        }
    }
}


/*
 * =========================================================
 * STEP 2 (POST): ADD EACH DEVICE TO THE SELL CART
 * =========================================================
 */

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $showing_results = true;

    $device_ids = isset($_POST['device_id'])
        ? $_POST['device_id']
        : array();

    $prices = isset($_POST['selling_price'])
        ? $_POST['selling_price']
        : array();

    if (!is_array($device_ids)) {
        $device_ids = array();
    }

    foreach ($device_ids as $device_id) {

        if (!ctype_digit((string)$device_id)) {
            continue;
        }

        $device_id = (int)$device_id;

        $price = isset($prices[$device_id])
            ? trim($prices[$device_id])
            : '';


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


        if ($price == '' || !is_numeric($price) || (float)$price < 0) {

            $skipped[] = array(
                'name' => device_display_label($device),
                'reason' => 'A valid selling price is required.'
            );

            continue;
        }


        $sn_safe = mysql_real_escape_string(
            $device['sn'],
            $conn
        );


        /*
         * Skip if already in this user's sell cart.
         */

        $check_query = "
            SELECT id
            FROM sell_cart
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
                'name' => device_display_label($device),
                'reason' => 'Already in your sell cart.'
            );

            continue;
        }


        $name_safe = mysql_real_escape_string(
            $device['name'],
            $conn
        );

        $brand_safe = mysql_real_escape_string(
            $device['brand'],
            $conn
        );

        $model_safe = mysql_real_escape_string(
            $device['model'],
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

        $price_safe = mysql_real_escape_string(
            $price,
            $conn
        );

        $quantity = (int)$device['quantity'];


        $insert_query = "
            INSERT INTO sell_cart
            (
                device_id,
                source,
                name,
                brand,
                model,
                sn,
                mac,
                quantity,
                selling_price,
                created_by,
                created_at
            )
            VALUES
            (
                $device_id,
                '$source_safe',
                '$name_safe',
                '$brand_safe',
                '$model_safe',
                '$sn_safe',
                '$mac_safe',
                $quantity,
                '$price_safe',
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
                'name' => device_display_label($device),
                'reason' => 'Could not be added (database error).'
            );

            continue;
        }


        audit_log_action(
            'ADD_TO_SELL_CART',
            $device_id,
            $device['sn'],
            'Device added to sell cart',
            '',
            json_encode(
                array_merge(
                    $device,
                    array('selling_price' => $price)
                )
            )
        );


        $added[] = device_display_label($device);
    }


    if (
        count($added) > 0 &&
        count($skipped) == 0
    ) {

        header('Location: sell.php');
        exit;
    }
}

?>
<!DOCTYPE html>
<html>

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Sell Device - Wifonic Hardware</title>

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

    max-width: 800px;

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

    margin-bottom: 8px;
}


.subtitle {

    color:
        rgba(255, 255, 255, 0.85);

    font-size: 14px;

    margin-bottom: 25px;
}


.message {

    padding: 13px 15px;

    border-radius: 10px;

    margin-bottom: 20px;

    font-size: 14px;

    background:
        rgba(255, 80, 80, 0.15);

    border:
        1px solid
        rgba(255, 80, 80, 0.30);

    color: #ffffff;
}


.device-row {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    background:
        rgba(255,255,255,0.20);

    border-radius: 12px;

    padding: 14px 16px;

    margin-bottom: 12px;

    flex-wrap: wrap;
}


.device-info {

    color: #ffffff;
}


.device-name {

    font-weight: bold;

    font-size: 14px;
}


.device-meta {

    font-size: 12px;

    color:
        rgba(255,255,255,0.80);

    font-family:
        "Courier New",
        monospace;
}


.price-field {

    display: flex;

    align-items: center;

    gap: 8px;
}


.price-field label {

    font-size: 12px;

    font-weight: bold;

    color: #ffffff;
}


.price-field input {

    width: 130px;

    height: 42px;

    border: none;

    outline: none;

    border-radius: 10px;

    padding: 0 12px;

    font-size: 14px;

    background:
        rgba(255,255,255,0.75);

    color: #333;
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

    color: #ffffff;
}


.result-item.ok {

    background:
        rgba(0, 180, 100, 0.18);
}


.result-item.fail {

    background:
        rgba(255, 80, 80, 0.20);
}


.result-item .reason {

    display: block;

    font-size: 12px;

    opacity: 0.85;

    margin-top: 3px;
}


.actions {

    margin-top: 20px;

    display: flex;

    justify-content: flex-end;

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


        <?php if ($showing_results) { ?>


            <div class="title">
                Sell Cart Update
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
                        — added to sell cart
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
                    href="sell.php"
                    class="button submit"
                >
                    Go to Sell Cart
                </a>

            </div>


        <?php } else { ?>


            <div class="title">
                Sell Device
            </div>

            <div class="subtitle">
                Set a selling price for each device below, then add them to your sell cart.
            </div>


            <?php if ($error != '') { ?>

                <div class="message">
                    <?php
                    echo htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                </div>

            <?php } ?>


            <?php if (count($devices) > 0) { ?>

                <form
                    method="post"
                    action="sell_device.php"
                >

                    <?php foreach ($devices as $device) { ?>

                        <div class="device-row">

                            <div class="device-info">

                                <div class="device-name">
                                    <?php
                                    echo htmlspecialchars(
                                        trim($device['brand'] . ' ' . $device['model']) != ''
                                            ? trim($device['brand'] . ' ' . $device['model'])
                                            : $device['name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                </div>

                                <div class="device-meta">
                                    SN: <?php
                                        echo htmlspecialchars(
                                            $device['sn'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                    ?>
                                    &nbsp;•&nbsp;
                                    Qty: <?php echo (int)$device['quantity']; ?>
                                </div>

                            </div>

                            <div class="price-field">

                                <input
                                    type="hidden"
                                    name="device_id[]"
                                    value="<?php echo (int)$device['id']; ?>"
                                >

                                <label for="price_<?php echo (int)$device['id']; ?>">
                                    ₹
                                </label>

                                <input
                                    type="number"
                                    id="price_<?php echo (int)$device['id']; ?>"
                                    name="selling_price[<?php echo (int)$device['id']; ?>]"
                                    min="0"
                                    step="0.01"
                                    placeholder="0.00"
                                    required
                                >

                            </div>

                        </div>

                    <?php } ?>


                    <div class="actions">

                        <a
                            href="inventory.php"
                            class="button cancel"
                        >
                            Cancel
                        </a>

                        <button
                            type="submit"
                            class="button submit"
                        >
                            Add to Sell Cart
                        </button>

                    </div>

                </form>

            <?php } ?>


        <?php } ?>


    </div>

</div>

</body>

</html>
