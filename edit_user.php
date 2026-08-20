<?php

require_once 'auth.php';
require_once 'audit.php';
require_once 'bcrypt_compat.php';

require_roles(array('admin'));

$current_user_id = (int)$_SESSION['user_id'];

$error = '';
$success = '';

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    header('Location: users.php');
    exit;
}

$user_id = (int)$_GET['id'];


/*
 * =========================================================
 * LOAD ROLES
 * =========================================================
 */

$roles = array();

$roles_result = mysql_query(
    "SELECT id, role_name FROM roles ORDER BY role_name ASC",
    $conn
);

if ($roles_result) {
    while ($role_row = mysql_fetch_assoc($roles_result)) {
        $roles[] = $role_row;
    }
}


/*
 * =========================================================
 * LOAD USER
 * =========================================================
 */

$user_query = "
    SELECT id, username, role_id, status
    FROM users
    WHERE id = $user_id
    LIMIT 1
";

$user_result = mysql_query($user_query, $conn);

if (!$user_result || mysql_num_rows($user_result) === 0) {
    header('Location: users.php');
    exit;
}

$existing_user = mysql_fetch_assoc($user_result);

$edit_username = $existing_user['username'];
$role_id = $existing_user['role_id'];
$status = $existing_user['status'];


if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $edit_username = isset($_POST['username'])
        ? trim($_POST['username'])
        : '';

    $password = isset($_POST['password'])
        ? $_POST['password']
        : '';

    $confirm_password = isset($_POST['confirm_password'])
        ? $_POST['confirm_password']
        : '';

    $role_id = isset($_POST['role_id'])
        ? (int)$_POST['role_id']
        : 0;

    $status = isset($_POST['status'])
        ? (int)$_POST['status']
        : 1;


    /*
     * Validation
     */

    $valid_role_ids = array();

    foreach ($roles as $role_row) {
        $valid_role_ids[] = (int)$role_row['id'];
    }

    if ($edit_username == '') {

        $error = 'Username is required.';

    } elseif (strlen($edit_username) < 3) {

        $error = 'Username must be at least 3 characters.';

    } elseif (!in_array($role_id, $valid_role_ids, true)) {

        $error = 'Please select a valid role.';

    } elseif ($status !== 0 && $status !== 1) {

        $error = 'Invalid status.';

    } elseif (
        $user_id === $current_user_id &&
        $status === 0
    ) {

        $error = 'You cannot disable your own account.';

    } elseif ($password != '' && strlen($password) < 8) {

        $error = 'Password must be at least 8 characters.';

    } elseif ($password != '' && $password !== $confirm_password) {

        $error = 'Passwords do not match.';
    }


    /*
     * If becoming/staying admin is not the case,
     * make sure we are not removing the last admin.
     */

    if ($error == '') {

        $original_role_query = "
            SELECT r.role_name
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE u.id = $user_id
            LIMIT 1
        ";

        $original_role_result = mysql_query($original_role_query, $conn);
        $original_role_row = mysql_fetch_assoc($original_role_result);

        $new_role_query = "
            SELECT role_name
            FROM roles
            WHERE id = $role_id
            LIMIT 1
        ";

        $new_role_result = mysql_query($new_role_query, $conn);
        $new_role_row = mysql_fetch_assoc($new_role_result);

        $was_admin = $original_role_row && $original_role_row['role_name'] === 'admin';
        $will_be_admin = $new_role_row && $new_role_row['role_name'] === 'admin';

        if ($was_admin && !$will_be_admin) {

            $admin_count_query = "
                SELECT COUNT(*) AS total
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                WHERE r.role_name = 'admin'
            ";

            $admin_count_result = mysql_query($admin_count_query, $conn);
            $admin_count_row = mysql_fetch_assoc($admin_count_result);

            if ((int)$admin_count_row['total'] <= 1) {
                $error = 'Cannot change the role of the last remaining admin account.';
            }
        }
    }


    /*
     * Check duplicate username (excluding self).
     */

    if ($error == '') {

        $username_safe = mysql_real_escape_string(
            $edit_username,
            $conn
        );

        $check_query = "
            SELECT id
            FROM users
            WHERE username = '$username_safe'
            AND id != $user_id
            LIMIT 1
        ";

        $check_result = mysql_query($check_query, $conn);

        if (!$check_result) {

            $error = mysql_error($conn);

        } elseif (mysql_num_rows($check_result) > 0) {

            $error = 'A user with this username already exists.';
        }
    }


    /*
     * Update user.
     */

    if ($error == '') {

        $old_data = json_encode($existing_user);

        $username_safe = mysql_real_escape_string(
            $edit_username,
            $conn
        );

        $set_clause = "
            username = '$username_safe',
            role_id = $role_id,
            status = $status,
            updated_at = NOW()
        ";

        if ($password != '') {

            $password_hash = bcrypt_hash($password);

            $password_hash_safe = mysql_real_escape_string(
                $password_hash,
                $conn
            );

            $set_clause .= ", password = '$password_hash_safe'";
        }

        $update_query = "
            UPDATE users
            SET $set_clause
            WHERE id = $user_id
            LIMIT 1
        ";

        $result = mysql_query($update_query, $conn);

        if (!$result) {

            $error = mysql_error($conn);

        } else {

            $new_data = json_encode(
                array(
                    'username' => $edit_username,
                    'role_id' => $role_id,
                    'status' => $status,
                    'password_changed' => $password != ''
                )
            );

            audit_log_action(
                'EDIT_USER',
                null,
                null,
                "User '" . $edit_username . "' updated",
                $old_data,
                $new_data
            );

            $success = 'User updated successfully.';

            $existing_user['username'] = $edit_username;
            $existing_user['role_id'] = $role_id;
            $existing_user['status'] = $status;
        }
    }
}

?>
<!DOCTYPE html>
<html>

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Edit User - Wifonic Hardware</title>

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
    background: linear-gradient(135deg, #74ebd5, #acb6e5);
    min-height: 100vh;
    padding: 40px 20px;
    color: #333;
}

.page {
    max-width: 700px;
    margin: 0 auto;
}

.glass-card {
    background: rgba(255, 255, 255, 0.28);
    border: 1px solid rgba(255, 255, 255, 0.45);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    border-radius: 20px;
    padding: 35px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.title {
    font-size: 28px;
    font-weight: bold;
    color: #ffffff;
}

.subtitle {
    margin-top: 5px;
    color: rgba(255, 255, 255, 0.85);
    font-size: 14px;
}

.back {
    text-decoration: none;
    color: #ffffff;
    background: rgba(255, 255, 255, 0.20);
    border: 1px solid rgba(255, 255, 255, 0.35);
    padding: 10px 16px;
    border-radius: 10px;
}

.message {
    padding: 13px 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 14px;
}

.error {
    background: rgba(255, 80, 80, 0.15);
    border: 1px solid rgba(255, 80, 80, 0.30);
    color: #9b1c1c;
}

.success {
    background: rgba(0, 180, 100, 0.15);
    border: 1px solid rgba(0, 180, 100, 0.30);
    color: #126b45;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group.full {
    grid-column: 1 / -1;
}

label {
    font-size: 13px;
    font-weight: bold;
    margin-bottom: 7px;
    color: #ffffff;
}

input,
select {
    width: 100%;
    height: 45px;
    border: none;
    outline: none;
    border-radius: 10px;
    padding: 0 13px;
    font-size: 14px;
    background: rgba(255, 255, 255, 0.72);
    color: #333;
}

input:focus,
select:focus {
    box-shadow: 0 0 0 3px rgba(116, 235, 213, 0.30);
}

.required {
    color: #ffdddd;
}

.note {
    margin-top: 8px;
    font-size: 12px;
    color: rgba(255, 255, 255, 0.80);
}

.actions {
    margin-top: 30px;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.button {
    border: none;
    cursor: pointer;
    border-radius: 10px;
    padding: 12px 24px;
    font-size: 14px;
    font-weight: bold;
}

.cancel {
    background: rgba(255, 255, 255, 0.30);
    color: #ffffff;
    text-decoration: none;
}

.submit {
    background: linear-gradient(to right, #74ebd5, #acb6e5);
    color: #ffffff;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
}

.submit:hover {
    opacity: 0.9;
}

@media (max-width: 700px) {

    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-group.full {
        grid-column: auto;
    }

    .header {
        align-items: flex-start;
        gap: 15px;
        flex-direction: column;
    }

    .glass-card {
        padding: 25px;
    }
}

</style>

</head>

<body>

<div class="page">

    <div class="glass-card">

        <div class="header">

            <div>

                <div class="title">
                    Edit User
                </div>

                <div class="subtitle">
                    Update account details
                </div>

            </div>

            <a href="users.php" class="back">
                Back
            </a>

        </div>


        <?php if ($error != '') { ?>

            <div class="message error">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>

        <?php } ?>


        <?php if ($success != '') { ?>

            <div class="message success">
                <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
            </div>

        <?php } ?>


        <form method="post" action="edit_user.php?id=<?php echo (int)$user_id; ?>">

            <div class="form-grid">

                <div class="form-group full">

                    <label for="username">
                        Username
                        <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        id="username"
                        name="username"
                        value="<?php echo htmlspecialchars($edit_username, ENT_QUOTES, 'UTF-8'); ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="password">
                        New Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="new-password"
                    >

                    <div class="note">
                        Leave blank to keep current password
                    </div>

                </div>

                <div class="form-group">

                    <label for="confirm_password">
                        Confirm New Password
                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        autocomplete="new-password"
                    >

                </div>

                <div class="form-group">

                    <label for="role_id">
                        Role
                        <span class="required">*</span>
                    </label>

                    <select id="role_id" name="role_id" required>

                        <?php foreach ($roles as $role_row) { ?>

                            <option
                                value="<?php echo (int)$role_row['id']; ?>"
                                <?php echo ((string)$role_id === (string)$role_row['id']) ? 'selected' : ''; ?>
                            >
                                <?php echo htmlspecialchars(ucfirst($role_row['role_name']), ENT_QUOTES, 'UTF-8'); ?>
                            </option>

                        <?php } ?>

                    </select>

                </div>

                <div class="form-group">

                    <label for="status">
                        Status
                    </label>

                    <select
                        id="status"
                        name="status"
                        <?php echo ($user_id === $current_user_id) ? 'disabled' : ''; ?>
                    >
                        <option value="1" <?php echo ((string)$status === '1') ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo ((string)$status === '0') ? 'selected' : ''; ?>>Disabled</option>
                    </select>

                    <?php if ($user_id === $current_user_id) { ?>

                        <input type="hidden" name="status" value="1">

                        <div class="note">
                            You cannot disable your own account
                        </div>

                    <?php } ?>

                </div>

            </div>


            <div class="actions">

                <a href="users.php" class="button cancel">
                    Cancel
                </a>

                <button type="submit" class="button submit">
                    Save Changes
                </button>

            </div>

        </form>

    </div>

</div>

</body>

</html>
