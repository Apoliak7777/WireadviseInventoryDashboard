<?php
require_once 'db.php';

$error = '';
$msg = '';

// ID
$item_id = $_GET['id'] ?? '';
if (!$item_id) { die('Missing item ID.'); }

// Load item
$stmt = $db->prepare("SELECT * FROM items WHERE item_id = ?");
$stmt->execute([$item_id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$item) { die('Item not found.'); }

// PROJECTS for autocomplete
$projects = [];
try {
    $result = $db->query("SELECT DISTINCT project FROM items WHERE project IS NOT NULL AND TRIM(project) <> '' ORDER BY project ASC");
    $projects = $result->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $projects = [];
}

$allowedLocations = ['Office','Storage','Honda Vehicle','Nissan Vehicle'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barcode = trim($_POST['barcode'] ?? '');
    $part_number = trim($_POST['part_number'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $vendor = trim($_POST['vendor'] ?? '');
    $qty_received = (int)($_POST['quantity_received'] ?? 1);
    $qty_available = (int)($_POST['quantity_available'] ?? 1);
    $status = trim($_POST['status'] ?? '');
    $tech = trim($_POST['assigned_technician'] ?? '');
    $date_received = $_POST['date_received'] ?? '';
    $date_deployed = $_POST['date_deployed'] ?? '';
    $project = trim($_POST['project'] ?? '');
    $location = trim($_POST['location'] ?? '');

    if ($barcode === '' || $description === '' || $vendor === '' || $qty_received < 1) {
        $error = "All fields are required!";
    }
    if ($location !== '' && !in_array($location, $allowedLocations, true)) {
        $error = "Invalid location.";
    }

    // Image upload (camera or gallery)
    if (!$error && isset($_FILES['picture']) && $_FILES['picture']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['picture']['error'] === UPLOAD_ERR_OK) {
            $target_dir = "uploads/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (in_array($ext, $allowed)) {
                foreach($allowed as $old_ext) {
                    $old_path = $target_dir . $item_id . '.' . $old_ext;
                    if (file_exists($old_path)) @unlink($old_path);
                }
                $target_file = $target_dir . $item_id . "." . $ext;
                if (!move_uploaded_file($_FILES['picture']['tmp_name'], $target_file)) {
                    $error = "Failed to save image. Check write permissions.";
                }
            } else {
                $error = "Unsupported image type.";
            }
        } else {
            $error = "Image upload error: " . $_FILES['picture']['error'];
        }
    }

    if (!$error) {
        try {
            $stmt = $db->prepare("UPDATE items SET barcode=?, part_number=?, description=?, vendor=?, quantity_received=?, quantity_available=?, status=?, assigned_technician=?, date_received=?, date_deployed=?, project=?, location=? WHERE item_id=?");
            $stmt->execute([
                $barcode,
                $part_number,
                $description,
                $vendor,
                $qty_received,
                $qty_available,
                $status,
                $tech,
                $date_received,
                $date_deployed,
                $project,
                $location,
                $item_id
            ]);
            $msg = "Item updated!";
            // Reload
            $stmt = $db->prepare("SELECT * FROM items WHERE item_id = ?");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $ex) {
            $error = "DB error: " . $ex->getMessage();
        }
    }
}

// Current image
$img = '';
foreach(['jpg','jpeg','png','gif','webp'] as $ext) {
    if (file_exists("uploads/$item_id.$ext")) { $img = "uploads/$item_id.$ext"; break; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit item</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="stylesheet" href="assets/style.css">
    <style>
    html, body {background:#14181a; color:#eaeaea; font-family:sans-serif; margin:0; padding:0;}
    .container {max-width:420px; margin:0 auto; background:#232c34; border-radius:16px; box-shadow:0 2px 18px #000a; padding:19px 8px; margin-top:12px;}
    @media (max-width:700px) { html, body { font-size:15px;} .container {max-width:100vw; margin:0 auto; padding:7vw 2vw 5vw 2vw; border-radius:13px;} }
    h2 {text-align:center; font-size:1.25em; color:#00e676; margin-bottom:18px;}
    .error-msg {color:#ff1744;font-weight:bold;margin-bottom:10px;text-align:center;}
    .success-msg {color:#00e676;font-weight:bold;margin-bottom:10px;text-align:center;}
    .form-row {display:flex;flex-direction:column;margin-bottom:14px;}
    label {margin-bottom:5px;color:#00e676;font-size:0.98em;}
    input, select {padding:10px 8px;border-radius:7px;border:none;background:#181a1b;color:#eaeaea;font-size:1em;margin-bottom:2px;}
    input[type="file"] {padding:2px 0;}
    .btn {background:#00e676;color:#181a1b;border:none;border-radius:7px;padding:12px 0;font-size:1em;font-weight:600;margin-bottom:11px;margin-top:5px;cursor:pointer;}
    .btn:active {background:#00be5a;}
    a.btn {display:block;text-align:center;text-decoration:none;}
    .item-picture-thumb {max-width:92px;max-height:92px;border-radius:8px;box-shadow:0 2px 8px #0008;margin-bottom:10px;}
    </style>
</head>
<body>
    <div class="container">
        <h2>Edit item</h2>
        <?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($msg): ?><div class="success-msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-row">
                <label>Barcode:</label>
                <input type="text" name="barcode" value="<?= htmlspecialchars($item['barcode']) ?>">
            </div>
            <div class="form-row">
                <label>Part #:</label>
                <input type="text" name="part_number" value="<?= htmlspecialchars($item['part_number'] ?? '') ?>">
            </div>
            <div class="form-row">
                <label>Description:</label>
                <input type="text" name="description" value="<?= htmlspecialchars($item['description']) ?>">
            </div>
            <div class="form-row">
                <label>Vendor:</label>
                <input type="text" name="vendor" value="<?= htmlspecialchars($item['vendor']) ?>">
            </div>
            <div class="form-row">
                <label>Quantity received:</label>
                <input type="number" name="quantity_received" min="1" value="<?= htmlspecialchars($item['quantity_received']) ?>">
            </div>
            <div class="form-row">
                <label>Quantity available:</label>
                <input type="number" name="quantity_available" min="0" value="<?= htmlspecialchars($item['quantity_available']) ?>">
            </div>
            <div class="form-row">
                <label>Status:</label>
                <input type="text" name="status" value="<?= htmlspecialchars($item['status']) ?>">
            </div>
            <div class="form-row">
                <label>Technician:</label>
                <input type="text" name="assigned_technician" value="<?= htmlspecialchars($item['assigned_technician']) ?>">
            </div>
            <div class="form-row">
                <label>Location:</label>
                <select name="location">
                    <option value="">-- Select location --</option>
                    <?php foreach ($allowedLocations as $loc): ?>
                        <option value="<?= htmlspecialchars($loc) ?>" <?= (($item['location'] ?? '') === $loc ? 'selected' : '') ?>><?= htmlspecialchars($loc) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label>Date received:</label>
                <input type="datetime-local" name="date_received" value="<?= $item['date_received'] ? date('Y-m-d\TH:i', strtotime($item['date_received'])) : '' ?>">
            </div>
            <div class="form-row">
                <label>Date deployed:</label>
                <input type="datetime-local" name="date_deployed" value="<?= $item['date_deployed'] ? date('Y-m-d\TH:i', strtotime($item['date_deployed'])) : '' ?>">
            </div>

            <div class="form-row">
                <label>Project:</label>
                <input type="text" name="project" list="project_list" placeholder="e.g. Anjel" value="<?= htmlspecialchars($item['project'] ?? '') ?>">
                <datalist id="project_list">
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= htmlspecialchars($p) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="form-row">
                <label>Replace picture:</label>
                <input type="file" name="picture" accept="image/*">
            </div>
            <?php if($img): ?><img src="<?= $img ?>" class="item-picture-thumb" alt="Current picture"><?php endif; ?>
            <button type="submit" class="btn">Save changes</button>
            <a href="index.php" class="btn" style="background:#232c34;color:#00e676;">Back</a>
        </form>
    </div>
</body>
</html>