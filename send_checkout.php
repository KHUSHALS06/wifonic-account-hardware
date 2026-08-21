<?php

require_once 'auth.php';
require_once 'audit.php';

require_login();

$user_id  = (int)$_SESSION['user_id'];
$username = $_SESSION['username'];
$role     = $_SESSION['role_name'];

$error = '';

$sent_to      = '';
$property_name = '';
$sent_date    = date('Y-m-d');

$properties = array();
$properties_query = "
    SELECT id, name
    FROM properties
    ORDER BY name ASC
";
$properties_result = mysql_query($properties_query, $conn);
if ($properties_result) {
    while ($row = mysql_fetch_assoc($properties_result)) {
        $properties[] = $row;
    }
}


/*
 * =========================================================
 * REMOVE ITEM FROM SEND CART
 * =========================================================
 */

if (
    isset($_GET['remove']) &&
    ctype_digit($_GET['remove'])
) {

    $cart_id = (int)$_GET['remove'];

    $query = "
        SELECT *
        FROM send_cart
        WHERE id = $cart_id
        AND created_by = $user_id
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

        $cart = mysql_fetch_assoc($result);

        $old_data = json_encode($cart);

        mysql_query("
            DELETE FROM send_cart
            WHERE id = $cart_id
            AND created_by = $user_id
            LIMIT 1
        ", $conn);

        audit_log_action(
            'REMOVE_FROM_SEND_CART',
            $cart['device_id']
                ? (int)$cart['device_id']
                : null,
            $cart['sn'],
            'Device removed from send cart',
            $old_data,
            ''
        );
    }

    header('Location: send.php');
    exit;
}


/*
 * =========================================================
 * LOAD SEND CART
 * =========================================================
 */

$cart_query = "
    SELECT *
    FROM send_cart
    WHERE created_by = $user_id
    ORDER BY id ASC
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


/*
 * No items in cart
 */

if (mysql_num_rows($cart_result) == 0) {

    header('Location: send.php');
    exit;
}


/*
 * =========================================================
 * COMPLETE SEND
 * =========================================================
 */

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $sent_to = isset($_POST['sent_to'])
        ? trim($_POST['sent_to'])
        : '';

    $property_name = isset($_POST['property_name'])
        ? trim($_POST['property_name'])
        : '';

    $sent_date = isset($_POST['sent_date'])
        ? trim($_POST['sent_date'])
        : '';


    /*
     * -----------------------------------------------------
     * VALIDATION
     * -----------------------------------------------------
     */

    if ($sent_to == '') {

        $error = 'Sent To is required.';

    } elseif ($sent_date == '') {

        $error = 'Date sent is required.';
    }


    /*
     * -----------------------------------------------------
     * RELOAD CART
     * -----------------------------------------------------
     */

    if ($error == '') {

        $cart_query = "
            SELECT *
            FROM send_cart
            WHERE created_by = $user_id
            ORDER BY id ASC
        ";

        $cart_result = mysql_query(
            $cart_query,
            $conn
        );

        if (!$cart_result) {

            $error = mysql_error($conn);
        }
    }


    /*
     * =====================================================
     * START TRANSACTION
     * =====================================================
     */

    if ($error == '') {

        mysql_query(
            "START TRANSACTION",
            $conn
        );

        $transaction_failed = false;

        $cart_items = array();


        /*
         * -------------------------------------------------
         * FIRST CHECK ALL INVENTORY ITEMS
         * -------------------------------------------------
         */

        while (
            $cart =
            mysql_fetch_assoc(
                $cart_result
            )
        ) {

            $cart_items[] = $cart;


            /*
             * If device came from inventory,
             * check it still exists and has
             * enough quantity.
             */

            if (
                $cart['device_id'] !== null &&
                $cart['device_id'] != ''
            ) {

                $device_id =
                    (int)$cart['device_id'];


                $device_query = "
                    SELECT *
                    FROM devices
                    WHERE id = $device_id
                    AND status = 'Available'
                    LIMIT 1
                ";


                $device_result = mysql_query(
                    $device_query,
                    $conn
                );


                if (
                    !$device_result ||
                    mysql_num_rows($device_result) == 0
                ) {

                    $error =
                        'Device SN ' .
                        $cart['sn'] .
                        ' is no longer available in inventory.';

                    $transaction_failed = true;

                    break;
                }


                $device =
                    mysql_fetch_assoc(
                        $device_result
                    );


                /*
                 * Check quantity.
                 */

                if (
                    (int)$cart['quantity'] >
                    (int)$device['quantity']
                ) {

                    $error =
                        'Send quantity for SN ' .
                        $cart['sn'] .
                        ' is greater than the available inventory quantity.';

                    $transaction_failed = true;

                    break;
                }
            }
        }


        /*
         * =================================================
         * PROCESS SEND
         * =================================================
         */

        if (!$transaction_failed) {

            foreach (
                $cart_items
                as $cart
            ) {

                $device_id =
                    (
                        $cart['device_id'] !== null &&
                        $cart['device_id'] != ''
                    )
                    ? (int)$cart['device_id']
                    : null;


                $source_safe =
                    mysql_real_escape_string(
                        $cart['source'],
                        $conn
                    );


                $name_safe =
                    mysql_real_escape_string(
                        $cart['name'],
                        $conn
                    );


                $brand_safe =
                    mysql_real_escape_string(
                        $cart['brand'],
                        $conn
                    );


                $model_safe =
                    mysql_real_escape_string(
                        $cart['model'],
                        $conn
                    );


                $sn_safe =
                    mysql_real_escape_string(
                        $cart['sn'],
                        $conn
                    );


                $mac_safe =
                    mysql_real_escape_string(
                        $cart['mac'],
                        $conn
                    );


                $sent_to_safe =
                    mysql_real_escape_string(
                        $sent_to,
                        $conn
                    );


                $property_safe =
                    mysql_real_escape_string(
                        $property_name,
                        $conn
                    );


                $quantity =
                    (int)$cart['quantity'];


                /*
                 * Device ID can be NULL for devices
                 * that were never in inventory.
                 */

                $device_id_sql =
                    $device_id === null
                    ? 'NULL'
                    : $device_id;


                /*
                 * -----------------------------------------
                 * INSERT PERMANENT SEND RECORD
                 * -----------------------------------------
                 */

                $insert_query = "
                    INSERT INTO device_sends (
                        device_id,
                        source,
                        name,
                        brand,
                        model,
                        sn,
                        mac,
                        quantity,
                        sent_to,
                        property_name,
                        sent_date,
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
                        '$sent_to_safe',
                        '$property_safe',
                        '$sent_date',
                        $user_id,
                        NOW()
                    )
                ";


                if (
                    !mysql_query(
                        $insert_query,
                        $conn
                    )
                ) {

                    $error =
                        mysql_error($conn);

                    $transaction_failed = true;

                    break;
                }


                /*
                 * -----------------------------------------
                 * UPDATE INVENTORY
                 * -----------------------------------------
                 */

                if ($device_id !== null) {

                    /*
                     * Load latest device information.
                     */

                    $device_query = "
                        SELECT *
                        FROM devices
                        WHERE id = $device_id
                        LIMIT 1
                    ";

                    $device_result = mysql_query(
                        $device_query,
                        $conn
                    );


                    if (
                        !$device_result ||
                        mysql_num_rows($device_result) == 0
                    ) {

                        $error =
                            'Device could not be found while completing the send.';

                        $transaction_failed = true;

                        break;
                    }


                    $device =
                        mysql_fetch_assoc(
                            $device_result
                        );


                    $old_data =
                        json_encode(
                            $device
                        );


                    $remaining_quantity =
                        (int)$device['quantity']
                        -
                        $quantity;


                    /*
                     * If all quantity is sent,
                     * mark device as Sent.
                     */

                    if (
                        $remaining_quantity <= 0
                    ) {

                        $update_query = "
                            UPDATE devices
                            SET
                                quantity = 0,
                                status = 'Sent',
                                updated_at = NOW()
                            WHERE id = $device_id
                            LIMIT 1
                        ";

                    } else {

                        /*
                         * Some quantity remains.
                         */

                        $update_query = "
                            UPDATE devices
                            SET
                                quantity = $remaining_quantity,
                                updated_at = NOW()
                            WHERE id = $device_id
                            LIMIT 1
                        ";
                    }


                    if (
                        !mysql_query(
                            $update_query,
                            $conn
                        )
                    ) {

                        $error =
                            mysql_error($conn);

                        $transaction_failed = true;

                        break;
                    }


                    /*
                     * New state for audit.
                     */

                    $new_status =
                        $remaining_quantity <= 0
                        ? 'Sent'
                        : 'Available';


                    $new_data =
                        json_encode(
                            array(
                                'quantity' =>
                                    $remaining_quantity,

                                'status' =>
                                    $new_status,

                                'sent_to' =>
                                    $sent_to,

                                'property_name' =>
                                    $property_name,

                                'sent_date' =>
                                    $sent_date
                            )
                        );


                    audit_log_action(
                        'COMPLETE_SEND',
                        $device_id,
                        $cart['sn'],
                        'Device sent to ' .
                        $sent_to .
                        (
                            $property_name != ''
                            ? ' / ' .
                              $property_name
                            : ''
                        ),
                        $old_data,
                        $new_data
                    );

                } else {

                    /*
                     * -------------------------------------
                     * DEVICE WAS NOT IN INVENTORY
                     * -------------------------------------
                     *
                     * It is still permanently recorded
                     * in device_sends.
                     */

                    $new_data =
                        json_encode(
                            array(
                                'source' =>
                                    $cart['source'],

                                'quantity' =>
                                    $quantity,

                                'sent_to' =>
                                    $sent_to,

                                'property_name' =>
                                    $property_name,

                                'sent_date' =>
                                    $sent_date
                            )
                        );


                    audit_log_action(
                        'COMPLETE_SEND',
                        null,
                        $cart['sn'],
                        'External device sent to ' .
                        $sent_to .
                        (
                            $property_name != ''
                            ? ' / ' .
                              $property_name
                            : ''
                        ),
                        '',
                        $new_data
                    );
                }
            }
        }


        /*
         * =================================================
         * FINISH TRANSACTION
         * =================================================
         */

        if ($transaction_failed) {

            mysql_query(
                "ROLLBACK",
                $conn
            );

        } else {

            /*
             * Remove all current user's items
             * from send cart.
             */

            $delete_query = "
                DELETE FROM send_cart
                WHERE created_by = $user_id
            ";


            if (
                !mysql_query(
                    $delete_query,
                    $conn
                )
            ) {

                $error =
                    mysql_error($conn);

                mysql_query(
                    "ROLLBACK",
                    $conn
                );

            } else {

                mysql_query(
                    "COMMIT",
                    $conn
                );


                header(
                    'Location: send.php'
                );

                exit;
            }
        }
    }
}


/*
 * =========================================================
 * RELOAD CART FOR DISPLAY
 * =========================================================
 */

$cart_query = "
    SELECT *
    FROM send_cart
    WHERE created_by = $user_id
    ORDER BY id ASC
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
    Complete Send - Wifonic Hardware
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
        1250px;

    margin:
        0 auto;

    padding:
        38px 25px 60px;

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

    margin-top:
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

    margin-top:
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

}


/* =========================================================
   FORM
   ========================================================= */

.form-grid {

    display:
        grid;

    grid-template-columns:
        repeat(
            3,
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


input {

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


input:focus {

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

    color:
        #607d8b;

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
   ACTIONS
   ========================================================= */

.actions {

    margin-top:
        22px;

    display:
        flex;

    justify-content:
        flex-end;

    gap:
        10px;

}


.button {

    min-height:
        44px;

    padding:
        0 20px;

    border:
        none;

    border-radius:
        10px;

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    text-decoration:
        none;

    cursor:
        pointer;

    font-size:
        13px;

    font-weight:
        bold;

}


.cancel {

    color:
        #455a64;

    background:
        rgba(
            255,
            255,
            255,
            0.40
        );

}


.complete {

    color:
        #263238;

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

    .form-grid {

        grid-template-columns:
            1fr;

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
            30px 12px;

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


    <h1 class="title">
        Complete Send
    </h1>


    <div class="subtitle">
        Review the send cart and complete the movement
    </div>


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
         SEND CART
         ================================================= -->

    <div class="card">


        <div class="section-title">
            Send Cart
        </div>


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
                                    $cart['brand'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );

                                ?>

                            </strong>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $cart['model'],
                                ENT_QUOTES,
                                'UTF-8'
                            );

                            ?>

                        </td>


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


                    </tr>


                <?php } ?>


                </tbody>


            </table>


        </div>


    </div>


    <!-- =================================================
         SEND INFORMATION
         ================================================= -->

    <div class="card">


        <div class="section-title">
            Send Information
        </div>


        <form
            method="post"
            action="send_checkout.php"
        >


            <div class="form-grid">


                <div class="form-group">

                    <label>
                        Sent To *
                    </label>

                    <input
                        type="text"
                        name="sent_to"
                        placeholder="Person / Client / Office"
                        value="<?php

                        echo htmlspecialchars(
                            $sent_to,
                            ENT_QUOTES,
                            'UTF-8'
                        );

                        ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Property
                    </label>

                    <div class="combo" data-combo="property">
                        <input
                            type="text"
                            id="property_search"
                            name="property_name"
                            class="combo-input"
                            placeholder="Search or type a property..."
                            autocomplete="off"
                            value="<?php

                            echo htmlspecialchars(
                                $property_name,
                                ENT_QUOTES,
                                'UTF-8'
                            );

                            ?>"
                        >
                        <div class="combo-list"></div>
                    </div>

                </div>


                <div class="form-group">

                    <label>
                        Date Sent *
                    </label>

                    <input
                        type="date"
                        name="sent_date"
                        value="<?php

                        echo htmlspecialchars(
                            $sent_date,
                            ENT_QUOTES,
                            'UTF-8'
                        );

                        ?>"
                        required
                    >

                </div>


            </div>


            <div class="actions">


                <a
                    href="send.php"
                    class="button cancel"
                >
                    Back to Send
                </a>


                <button
                    type="submit"
                    class="button complete"
                    onclick="return confirm('Complete this send? The inventory will be updated.');"
                >
                    Complete Send
                </button>


            </div>


        </form>


    </div>


</div>


<script>
var PROPERTIES = <?php
    $property_rows = array();
    foreach ($properties as $p) {
        $property_rows[] = array(
            'id' => (int)$p['id'],
            'name' => $p['name']
        );
    }
    echo json_encode($property_rows);
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

var propertyRoot = document.querySelector('[data-combo="property"]');

if (propertyRoot) {
    initCombo(propertyRoot, PROPERTIES, function (item) {
    });
}
</script>

</body>

</html>
