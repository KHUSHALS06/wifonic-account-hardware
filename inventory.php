<?php

require_once 'auth.php';

require_login();

$role = $_SESSION['role_name'];
$username = $_SESSION['username'];

$search = isset($_GET['search'])
    ? trim($_GET['search'])
    : '';

$search_safe = mysql_real_escape_string(
    $search,
    $conn
);

$query = "
    SELECT
        id,
        name,
        sn,
        mac,
        purchase_price AS price,
        purchase_date,
        purchased_from,
        quantity
    FROM devices
    WHERE status = 'Available'
    AND quantity > 0
";

if ($search != '') {

    $query .= "
        AND (
            name LIKE '%$search_safe%'
            OR sn LIKE '%$search_safe%'
            OR mac LIKE '%$search_safe%'
            OR purchased_from LIKE '%$search_safe%'
        )
    ";

}

$query .= "
    ORDER BY id DESC
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

$devices = array();

while ($row = mysql_fetch_assoc($result)) {

    $devices[] = $row;

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
    Inventory - Wifonic Hardware
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


@keyframes spin {

    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
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
        flex-end;

    justify-content:
        space-between;

    margin-bottom:
        25px;

    animation:
        riseIn 0.4s ease;

}


.page-title {

    margin:
        0;

    font-size:
        30px;

    font-weight:
        700;

    color:
        #000000;

}


.page-subtitle {

    margin-top:
        7px;

    color:
        #000000;

    font-size:
        14px;

}


.add-button {

    height:
        44px;

    padding:
        0 20px;

    border:
        none;

    border-radius:
        11px;

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    gap:
        8px;

    text-decoration:
        none;

    color:
        #000000;

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
        0 6px 20px
        rgba(
            31,
            38,
            135,
            0.14
        );

    transition:
        0.2s ease;

}


.add-button:hover {

    transform:
        translateY(-2px);

}


.search-card {

    padding:
        18px;

    border-radius:
        18px;

    margin-bottom:
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
            0.55
        );

    box-shadow:
        0 8px 25px
        rgba(
            31,
            38,
            135,
            0.08
        );

    backdrop-filter:
        blur(16px);

    -webkit-backdrop-filter:
        blur(16px);

    animation:
        riseIn 0.45s ease;

}


.search-form {

    display:
        flex;

    gap:
        10px;

}


.search-wrapper {

    position:
        relative;

    flex:
        1;

}


.search-icon {

    position:
        absolute;

    left:
        15px;

    top:
        50%;

    transform:
        translateY(-50%);

    color:
        #000000;

    font-size:
        15px;

}


.search-input {

    width:
        100%;

    height:
        46px;

    padding:
        0 15px 0 42px;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            0.60
        );

    outline:
        none;

    border-radius:
        11px;

    background:
        rgba(
            255,
            255,
            255,
            0.52
        );

    color:
        #000000;

    font-size:
        14px;

    transition:
        0.2s ease;

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
            0.20
        );

}


.search-button {

    height:
        46px;

    padding:
        0 22px;

    border:
        none;

    border-radius:
        11px;

    cursor:
        pointer;

    color:
        #000000;

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

    transition:
        0.2s ease;

}


.search-button:hover {

    transform:
        translateY(-1px);

}


.clear-button {

    height:
        46px;

    display:
        flex;

    align-items:
        center;

    padding:
        0 18px;

    text-decoration:
        none;

    border-radius:
        11px;

    color:
        #000000;

    background:
        rgba(
            255,
            255,
            255,
            0.45
        );

    transition:
        0.2s ease;

}


.clear-button:hover {

    background:
        rgba(
            255,
            255,
            255,
            0.65
        );

}


.inventory-card {

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

    animation:
        riseIn 0.5s ease;

}


.table-wrapper {

    overflow-x:
        auto;

}


table {

    width:
        100%;

    border-collapse:
        collapse;

}


thead {

    background:
        rgba(
            255,
            255,
            255,
            0.30
        );

}


th {

    padding:
        17px 16px;

    text-align:
        left;

    font-size:
        11px;

    text-transform:
        uppercase;

    letter-spacing:
        0.5px;

    color:
        #000000;

    border-bottom:
        1px solid
        rgba(
            255,
            255,
            255,
            0.45
        );

    white-space:
        nowrap;

}


td {

    padding:
        16px;

    font-size:
        13px;

    color:
        #000000;

    border-bottom:
        1px solid
        rgba(
            255,
            255,
            255,
            0.35
        );

    white-space:
        nowrap;

}


tbody tr {

    transition:
        0.18s ease;

    animation:
        riseIn 0.4s ease;

}


tbody tr:hover {

    background:
        rgba(
            255,
            255,
            255,
            0.25
        );

}


tbody tr:last-child td {

    border-bottom:
        none;

}


.device-name {

    font-weight:
        bold;

    color:
        #000000;

}


.mono {

    font-family:
        "Courier New",
        monospace;

    font-size:
        12px;

    color:
        #000000;

}


.price {

    font-weight:
        bold;

    color:
        #000000;

}


.quantity {

    font-weight:
        bold;

    text-align:
        center;

    color:
        #000000;

}


.select-cell {

    width:
        45px;

    text-align:
        center;

}


.device-checkbox {

    width:
        17px;

    height:
        17px;

    cursor:
        pointer;

    accent-color:
        #74ebd5;

}


.action {

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    height:
        34px;

    padding:
        0 12px;

    border-radius:
        9px;

    text-decoration:
        none;

    color:
        #000000;

    font-size:
        12px;

    font-weight:
        bold;

    transition:
        0.2s ease;

}


.action:hover {

    transform:
        translateY(-1px);

}


.send-button {

    color:
        #000000;

    background:
        rgba(
            116,
            235,
            213,
            0.65
        );

}


.sell-button {

    color:
        #000000;

    background:
        rgba(
            172,
            182,
            229,
            0.75
        );

}


.bulk-bar {

    display:
        none;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        15px;

    padding:
        14px 18px;

    background:
        rgba(
            255,
            255,
            255,
            0.50
        );

    border-bottom:
        1px solid
        rgba(
            255,
            255,
            255,
            0.45
        );

}


.bulk-bar.active {

    display:
        flex;

    animation:
        riseIn 0.25s ease;

}


.selected-count {

    font-size:
        13px;

    font-weight:
        bold;

    color:
        #000000;

}


.bulk-actions {

    display:
        flex;

    gap:
        8px;

}


.bulk-button {

    height:
        36px;

    padding:
        0 15px;

    border:
        none;

    border-radius:
        9px;

    cursor:
        pointer;

    color:
        #000000;

    font-size:
        12px;

    font-weight:
        bold;

    transition:
        0.2s ease;

}


.bulk-button:hover {

    transform:
        translateY(-1px);

}


.bulk-send {

    background:
        #74ebd5;

}


.bulk-sell {

    background:
        #acb6e5;

}


.empty {

    text-align:
        center;

    padding:
        80px 20px;

    animation:
        fadeIn 0.5s ease;

}


.empty-icon {

    width:
        65px;

    height:
        65px;

    margin:
        0 auto 15px;

    border-radius:
        18px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    font-size:
        22px;

    font-weight:
        bold;

    color:
        #000000;

    background:
        rgba(
            255,
            255,
            255,
            0.40
        );

    animation:
        spin 2.5s linear infinite;

}


.empty-title {

    font-size:
        19px;

    font-weight:
        bold;

    margin-bottom:
        7px;

    color:
        #000000;

}


.empty-text {

    color:
        #000000;

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


@media (max-width: 800px) {

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
            30px 12px 40px;

    }

    .page-header {

        align-items:
            flex-start;

        flex-direction:
            column;

        gap:
            15px;

    }

    .add-button {

        width:
            100%;

        justify-content:
            center;

    }

    .search-form {

        flex-wrap:
            wrap;

    }

    .search-wrapper {

        flex-basis:
            100%;

    }

    .search-button,
    .clear-button {

        flex:
            1;

        justify-content:
            center;

    }

    .bulk-bar {

        align-items:
            flex-start;

        flex-direction:
            column;

    }

    .bulk-actions {

        width:
            100%;

    }

    .bulk-button {

        flex:
            1;

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
            href="inventory.php"
            class="nav-link active"
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

            <h1 class="page-title">
                Inventory
            </h1>

            <div class="page-subtitle">
                Select devices to send or add to the sell cart
            </div>

        </div>

        <?php if (
            $role == 'admin' ||
            $role == 'manager'
        ) { ?>

            <a
                href="add_device.php"
                class="add-button"
            >
                + Add Device
            </a>

        <?php } ?>

    </div>


    <div class="search-card">

        <form
            method="get"
            action="inventory.php"
            class="search-form"
        >

            <div class="search-wrapper">

                <input
                    type="text"
                    name="search"
                    class="search-input"
                    placeholder="Search by name, SN, MAC or purchased from..."
                    value="<?php

                    echo htmlspecialchars(
                        $search,
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    ?>"
                >

            </div>

            <button
                type="submit"
                class="search-button"
            >
                Search
            </button>

            <?php if (
                $search != ''
            ) { ?>

                <a
                    href="inventory.php"
                    class="clear-button"
                >
                    Clear
                </a>

            <?php } ?>

        </form>

    </div>


    <div class="inventory-card">

        <div
            id="bulkBar"
            class="bulk-bar"
        >

            <div
                id="selectedCount"
                class="selected-count"
            >
                0 selected
            </div>

            <div class="bulk-actions">

                <button
                    type="button"
                    class="bulk-button bulk-send"
                    onclick="sendSelected()"
                >
                    Send Selected
                </button>

                <button
                    type="button"
                    class="bulk-button bulk-sell"
                    onclick="sellSelected()"
                >
                    Add to Sell Cart
                </button>

            </div>

        </div>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th class="select-cell">

                            <input
                                type="checkbox"
                                id="selectAll"
                                class="device-checkbox"
                                onclick="toggleAll(this)"
                            >

                        </th>

                        <th>
                            Name
                        </th>

                        <th>
                            SN
                        </th>

                        <th>
                            MAC
                        </th>

                        <?php if (
                            $role == 'admin' ||
                            $role == 'manager'
                        ) { ?>

                            <th>
                                Price
                            </th>

                        <?php } ?>

                        <th>
                            Date of Purchase
                        </th>

                        <th>
                            Purchased From
                        </th>

                        <th>
                            Available Qty
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php if (
                    count($devices) == 0
                ) { ?>

                    <tr>

                        <td
                            colspan="9"
                        >

                            <div class="empty">

                                <div class="empty-icon">
                                    0
                                </div>

                                <div class="empty-title">
                                    Inventory is empty
                                </div>

                                <div class="empty-text">

                                    <?php if (
                                        $search != ''
                                    ) { ?>

                                        No devices match
                                        your search.

                                    <?php } else { ?>

                                        Add a device
                                        to your inventory.

                                    <?php } ?>

                                </div>

                            </div>

                        </td>

                    </tr>

                <?php } else { ?>

                    <?php foreach (
                        $devices as $device
                    ) { ?>

                        <tr>

                            <td
                                class="select-cell"
                            >

                                <input
                                    type="checkbox"
                                    class="device-checkbox device-select"
                                    value="<?php
                                        echo (int)
                                            $device['id'];
                                    ?>"
                                    onclick="updateSelection()"
                                >

                            </td>

                            <td>

                                <span
                                    class="device-name"
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $device['name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );

                                    ?>

                                </span>

                            </td>

                            <td>

                                <span
                                    class="mono"
                                >

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

                                <span
                                    class="mono"
                                >

                                    <?php

                                    if (
                                        $device['mac'] != ''
                                    ) {

                                        echo htmlspecialchars(
                                            $device['mac'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );

                                    } else {

                                        echo '-';

                                    }

                                    ?>

                                </span>

                            </td>

                            <?php if (
                                $role == 'admin' ||
                                $role == 'manager'
                            ) { ?>

                                <td>

                                    <span
                                        class="price"
                                    >

                                        <?php

                                        if (
                                            $device['price'] !== null &&
                                            $device['price'] !== ''
                                        ) {

                                            echo '₹' .
                                                number_format(
                                                    $device['price'],
                                                    2
                                                );

                                        } else {

                                            echo '-';

                                        }

                                        ?>

                                    </span>

                                </td>

                            <?php } ?>

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $device['purchase_date'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );

                                ?>

                            </td>

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $device['purchased_from'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );

                                ?>

                            </td>

                            <td
                                class="quantity"
                            >

                                <?php

                                echo (int)
                                    $device['quantity'];

                                ?>

                            </td>

                            <td>

                                <a
                                    href="send_device.php?id=<?php
                                        echo (int)
                                            $device['id'];
                                    ?>"
                                    class="action send-button"
                                >
                                    Send
                                </a>

                                <a
                                    href="sell_device.php?id=<?php
                                        echo (int)
                                            $device['id'];
                                    ?>"
                                    class="action sell-button"
                                >
                                    Sell
                                </a>

                            </td>

                        </tr>

                    <?php } ?>

                <?php } ?>

                </tbody>

            </table>

        </div>

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

function toggleAll(source) {

    var checkboxes = document.getElementsByClassName('device-select');

    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }

    updateSelection();
}

function updateSelection() {

    var checkboxes = document.getElementsByClassName('device-select');

    var selected = [];

    for (var i = 0; i < checkboxes.length; i++) {
        if (checkboxes[i].checked) {
            selected.push(checkboxes[i].value);
        }
    }

    var bulkBar = document.getElementById('bulkBar');
    var selectedCount = document.getElementById('selectedCount');

    if (selected.length > 0) {
        bulkBar.className = 'bulk-bar active';
        selectedCount.innerHTML = selected.length + ' selected';
    } else {
        bulkBar.className = 'bulk-bar';
        selectedCount.innerHTML = '0 selected';
    }

}

function getSelected() {

    var checkboxes = document.getElementsByClassName('device-select');

    var selected = [];

    for (var i = 0; i < checkboxes.length; i++) {
        if (checkboxes[i].checked) {
            selected.push(checkboxes[i].value);
        }
    }

    return selected;
}

function sendSelected() {

    var selected = getSelected();

    if (selected.length == 0) {
        alert('Please select at least one device.');
        return;
    }

    window.location = 'send_device.php?ids=' + selected.join(',');

}

function sellSelected() {

    var selected = getSelected();

    if (selected.length == 0) {
        alert('Please select at least one device.');
        return;
    }

    window.location = 'sell_device.php?ids=' + selected.join(',');

}

</script>

</body>

</html>
