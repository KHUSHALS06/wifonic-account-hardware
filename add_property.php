<?php
session_start();
require_once 'config.php';
require_once 'auth.php';
require_once 'audit.php';
require_login();

$error = '';
$success = '';
$property_name = '';
$property_address = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $property_name = isset($_POST['property_name'])
        ? trim($_POST['property_name'])
        : '';
    $property_address = isset($_POST['property_address'])
        ? trim($_POST['property_address'])
        : '';

    if ($property_name == '') {
        $error = 'Property name is required.';
    }

    if ($error == '') {
        $property_name_safe = mysql_real_escape_string($property_name, $conn);

        $check_query = "
            SELECT id
            FROM properties
            WHERE name = '$property_name_safe'
            LIMIT 1
        ";
        $check_result = mysql_query($check_query, $conn);

        if (!$check_result) {
            $error = mysql_error($conn);
        } elseif (mysql_num_rows($check_result) > 0) {
            $error = 'This property already exists.';
        }
    }

    if ($error == '') {
        $property_address_safe = mysql_real_escape_string($property_address, $conn);
        $user_id = (int)$_SESSION['user_id'];

        $query = "
            INSERT INTO properties (
                name,
                address,
                created_by,
                created_at
            )
            VALUES (
                '$property_name_safe',
                '$property_address_safe',
                $user_id,
                NOW()
            )
        ";
        $result = mysql_query($query, $conn);

        if (!$result) {
            $error = mysql_error($conn);
        } else {
            $property_id = mysql_insert_id($conn);

            $new_data = json_encode(array(
                'property_name' => $property_name,
                'property_address' => $property_address
            ));

            audit_log_action(
                'ADD_PROPERTY',
                $property_id,
                '',
                'Property added',
                '',
                $new_data
            );

            $success = 'Property added successfully.';
            $property_name = '';
            $property_address = '';
        }
    }
}

$properties = array();
$list_query = "
    SELECT id, name AS property_name, address AS property_address
    FROM properties
    ORDER BY name ASC
";
$list_result = mysql_query($list_query, $conn);
if ($list_result) {
    while ($row = mysql_fetch_assoc($list_result)) {
        $properties[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Property - Wifonic Hardware</title>
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
.form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
.form-group { display: flex; flex-direction: column; }
.form-group.full { grid-column: 1 / -1; }
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
@media (max-width: 700px) {
    .form-grid { grid-template-columns: 1fr; }
    .form-group.full { grid-column: auto; }
}
</style>
</head>
<body>
<div class="page">

    <div class="glass-card">
        <div class="header">
            <div>
                <div class="title">Add Property</div>
                <div class="subtitle">Add a hotel property</div>
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

        <form method="post" action="add_property.php">
            <div class="form-grid">
                <div class="form-group full">
                    <label for="property_name">
                        Property Name <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        id="property_name"
                        name="property_name"
                        value="<?php echo htmlspecialchars($property_name, ENT_QUOTES, 'UTF-8'); ?>"
                        required
                    >
                </div>

                <div class="form-group full">
                    <label for="property_address">
                        Property Address
                    </label>
                    <input
                        type="text"
                        id="property_address"
                        name="property_address"
                        value="<?php echo htmlspecialchars($property_address, ENT_QUOTES, 'UTF-8'); ?>"
                    >
                </div>
            </div>

            <div class="actions">
                <a href="dashboard.php" class="button cancel">Cancel</a>
                <button type="submit" class="button submit">Save Property</button>
            </div>
        </form>
    </div>

    <div class="glass-card">
        <div class="header">
            <div class="title" style="font-size: 20px;">Existing Properties</div>
        </div>

        <?php if (count($properties) == 0) { ?>
            <div class="empty">No properties added yet.</div>
        <?php } else { ?>
            <table>
                <tr>
                    <th>Property Name</th>
                    <th>Address</th>
                </tr>
                <?php foreach ($properties as $p) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($p['property_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($p['property_address'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php } ?>
            </table>
        <?php } ?>
    </div>

</div>
</body>
</html>
