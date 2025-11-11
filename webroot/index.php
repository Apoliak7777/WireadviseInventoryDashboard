<?php
require_once 'db.php';

// Delete item (available to everyone via POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_item' && isset($_POST['delete_item_id'])) {
    $toDel = $_POST['delete_item_id'];
    $stmt = $db->prepare("DELETE FROM items WHERE item_id=?");
    $stmt->execute([$toDel]);
    foreach (['jpg','jpeg','png','gif','webp'] as $ext) {
        $img_path = "uploads/" . $toDel . "." . $ext;
        if (file_exists($img_path)) @unlink($img_path);
    }
    header("Location: index.php");
    exit;
}

$items = $db->query("SELECT * FROM items ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$total = count($items);
$available = count(array_filter($items, fn($i) => strtolower($i['status']) === 'available'));
$assigned = count(array_filter($items, fn($i) => strtolower($i['status']) === 'assigned' || strtolower($i['status']) === 'in field'));
$used = count(array_filter($items, fn($i) => strtolower($i['status']) === 'used'));

// Group by project
$grouped = [];
foreach ($items as $it) {
    $key = trim($it['project'] ?? '');
    if ($key === '') $key = 'Unassigned';
    if (!isset($grouped[$key])) $grouped[$key] = [];
    $grouped[$key][] = $it;
}
uksort($grouped, function($a, $b){
    if ($a === 'Unassigned' && $b !== 'Unassigned') return 1;
    if ($b === 'Unassigned' && $a !== 'Unassigned') return -1;
    return strcasecmp($a, $b);
});
?>
<!DOCTYPE html>
<html lang="sk">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Wireadvise Inventory</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
  /* MOBILE-FIRST: restore original card layout for mobile (thead hidden, td:before labels) */
  :root{
    --bg:#14181a; --panel:#232c34; --panel-2:#1b2227; --accent:#00e676; --muted:#9aa6ab; --danger:#ff1744;
    --cell:#0f1112; --cell-border:#243039;
  }
  html,body{background:var(--bg);color:#eaeaea;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;margin:0;padding:0;}
  .wrap{max-width:420px;margin:0 auto;padding:14px;}
  header h1{text-align:center;color:var(--accent);margin:8px 0 14px;font-size:1.6rem;}
  .panel{background:var(--panel);border-radius:12px;padding:16px;box-shadow:0 8px 24px rgba(0,0,0,.6);}
  .actions{display:flex;gap:10px;margin-bottom:12px;flex-wrap:wrap;}
  .btn{flex:1 1 40%;background:var(--accent);color:#061013;border:0;border-radius:8px;padding:10px 12px;font-weight:700;text-align:center;text-decoration:none;cursor:pointer;}
  .stats{margin:10px 0;color:var(--muted);font-size:0.95rem}
  .project-title{color:var(--accent);font-weight:700;margin:14px 0 8px;}

  /* Mobile cards */
  .table-responsive{margin-top:8px;}
  table{width:100%;border-collapse:collapse;}
  thead,tbody,tr,th,td{display:block;width:100%;}
  thead{display:none;}
  tr{margin-bottom:10px;background:var(--panel);border-radius:8px;padding:8px 0;box-shadow:0 1px 6px rgba(0,0,0,.35);overflow:hidden;}
  td{display:block;padding:10px 12px;border-bottom:1px solid rgba(0,0,0,.12);font-size:0.95rem;}
  td:last-child{border-bottom:0;}
  td:before{content:attr(data-label);display:block;color:var(--accent);font-weight:700;margin-bottom:6px;font-size:0.9rem;}
  .item-picture-thumb{max-width:48px;max-height:48px;border-radius:8px;object-fit:cover;display:inline-block;vertical-align:middle;}

  /* DESKTOP: revert to full table with project separator rows */
  @media(min-width:992px){
    .wrap{max-width:1160px;margin:20px auto;padding:18px;}
    header h1{font-size:2rem;margin-bottom:18px;}
    .panel{padding:22px;}
    .actions{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:18px;}
    .btn{flex:0 0 auto;padding:10px 14px;}

    .table-responsive{overflow:auto;margin-top:10px;background:transparent;padding:0;border-radius:8px;}
    table{width:100%;table-layout:auto;border-collapse:separate;border-spacing:0;background:transparent;}
    thead{display:table-header-group;}
    tbody{display:table-row-group;}
    tr{display:table-row;margin:0;background:transparent;box-shadow:none;border-radius:0;}
    th,td{display:table-cell;padding:10px 12px;vertical-align:middle;border-bottom:1px solid #222930;background:var(--cell);color:#dfeeee;}
    th{color:var(--accent);font-weight:700;background:var(--panel-2);position:sticky;top:0;z-index:3;text-align:left;}
    td{color:#e6eded;border-right:1px solid var(--cell-border);white-space:nowrap;}
    td:last-child{border-right:none;}

    .project-row td{background:linear-gradient(180deg, rgba(0,0,0,0.12), rgba(0,0,0,0.06));color:var(--accent);font-weight:800;padding:12px 14px;border-bottom:0;}
    .col-desc{max-width:380px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .col-item{max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .item-picture-thumb{width:40px;height:40px;}
    .btn-edit{background:var(--accent);color:#061013;border:0;border-radius:8px;padding:8px 10px;margin-right:6px;cursor:pointer;}
    .btn-info{background:#2ecb85;color:#041012;border:0;border-radius:8px;padding:8px 10px;margin-right:6px;cursor:pointer;}
    .btn-delete{background:var(--danger);color:#fff;border:0;border-radius:8px;padding:8px 10px;cursor:pointer;}
  }
  </style>
</head>
<body>
  <div class="wrap">
    <header><h1>Wireadvise Inventory</h1></header>

    <section class="panel">
      <div class="actions">
        <a href="add_item.php" class="btn">Add Item</a>
        <a href="take_item.php" class="btn">Take Item</a>
        <a href="technicians.php" class="btn">Employees</a>
      </div>

      <div class="stats">
        <strong>Total:</strong> <?= $total ?> &nbsp;
        <span style="color:var(--accent)">Available: <?= $available ?></span> &nbsp;
        <span style="color:#ff8c00">Used: <?= $assigned ?></span> &nbsp;
        <span style="color:var(--danger)">Consumed: <?= $used ?></span>
      </div>

      <div class="table-responsive">
        <table>
          <thead>
            <tr>
              <th>Item</th>
              <th>Part #</th>
              <th class="col-desc">Description</th>
              <th>Vendor</th>
              <th>Received</th>
              <th>Available</th>
              <th>Status</th>
              <th>Technician</th>
              <th>Location</th>
              <th>date received</th>
              <th>Date issued</th>
              <th>Picture</th>
              <th>Barcode</th>
              <th>QR</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($grouped as $projectName => $list): ?>
              <tr class="project-row"><td colspan="15">Project: <?= htmlspecialchars($projectName) ?></td></tr>
              <?php foreach ($list as $item): ?>
                <tr class="<?= htmlspecialchars(strtolower($item['status'])) ?>">
                  <td class="col-item" data-label="Item"><?= htmlspecialchars($item['item_id']) ?></td>
                  <td data-label="Part #"><?= htmlspecialchars($item['part_number'] ?? '') ?></td>
                  <td class="col-desc" data-label="Description"><?= htmlspecialchars($item['description']) ?></td>
                  <td data-label="Vendor"><?= htmlspecialchars($item['vendor']) ?></td>
                  <td data-label="Received"><?= (int)$item['quantity_received'] ?></td>
                  <td data-label="Available"><?= (int)$item['quantity_available'] ?></td>
                  <td data-label="Status"><?= htmlspecialchars($item['status']) ?></td>
                  <td data-label="Technician"><?= htmlspecialchars($item['assigned_technician']) ?></td>
                  <td data-label="Location"><?= htmlspecialchars($item['location'] ?? '') ?></td>
                  <td data-label="date received"><?= htmlspecialchars($item['date_received']) ?></td>
                  <td data-label="Date issued"><?= htmlspecialchars($item['date_deployed']) ?></td>
                  <td data-label="Picture">
                    <?php
                      $id = $item['item_id'];
                      $img = '';
                      foreach (['jpg','jpeg','png','gif','webp'] as $ext) {
                          if (file_exists("uploads/$id.$ext")) { $img = "uploads/$id.$ext"; break; }
                      }
                      if ($img) echo "<img src=\"" . htmlspecialchars($img) . "\" class=\"item-picture-thumb\" alt=\"pic\">";
                      else echo "–";
                    ?>
                  </td>
                  <td data-label="Barcode"><?= htmlspecialchars($item['barcode'] ?? '') ?></td>
                  <td data-label="QR">
                    <button type="button" class="btn-info" onclick="showQR('<?= htmlspecialchars($item['item_id']) ?>', '<?= htmlspecialchars($item['barcode']) ?>')">QR</button>
                  </td>
                  <td class="actions-cell" data-label="Actions">
                    <a class="btn-edit" href="edit_item.php?id=<?= urlencode($item['item_id']) ?>">Edit</a>
                    <button type="button" class="btn-info" onclick="showInfo('<?= htmlspecialchars($item['item_id']) ?>')">Info</button>
                    <form method="POST" style="display:inline-block;margin:0;" onsubmit="return confirm('Delete this item?');">
                      <input type="hidden" name="action" value="delete_item">
                      <input type="hidden" name="delete_item_id" value="<?= htmlspecialchars($item['item_id']) ?>">
                      <button type="submit" class="btn-delete">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>

  <!-- QR Modal -->
  <div id="qrModal" style="display:none;position:fixed;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.6);z-index:1200;align-items:center;justify-content:center;">
    <div style="background:#181a1b;color:#eaeaea;padding:22px;border-radius:10px;min-width:300px;text-align:center;">
      <div id="qrcode"></div>
      <div id="qrText" style="margin:12px 0;color:#dfeeee"></div>
      <div style="display:flex;gap:8px;justify-content:center;">
        <button class="btn" onclick="window.print()">Print</button>
        <button class="btn" onclick="closeQR()" style="background:#232c34;color:var(--accent)">Close</button>
      </div>
    </div>
  </div>

  <!-- INFO Modal -->
  <div id="infoModal" style="display:none;position:fixed;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.6);z-index:1210;align-items:center;justify-content:center;">
    <div id="infoContent" style="background:#181a1b;color:#eaeaea;padding:18px;border-radius:10px;min-width:420px;max-width:90vw;max-height:80vh;overflow:auto;"></div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
  <script>
    function showQR(itemid, code) {
      document.getElementById('qrModal').style.display = 'flex';
      document.getElementById('qrText').innerText = 'ID: ' + itemid + (code ? ' | Barcode: ' + code : '');
      document.getElementById('qrcode').innerHTML = '';
      new QRCode(document.getElementById("qrcode"), { text: code ? code : itemid, width: 180, height: 180 });
    }
    function closeQR(){ document.getElementById('qrModal').style.display = 'none'; document.getElementById('qrcode').innerHTML = ''; }

    async function showInfo(itemId) {
      try {
        const res = await fetch('history.php?item=' + encodeURIComponent(itemId));
        if (!res.ok) throw new Error('Network ' + res.status);
        const html = await res.text();
        document.getElementById('infoContent').innerHTML = html;
        document.getElementById('infoModal').style.display = 'flex';
      } catch (e) {
        alert('Chyba pri načítaní informácií: ' + e.message);
      }
    }
    function closeInfo(){ document.getElementById('infoModal').style.display = 'none'; document.getElementById('infoContent').innerHTML = ''; }

    // close modals on background click
    document.getElementById('infoModal').addEventListener('click', function(e){ if (e.target === this) closeInfo(); });
    document.getElementById('qrModal').addEventListener('click', function(e){ if (e.target === this) closeQR(); });
  </script>
</body>
</html>