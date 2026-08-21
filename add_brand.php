<?php
session_start();
require_once 'config.php';
require_once 'auth.php';
require_once 'audit.php';
require_login();

$error = '';
$success = '';
$brand_name = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $brand_name = isset($_POST['brand_name'])
        ? trim($_POST['brand_name'])
        : '';

    if ($brand_name == '') {
        $error = 'Brand name is required.';
    }

    if ($error == '') {
        $brand_name_safe = mysql_real_escape_string($brand_name, $conn);

        $check_query = "
            SELECT id
            FROM brands
            WHERE name = '$brand_name_safe'
            LIMIT 1
        ";
        $check_result = mysql_query($check_query, $conn);

        if (!$check_result) {
            $error = mysql_error($conn);
        } elseif (mysql_num_rows($check_result) > 0) {
            $error = 'This brand already exists.';
        }
    }

    if ($error == '') {
        $user_id = (int)$_SESSION['user_id'];

        $query = "
            INSERT INTO brands (
                name,
                created_by,
                created_at
            )
            VALUES (
                '$brand_name_safe',
                $user_id,
                NOW()
            )
        ";
        $result = mysql_query($query, $conn);

        if (!$result) {
            $error = mysql_error($conn);
        } else {
            $brand_id = mysql_insert_id($conn);

            $new_data = json_encode(array(
                'brand_name' => $brand_name
            ));

            audit_log_action(
                'ADD_BRAND',
                $brand_id,
                '',
                'Brand added',
                '',
                $new_data
            );

            $success = 'Brand added successfully.';
            $brand_name = '';
        }
    }
}

$brands = array();
$list_query = "
    SELECT id, name AS brand_name
    FROM brands
    ORDER BY name ASC
";
$list_result = mysql_query($list_query, $conn);
if ($list_result) {
    while ($row = mysql_fetch_assoc($list_result)) {
        $brands[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Brand - Wifonic Hardware</title>
<style>
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; min-height: 100%; }
body {
    font-family: Arial, Helvetica, sans-serif;
    background: linear-gradient(135deg, #74ebd5, #acb6e5);
    min-height: 100vh;
    padding: 40px 20px;
    color: #333;
}
.page { max-width: 700px; margin: 0 auto; }
.glass-card {
    background: rgba(255, 255, 255, 0.28);
    border: 1px solid rgba(255, 255, 255, 0.45);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    border-radius: 20px;
    padding: 35px;
    margin-bottom: 25px;
}
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
.title { font-size: 28px; font-weight: bold; color: #ffffff; }
.subtitle { margin-top: 5px; color: rgba(255, 255, 255, 0.85); font-size: 14px; }
.back {
    text-decoration: none;
    color: #ffffff;
    background: rgba(255,255,255,0.20);
    border: 1px solid rgba(255,255,255,0.35);
    padding: 10px 16px;
    border-radius: 10px;
}
.message { padding: 13px 15px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
.error { background: rgba(255, 80, 80, 0.15); border: 1px solid rgba(255, 80, 80, 0.30); color: #9b1c1c; }
.success { background: rgba(0, 180, 100, 0.15); border: 1px solid rgba(0, 180, 100, 0.30); color: #126b45; }
.form-group { display: flex; flex-direction: column; }
label { font-size: 13px; font-weight: bold; margin-bottom: 7px; color: #ffffff; }
input {
    width: 100%;
    height: 45px;
    border: none;
    outline: none;
    border-radius: 10px;
    padding: 0 13px;
    font-size: 14px;
    background: rgba(255,255,255,0.72);
    color: #333;
}
input:focus { box-shadow: 0 0 0 3px rgba(116,235,213,0.30); }
.required { color: #ffdddd; }
.actions { margin-top: 25px; display: flex; justify-content: flex-end; gap: 12px; }
.button { border: none; cursor: pointer; border-radius: 10px; padding: 12px 24px; font-size: 14px; font-weight: bold; }
.cancel { background: rgba(255,255,255,0.30); color: #ffffff; text-decoration: none; }
.submit {
    background: linear-gradient(to right, #74ebd5, #acb6e5);
    color: #ffffff;
    box-shadow: 0 8px 20px rgba(0,0,0,0.12);
}
.submit:hover { opacity: 0.9; }
table { width: 100%; border-collapse: collapse; }
th, td {
    text-align: left;
    padding: 10px 12px;
    font-size: 14px;
    color: #333;
    border-bottom: 1px solid rgba(255,255,255,0.35);
}
th { color: #ffffff; }
.empty { color: rgba(255,255,255,0.85); font-size: 14px; }
</style>
</head>
<body>
<div class="page">

    <div class="glass-card">
        <div class="header">
            <div>
                <div class="title">Add Brand</div>
                <div class="subtitle">Add a new device brand</div>
            </div>
            <a href="dashboard.php" class="back">Back</a>
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

        <form method="post" action="add_brand.php">
            <div class="form-group">
                <label for="brand_name">
                    Brand Name <span class="required">*</span>
                </label>
                <input
                    type="text"
                    id="brand_name"
                    name="brand_name"
                    value="<?php echo htmlspecialchars($brand_name, ENT_QUOTES, 'UTF-8'); ?>"
                    required
                >
            </div>

            <div class="actions">
                <a href="dashboard.php" class="button cancel">Cancel</a>
                <button type="submit" class="button submit">Save Brand</button>
            </div>
        </form>
    </div>

    <div class="glass-card">
        <div class="header">
            <div class="title" style="font-size: 20px;">Existing Brands</div>
        </div>

        <?php if (count($brands) == 0) { ?>
            <div class="empty">No brands added yet.</div>
        <?php } else { ?>
            <table>
                <tr>
                    <th>Brand Name</th>
                </tr>
                <?php foreach ($brands as $b) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($b['brand_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php } ?>
            </table>
        <?php } ?>
    </div>

</div>
</body>
</html>
