	<!--Block#1 start dont change the order-->
	<?php
  $title = "Course details | SLGTI";
  include_once("../config.php");
  include_once("../head.php");
  include_once("../menu.php");
  ?>
	<!-- end dont change the order-->


	<!-- Block#2 start your code -->

	<?php
  // Role flags and context
  $isADM = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'ADM';
  $isHOD = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'HOD';
  $deptCode = isset($_SESSION['department_code']) ? trim((string)$_SESSION['department_code']) : '';

  // Filters
  $filterDept = isset($_GET['department_id']) ? trim((string)$_GET['department_id']) : '';
  // Backward compatibility with old param `id`
  if ($filterDept === '' && isset($_GET['id'])) {
    $filterDept = trim((string)$_GET['id']);
  }
  $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

  // Enforce HOD scoping
  if ($isHOD && $deptCode !== '') {
    $filterDept = $deptCode;
  }

  // Handle delete (ADM or HOD) with prepared statements
  $flash = '';
  if ((($isADM) || ($isHOD && $deptCode !== '')) && isset($_GET['delete_id']) && $_GET['delete_id'] !== '') {
    $cid = $_GET['delete_id'];
    // ADM can delete any course; HOD can delete only within their department
    if ($isADM) {
      $sqlDel = 'DELETE FROM course WHERE course_id=?';
    } else {
      $sqlDel = 'DELETE FROM course WHERE course_id=? AND department_id=?';
    }
    if ($stmt = mysqli_prepare($con, $sqlDel)) {
      if ($isADM) {
        mysqli_stmt_bind_param($stmt, 's', $cid);
      } else {
        mysqli_stmt_bind_param($stmt, 'ss', $cid, $deptCode);
      }
      if (@mysqli_stmt_execute($stmt)) {
        if (mysqli_affected_rows($con) > 0) {
          $flash = '<div class="alert alert-success alert-dismissible fade show" role="alert">Deleted: <strong>' . htmlspecialchars($cid) . '</strong><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
        } else {
          $flash = '<div class="alert alert-warning alert-dismissible fade show" role="alert">No course deleted (may not exist or not in your department).<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
        }
      } else {
        $flash = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Cannot delete course. It may be referenced elsewhere.<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
      }
      mysqli_stmt_close($stmt);
    }
  }
  ?>

	<div class="container-fluid">
	  <?php echo $flash; ?>
	  <div class="card shadow-sm mb-3">
	    <div class="card-header d-flex justify-content-between align-items-center bg-white">
	      <div>
	        <h5 class="mb-0">Course Details</h5>
	        <small class="text-muted">Browse and manage courses</small>
	      </div>
	      <div class="d-flex align-items-center">
	        <?php if ($isADM || $isHOD) { ?>
	          <a href="<?php echo (defined('APP_BASE') ? APP_BASE : ''); ?>/course/AddCourse.php" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>Add Course</a>
	        <?php } ?>
	      </div>
	    </div>
	    <div class="card-body">
	      <form class="form-row mb-3" method="get">
	        <div class="form-group col-md-4 mb-2">
	          <label class="small text-muted">Department</label>
	          <select class="custom-select" name="department_id" <?php echo ($isHOD && $deptCode !== '') ? 'disabled' : ''; ?>>
	            <option value="">All</option>
	            <?php
              $depRs = mysqli_query($con, 'SELECT department_id, department_name FROM department ORDER BY department_name');
              if ($depRs) {
                while ($d = mysqli_fetch_assoc($depRs)) {
                  $sel = ($filterDept !== '' && $filterDept === $d['department_id']) ? 'selected' : '';
                  echo '<option value="' . htmlspecialchars($d['department_id']) . '" ' . $sel . '>' . htmlspecialchars($d['department_name']) . '</option>';
                }
                mysqli_free_result($depRs);
              }
              ?>
	          </select>
	          <?php if ($isHOD && $deptCode !== '') {
              echo '<input type="hidden" name="department_id" value="' . htmlspecialchars($deptCode) . '">';
            } ?>
	        </div>
	        <div class="form-group col-md-4 mb-2">
	          <label class="small text-muted">Search</label>
	          <input type="text" class="form-control" name="q" placeholder="Course ID or Name" value="<?php echo htmlspecialchars($q); ?>">
	        </div>
	        <div class="form-group col-md-4 d-flex align-items-end mb-2">
	          <button class="btn btn-outline-primary mr-2" type="submit">Apply</button>
	          <a class="btn btn-outline-secondary" href="<?php echo (defined('APP_BASE') ? APP_BASE : ''); ?>/course/Course.php<?php echo $isHOD && $deptCode !== '' ? ('?department_id=' . urlencode($deptCode)) : ''; ?>">Reset</a>
	        </div>
	      </form>

	      <div class="table-responsive">
	        <table class="table table-hover table-striped mb-0">
	          <thead class="thead-dark">
	            <tr>
	              <th style="width:60px">#</th>
	              <th style="width:120px">ID</th>
	              <th>Course</th>
	              <th>Department</th>
	              <th style="width:120px">NVQ Level</th>
	              <?php if ($isADM || $isHOD) { ?><th style="width:210px">Actions</th><?php } ?>
	            </tr>
	          </thead>
	          <tbody>
	            <?php
              // Build query with filters
              $where = [];
              if ($filterDept !== '') {
                $where[] = "c.department_id='" . mysqli_real_escape_string($con, $filterDept) . "'";
              }
              if ($q !== '') {
                $qEsc = mysqli_real_escape_string($con, $q);
                $where[] = "(c.course_id LIKE '%$qEsc%' OR c.course_name LIKE '%$qEsc%')";
              }
              $sql = "SELECT c.course_id, c.course_name, c.course_nvq_level, d.department_name
                          FROM course c
                          INNER JOIN department d ON d.department_id=c.department_id
                          " . (!empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '') . "
                          ORDER BY d.department_name, c.course_name";
              $result = mysqli_query($con, $sql);
              $i = 1;
              if ($result && mysqli_num_rows($result) > 0) {
                while ($row = mysqli_fetch_assoc($result)) {
                  echo '<tr>';
                  echo '<td>' . ($i++) . '</td>';
                  echo '<td>' . htmlspecialchars($row['course_id']) . '</td>';
                  echo '<td>' . htmlspecialchars($row['course_name']) . '</td>';
                  echo '<td>' . htmlspecialchars($row['department_name']) . '</td>';
                  echo '<td>' . htmlspecialchars($row['course_nvq_level']) . '</td>';
                  if ($isADM || $isHOD) {
                    $base = (defined('APP_BASE') ? APP_BASE : '');
                    $cid = htmlspecialchars($row['course_id']);
                    echo '<td>'
                      . '<div class="btn-group btn-group-sm" role="group">'
                      . '<a class="btn btn-warning" href="' . $base . '/course/AddCourse.php?edits=' . urlencode($row['course_id']) . '" title="Edit"><i class="far fa-edit"></i></a>'
                      . '<button type="button" class="btn btn-danger" data-toggle="modal" data-target="#confirm-delete" data-href="?' . http_build_query(array_merge($_GET, ['delete_id' => $row['course_id']])) . '" title="Delete"><i class="fas fa-trash"></i></button>'
                      . '</div>'
                      . '</td>';
                  }
                  echo '</tr>';
                }
                mysqli_free_result($result);
              } else {
                echo '<tr><td colspan="' . ($isADM ? 6 : 5) . '" class="text-center text-muted">No courses found</td></tr>';
              }
              ?>
	          </tbody>
	        </table>
	      </div>
	    </div>
	  </div>
	</div>

	<!-- Confirm Delete Modal -->
	<div class="modal fade" id="confirm-delete" tabindex="-1" role="dialog" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
	  <div class="modal-dialog" role="document">
	    <div class="modal-content">
	      <div class="modal-header">
	        <h6 class="modal-title" id="confirmDeleteLabel">Confirm delete</h6>
	        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
	      </div>
	      <div class="modal-body">This action cannot be undone. Do you really want to delete this course?</div>
	      <div class="modal-footer">
	        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
	        <a href="#" class="btn btn-danger btn-ok">Delete</a>
	      </div>
	    </div>
	  </div>
	</div>
	<script>
	  $('#confirm-delete').on('show.bs.modal', function(e) {
	    var href = $(e.relatedTarget).data('href') || '#';
	    $(this).find('.btn-ok').attr('href', href);
	  });
	</script>

	<!-- end your code -->


	<!--Block#3 start dont change the order-->
	<?php include_once("../footer.php"); ?>
	<!--  end dont change the order-->