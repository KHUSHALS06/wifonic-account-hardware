<?php

require_once '../permissions.php';

require_permission('device.create');

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $asset_tag = isset($_POST['asset_tag'])
        ? trim($_POST['asset_tag'])
        : '';

    $device_type = isset($_POST['device_type'])
        ? trim($_POST['device_type'])
        : '';

    $brand = isset($_POST['brand'])
        ? trim($_POST['brand'])
        : '';

    $model = isset($_POST['model'])
        ? trim($_POST['model'])
        : '';

    $serial_number = isset($_POST['serial_number'])
        ? trim($_POST['serial_number'])
        : '';

    $specification = isset($_POST['specification'])
        ? trim($_POST['specification'])
        : '';

    $purchased = isset($_POST['purchased'])
        ? (int)$_POST['purchased']
        : -1;

    if ($asset_tag == '') {

        $error = 'Asset tag is required.';

    } elseif ($device_type == '') {

        $error = 'Device type is required.';

    } elseif ($purchased != 0 && $purchased != 1) {

        $error = 'Please select whether the device was purchased.';

    } else {

        $asset_tag_safe = mysql_real_escape_string(
            $asset_tag,
            $conn
        );

        $device_type_safe = mysql_real_escape_string(
            $device_type,
            $conn
        );

        $brand_safe = mysql_real_escape_string(
            $brand,
            $conn
        );

        $model_safe = mysql_real_escape_string(
            $model,
            $conn
        );

        $serial_number_safe = mysql_real_escape_string(
            $serial_number,
            $conn
        );

        $specification_safe = mysql_real_escape_string(
            $specification,
            $conn
        );

        $user_id = (int)$_SESSION['user_id'];

        $query = "
            INSERT INTO devices
            (
                asset_tag,
                device_type,
                brand,
                model,
                serial_number,
                specification,
                purchased,
                current_status,
                created_by,
                created_at
            )
            VALUES
            (
                '$asset_tag_safe',
                '$device_type_safe',
                '$brand_safe',
                '$model_safe',
                '$serial_number_safe',
                '$specification_safe',
                $purchased,
                'Available',
                $user_id,
                NOW()
            )
        ";

        $result = mysql_query($query, $conn);

        if (!$result) {

            $error = mysql_error($conn);

        } else {

            $device_id = mysql_insert_id($conn);

            $history_description =
                'Device created';

            $history_description_safe =
                mysql_real_escape_string(
                    $history_description,
                    $conn
                );

            mysql_query("
                INSERT INTO device_history
                (
                    device_id,
                    action,
                    description,
                    new_status,
                    changed_by,
                    changed_at
                )
                VALUES
                (
                    $device_id,
                    'Created',
                    '$history_description_safe',
                    'Available',
                    $user_id,
                    NOW()
                )
            ", $conn);

            if ($purchased == 1) {

                $vendor = isset($_POST['vendor'])
                    ? trim($_POST['vendor'])
                    : '';

                $purchase_date =
                    isset($_POST['purchase_date'])
                    ? trim($_POST['purchase_date'])
                    : '';

                $price =
                    isset($_POST['price'])
                    ? trim($_POST['price'])
                    : '';

                $invoice_number =
                    isset($_POST['invoice_number'])
                    ? trim($_POST['invoice_number'])
                    : '';

                if ($price == '') {
                    $price = '0';
                }

                $vendor_safe =
                    mysql_real_escape_string(
                        $vendor,
                        $conn
                    );

                $purchase_date_safe =
                    mysql_real_escape_string(
                        $purchase_date,
                        $conn
                    );

                $invoice_number_safe =
                    mysql_real_escape_string(
                        $invoice_number,
                        $conn
                    );

                $price_safe =
                    mysql_real_escape_string(
                        $price,
                        $conn
                    );

                mysql_query("
                    INSERT INTO device_purchases
                    (
                        device_id,
                        vendor,
                        purchase_date,
                        price,
                        invoice_number,
                        created_by,
                        created_at
                    )
                    VALUES
                    (
                        $device_id,
                        '$vendor_safe',
                        '$purchase_date_safe',
                        '$price_safe',
                        '$invoice_number_safe',
                        $user_id,
                        NOW()
                    )
                ", $conn);

            } else {

                $movement_reason =
                    isset($_POST['movement_reason'])
                    ? trim($_POST['movement_reason'])
                    : '';

                $movement_type =
                    isset($_POST['movement_type'])
                    ? trim($_POST['movement_type'])
                    : '';

                $destination =
                    isset($_POST['destination'])
                    ? trim($_POST['destination'])
                    : '';

                $movement_date =
                    isset($_POST['movement_date'])
                    ? trim($_POST['movement_date'])
                    : '';

                $property_number =
                    isset($_POST['property_number'])
                    ? trim($_POST['property_number'])
                    : '';

                $movement_price =
                    isset($_POST['movement_price'])
                    ? trim($_POST['movement_price'])
                    : '';

                if ($movement_price == '') {
                    $movement_price = '0';
                }

                $movement_reason_safe =
                    mysql_real_escape_string(
                        $movement_reason,
                        $conn
                    );

                $movement_type_safe =
                    mysql_real_escape_string(
                        $movement_type,
                        $conn
                    );

                $destination_safe =
                    mysql_real_escape_string(
                        $destination,
                        $conn
                    );

                $movement_date_safe =
                    mysql_real_escape_string(
                        $movement_date,
                        $conn
                    );

                $property_number_safe =
                    mysql_real_escape_string(
                        $property_number,
                        $conn
                    );

                $movement_price_safe =
                    mysql_real_escape_string(
                        $movement_price,
                        $conn
                    );

                mysql_query("
                    INSERT INTO device_movements
                    (
                        device_id,
                        movement_reason,
                        destination,
                        movement_date,
                        movement_type,
                        property_number,
                        price,
                        created_by,
                        created_at
                    )
                    VALUES
                    (
                        $device_id,
                        '$movement_reason_safe',
                        '$destination_safe',
                        '$movement_date_safe',
                        '$movement_type_safe',
                        '$property_number_safe',
                        '$movement_price_safe',
                        $user_id,
                        NOW()
                    )
                ", $conn);
            }

            header(
                'Location: view.php?id=' .
                $device_id
            );

            exit;
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

<title>Add Device - Wifonic Hardware</title>

<style>

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
}

body {

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background:
        linear-gradient(
            to right,
            #74ebd5,
            #acb6e5
        );

    color: #333333;

    min-height: 100vh;
}

.header {

    height: 70px;

    background: #ffffff;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 30px;

    box-shadow:
        0 4px 15px
        rgba(0, 0, 0, 0.10);
}

.logo {

    font-size: 21px;

    font-weight: bold;
}

.header-right {

    display: flex;

    align-items: center;

    gap: 15px;

    font-size: 14px;

    color: #555555;
}

.logout {

    color: #777777;

    text-decoration: none;

    font-weight: bold;
}

.container {

    max-width: 900px;

    margin: 0 auto;

    padding: 35px 20px 50px;
}

.form-box {

    background: #ffffff;

    border-radius: 14px;

    padding: 35px;

    box-shadow:
        0 15px 40px
        rgba(0, 0, 0, 0.15);
}

.page-title {

    margin: 0 0 8px;

    font-size: 27px;
}

.page-subtitle {

    color: #777777;

    font-size: 14px;

    margin-bottom: 30px;
}

.section {

    margin-top: 30px;

    padding-top: 25px;

    border-top: 1px solid #eeeeee;
}

.section-title {

    font-size: 19px;

    font-weight: bold;

    margin-bottom: 20px;
}

.form-grid {

    display: grid;

    grid-template-columns:
        repeat(
            2,
            minmax(0, 1fr)
        );

    gap: 20px;
}

.form-group {

    margin-bottom: 5px;
}

.form-group.full {

    grid-column: 1 / -1;
}

label {

    display: block;

    margin-bottom: 8px;

    font-size: 14px;

    font-weight: bold;
}

input,
select,
textarea {

    width: 100%;

    border: 1px solid #dddddd;

    border-radius: 7px;

    font-size: 14px;

    outline: none;

    font-family: Arial, Helvetica, sans-serif;
}

input,
select {

    height: 46px;

    padding: 0 13px;
}

textarea {

    min-height: 100px;

    padding: 13px;

    resize: vertical;
}

input:focus,
select:focus,
textarea:focus {

    border-color: #74ebd5;

    box-shadow:
        0 0 0 3px
        rgba(
            116,
            235,
            213,
            0.15
        );
}

.purchase-options {

    display: flex;

    gap: 15px;
}

.purchase-option {

    flex: 1;

    position: relative;
}

.purchase-option input {

    position: absolute;

    opacity: 0;
}

.purchase-option label {

    height: 60px;

    border: 1px solid #dddddd;

    border-radius: 8px;

    display: flex;

    align-items: center;

    justify-content: center;

    cursor: pointer;

    font-size: 15px;

    transition: 0.2s;
}

.purchase-option input:checked + label {

    background:
        linear-gradient(
            to right,
            #74ebd5,
            #acb6e5
        );

    color: #ffffff;

    border-color: #74ebd5;
}

.dynamic-section {

    display: none;
}

.error {

    background: #ffe8e8;

    border: 1px solid #ffbaba;

    color: #c62828;

    padding: 12px;

    border-radius: 7px;

    margin-bottom: 25px;

    font-size: 14px;
}

.actions {

    display: flex;

    justify-content: flex-end;

    gap: 12px;

    margin-top: 30px;

    padding-top: 25px;

    border-top: 1px solid #eeeeee;
}

.button {

    height: 46px;

    padding: 0 25px;

    border-radius: 7px;

    border: none;

    font-size: 14px;

    font-weight: bold;

    cursor: pointer;

    text-decoration: none;

    display: inline-flex;

    align-items: center;

    justify-content: center;
}

.cancel {

    background: #eeeeee;

    color: #555555;
}

.save {

    background:
        linear-gradient(
            to right,
            #74ebd5,
            #acb6e5
        );

    color: #ffffff;
}

@media (max-width: 700px) {

    .form-grid {

        grid-template-columns: 1fr;
    }

    .form-group.full {

        grid-column: auto;
    }

    .form-box {

        padding: 25px 20px;
    }

    .purchase-options {

        flex-direction: column;
    }

}

</style>

<script>

function showPurchaseSection(value)
{
    var purchase =
        document.getElementById(
            'purchase-section'
        );

    var movement =
        document.getElementById(
            'movement-section'
        );

    if (value == '1') {

        purchase.style.display = 'block';

        movement.style.display = 'none';

    } else if (value == '0') {

        purchase.style.display = 'none';

        movement.style.display = 'block';

    } else {

        purchase.style.display = 'none';

        movement.style.display = 'none';
    }
}

function showMovementType(value)
{
    var soldPrice =
        document.getElementById(
            'sold-price'
        );

    if (value == 'Sold') {

        soldPrice.style.display = 'block';

    } else {

        soldPrice.style.display = 'none';
    }
}

</script>

</head>

<body>

<div class="header">

<div class="logo">
Wifonic Hardware
</div>

<div class="header-right">

<span>
<?php
echo htmlspecialchars(
    $_SESSION['username'],
    ENT_QUOTES,
    'UTF-8'
);
?>
</span>

<a
    class="logout"
    href="../logout.php"
>
Logout
</a>

</div>

</div>

<div class="container">

<div class="form-box">

<h1 class="page-title">
Add Device
</h1>

<div class="page-subtitle">
Enter the basic information for the new device.
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

<form method="post">

<div class="section">

<div class="section-title">
Device Basic Information
</div>

<div class="form-grid">

<div class="form-group">

<label>
Asset Tag *
</label>

<input
    type="text"
    name="asset_tag"
    required
    value="<?php

    echo isset($_POST['asset_tag'])
        ? htmlspecialchars(
            $_POST['asset_tag'],
            ENT_QUOTES,
            'UTF-8'
        )
        : '';

    ?>"
>

</div>

<div class="form-group">

<label>
Device Type *
</label>

<input
    type="text"
    name="device_type"
    placeholder="Laptop, Monitor, Router..."
    required
    value="<?php

    echo isset($_POST['device_type'])
        ? htmlspecialchars(
            $_POST['device_type'],
            ENT_QUOTES,
            'UTF-8'
        )
        : '';

    ?>"
>

</div>

<div class="form-group">

<label>
Brand
</label>

<input
    type="text"
    name="brand"
    value="<?php

    echo isset($_POST['brand'])
        ? htmlspecialchars(
            $_POST['brand'],
            ENT_QUOTES,
            'UTF-8'
        )
        : '';

    ?>"
>

</div>

<div class="form-group">

<label>
Model
</label>

<input
    type="text"
    name="model"
    value="<?php

    echo isset($_POST['model'])
        ? htmlspecialchars(
            $_POST['model'],
            ENT_QUOTES,
            'UTF-8'
        )
        : '';

    ?>"
>

</div>

<div class="form-group">

<label>
Serial Number
</label>

<input
    type="text"
    name="serial_number"
    value="<?php

    echo isset($_POST['serial_number'])
        ? htmlspecialchars(
            $_POST['serial_number'],
            ENT_QUOTES,
            'UTF-8'
        )
        : '';

    ?>"
>

</div>

<div class="form-group full">

<label>
Specification
</label>

<textarea
    name="specification"
><?php

echo isset($_POST['specification'])
    ? htmlspecialchars(
        $_POST['specification'],
        ENT_QUOTES,
        'UTF-8'
    )
    : '';

?></textarea>

</div>

</div>

</div>

<div class="section">

<div class="section-title">
Was this device purchased?
</div>

<div class="purchase-options">

<div class="purchase-option">

<input
    type="radio"
    id="purchased_yes"
    name="purchased"
    value="1"
    onclick="showPurchaseSection('1')"
>

<label for="purchased_yes">
YES
</label>

</div>

<div class="purchase-option">

<input
    type="radio"
    id="purchased_no"
    name="purchased"
    value="0"
    onclick="showPurchaseSection('0')"
>

<label for="purchased_no">
NO
</label>

</div>

</div>

</div>

<div
    id="purchase-section"
    class="section dynamic-section"
>

<div class="section-title">
Purchase Details
</div>

<div class="form-grid">

<div class="form-group">

<label>
Vendor
</label>

<input
    type="text"
    name="vendor"
>

</div>

<div class="form-group">

<label>
Purchase Date
</label>

<input
    type="date"
    name="purchase_date"
>

</div>

<div class="form-group">

<label>
Price
</label>

<input
    type="number"
    step="0.01"
    name="price"
>

</div>

<div class="form-group">

<label>
Invoice Number
</label>

<input
    type="text"
    name="invoice_number"
>

</div>

</div>

</div>

<div
    id="movement-section"
    class="section dynamic-section"
>

<div class="section-title">
Movement Details
</div>

<div class="form-grid">

<div class="form-group">

<label>
Movement Reason
</label>

<select name="movement_reason">

<option value="">
Select reason
</option>

<option value="Terminated Client">
Terminated Client
</option>

<option value="Office">
Office
</option>

<option value="Replacement">
Replacement
</option>

</select>

</div>

<div class="form-group">

<label>
Movement Type
</label>

<select
    name="movement_type"
    onchange="showMovementType(this.value)"
>

<option value="">
Select type
</option>

<option value="Sent">
Sent
</option>

<option value="Sold">
Sold
</option>

</select>

</div>

<div class="form-group">

<label>
Date
</label>

<input
    type="date"
    name="movement_date"
>

</div>

<div class="form-group">

<label>
Property
</label>

<input
    type="text"
    name="property_number"
>

</div>

<div class="form-group">

<label>
Destination / Office / Client
</label>

<input
    type="text"
    name="destination"
>

</div>

<div
    class="form-group"
    id="sold-price"
    style="display:none;"
>

<label>
Sold Price
</label>

<input
    type="number"
    step="0.01"
    name="movement_price"
>

</div>

</div>

</div>

<div class="actions">

<a
    class="button cancel"
    href="../dashboard.php"
>
Cancel
</a>

<button
    type="submit"
    class="button save"
>
Save Device
</button>

</div>

</form>

</div>

</div>

</body>

</html>
