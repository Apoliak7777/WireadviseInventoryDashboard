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

// Add missing columns: project, vehicle (legacy), part_number, location
try {
    $cols = $db->query("PRAGMA table_info(items)")->fetchAll(PDO::FETCH_ASSOC);
    $have = [];
    foreach ($cols as $c) { $have[$c['name']] = true; }

    if (empty($have['project'])) {
        $db->exec("ALTER TABLE items ADD COLUMN project TEXT");
    }
    if (empty($have['vehicle'])) {
        // keep vehicle as legacy column if not present (some previous flows used it)
        $db->exec("ALTER TABLE items ADD COLUMN vehicle TEXT");
    }
    if (empty($have['part_number'])) {
        $db->exec("ALTER TABLE items ADD COLUMN part_number TEXT");
    }
    if (empty($have['location'])) {
        $db->exec("ALTER TABLE items ADD COLUMN location TEXT");
    }

    // If vehicle column had values, migrate to location (map Honda/Nissan)
    // Only do migration if location empty and vehicle not empty for rows
    $migrate = $db->query("SELECT item_id, vehicle, location FROM items WHERE (location IS NULL OR TRIM(location)='') AND vehicle IS NOT NULL AND TRIM(vehicle) <> ''")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($migrate)) {
        $update = $db->prepare("UPDATE items SET location = ? WHERE item_id = ?");
        foreach ($migrate as $row) {
            $veh = trim($row['vehicle']);
            $loc = '';
            if (strcasecmp($veh, 'Honda') === 0) $loc = 'Honda Vehicle';
            elseif (strcasecmp($veh, 'Nissan') === 0) $loc = 'Nissan Vehicle';
            else $loc = $veh; // fallback keep original
            $update->execute([$loc, $row['item_id']]);
        }
    }
} catch (Exception $e) {
    // ignore migration errors to avoid breaking existing installs
}
?>