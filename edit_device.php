<?php

session_start();

require_once 'config.php';
require_once 'auth.php';
require_once 'audit.php';

require_login();

$error = '';
$success = '';

$device = null;
$sales_history = array();
$sends_history = array();

$name = '';
$sn = '';
$mac = '';
$quantity = '1';
$source = '';
$purchase_date = '';
$purchased_from = '';
$purchase_price = '';

$allowed_sources = array(
    'Purchased',
    'Office',
    'Replaced',
    'Terminated Client',
    'Repaired'
);


/*
 * =========================================================
 * LOAD DEVICE & HISTORY
 * =========================================================
 */

if (
    !isset($_GET['id']) ||
    !ctype_digit($_GET['id'])
) {

    $error = 'No device selected.';

} else {

    $device_id = (int)$_GET['id'];

    // Load device
    $query = "
        SELECT *
        FROM devices
        WHERE id = $device_id
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

        $error = 'Device not found.';

    } else {

        $device = mysql_fetch_assoc($result);

        $name = $device['name'];
        $sn = $device['sn'];
        $mac = $device['mac'];
        $quantity = $device['quantity'];
        $source = $device['source'];
        $purchase_date = $device['purchase_date'];
        $purchased_from = $device['purchased_from'];
        $purchase_price = $device['purchase_price'];

        // Load sales history
        $sales_query = "
            SELECT *
            FROM device_sales
            WHERE device_id = $device_id
            ORDER BY sale_date DESC, created_at DESC
        ";
        $sales_result = mysql_query($sales_query, $conn);
        if ($sales_result) {
            while ($row = mysql_fetch_assoc($sales_result)) {
                $sales_history[] = $row;
            }
        }

        // Load sends history
        $sends_query = "
            SELECT *
            FROM device_sends
            WHERE device_id = $device_id
            ORDER BY sent_date DESC, created_at DESC
        ";
        $sends_result = mysql_query($sends_query, $conn);
        if ($sends_result) {
            while ($row = mysql_fetch_assoc($sends_result)) {
                $sends_history[] = $row;
            }
        }
    }
}


/*
 * =========================================================
 * UPDATE DEVICE
 * =========================================================
 */

if (
    $device !== null &&
    $_SERVER['REQUEST_METHOD'] == 'POST'
) {

    $device_id = (int)$_GET['id'];

    $name = isset($_POST['name'])
        ? trim($_POST['name'])
        : '';

    $sn = isset($_POST['sn'])
        ? trim($_POST['sn'])
        : '';

    $mac = isset($_POST['mac'])
        ? trim($_POST['mac'])
        : '';

    $quantity = isset($_POST['quantity'])
        ? (int)$_POST['quantity']
        : 0;

    $source = isset($_POST['source'])
        ? trim($_POST['source'])
        : '';

    $purchase_date = isset($_POST['purchase_date'])
        ? trim($_POST['purchase_date'])
        : '';

    $purchased_from = isset($_POST['purchased_from'])
        ? trim($_POST['purchased_from'])
        : '';

    $purchase_price = isset($_POST['purchase_price'])
        ? trim($_POST['purchase_price'])
        : '';

    // Handle sale history updates (for specific sale records)
    if (isset($_POST['sale_updates']) && is_array($_POST['sale_updates'])) {
        foreach ($_POST['sale_updates'] as $sale_id => $data) {
            $sale_id = (int)$sale_id;
            $sold_to = isset($data['sold_to']) ? trim($data['sold_to']) : '';
            $selling_price = isset($data['selling_price']) ? trim($data['selling_price']) : '';
            $sale_date = isset($data['sale_date']) ? trim($data['sale_date']) : '';
            $property_name = isset($data['property_name']) ? trim($data['property_name']) : '';

            if ($sold_to != '') {
                $update_sql = "UPDATE device_sales SET 
                    sold_to = '" . mysql_real_escape_string($sold_to, $conn) . "',
                    selling_price = '" . mysql_real_escape_string($selling_price, $conn) . "',
                    sale_date = '" . mysql_real_escape_string($sale_date, $conn) . "',
                    property_name = '" . mysql_real_escape_string($property_name, $conn) . "'
                    WHERE id = $sale_id AND device_id = $device_id";
                mysql_query($update_sql, $conn);
            }
        }
    }

    // Handle send history updates (for specific send records)
    if (isset($_POST['send_updates']) && is_array($_POST['send_updates'])) {
        foreach ($_POST['send_updates'] as $send_id => $data) {
            $send_id = (int)$send_id;
            $sent_to = isset($data['sent_to']) ? trim($data['sent_to']) : '';
            $sent_date = isset($data['sent_date']) ? trim($data['sent_date']) : '';
            $property_name = isset($data['property_name']) ? trim($data['property_name']) : '';

            if ($sent_to != '') {
                $update_sql = "UPDATE device_sends SET 
                    sent_to = '" . mysql_real_escape_string($sent_to, $conn) . "',
                    sent_date = '" . mysql_real_escape_string($sent_date, $conn) . "',
                    property_name = '" . mysql_real_escape_string($property_name, $conn) . "'
                    WHERE id = $send_id AND device_id = $device_id";
                mysql_query($update_sql, $conn);
            }
        }
    }

    /*
     * Validation
     */

    if ($name == '') {

        $error = 'Device name is required.';

    } elseif ($sn == '') {

        $error = 'Serial number is required.';

    } elseif ($quantity <= 0) {

        $error = 'Quantity must be greater than 0.';

    } elseif (!in_array($source, $allowed_sources)) {

        $error = 'Invalid device source.';
    }


    /*
     * Purchase price is optional.
     */

    if ($error == '') {

        if ($purchase_price !== '') {

            if (!is_numeric($purchase_price)) {

                $error = 'Purchase price must be a valid number.';

            } elseif ((float)$purchase_price < 0) {

                $error = 'Purchase price cannot be negative.';
            }
        }
    }


    /*
     * Check duplicate SN on another device.
     */

    if ($error == '') {

        $sn_safe = mysql_real_escape_string(
            $sn,
            $conn
        );

        $check_query = "
            SELECT id
            FROM devices
            WHERE sn = '$sn_safe'
            AND id != $device_id
            LIMIT 1
        ";

        $check_result = mysql_query(
            $check_query,
            $conn
        );

        if (!$check_result) {

            $error = mysql_error($conn);

        } elseif (mysql_num_rows($check_result) > 0) {

            $error = 'Another device with this SN already exists.';
        }
    }


    /*
     * Update device.
     */

    if ($error == '') {

        $name_safe = mysql_real_escape_string(
            $name,
            $conn
        );

        $sn_safe = mysql_real_escape_string(
            $sn,
            $conn
        );

        $mac_safe = mysql_real_escape_string(
            $mac,
            $conn
        );

        $source_safe = mysql_real_escape_string(
            $source,
            $conn
        );

        $purchased_from_safe = mysql_real_escape_string(
            $purchased_from,
            $conn
        );


        /*
         * NULL values
         */

        if ($purchase_date == '') {

            $purchase_date_sql = 'NULL';

        } else {

            $purchase_date_safe =
                mysql_real_escape_string(
                    $purchase_date,
                    $conn
                );

            $purchase_date_sql =
                "'$purchase_date_safe'";
        }


        if ($purchase_price == '') {

            $purchase_price_sql = 'NULL';

        } else {

            $purchase_price_sql =
                "'" .
                mysql_real_escape_string(
                    $purchase_price,
                    $conn
                ) .
                "'";
        }


        $old_data = json_encode($device);


        $query = "
            UPDATE devices
            SET
                name = '$name_safe',
                sn = '$sn_safe',
                mac = '$mac_safe',
                quantity = $quantity,
                source = '$source_safe',
                purchase_date = $purchase_date_sql,
                purchased_from = '$purchased_from_safe',
                purchase_price = $purchase_price_sql
            WHERE id = $device_id
            LIMIT 1
        ";


        $result = mysql_query(
            $query,
            $conn
        );


        if (!$result) {

            $error = mysql_error($conn);

        } else {

            $new_data = json_encode(
                array(
                    'name' => $name,
                    'sn' => $sn,
                    'mac' => $mac,
                    'quantity' => $quantity,
                    'source' => $source,
                    'purchase_date' => $purchase_date,
                    'purchased_from' => $purchased_from,
                    'purchase_price' => $purchase_price
                )
            );


            audit_log_action(
                'EDIT_DEVICE',
                $device_id,
                $sn,
                'Device details updated',
                $old_data,
                $new_data
            );


            $success =
                'Device updated successfully.';


            /*
             * Reload fresh data.
             */

            $reload_query = "
                SELECT *
                FROM devices
                WHERE id = $device_id
                LIMIT 1
            ";

            $reload_result = mysql_query(
                $reload_query,
                $conn
            );

            if (
                $reload_result &&
                mysql_num_rows($reload_result) > 0
            ) {

                $device = mysql_fetch_assoc(
                    $reload_result
                );
            }

            // Reload history
            $sales_query = "SELECT * FROM device_sales WHERE device_id = $device_id ORDER BY sale_date DESC";
            $sales_result = mysql_query($sales_query, $conn);
            $sales_history = array();
            if ($sales_result) {
                while ($row = mysql_fetch_assoc($sales_result)) {
                    $sales_history[] = $row;
                }
            }

            $sends_query = "SELECT * FROM device_sends WHERE device_id = $device_id ORDER BY sent_date DESC";
            $sends_result = mysql_query($sends_query, $conn);
            $sends_history = array();
            if ($sends_result) {
                while ($row = mysql_fetch_assoc($sends_result)) {
                    $sends_history[] = $row;
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html>

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Edit Device - Wifonic Hardware</title>

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

    color: #000000;
}


.page {

    max-width: 1000px;

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


.header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 30px;
}


.title {

    font-size: 28px;

    font-weight: bold;

    color: #000000;
}


.subtitle {

    margin-top: 5px;

    color: #000000;

    font-size: 14px;
}


.back {

    text-decoration: none;

    color: #000000;

    background:
        rgba(255,255,255,0.20);

    border:
        1px solid
        rgba(255,255,255,0.35);

    padding:
        10px 16px;

    border-radius: 10px;
}


.message {

    padding: 13px 15px;

    border-radius: 10px;

    margin-bottom: 20px;

    font-size: 14px;
}


.error {

    background:
        rgba(255, 80, 80, 0.15);

    border:
        1px solid
        rgba(255, 80, 80, 0.30);

    color: #000000;
}


.success {

    background:
        rgba(0, 180, 100, 0.15);

    border:
        1px solid
        rgba(0, 180, 100, 0.30);

    color: #000000;
}


.section-title {

    font-size: 18px;

    font-weight: bold;

    margin:
        25px 0 18px;

    color: #000000;

    border-bottom: 2px solid rgba(0,0,0,0.2);
    padding-bottom: 8px;
}


.history-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.history-table th {
    background: rgba(0,0,0,0.1);
    color: #000000;
    padding: 8px 10px;
    text-align: left;
    font-size: 12px;
    font-weight: bold;
}

.history-table td {
    padding: 8px 10px;
    color: #000000;
    font-size: 13px;
    border-bottom: 1px solid rgba(0,0,0,0.1);
}

.history-table input {
    width: 100%;
    background: rgba(255,255,255,0.7);
    border: 1px solid rgba(0,0,0,0.2);
    color: #000000;
    padding: 5px 8px;
    border-radius: 5px;
    font-size: 12px;
}

.history-table input:focus {
    background: rgba(255,255,255,0.9);
    outline: none;
}

.history-table .empty-message {
    text-align: center;
    color: rgba(0,0,0,0.6);
    padding: 20px;
    font-style: italic;
}


.form-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 20px;
}


.form-group {

    display: flex;

    flex-direction: column;
}


.form-group.full {

    grid-column:
        1 / -1;
}


label {

    font-size: 13px;

    font-weight: bold;

    margin-bottom: 7px;

    color: #000000;
}


input,
select {

    width: 100%;

    height: 45px;

    border: none;

    outline: none;

    border-radius: 10px;

    padding:
        0 13px;

    font-size: 14px;

    background:
        rgba(255,255,255,0.72);

    color: #000000;
}


input:focus,
select:focus {

    box-shadow:
        0 0 0 3px
        rgba(116,235,213,0.30);
}


.required {

    color: #ff0000;
}


.actions {

    margin-top: 30px;

    display: flex;

    justify-content: flex-end;

    gap: 12px;
}


.button {

    border: none;

    cursor: pointer;

    border-radius: 10px;

    padding:
        12px 24px;

    font-size: 14px;

    font-weight: bold;
}


.cancel {

    background:
        rgba(255,255,255,0.30);

    color: #000000;

    text-decoration: none;
}


.submit {

    background:
        linear-gradient(
            to right,
            #74ebd5,
            #acb6e5
        );

    color: #000000;

    box-shadow:
        0 8px 20px
        rgba(0,0,0,0.12);
}


.submit:hover {

    opacity: 0.9;
}


.note {

    margin-top: 8px;

    font-size: 12px;

    color: #000000;
}


.status-note {

    display: inline-flex;

    padding: 6px 10px;

    border-radius: 8px;

    background:
        rgba(255,255,255,0.35);

    font-size: 12px;

    font-weight: bold;

    color: #000000;
}


.history-container {
    margin-top: 10px;
    overflow-x: auto;
}


@media (max-width: 700px) {

    .form-grid {

        grid-template-columns: 1fr;
    }

    .form-group.full {

        grid-column: auto;
    }

    .header {

        align-items: flex-start;

        gap: 15px;

        flex-direction: column;
    }

    .glass-card {

        padding: 25px;
    }

    .history-table input {
        font-size: 11px;
        padding: 4px 6px;
    }
}

</style>

</head>

<body>

<div class="page">

    <div class="glass-card">

        <div class="header">

            <div>

                <div class="title">
                    Edit Device
                </div>

                <div class="subtitle">
                    <?php if ($device !== null) { ?>
                        Update the details for this device in inventory
                    <?php } else { ?>
                        Update device details
                    <?php } ?>
                </div>

            </div>

            <a
                href="dashboard.php"
                class="back"
            >
                Back
            </a>

        </div>


        <?php if ($error != '') { ?>

            <div class="message error">
                <?php
                echo htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
            </div>

        <?php } ?>


        <?php if ($success != '') { ?>

            <div class="message success">
                <?php
                echo htmlspecialchars(
                    $success,
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
            </div>

        <?php } ?>


        <?php if ($device !== null) { ?>

        <form
            method="post"
            action="edit_device.php?id=<?php
                echo (int)$device['id'];
            ?>"
        >


            <div class="section-title">
                Device Information
                &nbsp;
                <span class="status-note">
                    <?php
                    echo htmlspecialchars(
                        $device['status'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                </span>
            </div>


            <div class="form-grid">


                <div class="form-group">

                    <label for="name">
                        Device Name
                        <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?php
                        echo htmlspecialchars(
                            $name,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="sn">
                        Serial Number (SN)
                        <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        id="sn"
                        name="sn"
                        value="<?php
                        echo htmlspecialchars(
                            $sn,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="mac">
                        MAC Address
                    </label>

                    <input
                        type="text"
                        id="mac"
                        name="mac"
                        value="<?php
                        echo htmlspecialchars(
                            $mac,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>"
                    >

                </div>


                <div class="form-group">

                    <label for="quantity">
                        Quantity
                        <span class="required">*</span>
                    </label>

                    <input
                        type="number"
                        id="quantity"
                        name="quantity"
                        min="1"
                        value="<?php
                        echo htmlspecialchars(
                            $quantity,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>"
                        required
                    >

                </div>


                <div class="form-group full">

                    <label for="source">
                        Source
                        <span class="required">*</span>
                    </label>

                    <select
                        id="source"
                        name="source"
                        required
                    >

                        <option value="">
                            Select Source
                        </option>

                        <?php

                        foreach ($allowed_sources as $item) {

                            $selected =
                                ($source == $item)
                                    ? 'selected'
                                    : '';

                            echo '<option value="' .
                                htmlspecialchars(
                                    $item,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) .
                                '" ' .
                                $selected .
                                '>' .
                                htmlspecialchars(
                                    $item,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) .
                                '</option>';
                        }

                        ?>

                    </select>

                </div>

            </div>


            <div class="section-title">
                Purchase Information
            </div>


            <div class="form-grid">


                <div class="form-group">

                    <label for="purchase_date">
                        Purchase Date
                    </label>

                    <input
                        type="date"
                        id="purchase_date"
                        name="purchase_date"
                        value="<?php
                        echo htmlspecialchars(
                            $purchase_date,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>"
                    >

                </div>


                <div class="form-group">

                    <label for="purchased_from">
                        Purchased From
                    </label>

                    <input
                        type="text"
                        id="purchased_from"
                        name="purchased_from"
                        value="<?php
                        echo htmlspecialchars(
                            $purchased_from,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>"
                    >

                </div>


                <div class="form-group">

                    <label for="purchase_price">
                        Purchase Price
                    </label>

                    <input
                        type="number"
                        id="purchase_price"
                        name="purchase_price"
                        min="0"
                        step="0.01"
                        placeholder="₹ 0.00"
                        value="<?php
                        echo htmlspecialchars(
                            $purchase_price,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>"
                    >

                    <div class="note">
                        Optional
                    </div>

                </div>

            </div>


            <!-- Sales History Section -->
            <div class="section-title">
                Sale History
                <span style="font-size:12px;font-weight:normal;margin-left:10px;color:#000000;">
                    (Edit sale details directly below)
                </span>
            </div>

            <?php if (count($sales_history) > 0) { ?>
                <div class="history-container">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>SN</th>
                                <th>Sold To</th>
                                <th>Selling Price</th>
                                <th>Sale Date</th>
                                <th>Property</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales_history as $sale) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($sale['sn'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <input type="text" 
                                               name="sale_updates[<?php echo $sale['id']; ?>][sold_to]" 
                                               value="<?php echo htmlspecialchars($sale['sold_to'], ENT_QUOTES, 'UTF-8'); ?>"
                                               placeholder="Sold to">
                                    </td>
                                    <td>
                                        <input type="number" 
                                               name="sale_updates[<?php echo $sale['id']; ?>][selling_price]" 
                                               value="<?php echo htmlspecialchars($sale['selling_price'], ENT_QUOTES, 'UTF-8'); ?>"
                                               step="0.01"
                                               placeholder="0.00">
                                    </td>
                                    <td>
                                        <input type="date" 
                                               name="sale_updates[<?php echo $sale['id']; ?>][sale_date]" 
                                               value="<?php echo htmlspecialchars($sale['sale_date'], ENT_QUOTES, 'UTF-8'); ?>">
                                    </td>
                                    <td>
                                        <input type="text" 
                                               name="sale_updates[<?php echo $sale['id']; ?>][property_name]" 
                                               value="<?php echo htmlspecialchars(isset($sale['property_name']) ? $sale['property_name'] : '', ENT_QUOTES, 'UTF-8'); ?>"
                                               placeholder="Property">
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                    <div class="note" style="margin-top:8px;">
                        Edit any field and save to update sale records.
                    </div>
                </div>
            <?php } else { ?>
                <div class="note" style="padding:15px;background:rgba(255,255,255,0.3);border-radius:8px;color:#000000;">
                    No sale history found for this device.
                </div>
            <?php } ?>


            <!-- Sends History Section -->
            <div class="section-title">
                Send History
                <span style="font-size:12px;font-weight:normal;margin-left:10px;color:#000000;">
                    (Edit send details directly below)
                </span>
            </div>

            <?php if (count($sends_history) > 0) { ?>
                <div class="history-container">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>SN</th>
                                <th>Sent To</th>
                                <th>Sent Date</th>
                                <th>Property</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sends_history as $send) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($send['sn'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <input type="text" 
                                               name="send_updates[<?php echo $send['id']; ?>][sent_to]" 
                                               value="<?php echo htmlspecialchars($send['sent_to'], ENT_QUOTES, 'UTF-8'); ?>"
                                               placeholder="Sent to">
                                    </td>
                                    <td>
                                        <input type="date" 
                                               name="send_updates[<?php echo $send['id']; ?>][sent_date]" 
                                               value="<?php echo htmlspecialchars($send['sent_date'], ENT_QUOTES, 'UTF-8'); ?>">
                                    </td>
                                    <td>
                                        <input type="text" 
                                               name="send_updates[<?php echo $send['id']; ?>][property_name]" 
                                               value="<?php echo htmlspecialchars(isset($send['property_name']) ? $send['property_name'] : '', ENT_QUOTES, 'UTF-8'); ?>"
                                               placeholder="Property">
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                    <div class="note" style="margin-top:8px;">
                        Edit any field and save to update send records.
                    </div>
                </div>
            <?php } else { ?>
                <div class="note" style="padding:15px;background:rgba(255,255,255,0.3);border-radius:8px;color:#000000;">
                    No send history found for this device.
                </div>
            <?php } ?>


            <div class="actions">

                <a
                    href="device_details.php?id=<?php
                        echo (int)$device['id'];
                    ?>"
                    class="button cancel"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="button submit"
                >
                    Save Changes
                </button>

            </div>


        </form>

        <?php } ?>

    </div>

</div>

</body>

</html>
