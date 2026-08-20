<?php

session_start();

require_once 'config.php';
require_once 'auth.php';

require_login();

$username = $_SESSION['username'];
$role     = $_SESSION['role_name'];

$error = '';
$device = null;
$sales = array();
$sends = array();
$audit_entries = array();


/*
 * =========================================================
 * LOAD DEVICE
 * =========================================================
 */

if (
    !isset($_GET['id']) ||
    !ctype_digit($_GET['id'])
) {

    $error = 'No device selected.';

} else {

    $device_id = (int)$_GET['id'];

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


        /*
         * Sale history
         */

        $sales_query = "
            SELECT *
            FROM device_sales
            WHERE device_id = $device_id
            ORDER BY sale_date DESC, id DESC
        ";

        $sales_result = mysql_query(
            $sales_query,
            $conn
        );

        if ($sales_result) {

            while (
                $row = mysql_fetch_assoc($sales_result)
            ) {

                $sales[] = $row;
            }
        }


        /*
         * Send history
         */

        $sends_query = "
            SELECT *
            FROM device_sends
            WHERE device_id = $device_id
            ORDER BY sent_date DESC, id DESC
        ";

        $sends_result = mysql_query(
            $sends_query,
            $conn
        );

        if ($sends_result) {

            while (
                $row = mysql_fetch_assoc($sends_result)
            ) {

                $sends[] = $row;
            }
        }


        /*
         * Audit trail
         */

        $audit_query = "
            SELECT *
            FROM audit_log
            WHERE device_id = $device_id
            ORDER BY created_at DESC, id DESC
            LIMIT 50
        ";

        $audit_result = mysql_query(
            $audit_query,
            $conn
        );

        if ($audit_result) {

            while (
                $row = mysql_fetch_assoc($audit_result)
            ) {

                $audit_entries[] = $row;
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

<title>Device Details - Wifonic Hardware</title>

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

    color: #263238;

    min-height: 100vh;

    background:
        linear-gradient(
            135deg,
            #74ebd5 0%,
            #acb6e5 100%
        );

    background-attachment: fixed;
}


.page {

    max-width: 1100px;

    margin: 0 auto;

    padding: 40px 20px 60px;
}


.header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 25px;

    flex-wrap: wrap;

    gap: 15px;
}


.title {

    font-size: 28px;

    font-weight: bold;

    color: #ffffff;
}


.subtitle {

    margin-top: 5px;

    color:
        rgba(255, 255, 255, 0.85);

    font-size: 14px;
}


.header-actions {

    display: flex;

    gap: 10px;
}


.back,
.edit-link {

    text-decoration: none;

    color: #ffffff;

    background:
        rgba(255,255,255,0.20);

    border:
        1px solid
        rgba(255,255,255,0.35);

    padding:
        10px 16px;

    border-radius: 10px;

    font-size: 13px;

    font-weight: bold;
}


.edit-link {

    background:
        linear-gradient(
            135deg,
            #74ebd5,
            #acb6e5
        );

    color: #263238;

    border: none;
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

    color: #9b1c1c;
}


.card {

    background:
        rgba(255, 255, 255, 0.42);

    border:
        1px solid
        rgba(255, 255, 255, 0.60);

    box-shadow:
        0 8px 32px
        rgba(31, 38, 135, 0.12);

    backdrop-filter:
        blur(18px);

    -webkit-backdrop-filter:
        blur(18px);

    border-radius: 20px;

    padding: 30px;

    margin-bottom: 25px;
}


.card-title {

    font-size: 18px;

    font-weight: bold;

    margin-bottom: 18px;
}


.info-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 20px;
}


.info-item {

    display: flex;

    flex-direction: column;
}


.info-label {

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: 0.4px;

    color:
        rgba(38, 50, 56, 0.65);

    margin-bottom: 4px;
}


.info-value {

    font-size: 15px;

    font-weight: bold;
}


.mono {

    font-family:
        "Courier New",
        monospace;

    font-size: 13px;
}


.status-pill {

    display: inline-flex;

    padding: 6px 10px;

    border-radius: 8px;

    color: #2e7d32;

    background:
        rgba(76, 175, 80, 0.14);

    font-size: 11px;

    font-weight: bold;

    width: fit-content;
}


.status-pill.sold {

    color: #1565c0;

    background:
        rgba(33, 150, 243, 0.14);
}


.status-pill.sent {

    color: #e65100;

    background:
        rgba(255, 152, 0, 0.16);
}


.table-wrapper {

    overflow-x: auto;
}


table {

    width: 100%;

    border-collapse: collapse;
}


thead {

    background:
        rgba(255, 255, 255, 0.28);
}


th {

    padding: 12px 14px;

    text-align: left;

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: 0.4px;

    color:
        rgba(38, 50, 56, 0.68);

    white-space: nowrap;
}


td {

    padding: 12px 14px;

    font-size: 13px;

    border-top:
        1px solid
        rgba(255, 255, 255, 0.30);
}


.empty-row td {

    text-align: center;

    padding: 25px;

    color:
        rgba(38, 50, 56, 0.55);
}


@media (max-width: 800px) {

    .info-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }
}

@media (max-width: 500px) {

    .info-grid {

        grid-template-columns: 1fr;
    }
}

</style>

</head>

<body>

<div class="page">


    <div class="header">

        <div>

            <div class="title">
                Device Details
            </div>

            <div class="subtitle">
                <?php if ($device !== null) { ?>
                    Full history for this device
                <?php } else { ?>
                    View device history
                <?php } ?>
            </div>

        </div>

        <div class="header-actions">

            <?php if ($device !== null) { ?>

                <a
                    href="edit_device.php?id=<?php
                        echo (int)$device['id'];
                    ?>"
                    class="edit-link"
                >
                    Edit Device
                </a>

            <?php } ?>

            <a
                href="dashboard.php"
                class="back"
            >
                Back
            </a>

        </div>

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


    <?php if ($device !== null) { ?>


        <!-- =============================================
             DEVICE INFO
             ============================================= -->

        <div class="card">

            <div class="card-title">
                <?php
                echo htmlspecialchars(
                    $device['name'],
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
            </div>

            <div class="info-grid">

                <div class="info-item">
                    <div class="info-label">Status</div>
                    <div class="info-value">
                        <?php

                        $status_class = '';

                        if ($device['status'] === 'Sold') {
                            $status_class = 'sold';
                        } elseif ($device['status'] === 'Sent') {
                            $status_class = 'sent';
                        }

                        ?>
                        <span class="status-pill <?php echo $status_class; ?>">
                            <?php
                            echo htmlspecialchars(
                                $device['status'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>
                        </span>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Serial Number</div>
                    <div class="info-value mono">
                        <?php
                        echo htmlspecialchars(
                            $device['sn'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">MAC Address</div>
                    <div class="info-value mono">
                        <?php
                        echo $device['mac'] != ''
                            ? htmlspecialchars(
                                $device['mac'],
                                ENT_QUOTES,
                                'UTF-8'
                              )
                            : '—';
                        ?>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Quantity</div>
                    <div class="info-value">
                        <?php
                        echo (int)$device['quantity'];
                        ?>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Source</div>
                    <div class="info-value">
                        <?php
                        echo htmlspecialchars(
                            $device['source'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Added On</div>
                    <div class="info-value">
                        <?php
                        echo htmlspecialchars(
                            $device['created_at'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Purchase Date</div>
                    <div class="info-value">
                        <?php
                        echo $device['purchase_date'] !== null
                            ? htmlspecialchars(
                                $device['purchase_date'],
                                ENT_QUOTES,
                                'UTF-8'
                              )
                            : '—';
                        ?>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Purchased From</div>
                    <div class="info-value">
                        <?php
                        echo ($device['purchased_from'] !== null && $device['purchased_from'] != '')
                            ? htmlspecialchars(
                                $device['purchased_from'],
                                ENT_QUOTES,
                                'UTF-8'
                              )
                            : '—';
                        ?>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Purchase Price</div>
                    <div class="info-value">
                        <?php
                        if ($device['purchase_price'] !== null) {

                            echo '₹' .
                                number_format(
                                    (float)$device['purchase_price'],
                                    2
                                );

                        } else {

                            echo '—';
                        }
                        ?>
                    </div>
                </div>

            </div>

        </div>


        <!-- =============================================
             SALE HISTORY
             ============================================= -->

        <div class="card">

            <div class="card-title">
                Sale History
            </div>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Sold To</th>
                            <th>Property</th>
                            <th>Quantity</th>
                            <th>Selling Price</th>
                            <th>Total</th>
                            <th>Sale Date</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (count($sales) == 0) { ?>

                            <tr class="empty-row">
                                <td colspan="6">
                                    This device has not been sold.
                                </td>
                            </tr>

                        <?php } else { ?>

                            <?php foreach ($sales as $sale) { ?>

                                <tr>
                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $sale['sold_to'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo ($sale['property_name'] !== null && $sale['property_name'] != '')
                                            ? htmlspecialchars(
                                                $sale['property_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                              )
                                            : '—';
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo (int)$sale['quantity']; ?>
                                    </td>
                                    <td>
                                        ₹<?php
                                        echo number_format(
                                            (float)$sale['selling_price'],
                                            2
                                        );
                                        ?>
                                    </td>
                                    <td>
                                        ₹<?php
                                        echo number_format(
                                            (float)$sale['total_price'],
                                            2
                                        );
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $sale['sale_date'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>
                                    </td>
                                </tr>

                            <?php } ?>

                        <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>


        <!-- =============================================
             SEND HISTORY
             ============================================= -->

        <div class="card">

            <div class="card-title">
                Send History
            </div>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Sent To</th>
                            <th>Property</th>
                            <th>Quantity</th>
                            <th>Sent Date</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (count($sends) == 0) { ?>

                            <tr class="empty-row">
                                <td colspan="4">
                                    This device has not been sent out.
                                </td>
                            </tr>

                        <?php } else { ?>

                            <?php foreach ($sends as $send) { ?>

                                <tr>
                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $send['sent_to'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo ($send['property_name'] !== null && $send['property_name'] != '')
                                            ? htmlspecialchars(
                                                $send['property_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                              )
                                            : '—';
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo (int)$send['quantity']; ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $send['sent_date'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>
                                    </td>
                                </tr>

                            <?php } ?>

                        <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>


        <!-- =============================================
             AUDIT TRAIL
             ============================================= -->

        <div class="card">

            <div class="card-title">
                Audit Trail
            </div>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Description</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (count($audit_entries) == 0) { ?>

                            <tr class="empty-row">
                                <td colspan="4">
                                    No audit entries for this device yet.
                                </td>
                            </tr>

                        <?php } else { ?>

                            <?php foreach ($audit_entries as $entry) { ?>

                                <tr>
                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $entry['created_at'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo ($entry['username'] !== null && $entry['username'] != '')
                                            ? htmlspecialchars(
                                                $entry['username'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                              )
                                            : 'System';
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $entry['action'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo ($entry['description'] !== null && $entry['description'] != '')
                                            ? htmlspecialchars(
                                                $entry['description'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                              )
                                            : '—';
                                        ?>
                                    </td>
                                </tr>

                            <?php } ?>

                        <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>


    <?php } ?>


</div>

</body>

</html>
