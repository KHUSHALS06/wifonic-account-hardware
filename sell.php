<?php

require_once 'auth.php';
require_once 'audit.php';

require_login();

$username = $_SESSION['username'];
$role     = $_SESSION['role_name'];
$user_id  = (int)$_SESSION['user_id'];

$error = '';

$device_id     = '';
$name          = '';
$brand         = '';
$model         = '';
$brand_id      = 0;
$model_id      = 0;
$sn            = '';
$mac           = '';
$quantity      = '1';
$source        = '';
$selling_price = '';

$allowed_sources = array(
    'Purchased',
    'Office',
    'Replaced',
    'Terminated Client',
    'Repaired'
);

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


/*
 * =========================================================
 * LOAD DEVICE FROM INVENTORY
 * =========================================================
 */

if (
    isset($_GET['device_id']) &&
    ctype_digit($_GET['device_id'])
) {

    $selected_id = (int)$_GET['device_id'];

    $query = "
        SELECT
            id,
            name,
            brand,
            model,
            brand_id,
            model_id,
            sn,
            mac,
            quantity,
            source
        FROM devices
        WHERE id = $selected_id
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

        $device = mysql_fetch_assoc($result);

        $device_id = $device['id'];
        $name      = $device['name'];
        $brand     = $device['brand'];
        $model     = $device['model'];
        $brand_id  = (int)$device['brand_id'];
        $model_id  = (int)$device['model_id'];
        $sn        = $device['sn'];
        $mac       = $device['mac'];
        $quantity  = $device['quantity'];
        $source    = $device['source'];
    }
}


/*
 * =========================================================
 * ADD DEVICE TO SELL CART
 * =========================================================
 */

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $device_id = isset($_POST['device_id'])
        ? trim($_POST['device_id'])
        : '';

    $name = isset($_POST['name'])
        ? trim($_POST['name'])
        : '';

    $brand = isset($_POST['brand'])
        ? trim($_POST['brand'])
        : '';

    $model = isset($_POST['model'])
        ? trim($_POST['model'])
        : '';

    $brand_id = isset($_POST['brand_id'])
        ? (int)$_POST['brand_id']
        : 0;

    $model_id = isset($_POST['model_id'])
        ? (int)$_POST['model_id']
        : 0;

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

    $selling_price = isset($_POST['selling_price'])
        ? trim($_POST['selling_price'])
        : '';

    if ($name == '' && ($brand != '' || $model != '')) {
        $name = trim($brand . ' ' . $model);
    }


    /*
     * -----------------------------------------------------
     * VALIDATION
     * -----------------------------------------------------
     */

    if ($name == '') {

        $error = 'Device name is required.';

    } elseif ($sn == '') {

        $error = 'Serial number is required.';

    } elseif ($quantity <= 0) {

        $error = 'Quantity must be greater than 0.';

    } elseif (!in_array($source, $allowed_sources)) {

        $error = 'Please select a valid source.';

    } elseif ($selling_price == '') {

        $error = 'Selling price is required.';

    } elseif (!is_numeric($selling_price)) {

        $error = 'Selling price must be a valid number.';

    } elseif ((float)$selling_price < 0) {

        $error = 'Selling price cannot be negative.';
    }


    /*
     * -----------------------------------------------------
     * IF DEVICE IS FROM INVENTORY
     * -----------------------------------------------------
     */

    if (
        $error == '' &&
        $device_id != ''
    ) {

        if (!ctype_digit($device_id)) {

            $error = 'Invalid device selected.';

        } else {

            $selected_id = (int)$device_id;

            $query = "
                SELECT *
                FROM devices
                WHERE id = $selected_id
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

                $error =
                    'The selected device is no longer available in inventory.';

            } else {

                $device = mysql_fetch_assoc(
                    $result
                );


                /*
                 * Don't allow selling more than
                 * available inventory.
                 */

                if (
                    $quantity >
                    (int)$device['quantity']
                ) {

                    $error =
                        'Selling quantity cannot be greater than the available inventory quantity.';
                }
            }
        }
    }


    /*
     * -----------------------------------------------------
     * CHECK DUPLICATE SN IN CURRENT USER CART
     * -----------------------------------------------------
     */

    if ($error == '') {

        $sn_safe = mysql_real_escape_string(
            $sn,
            $conn
        );

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

            $error =
                'This device is already in your sell cart.';
        }
    }


    /*
     * -----------------------------------------------------
     * INSERT INTO CART
     * -----------------------------------------------------
     */

    if ($error == '') {

        $name_safe = mysql_real_escape_string(
            $name,
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

        $mac_safe = mysql_real_escape_string(
            $mac,
            $conn
        );

        $source_safe = mysql_real_escape_string(
            $source,
            $conn
        );

        $selling_price_safe =
            mysql_real_escape_string(
                $selling_price,
                $conn
            );


        /*
         * Device can be:
         *
         * 1. From inventory
         * 2. Manually entered
         *
         * Therefore device_id can be NULL.
         */

        if ($device_id == '') {

            $device_id_sql = 'NULL';

        } else {

            $device_id_sql = (int)$device_id;
        }


        $query = "
            INSERT INTO sell_cart (
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
            VALUES (
                $device_id_sql,
                '$source_safe',
                '$name_safe',
                '$brand_safe',
                '$model_safe',
                '$sn_safe',
                '$mac_safe',
                $quantity,
                '$selling_price_safe',
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

            $cart_id = mysql_insert_id(
                $conn
            );


            /*
             * Audit information
             */

            $new_data = json_encode(
                array(
                    'cart_id' =>
                        $cart_id,

                    'device_id' =>
                        (
                            $device_id == ''
                            ? null
                            : (int)$device_id
                        ),

                    'name' =>
                        $name,

                    'brand' =>
                        $brand,

                    'model' =>
                        $model,

                    'sn' =>
                        $sn,

                    'mac' =>
                        $mac,

                    'quantity' =>
                        $quantity,

                    'source' =>
                        $source,

                    'selling_price' =>
                        $selling_price
                )
            );


            audit_log_action(
                'ADD_TO_SELL_CART',

                (
                    $device_id == ''
                    ? null
                    : (int)$device_id
                ),

                $sn,

                'Device added to sell cart',

                '',

                $new_data
            );


            /*
             * Redirect to prevent
             * duplicate form submission.
             */

            header(
                'Location: sell.php'
            );

            exit;
        }
    }
}


/*
 * =========================================================
 * LOAD CURRENT USER'S CART
 * =========================================================
 */

$cart_query = "
    SELECT *
    FROM sell_cart
    WHERE created_by = $user_id
    ORDER BY id DESC
";

$cart_result = mysql_query(
    $cart_query,
    $conn
);

if (!$cart_result) {

    die(
        'Database error: ' .
        mysql_error($conn)
    );
}

?>

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Sell - Wifonic Hardware
</title>


<style>

/* =========================================================
   RESET
   ========================================================= */

* {
    box-sizing: border-box;
}

html,
body {

    margin: 0;
    padding: 0;

    min-height: 100%;

}


/* =========================================================
   BODY
   ========================================================= */

body {

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    color:
        #263238;

    min-height:
        100vh;

    background:
        linear-gradient(
            135deg,
            #74ebd5 0%,
            #acb6e5 100%
        );

    background-attachment:
        fixed;

}


/* =========================================================
   GLASS HEADER
   ========================================================= */

.header {

    position:
        relative;

    z-index:
        10;

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

}


/* =========================================================
   LOGO
   ========================================================= */

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
        #ffffff;

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

}


.logo-text {

    font-size:
        18px;

    font-weight:
        bold;

    white-space:
        nowrap;

}


/* =========================================================
   NAVIGATION
   ========================================================= */

.navigation {

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    gap:
        5px;

    flex:
        1;

}


.nav-link {

    padding:
        10px 13px;

    border-radius:
        10px;

    text-decoration:
        none;

    color:
        #455a64;

    font-size:
        12px;

    font-weight:
        bold;

    white-space:
        nowrap;

    transition:
        0.2s;

}


.nav-link:hover {

    background:
        rgba(
            255,
            255,
            255,
            0.45
        );

}


.nav-link.active {

    color:
        #263238;

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


/* =========================================================
   USER AREA
   ========================================================= */

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

}


.username {

    font-weight:
        bold;

}


.role {

    margin-left:
        5px;

    color:
        #666;

}


.logout {

    padding:
        10px 14px;

    border-radius:
        9px;

    color:
        #ffffff;

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
            0.82
        );

}


/* =========================================================
   MAIN CONTAINER
   ========================================================= */

.container {

    position:
        relative;

    z-index:
        2;

    max-width:
        1300px;

    margin:
        0 auto;

    padding:
        38px 25px 60px;

}


/* =========================================================
   PAGE HEADER
   ========================================================= */

.page-header {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    margin-bottom:
        22px;

}


.title {

    margin:
        0;

    font-size:
        30px;

    font-weight:
        700;

}


.subtitle {

    margin-top:
        7px;

    color:
        rgba(
            38,
            50,
            56,
            0.65
        );

    font-size:
        14px;

}


/* =========================================================
   GLASS CARD
   ========================================================= */

.card {

    margin-bottom:
        22px;

    padding:
        25px;

    border-radius:
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
        0 8px 32px
        rgba(
            31,
            38,
            135,
            0.10
        );

    backdrop-filter:
        blur(18px);

    -webkit-backdrop-filter:
        blur(18px);

}


.section-title {

    margin:
        0 0 20px;

    font-size:
        18px;

    font-weight:
        bold;

}


/* =========================================================
   ERROR
   ========================================================= */

.error {

    margin-bottom:
        20px;

    padding:
        13px 15px;

    border-radius:
        10px;

    color:
        #9b1c1c;

    background:
        rgba(
            255,
            80,
            80,
            0.15
        );

    border:
        1px solid
        rgba(
            255,
            80,
            80,
            0.30
        );

    font-size:
        14px;

}


/* =========================================================
   FORM
   ========================================================= */

.form-grid {

    display:
        grid;

    grid-template-columns:
        repeat(
            2,
            1fr
        );

    gap:
        18px;

}


.form-group {

    display:
        flex;

    flex-direction:
        column;

}


label {

    margin-bottom:
        7px;

    font-size:
        13px;

    font-weight:
        bold;

}


input,
select {

    width:
        100%;

    height:
        44px;

    padding:
        0 13px;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            0.60
        );

    border-radius:
        10px;

    outline:
        none;

    background:
        rgba(
            255,
            255,
            255,
            0.65
        );

    font-size:
        14px;

}


input:focus,
select:focus {

    background:
        rgba(
            255,
            255,
            255,
            0.80
        );

    box-shadow:
        0 0 0 3px
        rgba(
            116,
            235,
            213,
            0.18
        );

}


/* =========================================================
   FORM ACTION
   ========================================================= */

.form-actions {

    display:
        flex;

    justify-content:
        flex-end;

    margin-top:
        22px;

}


.add-button {

    height:
        44px;

    padding:
        0 22px;

    border:
        none;

    border-radius:
        10px;

    cursor:
        pointer;

    color:
        #263238;

    font-size:
        13px;

    font-weight:
        bold;

    background:
        linear-gradient(
            135deg,
            #74ebd5,
            #acb6e5
        );

    box-shadow:
        0 6px 18px
        rgba(
            31,
            38,
            135,
            0.10
        );

}


/* =========================================================
   CART HEADER
   ========================================================= */

.cart-header {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        15px;

    margin-bottom:
        20px;

}


.cart-count {

    padding:
        6px 10px;

    border-radius:
        8px;

    background:
        rgba(
            255,
            255,
            255,
            0.45
        );

    font-size:
        11px;

    font-weight:
        bold;

}


/* =========================================================
   TABLE
   ========================================================= */

.table-wrapper {

    overflow-x:
        auto;

}


table {

    width:
        100%;

    min-width:
        900px;

    border-collapse:
        collapse;

}


thead {

    background:
        rgba(
            255,
            255,
            255,
            0.25
        );

}


th {

    padding:
        13px;

    text-align:
        left;

    font-size:
        11px;

    text-transform:
        uppercase;

    letter-spacing:
        0.3px;

    color:
        #607d8b;

    white-space:
        nowrap;

}


td {

    padding:
        13px;

    font-size:
        13px;

    border-top:
        1px solid
        rgba(
            255,
            255,
            255,
            0.30
        );

    white-space:
        nowrap;

}


tbody tr:hover {

    background:
        rgba(
            255,
            255,
            255,
            0.18
        );

}


.mono {

    font-family:
        "Courier New",
        monospace;

    font-size:
        12px;

}


.source {

    display:
        inline-flex;

    padding:
        5px 8px;

    border-radius:
        7px;

    background:
        rgba(
            255,
            255,
            255,
            0.45
        );

    font-size:
        11px;

    font-weight:
        bold;

}


/* =========================================================
   CART TOTAL
   ========================================================= */

.cart-total-row td {

    padding-top:
        18px;

    font-weight:
        bold;

    border-top:
        1px solid
        rgba(
            255,
            255,
            255,
            0.50
        );

}


.cart-total {

    font-size:
        16px;

}


/* =========================================================
   REMOVE BUTTON
   ========================================================= */

.remove-button {

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    padding:
        7px 10px;

    border-radius:
        7px;

    color:
        #c62828;

    text-decoration:
        none;

    background:
        rgba(
            239,
            83,
            80,
            0.10
        );

    font-size:
        11px;

    font-weight:
        bold;

}


.remove-button:hover {

    background:
        rgba(
            239,
            83,
            80,
            0.18
        );

}


/* =========================================================
   PROCEED BUTTON
   ========================================================= */

.checkout-area {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        20px;

    margin-top:
        22px;

}


.checkout-note {

    color:
        #607d8b;

    font-size:
        12px;

}


.checkout-button {

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    min-height:
        44px;

    padding:
        0 22px;

    border-radius:
        10px;

    text-decoration:
        none;

    color:
        #263238;

    font-size:
        13px;

    font-weight:
        bold;

    background:
        linear-gradient(
            135deg,
            #74ebd5,
            #acb6e5
        );

    box-shadow:
        0 6px 18px
        rgba(
            31,
            38,
            135,
            0.10
        );

}


/* =========================================================
   EMPTY
   ========================================================= */

.empty {

    padding:
        55px 20px;

    text-align:
        center;

    color:
        #68777d;

}


.empty-icon {

    font-size:
        38px;

    margin-bottom:
        12px;

}


.empty-title {

    margin-bottom:
        6px;

    color:
        #37474f;

    font-size:
        18px;

    font-weight:
        bold;

}


.empty-text {

    font-size:
        13px;

}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 1000px) {

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

    .container {

        padding:
            30px 12px 45px;

    }

    .page-header {

        align-items:
            flex-start;

        flex-direction:
            column;

    }

    .form-grid {

        grid-template-columns:
            1fr;

    }

    .checkout-area {

        align-items:
            stretch;

        flex-direction:
            column;

    }

    .checkout-button {

        width:
            100%;

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
    color: #333;
}

.combo-input:focus {
    box-shadow: 0 0 0 3px rgba(116,235,213,0.30);
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
}

.combo-list.open {
    display: block;
}

.combo-option {
    padding: 10px 13px;
    font-size: 14px;
    color: #333;
    cursor: pointer;
}

.combo-option:hover,
.combo-option.active {
    background: rgba(116,235,213,0.25);
}

.combo-empty {
    padding: 10px 13px;
    font-size: 13px;
    color: #888;
}

</style>

</head>


<body>


<!-- =====================================================
     HEADER
     ===================================================== -->

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
            href="add_device.php"
            class="nav-link"
        >
            Add Device
        </a>

        <a
            href="sell.php"
            class="nav-link active"
        >
            Sell
        </a>

        <a
            href="send.php"
            class="nav-link"
        >
            Send
        </a>

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


<!-- =====================================================
     MAIN
     ===================================================== -->

<div class="container">


    <div class="page-header">

        <div>

            <h1 class="title">
                Sell Devices
            </h1>

            <div class="subtitle">
                Add devices to the selling cart
            </div>

        </div>

    </div>


    <!-- =================================================
         ERROR
         ================================================= -->

    <?php if ($error != '') { ?>

        <div class="error">

            <?php

            echo htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            );

            ?>

        </div>

    <?php } ?>


    <!-- =================================================
         ADD DEVICE
         ================================================= -->

    <div class="card">


        <div class="section-title">
            Add Device to Sell Cart
        </div>


        <form
            method="post"
            action="sell.php"
        >


            <input
                type="hidden"
                name="device_id"
                value="<?php

                echo htmlspecialchars(
                    $device_id,
                    ENT_QUOTES,
                    'UTF-8'
                );

                ?>"
            >


            <div class="form-grid">


                <!-- BRAND / MODEL -->

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
                            value="<?php
                            echo htmlspecialchars(
                                $brand,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>"
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
                            placeholder="<?php echo ($brand_id > 0) ? 'Search model...' : 'Select a brand first'; ?>"
                            autocomplete="off"
                            value="<?php
                            echo htmlspecialchars(
                                $model,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>"
                            <?php echo ($brand_id > 0) ? '' : 'disabled'; ?>
                        >
                        <div class="combo-list"></div>
                    </div>

                    <input type="hidden" id="model_id" name="model_id" value="<?php echo (int)$model_id; ?>">
                    <input type="hidden" id="model" name="model" value="<?php echo htmlspecialchars($model, ENT_QUOTES, 'UTF-8'); ?>">

                </div>


                <input
                    type="hidden"
                    name="name"
                    value="<?php

                    echo htmlspecialchars(
                        $name,
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    ?>"
                >


                <!-- SN -->

                <div class="form-group">

                    <label>
                        Serial Number (SN) *
                    </label>

                    <input
                        type="text"
                        name="sn"
                        placeholder="Serial number"
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


                <!-- MAC -->

                <div class="form-group">

                    <label>
                        MAC Address
                    </label>

                    <input
                        type="text"
                        name="mac"
                        placeholder="MAC address (optional)"
                        value="<?php

                        echo htmlspecialchars(
                            $mac,
                            ENT_QUOTES,
                            'UTF-8'
                        );

                        ?>"
                    >

                </div>


                <!-- QUANTITY -->

                <div class="form-group">

                    <label>
                        Quantity *
                    </label>

                    <input
                        type="number"
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


                <!-- SOURCE -->

                <div class="form-group">

                    <label>
                        Source *
                    </label>

                    <select
                        name="source"
                        required
                    >

                        <option value="">
                            Select Source
                        </option>


                        <?php

                        foreach (
                            $allowed_sources
                            as $item
                        ) {

                            $selected =
                                (
                                    $source == $item
                                )
                                ? 'selected'
                                : '';

                            echo
                                '<option value="' .
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


                <!-- SELLING PRICE -->

                <div class="form-group">

                    <label>
                        Selling Price *
                    </label>

                    <input
                        type="number"
                        name="selling_price"
                        min="0"
                        step="0.01"
                        placeholder="₹ 0.00"
                        value="<?php

                        echo htmlspecialchars(
                            $selling_price,
                            ENT_QUOTES,
                            'UTF-8'
                        );

                        ?>"
                        required
                    >

                </div>


            </div>


            <div class="form-actions">

                <button
                    type="submit"
                    class="add-button"
                >
                    + Add to Sell Cart
                </button>

            </div>


        </form>


    </div>


    <!-- =================================================
         SELL CART
         ================================================= -->

    <div class="card">


        <div class="cart-header">


            <div class="section-title"
                 style="margin:0;">

                Sell Cart

            </div>


            <div class="cart-count">

                <?php

                echo mysql_num_rows(
                    $cart_result
                );

                ?>

                item(s)

            </div>


        </div>


        <?php if (
            mysql_num_rows($cart_result) > 0
        ) { ?>


            <div class="table-wrapper">


                <table>


                    <thead>

                    <tr>

                        <th>
                            Brand
                        </th>

                        <th>
                            Model
                        </th>

                        <th>
                            SN
                        </th>

                        <th>
                            MAC
                        </th>

                        <th>
                            Source
                        </th>

                        <th>
                            Quantity
                        </th>

                        <th>
                            Selling Price
                        </th>

                        <th>
                            Total
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                    </thead>


                    <tbody>


                    <?php

                    $cart_total = 0;

                    while (
                        $cart =
                        mysql_fetch_assoc(
                            $cart_result
                        )
                    ) {


                        $total =
                            (float)
                            $cart['quantity']
                            *
                            (float)
                            $cart['selling_price'];


                        $cart_total +=
                            $total;


                    ?>


                        <tr>


                            <!-- BRAND -->

                            <td>

                                <strong>

                                    <?php

                                    echo htmlspecialchars(
                                        $cart['brand'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );

                                    ?>

                                </strong>

                            </td>


                            <!-- MODEL -->

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $cart['model'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );

                                ?>

                            </td>


                            <!-- SN -->

                            <td>

                                <span class="mono">

                                    <?php

                                    echo htmlspecialchars(
                                        $cart['sn'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );

                                    ?>

                                </span>

                            </td>


                            <!-- MAC -->

                            <td>

                                <?php

                                if (
                                    $cart['mac'] != ''
                                ) {

                                    echo htmlspecialchars(
                                        $cart['mac'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );

                                } else {

                                    echo '—';

                                }

                                ?>

                            </td>


                            <!-- SOURCE -->

                            <td>

                                <span class="source">

                                    <?php

                                    echo htmlspecialchars(
                                        $cart['source'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );

                                    ?>

                                </span>

                            </td>


                            <!-- QUANTITY -->

                            <td>

                                <?php

                                echo (int)
                                    $cart['quantity'];

                                ?>

                            </td>


                            <!-- SELLING PRICE -->

                            <td>

                                ₹<?php

                                echo number_format(
                                    (float)
                                    $cart[
                                        'selling_price'
                                    ],
                                    2
                                );

                                ?>

                            </td>


                            <!-- TOTAL -->

                            <td>

                                <strong>

                                    ₹<?php

                                    echo number_format(
                                        $total,
                                        2
                                    );

                                    ?>

                                </strong>

                            </td>


                            <!-- REMOVE -->

                            <td>

                                <a
                                    href="sell_checkout.php?remove=<?php

                                    echo (int)
                                        $cart['id'];

                                    ?>"
                                    class="remove-button"
                                    onclick="return confirm('Remove this device from the sell cart?');"
                                >
                                    Remove
                                </a>

                            </td>


                        </tr>


                    <?php } ?>


                    <!-- CART TOTAL -->

                    <tr
                        class="cart-total-row"
                    >

                        <td
                            colspan="6"
                            style="
                                text-align:right;
                            "
                        >

                            Cart Total

                        </td>


                        <td
                            class="cart-total"
                        >

                            ₹<?php

                            echo number_format(
                                $cart_total,
                                2
                            );

                            ?>

                        </td>


                        <td></td>

                    </tr>


                    </tbody>


                </table>


            </div>


            <!-- =================================================
                 CHECKOUT
                 ================================================= -->

            <div class="checkout-area">


                <div class="checkout-note">

                    Review the cart before completing the sale.

                </div>


                <a
                    href="sell_checkout.php"
                    class="checkout-button"
                >
                    Proceed to Sale →
                </a>


            </div>


        <?php } else { ?>


            <div class="empty">


                <div class="empty-icon">
                    🛒
                </div>


                <div class="empty-title">
                    Sell cart is empty
                </div>


                <div class="empty-text">

                    Add a device above to start a sale.

                </div>


            </div>


        <?php } ?>


    </div>


</div>


<script>
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

var brandIdField = document.getElementById('brand_id');
var brandTextField = document.getElementById('brand');
var modelIdField = document.getElementById('model_id');
var modelTextField = document.getElementById('model');
var selectedBrand = null;
var selectedModel = null;

var currentBrandId = <?php echo (int)$brand_id; ?>;

var brandRoot = document.querySelector('[data-combo="brand"]');
var modelRoot = document.querySelector('[data-combo="model"]');

var initialModels = MODELS.filter(function (m) {
    return m.brand_id === currentBrandId;
});

var modelCombo = initCombo(modelRoot, initialModels, function (item) {
    selectedModel = item;
    modelIdField.value = item.id;
    modelTextField.value = item.name;
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
});
</script>

</body>

</html>
