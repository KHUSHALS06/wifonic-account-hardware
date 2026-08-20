<?php

require_once 'auth.php';
require_once 'audit.php';

require_roles(array('admin'));

$username = $_SESSION['username'];
$role = $_SESSION['role_name'];
$current_user_id = (int)$_SESSION['user_id'];

$search = '';

if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

$error = '';


/*
 * =========================================================
 * TOGGLE STATUS
 * =========================================================
 */

if (
    isset($_GET['toggle_status']) &&
    ctype_digit($_GET['toggle_status'])
) {

    $target_id = (int)$_GET['toggle_status'];

    if ($target_id === $current_user_id) {

        $error = 'You cannot change your own account status.';

    } else {

        $user_query = "
            SELECT id, username, status
            FROM users
            WHERE id = $target_id
            LIMIT 1
        ";

        $user_result = mysql_query($user_query, $conn);

        if ($user_result && mysql_num_rows($user_result) > 0) {

            $target_user = mysql_fetch_assoc($user_result);

            $new_status =
                ((int)$target_user['status'] === 1)
                    ? 0
                    : 1;

            $update_query = "
                UPDATE users
                SET
                    status = $new_status,
                    updated_at = NOW()
                WHERE id = $target_id
                LIMIT 1
            ";

            if (mysql_query($update_query, $conn)) {

                audit_log_action(
                    'TOGGLE_USER_STATUS',
                    null,
                    null,
                    "User '" . $target_user['username'] . "' status changed to " .
                        ($new_status ? 'Active' : 'Disabled'),
                    json_encode(array('status' => (int)$target_user['status'])),
                    json_encode(array('status' => $new_status))
                );

            } else {

                $error = mysql_error($conn);
            }

        } else {

            $error = 'User not found.';
        }
    }

    if ($error == '') {
        header('Location: users.php');
        exit;
    }
}


/*
 * =========================================================
 * DELETE USER
 * =========================================================
 */

if (
    isset($_GET['delete']) &&
    ctype_digit($_GET['delete'])
) {

    $target_id = (int)$_GET['delete'];

    if ($target_id === $current_user_id) {

        $error = 'You cannot delete your own account.';

    } else {

        $user_query = "
            SELECT
                u.id,
                u.username,
                u.role_id,
                u.status,
                r.role_name
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE u.id = $target_id
            LIMIT 1
        ";

        $user_result = mysql_query($user_query, $conn);

        if ($user_result && mysql_num_rows($user_result) > 0) {

            $target_user = mysql_fetch_assoc($user_result);

            if ($target_user['role_name'] === 'admin') {

                $admin_count_query = "
                    SELECT COUNT(*) AS total
                    FROM users u
                    INNER JOIN roles r ON r.id = u.role_id
                    WHERE r.role_name = 'admin'
                ";

                $admin_count_result = mysql_query($admin_count_query, $conn);
                $admin_count_row = mysql_fetch_assoc($admin_count_result);

                if ((int)$admin_count_row['total'] <= 1) {
                    $error = 'Cannot delete the last remaining admin account.';
                }
            }

            if ($error == '') {

                $old_data = json_encode($target_user);

                $delete_query = "
                    DELETE FROM users
                    WHERE id = $target_id
                    LIMIT 1
                ";

                if (mysql_query($delete_query, $conn)) {

                    audit_log_action(
                        'DELETE_USER',
                        null,
                        null,
                        "User '" . $target_user['username'] . "' deleted",
                        $old_data,
                        ''
                    );

                } else {

                    $error = mysql_error($conn);
                }
            }

        } else {

            $error = 'User not found.';
        }
    }

    if ($error == '') {
        header('Location: users.php');
        exit;
    }
}


/*
 * =========================================================
 * LOAD USERS
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
            u.username LIKE '%$search_safe%'
            OR r.role_name LIKE '%$search_safe%'
        )
    ";
}

$query = "
    SELECT
        u.id,
        u.username,
        u.status,
        u.created_at,
        r.role_name
    FROM users u
    INNER JOIN roles r ON r.id = u.role_id
    $where
    ORDER BY u.id DESC
";

$result = mysql_query($query, $conn);

if (!$result) {
    die('Database error: ' . mysql_error($conn));
}

?>
<!DOCTYPE html>
<html>

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Users - Wifonic Hardware</title>

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
    max-width: 1300px;
    margin: 0 auto;
    padding: 38px 25px 60px;
}

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
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

.add-button {
    height: 42px;
    padding: 0 18px;
    border: none;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    color: #263238;
    font-size: 13px;
    font-weight: bold;
    background: linear-gradient(135deg, #74ebd5, #acb6e5);
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

.search-area {
    padding: 18px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.40);
}

.search-form {
    display: flex;
    gap: 10px;
}

.search-input {
    flex: 1;
    height: 44px;
    padding: 0 14px;
    border: 1px solid rgba(255, 255, 255, 0.60);
    border-radius: 10px;
    outline: none;
    background: rgba(255, 255, 255, 0.55);
    font-size: 14px;
}

.search-button {
    height: 44px;
    padding: 0 20px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    background: rgba(255, 255, 255, 0.55);
    font-size: 13px;
    font-weight: bold;
}

.clear-search {
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

.message {
    margin: 18px;
    padding: 13px 15px;
    border-radius: 10px;
    font-size: 14px;
}

.error-message {
    background: rgba(255, 80, 80, 0.15);
    border: 1px solid rgba(255, 80, 80, 0.30);
    color: #9b1c1c;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 800px;
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
    white-space: nowrap;
    border-top: 1px solid rgba(255, 255, 255, 0.30);
}

tbody tr:hover {
    background: rgba(255, 255, 255, 0.20);
}

.badge {
    display: inline-flex;
    padding: 6px 10px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: bold;
}

.role-badge {
    background: rgba(255, 255, 255, 0.42);
    color: #37474f;
    text-transform: capitalize;
}

.status-active {
    color: #2e7d32;
    background: rgba(76, 175, 80, 0.14);
}

.status-disabled {
    color: #9b1c1c;
    background: rgba(198, 40, 40, 0.14);
}

.actions {
    display: flex;
    gap: 6px;
}

.action {
    padding: 7px 9px;
    border-radius: 7px;
    text-decoration: none;
    color: #37474f;
    background: rgba(255, 255, 255, 0.40);
    font-size: 11px;
    font-weight: bold;
}

.action-delete {
    color: #ffffff;
    background: rgba(198, 40, 40, 0.82);
}

.action-disabled {
    opacity: 0.45;
    pointer-events: none;
}

.empty {
    padding: 60px 20px;
    text-align: center;
}

.empty-icon {
    font-size: 40px;
    margin-bottom: 12px;
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

        <a href="users.php" class="nav-link active">
            Users
        </a>

        <a href="audit_log.php" class="nav-link">
            Audit Logs
        </a>

    </nav>


    <div class="header-right">

        <div class="user-box">

            <span class="username">
                <?php
                    echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
                ?>
            </span>

            <span class="role">
                <?php
                    echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8');
                ?>
            </span>

        </div>

        <a href="logout.php" class="logout">
            Logout
        </a>

    </div>


</div>


<div class="container">


    <div class="page-header">

        <div>

            <h1 class="title">
                Users
            </h1>

            <div class="subtitle">
                Manage user accounts and access
            </div>

        </div>

        <a href="add_user.php" class="add-button">
            + Add User
        </a>

    </div>


    <div class="card">


        <div class="search-area">

            <form method="get" action="users.php" class="search-form">

                <input
                    type="text"
                    name="search"
                    class="search-input"
                    placeholder="Search username or role..."
                    value="<?php
                        echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8');
                    ?>"
                >

                <button type="submit" class="search-button">
                    Search
                </button>

                <?php if ($search != '') { ?>

                    <a href="users.php" class="clear-search">
                        Clear
                    </a>

                <?php } ?>

            </form>

        </div>


        <?php if ($error != '') { ?>

            <div class="message error-message">
                <?php
                    echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
                ?>
            </div>

        <?php } ?>


        <?php if (mysql_num_rows($result) > 0) { ?>


            <div class="table-wrapper">


                <table>

                    <thead>

                    <tr>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>

                    </thead>

                    <tbody>

                    <?php while ($user = mysql_fetch_assoc($result)) { ?>

                        <tr>

                            <td>
                                <?php
                                    echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8');
                                ?>
                                <?php if ((int)$user['id'] === $current_user_id) { ?>
                                    <span style="color: rgba(38,50,56,0.5); font-size: 11px;">(you)</span>
                                <?php } ?>
                            </td>

                            <td>
                                <span class="badge role-badge">
                                    <?php
                                        echo htmlspecialchars($user['role_name'], ENT_QUOTES, 'UTF-8');
                                    ?>
                                </span>
                            </td>

                            <td>
                                <?php if ((int)$user['status'] === 1) { ?>
                                    <span class="badge status-active">Active</span>
                                <?php } else { ?>
                                    <span class="badge status-disabled">Disabled</span>
                                <?php } ?>
                            </td>

                            <td>
                                <?php
                                    echo htmlspecialchars($user['created_at'], ENT_QUOTES, 'UTF-8');
                                ?>
                            </td>

                            <td>

                                <div class="actions">

                                    <a
                                        href="edit_user.php?id=<?php echo (int)$user['id']; ?>"
                                        class="action"
                                    >
                                        Edit
                                    </a>

                                    <a
                                        href="users.php?toggle_status=<?php echo (int)$user['id']; ?>"
                                        class="action <?php echo (int)$user['id'] === $current_user_id ? 'action-disabled' : ''; ?>"
                                        onclick="return confirm('<?php echo (int)$user['status'] === 1 ? 'Disable' : 'Enable'; ?> this user?');"
                                    >
                                        <?php echo (int)$user['status'] === 1 ? 'Disable' : 'Enable'; ?>
                                    </a>

                                    <a
                                        href="users.php?delete=<?php echo (int)$user['id']; ?>"
                                        class="action action-delete <?php echo (int)$user['id'] === $current_user_id ? 'action-disabled' : ''; ?>"
                                        onclick="return confirm('Delete this user? This cannot be undone.');"
                                    >
                                        Delete
                                    </a>

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
                    👤
                </div>

                <div>
                    No users found.
                </div>

            </div>

        <?php } ?>


    </div>


</div>


</body>

</html>
