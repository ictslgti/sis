<!-- BLOCK#1 START DON'T CHANGE THE ORDER-->
<?php
$title = "Sign in to continue to MIS @ SLGTI";
include_once("config.php");
?>
<!--END DON'T CHANGE THE ORDER-->
<?php

//loginWithCookieData
if (isset($_COOKIE['rememberme'])) {
  list ($user_name, $token, $hash) = explode(':', $_COOKIE['rememberme']);
  if ($hash == hash('sha256', $user_name . ':' . $token . COOKIE_SECRET_KEY) && !empty($token)) {

  $sql = "SELECT user_id, user_table, staff_position_type_id, user_name, user_email FROM user WHERE user_name = '$user_name'
  AND user_remember_me_token = '$token' AND user_remember_me_token IS NOT NULL";
  $result = mysqli_query($con,$sql);
  if(mysqli_num_rows($result)==1){
    $row = mysqli_fetch_assoc($result);
//set session data
    $username = $row['user_name'];
    $_SESSION['user_name'] =  $row['user_name'];
    $_SESSION['user_table'] =  $row['user_table'];
    $_SESSION['user_type'] =  $row['staff_position_type_id'];
//end session data

//update cookie
     $random_token_string = hash('sha256', mt_rand());
     $sql = "UPDATE user SET user_remember_me_token = '$random_token_string' WHERE user_name = '$user_name'";
      // generate cookie string that consists of userid, randomstring and combined hash of both
    $result = mysqli_query($con,$sql) or die();
    $cookie_string_first_part = $_SESSION['user_name'] . ':' . $random_token_string;
    $cookie_string_hash = hash('sha256', $cookie_string_first_part . COOKIE_SECRET_KEY);
    $cookie_string = $cookie_string_first_part . ':' . $cookie_string_hash;
    // set cookie (no explicit domain)
    setcookie('rememberme', $cookie_string, time() + COOKIE_RUNTIME, "/");
//end update cookie

//set department session data
    if($row['user_table']=='staff'){
        $sql_u = "SELECT * FROM `staff` WHERE `staff_id` = ?";
        $stmt = mysqli_prepare($con, $sql_u);
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result_u = mysqli_stmt_get_result($stmt);
        
        if(mysqli_num_rows($result_u)==1){
            $row_u = mysqli_fetch_assoc($result_u);
            $_SESSION['department_code'] = $row_u['department_id'];
        }
        mysqli_stmt_close($stmt);
    }
    if($row['user_table']=='student'){
        $sql_s = "SELECT `course`.`department_id` AS `department_id` FROM `student_enroll` 
                  LEFT JOIN `course` ON `student_enroll`.`course_id` = `course`.`course_id` 
                  WHERE `student_enroll`.`student_id` = ?";
        $stmt = mysqli_prepare($con, $sql_s);
        mysqli_stmt_bind_param($stmt, "s", $_SESSION['user_name']);
        mysqli_stmt_execute($stmt);
        $result_s = mysqli_stmt_get_result($stmt);
        
        if(mysqli_num_rows($result_s)==1){
            $row_s = mysqli_fetch_assoc($result_s);
            $_SESSION['department_code'] = $row_s['department_id'];
        }
        mysqli_stmt_close($stmt);
    }
//end department session

// Check if user is active
$sql_active = "SELECT `user_active` FROM `user` WHERE `user_name` = ?";
$stmt = mysqli_prepare($con, $sql_active);
mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$result_active = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($result_active) == 1) {
    $row_active = mysqli_fetch_assoc($result_active);
    if($row_active['user_active'] == 1) {
        // Redirect based on role
        if (isset($_SESSION['user_type']) && strtoupper((string)$_SESSION['user_type']) === 'MA4') {
            header('Location: ' . (defined('APP_BASE') ? APP_BASE : '') . '/finance/CollectPayment.php');
        } elseif (isset($_SESSION['user_table']) && $_SESSION['user_table'] === 'student') {
            header('Location: ' . (defined('APP_BASE') ? APP_BASE : '') . '/student/StudentDashboard.php');
        } elseif (isset($_SESSION['user_type']) && in_array(strtoupper((string)$_SESSION['user_type']), ['HOD','IN1','IN2','IN3'], true)) {
            header('Location: ' . (defined('APP_BASE') ? APP_BASE : '') . '/hod/Dashboard.php');
        } else {
            header('Location: ' . (defined('APP_BASE') ? APP_BASE : '') . '/dashboard/');
        }
        exit();
    } else {
        $msg = 'Your account is inactive. Please contact the administrator.';
    }
}
mysqli_stmt_close($stmt);

    }
  }
}


//-----------------------------------------------------------------------------------------------

// SIGNIN WITH SESSION AND COOKIE
$msg = null;
if (isset($_POST['SignIn']) && !empty($_POST['username']) && !empty($_POST['password'])) {
    $username = trim(htmlspecialchars($_POST['username']));
    $password = $_POST['password'];
    
    // Hash the password using SHA-256
    $password_hash = hash('sha256', $password);
    
    // Prepare and execute the query
    $sql = "SELECT * FROM `user` WHERE `user_name` = ? LIMIT 1";
    $stmt = mysqli_prepare($con, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) == 1) {
            $row = mysqli_fetch_assoc($result);
            
            // Verify password using SHA-256
            if ($password_hash === $row['user_password_hash']) {
                // Password is correct
                $_SESSION['user_name'] = $row['user_name'];
                $_SESSION['user_table'] = $row['user_table'];
                $_SESSION['user_type'] = $row['staff_position_type_id'];
                
                // Handle remember me
                if (!empty($_POST['rememberme'])) {
                    $random_token = bin2hex(random_bytes(32));
                    $cookie_value = $row['user_name'] . ':' . $random_token;
                    $cookie_hash = hash('sha256', $cookie_value . COOKIE_SECRET_KEY);
                    $cookie_string = $cookie_value . ':' . $cookie_hash;
                    
                    // Update database with new token
                    $update_sql = "UPDATE `user` SET `user_remember_me_token` = ? WHERE `user_name` = ?";
                    $update_stmt = mysqli_prepare($con, $update_sql);
                    mysqli_stmt_bind_param($update_stmt, "ss", $random_token, $row['user_name']);
                    mysqli_stmt_execute($update_stmt);
                    
                    // Set cookie (no explicit domain)
                    setcookie('rememberme', $cookie_string, time() + COOKIE_RUNTIME, "/", "", false, true);
                }
                
                // Check if user is active
                if ($row['user_active'] == 1) {
                    // Set department session data if needed
                    if ($row['user_table'] == 'staff') {
                        $dept_sql = "SELECT `department_id` FROM `staff` WHERE `staff_id` = ?";
                        $dept_stmt = mysqli_prepare($con, $dept_sql);
                        mysqli_stmt_bind_param($dept_stmt, "s", $username);
                        mysqli_stmt_execute($dept_stmt);
                        $dept_result = mysqli_stmt_get_result($dept_stmt);
                        if ($dept_row = mysqli_fetch_assoc($dept_result)) {
                            $_SESSION['department_code'] = $dept_row['department_id'];
                        }
                        mysqli_stmt_close($dept_stmt);
                    }
                    
                    // Redirect based on role
                    if (isset($_SESSION['user_type']) && strtoupper((string)$_SESSION['user_type']) === 'MA4') {
                        header('Location: ' . (defined('APP_BASE') ? APP_BASE : '') . '/finance/CollectPayment.php');
                    } elseif ($row['user_table'] === 'student') {
                        header('Location: ' . (defined('APP_BASE') ? APP_BASE : '') . '/student/StudentDashboard.php');
                    } elseif (isset($_SESSION['user_type']) && in_array(strtoupper((string)$_SESSION['user_type']), ['HOD','IN3'], true)) {
                        header('Location: ' . (defined('APP_BASE') ? APP_BASE : '') . '/hod/Dashboard.php');
                    } else {
                        header('Location: ' . (defined('APP_BASE') ? APP_BASE : '') . '/dashboard/');
                    }
                    exit();
                } else {
                    $msg = 'Your account is not active. Please contact the administrator.';
                }
            } else {
                // Wrong password
                $msg = 'Invalid username or password';
            }
        } else {
            // User not found
            $msg = 'Invalid username or password';
        }
        
        mysqli_stmt_close($stmt);
    } else {
        // Database error
        $msg = 'System error. Please try again later.';
        error_log("Database error: " . mysqli_error($con));
    }
}
?>

<!-- SignOut -->
<?php
if (isset($_GET['signout'])) {
  // delete remember me cookie (no explicit domain)
  setcookie('rememberme', '', time() - 3600, '/', '', false, true);

  // clear all session data and destroy session
  if (session_status() === PHP_SESSION_NONE) {
      session_start();
  }
  $_SESSION = array();
  if (ini_get('session.use_cookies')) {
      $params = session_get_cookie_params();
      // clear session cookie without explicit domain
      setcookie(session_name(), '', time() - 42000, $params['path'], '', $params['secure'], $params['httponly']);
  }
  session_destroy();
  session_write_close();
  session_regenerate_id(true);

  // redirect to sign-in page (index)
  header('Location: index');
  exit();

}
?>

<!doctype html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <?php $__base = (defined('APP_BASE') ? APP_BASE : ''); if ($__base !== '' && substr($__base,-1) !== '/') { $__base .= '/'; } ?>
    <base href="<?php echo $__base === '' ? '/' : $__base; ?>">
    <link rel="shortcut icon" href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/img/favicon.ico" type="image/x-icon">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/css/signin.css">
    <link href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.9/dist/css/bootstrap-select.min.css">
    <link href="https://use.fontawesome.com/releases/v5.0.6/css/all.css" rel="stylesheet">
    <title><?php echo $title; ?></title>
    <style>
      /* Desktop-only left side visual */
      @media (min-width: 768px) {
        :root{
          --nvq-primary:#0d6efd;        /* brand blue */
          --nvq-primary-soft:rgba(13,110,253,.15);
          --nvq-deep:#0b2740;
        }
        .bg-image {
          position: relative;
          overflow: hidden;
          /* Animated gradient background */
          background: linear-gradient(135deg, #e9f2ff, #cfe0ff, #d7fff1, #fff3d6);
          background-size: 300% 300%;
          animation: bgGradient 18s ease infinite;
        }
        /* Optional looping background video */
        .bg-image .bg-video{
          position:absolute; inset:0; width:100%; height:100%; object-fit:cover; opacity:.35; filter: grayscale(100%) contrast(1.05);
        }
        /* Optional students hero image (black & white dress code). Place file at /img/hero_students_bw.jpg */
        .bg-image .bg-photo {
          position: absolute; inset: 0; background: url('<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/img/hero_students_bw.jpg') center/cover no-repeat;
          filter: grayscale(100%) contrast(1.05);
          opacity: .28; /* keep subtle so text & animations readable */
          animation: photoDrift 30s ease-in-out infinite alternate;
        }
        @keyframes photoDrift { 0%{transform: scale(1) translateY(0);} 100%{transform: scale(1.05) translateY(-2%);} }
        .bg-image:before {
          content: "";
          position: absolute; inset: 0;
        
          opacity: 0.10; /* watermark */
        }
        /* soft floating particles */
        .bg-image:after {
          content: ""; position: absolute; inset: 0; pointer-events: none;
          background-image: radial-gradient(rgba(13,110,253,.08) 2px, transparent 2px), radial-gradient(rgba(25,135,84,.06) 2px, transparent 2px);
          background-size: 36px 36px, 48px 48px;
          background-position: 0 0, 18px 18px;
          animation: floatDots 20s linear infinite;
        }
        @keyframes bgGradient {
          0% { background-position: 0% 50%; }
          50% { background-position: 100% 50%; }
          100% { background-position: 0% 50%; }
        }
        @keyframes floatDots {
          0% { transform: translateY(0); }
          50% { transform: translateY(-8px); }
          100% { transform: translateY(0); }
        }

        .nvq-path {
          position: absolute; left: 10%; right: 10%; bottom: 10%; top: 25%;
          display: flex; flex-direction: column; justify-content: space-between;
          pointer-events: none;
        }
        /* Flowing vertical line */
        .nvq-line {
          position: absolute; left: calc(10% + 7px); top: 25%; bottom: 10%; width: 3px;
          background: linear-gradient(180deg, rgba(13,110,253,0.9), rgba(13,110,253,0.3), rgba(13,110,253,0.9));
          background-size: 100% 200%;
          animation: flowLine 5s linear infinite;
          filter: drop-shadow(0 0 6px rgba(13,110,253,.25));
        }
        @keyframes flowLine {
          0% { background-position: 0 0; }
          100% { background-position: 0 200%; }
        }
        /* Moving indicator */
        .nvq-indicator {
          position: absolute; left: calc(10% - 1px); top: 25%; width: 18px; height: 18px; border-radius: 50%;
          border: 2px solid var(--nvq-primary); background: #fff;
          box-shadow: 0 0 0 6px var(--nvq-primary-soft), 0 2px 6px rgba(0,0,0,.08);
          animation: nvqRun 9s cubic-bezier(.65,.01,.24,1) infinite;
        }
        @keyframes nvqRun {
          0%   { transform: translate(0, 0%); }
          16.66%  { transform: translate(0, 16.66%); }
          33.33%  { transform: translate(0, 33.33%); }
          50%  { transform: translate(0, 50%); }
          66.66%  { transform: translate(0, 66.66%); }
          83.33%  { transform: translate(0, 83.33%); }
          100% { transform: translate(0, 100%); }
        }
        /* Steps */
        .nvq-step { display: flex; align-items: center; color: var(--nvq-deep); opacity: 0; transform: translateX(-10px); }
        .nvq-step .dot {
          width: 15px; height: 15px; border-radius: 50%; background: var(--nvq-primary); margin-right: 12px; position: relative;
          box-shadow: 0 0 0 0 rgba(13,110,253,.35);
          animation: pulseDot 2.4s ease-out infinite;
        }
        .nvq-step .dot:after {
          content: ""; position: absolute; inset: -6px; border-radius: 50%;
          box-shadow: 0 0 0 0 rgba(13,110,253,.25);
          animation: ripple 2.4s ease-out infinite;
        }
        .nvq-step .label { font-weight: 700; letter-spacing: .2px; text-shadow: 0 1px 0 rgba(255,255,255,.4); }
        /* Sequential highlight per step for a continuous feel */
        .nvq-step .label, .nvq-step .dot { transition: transform .4s ease, color .4s ease, background .4s ease; }
        .nvq-step:nth-child(1) .label { animation: labelGlow 9s ease-in-out infinite; }
        .nvq-step:nth-child(2) .label { animation: labelGlow 9s ease-in-out 3s infinite; }
        .nvq-step:nth-child(3) .label { animation: labelGlow 9s ease-in-out 6s infinite; }
        .nvq-step:nth-child(1) .dot   { animation: pulseDot 2.4s ease-out 0s infinite, dotGlow 9s ease-in-out 0s infinite; }
        .nvq-step:nth-child(2) .dot   { animation: pulseDot 2.4s ease-out .2s infinite, dotGlow 9s ease-in-out 3s infinite; }
        .nvq-step:nth-child(3) .dot   { animation: pulseDot 2.4s ease-out .4s infinite, dotGlow 9s ease-in-out 6s infinite; }
        @keyframes labelGlow { 0%,80%,100%{opacity:.75; transform:none;} 10%,30%{opacity:1; transform:translateX(2px);} }
        @keyframes dotGlow { 0%,80%,100%{filter:none;} 10%,30%{filter: drop-shadow(0 0 8px rgba(13,110,253,.45));} }
        @keyframes pulseDot { 0%{box-shadow:0 0 0 0 rgba(13,110,253,.35);} 70%{box-shadow:0 0 0 10px rgba(13,110,253,0);} 100%{box-shadow:0 0 0 0 rgba(13,110,253,0);} }
        @keyframes ripple   { 0%{box-shadow:0 0 0 0 rgba(13,110,253,.25);} 70%{box-shadow:0 0 0 14px rgba(13,110,253,0);} 100%{box-shadow:0 0 0 0 rgba(13,110,253,0);} }
        .nvq-step:nth-child(1){ animation: stepIn .8s ease .2s forwards; }
        .nvq-step:nth-child(2){ animation: stepIn .8s ease .6s forwards; }
        .nvq-step:nth-child(3){ animation: stepIn .8s ease 1.0s forwards; }
        @keyframes stepIn { to { opacity: 1; transform: translateX(0); } }

        /* SLGTI animated badge */
        .slgti-badge { position: absolute; right: 7%; top: 8%; color: var(--nvq-deep); font-weight: 700; letter-spacing: .8px; opacity: .9; text-shadow: 0 1px 0 rgba(255,255,255,.5); }
        .slgti-badge .dot { display:inline-block; width:8px; height:8px; border-radius:50%; background:var(--nvq-primary); box-shadow:0 0 0 0 rgba(13,110,253,.4); animation: pulseDot 2.4s ease-out infinite; vertical-align: middle; margin-right: 6px; }
      }
    </style>
</head>

<body>

    <div class="container-fluid">
        <div class="row no-gutter">
            <!-- The image half -->
            <div class="col-md-6 d-none d-md-flex bg-image">
              <video class="bg-video" autoplay muted loop playsinline>
                <source src="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/img/Nvq_level_04.mp4" type="video/mp4">
              </video>
              <div class="bg-photo"></div>
              <div class="nvq-line"></div>
              <div class="nvq-indicator"></div>
              
              <div class="nvq-path">
                <div class="nvq-step">
                  <div class="dot"></div>
                  <div class="label">NVQ Level 4 · Intermediate</div>
                </div>
                <div class="nvq-step">
                  <div class="dot"></div>
                  <div class="label">NVQ Level 5 · Diploma</div>
                </div>
                <div class="nvq-step">
                  <div class="dot"></div>
                  <div class="label">NVQ Level 6 · Higher Diploma</div>
                </div>
              </div>
            </div>


            <!-- The content half -->
            <div class="col-md-6 bg-light">
                <div class="login d-flex align-items-center py-5">

                    <!-- Demo content-->
                    <div class="container">
                        <div class="row">
                            <div class="col-lg-10 col-xl-7 mx-auto">
                                <div class="text-center mb-3">
                                  <img src="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/img/SLGTI_logo.png" alt="SLGTI" style="max-height:72px;width:auto;" class="mb-2">
                     
                                  <p class="text-muted small mb-1">Sri Lanka-German Training Institute</p>
                                  <p class="text-muted">Management Information System</p>
                                </div>
                                <form  method="post">
                                    <?php
                                    if (!empty($msg))
                                    echo '<div class="alert alert-danger rounded-pill border-0 shadow-sm px-4" >' . $msg . '</div>';
                                    ?>
                                    <div class="form-group mb-3">
                                        <input id="inputEmail" type="text" name="username" placeholder="Username" required=""
                                            autofocus="" class="form-control rounded-pill border-0 shadow-sm px-4">
                                    </div>
                                    <div class="form-group mb-3">
                                        <input id="inputPassword" type="password" name="password" placeholder="Password" required=""
                                            class="form-control rounded-pill border-0 shadow-sm px-4 text-primary">
                                    </div>
                                    <div class="custom-control custom-checkbox mb-3">
                                        <input id="customCheck1" name="rememberme" value="yes" type="checkbox" checked class="custom-control-input">
                                        <label for="customCheck1" class="custom-control-label">Remember password</label>
                                    </div>
                                    <button type="submit" name="SignIn"
                                        class="btn btn-primary btn-block text-uppercase mb-2 rounded-pill shadow-sm">Sign
                                        in</button>

                                    <div class="mt-4">
                                      <a href="search_student.php" class="btn btn-outline-primary btn-block rounded-pill py-2">
                                        <i class="fas fa-search"></i> Search Student (Public)
                                      </a>
                                    </div>

                                    <div class="text-center d-flex justify-content-between mt-4">
                                      <p class="small">All Rights Reserved. Designed and Developed by Department of Information and Communication Technology, <a href="http://slgti.com" class="font-italic text-muted">Sri Lanka-German Training Institute.</a></p>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div><!-- End -->

                </div>
            </div><!-- End -->

        </div>
    </div>

    <?php if (false): ?>
    <?php
    // Build course-wise students list (exclude courses with zero students)
    $courseRows = [];
    $sqlCourses = "
      SELECT 
        d.department_name,
        c.course_id,
        c.course_name,
        COUNT(DISTINCT s.student_id) AS total
      FROM course c
      LEFT JOIN department d ON d.department_id = c.department_id
      LEFT JOIN student_enroll e ON e.course_id = c.course_id 
        AND e.student_enroll_status IN ('Following','Active')
      LEFT JOIN student s ON s.student_id = e.student_id AND COALESCE(s.student_status,'') <> 'Inactive'
      WHERE LOWER(TRIM(d.department_name)) NOT IN ('admin','administration')
      GROUP BY d.department_name, c.course_id, c.course_name
      HAVING total > 0
      ORDER BY d.department_name ASC, c.course_name ASC";
    if ($rs = mysqli_query($con, $sqlCourses)) {
      while ($r = mysqli_fetch_assoc($rs)) { $courseRows[] = $r; }
      mysqli_free_result($rs);
    }
    ?>
    <div class="container mt-4 mb-5">
      <div class="row">
        <div class="col-12">
          <div class="card shadow-sm">
            <div class="card-header bg-white">
              <strong>Course-wise Students</strong>
              <small class="text-muted d-block">Only courses with at least one active student are shown</small>
            </div>
            <div class="card-body p-0">
              <?php if (empty($courseRows)): ?>
                <div class="p-3 text-center text-muted small">No course-wise student data available.</div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table table-sm table-striped mb-0">
                    <thead class="thead-light">
                      <tr>
                        <th style="width:40%;">Department</th>
                        <th style="width:50%;">Course</th>
                        <th class="text-right" style="width:10%;">Students</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($courseRows as $row): ?>
                        <tr>
                          <td><?php echo htmlspecialchars($row['department_name'] ?? ''); ?></td>
                          <td><?php echo htmlspecialchars($row['course_name'] ?? ''); ?></td>
                          <td class="text-right font-weight-bold"><?php echo (int)($row['total'] ?? 0); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

</body>

</html>