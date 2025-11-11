<?php
require_once 'db.php';

$error = '';
$msg = '';

// TECHNICIANS
$technicians = [];
try {
    $result = $db->query("SELECT name FROM technicians ORDER BY name ASC");
    $technicians = $result->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $technicians = [];
}

$allowedLocations = ['Office','Storage','Honda Vehicle','Nissan Vehicle'];

// ITEMS (only those available)
$items = [];
try {
    $result = $db->query("SELECT * FROM items WHERE quantity_available > 0 ORDER BY description ASC");
    $items = $result->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $items = [];
}

// FORM SUBMIT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = $_POST['item_id'] ?? '';
    $technician = trim($_POST['technician'] ?? '');
    $qty = (int)($_POST['quantity'] ?? 1);
    $job_location = trim($_POST['job_location'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $date = date('Y-m-d H:i');

    if ($item_id === '' || $technician === '' || $qty < 1) {
        $error = "Please select item, technician and valid quantity.";
    } elseif ($location !== '' && !in_array($location, $allowedLocations, true)) {
        $error = "Invalid location.";
    } else {
        $itemS = $db->prepare("SELECT * FROM items WHERE item_id = ?");
        $itemS->execute([$item_id]);
        $item = $itemS->fetch(PDO::FETCH_ASSOC);
        if ($item && $item['quantity_available'] >= $qty) {
            $new_qty = $item['quantity_available'] - $qty;
            $status = ($new_qty == 0) ? 'Assigned' : $item['status'];
            $db->prepare("UPDATE items SET quantity_available=?, status=?, assigned_technician=?, date_deployed=?, job_location=?, location=? WHERE item_id=?")
               ->execute([$new_qty, $status, $technician, $date, $job_location, $location, $item_id]);
            // insert history
            $db->prepare("INSERT INTO history (item_id, action, technician, quantity, date, job_location) VALUES (?, 'Assigned', ?, ?, ?, ?)")
               ->execute([$item_id, $technician, $qty, $date, $job_location]);
            $msg = "Item assigned to technician.";
            // refresh items
            $result = $db->query("SELECT * FROM items WHERE quantity_available > 0 ORDER BY description ASC");
            $items = $result->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $error = "Not enough stock!";
        }
    }
}

// Barcode->item_id map for scanner
$barcodeMap = [];
foreach ($items as $i) {
    $barcodeMap[$i['barcode']] = $i['item_id'];
}
?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Take item</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="stylesheet" href="assets/style.css">
    <style>
    /* Mobile-first form layout (unchanged) */
    html,body{background:#14181a;color:#eaeaea;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;margin:0;padding:0;}
    .container{max-width:420px;margin:0 auto;background:#232c34;border-radius:16px;box-shadow:0 2px 18px #0008;padding:18px 10px;margin-top:14px;}
    h2{color:#00e676;text-align:center;margin:10px 0 12px;}
    .form-row{display:flex;flex-direction:column;margin-bottom:14px;}
    label{color:#00e676;margin-bottom:6px;}
    input,select,datalist,textarea{padding:10px;border-radius:7px;border:none;background:#181a1b;color:#eaeaea;}
    .barcode-btns{display:flex;gap:8px;margin-top:6px;}
    .btn{background:#00e676;color:#081018;border:none;border-radius:8px;padding:10px 12px;font-weight:700;cursor:pointer;}
    .item-picture-thumb{max-width:48px;max-height:48px;border-radius:8px;object-fit:cover;display:inline-block;}

    .items-table{margin-top:14px;}
    table{width:100%;border-collapse:collapse;}
    thead,tbody,tr,th,td{display:block;width:100%;}
    thead{display:none;}
    tr{margin-bottom:9px;background:#232c34;border-radius:8px;padding:8px 0;box-shadow:0 1px 6px #0002;}
    td{padding:6px 10px;display:block;}
    td:before{content:attr(data-label);display:block;color:#00e676;font-weight:700;margin-bottom:6px;}

    #qr-reader{width:96vw;max-width:340px;margin:8px auto;display:none;}

    /* DESKTOP: show classic table and wider container */
    @media (min-width:992px) {
      .container{max-width:1000px;padding:24px;margin:20px auto;border-radius:12px;}
      .form-row{flex-direction:row;align-items:center;gap:12px;}
      .form-row label{flex:0 0 180px;margin:0;}
      .form-row input,.form-row select,.form-row textarea{flex:1;}
      .barcode-btns{flex:1;display:flex;justify-content:flex-start;}
      /* revert table elements */
      table, thead, tbody, tr, th, td { display: table; width: auto; }
      table { width:100%; table-layout: auto; border-collapse: separate; border-spacing: 0; }
      thead { display: table-header-group; }
      tbody { display: table-row-group; }
      tr { display: table-row; margin:0; background:transparent; box-shadow:none; }
      th, td { display: table-cell; padding:10px 12px; vertical-align:middle; border-bottom:1px solid #2b343a; white-space:nowrap; }
      th { color:#00e676; font-weight:700; background:#1b2227; position:sticky; top:0; z-index:2; text-align:left; }
      td { color:#e6eded; background:#0f1112; border-right:1px solid #243039; }
      td:before{ display:none !important; content:none !important; }
      th, td { border-left:none !important; }
      .item-picture-thumb { width:36px; height:36px; }
    }
    </style>
</head>
<body>
    <div class="container">
        <h2>Take item for technician</h2>
        <?php if ($error): ?><div style="color:#ff1744;margin-bottom:10px;text-align:center"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($msg): ?><div style="color:#00e676;margin-bottom:10px;text-align:center"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

        <form method="POST" id="assignForm">
            <div class="form-row">
                <label for="item_id">Item:</label>
                <select name="item_id" id="item_id" required>
                    <option value="">-- Select item --</option>
                    <?php foreach ($items as $i): ?>
                        <option value="<?= htmlspecialchars($i['item_id']) ?>" data-barcode="<?= htmlspecialchars($i['barcode']) ?>">
                            <?= htmlspecialchars($i['description']) ?> (<?= (int)$i['quantity_available'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label></label>
                <div class="barcode-btns">
                    <button type="button" class="btn" onclick="openScanner()">Scan</button>
                    <button type="button" class="btn" onclick="document.getElementById('qr-reader').style.display='none';">Close scanner</button>
                </div>
            </div>

            <div id="qr-reader"></div>

            <div class="form-row">
                <label for="technician">Technician:</label>
                <select name="technician" id="technician" required>
                    <option value="">-- Select technician --</option>
                    <?php foreach ($technicians as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label for="quantity">Quantity:</label>
                <input type="number" name="quantity" id="quantity" min="1" value="1" required>
            </div>

            <div class="form-row">
                <label for="job_location">Job / Location:</label>
                <input type="text" name="job_location" id="job_location">
            </div>

            <div class="form-row">
                <label for="location">Location:</label>
                <select name="location" id="location">
                    <option value="">-- Select --</option>
                    <option value="Office">Office</option>
                    <option value="Storage">Storage</option>
                    <option value="Honda Vehicle">Honda Vehicle</option>
                    <option value="Nissan Vehicle">Nissan Vehicle</option>
                </select>
            </div>

            <div style="text-align:center;margin-top:8px;">
                <button type="submit" class="btn">Assign to technician</button>
                <a href="index.php" class="btn" style="background:#232c34;color:#00e676;margin-left:8px;">Back to warehouse</a>
            </div>
        </form>

        <h2 style="margin-top:20px">Available items</h2>
        <div class="items-table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Barcode</th>
                        <th>Description</th>
                        <th>Vendor</th>
                        <th>Available</th>
                        <th>Picture</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td data-label="ID"><?= htmlspecialchars($it['item_id']) ?></td>
                        <td data-label="Barcode"><?= htmlspecialchars($it['barcode']) ?></td>
                        <td data-label="Description"><?= htmlspecialchars($it['description']) ?></td>
                        <td data-label="Vendor"><?= htmlspecialchars($it['vendor']) ?></td>
                        <td data-label="Available"><?= htmlspecialchars($it['quantity_available']) ?></td>
                        <td data-label="Picture">
                            <?php
                            $img='';
                            foreach(['jpg','jpeg','png','gif','webp'] as $ext){
                                if(file_exists("uploads/{$it['item_id']}.$ext")){ $img="uploads/{$it['item_id']}.$ext"; break; }
                            }
                            if($img) echo "<img src=\"$img\" class=\"item-picture-thumb\" alt=\"pic\">"; else echo "–";
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
    let scannerOpen=false, html5QrcodeScanner;
    const barcodeMap = <?= json_encode($barcodeMap) ?>;
    function openScanner(){
      if(scannerOpen) return;
      const qrEl = document.getElementById('qr-reader');
      if(qrEl) qrEl.style.display = 'block';
      html5QrcodeScanner = new Html5Qrcode("qr-reader");
      html5QrcodeScanner.start({ facingMode: "environment" }, { fps:10, qrbox:180 },
        qrCodeMessage => {
          if(barcodeMap[qrCodeMessage]){
            document.getElementById('item_id').value = barcodeMap[qrCodeMessage];
          } else {
            alert("This code is not in warehouse!");
          }
          html5QrcodeScanner.stop();
          document.getElementById('qr-reader').style.display = 'none';
          scannerOpen=false;
        },
        errorMessage => {}
      );
      scannerOpen=true;
    }
    </script>
</body>
</html>