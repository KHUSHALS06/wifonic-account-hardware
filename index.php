<?php

session_start();

require_once 'config.php';

$error = '';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $username = isset($_POST['username'])
        ? trim($_POST['username'])
        : '';

    $password = isset($_POST['password'])
        ? $_POST['password']
        : '';

    if ($username == '' || $password == '') {

        $error = 'Username and password are required.';

    } else {

        $username_safe = mysql_real_escape_string(
            $username,
            $conn
        );

        $query = "
            SELECT
                u.id,
                u.username,
                u.password,
                u.role_id,
                u.status,
                r.role_name
            FROM users u
            INNER JOIN roles r
                ON r.id = u.role_id
            WHERE u.username = '$username_safe'
            LIMIT 1
        ";

        $result = mysql_query($query, $conn);

        if (!$result) {

            $error = mysql_error($conn);

        } else {

            $user = mysql_fetch_assoc($result);

            if (!$user) {

                $error = 'Invalid username or password.';

            } elseif ((int)$user['status'] !== 1) {

                $error = 'This user account is disabled.';

            } elseif (
                crypt(
                    $password,
                    $user['password']
                ) !== $user['password']
            ) {

                $error = 'Invalid username or password.';

            } else {

                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role_id'] = $user['role_id'];
                $_SESSION['role_name'] = $user['role_name'];

                header('Location: dashboard.php');
                exit;
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

<title>Login - Wifonic Hardware</title>

<style>

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
    width: 100%;
    height: 100%;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: linear-gradient(
        to right,
        #74ebd5,
        #acb6e5
    );
    display: flex;
    align-items: center;
    justify-content: center;
}

.login-wrapper {
    width: 100%;
    max-width: 420px;
    padding: 20px;
}

.login-box {
    background: #ffffff;
    padding: 40px;
    border-radius: 14px;
    box-shadow:
        0 15px 40px
        rgba(0, 0, 0, 0.18);
}

.title {
    text-align: center;
    font-size: 27px;
    font-weight: bold;
    color: #333333;
    margin-bottom: 8px;
}

.subtitle {
    text-align: center;
    color: #777777;
    font-size: 14px;
    margin-bottom: 30px;
}

.error {
    background: #ffe8e8;
    border: 1px solid #ffbaba;
    color: #c62828;
    padding: 12px 14px;
    border-radius: 7px;
    margin-bottom: 20px;
    font-size: 14px;
    text-align: center;
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    margin-bottom: 8px;
    font-size: 14px;
    font-weight: bold;
    color: #444444;
}

input[type="text"],
input[type="password"] {
    width: 100%;
    height: 46px;
    padding: 0 14px;
    border: 1px solid #dddddd;
    border-radius: 7px;
    font-size: 15px;
    outline: none;
}

input[type="text"]:focus,
input[type="password"]:focus {
    border-color: #74ebd5;
    box-shadow:
        0 0 0 3px
        rgba(116, 235, 213, 0.15);
}

.login-button {
    width: 100%;
    height: 48px;
    border: none;
    border-radius: 7px;
    background: linear-gradient(
        to right,
        #74ebd5,
        #acb6e5
    );
    color: #ffffff;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
}

.login-button:hover {
    opacity: 0.9;
}

.footer {
    text-align: center;
    margin-top: 25px;
    font-size: 12px;
    color: #999999;
}

</style>

</head>

<body>

<div class="login-wrapper">

<div class="login-box">

<div class="title">
Wifonic Hardware
</div>

<div class="subtitle">
Login
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

<form method="post" action="index.php">

<div class="form-group">

<label for="username">
Username
</label>

<input
    type="text"
    id="username"
    name="username"
    autocomplete="username"
    autofocus
    value="<?php

    echo isset($_POST['username'])
        ? htmlspecialchars(
            $_POST['username'],
            ENT_QUOTES,
            'UTF-8'
        )
        : '';

    ?>"
>

</div>

<div class="form-group">

<label for="password">
Password
</label>

<input
    type="password"
    id="password"
    name="password"
    autocomplete="current-password"
>

</div>

<button
    type="submit"
    class="login-button"
>
Login
</button>

</form>

<div class="footer">
Hardware Management System
</div>

</div>

</div>

</body>

</html>
