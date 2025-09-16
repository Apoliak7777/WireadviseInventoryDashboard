<?php
session_start();
require_once 'db.php';

// Login logic
$login_error = '';
if (isset($_POST['secret_login'])) {
    $user = $_POST['login_user'] ?? '';
    $pass = $_POST['login_pass'] ?? '';
    if ($user === 'admin' && $pass === 'alex555') {
        $_SESSION['admin'] = true;
        header("Location: index.php");
        exit;
    } else {
        $login_error = "Incorrect username or password!";
    }
}
if (isset($_GET['logout'])) {
    unset($_SESSION['admin']);
    header("Location: index.php");
    exit;
}

// Delete item (admin only)
if (isset($_GET['delete']) && isset($_SESSION['admin'])) {
    $db->prepare("DELETE FROM items WHERE item_id=?")->execute([$_GET['delete']]);
    $db->prepare("DELETE FROM history WHERE item_id=?")->execute([$_GET['delete']]);
    header("Location: index.php");
    exit;
}

$items = $db->query("SELECT * FROM items ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$total = count($items);
$available = count(array_filter($items, fn($i) => strtolower($i['status']) === 'available'));
$assigned = count(array_filter($items, fn($i) => strtolower($i['status']) === 'assigned' || strtolower($i['status']) === 'in field'));
$used = count(array_filter($items, fn($i) => strtolower($i['status']) === 'used'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Wireadvise Inventory Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/style.css">
    <style>
    /* --- Responsive & Mobile-first table & form styles --- */
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
        /* Table as cards on mobile */
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
    .item-picture-thumb {
        max-width: 42px;
        max-height: 42px;
        border-radius: 6px;
        border: 1px solid #232c34;
        margin: 0 auto;
        display: block;
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
    <h1>Wireadvise Inventory Dashboard</h1>
    <div class="container">
        <div class="actions">
            <a href="add_item.php" class="btn">ADD item</a>
            <a href="take_item.php" class="btn">TAKE item</a>
            <a href="technicians.php" class="btn">Employees</a>
            <a href="history.php" class="btn">Inventory History</a>
        </div>
        <h2>warehouse</h2>
        <div>
            <strong>total items:</strong> <?= $total ?> |
            <span style="color:#00e676">Available:</span> <?= $available ?> |
            <span style="color:#ff8c00">Used:</span> <?= $assigned ?> |
            <span style="color:#ff1744">Consumed:</span> <?= $used ?>
        </div>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Barcode</th>
                <th>Description</th>
                <th>Vendor</th>
                <th>Received</th>
                <th>Available</th>
                <th>Status</th>
                <th>Technician</th>
                <th>date received</th>
                <th>Date issued</th>
                <th>QR code</th>
                <th>Picture</th>
                <?php if(isset($_SESSION['admin'])): ?><th>Delete</th><?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
            <tr class="<?= strtolower($item['status']) ?>">
                <td data-label="ID"><?= htmlspecialchars($item['item_id']) ?></td>
                <td data-label="Barcode"><?= htmlspecialchars($item['barcode']) ?></td>
                <td data-label="Description"><?= htmlspecialchars($item['description']) ?></td>
                <td data-label="Vendor"><?= htmlspecialchars($item['vendor']) ?></td>
                <td data-label="Received"><?= htmlspecialchars($item['quantity_received']) ?></td>
                <td data-label="Available"><?= htmlspecialchars($item['quantity_available']) ?></td>
                <td data-label="Status"><?= htmlspecialchars($item['status']) ?></td>
                <td data-label="Technician"><?= htmlspecialchars($item['assigned_technician']) ?></td>
                <td data-label="date received"><?= htmlspecialchars($item['date_received']) ?></td>
                <td data-label="Date issued"><?= htmlspecialchars($item['date_deployed']) ?></td>
                <td data-label="QR code">
                    <button class="btn" onclick="showQR('<?= htmlspecialchars($item['item_id']) ?>', '<?= htmlspecialchars($item['barcode']) ?>')">QR code</button>
                </td>
                <td data-label="Picture">
                    <?php if (!empty($item['picture'])): ?>
                        <img src="uploads/<?= htmlspecialchars($item['picture']) ?>" class="item-picture-thumb" alt="item picture">
                    <?php endif; ?>
                </td>
                <?php if(isset($_SESSION['admin'])): ?>
                <td data-label="Delete">
                    <a href="?delete=<?= $item['item_id'] ?>" class="btn" onclick="return confirm('Delete this item?')">Delete</a>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <!-- QR Modal -->
    <div id="qrModal" style="display:none; position:fixed; left:0; top:0; width:100vw; height:100vh; background:#000a; z-index:1000; align-items:center; justify-content:center;">
        <div style="background:#181a1b; color:#eaeaea; padding:32px; border-radius:12px; box-shadow:0 2px 16px #000a; text-align:center; min-width:320px;">
            <div id="qrcode"></div>
            <div style="margin:10px 0;" id="qrText"></div>
            <button class="btn" onclick="window.print()">Print</button>
            <button class="btn" onclick="closeQR()">Close</button>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
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