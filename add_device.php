<?php

session_start();

require_once 'config.php';
require_once 'auth.php';
require_once 'audit.php';

require_login();

$username = $_SESSION['username'];
$role     = $_SESSION['role_name'];

$error = '';
$success = '';

$name = '';
$sn = '';
$mac = '';
$quantity = '1';
$source = '';
$purchase_date = '';
$purchased_from = '';
$purchase_price = '';
$brand_id = 0;
$model_id = 0;
$brand = '';
$model = '';

$brands = array();
$brands_query = "
    SELECT id, name AS brand_name
    FROM brands
    ORDER BY name ASC
";
$brands_result = mysql_query($brands_query, $conn);
if ($brands_result) {
    while ($row = mysql_fetch_assoc($brands_result)) {
        $brands[] = $row;
    }
}

$models = array();
$models_query = "
    SELECT id, brand_id, model_name
    FROM models
    ORDER BY model_name ASC
";
$models_result = mysql_query($models_query, $conn);
if ($models_result) {
    while ($row = mysql_fetch_assoc($models_result)) {
        $models[] = $row;
    }
}

$couriers = array();
$couriers_query = "
    SELECT id, name
    FROM couriers
    ORDER BY name ASC
";
$couriers_result = mysql_query($couriers_query, $conn);
if ($couriers_result) {
    while ($row = mysql_fetch_assoc($couriers_result)) {
        $couriers[] = $row;
    }
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {

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

    $brand_id = isset($_POST['brand_id'])
        ? (int)$_POST['brand_id']
        : 0;

    $model_id = isset($_POST['model_id'])
        ? (int)$_POST['model_id']
        : 0;

    $brand = isset($_POST['brand'])
        ? trim($_POST['brand'])
        : '';

    $model = isset($_POST['model'])
        ? trim($_POST['model'])
        : '';

    $allowed_sources = array(
        'Purchased',
        'Office',
        'Replaced',
        'Terminated Client',
        'Repaired'
    );

    if ($name == '') {

        $error = 'Device name is required.';

    } elseif ($sn == '') {

        $error = 'Serial number is required.';

    } elseif ($quantity <= 0) {

        $error = 'Quantity must be greater than 0.';

    } elseif (!in_array($source, $allowed_sources)) {

        $error = 'Invalid device source.';

    }

    if ($error == '') {

        if ($purchase_price !== '') {

            if (!is_numeric($purchase_price)) {

                $error = 'Purchase price must be a valid number.';

            } elseif ((float)$purchase_price < 0) {

                $error = 'Purchase price cannot be negative.';
            }
        }
    }

    if ($error == '') {

        $sn_safe = mysql_real_escape_string(
            $sn,
            $conn
        );

        $check_query = "
            SELECT id
            FROM devices
            WHERE sn = '$sn_safe'
            LIMIT 1
        ";

        $check_result = mysql_query(
            $check_query,
            $conn
        );

        if (!$check_result) {

            $error = mysql_error($conn);

        } elseif (mysql_num_rows($check_result) > 0) {

            $error = 'A device with this SN already exists.';
        }
    }

    if ($error == '') {

        $name_safe = mysql_real_escape_string(
            $name,
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

        $brand_safe = mysql_real_escape_string(
            $brand,
            $conn
        );

        $model_safe = mysql_real_escape_string(
            $model,
            $conn
        );

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

        $user_id =
            (int)$_SESSION['user_id'];

        $query = "
            INSERT INTO devices (
                name,
                sn,
                mac,
                quantity,
                source,
                brand_id,
                model_id,
                brand,
                model,
                purchase_date,
                purchased_from,
                purchase_price,
                status,
                created_by,
                created_at
            )
            VALUES (
                '$name_safe',
                '$sn_safe',
                '$mac_safe',
                $quantity,
                '$source_safe',
                " . ($brand_id > 0 ? $brand_id : 'NULL') . ",
                " . ($model_id > 0 ? $model_id : 'NULL') . ",
                '$brand_safe',
                '$model_safe',
                $purchase_date_sql,
                '$purchased_from_safe',
                $purchase_price_sql,
                'Available',
                $user_id,
                NOW()
            )
        ";

        $result = mysql_query(
            $query,
            $conn
        );

        if (!$result) {

            $error = mysql_error($conn);

        } else {

            $device_id =
                mysql_insert_id($conn);

            $new_data = json_encode(
                array(
                    'name' => $name,
                    'sn' => $sn,
                    'mac' => $mac,
                    'quantity' => $quantity,
                    'source' => $source,
                    'brand_id' => $brand_id,
                    'model_id' => $model_id,
                    'brand' => $brand,
                    'model' => $model,
                    'purchase_date' => $purchase_date,
                    'purchased_from' => $purchased_from,
                    'purchase_price' => $purchase_price,
                    'status' => 'Available'
                )
            );

            audit_log_action(
                'ADD_DEVICE',
                $device_id,
                $sn,
                'Device added to inventory',
                '',
                $new_data
            );

            $success =
                'Device added successfully to inventory.';

            $name = '';
            $sn = '';
            $mac = '';
            $quantity = '1';
            $source = '';
            $purchase_date = '';
            $purchased_from = '';
            $purchase_price = '';
            $brand_id = 0;
            $model_id = 0;
            $brand = '';
            $model = '';
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

<title>Add Device - Wifonic Hardware</title>

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

    color: #000000;

    min-height: 100vh;

    background:
        linear-gradient(
            135deg,
            #74ebd5 0%,
            #acb6e5 100%
        );

    background-attachment: fixed;

    animation:
        fadeIn 0.5s ease;

}


@keyframes fadeIn {

    from {
        opacity: 0;
    }

    to {
        opacity: 1;
    }

}


@keyframes slideDown {

    from {
        opacity: 0;
        transform: translateY(-12px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }

}


@keyframes riseIn {

    from {
        opacity: 0;
        transform: translateY(14px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }

}


body:before {

    content: "";

    position: fixed;

    width: 350px;
    height: 350px;

    border-radius: 50%;

    top: -150px;
    left: -100px;

    background:
        rgba(
            255,
            255,
            255,
            0.18
        );

    pointer-events: none;

}

body:after {

    content: "";

    position: fixed;

    width: 400px;
    height: 400px;

    border-radius: 50%;

    right: -150px;
    bottom: -180px;

    background:
        rgba(
            255,
            255,
            255,
            0.16
        );

    pointer-events: none;

}


.header {

    position: relative;

    z-index: 10;

    margin:
        18px 25px 0;

    min-height:
        70px;

    padding:
        0 22px;

    border-radius:
        18px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        20px;

    background:
        rgba(
            255,
            255,
            255,
            0.42
        );

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            0.60
        );

    box-shadow:
        0 8px 30px
        rgba(
            31,
            38,
            135,
            0.12
        );

    backdrop-filter:
        blur(18px);

    -webkit-backdrop-filter:
        blur(18px);

    animation:
        slideDown 0.5s ease;

}


.logo {

    display:
        flex;

    align-items:
        center;

    gap:
        10px;

    flex-shrink:
        0;

}


.logo-icon {

    width:
        42px;

    height:
        42px;

    border-radius:
        12px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    color:
        #000000;

    font-size:
        18px;

    font-weight:
        bold;

    background:
        linear-gradient(
            135deg,
            #74ebd5,
            #acb6e5
        );

    transition:
        transform 0.25s ease;

}


.logo-icon:hover {

    transform:
        rotate(-8deg)
        scale(1.05);

}


.logo-text {

    font-size:
        18px;

    font-weight:
        bold;

    white-space:
        nowrap;

    color:
        #000000;

}


.navigation {

    display:
        flex;

    align-items:
        center;

    gap:
        5px;

    flex: 1;

    justify-content:
        center;

}


.nav-link {

    padding:
        10px 13px;

    border-radius:
        10px;

    text-decoration:
        none;

    color:
        #000000;

    font-size:
        12px;

    font-weight:
        bold;

    transition:
        0.2s;

    white-space:
        nowrap;

}


.nav-link:hover {

    background:
        rgba(
            255,
            255,
            255,
            0.45
        );

    transform:
        translateY(-1px);

}


.nav-link.active {

    color:
        #000000;

    background:
        rgba(
            255,
            255,
            255,
            0.62
        );

    box-shadow:
        0 4px 12px
        rgba(
            0,
            0,
            0,
            0.05
        );

}


.dropdown {
    position: relative;
    display: inline-block;
}

.dropdown-content {
    display: none;
    position: absolute;
    background: rgba(255, 255, 255, 0.95);
    min-width: 180px;
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
    border-radius: 10px;
    z-index: 1000;
    padding: 8px 0;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    animation: riseIn 0.2s ease;
}

.dropdown-content a {
    display: block;
    padding: 10px 18px;
    text-decoration: none;
    color: #000000;
    font-size: 13px;
    transition: 0.2s;
}

.dropdown-content a:hover {
    background: rgba(116, 235, 213, 0.2);
    padding-left: 22px;
}

.dropdown-toggle {
    cursor: pointer;
}


.header-right {

    display:
        flex;

    align-items:
        center;

    gap:
        10px;

    flex-shrink:
        0;

}


.user-box {

    padding:
        8px 12px;

    border-radius:
        10px;

    background:
        rgba(
            255,
            255,
            255,
            0.30
        );

    font-size:
        12px;

    color:
        #000000;

}


.username {

    font-weight:
        bold;

    color:
        #000000;

}


.role {

    margin-left:
        5px;

    color:
        #000000;

}


.logout {

    padding:
        10px 14px;

    border-radius:
        9px;

    color:
        #000000;

    text-decoration:
        none;

    font-size:
        12px;

    font-weight:
        bold;

    background:
        rgba(
            198,
            40,
            40,
            0.55
        );

    transition:
        0.2s ease;

}


.logout:hover {

    background:
        rgba(
            198,
            40,
            40,
            0.75
        );

    transform:
        translateY(-1px);

}


.page {

    position: relative;

    z-index: 2;

    max-width: 900px;

    margin: 0 auto;

    padding: 35px 20px 60px;
}


.glass-card {

    background:
        rgba(255, 255, 255, 0.42);

    border:
        1px solid
        rgba(255, 255, 255, 0.60);

    box-shadow:
        0 20px 50px
        rgba(0, 0, 0, 0.15);

    backdrop-filter:
        blur(18px);

    -webkit-backdrop-filter:
        blur(18px);

    border-radius: 20px;

    padding: 35px;

    animation:
        riseIn 0.5s ease;
}


.page-header {

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
        rgba(255,255,255,0.40);

    border:
        1px solid
        rgba(255,255,255,0.55);

    padding:
        10px 16px;

    border-radius: 10px;

    transition:
        0.2s ease;
}


.back:hover {

    transform:
        translateY(-1px);

}


.message {

    padding: 13px 15px;

    border-radius: 10px;

    margin-bottom: 20px;

    font-size: 14px;

    color: #000000;

    animation:
        riseIn 0.35s ease;
}


.error {

    background:
        rgba(255, 80, 80, 0.20);

    border:
        1px solid
        rgba(255, 80, 80, 0.35);
}


.success {

    background:
        rgba(0, 180, 100, 0.20);

    border:
        1px solid
        rgba(0, 180, 100, 0.35);
}


.section-title {

    font-size: 18px;

    font-weight: bold;

    margin:
        25px 0 18px;

    color: #000000;
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

    transition:
        0.2s ease;
}


input:focus,
select:focus {

    box-shadow:
        0 0 0 3px
        rgba(116,235,213,0.35);
}


.required {

    color: #000000;
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

    color: #000000;

    transition:
        0.2s ease;
}


.button:hover {

    transform:
        translateY(-1px);

}


.cancel {

    background:
        rgba(255,255,255,0.45);

    text-decoration: none;
}


.submit {

    background:
        linear-gradient(
            to right,
            #74ebd5,
            #acb6e5
        );

    box-shadow:
        0 8px 20px
        rgba(0,0,0,0.12);
}


.note {

    margin-top: 8px;

    font-size: 12px;

    color: #000000;
}


@media (max-width: 700px) {

    .form-grid {

        grid-template-columns: 1fr;
    }

    .form-group.full {

        grid-column: auto;
    }

    .page-header {

        align-items: flex-start;

        gap: 15px;

        flex-direction: column;
    }

    .glass-card {

        padding: 25px;
    }
}

@media (max-width: 1100px) {

    .header {

        flex-wrap:
            wrap;

        padding:
            12px 18px;

    }

    .navigation {

        order:
            3;

        width:
            100%;

        overflow-x:
            auto;

        justify-content:
            flex-start;

        padding-bottom:
            3px;

    }

}

@media (max-width: 700px) {

    .header {

        margin:
            10px;

    }

    .user-box {

        display:
            none;

    }

}

.combo {
    position: relative;
}

.combo-input {
    width: 100%;
    height: 45px;
    border: none;
    outline: none;
    border-radius: 10px;
    padding: 0 13px;
    font-size: 14px;
    background: rgba(255,255,255,0.72);
    color: #000000;
    transition: 0.2s ease;
}

.combo-input:focus {
    box-shadow: 0 0 0 3px rgba(116,235,213,0.35);
}

.combo-input:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.combo-list {
    display: none;
    position: absolute;
    top: 50px;
    left: 0;
    right: 0;
    max-height: 220px;
    overflow-y: auto;
    background: #ffffff;
    border-radius: 10px;
    box-shadow: 0 12px 30px rgba(0,0,0,0.18);
    z-index: 50;
    animation: riseIn 0.2s ease;
}

.combo-list.open {
    display: block;
}

.combo-option {
    padding: 10px 13px;
    font-size: 14px;
    color: #000000;
    cursor: pointer;
    transition: 0.15s ease;
}

.combo-option:hover,
.combo-option.active {
    background: rgba(116,235,213,0.30);
    padding-left: 17px;
}

.combo-empty {
    padding: 10px 13px;
    font-size: 13px;
    color: #000000;
}


</style>

</head>

<body>

<div class="header">

    <div class="logo">

        <div class="logo-icon">
            W
        </div>

        <div class="logo-text">
            Wifonic Hardware
        </div>

    </div>

    <nav class="navigation">

        <a
            href="dashboard.php"
            class="nav-link"
        >
            Dashboard
        </a>

        <a
            href="inventory.php"
            class="nav-link"
        >
            Inventory
        </a>

        <a
            href="add_device.php"
            class="nav-link active"
        >
            Add Device
        </a>

        <a
            href="sell.php"
            class="nav-link"
        >
            Sell
        </a>

        <a
            href="send.php"
            class="nav-link"
        >
            Send
        </a>

        <?php if ($role === 'admin' || $role === 'manager') { ?>

            <div class="dropdown">
                <a href="#" class="nav-link dropdown-toggle" onclick="toggleDropdown(event)">
                    Management
                </a>
                <div class="dropdown-content" id="managementDropdown">
                    <a href="add_brand.php">Add Brand</a>
                    <a href="add_model.php">Add Model</a>
                    <a href="add_property.php">Add Property</a>
                    <a href="add_courier.php">Add Courier</a>
                </div>
            </div>

        <?php } ?>

        <?php if ($role === 'admin') { ?>

            <a
                href="users.php"
                class="nav-link"
            >
                Users
            </a>

            <a
                href="audit_log.php"
                class="nav-link"
            >
                Audit Logs
            </a>

        <?php } ?>

    </nav>

    <div class="header-right">

        <div class="user-box">

            <span class="username">

                <?php

                echo htmlspecialchars(
                    $username,
                    ENT_QUOTES,
                    'UTF-8'
                );

                ?>

            </span>

            <span class="role">

                <?php

                echo htmlspecialchars(
                    $role,
                    ENT_QUOTES,
                    'UTF-8'
                );

                ?>

            </span>

        </div>

        <a
            href="logout.php"
            class="logout"
        >
            Logout
        </a>

    </div>

</div>

<div class="page">

    <div class="glass-card">

        <div class="page-header">

            <div>

                <div class="title">
                    Add Device
                </div>

                <div class="subtitle">
                    Add a new device to the hardware inventory
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


        <form
            method="post"
            action="add_device.php"
        >


            <div class="section-title">
                Device Information
            </div>


            <div class="form-grid">


                <div class="form-group">

                    <label for="brand_search">
                        Brand
                    </label>

                    <div class="combo" data-combo="brand">
                        <input
                            type="text"
                            id="brand_search"
                            class="combo-input"
                            placeholder="Search brand..."
                            autocomplete="off"
                        >
                        <div class="combo-list"></div>
                    </div>

                    <input type="hidden" id="brand_id" name="brand_id" value="<?php echo (int)$brand_id; ?>">
                    <input type="hidden" id="brand" name="brand" value="<?php echo htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'); ?>">

                </div>


                <div class="form-group">

                    <label for="model_search">
                        Model
                    </label>

                    <div class="combo" data-combo="model">
                        <input
                            type="text"
                            id="model_search"
                            class="combo-input"
                            placeholder="Select a brand first"
                            autocomplete="off"
                            disabled
                        >
                        <div class="combo-list"></div>
                    </div>

                    <input type="hidden" id="model_id" name="model_id" value="<?php echo (int)$model_id; ?>">
                    <input type="hidden" id="model" name="model" value="<?php echo htmlspecialchars($model, ENT_QUOTES, 'UTF-8'); ?>">

                </div>


                <div class="form-group full">

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

                    <div class="note">
                        Auto-filled from Brand + Model, edit as needed
                    </div>

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

                        $sources = array(
                            'Purchased',
                            'Office',
                            'Replaced',
                            'Terminated Client',
                            'Repaired'
                        );

                        foreach ($sources as $item) {

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

                    <div class="combo" data-combo="courier">
                        <input
                            type="text"
                            id="purchased_from"
                            name="purchased_from"
                            class="combo-input"
                            placeholder="Search or type a name..."
                            autocomplete="off"
                            value="<?php
                            echo htmlspecialchars(
                                $purchased_from,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>"
                        >
                        <div class="combo-list"></div>
                    </div>

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
                        placeholder="0.00"
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


            <div class="actions">

                <a
                    href="dashboard.php"
                    class="button cancel"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="button submit"
                >
                    Add Device
                </button>

            </div>


        </form>

    </div>

</div>

<script>
function toggleDropdown(event) {
    event.preventDefault();
    event.stopPropagation();
    var dropdown = document.getElementById('managementDropdown');
    if (dropdown.style.display === 'block') {
        dropdown.style.display = 'none';
    } else {
        dropdown.style.display = 'block';
    }
}

document.addEventListener('click', function(event) {
    var dropdown = document.getElementById('managementDropdown');
    var toggle = document.querySelector('.dropdown-toggle');
    if (dropdown && toggle) {
        if (!toggle.contains(event.target) && !dropdown.contains(event.target)) {
            dropdown.style.display = 'none';
        }
    }
});

var BRANDS = <?php
    $brand_rows = array();
    foreach ($brands as $b) {
        $brand_rows[] = array(
            'id' => (int)$b['id'],
            'name' => $b['brand_name']
        );
    }
    echo json_encode($brand_rows);
?>;

var MODELS = <?php
    $model_rows = array();
    foreach ($models as $m) {
        $model_rows[] = array(
            'id' => (int)$m['id'],
            'brand_id' => (int)$m['brand_id'],
            'name' => $m['model_name']
        );
    }
    echo json_encode($model_rows);
?>;

var COURIERS = <?php
    $courier_rows = array();
    foreach ($couriers as $c) {
        $courier_rows[] = array(
            'id' => (int)$c['id'],
            'name' => $c['name']
        );
    }
    echo json_encode($courier_rows);
?>;

function initCombo(root, items, onSelect) {
    var input = root.querySelector('.combo-input');
    var list = root.querySelector('.combo-list');

    function render(filterText) {
        var text = filterText.toLowerCase();
        var matches = items.filter(function (item) {
            return item.name.toLowerCase().indexOf(text) !== -1;
        });

        list.innerHTML = '';

        if (matches.length === 0) {
            var empty = document.createElement('div');
            empty.className = 'combo-empty';
            empty.textContent = 'No matches';
            list.appendChild(empty);
        } else {
            matches.forEach(function (item) {
                var option = document.createElement('div');
                option.className = 'combo-option';
                option.textContent = item.name;
                option.addEventListener('click', function () {
                    input.value = item.name;
                    list.classList.remove('open');
                    onSelect(item);
                });
                list.appendChild(option);
            });
        }
    }

    input.addEventListener('focus', function () {
        render(input.value);
        list.classList.add('open');
    });

    input.addEventListener('input', function () {
        render(input.value);
        list.classList.add('open');
    });

    document.addEventListener('click', function (e) {
        if (!root.contains(e.target)) {
            list.classList.remove('open');
        }
    });

    return {
        setItems: function (newItems) {
            items = newItems;
        }
    };
}

var nameField = document.getElementById('name');
var brandIdField = document.getElementById('brand_id');
var brandTextField = document.getElementById('brand');
var modelIdField = document.getElementById('model_id');
var modelTextField = document.getElementById('model');
var selectedBrand = null;
var selectedModel = null;

function updateComposedName() {
    if (selectedBrand && selectedModel) {
        nameField.value = selectedBrand.name + ' ' + selectedModel.name;
    }
}

var brandRoot = document.querySelector('[data-combo="brand"]');
var modelRoot = document.querySelector('[data-combo="model"]');
var supplierRoot = document.querySelector('[data-combo="courier"]');

var modelCombo = initCombo(modelRoot, [], function (item) {
    selectedModel = item;
    modelIdField.value = item.id;
    modelTextField.value = item.name;
    updateComposedName();
});

var brandCombo = initCombo(brandRoot, BRANDS, function (item) {
    selectedBrand = item;
    selectedModel = null;

    brandIdField.value = item.id;
    brandTextField.value = item.name;
    modelIdField.value = '';
    modelTextField.value = '';

    var modelInput = modelRoot.querySelector('.combo-input');
    modelInput.value = '';
    modelInput.disabled = false;
    modelInput.placeholder = 'Search model...';

    var filteredModels = MODELS.filter(function (m) {
        return m.brand_id === item.id;
    });
    modelCombo.setItems(filteredModels);

    updateComposedName();
});

initCombo(supplierRoot, COURIERS, function (item) {
});
</script>

</body>

</html>
