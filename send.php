<?php

require_once 'auth.php';
require_once 'audit.php';

require_login();

$username = $_SESSION['username'];
$role     = $_SESSION['role_name'];
$user_id  = (int)$_SESSION['user_id'];

$error = '';

$device_id = '';
$name      = '';
$sn        = '';
$mac       = '';
$quantity  = '1';
$source    = '';

$allowed_sources = array(
    'Purchased',
    'Office',
    'Replaced',
    'Terminated Client',
    'Repaired'
);


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
        $sn        = $device['sn'];
        $mac       = $device['mac'];
        $quantity  = $device['quantity'];
        $source    = $device['source'];
    }
}


/*
 * =========================================================
 * ADD DEVICE TO SEND CART
 * =========================================================
 */

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $device_id = isset($_POST['device_id'])
        ? trim($_POST['device_id'])
        : '';

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
                 * Check available quantity.
                 */

                if (
                    $quantity >
                    (int)$device['quantity']
                ) {

                    $error =
                        'Send quantity cannot be greater than the available inventory quantity.';
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

            $error =
                'This device is already in your send cart.';
        }
    }


    /*
     * -----------------------------------------------------
     * INSERT INTO SEND CART
     * -----------------------------------------------------
     */

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


        /*
         * Device can either be:
         *
         * 1. From inventory
         * 2. Manually entered
         */

        if ($device_id == '') {

            $device_id_sql = 'NULL';

        } else {

            $device_id_sql = (int)$device_id;
        }


        $query = "
            INSERT INTO send_cart (
                device_id,
                source,
                name,
                sn,
                mac,
                quantity,
                created_by,
                created_at
            )
            VALUES (
                $device_id_sql,
                '$source_safe',
                '$name_safe',
                '$sn_safe',
                '$mac_safe',
                $quantity,
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
             * Audit
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

                    'sn' =>
                        $sn,

                    'mac' =>
                        $mac,

                    'quantity' =>
                        $quantity,

                    'source' =>
                        $source
                )
            );


            audit_log_action(
                'ADD_TO_SEND_CART',

                (
                    $device_id == ''
                    ? null
                    : (int)$device_id
                ),

                $sn,

                'Device added to send cart',

                '',

                $new_data
            );


            /*
             * Prevent duplicate form submission.
             */

            header(
                'Location: send.php'
            );

            exit;
        }
    }
}


/*
 * =========================================================
 * LOAD CURRENT USER'S SEND CART
 * =========================================================
 */

$cart_query = "
    SELECT *
    FROM send_cart
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
    Send - Wifonic Hardware
</title>


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
   HEADER
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

}


/* =========================================================
   USER
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
   MAIN
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
   CARD
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
   ADD BUTTON
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
   CART
   ========================================================= */

.cart-header {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

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


.table-wrapper {

    overflow-x:
        auto;

}


table {

    width:
        100%;

    min-width:
        850px;

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
   REMOVE
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


/* =========================================================
   CHECKOUT
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
            class="nav-link"
        >
            Sell
        </a>

        <a
            href="send.php"
            class="nav-link active"
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
                Send Devices
            </h1>

            <div class="subtitle">
                Add devices to the send cart
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
            Add Device to Send Cart
        </div>


        <form
            method="post"
            action="send.php"
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


                <!-- DEVICE NAME -->

                <div class="form-group">

                    <label>
                        Device Name *
                    </label>

                    <input
                        type="text"
                        name="name"
                        placeholder="Device name"
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


            </div>


            <div class="form-actions">

                <button
                    type="submit"
                    class="add-button"
                >
                    + Add to Send Cart
                </button>

            </div>


        </form>


    </div>


    <!-- =================================================
         SEND CART
         ================================================= -->

    <div class="card">


        <div class="cart-header">


            <div
                class="section-title"
                style="margin:0;"
            >
                Send Cart
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
                            Device
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
                            Action
                        </th>

                    </tr>

                    </thead>


                    <tbody>


                    <?php

                    while (
                        $cart =
                        mysql_fetch_assoc(
                            $cart_result
                        )
                    ) {

                    ?>


                        <tr>


                            <td>

                                <strong>

                                    <?php

                                    echo htmlspecialchars(
                                        $cart['name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );

                                    ?>

                                </strong>

                            </td>


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


                            <td>

                                <?php

                                echo (int)
                                    $cart['quantity'];

                                ?>

                            </td>


                            <td>

                                <a
                                    href="send_checkout.php?remove=<?php

                                    echo (int)
                                        $cart['id'];

                                    ?>"
                                    class="remove-button"
                                    onclick="return confirm('Remove this device from the send cart?');"
                                >
                                    Remove
                                </a>

                            </td>


                        </tr>


                    <?php } ?>


                    </tbody>


                </table>


            </div>


            <!-- =================================================
                 CHECKOUT
                 ================================================= -->

            <div class="checkout-area">


                <div class="checkout-note">

                    Review the devices before sending.

                </div>


                <a
                    href="send_checkout.php"
                    class="checkout-button"
                >
                    Proceed to Send →
                </a>


            </div>


        <?php } else { ?>


            <div class="empty">


                <div class="empty-icon">
                    📦
                </div>


                <div class="empty-title">
                    Send cart is empty
                </div>


                <div class="empty-text">

                    Add a device above to start a send.

                </div>


            </div>


        <?php } ?>


    </div>


</div>


</body>

</html>
