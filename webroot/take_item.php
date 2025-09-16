<?php
session_start();
require_once 'db.php';

$login_error = '';
if (isset($_POST['secret_login'])) {
    $user = $_POST['login_user'] ?? '';
    $pass = $_POST['login_pass'] ?? '';
    if ($user === 'admin' && $pass === 'alex555') {
        $_SESSION['admin'] = true;
        header("Location: take_item.php");
        exit;
    } else {
        $login_error = "Incorrect username or password!";
    }
}
if (isset($_GET['logout'])) {
    unset($_SESSION['admin']);
    header("Location: take_item.php");
    exit;
}

$techs = $db->query("SELECT name FROM technicians")->fetchAll(PDO::FETCH_COLUMN);
$items = $db->query("SELECT * FROM items WHERE quantity_available > 0")->fetchAll(PDO::FETCH_ASSOC);

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['secret_login'])) {
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
        $msg = "Item has been assigned to technician.";
    } else {
        $msg = "Not enough stock!";
    }
}

$barcodeMap = [];
foreach ($items as $i) {
    $barcodeMap[$i['barcode']] = $i['item_id'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TAKE item</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/style.css">
    <style>
    @media (max-width: 700px) {
        body, .container {
            padding: 6px !important;
        }
        h1, h2 {
            font-size: 1.1em;
            margin-top: 16px;
        }
        .actions {
            text-align: left !important;
            margin-bottom: 15px !important;
        }
        .btn, button.btn {
            width: 100%;
            font-size: 1.08em;
            margin-bottom: 10px;
            padding: 18px 0;
        }
        .form-row, .employee-row {
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 0 !important;
        }
        .employee-row input, .employee-row select,
        .form-row input, .form-row select {
            width: 100% !important;
            margin-bottom: 12px !important;
            font-size: 1.09em;
            padding: 13px 10px !important;
        }
        .employee-row label, .form-row label {
            margin-bottom: 5px !important;
            margin-right: 0 !important;
            font-size: 1em !important;
            text-align: left !important;
        }
        table, thead, tbody, th, td, tr {
            display: block;
            width: 100%;
        }
        thead {
            display: none;
        }
        tr {
            margin-bottom: 18px;
            border-radius: 8px;
            background: #232c34;
            box-shadow: 0 2px 12px #0004;
            padding: 10px 0;
        }
        td {
            padding: 7px 12px !important;
            text-align: left;
            border: none;
            position: relative;
        }
        td:before {
            content: attr(data-label);
            font-weight: bold;
            color: #00e676;
            display: block;
            margin-bottom: 2px;
            font-size: 0.98em;
        }
        .item-picture-thumb {
            max-width: 100px;
            max-height: 100px;
        }
    }
    .secret-login-btn {
        position: fixed;
        top: 18px;
        right: 18px;
        background: transparent;
        border: none;
        font-size: 1.4em;
        color: #00e676;
        cursor: pointer;
        z-index: 50;
    }
    .secret-login-form {
        position: fixed;
        top: 55px;
        right: 18px;
        background: #232c34;
        color: #eaeaea;
        padding: 18px 20px 12px 20px;
        border-radius: 9px;
        box-shadow: 0 2px 24px #000a;
        display: none;
        z-index: 99;
        min-width: 210px;
    }
    .secret-login-form input[type="password"], .secret-login-form input[type="text"] {
        background: #181a1b;
        border: 1px solid #00e676;
        color: #eaeaea;
        border-radius: 5px;
        padding: 5px 8px;
        margin-bottom: 8px;
        width: 100%;
    }
    .secret-login-form button {
        background: #00e676;
        color: #232c34;
        border: none;
        border-radius: 5px;
        padding: 5px 14px;
        font-weight: 600;
        margin-top: 4px;
        cursor: pointer;
    }
    .login-error {color:#ff1744; font-size:0.97em;}
    .logout-btn {
        position: fixed;
        top: 18px;
        right: 18px;
        background: transparent;
        border: none;
        font-size: 1.4em;
        color: #ff1744;
        cursor: pointer;
        z-index: 51;
    }
    </style>
</head>
<body>
    <?php if (!isset($_SESSION['admin'])): ?>
        <button class="secret-login-btn" onclick="showLoginForm()" title="Sign in">🔑</button>
        <form method="POST" id="secretLoginForm" class="secret-login-form" autocomplete="off" onsubmit="return true;">
            <div>
                <label style="font-size:0.97em;">Username:</label>
                <input type="text" name="login_user" autocomplete="off" autofocus>
            </div>
            <div>
                <label style="font-size:0.97em;">Password:</label>
                <input type="password" name="login_pass" autocomplete="off">
            </div>
            <?php if($login_error): ?><div class="login-error"><?= $login_error ?></div><?php endif; ?>
            <button type="submit" name="secret_login">Sign in</button>
        </form>
    <?php else: ?>
        <form method="GET" style="display:inline;">
            <button class="logout-btn" type="submit" name="logout" title="Sign out">⏻</button>
        </form>
    <?php endif; ?>
    <div class="container">
        <h2>TAKE item for technician</h2>
        <?php if ($msg): ?><p style="color:#00e676;"><?= $msg ?></p><?php endif; ?>
        <form method="POST" id="assignForm">
            <div class="form-row">
                <label>Item:</label>
                <select name="item_id" id="item_id" required>
                    <?php foreach ($items as $i): ?>
                        <option value="<?= $i['item_id'] ?>" data-barcode="<?= htmlspecialchars($i['barcode']) ?>">
                            <?= $i['description'] ?> (<?= $i['quantity_available'] ?> pcs)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn" onclick="openScanner()">Scan</button>
            </div>
            <div id="qr-reader" style="width:300px; margin:auto; display:none"></div>
            <div class="form-row">
                <label>Technician:</label>
                <select name="technician" required>
                    <?php foreach ($techs as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label>Quantity:</label>
                <input type="number" name="quantity" min="1" value="1" required>
            </div>
            <div class="form-row">
                <label>Job / Location:</label>
                <input type="text" name="job_location">
            </div>
            <button type="submit" class="btn">Assign to technician</button>
            <a href="index.php" class="btn">Back to warehouse</a>
        </form>
        <h2 style="margin-top:40px">Available items</h2>
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
            <?php foreach ($items as $item): ?>
            <tr>
                <td data-label="ID"><?= htmlspecialchars($item['item_id']) ?></td>
                <td data-label="Barcode"><?= htmlspecialchars($item['barcode']) ?></td>
                <td data-label="Description"><?= htmlspecialchars($item['description']) ?></td>
                <td data-label="Vendor"><?= htmlspecialchars($item['vendor']) ?></td>
                <td data-label="Available"><?= htmlspecialchars($item['quantity_available']) ?></td>
                <td data-label="Picture">
                    <?php if (!empty($item['picture'])): ?>
                        <img src="uploads/<?= htmlspecialchars($item['picture']) ?>" class="item-picture-thumb" alt="item picture">
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div id="qrModal" style="display:none; position:fixed; left:0; top:0; width:100vw; height:100vh; background:#000a; z-index:1000; align-items:center; justify-content:center;">
        <div style="background:#181a1b; color:#eaeaea; padding:32px; border-radius:12px; box-shadow:0 2px 16px #000a; text-align:center; min-width:320px;">
            <div id="qrcode"></div>
            <div style="margin:10px 0;" id="qrText"></div>
            <button class="btn" onclick="window.print()">Print</button>
            <button class="btn" onclick="closeQR()">Close</button>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode"></script>
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
                    alert("This code is not in warehouse!");
                }
                html5QrcodeScanner.stop();
                document.getElementById('qr-reader').style.display = "none";
                scannerOpen = false;
            },
            errorMessage => { }
        );
        scannerOpen = true;
    }
    function showQR(itemid, code) {
        document.getElementById('qrModal').style.display = 'flex';
        document.getElementById('qrText').innerText = 'ID: ' + itemid + (code ? ' | Barcode: ' + code : '');
        document.getElementById('qrcode').innerHTML = '';
        new QRCode(document.getElementById("qrcode"), {
            text: code ? code : itemid,
            width: 180,
            height: 180
        });
    }
    function closeQR() {
        document.getElementById('qrModal').style.display = 'none';
    }
    function showLoginForm() {
        var f = document.getElementById('secretLoginForm');
        f.style.display = (f.style.display === 'block') ? 'none' : 'block';
        if(f.style.display === 'block') f.querySelector('input[type=text]').focus();
    }
    document.addEventListener('click', function(e){
        var form = document.getElementById('secretLoginForm');
        var btn = document.querySelector('.secret-login-btn');
        if(form && form.style.display === 'block' && !form.contains(e.target) && e.target !== btn) {
            form.style.display = 'none';
        }
    });
    </script>
</body>
</html>