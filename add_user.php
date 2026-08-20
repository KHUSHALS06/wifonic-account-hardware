<?php

require_once 'auth.php';
require_once 'audit.php';

require_roles(array('admin'));

$error = '';
$success = '';

$new_username = '';
$role_id = '';
$status = '1';


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


if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $new_username = isset($_POST['username'])
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

    if ($new_username == '') {

        $error = 'Username is required.';

    } elseif (strlen($new_username) < 3) {

        $error = 'Username must be at least 3 characters.';

    } elseif ($password == '') {

        $error = 'Password is required.';

    } elseif (strlen($password) < 8) {

        $error = 'Password must be at least 8 characters.';

    } elseif ($password !== $confirm_password) {

        $error = 'Passwords do not match.';

    } elseif (!in_array($role_id, $valid_role_ids, true)) {

        $error = 'Please select a valid role.';

    } elseif ($status !== 0 && $status !== 1) {

        $error = 'Invalid status.';
    }


    /*
     * Check duplicate username.
     */

    if ($error == '') {

        $username_safe = mysql_real_escape_string(
            $new_username,
            $conn
        );

        $check_query = "
            SELECT id
            FROM users
            WHERE username = '$username_safe'
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
     * Insert user.
     */

    if ($error == '') {

        $username_safe = mysql_real_escape_string(
            $new_username,
            $conn
        );

        $password_hash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $password_hash_safe = mysql_real_escape_string(
            $password_hash,
            $conn
        );

        $insert_query = "
            INSERT INTO users (
                username,
                password,
                role_id,
                status,
                created_at
            )
            VALUES (
                '$username_safe',
                '$password_hash_safe',
                $role_id,
                $status,
                NOW()
            )
        ";

        $result = mysql_query($insert_query, $conn);

        if (!$result) {

            $error = mysql_error($conn);

        } else {

            $new_user_id = mysql_insert_id($conn);

            $new_data = json_encode(
                array(
                    'username' => $new_username,
                    'role_id' => $role_id,
                    'status' => $status
                )
            );

            audit_log_action(
                'ADD_USER',
                null,
                null,
                "User '" . $new_username . "' created",
                '',
                $new_data
            );

            $success = 'User created successfully.';

            $new_username = '';
            $role_id = '';
            $status = '1';
        }
    }
}

?>
<!DOCTYPE html>
<html>

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Add User - Wifonic Hardware</title>

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
                    Add User
                </div>

                <div class="subtitle">
                    Create a new user account
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


        <form method="post" action="add_user.php">

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
                        value="<?php echo htmlspecialchars($new_username, ENT_QUOTES, 'UTF-8'); ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="password">
                        Password
                        <span class="required">*</span>
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="new-password"
                        required
                    >

                    <div class="note">
                        Minimum 8 characters
                    </div>

                </div>

                <div class="form-group">

                    <label for="confirm_password">
                        Confirm Password
                        <span class="required">*</span>
                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        autocomplete="new-password"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="role_id">
                        Role
                        <span class="required">*</span>
                    </label>

                    <select id="role_id" name="role_id" required>

                        <option value="">
                            Select Role
                        </option>

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

                    <select id="status" name="status">
                        <option value="1" <?php echo ((string)$status === '1') ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo ((string)$status === '0') ? 'selected' : ''; ?>>Disabled</option>
                    </select>

                </div>

            </div>


            <div class="actions">

                <a href="users.php" class="button cancel">
                    Cancel
                </a>

                <button type="submit" class="button submit">
                    Add User
                </button>

            </div>

        </form>

    </div>

</div>

</body>

</html>
