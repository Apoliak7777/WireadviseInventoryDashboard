<?php
require_once 'db.php';

// TECHNICIANS
$technicians = [];
try {
    $result = $db->query("SELECT name FROM technicians ORDER BY name ASC");
    $technicians = $result->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $technicians = [];
}

// DISTINCT PROJECTS for autocomplete
$projects = [];
try {
    $result = $db->query("SELECT DISTINCT project FROM items WHERE project IS NOT NULL AND TRIM(project) <> '' ORDER BY project ASC");
    $projects = $result->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $projects = [];
}

$allowedLocations = ['Office','Storage','Honda Vehicle','Nissan Vehicle'];

$error = '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barcode = trim($_POST['barcode'] ?? '');
    $part_number = trim($_POST['part_number'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $vendor = trim($_POST['vendor'] ?? '');
    $assigned_technician = trim($_POST['assigned_technician'] ?? '');
    $project = trim($_POST['project'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $qty = (int)($_POST['quantity_received'] ?? 1);
    $date = date('Y-m-d H:i');
    $item_id = uniqid('item-');

    if ($barcode === '') {
        $barcode = strval(rand(100000000000, 999999999999));
    }
    if ($description === '' || $vendor === '' || $qty < 1 || $location === '') {
        $error = "All fields are required!";
    }
    if ($location && !in_array($location, $allowedLocations, true)) {
        $error = "Invalid location.";
    }

    // UPLOAD PICTURE (allow camera or gallery – no capture attribute)
    if (!$error && isset($_FILES['picture']) && $_FILES['picture']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['picture']['error'] === UPLOAD_ERR_OK) {
            $target_dir = "uploads/";
            if (!is_dir($target_dir) && !mkdir($target_dir, 0777, true)) {
                $error = "Could not create uploads directory.";
            } else {
                $ext = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','gif','webp'];
                if (in_array($ext, $allowed)) {
                    $target_file = $target_dir . $item_id . "." . $ext;
                    if (!move_uploaded_file($_FILES['picture']['tmp_name'], $target_file)) {
                        $error = "Failed to save image. Check write permissions.";
                    }
                } else {
                    $error = "Unsupported image type.";
                }
            }
        } else {
            $error = "Image upload error: " . $_FILES['picture']['error'];
        }
    }

    if (!$error) {
        try {
            $stmt = $db->prepare("INSERT INTO items (item_id, barcode, part_number, description, vendor, assigned_technician, quantity_received, quantity_available, date_received, status, project, location) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Available', ?, ?)");
            $stmt->execute([$item_id, $barcode, $part_number, $description, $vendor, $assigned_technician, $qty, $qty, $date, $project, $location]);
            $msg = "Item has been added.";
            $_POST = [];
        } catch (PDOException $ex) {
            $error = "DB error: " . $ex->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Add item</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="stylesheet" href="assets/style.css">
    <style>
    /* Mobile-first form layout (unchanged) */
    html,body{background:#14181a;color:#eaeaea;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;margin:0;padding:0;}
    .container{max-width:420px;margin:0 auto;background:#232c34;border-radius:16px;box-shadow:0 2px 18px #0008;padding:18px 10px;margin-top:14px;}
    h2{color:#00e676;text-align:center;margin:10px 0 12px;}
    .form-row{display:flex;flex-direction:column;margin-bottom:14px;}
    label{color:#00e676;margin-bottom:6px;}
    input,select,datalist{padding:10px;border-radius:7px;border:none;background:#181a1b;color:#eaeaea;}
    .barcode-btns{display:flex;gap:8px;margin-top:6px;}
    .btn{background:#00e676;color:#081018;border:none;border-radius:8px;padding:10px 12px;font-weight:700;cursor:pointer;}
    .item-picture-thumb{max-width:48px;max-height:48px;border-radius:8px;object-fit:cover;display:inline-block;}
    #qr-reader{width:96vw;max-width:340px;margin:8px auto;display:none;}

    /* DESKTOP: wider container and better form layout */
    @media (min-width:992px) {
      .container{max-width:1000px;padding:24px;margin:20px auto;border-radius:12px;}
      .form-row{flex-direction:row;align-items:center;gap:12px;}
      .form-row label{flex:0 0 180px;margin:0;}
      .form-row input,.form-row select{flex:1;}
      .barcode-btns{flex:1;display:flex;justify-content:flex-start;}
      .btn{padding:10px 14px;}
    }
    </style>
</head>
<body>
    <div class="container">
        <h2>Add item to warehouse</h2>
        <?php if ($error): ?><div style="color:#ff1744;margin-bottom:10px;text-align:center"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($msg): ?><div style="color:#00e676;margin-bottom:10px;text-align:center"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-row">
                <label>Item (optional):</label>
                <input type="text" name="manual_item" placeholder="Optional item label" value="<?= htmlspecialchars($_POST['manual_item'] ?? '') ?>">
            </div>

            <div class="form-row">
                <label>Part #:</label>
                <input type="text" name="part_number" value="<?= htmlspecialchars($_POST['part_number'] ?? '') ?>">
            </div>

            <div class="form-row">
                <label>Barcode / QR:</label>
                <input type="text" id="barcode" name="barcode" value="<?= htmlspecialchars($_POST['barcode'] ?? '') ?>">
                <div class="barcode-btns">
                    <button type="button" class="btn" onclick="openScanner()">Scan</button>
                    <button type="button" class="btn" onclick="regenerateBarcode()">Regenerate</button>
                </div>
            </div>

            <div id="qr-reader"></div>

            <div class="form-row">
                <label>Description:</label>
                <input type="text" name="description" value="<?= htmlspecialchars($_POST['description'] ?? '') ?>" required>
            </div>

            <div class="form-row">
                <label>Vendor:</label>
                <input type="text" name="vendor" value="<?= htmlspecialchars($_POST['vendor'] ?? '') ?>" required>
            </div>

            <div class="form-row">
                <label>Technician:</label>
                <select name="assigned_technician">
                    <option value="">-- Select technician --</option>
                    <?php foreach ($technicians as $tech): ?>
                        <option value="<?= htmlspecialchars($tech) ?>" <?= (($_POST['assigned_technician'] ?? '') == $tech ? 'selected' : '') ?>><?= htmlspecialchars($tech) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label>Project:</label>
                <input type="text" name="project" list="project_list" placeholder="e.g. Anjel" value="<?= htmlspecialchars($_POST['project'] ?? '') ?>">
                <datalist id="project_list">
                    <?php foreach ($projects as $p): ?><option value="<?= htmlspecialchars($p) ?>"></option><?php endforeach; ?>
                </datalist>
            </div>

            <div class="form-row">
                <label>Location:</label>
                <select name="location" required>
                    <option value="">-- Select location --</option>
                    <option value="Office" <?= (($_POST['location'] ?? '') === 'Office' ? 'selected' : '') ?>>Office</option>
                    <option value="Storage" <?= (($_POST['location'] ?? '') === 'Storage' ? 'selected' : '') ?>>Storage</option>
                    <option value="Honda Vehicle" <?= (($_POST['location'] ?? '') === 'Honda Vehicle' ? 'selected' : '') ?>>Honda Vehicle</option>
                    <option value="Nissan Vehicle" <?= (($_POST['location'] ?? '') === 'Nissan Vehicle' ? 'selected' : '') ?>>Nissan Vehicle</option>
                </select>
            </div>

            <div class="form-row">
                <label>Quantity received:</label>
                <input type="number" name="quantity_received" min="1" value="<?= htmlspecialchars($_POST['quantity_received'] ?? '1') ?>" required>
            </div>

            <div class="form-row">
                <label>Add picture:</label>
                <input type="file" name="picture" accept="image/*">
            </div>

            <div style="text-align:center;margin-top:10px;">
                <button type="submit" class="btn">Save</button>
                <a href="index.php" class="btn" style="background:#232c34;color:#00e676;margin-left:8px;">Back to warehouse</a>
            </div>
        </form>
    </div>

    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
    let scannerOpen=false, html5QrcodeScanner;
    function openScanner(){
      if(scannerOpen) return;
      const qrEl = document.getElementById('qr-reader');
      if(qrEl) qrEl.style.display = 'block';
      html5QrcodeScanner = new Html5Qrcode("qr-reader");
      html5QrcodeScanner.start({ facingMode: "environment" }, { fps:10, qrbox:180 },
        qrCodeMessage => {
          document.getElementById('barcode').value = qrCodeMessage;
          html5QrcodeScanner.stop();
          if(qrEl) qrEl.style.display = 'none';
          scannerOpen=false;
        },
        errorMessage => {}
      );
      scannerOpen=true;
    }
    function regenerateBarcode(){ document.getElementById('barcode').value = Math.floor(100000000000 + Math.random()*900000000000); }
    </script>
</body>
</html>