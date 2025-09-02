<!-- BLOCK#1 START DON'T CHANGE THE ORDER -->
<?php 
$title = "Department Students | SLGTI";
include_once("../config.php");
// Allow ADM, HOD, IN2
require_roles(['ADM','HOD','IN2']);
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
$isADM = is_role('ADM');
$isHOD = is_role('HOD');
$isIN2 = is_role('IN2');
$deptCode = isset($_SESSION['department_code']) ? $_SESSION['department_code'] : null;

// Determine department scope
$deptFilter = null;
if ($isHOD && !empty($deptCode)) {
    $deptFilter = mysqli_real_escape_string($con, $deptCode);
} elseif ($isIN2 && !empty($deptCode)) {
    // IN2 strictly limited to their own department; no override
    $deptFilter = mysqli_real_escape_string($con, $deptCode);
} elseif ($isADM && isset($_GET['dept'])) {
    $deptFilter = mysqli_real_escape_string($con, $_GET['dept']);
}

// Fallback: if HOD/IN2 don't have department_code in session, resolve from staff table
if ($deptFilter === null && ($isHOD || $isIN2) && isset($_SESSION['user_name']) && $_SESSION['user_name'] !== '') {
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
    echo '<div class="alert alert-info">Please select a department to view students.</div>';
    // Simple selector for Admins only
    if ($isADM) {
        $dres = mysqli_query($con, "SELECT department_id, department_name FROM department ORDER BY department_name");
        echo '<form method="get" class="form-inline mb-3">';
        echo '  <label class="mr-2">Department</label>';
        echo '  <select name="dept" class="form-control mr-2">';
        if ($dres) {
            while ($dr = mysqli_fetch_assoc($dres)) {
                echo '<option value="'.htmlspecialchars($dr['department_id']).'">'.htmlspecialchars($dr['department_name']).'</option>';
            }
        }
        echo '  </select>';
        echo '  <button type="submit" class="btn btn-primary">View</button>';
        echo '</form>';
    }
} else {
        // Optional academic year filter
        $year = isset($_GET['year']) ? mysqli_real_escape_string($con, $_GET['year']) : '';
        $yearCond = $year !== '' ? " AND se.academic_year = '$year'" : '';

        // Build query (department scoped)
        $sql = "SELECT se.student_id,
                       s.student_fullname,
                       se.course_id,
                       c.course_name,
                       se.academic_year,
                       se.student_enroll_date,
                       se.student_enroll_status
                FROM student_enroll se
                JOIN course c ON c.course_id = se.course_id
                JOIN student s ON s.student_id = se.student_id
                WHERE c.department_id = '$deptFilter' $yearCond
                ORDER BY se.academic_year DESC, se.course_id, s.student_fullname";

        $res = mysqli_query($con, $sql);

        // Toolbar
        echo '<div class="d-flex justify-content-between align-items-center mb-2">';
        echo '  <div>';
        echo '    <strong>Department:</strong> '.htmlspecialchars($deptFilter);
        echo '  </div>';
        echo '  <form method="get" class="form-inline">';
        echo '    <input type="hidden" name="dept" value="'.htmlspecialchars($deptFilter).'" />';
        echo '    <label class="mr-2">Academic Year</label>';
        echo '    <input type="text" name="year" value="'.htmlspecialchars($year).'" class="form-control mr-2" placeholder="e.g. 2023/2024" />';
        echo '    <button type="submit" class="btn btn-outline-primary">Filter</button>';
        echo '  </form>';
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
        if ($res && mysqli_num_rows($res) > 0) {
            while ($r = mysqli_fetch_assoc($res)) {
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
