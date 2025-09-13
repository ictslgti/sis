<!-- BLOCK#1 START DON'T CHANGE THE ORDER-->
<?php
$title = "OnPeak Request | SLGTI";
include_once("../config.php");
include_once("../head.php");
// Use the compact student top nav and remove sidebar/menu for this page
include_once("../student/top_nav.php");


//  $student_id = $_SESSION['user_name'];
//  $user_type = $_SESSION['user_type'];
//  echo $department_id = $_SESSION['department_code'];
//  if($user_type == 'ADM'){
if ($_SESSION['user_type'] == 'STU') {
?>
    <!--END DON'T CHANGE THE ORDER-->

    <!--BLOCK#2 START YOUR CODE HERE -->
    <style>
        /* OnPeak page responsive polish */
        .onpeak-container {
            max-width: 860px;
            margin: 0 auto;
        }

        .onpeak-container .shadow {
            box-shadow: 0 8px 24px rgba(0, 0, 0, .06), 0 2px 8px rgba(0, 0, 0, .04) !important;
        }

        .onpeak-container .bg-white {
            background-color: #fff !important;
        }

        /* Modern card look */
        body {
            background: #eef3f7;
        }

        .card-lite {
            border: 1px solid rgba(0, 0, 0, .06);
            border-radius: .6rem;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .06), 0 2px 8px rgba(0, 0, 0, .04);
            background: #fff;
            overflow: hidden;
        }

        .section-title {
            font-weight: 600;
        }

        .card-lite {
            width: 100%;
        }

        .history-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: .5rem;
        }

        /* Row background colors for status */
        .row-status-pending {
            background-color: #fff3cd;
        }

        /* warning */
        .row-status-approved {
            background-color: #d4edda;
        }

        /* success */
        .row-status-rejected {
            background-color: #f8d7da;
        }

        /* danger */

        /* Status badges */
        .badge-status {
            font-weight: 600;
            letter-spacing: .2px;
        }

        .badge-pending {
            background: #ffc107;
            color: #212529;
        }

        .badge-approved {
            background: #28a745;
        }

        .badge-rejected {
            background: #dc3545;
        }

        /* Tighten table spacing */
        .onpeak-container .table th,
        .onpeak-container .table td {
            padding: .6rem .65rem;
            vertical-align: middle;
        }

        .onpeak-container .table thead th {
            white-space: nowrap;
        }

        /* Mobile tweaks */
        @media (max-width: 575.98px) {
            .onpeak-container {
                padding-left: .75rem;
                padding-right: .75rem;
            }

            .onpeak-container .page-title {
                font-size: 1.5rem;
                line-height: 1.3;
            }

            .onpeak-container .blockquote {
                margin-bottom: .75rem;
            }

            .onpeak-container .form-group {
                margin-bottom: .75rem;
            }

            .onpeak-container .table {
                font-size: .95rem;
            }

            /* Compact cards and tables on small screens */
            .onpeak-container .card-lite {
                margin-bottom: .75rem;
                border-radius: .5rem;
            }

            .onpeak-container .card-lite.p-3 {
                padding: .75rem !important;
            }

            .onpeak-container .table th,
            .onpeak-container .table td {
                padding: .5rem .55rem;
            }

            .onpeak-container .section-title {
                margin-bottom: .5rem;
            }
        }

        /* Align action buttons */
        .onpeak-container .btn {
            border-radius: .35rem;
        }

        .onpeak-container .btn-primary {
            box-shadow: 0 2px 6px rgba(0, 123, 255, .25);
        }
    </style>

    <!--insert Code-->
    <?php

    $s_id =  $_SESSION['user_name'];
    $u_type =  $_SESSION['user_type'];

    $student_id = $name = $department_id = null;
    if ($_SESSION['user_type'] == 'STU') {
        // Derive current department_id from the student's active enrollment
        $sql = "SELECT d.department_id
           FROM student_enroll e
           JOIN course c ON e.course_id = c.course_id
           JOIN department d ON c.department_id = d.department_id
           WHERE e.student_id = '$s_id' AND e.student_enroll_status='Following'
           LIMIT 1";
        if ($result = mysqli_query($con, $sql)) {
            if (mysqli_num_rows($result) >= 1) {
                $row = mysqli_fetch_assoc($result);
                $department_id = $row['department_id'];
            }
            mysqli_free_result($result);
        }
    }
    ?>

    <?PHP
    // If editing, load the existing record for this student
    $editing_id = 0;
    $form_contact_no = '';
    $form_reason = '';
    $form_exit_date = '';
    $form_exit_time = '';
    $form_return_date = '';
    $form_return_time = '';
    $form_comment = '';

    if (isset($_GET['edit'])) {
        $maybe_id = (int) $_GET['edit'];
        if ($maybe_id > 0) {
            $owner_id = mysqli_real_escape_string($con, $s_id);
            // Only allow editing if status is Pending approval
            $sql = "SELECT * FROM `onpeak_request` WHERE `id`=$maybe_id AND `student_id`='$owner_id' AND TRIM(LOWER(`onpeak_request_status`)) LIKE 'pending%'";
            if ($res = mysqli_query($con, $sql)) {
                if (mysqli_num_rows($res) === 1) {
                    $row = mysqli_fetch_assoc($res);
                    $editing_id = (int) $row['id'];
                    $form_contact_no = $row['contact_no'];
                    $form_reason = $row['reason'];
                    $form_exit_date = $row['exit_date'];
                    $form_exit_time = $row['exit_time'];
                    $form_return_date = $row['return_date'];
                    $form_return_time = $row['return_time'];
                    $form_comment = $row['comment'];
                }
                mysqli_free_result($res);
            }
        }
    }

    // Handle create or update
    if (isset($_POST['req']) || isset($_POST['update'])) {
        // Always take student id from session for security
        $s_id = mysqli_real_escape_string($con, $_SESSION['user_name']);
        // Use derived $department_id (no user input)
        $d_id = mysqli_real_escape_string($con, $department_id);
        $contact_no = mysqli_real_escape_string($con, $_POST['contact_no']);
        $reason = mysqli_real_escape_string($con, $_POST['reason']);
        $exit_date = mysqli_real_escape_string($con, $_POST['exit_date']);
        $exit_time = mysqli_real_escape_string($con, $_POST['exit_time']);
        $return_date = mysqli_real_escape_string($con, $_POST['return_date']);
        $return_time = mysqli_real_escape_string($con, $_POST['return_time']);
        $comment = mysqli_real_escape_string($con, $_POST['comment']);

        // Server-side required validation
        $errors = [];
        if ($contact_no === '') {
            $errors[] = 'Contact Number is required';
        }
        if ($reason === '') {
            $errors[] = 'Reason is required';
        }
        if ($exit_date === '') {
            $errors[] = 'Exit Date is required';
        }
        if ($exit_time === '') {
            $errors[] = 'Exit Time is required';
        }
        if ($return_date === '') {
            $errors[] = 'Return Date is required';
        }
        if ($return_time === '') {
            $errors[] = 'Return Time is required';
        }
        if (!empty($errors)) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert"><strong>Please fix the following:</strong><ul class="mb-0">';
            foreach ($errors as $e) {
                echo '<li>' . htmlspecialchars($e) . '</li>';
            }
            echo '</ul><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
        } else {
            $post_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            if ($post_id > 0) {
                // UPDATE flow (must belong to student)
                $owner_id = mysqli_real_escape_string($con, $s_id);
                $sql = "UPDATE `onpeak_request` SET 
                        `department_id`='$d_id',
                        `contact_no`='$contact_no',
                        `reason`='$reason',
                        `exit_date`='$exit_date',
                        `exit_time`='$exit_time',
                        `return_date`='$return_date',
                        `return_time`='$return_time',
                        `comment`='$comment'
                    WHERE `id`=$post_id AND `student_id`='$owner_id' AND TRIM(LOWER(`onpeak_request_status`)) LIKE 'pending%'";
                if (mysqli_query($con, $sql)) {
                    if (mysqli_affected_rows($con) > 0) {
                        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">'
                            . '<strong><h5> Request updated successfully</h5></strong>'
                            . '<button type="button" class="close" data-dismiss="alert" aria-label="Close">'
                            . '<span aria-hidden="true">&times;</span>'
                            . '</button></div>';
                    } else {
                        // Distinguish between no-change vs not-pending/unauthorized
                        $chk_sql = "SELECT TRIM(LOWER(onpeak_request_status)) AS st FROM onpeak_request WHERE id=$post_id AND student_id='$owner_id'";
                        $chk_res = mysqli_query($con, $chk_sql);
                        if ($chk_res && mysqli_num_rows($chk_res) === 1) {
                            $st_row = mysqli_fetch_assoc($chk_res);
                            $st = isset($st_row['st']) ? $st_row['st'] : '';
                            if (strpos($st, 'pending') === 0) {
                                echo '<div class="alert alert-info alert-dismissible fade show" role="alert">'
                                    . 'No changes detected. The request remains pending.'
                                    . '<button type="button" class="close" data-dismiss="alert" aria-label="Close">'
                                    . '<span aria-hidden="true">&times;</span>'
                                    . '</button></div>';
                            } else {
                                echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">'
                                    . 'This request is not pending anymore, so it cannot be updated.'
                                    . '<button type="button" class="close" data-dismiss="alert" aria-label="Close">'
                                    . '<span aria-hidden="true">&times;</span>'
                                    . '</button></div>';
                            }
                            mysqli_free_result($chk_res);
                        } else {
                            echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">'
                                . 'Record not found or you do not have permission to update it.'
                                . '<button type="button" class="close" data-dismiss="alert" aria-label="Close">'
                                . '<span aria-hidden="true">&times;</span>'
                                . '</button></div>';
                        }
                    }
                } else {
                    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
                        . htmlspecialchars('Error updating record: ' . mysqli_error($con)) .
                        '<button type="button" class="close" data-dismiss="alert" aria-label="Close">'
                        . '<span aria-hidden="true">&times;</span>'
                        . '</button></div>';
                }
            } else {
                // INSERT flow
                if ($d_id === '' || $d_id === null) {
                    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
                        . 'Cannot determine your Department. Ensure you are actively enrolled in a course.'
                        . '<button type="button" class="close" data-dismiss="alert" aria-label="Close">'
                        . '<span aria-hidden="true">&times;</span>'
                        . '</button></div>';
                } else {
                    $status = 'Pending approval';
                    $sql = "INSERT INTO `onpeak_request`(
                        `student_id`,`department_id`, `contact_no`, `reason`, `exit_date`, `exit_time`, `return_date`, `return_time`, `comment`, `onpeak_request_status`, `request_date_time`
                    ) VALUES (
                        '$s_id','$d_id','$contact_no','$reason','$exit_date','$exit_time','$return_date','$return_time','$comment', '$status', NOW()
                    )";
                    if (mysqli_query($con, $sql)) {
                        if (mysqli_affected_rows($con) > 0) {
                            echo '
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <strong><h5> ' . $s_id . '</strong> Request Submitted </h5>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                        </button>
                        </div>    
                        ';
                            // Optional: redirect to avoid duplicate submit and ensure history refresh
                            echo '<script>setTimeout(function(){ window.location = "' . (defined('APP_BASE') ? APP_BASE : '') . '/onpeak/RequestOnPeak.php"; }, 500);</script>';
                        } else {
                            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
                                . 'Insert did not affect any rows. ' . htmlspecialchars(mysqli_error($con)) .
                                '<button type="button" class="close" data-dismiss="alert" aria-label="Close">'
                                . '<span aria-hidden="true">&times;</span>'
                                . '</button></div>';
                        }
                    } else {
                        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
                            . htmlspecialchars('Error inserting record: ' . mysqli_error($con)) .
                            '<button type="button" class="close" data-dismiss="alert" aria-label="Close">'
                            . '<span aria-hidden="true">&times;</span>'
                            . '</button></div>';
                    }
                }
            }
        }
    }
    ?>

    <!--Delete Code-->

    <?php

    if (isset($_GET['delete'])) {
        $delete_id = isset($_GET['delete']) ? (int) $_GET['delete'] : 0;
        $owner_id = mysqli_real_escape_string($con, $_SESSION['user_name']);
        if ($delete_id > 0) {
            // Only allow delete when pending
            $sql = "DELETE FROM `onpeak_request` WHERE `id` = $delete_id AND `student_id` = '$owner_id' AND TRIM(LOWER(`onpeak_request_status`)) LIKE 'pending%'";
            if (mysqli_query($con, $sql)) {
                if (mysqli_affected_rows($con) > 0) {
                    echo '
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                     <strong> <h5> Record deleted successfully </h5> </strong>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                    </button>
                    </div>    
                    ';
                } else {
                    echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">'
                        . 'Record not found, not pending, or you do not have permission to delete it.' .
                        '<button type="button" class="close" data-dismiss="alert" aria-label="Close">'
                        . '<span aria-hidden="true">&times;</span>'
                        . '</button></div>';
                }
            } else {
                echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
                    . htmlspecialchars('Error deleting record: ' . mysqli_error($con)) .
                    '<button type="button" class="close" data-dismiss="alert" aria-label="Close">'
                    . '<span aria-hidden="true">&times;</span>'
                    . '</button></div>';
            }
        }
    }
    ?>


    <!--Form Deign Start-->

    <form method="POST">
        <div class="container my-4 onpeak-container">

            <!-- card start here-->

            <div class="card-lite p-3 mb-3">
                <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom" style="background:#f8f9fa;border-top-left-radius:.6rem;border-top-right-radius:.6rem;">
                    <div class="d-flex align-items-center">
                        <i class="far fa-calendar-check text-primary mr-2"></i>
                        <h5 class="mb-0">OnPeak Request</h5>
                    </div>
                    <button id="toggleFormBtn" class="btn btn-sm btn-outline-secondary" data-toggle="collapse" data-target="#formFields" aria-expanded="true" aria-controls="formFields">Hide form</button>
                </div>
                <div class="px-3 py-2">
                    <div class="text-muted small">Temporary Exit Application</div>
                </div>
                <div id="formFields" class="collapse show">
                    <div class="container-fluid px-0">

                        <div class="intro">

                            <div class="form-group">
                                <label for="student_id">Registration No</label>
                                <input class="form-control" id="student_id" name="student_id" type="text"
                                    value="<?php if ($_SESSION['user_type'] == 'STU') echo $s_id; ?>" readonly
                                    aria-readonly="true" placeholder="Your Registration Number">
                            </div>


                            <div class="form-group">
                                <label for="contact_no">Contact Number</label>
                                <input class="form-control" name="contact_no" type="tel" id="contact_no"
                                    placeholder="e.g., 0712345678" value="<?php echo htmlspecialchars($form_contact_no); ?>"
                                    required>
                            </div>



                            <div class="form-group">
                                <label for="reason">Reason for Exit</label>
                                <select class="form-control" id="reason" name="reason" required>
                                    <option value="" disabled <?php echo $form_reason === '' ? 'selected' : ''; ?>>Select reason
                                    </option>
                                    <option value="Hospital" <?php echo ($form_reason === 'Hospital') ? 'selected' : ''; ?>>
                                        Hospital</option>
                                    <option value="Family issues"
                                        <?php echo ($form_reason === 'Family issues') ? 'selected' : ''; ?>>Family issues</option>
                                    <option value="Other Reasons"
                                        <?php echo ($form_reason === 'Other Reasons') ? 'selected' : ''; ?>>Other Reasons</option>
                                </select>
                            </div>



                            <div class="form-row">
                                <div class="form-group col-12 col-md-6">
                                    <label for="exit_date">Exit Date</label>
                                    <input class="form-control" type="date" name="exit_date" id="exit_date"
                                        value="<?php echo htmlspecialchars($form_exit_date); ?>" required>
                                </div>
                                <div class="form-group col-12 col-md-6">
                                    <label for="exit_time">Exit Time</label>
                                    <input class="form-control" type="time" name="exit_time" id="exit_time"
                                        value="<?php echo htmlspecialchars($form_exit_time); ?>" required>
                                </div>
                            </div>



                            <div class="form-row">
                                <div class="form-group col-12 col-md-6">
                                    <label for="return_date">Return Date</label>
                                    <input class="form-control" type="date" name="return_date" id="return_date"
                                        value="<?php echo htmlspecialchars($form_return_date); ?>" required>
                                </div>
                                <div class="form-group col-12 col-md-6">
                                    <label for="return_time">Return Time</label>
                                    <input class="form-control" type="time" name="return_time" id="return_time"
                                        value="<?php echo htmlspecialchars($form_return_time); ?>" required>
                                </div>
                            </div>



                            <div class="form-group mb-2">
                                <label for="comment">Comments</label>
                                <textarea class="form-control" name="comment" rows="3" id="comment"
                                    placeholder="Comments (optional)"><?php echo htmlspecialchars($form_comment); ?></textarea>
                            </div>


                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    var exitDate = document.getElementById('exit_date');
                                    var returnDate = document.getElementById('return_date');

                                    function syncMinReturnDate() {
                                        if (!exitDate || !returnDate) return;
                                        var exitVal = exitDate.value;
                                        if (exitVal) {
                                            returnDate.min = exitVal; // disable dates before exit date
                                            if (returnDate.value && returnDate.value < exitVal) {
                                                returnDate.value = exitVal; // correct invalid selection
                                            }
                                        } else {
                                            returnDate.removeAttribute('min');
                                        }
                                    }

                                    // Apply on load (handles pre-filled values) and when exit date changes
                                    syncMinReturnDate();
                                    exitDate && exitDate.addEventListener('change', syncMinReturnDate);
                                });
                            </script>


                            <div class="row">
                                <div class="col">
                                    <small class="text-muted d-block text-center">

                                        This request must be approved by the HOD and Warden, when students
                                        want to exit SLGTI during school hours/ on peak (8.15 am - 4.15 pm)

                                    </small>
                                    <br>

                                    <div class="mx-auto" style="width: 100%">
                                        <?php if ($editing_id > 0) {
                                            echo '<input type="hidden" name="id" value="' . (int)$editing_id . '">';
                                        }
                                        ?>
                                        <button type="submit" class="btn btn-primary btn-block"
                                            name="<?php echo ($editing_id > 0) ? 'update' : 'req'; ?>"> <i
                                                class="fab fa-telegram"></i><strong>
                                                <?php echo ($editing_id > 0) ? 'Update Request' : 'Request to approval'; ?>
                                            </strong></button>
                                        <!-- <input type="submit" name="req" class="btn btn-primary " > -->
                                    </div>
                                </div>
                            </div>



                        </div>
                    </div>
                </div>
            </div>

            <div class="card-lite p-3 mb-3">
                <div class="history-toolbar mb-2">
                    <span class="section-title text-info mb-0">History</span>
                    <div class="d-flex align-items-center" style="gap:.5rem;">
                        <input type="month" id="opFilterMonth" class="form-control form-control-sm" style="max-width: 170px;">
                        <input type="text" id="opFilterSearch" class="form-control form-control-sm" placeholder="Search..." style="max-width: 190px;">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover" id="opHistoryTable">
                        <thead>
                            <tr>
                                <th scope="col">EXIT DATE</th>
                                <th scope="col">RETURN DATE</th>
                                <th scope="col" class="text-center">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody id="opHistoryBody">

                            <?php
                            $s_id =  $_SESSION['user_name'];
                            $sql = "SELECT * FROM `onpeak_request` WHERE `student_id`='$s_id' ORDER BY `id` DESC";
                            $result = mysqli_query($con, $sql);
                            if (mysqli_num_rows($result) > 0) {
                                while ($row = mysqli_fetch_assoc($result)) {

                                    // Determine status for row background color
                                    $stRaw = isset($row['onpeak_request_status']) ? trim($row['onpeak_request_status']) : '';
                                    $stLower = strtolower($stRaw);
                                    $rowClass = '';
                                    if ($stRaw === '' || strpos($stLower, 'pend') === 0) {
                                        $stRaw = $stRaw === '' ? 'Pending' : $stRaw;
                                        $rowClass = 'row-status-pending';
                                    } elseif (strpos($stLower, 'approv') === 0) {
                                        $rowClass = 'row-status-approved';
                                    } elseif (strpos($stLower, 'not') === 0 || strpos($stLower, 'reject') === 0) {
                                        $rowClass = 'row-status-rejected';
                                    }

                                    // Keep full details via data-* but only show selected columns
                                    $isPending = ($stRaw === '' || strpos($stLower, 'pend') === 0);
                                    echo '
                 <tr class="' . $rowClass . '" data-exit="' . htmlspecialchars($row["exit_date"]) . '" data-reason="' . htmlspecialchars($row["reason"]) . '" data-ref="' . htmlspecialchars($row["request_date_time"]) . '" data-status="' . htmlspecialchars($stRaw) . '">
                    <td class="op-exit-date">' . htmlspecialchars($row["exit_date"]) . '</td>
                    <td class="op-return-date">' . htmlspecialchars($row["return_date"]) . '</td>
                    <td class="text-center">
                      <button type="button" class="btn btn-sm btn-outline-secondary op-view"
                        data-id="' . (int)$row["id"] . '"
                        data-exit-date="' . htmlspecialchars($row["exit_date"]) . '"
                        data-exit-time="' . htmlspecialchars($row["exit_time"]) . '"
                        data-return-date="' . htmlspecialchars($row["return_date"]) . '"
                        data-return-time="' . htmlspecialchars($row["return_time"]) . '"
                        data-reason="' . htmlspecialchars($row["reason"]) . '"
                        data-contact="' . htmlspecialchars($row["contact_no"]) . '"
                        data-comment="' . htmlspecialchars($row["comment"]) . '"
                        data-status="' . htmlspecialchars($stRaw) . '"
                        data-ref="' . htmlspecialchars($row["request_date_time"]) . '">
                        <i class="far fa-eye"></i>
                      </button>
                      ' . ($isPending ? '<a class="btn btn-sm btn-danger ml-1" onclick="return confirm(\'Are you sure you want to delete this request?\');" href="' . (defined('APP_BASE') ? APP_BASE : '') . '/onpeak/RequestOnPeak.php?delete=' . (int)$row["id"] . '"><i class="fas fa-trash"></i></a>' : '') . '
                    </td>
                 </tr>';
                                }
                            } else {
                                echo '<tr class="text-muted"><td colspan="3" class="text-center">No more Requests</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        </div>
    </form>




    <!-- View Details Modal -->
    <div class="modal fade" id="opViewModal" tabindex="-1" role="dialog" aria-labelledby="opViewLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="opViewLabel"><i class="far fa-eye mr-1 text-secondary"></i> OnPeak Request Details</h6>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 col-md-6"><strong>Exit</strong><br><span id="vExitDate">-</span> <small id="vExitTime" class="text-muted"></small></div>
                        <div class="col-12 col-md-6"><strong>Return</strong><br><span id="vReturnDate">-</span> <small id="vReturnTime" class="text-muted"></small></div>
                    </div>
                    <hr>
                    <div><strong>Reason:</strong> <span id="vReason">-</span></div>
                    <div><strong>Contact:</strong> <span id="vContact">-</span></div>
                    <div><strong>Status:</strong> <span id="vStatus">-</span></div>
                    <div><strong>Reference:</strong> <small id="vRef" class="text-muted">-</small></div>
                    <div class="mt-2"><strong>Comments:</strong><br>
                        <div id="vComment" class="text-muted">-</div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function() {
            function bindViewButtons() {
                var btns = document.querySelectorAll('.op-view');
                btns.forEach(function(b) {
                    b.addEventListener('click', function() {
                        document.getElementById('vExitDate').textContent = this.getAttribute('data-exit-date') || '-';
                        document.getElementById('vExitTime').textContent = this.getAttribute('data-exit-time') || '';
                        document.getElementById('vReturnDate').textContent = this.getAttribute('data-return-date') || '-';
                        document.getElementById('vReturnTime').textContent = this.getAttribute('data-return-time') || '';
                        document.getElementById('vReason').textContent = this.getAttribute('data-reason') || '-';
                        document.getElementById('vContact').textContent = this.getAttribute('data-contact') || '-';
                        document.getElementById('vComment').textContent = this.getAttribute('data-comment') || '-';
                        document.getElementById('vStatus').textContent = this.getAttribute('data-status') || '-';
                        document.getElementById('vRef').textContent = this.getAttribute('data-ref') || '';
                        if (window.jQuery) jQuery('#opViewModal').modal('show');
                    });
                });
            }
            // Run after load
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bindViewButtons);
            } else {
                bindViewButtons();
            }
        })();
    </script>

    <!--END OF YOUR COD-->
<?php } ?>
<!--BLOCK#3 START DON'T CHANGE THE ORDER-->
<?php include_once("../footer.php"); ?>