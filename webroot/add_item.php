<?php
session_start();
require_once 'db.php';

$login_error = '';
if (isset($_POST['secret_login'])) {
    $user = $_POST['login_user'] ?? '';
    $pass = $_POST['login_pass'] ?? '';
    if ($user === 'admin' && $pass === 'alex555') {
        $_SESSION['admin'] = true;
        header("Location: add_item.php");
        exit;
    } else {
        $login_error = "Incorrect username or password!";
    }
}
if (isset($_GET['logout'])) {
    unset($_SESSION['admin']);
    header("Location: add_item.php");
    exit;
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['secret_login'])) {
    $barcode = $_POST['barcode'] ?? '';
    $description = $_POST['description'] ?? '';
    $vendor = $_POST['vendor'] ?? '';
    $qty = (int)($_POST['quantity_received'] ?? 1);
    $date = date('Y-m-d H:i');
    $item_id = uniqid('item-');

    // Upload picture if present
    $picture = '';
    if (isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $ext = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));
        $filename = uniqid("img_") . "." . $ext;
        move_uploaded_file($_FILES['picture']['tmp_name'], $target_dir . $filename);
        $picture = $filename;
    }

    $stmt = $db->prepare("INSERT INTO items (item_id, barcode, description, vendor, quantity_received, quantity_available, date_received, status, picture) VALUES (?, ?, ?, ?, ?, ?, ?, 'Available', ?)");
    $stmt->execute([$item_id, $barcode, $description, $vendor, $qty, $qty, $date, $picture]);
    $msg = "Item has been added.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ADD item</title>
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
        <h2>ADD item to warehouse</h2>
        <?php if ($msg): ?><p style="color:#00e676;"><?= $msg ?></p><?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-row">
                <label>Barcode / QR:</label>
                <input type="text" id="barcode" name="barcode" required>
                <button type="button" class="btn" onclick="openScanner()">Scan</button>
            </div>
            <div id="qr-reader" style="width:300px; margin:auto; display:none"></div>
            <div class="form-row">
                <label>Description:</label>
                <input type="text" name="description" required>
            </div>
            <div class="form-row">
                <label>Vendor:</label>
                <input type="text" name="vendor" required>
            </div>
            <div class="form-row">
                <label>Quantity received:</label>
                <input type="number" name="quantity_received" min="1" value="1" required>
            </div>
            <div class="form-row">
                <label>Add picture:</label>
                <input type="file" name="picture" accept="image/*" capture="environment">
            </div>
            <button type="submit" class="btn">ADD item</button>
            <a href="index.php" class="btn">Back to warehouse</a>
        </form>
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
    function openScanner() {
        if(scannerOpen) return;
        document.getElementById('qr-reader').style.display = "block";
        html5QrcodeScanner = new Html5Qrcode("qr-reader");
        html5QrcodeScanner.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: 250 },
            qrCodeMessage => {
                document.getElementById('barcode').value = qrCodeMessage;
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