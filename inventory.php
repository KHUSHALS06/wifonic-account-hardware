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


/*
 * Only show devices that still have
 * inventory available.
 */
$query = "
    SELECT
        id,
        name,
        sn,
        mac,
        price,
        purchase_date,
        purchased_from,
        quantity
    FROM devices
    WHERE quantity > 0
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

body {

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    color: #263238;

    background:
        linear-gradient(
            135deg,
            #74ebd5 0%,
            #acb6e5 100%
        );

    background-attachment: fixed;

    min-height: 100vh;
}


/* =========================================================
   BACKGROUND
   ========================================================= */

body:before {

    content: "";

    position: fixed;

    width: 320px;
    height: 320px;

    border-radius: 50%;

    background:
        rgba(
            255,
            255,
            255,
            0.20
        );

    top: -120px;
    left: -90px;

    pointer-events: none;
}

body:after {

    content: "";

    position: fixed;

    width: 360px;
    height: 360px;

    border-radius: 50%;

    background:
        rgba(
            255,
            255,
            255,
            0.16
        );

    bottom: -150px;
    right: -100px;

    pointer-events: none;
}


/* =========================================================
   HEADER
   ========================================================= */

.header {

    position: relative;

    z-index: 10;

    margin: 18px 25px 0;

    min-height: 70px;

    padding: 0 25px;

    border-radius: 18px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    background:
        rgba(
            255,
            255,
            255,
            0.45
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


/* Logo */

.logo {

    display: flex;

    align-items: center;

    gap: 12px;
}

.logo-icon {

    width: 42px;
    height: 42px;

    border-radius: 12px;

    display: flex;

    align-items: center;
    justify-content: center;

    color: #ffffff;

    font-size: 20px;

    font-weight: bold;

    background:
        linear-gradient(
            135deg,
            #74ebd5,
            #acb6e5
        );

    box-shadow:
        0 6px 18px
        rgba(
            116,
            235,
            213,
            0.25
        );
}

.logo-text {

    font-size: 19px;

    font-weight: bold;
}


/* Header right */

.header-right {

    display: flex;

    align-items: center;

    gap: 15px;
}

.user-box {

    padding:
        8px 14px;

    border-radius:
        12px;

    background:
        rgba(
            255,
            255,
            255,
            0.35
        );

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            0.45
        );

    font-size: 13px;
}

.username {

    font-weight: bold;
}

.role {

    margin-left: 5px;

    color: #666;
}

.logout {

    text-decoration: none;

    color: #ffffff;

    padding:
        10px 17px;

    border-radius:
        10px;

    font-size: 13px;

    font-weight: bold;

    background:
        rgba(
            198,
            40,
            40,
            0.82
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
            0.95
        );

    transform:
        translateY(-1px);
}


/* =========================================================
   MAIN
   ========================================================= */

.container {

    position: relative;

    z-index: 2;

    max-width: 1500px;

    margin: 0 auto;

    padding:
        40px 25px 60px;
}


/* =========================================================
   PAGE HEADER
   ========================================================= */

.page-header {

    display: flex;

    justify-content:
        space-between;

    align-items:
        flex-end;

    margin-bottom:
        25px;
}

.page-title {

    margin: 0;

    font-size:
        30px;

    font-weight:
        700;
}

.page-subtitle {

    margin-top:
        7px;

    font-size:
        14px;

    color:
        rgba(
            38,
            50,
            56,
            0.65
        );
}


/* =========================================================
   ADD BUTTON
   ========================================================= */

.add-button {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        8px;

    height:
        44px;

    padding:
        0 20px;

    text-decoration:
        none;

    border-radius:
        11px;

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


/* =========================================================
   SEARCH
   ========================================================= */

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
        #777;

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
        #263238;

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
            0.16
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
        #555;

    background:
        rgba(
            255,
            255,
            255,
            0.45
        );
}


/* =========================================================
   TABLE
   ========================================================= */

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
        rgba(
            38,
            50,
            56,
            0.65
        );

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


/* =========================================================
   DEVICE
   ========================================================= */

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

    color:
        #555;
}

.price {

    font-weight:
        bold;
}

.quantity {

    font-weight:
        bold;

    text-align:
        center;
}


/* =========================================================
   SELECT
   ========================================================= */

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


/* =========================================================
   ACTIONS
   ========================================================= */

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
        #263238;

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
        #263238;

    background:
        rgba(
            172,
            182,
            229,
            0.75
        );
}


/* =========================================================
   BULK BAR
   ========================================================= */

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
}

.selected-count {

    font-size:
        13px;

    font-weight:
        bold;
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

    font-size:
        12px;

    font-weight:
        bold;
}

.bulk-send {

    color:
        #263238;

    background:
        #74ebd5;
}

.bulk-sell {

    color:
        #263238;

    background:
        #acb6e5;
}


/* =========================================================
   EMPTY
   ========================================================= */

.empty {

    text-align:
        center;

    padding:
        70px 20px;
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
        27px;

    background:
        rgba(
            255,
            255,
            255,
            0.40
        );
}

.empty-title {

    font-size:
        18px;

    font-weight:
        bold;

    margin-bottom:
        7px;
}

.empty-text {

    font-size:
        13px;

    color:
        #777;
}


/* =========================================================
   MOBILE
   ========================================================= */

@media (max-width: 800px) {

    .header {

        margin:
            10px;

        padding:
            0 15px;
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


    <!-- =================================================
         SEARCH
         ================================================= -->

    <div class="search-card">


        <form
            method="get"
            action="inventory.php"
            class="search-form"
        >


            <div class="search-wrapper">

                <span class="search-icon">
                    🔍
                </span>

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


    <!-- =================================================
         INVENTORY TABLE
         ================================================= -->

    <div class="inventory-card">


        <!-- BULK BAR -->

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
                                    📦
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


                            <!-- SELECT -->

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


                            <!-- NAME -->

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


                            <!-- SN -->

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


                            <!-- MAC -->

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

                                        echo '—';

                                    }

                                    ?>

                                </span>

                            </td>


                            <!-- PRICE -->

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

                                            echo '—';

                                        }

                                        ?>

                                    </span>

                                </td>


                            <?php } ?>


                            <!-- PURCHASE DATE -->

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $device['purchase_date'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );

                                ?>

                            </td>


                            <!-- PURCHASED FROM -->

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $device['purchased_from'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );

                                ?>

                            </td>


                            <!-- QUANTITY -->

                            <td
                                class="quantity"
                            >

                                <?php

                                echo (int)
                                    $device['quantity'];

                                ?>

                            </td>


                            <!-- ACTION -->

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
                                    href="sell_cart.php?add=<?php
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

/*
 * Select all
 */

function toggleAll(source) {

    var checkboxes =
        document.getElementsByClassName(
            'device-select'
        );

    for (
        var i = 0;
        i < checkboxes.length;
        i++
    ) {

        checkboxes[i].checked =
            source.checked;

    }

    updateSelection();
}


/*
 * Update selected count
 */

function updateSelection() {

    var checkboxes =
        document.getElementsByClassName(
            'device-select'
        );

    var selected = [];

    for (
        var i = 0;
        i < checkboxes.length;
        i++
    ) {

        if (
            checkboxes[i].checked
        ) {

            selected.push(
                checkboxes[i].value
            );

        }

    }


    var bulkBar =
        document.getElementById(
            'bulkBar'
        );

    var selectedCount =
        document.getElementById(
            'selectedCount'
        );


    if (
        selected.length > 0
    ) {

        bulkBar.className =
            'bulk-bar active';

        selectedCount.innerHTML =
            selected.length +
            ' selected';

    } else {

        bulkBar.className =
            'bulk-bar';

        selectedCount.innerHTML =
            '0 selected';

    }

}


/*
 * Get selected IDs
 */

function getSelected() {

    var checkboxes =
        document.getElementsByClassName(
            'device-select'
        );

    var selected = [];

    for (
        var i = 0;
        i < checkboxes.length;
        i++
    ) {

        if (
            checkboxes[i].checked
        ) {

            selected.push(
                checkboxes[i].value
            );

        }

    }

    return selected;
}


/*
 * Send selected devices
 */

function sendSelected() {

    var selected =
        getSelected();


    if (
        selected.length == 0
    ) {

        alert(
            'Please select at least one device.'
        );

        return;

    }


    window.location =
        'send_device.php?ids=' +
        selected.join(',');

}


/*
 * Add selected devices to
 * sell cart
 */

function sellSelected() {

    var selected =
        getSelected();


    if (
        selected.length == 0
    ) {

        alert(
            'Please select at least one device.'
        );

        return;

    }


    window.location =
        'sell_cart.php?ids=' +
        selected.join(',');

}

</script>


</body>

</html>
