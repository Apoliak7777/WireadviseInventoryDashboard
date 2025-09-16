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
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://unpkg.com/html5-qrcode"></script>
</head>
<body>
    <div class="container">
        <h2>Výdaj položky technikovi</h2>
        <?php if ($msg): ?><p style="color:#00e676;"><?= $msg ?></p><?php endif; ?>
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
                <button type="button" class="btn" onclick="openScanner()">Skenovať</button>
            </div>
            <div id="qr-reader" style="width:300px; margin:auto; display:none"></div>
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
            <a href="index.php" class="btn">Späť na prehľad</a>
        </form>
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
            { fps: 10, qrbox: 250 },
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