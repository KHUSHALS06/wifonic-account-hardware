<?php
session_start();
require_once 'config.php';
require_once 'auth.php';
require_once 'audit.php';

require_login();

/*
 * Only admin and manager can add courier companies.
 */
$allowed_roles = array('admin', 'manager');
if (!isset($_SESSION['role_name']) || !in_array($_SESSION['role_name'], $allowed_roles)) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

$name = '';
$contact_person = '';
$phone = '';
$address = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $contact_person = isset($_POST['contact_person']) ? trim($_POST['contact_person']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';

    /*
     * Validation
     */
    if ($name == '') {
        $error = 'Purchased from name is required.';
    }

    /*
     * Check duplicate courier name.
     */
    if ($error == '') {
        $name_safe = mysql_real_escape_string($name, $conn);

        $check_query = "
            SELECT id
            FROM couriers
            WHERE name = '$name_safe'
            LIMIT 1
        ";

        $check_result = mysql_query($check_query, $conn);

        if (!$check_result) {
            $error = mysql_error($conn);
        } elseif (mysql_num_rows($check_result) > 0) {
            $error = 'A purchased from with this name already exists.';
        }
    }

    /*
     * Insert courier.
     */
    if ($error == '') {
        $name_safe = mysql_real_escape_string($name, $conn);
        $contact_person_safe = mysql_real_escape_string($contact_person, $conn);
        $phone_safe = mysql_real_escape_string($phone, $conn);
        $address_safe = mysql_real_escape_string($address, $conn);

        $user_id = (int)$_SESSION['user_id'];

        $query = "
            INSERT INTO couriers (
                name,
                contact_person,
                phone,
                address,
                created_by,
                created_at
            )
            VALUES (
                '$name_safe',
                '$contact_person_safe',
                '$phone_safe',
                '$address_safe',
                $user_id,
                NOW()
            )
        ";

        $result = mysql_query($query, $conn);

        if (!$result) {
            $error = mysql_error($conn);
        } else {
            $courier_id = mysql_insert_id($conn);

            $new_data = json_encode(array(
                'name' => $name,
                'contact_person' => $contact_person,
                'phone' => $phone,
                'address' => $address
            ));

            audit_log_action(
                'ADD_COURIER',
                $courier_id,
                $name,
                'Purchased from added',
                '',
                $new_data
            );

            $success = 'Purchased from added successfully.';

            /*
             * Clear form.
             */
            $name = '';
            $contact_person = '';
            $phone = '';
            $address = '';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Purchased From - Wifonic Hardware</title>
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
.page { max-width: 900px; margin: 0 auto; }
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
.title { font-size: 28px; font-weight: bold; color: #333; }
.subtitle { margin-top: 5px; color: rgba(0, 0, 0, 0.7); font-size: 14px; }
.back {
    text-decoration: none;
    color: #333;
    background: rgba(255,255,255,0.50);
    border: 1px solid rgba(0,0,0,0.20);
    padding: 10px 16px;
    border-radius: 10px;
}
.message { padding: 13px 15px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
.error {
    background: rgba(255, 80, 80, 0.25);
    border: 1px solid rgba(255, 80, 80, 0.30);
    color: #9b1c1c;
}
.success {
    background: rgba(0, 180, 100, 0.25);
    border: 1px solid rgba(0, 180, 100, 0.30);
    color: #126b45;
}
.section-title { font-size: 18px; font-weight: bold; margin: 25px 0 18px; color: #333; }
.form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
.form-group { display: flex; flex-direction: column; }
.form-group.full { grid-column: 1 / -1; }
label { font-size: 13px; font-weight: bold; margin-bottom: 7px; color: #333; }
input, textarea {
    width: 100%;
    border: none;
    outline: none;
    border-radius: 10px;
    padding: 0 13px;
    font-size: 14px;
    background: rgba(255,255,255,0.85);
    color: #000;
}
input { height: 45px; }
textarea { padding: 10px 13px; min-height: 90px; resize: vertical; }
input:focus, textarea:focus { box-shadow: 0 0 0 3px rgba(116,235,213,0.30); }
.required { color: #cc0000; }
.actions { margin-top: 30px; display: flex; justify-content: flex-end; gap: 12px; }
.button { border: none; cursor: pointer; border-radius: 10px; padding: 12px 24px; font-size: 14px; font-weight: bold; }
.cancel { background: rgba(255,255,255,0.50); color: #333; text-decoration: none; border: 1px solid rgba(0,0,0,0.15); }
.submit {
    background: linear-gradient(to right, #74ebd5, #acb6e5);
    color: #333;
    box-shadow: 0 8px 20px rgba(0,0,0,0.12);
}
.submit:hover { opacity: 0.85; }
.note { margin-top: 8px; font-size: 12px; color: rgba(0, 0, 0, 0.7); }
.readonly-field {
    background: rgba(255,255,255,0.60);
    cursor: not-allowed;
    color: #000;
}
@media (max-width: 700px) {
    .form-grid { grid-template-columns: 1fr; }
    .form-group.full { grid-column: auto; }
    .header { align-items: flex-start; gap: 15px; flex-direction: column; }
    .glass-card { padding: 25px; }
}
</style>
</head>
<body>
<div class="page">
<div class="glass-card">

<div class="header">
    <div>
        <div class="title">Add Purchased From</div>
        <div class="subtitle">Add a purchased from vendor to the master list</div>
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

<form method="post" action="add_courier.php">

    <div class="section-title">Purchased From Information</div>

    <div class="form-grid">

        <div class="form-group">
            <label for="name">Purchased From Name <span class="required">*</span></label>
            <input type="text" id="name" name="name" readonly
                class="readonly-field"
                value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="form-group">
            <label for="contact_person">Contact Person</label>
            <input type="text" id="contact_person" name="contact_person"
                value="<?php echo htmlspecialchars($contact_person, ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="form-group">
            <label for="phone">Phone</label>
            <input type="text" id="phone" name="phone"
                value="<?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="form-group full">
            <label for="address">Address</label>
            <textarea id="address" name="address"><?php echo htmlspecialchars($address, ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

    </div>

    <div class="actions">
        <a href="dashboard.php" class="button cancel">Cancel</a>
        <button type="submit" class="button submit">Add Purchased From</button>
    </div>

</form>

</div>
</div>
</body>
</html>
