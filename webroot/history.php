<?php
require_once 'db.php';

$item = $_GET['item'] ?? '';
if (!$item) {
    echo "<div style='color:#ff1744;padding:8px;'>Missing item id.</div>";
    exit;
}

// fetch item (for header)
$stmt = $db->prepare("SELECT item_id, barcode, part_number, description, vendor, quantity_received, quantity_available, date_received, date_deployed, assigned_technician, location FROM items WHERE item_id = ?");
$stmt->execute([$item]);
$it = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$it) {
    echo "<div style='color:#ff1744;padding:8px;'>Item not found.</div>";
    exit;
}

// fetch history (most recent first)
$hist = $db->prepare("SELECT action, technician, quantity, date, job_location FROM history WHERE item_id = ? ORDER BY date DESC");
$hist->execute([$item]);
$rows = $hist->fetchAll(PDO::FETCH_ASSOC);

// compute totals (e.g. total assigned)
$total_assigned = 0;
foreach ($rows as $r) {
    if (isset($r['action']) && strtolower(trim($r['action'])) === 'assigned') {
        $total_assigned += (int)$r['quantity'];
    }
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$count_rows = count($rows);
?>
<style>
/* Compact, mobile-first history modal styles (optimized for very small vertical footprint) */
.history-wrap {
  font-family: system-ui,-apple-system,Segoe UI,Roboto,Arial;
  color:#eaeaea;
  font-size:12px;
  line-height:1.1;
  max-width:94vw;
  margin:0 auto;
  padding:6px 8px;
  box-sizing:border-box;
}
/* header */
.history-header {
  color:#00e676;
  font-weight:800;
  margin:6px 0 6px;
  font-size:15px;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}

/* metadata row: compact grid */
.history-meta {
  display:flex;
  flex-wrap:wrap;
  gap:8px 12px;
  align-items:center;
  background:#0d0f10;
  padding:6px 8px;
  border-radius:8px;
  margin-bottom:8px;
}
.hm-col { min-width:70px; }
.hm-label { color:#9aa6ab; font-weight:700; font-size:10px; display:block; margin-bottom:4px; }
.hm-value { color:#dfeeee; font-weight:700; font-size:12px; }

/* compact list rows for mobile - single-line main row */
.history-list { display:block; }
.history-item {
  background:#0f1213;
  border-radius:6px;
  padding:6px 6px;
  margin-bottom:6px;
  border:1px solid rgba(255,255,255,0.02);
  box-sizing:border-box;
}
.history-main {
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:8px;
  min-height:28px;
}
.hm-left {
  display:flex;
  gap:8px;
  align-items:center;
  min-width:0;
  overflow:hidden;
}
.hm-action {
  color:#9aa6ab;
  font-weight:700;
  font-size:11px;
  max-width:90px;
  overflow:hidden;
  text-overflow:ellipsis;
  white-space:nowrap;
}
.hm-qty {
  color:#dfeeee;
  font-weight:700;
  font-size:12px;
  min-width:30px;
  text-align:center;
}
.hm-tech {
  color:#dfeeee;
  font-weight:700;
  font-size:12px;
  max-width:120px;
  overflow:hidden;
  text-overflow:ellipsis;
  white-space:nowrap;
}

/* tiny expand chevron */
.expand-btn {
  background:transparent;
  border:0;
  color:#9aa6ab;
  font-weight:800;
  padding:4px 6px;
  border-radius:4px;
  cursor:pointer;
  font-size:13px;
  line-height:1;
}

/* hidden compact details - very small font & left aligned */
.history-details {
  display:none;
  margin-top:6px;
  color:#cfead4;
  font-size:11px;
  line-height:1.15;
  padding:6px 6px 6px 6px;
  background:rgba(255,255,255,0.01);
  border-radius:6px;
}

/* show-more button */
.show-more {
  display:block;
  width:100%;
  text-align:center;
  margin:6px 0;
  padding:6px;
  background:#232c34;
  color:#00e676;
  border-radius:8px;
  cursor:pointer;
  border:0;
  font-weight:700;
  font-size:12px;
}

/* Desktop: show table, hide mobile row list */
@media(min-width:720px) {
  .history-wrap { max-width:720px; padding:8px; font-size:13px; }
  .history-list { display:none; }
  .history-meta { padding:8px 10px; gap:10px; }
  .history-table { width:100%; border-collapse:collapse; margin-top:6px; }
  .history-table thead th { text-align:left; padding:8px 10px; color:#cfead4; font-weight:700; background:#0f1213; border-bottom:1px solid #222; font-size:0.95rem; }
  .history-table tbody td { padding:8px 10px; border-bottom:1px solid rgba(255,255,255,0.03); color:#e6efe9; background:#0f1112; font-size:0.95rem; }
  .show-more { display:none; }
}

/* ensure modal content scrolls nicely on mobile */
#infoContent {
  -webkit-overflow-scrolling: touch;
  max-height:82vh;
  overflow:auto;
  padding-right:6px;
}
</style>

<div class="history-wrap" role="dialog" aria-label="Item history">
  <div class="history-header">Info — <?= h($it['item_id']) ?></div>

  <div class="history-meta" role="group" aria-label="Item metadata">
    <div class="hm-col"><span class="hm-label">Part</span><span class="hm-value"><?= h($it['part_number'] ?? '') ?></span></div>
    <div class="hm-col"><span class="hm-label">BC</span><span class="hm-value"><?= h($it['barcode'] ?? '') ?></span></div>
    <div class="hm-col"><span class="hm-label">Avail</span><span class="hm-value"><?= (int)$it['quantity_available'] ?></span></div>
    <div class="hm-col"><span class="hm-label">Recv</span><span class="hm-value"><?= (int)$it['quantity_received'] ?></span></div>
    <div class="hm-col"><span class="hm-label">Assigned</span><span class="hm-value"><?= (int)$total_assigned ?></span></div>
    <div class="hm-col"><span class="hm-label">Loc</span><span class="hm-value"><?= h($it['location'] ?? '') ?></span></div>
  </div>

  <h4 style="color:#00e676;margin:6px 0 4px;font-size:12px;">History</h4>

  <?php if ($count_rows === 0): ?>
    <div class="history-empty">No history records for this item.</div>
  <?php else: ?>

    <div id="historyList" class="history-list" aria-live="polite">
      <?php foreach ($rows as $idx => $r): ?>
        <div class="history-item" data-idx="<?= $idx ?>">
          <div class="history-main">
            <div class="hm-left">
              <div class="hm-action"><?= h($r['action']) ?></div>
              <div class="hm-qty"><?= (int)$r['quantity'] ?></div>
              <div class="hm-tech"><?= h($r['technician']) ?></div>
            </div>
            <div>
              <button class="expand-btn" type="button" aria-expanded="false" onclick="toggleDetails(this)">›</button>
            </div>
          </div>

          <div class="history-details" aria-hidden="true">
            <div><strong style="color:#9aa6ab;font-size:11px">Job:</strong> <?= h($r['job_location']) ?></div>
            <div style="margin-top:4px;"><strong style="color:#9aa6ab;font-size:11px">When:</strong> <?= h($r['date']) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Desktop table -->
    <table class="history-table" role="table" aria-label="History table">
      <thead>
        <tr>
          <th>Action</th>
          <th>Technician</th>
          <th style="text-align:center">Quantity</th>
          <th>Job / Location</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= h($r['action']) ?></td>
            <td><?= h($r['technician']) ?></td>
            <td style="text-align:center"><?= (int)$r['quantity'] ?></td>
            <td><?= h($r['job_location']) ?></td>
            <td><?= h($r['date']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if ($count_rows > 3): ?>
      <button id="showMoreBtn" class="show-more" type="button">Show more</button>
    <?php endif; ?>

  <?php endif; ?>

  <div style="text-align:right;margin-top:8px;">
    <button onclick="closeInfo()" style="background:#232c34;color:#00e676;border:0;padding:6px 8px;border-radius:6px;cursor:pointer;font-weight:700;font-size:12px">Close</button>
  </div>
</div>

<script>
(function(){
  try {
    var maxVisible = 3;
    var list = document.getElementById('historyList');
    var items = list ? list.querySelectorAll('.history-item') : [];
    if (items.length > maxVisible) {
      for (var i = 0; i < items.length; i++) {
        var idx = parseInt(items[i].getAttribute('data-idx')||0,10);
        if (idx >= maxVisible) items[i].style.display = 'none';
      }
    }
    var btn = document.getElementById('showMoreBtn');
    if (btn) {
      var expanded = false;
      btn.addEventListener('click', function(){
        expanded = !expanded;
        for (var i = 0; i < items.length; i++) {
          var idx = parseInt(items[i].getAttribute('data-idx')||0,10);
          if (idx >= maxVisible) items[i].style.display = expanded ? 'block' : 'none';
        }
        btn.textContent = expanded ? 'Show less' : 'Show more';
        if (!expanded) btn.scrollIntoView({behavior:'smooth', block:'center'});
      });
    }

    window.toggleDetails = function(btn) {
      var item = btn.closest('.history-item');
      if (!item) return;
      var details = item.querySelector('.history-details');
      var expanded = btn.getAttribute('aria-expanded') === 'true';
      if (expanded) {
        details.style.display = 'none';
        btn.setAttribute('aria-expanded','false');
        btn.textContent = '›';
      } else {
        details.style.display = 'block';
        btn.setAttribute('aria-expanded','true');
        btn.textContent = '‹';
      }
    };
  } catch (e) {
    console.error(e);
  }
})();
</script>