<?php
// Administration/ConductReport.php
// Department-wise Code of Conduct acceptance report with drill-down

require_once __DIR__ . '/../config.php';
$con = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (mysqli_connect_errno()) {
  die('Failed to connect to MySQL: ' . mysqli_connect_error());
}

// Session (no admin restriction required)
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$title = 'Code of Conduct Acceptance Report | SLGTI';
include_once __DIR__ . '/../head.php';
include_once __DIR__ . '/../menu.php';

// Ensure column exists (safe if already present)
@mysqli_query($con, "ALTER TABLE `student` ADD COLUMN `student_conduct_accepted_at` DATETIME NULL");

$selectedDeptId = isset($_GET['dept']) && $_GET['dept'] !== '' ? $_GET['dept'] : null;
$selectedCourseId = isset($_GET['course']) && $_GET['course'] !== '' ? $_GET['course'] : null;

// Helper: latest enrollment per student view (inline via subquery)
function esc($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

?>
<div class="container-fluid mt-3">
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <h3 class="mb-0">Student Code of Conduct Acceptance</h3>
      <small class="text-muted">Department-wise summary and drill-down</small>
    </div>
  </div>

  <?php if (!$selectedDeptId): ?>
    <?php
    $sql = "
      SELECT
        d.department_id,
        d.department_name,
        COUNT(DISTINCT s.student_id) AS total_students,
        SUM(CASE WHEN s.student_conduct_accepted_at IS NOT NULL THEN 1 ELSE 0 END) AS accepted_count,
        SUM(CASE WHEN s.student_conduct_accepted_at IS NULL THEN 1 ELSE 0 END) AS pending_count
      FROM student s
      JOIN (
        SELECT se.student_id, MAX(se.student_enroll_date) AS max_enroll_date
        FROM student_enroll se
        GROUP BY se.student_id
      ) le ON le.student_id = s.student_id
      JOIN student_enroll e
        ON e.student_id = le.student_id
       AND e.student_enroll_date = le.max_enroll_date
      JOIN course c
        ON c.course_id = e.course_id
      JOIN department d
        ON d.department_id = c.department_id
      WHERE e.student_enroll_status = 'Following'
      GROUP BY d.department_id, d.department_name
      ORDER BY d.department_name
    ";
    $res = mysqli_query($con, $sql);
    ?>

    <div class="card shadow-sm">
      <div class="card-body table-responsive">
        <table class="table table-sm table-hover align-middle">
          <thead class="thead-light">
            <tr>
              <th>Department</th>
              <th class="text-right">Total</th>
              <th class="text-right">Accepted</th>
              <th class="text-right">Pending</th>
              <th class="text-right">Accepted %</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php if ($res && mysqli_num_rows($res) > 0): ?>
            <?php $grandTotal = 0; $grandAccepted = 0; $grandPending = 0; ?>
            <?php while ($row = mysqli_fetch_assoc($res)): ?>
              <?php
                $total = (int)$row['total_students'];
                $accepted = (int)$row['accepted_count'];
                $pending = (int)$row['pending_count'];
                $pct = $total > 0 ? round(100.0 * $accepted / $total, 2) : 0;
                $grandTotal += $total; $grandAccepted += $accepted; $grandPending += $pending;
              ?>
              <tr>
                <td><?php echo esc($row['department_name']); ?></td>
                <td class="text-right"><?php echo number_format($total); ?></td>
                <td class="text-right text-success"><?php echo number_format($accepted); ?></td>
                <td class="text-right text-danger"><?php echo number_format($pending); ?></td>
                <td class="text-right"><?php echo number_format($pct, 2); ?>%</td>
                <td class="text-right">
                  <a class="btn btn-sm btn-outline-primary" href="<?php echo (defined('APP_BASE')?APP_BASE:''); ?>/administration/ConductReport.php?dept=<?php echo urlencode($row['department_id']); ?>">View details</a>
                </td>
              </tr>
            <?php endwhile; ?>
            <?php $gpct = $grandTotal > 0 ? round(100.0 * $grandAccepted / $grandTotal, 2) : 0; ?>
            <tr class="font-weight-bold">
              <td class="text-right">Total</td>
              <td class="text-right"><?php echo number_format($grandTotal); ?></td>
              <td class="text-right text-success"><?php echo number_format($grandAccepted); ?></td>
              <td class="text-right text-danger"><?php echo number_format($grandPending); ?></td>
              <td class="text-right"><?php echo number_format($gpct, 2); ?>%</td>
              <td></td>
            </tr>
          <?php else: ?>
            <tr><td colspan="6" class="text-center text-muted">No data</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php else: ?>
    <?php
      // Fetch department name
      $dname = null;
      if ($ds = mysqli_prepare($con, 'SELECT department_name FROM department WHERE department_id=?')) {
        mysqli_stmt_bind_param($ds, 's', $selectedDeptId);
        mysqli_stmt_execute($ds);
        $dr = mysqli_stmt_get_result($ds);
        if ($dr && ($drow = mysqli_fetch_assoc($dr))) { $dname = $drow['department_name']; }
        mysqli_stmt_close($ds);
      }

      // If a course filter is selected, fetch its name
      $cname = null;
      if ($selectedCourseId) {
        if ($cs = mysqli_prepare($con, 'SELECT course_name FROM course WHERE course_id=?')) {
          mysqli_stmt_bind_param($cs, 's', $selectedCourseId);
          mysqli_stmt_execute($cs);
          $cr = mysqli_stmt_get_result($cs);
          if ($cr && ($crow = mysqli_fetch_assoc($cr))) { $cname = $crow['course_name']; }
          mysqli_stmt_close($cs);
        }
      }

      // Course-wise summary within the department
      $sqlCourseSummary = "
        SELECT
          c.course_id,
          c.course_name,
          COUNT(DISTINCT s.student_id) AS total_students,
          SUM(CASE WHEN s.student_conduct_accepted_at IS NOT NULL THEN 1 ELSE 0 END) AS accepted_count,
          SUM(CASE WHEN s.student_conduct_accepted_at IS NULL THEN 1 ELSE 0 END) AS pending_count
        FROM student s
        JOIN (
          SELECT se.student_id, MAX(se.student_enroll_date) AS max_enroll_date
          FROM student_enroll se
          GROUP BY se.student_id
        ) le ON le.student_id = s.student_id
        JOIN student_enroll e
          ON e.student_id = le.student_id
         AND e.student_enroll_date = le.max_enroll_date
        JOIN course c
          ON c.course_id = e.course_id
        JOIN department d
          ON d.department_id = c.department_id
        WHERE e.student_enroll_status = 'Following'
          AND d.department_id = ?
        GROUP BY c.course_id, c.course_name
        ORDER BY c.course_name
      ";
      $stmtCourse = mysqli_prepare($con, $sqlCourseSummary);
      mysqli_stmt_bind_param($stmtCourse, 's', $selectedDeptId);
      mysqli_stmt_execute($stmtCourse);
      $coursesRs = mysqli_stmt_get_result($stmtCourse);

      // Build detail query, optionally filter by course
      if ($selectedCourseId) {
        $sqlDetail = "
          SELECT
            s.student_id,
            s.student_fullname,
            s.student_ininame,
            s.student_email,
            s.student_phone,
            s.student_conduct_accepted_at,
            c.course_name,
            e.academic_year,
            e.student_enroll_status
          FROM student s
          JOIN (
            SELECT se.student_id, MAX(se.student_enroll_date) AS max_enroll_date
            FROM student_enroll se
            GROUP BY se.student_id
          ) le ON le.student_id = s.student_id
          JOIN student_enroll e
            ON e.student_id = le.student_id
           AND e.student_enroll_date = le.max_enroll_date
          JOIN course c
            ON c.course_id = e.course_id
          JOIN department d
            ON d.department_id = c.department_id
          WHERE e.student_enroll_status = 'Following'
            AND d.department_id = ?
            AND c.course_id = ?
          ORDER BY (s.student_conduct_accepted_at IS NULL) ASC, s.student_fullname ASC
        ";
        $stmt = mysqli_prepare($con, $sqlDetail);
        mysqli_stmt_bind_param($stmt, 'ss', $selectedDeptId, $selectedCourseId);
      } else {
        $sqlDetail = "
          SELECT
            s.student_id,
            s.student_fullname,
            s.student_ininame,
            s.student_email,
            s.student_phone,
            s.student_conduct_accepted_at,
            c.course_name,
            e.academic_year,
            e.student_enroll_status
          FROM student s
          JOIN (
            SELECT se.student_id, MAX(se.student_enroll_date) AS max_enroll_date
            FROM student_enroll se
            GROUP BY se.student_id
          ) le ON le.student_id = s.student_id
          JOIN student_enroll e
            ON e.student_id = le.student_id
           AND e.student_enroll_date = le.max_enroll_date
          JOIN course c
            ON c.course_id = e.course_id
          JOIN department d
            ON d.department_id = c.department_id
          WHERE e.student_enroll_status = 'Following'
            AND d.department_id = ?
          ORDER BY (s.student_conduct_accepted_at IS NULL) ASC, s.student_fullname ASC
        ";
        $stmt = mysqli_prepare($con, $sqlDetail);
        mysqli_stmt_bind_param($stmt, 's', $selectedDeptId);
      }
      mysqli_stmt_execute($stmt);
      $rs = mysqli_stmt_get_result($stmt);

      // Summary counts for current filter (dept and optional course)
      if ($selectedCourseId) {
        $sqlCount = "
          SELECT
            COUNT(DISTINCT s.student_id) AS total_students,
            SUM(CASE WHEN s.student_conduct_accepted_at IS NOT NULL THEN 1 ELSE 0 END) AS accepted_count,
            SUM(CASE WHEN s.student_conduct_accepted_at IS NULL THEN 1 ELSE 0 END) AS pending_count
          FROM student s
          JOIN (
            SELECT se.student_id, MAX(se.student_enroll_date) AS max_enroll_date
            FROM student_enroll se
            GROUP BY se.student_id
          ) le ON le.student_id = s.student_id
          JOIN student_enroll e
            ON e.student_id = le.student_id
           AND e.student_enroll_date = le.max_enroll_date
          JOIN course c
            ON c.course_id = e.course_id
          JOIN department d
            ON d.department_id = c.department_id
          WHERE e.student_enroll_status = 'Following'
            AND d.department_id = ?
            AND c.course_id = ?
        ";
        $stmtCnt = mysqli_prepare($con, $sqlCount);
        mysqli_stmt_bind_param($stmtCnt, 'ss', $selectedDeptId, $selectedCourseId);
      } else {
        $sqlCount = "
          SELECT
            COUNT(DISTINCT s.student_id) AS total_students,
            SUM(CASE WHEN s.student_conduct_accepted_at IS NOT NULL THEN 1 ELSE 0 END) AS accepted_count,
            SUM(CASE WHEN s.student_conduct_accepted_at IS NULL THEN 1 ELSE 0 END) AS pending_count
          FROM student s
          JOIN (
            SELECT se.student_id, MAX(se.student_enroll_date) AS max_enroll_date
            FROM student_enroll se
            GROUP BY se.student_id
          ) le ON le.student_id = s.student_id
          JOIN student_enroll e
            ON e.student_id = le.student_id
           AND e.student_enroll_date = le.max_enroll_date
          JOIN course c
            ON c.course_id = e.course_id
          JOIN department d
            ON d.department_id = c.department_id
          WHERE e.student_enroll_status = 'Following'
            AND d.department_id = ?
        ";
        $stmtCnt = mysqli_prepare($con, $sqlCount);
        mysqli_stmt_bind_param($stmtCnt, 's', $selectedDeptId);
      }
      mysqli_stmt_execute($stmtCnt);
      $cntRs = mysqli_stmt_get_result($stmtCnt);
      $cnt = $cntRs ? mysqli_fetch_assoc($cntRs) : null;
    ?>

    <div class="d-flex align-items-center mb-2">
      <a class="btn btn-sm btn-outline-secondary mr-2" href="<?php echo (defined('APP_BASE')?APP_BASE:''); ?>/administration/ConductReport.php">← Back</a>
      <h4 class="mb-0">Department: <?php echo esc($dname ?: $selectedDeptId); ?><?php if ($selectedCourseId) { echo ' · Course: ' . esc($cname ?: $selectedCourseId); } ?></h4>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body table-responsive">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="mb-0">Course-wise summary</h5>
          <?php if ($selectedCourseId): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?php echo (defined('APP_BASE')?APP_BASE:''); ?>/administration/ConductReport.php?dept=<?php echo urlencode($selectedDeptId); ?>">Clear course filter</a>
          <?php endif; ?>
        </div>
        <table class="table table-sm table-hover align-middle">
          <thead class="thead-light">
            <tr>
              <th>Course</th>
              <th class="text-right">Total</th>
              <th class="text-right">Accepted</th>
              <th class="text-right">Pending</th>
              <th class="text-right">Accepted %</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($coursesRs && mysqli_num_rows($coursesRs) > 0): ?>
              <?php $ctGrand = 0; $caGrand = 0; $cpGrand = 0; ?>
              <?php while ($cr = mysqli_fetch_assoc($coursesRs)): ?>
                <?php
                  $ctotal = (int)$cr['total_students'];
                  $cacc = (int)$cr['accepted_count'];
                  $cpen = (int)$cr['pending_count'];
                  $cpct = $ctotal > 0 ? round(100.0 * $cacc / $ctotal, 2) : 0;
                  $ctGrand += $ctotal; $caGrand += $cacc; $cpGrand += $cpen;
                ?>
                <tr>
                  <td><?php echo esc($cr['course_name']); ?></td>
                  <td class="text-right"><?php echo number_format($ctotal); ?></td>
                  <td class="text-right text-success"><?php echo number_format($cacc); ?></td>
                  <td class="text-right text-danger"><?php echo number_format($cpen); ?></td>
                  <td class="text-right"><?php echo number_format($cpct, 2); ?>%</td>
                  <td class="text-right">
                    <a class="btn btn-sm btn-outline-primary" href="<?php echo (defined('APP_BASE')?APP_BASE:''); ?>/administration/ConductReport.php?dept=<?php echo urlencode($selectedDeptId); ?>&course=<?php echo urlencode($cr['course_id']); ?>">View details</a>
                  </td>
                </tr>
              <?php endwhile; ?>
              <?php $cgpct = $ctGrand > 0 ? round(100.0 * $caGrand / $ctGrand, 2) : 0; ?>
              <tr class="font-weight-bold">
                <td class="text-right">Total</td>
                <td class="text-right"><?php echo number_format($ctGrand); ?></td>
                <td class="text-right text-success"><?php echo number_format($caGrand); ?></td>
                <td class="text-right text-danger"><?php echo number_format($cpGrand); ?></td>
                <td class="text-right"><?php echo number_format($cgpct, 2); ?>%</td>
                <td></td>
              </tr>
            <?php else: ?>
              <tr><td colspan="6" class="text-center text-muted">No courses found</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body table-responsive">
        <table class="table table-sm table-hover align-middle">
          <thead class="thead-light">
            <tr>
              <th>Student ID</th>
              <th>Name</th>
              <th>Initials</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Course</th>
              <th>Academic Year</th>
              <th>Status</th>
              <th>Accepted At</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($rs && mysqli_num_rows($rs) > 0): ?>
            <?php while ($r = mysqli_fetch_assoc($rs)): ?>
              <tr class="<?php echo empty($r['student_conduct_accepted_at']) ? 'table-warning' : ''; ?>">
                <td><?php echo esc($r['student_id']); ?></td>
                <td><?php echo esc($r['student_fullname']); ?></td>
                <td><?php echo esc($r['student_ininame']); ?></td>
                <td><?php echo esc($r['student_email']); ?></td>
                <td><?php echo esc($r['student_phone']); ?></td>
                <td><?php echo esc($r['course_name']); ?></td>
                <td><?php echo esc($r['academic_year']); ?></td>
                <td><?php echo esc($r['student_enroll_status']); ?></td>
                <td><?php echo esc($r['student_conduct_accepted_at']); ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="9" class="text-center text-muted">No students found</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php include_once __DIR__ . '/../footer.php'; ?>
