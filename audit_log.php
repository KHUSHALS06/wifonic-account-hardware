<?php

require_once 'auth.php';

require_roles(array('admin'));

$username = $_SESSION['username'];
$role = $_SESSION['role_name'];

$search = '';
$action_filter = '';
$date_from = '';
$date_to = '';

if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

if (isset($_GET['action'])) {
    $action_filter = trim($_GET['action']);
}

if (isset($_GET['date_from'])) {
    $date_from = trim($_GET['date_from']);
}

if (isset($_GET['date_to'])) {
    $date_to = trim($_GET['date_to']);
}


/*
 * =========================================================
 * PAGINATION
 * =========================================================
 */

$per_page = 50;

$page = 1;

if (isset($_GET['page']) && ctype_digit($_GET['page'])) {
    $page = (int)$_GET['page'];
}

if ($page < 1) {
    $page = 1;
}

$offset = ($page - 1) * $per_page;


/*
 * =========================================================
 * DISTINCT ACTIONS (for the filter dropdown)
 * =========================================================
 */

$actions = array();

$actions_result = mysql_query(
    "SELECT DISTINCT action FROM audit_log ORDER BY action ASC",
    $conn
);

if ($actions_result) {
    while ($action_row = mysql_fetch_assoc($actions_result)) {
        $actions[] = $action_row['action'];
    }
}


/*
 * =========================================================
 * BUILD FILTER
 * =========================================================
 */

$where = "
    WHERE 1 = 1
";

if ($search != '') {

    $search_safe = mysql_real_escape_string(
        $search,
        $conn
    );

    $where .= "
        AND (
            username LIKE '%$search_safe%'
            OR sn LIKE '%$search_safe%'
            OR description LIKE '%$search_safe%'
            OR ip_address LIKE '%$search_safe%'
        )
    ";
}

if ($action_filter != '' && in_array($action_filter, $actions, true)) {

    $action_safe = mysql_real_escape_string(
        $action_filter,
        $conn
    );

    $where .= "
        AND action = '$action_safe'
    ";
}

if ($date_from != '') {

    $date_from_safe = mysql_real_escape_string(
        $date_from,
        $conn
    );

    $where .= "
        AND created_at >= '$date_from_safe 00:00:00'
    ";
}

if ($date_to != '') {

    $date_to_safe = mysql_real_escape_string(
        $date_to,
        $conn
    );

    $where .= "
        AND created_at <= '$date_to_safe 23:59:59'
    ";
}


/*
 * =========================================================
 * COUNT (for pagination)
 * =========================================================
 */

$count_query = "
    SELECT COUNT(*) AS total
    FROM audit_log
    $where
";

$count_result = mysql_query($count_query, $conn);

$total_rows = 0;

if ($count_result) {
    $count_row = mysql_fetch_assoc($count_result);
    $total_rows = (int)$count_row['total'];
}

$total_pages = max(1, ceil($total_rows / $per_page));

if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $per_page;
}


/*
 * =========================================================
 * LOAD LOGS
 * =========================================================
 */

$query = "
    SELECT
        id,
        user_id,
        username,
        action,
        device_id,
        sn,
        description,
        old_data,
        new_data,
        ip_address,
        created_at
    FROM audit_log
    $where
    ORDER BY id DESC
    LIMIT $offset, $per_page
";

$result = mysql_query($query, $conn);

if (!$result) {
    die('Database error: ' . mysql_error($conn));
}


/*
 * Helper to keep querystring across pagination /
 * filter links.
 */

function build_query_string($overrides = array())
{
    $params = array_merge($_GET, $overrides);

    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }

    return htmlspecialchars(
        http_build_query($params),
        ENT_QUOTES,
        'UTF-8'
    );
}

?>
<!DOCTYPE html>
<html>

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Audit Logs - Wifonic Hardware</title>

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
    font-family: Arial, Helvetica, sans-serif;
    color: #263238;
    min-height: 100vh;
    background: linear-gradient(135deg, #74ebd5 0%, #acb6e5 100%);
    background-attachment: fixed;
}

.header {
    position: relative;
    z-index: 10;
    margin: 18px 25px 0;
    min-height: 70px;
    padding: 0 22px;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    background: rgba(255, 255, 255, 0.42);
    border: 1px solid rgba(255, 255, 255, 0.60);
    box-shadow: 0 8px 30px rgba(31, 38, 135, 0.12);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
}

.logo {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.logo-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 18px;
    font-weight: bold;
    background: linear-gradient(135deg, #74ebd5, #acb6e5);
}

.logo-text {
    font-size: 18px;
    font-weight: bold;
    white-space: nowrap;
}

.navigation {
    display: flex;
    align-items: center;
    gap: 5px;
    flex: 1;
    justify-content: center;
    flex-wrap: wrap;
}

.nav-link {
    padding: 10px 13px;
    border-radius: 10px;
    text-decoration: none;
    color: #455a64;
    font-size: 12px;
    font-weight: bold;
    transition: 0.2s;
    white-space: nowrap;
}

.nav-link:hover {
    background: rgba(255, 255, 255, 0.45);
}

.nav-link.active {
    color: #263238;
    background: rgba(255, 255, 255, 0.62);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.header-right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.user-box {
    padding: 8px 12px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.30);
    font-size: 12px;
}

.username {
    font-weight: bold;
}

.role {
    margin-left: 5px;
    color: #666;
}

.logout {
    padding: 10px 14px;
    border-radius: 9px;
    color: #ffffff;
    text-decoration: none;
    font-size: 12px;
    font-weight: bold;
    background: rgba(198, 40, 40, 0.82);
}

.container {
    position: relative;
    z-index: 2;
    max-width: 1500px;
    margin: 0 auto;
    padding: 38px 25px 60px;
}

.page-header {
    margin-bottom: 22px;
}

.title {
    margin: 0;
    font-size: 30px;
    font-weight: 700;
}

.subtitle {
    margin-top: 7px;
    color: rgba(38, 50, 56, 0.65);
    font-size: 14px;
}

.card {
    overflow: hidden;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.42);
    border: 1px solid rgba(255, 255, 255, 0.60);
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.10);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
}

.filter-area {
    padding: 18px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.40);
}

.filter-form {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.filter-input,
.filter-select {
    height: 44px;
    padding: 0 14px;
    border: 1px solid rgba(255, 255, 255, 0.60);
    border-radius: 10px;
    outline: none;
    background: rgba(255, 255, 255, 0.55);
    font-size: 13px;
}

.filter-input.search {
    flex: 1;
    min-width: 220px;
}

.filter-button {
    height: 44px;
    padding: 0 20px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    background: rgba(255, 255, 255, 0.55);
    font-size: 13px;
    font-weight: bold;
}

.clear-filters {
    height: 44px;
    padding: 0 15px;
    display: inline-flex;
    align-items: center;
    text-decoration: none;
    border-radius: 10px;
    color: #555;
    background: rgba(255, 255, 255, 0.35);
    font-size: 13px;
}

.results-count {
    padding: 12px 18px;
    font-size: 12px;
    color: rgba(38, 50, 56, 0.6);
    border-bottom: 1px solid rgba(255, 255, 255, 0.30);
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 1100px;
    border-collapse: collapse;
}

thead {
    background: rgba(255, 255, 255, 0.28);
}

th {
    padding: 15px 14px;
    text-align: left;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: rgba(38, 50, 56, 0.68);
    white-space: nowrap;
}

td {
    padding: 14px;
    font-size: 13px;
    vertical-align: top;
    border-top: 1px solid rgba(255, 255, 255, 0.30);
}

tbody tr:hover {
    background: rgba(255, 255, 255, 0.20);
}

.mono {
    font-family: "Courier New", monospace;
    font-size: 12px;
    white-space: nowrap;
}

.action-badge {
    display: inline-flex;
    padding: 5px 9px;
    border-radius: 7px;
    background: rgba(255, 255, 255, 0.45);
    font-size: 10px;
    font-weight: bold;
    white-space: nowrap;
}

.description-cell {
    max-width: 320px;
}

details {
    margin-top: 6px;
}

summary {
    cursor: pointer;
    font-size: 11px;
    color: #1565c0;
    font-weight: bold;
}

pre {
    margin: 8px 0 0;
    padding: 10px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.55);
    font-size: 11px;
    white-space: pre-wrap;
    word-break: break-all;
    max-width: 320px;
}

.empty {
    padding: 60px 20px;
    text-align: center;
}

.empty-icon {
    font-size: 40px;
    margin-bottom: 12px;
}

.pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 20px;
}

.page-link {
    padding: 8px 14px;
    border-radius: 8px;
    text-decoration: none;
    color: #37474f;
    background: rgba(255, 255, 255, 0.45);
    font-size: 12px;
    font-weight: bold;
}

.page-link.active {
    background: rgba(255, 255, 255, 0.80);
}

.page-link.disabled {
    opacity: 0.4;
    pointer-events: none;
}

@media (max-width: 900px) {

    .navigation {
        display: none;
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

        <a href="dashboard.php" class="nav-link">
            Dashboard
        </a>

        <a href="inventory.php" class="nav-link">
            Inventory
        </a>

        <a href="add_device.php" class="nav-link">
            Add Device
        </a>

        <a href="sell.php" class="nav-link">
            Sell
        </a>

        <a href="send.php" class="nav-link">
            Send
        </a>

        <a href="users.php" class="nav-link">
            Users
        </a>

        <a href="audit_log.php" class="nav-link active">
            Audit Logs
        </a>

    </nav>


    <div class="header-right">

        <div class="user-box">

            <span class="username">
                <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>
            </span>

            <span class="role">
                <?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>
            </span>

        </div>

        <a href="logout.php" class="logout">
            Logout
        </a>

    </div>


</div>


<div class="container">


    <div class="page-header">

        <h1 class="title">
            Audit Logs
        </h1>

        <div class="subtitle">
            History of actions taken across the system
        </div>

    </div>


    <div class="card">


        <div class="filter-area">

            <form method="get" action="audit_log.php" class="filter-form">

                <input
                    type="text"
                    name="search"
                    class="filter-input search"
                    placeholder="Search user, SN, description or IP..."
                    value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
                >

                <select name="action" class="filter-select">

                    <option value="">
                        All Actions
                    </option>

                    <?php foreach ($actions as $action_item) { ?>

                        <option
                            value="<?php echo htmlspecialchars($action_item, ENT_QUOTES, 'UTF-8'); ?>"
                            <?php echo ($action_filter === $action_item) ? 'selected' : ''; ?>
                        >
                            <?php echo htmlspecialchars($action_item, ENT_QUOTES, 'UTF-8'); ?>
                        </option>

                    <?php } ?>

                </select>

                <input
                    type="date"
                    name="date_from"
                    class="filter-input"
                    value="<?php echo htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8'); ?>"
                >

                <input
                    type="date"
                    name="date_to"
                    class="filter-input"
                    value="<?php echo htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8'); ?>"
                >

                <button type="submit" class="filter-button">
                    Filter
                </button>

                <?php if ($search != '' || $action_filter != '' || $date_from != '' || $date_to != '') { ?>

                    <a href="audit_log.php" class="clear-filters">
                        Clear
                    </a>

                <?php } ?>

            </form>

        </div>


        <div class="results-count">
            <?php echo $total_rows; ?> record<?php echo $total_rows === 1 ? '' : 's'; ?> found
        </div>


        <?php if (mysql_num_rows($result) > 0) { ?>


            <div class="table-wrapper">

                <table>

                    <thead>

                    <tr>
                        <th>Date</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>SN</th>
                        <th>Description</th>
                        <th>IP Address</th>
                    </tr>

                    </thead>

                    <tbody>

                    <?php while ($log = mysql_fetch_assoc($result)) { ?>

                        <tr>

                            <td class="mono">
                                <?php echo htmlspecialchars($log['created_at'], ENT_QUOTES, 'UTF-8'); ?>
                            </td>

                            <td>
                                <?php
                                    echo $log['username']
                                        ? htmlspecialchars($log['username'], ENT_QUOTES, 'UTF-8')
                                        : '—';
                                ?>
                            </td>

                            <td>
                                <span class="action-badge">
                                    <?php echo htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>

                            <td class="mono">
                                <?php
                                    echo $log['sn']
                                        ? htmlspecialchars($log['sn'], ENT_QUOTES, 'UTF-8')
                                        : '—';
                                ?>
                            </td>

                            <td class="description-cell">

                                <?php
                                    echo $log['description']
                                        ? htmlspecialchars($log['description'], ENT_QUOTES, 'UTF-8')
                                        : '—';
                                ?>

                                <?php if ($log['old_data'] || $log['new_data']) { ?>

                                    <details>

                                        <summary>
                                            View data
                                        </summary>

                                        <?php if ($log['old_data']) { ?>
                                            <pre><?php echo htmlspecialchars($log['old_data'], ENT_QUOTES, 'UTF-8'); ?></pre>
                                        <?php } ?>

                                        <?php if ($log['new_data']) { ?>
                                            <pre><?php echo htmlspecialchars($log['new_data'], ENT_QUOTES, 'UTF-8'); ?></pre>
                                        <?php } ?>

                                    </details>

                                <?php } ?>

                            </td>

                            <td class="mono">
                                <?php
                                    echo $log['ip_address']
                                        ? htmlspecialchars($log['ip_address'], ENT_QUOTES, 'UTF-8')
                                        : '—';
                                ?>
                            </td>

                        </tr>

                    <?php } ?>

                    </tbody>

                </table>

            </div>


            <?php if ($total_pages > 1) { ?>

                <div class="pagination">

                    <a
                        href="audit_log.php?<?php echo build_query_string(array('page' => $page - 1)); ?>"
                        class="page-link <?php echo ($page <= 1) ? 'disabled' : ''; ?>"
                    >
                        Prev
                    </a>

                    <span class="page-link active">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </span>

                    <a
                        href="audit_log.php?<?php echo build_query_string(array('page' => $page + 1)); ?>"
                        class="page-link <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>"
                    >
                        Next
                    </a>

                </div>

            <?php } ?>


        <?php } else { ?>

            <div class="empty">

                <div class="empty-icon">
                    📋
                </div>

                <div>
                    No audit log entries found.
                </div>

            </div>

        <?php } ?>


    </div>


</div>


</body>

</html>
