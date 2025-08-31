<?php
// hostel/SwapRooms.php - Swap rooms between two active allocations (Admin/Warden)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../head.php';
require_once __DIR__ . '/../menu.php';

if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['ADM','WAR'])) {
  echo '<div class="container mt-4"><div class="alert alert-danger">Forbidden</div></div>';
  require_once __DIR__ . '/../footer.php';
  exit;
}

$base = defined('APP_BASE') ? APP_BASE : '';
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// Load list of active allocations with student and room info
$sql = "SELECT a.id, a.student_id, s.student_fullname, r.id AS room_id, r.room_no, b.name AS block_name, h.name AS hostel_name
          FROM hostel_allocations a
          INNER JOIN student s ON s.student_id=a.student_id
          INNER JOIN hostel_rooms r ON r.id=a.room_id
          INNER JOIN hostel_blocks b ON b.id=r.block_id
          INNER JOIN hostels h ON h.id=b.hostel_id
         WHERE a.status='active'
         ORDER BY h.name, b.name, r.room_no, s.student_fullname";
$rows = [];
$q = mysqli_query($con, $sql);
if ($q) { while ($r = mysqli_fetch_assoc($q)) { $rows[] = $r; } }
?>
<div class="container mt-4">
  <h3>Swap Hostel Rooms</h3>
  <div class="alert alert-info">Select two active allocations to swap their rooms. Swaps run in a transaction.</div>

  <form method="POST" action="<?php echo $base; ?>/controller/HostelSwapRooms.php">
    <div class="form-row">
      <div class="form-group col-md-6">
        <label>Allocation A</label>
        <select name="alloc_a" class="form-control" required>
          <option value="">Select allocation</option>
          <?php foreach ($rows as $r): ?>
            <option value="<?php echo (int)$r['id']; ?>">
              <?php echo h($r['hostel_name'].' / '.$r['block_name'].' / Room '.$r['room_no'].' — '.$r['student_fullname'].' ('.$r['student_id'].')'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group col-md-6">
        <label>Allocation B</label>
        <select name="alloc_b" class="form-control" required>
          <option value="">Select allocation</option>
          <?php foreach ($rows as $r): ?>
            <option value="<?php echo (int)$r['id']; ?>">
              <?php echo h($r['hostel_name'].' / '.$r['block_name'].' / Room '.$r['room_no'].' — '.$r['student_fullname'].' ('.$r['student_id'].')'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group col-md-3">
        <label>Effective Date</label>
        <input type="date" name="effective_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">Swap Rooms</button>
    <a href="<?php echo $base; ?>/hostel/Hostel.php" class="btn btn-secondary ml-2">Back</a>
  </form>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
