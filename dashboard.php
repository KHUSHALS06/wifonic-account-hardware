<?php

require_once 'auth.php';

require_login();

$username = $_SESSION['username'];
$role     = $_SESSION['role_name'];

$search = '';

if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}


if (
    isset($_GET['delete']) &&
    ctype_digit($_GET['delete'])
) {

    $device_id = (int)$_GET['delete'];

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
        $device_result &&
        mysql_num_rows($device_result) > 0
    ) {

        $device = mysql_fetch_assoc(
            $device_result
        );

        $old_data = json_encode($device);

        $delete_query = "
            DELETE FROM devices
            WHERE id = $device_id
            AND status = 'Available'
            LIMIT 1
        ";

        if (
            mysql_query(
                $delete_query,
                $conn
            )
        ) {

            require_once 'audit.php';

            audit_log_action(
                'DELETE_DEVICE',
                $device_id,
                $device['sn'],
                'Device deleted from inventory',
                $old_data,
                ''
            );
        }
    }

    header('Location: dashboard.php');
    exit;
}


$status_filter = '';

if (isset($_GET['status'])) {
    $status_filter = trim($_GET['status']);
}

$allowed_statuses = array('Available', 'Sold', 'Sent');

if (!in_array($status_filter, $allowed_statuses, true)) {
    $status_filter = '';
}


$where = "
    WHERE 1 = 1
";

if ($status_filter != '') {

    $status_safe = mysql_real_escape_string(
        $status_filter,
        $conn
    );

    $where .= "
        AND d.status = '$status_safe'
    ";
}

if ($search != '') {

    $search_safe = mysql_real_escape_string(
        $search,
        $conn
    );

    $where .= "
        AND (
            d.name LIKE '%$search_safe%'
            OR d.sn LIKE '%$search_safe%'
            OR d.mac LIKE '%$search_safe%'
            OR d.source LIKE '%$search_safe%'
            OR d.purchased_from LIKE '%$search_safe%'
            OR ds.sold_to LIKE '%$search_safe%'
            OR sd.sent_to LIKE '%$search_safe%'
        )
    ";
}


$query = "
    SELECT
        d.id,
        d.name,
        d.sn,
        d.mac,
        d.quantity,
        d.source,
        d.purchase_date,
        d.purchased_from,
        d.purchase_price,
        d.status,
        d.created_at,
        ds.sold_to,
        ds.property_name AS sold_property,
        ds.sale_date,
        sd.sent_to,
        sd.property_name AS sent_property,
        sd.sent_date
    FROM devices d
    LEFT JOIN (
        SELECT
            s1.device_id,
            s1.sold_to,
            s1.property_name,
            s1.sale_date
        FROM device_sales s1
        WHERE s1.id = (
            SELECT MAX(s2.id)
            FROM device_sales s2
            WHERE s2.device_id = s1.device_id
        )
    ) ds ON ds.device_id = d.id
    LEFT JOIN (
        SELECT
            n1.device_id,
            n1.sent_to,
            n1.property_name,
            n1.sent_date
        FROM device_sends n1
        WHERE n1.id = (
            SELECT MAX(n2.id)
            FROM device_sends n2
            WHERE n2.device_id = n1.device_id
        )
    ) sd ON sd.device_id = d.id
    $where
    ORDER BY d.id DESC
";

$result = mysql_query(
    $query,
    $conn
);

if (!$result) {

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
    Wifonic Hardware
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
        #455a64;

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
}

.dropdown-content a {
    display: block;
    padding: 10px 18px;
    text-decoration: none;
    color: #263238;
    font-size: 13px;
    transition: 0.2s;
}

.dropdown-content a:hover {
    background: rgba(116, 235, 213, 0.2);
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


.container {

    position:
        relative;

    z-index:
        2;

    max-width:
        1500px;

    margin:
        0 auto;

    padding:
        38px 25px 60px;

}


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


.add-button {

    height:
        42px;

    padding:
        0 18px;

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

}


.card {

    overflow:
        hidden;

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


.search-area {

    padding:
        18px;

    border-bottom:
        1px solid
        rgba(
            255,
            255,
            255,
            0.40
        );

}


.search-form {

    display:
        flex;

    gap:
        10px;

}


.search-input {

    flex:
        1;

    height:
        44px;

    padding:
        0 14px;

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
            0.55
        );

    font-size:
        14px;

}


.search-input:focus {

    background:
        rgba(
            255,
            255,
            255,
            0.75
        );

    box-shadow:
        0 0 0 3px
        rgba(
            116,
            235,
            213,
            0.15
        );

}


.status-select {

    height:
        44px;

    padding:
        0 12px;

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
            0.55
        );

    font-size:
        13px;

    cursor:
        pointer;

}


.search-button {

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

    background:
        rgba(
            255,
            255,
            255,
            0.55
        );

    font-size:
        13px;

    font-weight:
        bold;

}


.clear-search {

    height:
        44px;

    padding:
        0 15px;

    display:
        inline-flex;

    align-items:
        center;

    text-decoration:
        none;

    border-radius:
        10px;

    color:
        #555;

    background:
        rgba(
            255,
            255,
            255,
            0.35
        );

    font-size:
        13px;

}


.table-wrapper {

    overflow-x:
        auto;

}


table {

    width:
        100%;

    min-width:
        1250px;

    border-collapse:
        collapse;

}


thead {

    background:
        rgba(
            255,
            255,
            255,
            0.28
        );

}


th {

    padding:
        15px 14px;

    text-align:
        left;

    font-size:
        11px;

    text-transform:
        uppercase;

    letter-spacing:
        0.4px;

    color:
        rgba(
            38,
            50,
            56,
            0.68
        );

    white-space:
        nowrap;

}


td {

    padding:
        14px;

    font-size:
        13px;

    white-space:
        nowrap;

    border-top:
        1px solid
        rgba(
            255,
            255,
            255,
            0.30
        );

}


tbody tr:hover {

    background:
        rgba(
            255,
            255,
            255,
            0.20
        );

}


.device-name {

    font-weight:
        bold;

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
        6px 9px;

    border-radius:
        8px;

    background:
        rgba(
            255,
            255,
            255,
            0.42
        );

    font-size:
        11px;

    font-weight:
        bold;

}


.status {

    display:
        inline-flex;

    padding:
        6px 10px;

    border-radius:
        8px;

    color:
        #2e7d32;

    background:
        rgba(
            76,
            175,
            80,
            0.14
        );

    font-size:
        11px;

    font-weight:
        bold;

}


.status-sold {

    color:
        #1565c0;

    background:
        rgba(
            33,
            150,
            243,
            0.14
        );

}


.status-sent {

    color:
        #e65100;

    background:
        rgba(
            255,
            152,
            0,
            0.16
        );

}


.status-detail {

    display:
        block;

    margin-top:
        5px;

    font-size:
        11px;

    font-weight:
        normal;

    color:
        rgba(
            38,
            50,
            56,
            0.65
        );

    white-space:
        normal;

}


.actions {

    display:
        flex;

    gap:
        6px;

}


.action {

    padding:
        7px 9px;

    border-radius:
        7px;

    text-decoration:
        none;

    color:
        #37474f;

    background:
        rgba(
            255,
            255,
            255,
            0.40
        );

    font-size:
        11px;

    font-weight:
        bold;

}


.action:hover {

    background:
        rgba(
            255,
            255,
            255,
            0.70
        );

}


.action-delete {

    color:
        #c62828;

    background:
        rgba(
            239,
            83,
            80,
            0.10
        );

}


.empty {

    padding:
        80px 20px;

    text-align:
        center;

}


.empty-icon {

    font-size:
        40px;

    margin-bottom:
        12px;

}


.empty-title {

    font-size:
        19px;

    font-weight:
        bold;

    margin-bottom:
        7px;

}


.empty-text {

    color:
        #777;

    font-size:
        13px;

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

    .add-button {

        width:
            100%;

    }

    .search-form {

        flex-direction:
            column;

    }

    .search-button,
    .clear-search {

        width:
            100%;

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
            class="nav-link active"
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

<div class="container">

    <div class="page-header">

        <div>

            <h1 class="title">
                Wifonic
            </h1>

            <div class="subtitle">
                All hardware in stock, sold and sent
            </div>

        </div>

        <a
            href="add_device.php"
            class="add-button"
        >
            + Add Device
        </a>

    </div>

    <div class="card">

        <div class="search-area">

            <form
                method="get"
                action="dashboard.php"
                class="search-form"
            >

                <input
                    type="text"
                    name="search"
                    class="search-input"
                    placeholder="Search name, SN, MAC, source, sold to or sent to..."
                    value="<?php

                    echo htmlspecialchars(
                        $search,
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    ?>"
                >

                <select
                    name="status"
                    class="status-select"
                    onchange="this.form.submit()"
                >

                    <option value="">
                        All Statuses
                    </option>

                    <?php foreach ($allowed_statuses as $status_option) { ?>

                        <option
                            value="<?php
                                echo htmlspecialchars(
                                    $status_option,
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            ?>"
                            <?php

                            echo $status_filter === $status_option
                                ? 'selected'
                                : '';

                            ?>
                        >
                            <?php
                                echo htmlspecialchars(
                                    $status_option,
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            ?>
                        </option>

                    <?php } ?>

                </select>

                <button
                    type="submit"
                    class="search-button"
                >
                    Search
                </button>

                <?php if ($search != '' || $status_filter != '') { ?>

                    <a
                        href="dashboard.php"
                        class="clear-search"
                    >
                        Clear
                    </a>

                <?php } ?>

            </form>

        </div>

        <?php if (
            mysql_num_rows($result) > 0
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
                            Quantity
                        </th>

                        <th>
                            Source
                        </th>

                        <th>
                            Purchase Date
                        </th>

                        <th>
                            Purchased From
                        </th>

                        <th>
                            Price
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php while (
                        $device =
                        mysql_fetch_assoc($result)
                    ) { ?>

                        <tr>

                            <td>

                                <div class="device-name">

                                    <?php

                                    echo htmlspecialchars(
                                        $device['name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );

                                    ?>

                                </div>

                            </td>

                            <td>

                                <span class="mono">

                                    <?php

                                    echo htmlspecialchars(
                                        $device['sn'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );

                                    ?>

                                </span>

                            </td>

                            <td>

                                <span class="mono">

                                    <?php

                                    echo $device['mac']
                                        != ''
                                        ? htmlspecialchars(
                                            $device['mac'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        )
                                        : '';

                                    ?>

                                </span>

                            </td>

                            <td>

                                <?php

                                echo (int)
                                    $device['quantity'];

                                ?>

                            </td>

                            <td>

                                <span class="source">

                                    <?php

                                    echo htmlspecialchars(
                                        $device['source'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );

                                    ?>

                                </span>

                            </td>

                            <td>

                                <?php

                                echo $device['purchase_date']
                                    ? htmlspecialchars(
                                        $device[
                                            'purchase_date'
                                        ],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    )
                                    : '';

                                ?>

                            </td>

                            <td>

                                <?php

                                echo $device['purchased_from']
                                    ? htmlspecialchars(
                                        $device[
                                            'purchased_from'
                                        ],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    )
                                    : '';

                                ?>

                            </td>

                            <td>

                                <?php

                                if (
                                    $device[
                                        'purchase_price'
                                    ] !== null
                                ) {

                                    echo '₹' .
                                        number_format(
                                            (float)
                                            $device[
                                                'purchase_price'
                                            ],
                                            2
                                        );

                                } else {

                                    echo '';

                                }

                                ?>

                            </td>

                            <td>

                                <?php

                                $badge_class = 'status';

                                if ($device['status'] === 'Sold') {
                                    $badge_class .= ' status-sold';
                                } elseif ($device['status'] === 'Sent') {
                                    $badge_class .= ' status-sent';
                                }

                                ?>

                                <span class="<?php echo $badge_class; ?>">

                                    <?php
                                        echo htmlspecialchars(
                                            $device['status'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                    ?>

                                    <?php if (
                                        $device['status'] === 'Sold' &&
                                        $device['sold_to']
                                    ) { ?>

                                        <span class="status-detail">
                                            To:
                                            <?php
                                                echo htmlspecialchars(
                                                    $device['sold_to'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                            ?>
                                            <?php if ($device['sale_date']) { ?>
                                                on
                                                <?php
                                                    echo htmlspecialchars(
                                                        $device['sale_date'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    );
                                                ?>
                                            <?php } ?>
                                        </span>

                                    <?php } ?>

                                    <?php if (
                                        $device['status'] === 'Sent' &&
                                        $device['sent_to']
                                    ) { ?>

                                        <span class="status-detail">
                                            To:
                                            <?php
                                                echo htmlspecialchars(
                                                    $device['sent_to'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                            ?>
                                            <?php if ($device['sent_date']) { ?>
                                                on
                                                <?php
                                                    echo htmlspecialchars(
                                                        $device['sent_date'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    );
                                                ?>
                                            <?php } ?>
                                        </span>

                                    <?php } ?>

                                </span>

                            </td>

                            <td>

                                <div class="actions">

                                    <a
                                        href="edit_device.php?id=<?php
                                        echo (int)
                                            $device['id'];
                                        ?>"
                                        class="action"
                                    >
                                        Edit
                                    </a>

                                    <a
                                        href="device_details.php?id=<?php
                                        echo (int)
                                            $device['id'];
                                        ?>"
                                        class="action"
                                    >
                                        View
                                    </a>

                                    <?php if ($device['status'] === 'Available') { ?>

                                        <a
                                            href="sell.php?device_id=<?php
                                            echo (int)
                                                $device['id'];
                                            ?>"
                                            class="action"
                                        >
                                            Sell
                                        </a>

                                        <a
                                            href="send.php?device_id=<?php
                                            echo (int)
                                                $device['id'];
                                            ?>"
                                            class="action"
                                        >
                                            Send
                                        </a>

                                        <a
                                            href="dashboard.php?delete=<?php
                                            echo (int)
                                                $device['id'];
                                            ?>"
                                            class="action action-delete"
                                            onclick="return confirm('Delete this device?');"
                                        >
                                            Delete
                                        </a>

                                    <?php } ?>

                                </div>

                            </td>

                        </tr>

                    <?php } ?>

                    </tbody>

                </table>

            </div>

        <?php } else { ?>

            <div class="empty">

                <div class="empty-icon">
                    📦
                </div>

                <div class="empty-title">
                    No devices in inventory
                </div>

                <div class="empty-text">

                    <?php if ($search != '') { ?>

                        No available device matches your search.

                    <?php } else { ?>

                        Add a device to start building your inventory.

                    <?php } ?>

                </div>

            </div>

        <?php } ?>

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
</script>

</body>

</html>
