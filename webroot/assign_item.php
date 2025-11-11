<?php
require_once 'db.php';

$techs = $db->query("SELECT name FROM technicians")->fetchAll(PDO::FETCH_COLUMN);
$items = $db->query("SELECT * FROM items WHERE quantity_available > 0")->fetchAll(PDO::FETCH_ASSOC);

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = $_POST['item_id'];
    $technician = $_POST['technician'];
    $qty = (int)$_POST['quantity'];
    $job_location = $_POST['job_location'] ?? '';
    $date = date('Y-m-d H:i');

    $item = $db->query("SELECT * FROM items WHERE item_id='$item_id'")->fetch(PDO::FETCH_ASSOC);
    if ($item && $item['quantity_available'] >= $qty) {
        $new_qty = $item['quantity_available'] - $qty;
        $status = ($new_qty == 0) ? 'Assigned' : $item['status'];
        $db->prepare("UPDATE items SET quantity_available=?, status=?, assigned_technician=?, date_deployed=?, job_location=? WHERE item_id=?")
           ->execute([$new_qty, $status, $technician, $date, $job_location, $item_id]);
        $db->prepare("INSERT INTO history (item_id, action, technician, quantity, date, job_location) VALUES (?, 'Assigned', ?, ?, ?, ?)")
           ->execute([$item_id, $technician, $qty, $date, $job_location]);
        $msg = "Položka bola pridelená.";
    } else {
        $msg = "Nedostatočný počet na sklade!";
    }
}

// Barcode->item_id map
$barcodeMap = [];
foreach ($items as $i) {
    $barcodeMap[$i['barcode']] = $i['item_id'];
}
?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Výdaj položky</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="stylesheet" href="assets/style.css">
    <style>
    html, body {background:#14181a; color:#eaeaea; font-family:sans-serif; margin:0; padding:0;}
    .container {max-width:420px; margin:0 auto; background:#232c34; border-radius:16px; box-shadow:0 2px 18px #000a; padding:19px 8px; margin-top:12px;}
    @media(max-width:700px){html,body{font-size:15px;}.container{max-width:100vw;margin:0 auto;padding:7vw 2vw 5vw 2vw;border-radius:13px;}}
    h2 {text-align:center;font-size:1.25em;color:#00e676;margin-bottom:18px;}
    .form-row {display:flex;flex-direction:column;margin-bottom:14px;}
    label {margin-bottom:5px;color:#00e676;font-size:0.98em;}
    input, select {padding:10px 8px;border-radius:7px;border:none;background:#181a1b;color:#eaeaea;font-size:1em;margin-bottom:2px;}
    .barcode-btns {display:flex;gap:7px;margin-top:6px;}
    .barcode-btns .btn {width:99%;background:#232c34;}
    .btn {background:#00e676;color:#181a1b;border:none;border-radius:7px;padding:12px 0;font-size:1em;font-weight:600;margin-bottom:11px;margin-top:5px;cursor:pointer;}
    .btn:active {background:#00be5a;}
    a.btn {display:block;text-align:center;text-decoration:none;}
    @media(max-width:700px){input,select{font-size:1em;padding:11px 9px;}.btn,button.btn{font-size:1em;padding:13px 0;}.barcode-btns .btn{font-size:0.97em;}.form-row{margin-bottom:11px;}}
    .items-table {margin-top:13px;}
    table {width:100%;border-collapse:collapse;}
    th, td {padding:7px 6px;font-size:0.98em;}
    th {color:#00e676;}
    tr {background:#181a1b;}
    @media(max-width:700px){table,thead,tbody,th,td,tr{display:block;width:100%;}thead{display:none;}tr{margin-bottom:9px;border-radius:7px;background:#232c34;box-shadow:0 1px 6px #0002;padding:5px 0;}td{padding:4px 7px !important;text-align:left;border:none;position:relative;font-size:0.97em !important;}td:before{content:attr(data-label);font-weight:bold;color:#00e676;display:block;margin-bottom:1.5px;font-size:0.93em;}}
    </style>
    <script src="https://unpkg.com/html5-qrcode"></script>
</head>
<body>
    <div class="container">
        <h2>Výdaj položky technikovi</h2>
        <?php if ($msg): ?><p style="color:#00e676;text-align:center;"><?= $msg ?></p><?php endif; ?>
        <form method="POST" id="assignForm">
            <div class="form-row">
                <label>Položka:</label>
                <select name="item_id" id="item_id" required>
                    <?php foreach ($items as $i): ?>
                        <option value="<?= $i['item_id'] ?>" data-barcode="<?= htmlspecialchars($i['barcode']) ?>">
                            <?= $i['description'] ?> (<?= $i['quantity_available'] ?> ks)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="barcode-btns">
                    <button type="button" class="btn" onclick="openScanner()">Skenovať</button>
                </div>
            </div>
            <div id="qr-reader" style="width:96vw;max-width:340px;margin:auto;display:none"></div>
            <div class="form-row">
                <label>Technik:</label>
                <select name="technician" required>
                    <?php foreach ($techs as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label>Množstvo:</label>
                <input type="number" name="quantity" min="1" value="1" required>
            </div>
            <div class="form-row">
                <label>Job / Miesto použitia:</label>
                <input type="text" name="job_location">
            </div>
            <button type="submit" class="btn">Pridať technikovi</button>
            <a href="index.php" class="btn" style="background:#232c34;color:#00e676;">Späť na prehľad</a>
        </form>
        <div class="items-table">
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Barcode</th>
                <th>Description</th>
                <th>Vendor</th>
                <th>Available</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td data-label="ID"><?= htmlspecialchars($item['item_id']) ?></td>
                <td data-label="Barcode"><?= htmlspecialchars($item['barcode']) ?></td>
                <td data-label="Description"><?= htmlspecialchars($item['description']) ?></td>
                <td data-label="Vendor"><?= htmlspecialchars($item['vendor']) ?></td>
                <td data-label="Available"><?= htmlspecialchars($item['quantity_available']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <script>
    let scannerOpen = false;
    let html5QrcodeScanner;
    const barcodeMap = <?= json_encode($barcodeMap) ?>;

    function openScanner() {
        if(scannerOpen) return;
        document.getElementById('qr-reader').style.display = "block";
        html5QrcodeScanner = new Html5Qrcode("qr-reader");
        html5QrcodeScanner.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: 180 },
            qrCodeMessage => {
                if(barcodeMap[qrCodeMessage]) {
                    document.getElementById('item_id').value = barcodeMap[qrCodeMessage];
                } else {
                    alert("Tento kód nie je v sklade!");
                }
                html5QrcodeScanner.stop();
                document.getElementById('qr-reader').style.display = "none";
                scannerOpen = false;
            },
            errorMessage => { }
        );
        scannerOpen = true;
    }
    </script>
</body>
</html>