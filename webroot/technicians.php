<?php
require_once 'db.php';

// Add employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $name = $_POST['name'] ?? '';
    $empid = $_POST['employee_id'] ?? '';
    $contact = $_POST['contact'] ?? '';
    $db->prepare("INSERT INTO technicians (name, employee_id, phone) VALUES (?, ?, ?)")
        ->execute([$name, $empid, $contact]);
}

// Delete employee (now available to everyone)
if (isset($_GET['delete'])) {
    $db->prepare("DELETE FROM technicians WHERE id=?")->execute([$_GET['delete']]);
    header("Location: technicians.php");
    exit;
}

$techs = $db->query("SELECT * FROM technicians ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employees</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="stylesheet" href="assets/style.css">
    <style>
    html, body {background:#14181a; color:#eaeaea; font-family:sans-serif; margin:0; padding:0;}
    .container {max-width:420px; margin:0 auto; background:#232c34; border-radius:16px; box-shadow:0 2px 18px #000a; padding:19px 8px; margin-top:12px;}
    @media(max-width:700px){html,body{font-size:15px;}.container{max-width:100vw;margin:0 auto;padding:7vw 2vw 5vw 2vw;border-radius:13px;}}
    h2 {text-align:center;font-size:1.25em;color:#00e676;margin-bottom:18px;}
    .employee-row {display:flex;flex-direction:column;margin-bottom:14px;}
    label {margin-bottom:5px;color:#00e676;font-size:0.98em;}
    input[type="text"] {padding:10px 8px;border-radius:7px;border:none;background:#181a1b;color:#eaeaea;font-size:1em;margin-bottom:2px;}
    .btn {background:#00e676;color:#181a1b;border:none;border-radius:7px;padding:12px 0;font-size:1em;font-weight:600;margin-bottom:11px;margin-top:5px;cursor:pointer;}
    a.btn {display:block;text-align:center;text-decoration:none;}
    .tech-table {margin-top:13px;}
    table {width:100%;border-collapse:collapse;}
    th, td {padding:7px 6px;font-size:0.98em;}
    th {color:#00e676;}
    tr {background:#181a1b;}
    @media(max-width:700px){table,thead,tbody,th,td,tr{display:block;width:100%;}thead{display:none;}tr{margin-bottom:9px;border-radius:7px;background:#232c34;box-shadow:0 1px 6px #0002;padding:5px 0;}td{padding:4px 7px !important;text-align:left;border:none;position:relative;font-size:0.97em !important;}td:before{content:attr(data-label);font-weight:bold;color:#00e676;display:block;margin-bottom:1.5px;font-size:0.93em;}}
    </style>
</head>
<body>
    <div class="container">
        <h2>Employee list</h2>
        <form method="POST" style="margin-bottom:24px;">
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
        <div class="tech-table">
        <table>
            <thead>
            <tr>
                <th>Name</th>
                <th>Employee ID</th>
                <th>Phone / Email</th>
                <th>Delete</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($techs as $tech): ?>
            <tr>
                <td data-label="Name"><?= htmlspecialchars($tech['name']) ?></td>
                <td data-label="Employee ID"><?= htmlspecialchars($tech['employee_id']) ?></td>
                <td data-label="Phone / Email"><?= htmlspecialchars($tech['phone']) ?></td>
                <td data-label="Delete">
                    <a href="?delete=<?= $tech['id'] ?>" onclick="return confirm('Delete this employee?')" style="color:#ff1744;">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <a href="index.php" class="btn" style="background:#232c34;color:#00e676;">Back to warehouse</a>
    </div>
</body>
</html>