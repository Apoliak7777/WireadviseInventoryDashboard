<?php
session_start();
require_once 'db.php';

$login_error = '';
if (isset($_POST['secret_login'])) {
    $user = $_POST['login_user'] ?? '';
    $pass = $_POST['login_pass'] ?? '';
    if ($user === 'admin' && $pass === 'alex555') {
        $_SESSION['admin'] = true;
        header("Location: history.php");
        exit;
    } else {
        $login_error = "Incorrect username or password!";
    }
}
if (isset($_GET['logout'])) {
    unset($_SESSION['admin']);
    header("Location: history.php");
    exit;
}

$history = $db->query("SELECT * FROM history ORDER BY date DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory History</title>
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
        <h2>Inventory History</h2>
        <table>
            <thead>
            <tr>
                <th>Item ID</th>
                <th>Action</th>
                <th>Technician</th>
                <th>Quantity</th>
                <th>Date</th>
                <th>Job / Location</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($history as $h): ?>
            <tr>
                <td data-label="Item ID"><?= htmlspecialchars($h['item_id']) ?></td>
                <td data-label="Action"><?= htmlspecialchars($h['action']) ?></td>
                <td data-label="Technician"><?= htmlspecialchars($h['technician']) ?></td>
                <td data-label="Quantity"><?= htmlspecialchars($h['quantity']) ?></td>
                <td data-label="Date"><?= htmlspecialchars($h['date']) ?></td>
                <td data-label="Job / Location"><?= htmlspecialchars($h['job_location']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <a href="index.php" class="btn">Back to warehouse</a>
    </div>
    <script>
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