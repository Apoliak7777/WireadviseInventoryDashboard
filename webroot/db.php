<?php
$db = new PDO('sqlite:inventory.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec("CREATE TABLE IF NOT EXISTS items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id TEXT UNIQUE,
    barcode TEXT,
    description TEXT,
    vendor TEXT,
    quantity_received INTEGER,
    quantity_available INTEGER,
    date_received TEXT,
    storage_location TEXT,
    status TEXT DEFAULT 'Available',
    assigned_technician TEXT,
    date_deployed TEXT,
    job_location TEXT
)");
$db->exec("CREATE TABLE IF NOT EXISTS technicians (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    employee_id TEXT,
    phone TEXT
)");
$db->exec("CREATE TABLE IF NOT EXISTS history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id TEXT,
    action TEXT,
    technician TEXT,
    quantity INTEGER,
    date TEXT,
    job_location TEXT
)");
?>