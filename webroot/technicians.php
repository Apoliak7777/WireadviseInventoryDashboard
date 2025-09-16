<?php
session_start();
require_once 'db.php';

$login_error = '';
if (isset($_POST['secret_login'])) {
    $user = $_POST['login_user'] ?? '';
    $pass = $_POST['login_pass'] ?? '';
    if ($user === 'admin' && $pass === 'alex555') {
        $_SESSION['admin'] = true;
        header("Location: technicians.php");
        exit;
    } else {
        $login_error = "Incorrect username or password!";
    }
}
if (isset($_GET['logout'])) {
    unset($_SESSION['admin']);
    header("Location: technicians.php");
    exit;
}

// Add employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $name = $_POST['name'] ?? '';
    $empid = $_POST['employee_id'] ?? '';
    $contact = $_POST['contact'] ?? '';
    $db->prepare("INSERT INTO technicians (name, employee_id, phone) VALUES (?, ?, ?)")
        ->execute([$name, $empid, $contact]);
}

// Delete employee (admin only)
if (isset($_GET['delete']) && isset($_SESSION['admin'])) {
    $db->prepare("DELETE FROM technicians WHERE id=?")->execute([$_GET['delete']]);
}

$techs = $db->query("SELECT * FROM technicians ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employees</title>
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
        <h2>Employee list</h2>
        <form method="POST" style="margin-bottom:30px;">
            <div class="employee-row">
                <label>Name:</label>
                <input type="text" name="name" required>
                <label>Employee ID:</label>
                <input type="text" name="employee_id" required>
                <label>Phone / Email:</label>
                <input type="text" name="contact" placeholder="e.g. 0900 123 456 / mail@domain.com" required>
                <button type="submit" name="add" class="btn">Add employee</button>
            </div>
        </form>
        <table>
            <thead>
            <tr>
                <th>Name</th>
                <th>Employee ID</th>
                <th>Phone / Email</th>
                <?php if(isset($_SESSION['admin'])): ?><th>Delete</th><?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($techs as $tech): ?>
            <tr>
                <td data-label="Name"><?= htmlspecialchars($tech['name']) ?></td>
                <td data-label="Employee ID"><?= htmlspecialchars($tech['employee_id']) ?></td>
                <td data-label="Phone / Email"><?= htmlspecialchars($tech['phone']) ?></td>
                <?php if(isset($_SESSION['admin'])): ?>
                <td data-label="Delete">
                    <a href="?delete=<?= $tech['id'] ?>" onclick="return confirm('Delete this employee?')" style="color:#ff1744;">Delete</a>
                </td>
                <?php endif; ?>
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