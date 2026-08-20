<?php

require_once 'auth.php';
require_once 'audit.php';

require_login();

$user_id = (int)$_SESSION['user_id'];

$error = '';

$sold_to = '';
$property_name = '';
$sale_date = date('Y-m-d');


/*
 * =========================================================
 * REMOVE ITEM FROM CART
 * =========================================================
 */

if (
    isset($_GET['remove']) &&
    ctype_digit($_GET['remove'])
) {

    $cart_id = (int)$_GET['remove'];

    $query = "
        SELECT *
        FROM sell_cart
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
            DELETE FROM sell_cart
            WHERE id = $cart_id
            AND created_by = $user_id
            LIMIT 1
        ", $conn);

        audit_log_action(
            'REMOVE_FROM_SELL_CART',
            $cart['device_id']
                ? (int)$cart['device_id']
                : null,
            $cart['sn'],
            'Device removed from sell cart',
            $old_data,
            ''
        );
    }

    header('Location: sell.php');
    exit;
}


/*
 * =========================================================
 * LOAD CART
 * =========================================================
 */

$cart_query = "
    SELECT *
    FROM sell_cart
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
 * No items
 */

if (mysql_num_rows($cart_result) == 0) {

    header('Location: sell.php');
    exit;
}


/*
 * =========================================================
 * COMPLETE SALE
 * =========================================================
 */

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $sold_to = isset($_POST['sold_to'])
        ? trim($_POST['sold_to'])
        : '';

    $property_name = isset($_POST['property_name'])
        ? trim($_POST['property_name'])
        : '';

    $sale_date = isset($_POST['sale_date'])
        ? trim($_POST['sale_date'])
        : '';


    /*
     * Validation
     */

    if ($sold_to == '') {

        $error = 'Sold To is required.';

    } elseif ($sale_date == '') {

        $error = 'Sale date is required.';
    }


    /*
     * Reload cart after validation.
     */

    if ($error == '') {

        $cart_query = "
            SELECT *
            FROM sell_cart
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
     * PROCESS SALE
     * =====================================================
     */

    if ($error == '') {

        mysql_query(
            "START TRANSACTION",
            $conn
        );


        $transaction_failed = false;

        $cart_items = array();


        while (
            $cart =
            mysql_fetch_assoc(
                $cart_result
            )
        ) {

            $cart_items[] = $cart;


            $device_id =
                $cart['device_id']
                    ? (int)$cart['device_id']
                    : null;


            /*
             * If device came from inventory,
             * verify it is still available.
             */

            if ($device_id !== null) {

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
                        'One of the selected devices is no longer available in inventory.';

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
                        'Selling quantity for SN ' .
                        $cart['sn'] .
                        ' is greater than available quantity.';

                    $transaction_failed = true;

                    break;
                }
            }
        }


        /*
         * =================================================
         * CREATE SALE RECORDS
         * =================================================
         */

        if (!$transaction_failed) {

            foreach (
                $cart_items
                as $cart
            ) {

                $device_id =
                    $cart['device_id']
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


                $sold_to_safe =
                    mysql_real_escape_string(
                        $sold_to,
                        $conn
                    );


                $property_safe =
                    mysql_real_escape_string(
                        $property_name,
                        $conn
                    );


                $selling_price =
                    (float)$cart[
                        'selling_price'
                    ];


                $quantity =
                    (int)$cart[
                        'quantity'
                    ];


                $total_price =
                    $selling_price *
                    $quantity;


                $device_id_sql =
                    $device_id === null
                        ? 'NULL'
                        : $device_id;


                /*
                 * Insert permanent sale.
                 */

                $sale_query = "
                    INSERT INTO device_sales (
                        device_id,
                        source,
                        name,
                        sn,
                        mac,
                        quantity,
                        selling_price,
                        total_price,
                        sold_to,
                        property_name,
                        sale_date,
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
                        $selling_price,
                        $total_price,
                        '$sold_to_safe',
                        '$property_safe',
                        '$sale_date',
                        $user_id,
                        NOW()
                    )
                ";


                if (
                    !mysql_query(
                        $sale_query,
                        $conn
                    )
                ) {

                    $error =
                        mysql_error($conn);

                    $transaction_failed = true;

                    break;
                }


                /*
                 * If this came from inventory,
                 * update its status.
                 */

                if ($device_id !== null) {

                    $old_data =
                        json_encode(
                            $device
                        );


                    /*
                     * If quantity becomes zero,
                     * mark Sold.
                     *
                     * Otherwise reduce inventory.
                     */

                    $remaining_quantity =
                        (int)$device['quantity']
                        - $quantity;


                    if (
                        $remaining_quantity <= 0
                    ) {

                        $update_query = "
                            UPDATE devices
                            SET
                                quantity = 0,
                                status = 'Sold',
                                updated_at = NOW()
                            WHERE id = $device_id
                            LIMIT 1
                        ";

                    } else {

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
                            ? 'Sold'
                            : 'Available';


                    $new_data =
                        json_encode(
                            array(
                                'quantity' =>
                                    $remaining_quantity,
                                'status' =>
                                    $new_status,
                                'sold_to' =>
                                    $sold_to,
                                'property' =>
                                    $property_name,
                                'sale_date' =>
                                    $sale_date
                            )
                        );


                    audit_log_action(
                        'COMPLETE_SALE',
                        $device_id,
                        $cart['sn'],
                        'Device sold to ' .
                        $sold_to .
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
                     * Device was not in inventory.
                     *
                     * Still log the sale.
                     */

                    $new_data =
                        json_encode(
                            array(
                                'source' =>
                                    $cart['source'],
                                'quantity' =>
                                    $quantity,
                                'selling_price' =>
                                    $selling_price,
                                'sold_to' =>
                                    $sold_to,
                                'property' =>
                                    $property_name,
                                'sale_date' =>
                                    $sale_date
                            )
                        );


                    audit_log_action(
                        'COMPLETE_SALE',
                        null,
                        $cart['sn'],
                        'External device sold to ' .
                        $sold_to .
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
             * Remove cart.
             */

            if (
                !mysql_query("
                    DELETE FROM sell_cart
                    WHERE created_by = $user_id
                ", $conn)
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
                    'Location: sell.php'
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
    FROM sell_cart
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
    Complete Sale - Wifonic Hardware
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

    min-height:
        100vh;

    color:
        #263238;

    background:
        linear-gradient(
            135deg,
            #74ebd5,
            #acb6e5
        );

    background-attachment:
        fixed;

}


/* =========================================================
   HEADER
   ========================================================= */

.header {

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


.logo {

    display:
        flex;

    align-items:
        center;

    gap:
        10px;

    font-weight:
        bold;

}


.logo-icon {

    width:
        42px;

    height:
        42px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        12px;

    color:
        #ffffff;

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

}


.navigation {

    display:
        flex;

    gap:
        5px;

    flex:
        1;

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
        #455a64;

    font-size:
        12px;

    font-weight:
        bold;

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

    background:
        rgba(
            255,
            255,
            255,
            0.62
        );

    color:
        #263238;

}


.header-right {

    display:
        flex;

    align-items:
        center;

    gap:
        10px;

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

    color:
        #666;

    margin-left:
        5px;

}


.logout {

    padding:
        10px 14px;

    border-radius:
        9px;

    background:
        rgba(
            198,
            40,
            40,
            0.82
        );

    color:
        #ffffff;

    text-decoration:
        none;

    font-size:
        12px;

    font-weight:
        bold;

}


/* =========================================================
   MAIN
   ========================================================= */

.container {

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
        repeat(3, 1fr);

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

    border-collapse:
        collapse;

    min-width:
        850px;

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

    background:
        rgba(
            255,
            255,
            255,
            0.25
        );

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


/* =========================================================
   REMOVE
   ========================================================= */

.remove {

    color:
        #c62828;

    text-decoration:
        none;

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

    height:
        44px;

    padding:
        0 20px;

    border:
        none;

    border-radius:
        10px;

    cursor:
        pointer;

    font-size:
        13px;

    font-weight:
        bold;

}


.cancel {

    display:
        inline-flex;

    align-items:
        center;

    text-decoration:
        none;

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

    .user-box {

        display:
            none;

    }

    .container {

        padding:
            30px 12px;

    }

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
                    $_SESSION['username'],
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

            </span>

            <span class="role">

                <?php
                echo htmlspecialchars(
                    $_SESSION['role_name'],
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


<div class="container">


    <h1 class="title">
        Complete Sale
    </h1>

    <div class="subtitle">
        Review the sell cart and complete the transaction
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


    <!-- =====================================================
         CART
         ===================================================== -->

    <div class="card">

        <div class="section-title">
            Sell Cart
        </div>


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
                        (float)$cart[
                            'quantity'
                        ] *
                        (float)$cart[
                            'selling_price'
                        ];

                    $cart_total += $total;

                ?>

                    <tr>

                        <td>

                            <?php
                            echo htmlspecialchars(
                                $cart['name'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

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
                            echo (int)
                                $cart['quantity'];
                            ?>

                        </td>


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


                        <td>

                            <a
                                href="sell_checkout.php?remove=<?php
                                echo (int)
                                    $cart['id'];
                                ?>"
                                class="remove"
                                onclick="return confirm('Remove this device from the sell cart?');"
                            >
                                Remove
                            </a>

                        </td>

                    </tr>

                <?php } ?>


                <tr>

                    <td
                        colspan="4"
                        style="text-align:right;font-weight:bold;"
                    >
                        Total
                    </td>

                    <td>

                        <strong>

                            ₹<?php
                            echo number_format(
                                $cart_total,
                                2
                            );
                            ?>

                        </strong>

                    </td>

                    <td></td>

                </tr>


                </tbody>

            </table>

        </div>

    </div>


    <!-- =====================================================
         SALE INFORMATION
         ===================================================== -->

    <div class="card">

        <div class="section-title">
            Sale Information
        </div>


        <form
            method="post"
            action="sell_checkout.php"
        >


            <div class="form-grid">


                <div class="form-group">

                    <label>
                        Sold To *
                    </label>

                    <input
                        type="text"
                        name="sold_to"
                        value="<?php
                        echo htmlspecialchars(
                            $sold_to,
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

                    <input
                        type="text"
                        name="property_name"
                        value="<?php
                        echo htmlspecialchars(
                            $property_name,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Sale Date *
                    </label>

                    <input
                        type="date"
                        name="sale_date"
                        value="<?php
                        echo htmlspecialchars(
                            $sale_date,
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
                    href="sell.php"
                    class="button cancel"
                >
                    Back to Sell
                </a>


                <button
                    type="submit"
                    class="button complete"
                    onclick="return confirm('Complete this sale? The inventory quantities will be updated and the sale cannot be undone from this page.');"
                >
                    Complete Sale
                </button>

            </div>


        </form>

    </div>


</div>


</body>

</html>
