<?php 
// BLOCK#1 START DON'T CHANGE THE ORDER
$title = "Department Students | SLGTI";
// Start output buffering to allow header() later for CSV export
if (function_exists('ob_start')) { ob_start(); }
include_once("../config.php");
// Allow only HOD
require_roles(['HOD']);
include_once("../head.php");
include_once("../menu.php");
?>
<!-- END DON'T CHANGE THE ORDER -->

<!-- BLOCK#2 START YOUR CODER HERE -->
<div class="shadow p-3 mb-5 alert bg-dark rounded text-white text-center" role="alert">
  <div class="highlight-blue">
    <div class="container">
      <div class="intro">
        <h1 class="display-4 text-center">My Department - Students</h1>
      </div>
    </div>
  </div>
</div>

<?php
// Role and scoping
$isHOD = is_role('HOD');
$deptCode = isset($_SESSION['department_code']) ? $_SESSION['department_code'] : null;

// Determine department scope
$deptFilter = null;
if ($isHOD && !empty($deptCode)) {
    $deptFilter = mysqli_real_escape_string($con, $deptCode);
}

// Fallback: if HOD doesn't have department_code in session, resolve from staff table
if ($deptFilter === null && $isHOD && isset($_SESSION['user_name']) && $_SESSION['user_name'] !== '') {
    $uid = mysqli_real_escape_string($con, $_SESSION['user_name']);
    $q   = "SELECT department_id FROM staff WHERE staff_id='$uid' LIMIT 1";
    if ($rs = mysqli_query($con, $q)) {
        if ($row = mysqli_fetch_assoc($rs)) {
            if (!empty($row['department_id'])) {
                $deptFilter = mysqli_real_escape_string($con, $row['department_id']);
            }
        }
        mysqli_free_result($rs);
    }
}

if ($deptFilter === null) {
    echo '<div class="alert alert-warning">Department not configured for your account. Please contact admin.</div>';
} else {
        // Filters
        $year    = isset($_GET['year']) ? mysqli_real_escape_string($con, $_GET['year']) : '';
        $status  = isset($_GET['status']) ? mysqli_real_escape_string($con, $_GET['status']) : '';
        // conduct split will be handled by two queries below
        $course  = isset($_GET['course']) ? mysqli_real_escape_string($con, $_GET['course']) : '';

        // Ensure conduct acceptance column exists (no-op if already there)
        @mysqli_query($con, "ALTER TABLE `student` ADD COLUMN `student_conduct_accepted_at` DATETIME NULL");

        // Build base query (department scoped) with dynamic filters (without conduct condition)
        $baseSql = "SELECT se.student_id,
                           s.student_fullname,
                           se.course_id,
                           c.course_name,
                           se.academic_year,
                           se.student_enroll_date,
                           se.student_enroll_status,
                           s.student_conduct_accepted_at
                    FROM student_enroll se
                    JOIN course c ON c.course_id = se.course_id
                    JOIN student s ON s.student_id = se.student_id
                    WHERE c.department_id = '$deptFilter'";
        if ($year !== '')   { $baseSql .= " AND se.academic_year = '$year'"; }
        if ($status !== '') { $baseSql .= " AND se.student_enroll_status = '$status'"; }
        if ($course !== '') { $baseSql .= " AND se.course_id = '$course'"; }
        // Order by Student ID as requested
        $orderBy = " ORDER BY s.student_id ASC";

        $sqlAccepted = $baseSql . " AND s.student_conduct_accepted_at IS NOT NULL" . $orderBy;
        $sqlPending  = $baseSql . " AND s.student_conduct_accepted_at IS NULL" . $orderBy;

        // CSV Export handler (Accepted/Pending) with current filters
        if (isset($_GET['export']) && in_array($_GET['export'], ['accepted','pending'], true)) {
            $mode = $_GET['export'];
            $exportSql = $mode === 'accepted' ? $sqlAccepted : $sqlPending;
            $exportRes = mysqli_query($con, $exportSql);

            // Clean any previous output before sending headers
            if (function_exists('ob_get_level')) {
                while (ob_get_level() > 0) { ob_end_clean(); }
            }
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="department_students_' . $mode . '.csv"');

            $out = fopen('php://output', 'w');
            // Header row
            fputcsv($out, ['Student_ID','Student Name','Course ID','Course Name','Academic Year','Enroll Date','Status','Accepted At']);
            if ($exportRes && mysqli_num_rows($exportRes) > 0) {
                while ($row = mysqli_fetch_assoc($exportRes)) {
                    fputcsv($out, [
                        $row['student_id'],
                        $row['student_fullname'],
                        $row['course_id'],
                        $row['course_name'],
                        $row['academic_year'],
                        $row['student_enroll_date'],
                        $row['student_enroll_status'],
                        isset($row['student_conduct_accepted_at']) ? $row['student_conduct_accepted_at'] : ''
                    ]);
                }
            }
            if ($exportRes) { mysqli_free_result($exportRes); }
            fclose($out);
            exit;
        }

        $resA = mysqli_query($con, $sqlAccepted);
        $resP = mysqli_query($con, $sqlPending);

        // Load department courses for filter
        $courses = [];
        if ($cr = mysqli_query($con, "SELECT course_id, course_name FROM course WHERE department_id='".mysqli_real_escape_string($con, $deptFilter)."' ORDER BY course_name")) {
            while ($r = mysqli_fetch_assoc($cr)) { $courses[] = $r; }
            mysqli_free_result($cr);
        }

        // Toolbar
        echo '<div class="d-flex justify-content-between align-items-center mb-2">';
        echo '  <div>';
        echo '    <strong>Department:</strong> '.htmlspecialchars($deptFilter);
        echo '  </div>';
        echo '  <form method="get" class="form-inline">';
        echo '    <input type="hidden" name="dept" value="'.htmlspecialchars($deptFilter).'" />';
        echo '    <label class="mr-2">Academic Year</label>';
        echo '    <input type="text" name="year" value="'.htmlspecialchars($year).'" class="form-control mr-2" placeholder="e.g. 2023/2024" />';
        echo '    <label class="mr-2">Status</label>';
        echo '    <select name="status" class="form-control mr-2">';
        $statuses = [''=>"-- Any --", 'Following'=>'Following','Active'=>'Active','Completed'=>'Completed','Suspended'=>'Suspended','Inactive'=>'Inactive'];
        foreach ($statuses as $k=>$v) { echo '<option value="'.htmlspecialchars($k).'"'.($status===$k?' selected':'').'>'.htmlspecialchars($v).'</option>'; }
        echo '    </select>';
        // Conduct is shown as two separate tables below
        echo '    <label class="mr-2">Course</label>';
        echo '    <select name="course" class="form-control mr-2">';
        echo '      <option value="">-- Any --</option>';
        foreach ($courses as $c) { echo '<option value="'.htmlspecialchars($c['course_id']).'"'.($course===$c['course_id']?' selected':'').'>'.htmlspecialchars($c['course_name']).'</option>'; }
        echo '    </select>';
        echo '    <button type="submit" class="btn btn-outline-primary">Filter</button>';
        echo '  </form>';
        echo '</div>';

        // Build export URLs preserving filters
        $qs = $_GET;
        unset($qs['export']);
        $accQs = $qs; $accQs['export'] = 'accepted';
        $pendQs = $qs; $pendQs['export'] = 'pending';
        $exportAccUrl = $_SERVER['PHP_SELF'] . '?' . http_build_query($accQs);
        $exportPendUrl = $_SERVER['PHP_SELF'] . '?' . http_build_query($pendQs);

        // Accepted list
        echo '<div class="d-flex justify-content-between align-items-center mt-3">';
        echo '  <h5 class="mb-0">Accepted (Code of Conduct)</h5>';
        echo '  <a class="btn btn-sm btn-success" href="'.htmlspecialchars($exportAccUrl).'">Export CSV</a>';
        echo '</div>';
        echo '<div class="table-responsive">';
        echo '  <table class="table table-hover">';
        echo '    <thead class="thead-dark">';
        echo '      <tr>';
        echo '        <th>Student_ID</th>';
        echo '        <th>Student Name</th>';
        echo '        <th>Course</th>';
        echo '        <th>Academic Year</th>';
        echo '        <th>Enroll Date</th>';
        echo '        <th>Status</th>';
        echo '        <th>Accepted At</th>';
        echo '      </tr>';
        echo '    </thead>';
        echo '    <tbody>';
        if ($resA && mysqli_num_rows($resA) > 0) {
            while ($r = mysqli_fetch_assoc($resA)) {
                echo '<tr>';
                echo '  <td>'.htmlspecialchars($r['student_id']).'</td>';
                echo '  <td>'.htmlspecialchars($r['student_fullname']).'</td>';
                echo '  <td>'.htmlspecialchars($r['course_id']).' - '.htmlspecialchars($r['course_name']).'</td>';
                echo '  <td>'.htmlspecialchars($r['academic_year']).'</td>';
                echo '  <td>'.htmlspecialchars($r['student_enroll_date']).'</td>';
                echo '  <td>'.htmlspecialchars($r['student_enroll_status']).'</td>';
                echo '  <td>'.htmlspecialchars($r['student_conduct_accepted_at']).'</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="7">0 results</td></tr>';
        }
        echo '    </tbody>';
        echo '  </table>';
        echo '</div>';

        // Not accepted list
        echo '<div class="d-flex justify-content-between align-items-center mt-4">';
        echo '  <h5 class="mb-0">Not Accepted (Pending)</h5>';
        echo '  <a class="btn btn-sm btn-success" href="'.htmlspecialchars($exportPendUrl).'">Export CSV</a>';
        echo '</div>';
        echo '<div class="table-responsive">';
        echo '  <table class="table table-hover">';
        echo '    <thead class="thead-dark">';
        echo '      <tr>';
        echo '        <th>Student_ID</th>';
        echo '        <th>Student Name</th>';
        echo '        <th>Course</th>';
        echo '        <th>Academic Year</th>';
        echo '        <th>Enroll Date</th>';
        echo '        <th>Status</th>';
        echo '      </tr>';
        echo '    </thead>';
        echo '    <tbody>';
        if ($resP && mysqli_num_rows($resP) > 0) {
            while ($r = mysqli_fetch_assoc($resP)) {
                echo '<tr>';
                echo '  <td>'.htmlspecialchars($r['student_id']).'</td>';
                echo '  <td>'.htmlspecialchars($r['student_fullname']).'</td>';
                echo '  <td>'.htmlspecialchars($r['course_id']).' - '.htmlspecialchars($r['course_name']).'</td>';
                echo '  <td>'.htmlspecialchars($r['academic_year']).'</td>';
                echo '  <td>'.htmlspecialchars($r['student_enroll_date']).'</td>';
                echo '  <td>'.htmlspecialchars($r['student_enroll_status']).'</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="6">0 results</td></tr>';
        }
        echo '    </tbody>';
        echo '  </table>';
        echo '</div>';
}
?>

<a href="/MIS/department/Department.php" class="btn btn-primary" role="button" aria-pressed="true">Back</a>
<br>
<!-- END YOUR CODER HERE -->

<!-- BLOCK#3 START DON'T CHANGE THE ORDER -->
<?php 
include_once("../footer.php");
?>
<!-- END DON'T CHANGE THE ORDER -->
