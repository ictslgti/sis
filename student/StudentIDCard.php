<?php
require_once __DIR__ . '/../config.php';
$__base = (defined('APP_BASE') ? APP_BASE : '');
if ($__base !== '' && substr($__base, -1) !== '/') {
  $__base .= '/';
}
$id = isset($_GET['id']) ? trim($_GET['id']) : '';
$student = null;
$enroll = null;
$err = '';
if ($id !== '') {
  $sql = "SELECT s.student_id, s.student_fullname, s.student_ininame, s.student_nic, s.student_status, s.student_profile_img FROM student s WHERE s.student_id = ? LIMIT 1";
  if ($st = mysqli_prepare($con, $sql)) {
    mysqli_stmt_bind_param($st, 's', $id);
    mysqli_stmt_execute($st);
    mysqli_stmt_store_result($st);
    mysqli_stmt_bind_result($st, $sid, $sfull, $sini, $snic, $sstatus, $simg);
    if (mysqli_stmt_fetch($st)) {
      $student = [
        'student_id' => $sid,
        'student_fullname' => $sfull,
        'student_ininame' => $sini,
        'student_nic' => $snic,
        'student_status' => $sstatus,
        'student_profile_img' => $simg,
      ];
    }
    mysqli_stmt_close($st);
  }
  if ($student) {
    $sql2 = "SELECT d.department_name, c.course_name, e.student_enroll_status, e.student_enroll_date FROM student_enroll e LEFT JOIN course c ON c.course_id = e.course_id LEFT JOIN department d ON d.department_id = c.department_id WHERE e.student_id = ? ORDER BY e.student_enroll_date DESC LIMIT 1";
    if ($st2 = mysqli_prepare($con, $sql2)) {
      mysqli_stmt_bind_param($st2, 's', $id);
      mysqli_stmt_execute($st2);
      mysqli_stmt_store_result($st2);
      mysqli_stmt_bind_result($st2, $dname, $cname, $estatus, $edate);
      if (mysqli_stmt_fetch($st2)) {
        $enroll = [
          'department_name' => $dname,
          'course_name' => $cname,
          'student_enroll_status' => $estatus,
          'student_enroll_date' => $edate,
        ];
      }
      mysqli_stmt_close($st2);
    }
  }
} else {
  $err = 'Missing student id.';
}
$photoUrl = $__base . 'student/get_student_image.php?Sid=' . urlencode($id);
$principalSig = '';
// Prefer explicit principal signature path as requested
$pref = __DIR__ . '/../img/principal.png';
if (is_file($pref)) {
  $principalSig = $__base . 'img/principal.png';
} else {
  $try = [
    __DIR__ . '/../img/principal_signature.png',
    __DIR__ . '/../img/principal-signature.png',
    __DIR__ . '/../img/signature_principal.png'
  ];
  foreach ($try as $p) {
    if (is_file($p)) {
      $principalSig = $__base . 'img/' . basename($p);
      break;
    }
  }
}
$ministryLogo = '';
$tryMin = [
  __DIR__ . '/../img/ministry_logo.png',
  __DIR__ . '/../img/ministry-logo.png',
  __DIR__ . '/../img/SL_ministry_logo.png',
  __DIR__ . '/../img/moe_logo.png',
];
foreach ($tryMin as $p) {
  if (is_file($p)) {
    $ministryLogo = $__base . 'img/' . basename($p);
    break;
  }
}
$title = 'Student ID Card | SLGTI SIS';
// Prepare enroll and expiry dates for back side
$enrollDateFmt = '';
$expireDateFmt = '';
if (!empty($enroll) && !empty($enroll['student_enroll_date'])) {
  try {
    $dtEnroll = new DateTime($enroll['student_enroll_date']);
    $enrollDateFmt = $dtEnroll->format('d/m/Y');
    $dtExpire = clone $dtEnroll;
    $dtExpire->add(new DateInterval('P3Y')); // 2.5 years
    $expireDateFmt = $dtExpire->format('d/m/Y');
  } catch (Exception $e) { /* ignore parse errors */
  }
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <base href="<?php echo $__base === '' ? '/' : $__base; ?>">
  <title><?php echo htmlspecialchars($title); ?></title>
  <link rel="stylesheet" href="<?php echo $__base; ?>css/bootstrap.min.css">
  <link rel="stylesheet" href="<?php echo $__base; ?>css/all.min.css">
  <style>
    :root {
      --black: #111;
      --red: #e01e37;
      --gold: #ffce00;
      --card-w: 85.6mm;
      --card-h: 54mm;
      --fs-base: 8px;
      --fs-head: 18px;
      --fs-backhead: 14px;
      --fs-name: 12px;
      --fs-label: 10px;
      --fs-val: 10px;
      --qr-size: 32mm;
      --photo-w: 18mm;
      --photo-h: 23mm;
    }

    body {
      background: #f4f5f7;
    }

    .wrapper {
      max-width: 100%;
      padding: 0 16px 16px;
    }

    .grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 16px;
    }

    @media(min-width:992px) {
      .grid {
        grid-template-columns: repeat(2, var(--card-w));
        justify-content: center;
      }
    }

    .card-iso {
      width: var(--card-w);
      height: var(--card-h);
      border-radius: 6mm;
      overflow: hidden;
      box-shadow: 0 6px 20px rgba(0, 0, 0, .15);
      background: #fff;
      position: relative;
      border: 1px solid #e9ecef;
    }

    .card-iso,
    .card-iso * {
      font-size: 8px !important;
    }

    .card-iso .inst-name,
    .card-iso .heading,
    .card-iso .name,
    .card-iso .chip,
    .card-iso .label,
    .card-iso .val,
    .card-iso .smalltxt {
      margin-top: 0 !important;
      line-height: 1.1;
    }

    .card-iso .rowline {
      margin-top: 0 !important;
    }

    .card-iso .back-head {
      margin-top: 0 !important;
    }

    .card-iso .fields {
      line-height: 1.1;
    }

    .card-iso .rowline {
      gap: 2mm;
    }

    .face {
      position: relative;
      width: 100%;
      height: 100%;
      padding: 10px 6.5mm 6.5mm 6.5mm;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      box-sizing: border-box;
    }

    .topline {
      height: 1.8mm;
      width: 100%;
      margin-left: 0;
      margin-bottom:-1.5mm;
      background: linear-gradient(90deg, var(--black) 0 34%, var(--red) 34% 67%, var(--gold) 67% 100%);
      border-radius: 2mm;
    }

    .topline1 {
      height: 2mm;
      width: 30mm;
      background: linear-gradient(90deg, var(--black) 0 34%, var(--red) 34% 67%, var(--gold) 67% 100%);
      border-radius: 2mm;
    }

    .brand-line {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      margin-top: 0;
      min-height: 6.2mm;
    }

    .brand-right {
      display: flex;
      align-items: center;
      gap: 2.2mm;
      margin-top: 0;
    }

    .brand-right img {
      height: 6.5mm;
      width: auto;
      object-fit: contain;
    }

    .slgti-txt {
      font-weight: 700;
      font-size: 7.6pt;
      white-space: nowrap;
      line-height: 1;
    }

    .brand-sm img {
      height: 10mm;
    }

    .brand-sm .ti {
      font-size: 9pt;
      font-weight: 700;
    }

    .heading {
      font-weight: 700;
      font-size: 9px !important;
      margin-top: .8mm;
      text-align: center;
    }

    .id-title {
      text-align: center;
      font-weight: 700;
      letter-spacing: .5px;
      text-transform: uppercase;
      font-size: 12px !important;
      color: var(--black);
    }

    .inst-name {
      text-align: center;
      font-weight: 700;
      font-size: 16px !important;
      color: var(--black);
      letter-spacing: .2px;
      margin-top: -6px;
      white-space: nowrap;
    }

    .rowline {
      display: flex;
      gap: 5mm;
      align-items: flex-start;
    }

    .qrcol {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-start;
    }

    .photo {
      width: var(--photo-w);
      height: var(--photo-h);
      border-radius: 2mm;
      overflow: hidden;
      border: 2px solid rgba(0, 0, 0, .08);
      background: #fafafa;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .06);
    }

    .photo img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .fields {
      flex: 1;
      font-size: 9pt;
      min-width: 0;
      line-height: 2;
      text-align: left !important;
    }

    .name {
      font-size: 12px !important;
      font-weight: 700;
      color: #111;
      margin-top: 1mm;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .chip {
      display: inline-block;
      border-radius: 10px;
      font-size: 7.5pt;
      font-weight: 600;
      color: #222;
      margin-top: 1mm;
      max-width: 44mm;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      vertical-align: middle;
    }

    .label {
      font-weight: 600;
      color: #6c757d;
      margin-top: 2mm;
      font-size: 10px !important;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      text-align: left !important;
    }

    .val {
      font-weight: 600;
      color: #111;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 46mm;
      text-align: left !important;
      font-size: 10px !important;
    }

    /* Show full course name by allowing wrap on course value only */
    .val.course-name {
      white-space: normal;
      overflow: visible;
      text-overflow: unset;
      max-width: none;
      line-height: 1.2;
    }

    .footer {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      gap: 8mm;
      margin-top: -2mm;
    }

    .bar-bottom {
      position: absolute;
      left: 7mm;
      right: 7mm;
      bottom: 5mm;
      height: 2mm;
      background: linear-gradient(90deg, var(--gold), var(--red), var(--black));
      border-radius: 1mm;
      opacity: .25;
    }

    .sig {
      text-align: center;
      min-width: 38mm;
    }

    .sig img {
      max-height: 11mm;
      width: auto;
      display: block;
      margin: 0 auto;
    }

    .sig .cap {
      border-top: 1px solid #222;
      margin-top: 2mm;
      font-size: 8pt;
      font-weight: 600;
    }

    /* Validity layout helpers */
    .validity {
      margin-top: 3mm;
    }

    .validity-line {
      line-height: 1.2;
    }

    .qrbox {
      width: var(--qr-size);
      height: var(--qr-size);
      border: 1px dashed rgba(0, 0, 0, .18);
      border-radius: 2mm;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #fff;
      overflow: hidden;
    }

    .qrbox #qr img,
    .qrbox #qr canvas {
      width: 100% !important;
      height: 100% !important;
      display: block;
    }

    .notice {
      font-size: 8pt;
      color: #495057;
    }

    .badge-status {
      position: absolute;
      top: 7mm;
      right: 7mm;
      font-size: 8pt;
    }

    .back-head {
      font-weight: 700;
      font-size: 14px !important;
      margin-top: 4mm;
    }

    .smalltxt {
      font-size: 8px !important;
    }

    .qrurl {
      word-break: break-all;
      line-height: 1.2;
    }

    .print-actions {
      text-align: center;
      margin: 12px 0 4px;
    }

    /* Back-side tweaks to ensure neat fit */
    .card-iso.back .face {
      padding: 10px 7mm 7mm;
    }

    .card-iso.back .rowline {
      gap: 5mm;
    }

    .card-iso.back .qrcol {
      flex: 0 0 41.6667%;
      max-width: 41.6667%;
    }

    .card-iso.back .fields {
      flex: 0 0 58.3333%;
      max-width: 58.3333%;
    }

    .card-iso.back .sig {
      min-width: auto;
      margin-top: auto;
      padding-top: 3mm;
      display: flex;
      flex-direction: column;
      align-items: flex-end;
    }

    .card-iso.back .sig img {
      margin-left: auto;
      margin-right: 0;
    }

    .card-iso.back .fields {
      font-size: 8.5pt;
      display: flex;
      flex-direction: column;
      line-height: 1.3;
    }

    .card-iso.back .smalltxt {
      font-size: 8pt;
    }

    .card-iso.back .qrbox {
      width: var(--qr-size);
      height: var(--qr-size);
    }

    .card-iso:not(.back) .face>div:first-child {
      margin-top: 0;
    }

    .card-iso.back .face>div:first-child {
      margin-top: 0;
    }

    .card-iso.back .sig {
      min-width: auto;
    }

    .card-iso.back .sig .cap {
      width: 36mm;
      margin-left: auto;
      margin-right: 0;
    }

    @media print {
      body {
        background: #fff;
      }

      .print-actions {
        display: none !important;
      }

      .ctrls {
        display: none !important;
      }

      .wrapper {
        padding: 0;
      }

      .grid {
        grid-template-columns: 1fr 1fr;
        gap: 0;
        justify-content: initial;
      }

      .card-iso,
      .card-iso * {
        font-size: 8px !important;
      }

      .card-iso {
        box-shadow: none;
        margin: 0;
        page-break-inside: avoid;
      }
    }
  </style>
  <style>
    /* Font-size overrides driven by variables (fixed for title/institute) */
    .card-iso,
    .card-iso * {
      font-size: var(--fs-base) !important;
    }

    .inst-name {
      font-size: 13px !important;
    }

    .heading {
      font-size: var(--fs-head) !important;
    }

    .id-title {
      font-size: 9px !important;
    }

    .back-head {
      font-size: var(--fs-backhead) !important;
    }

    .name {
      font-size: var(--fs-name) !important;
    }

    .label {
      font-size: var(--fs-label) !important;
    }

    .val {
      font-size: var(--fs-val) !important;
    }

    .smalltxt {
      font-size: var(--fs-base) !important;
    }

    /* Controls UI */
    .ctrls {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
      justify-content: center;
      margin-top: 8px;
    }

    .ctrls label {
      margin: 0 4px 0 0;
      font-weight: 600;
    }

    .ctrls input {
      width: 56px;
      padding: 2px 4px;
    }

    .ctrls .group {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .ctrls .btn {
      padding: 4px 8px;
    }

    /* container to hold the two lines */
    


    /* paragraphs normally have margins; reset them so there's no blank line */
    .two-lines p {
      margin: 0;
      line-height: 1.1;
      text-align: center;
      color: black;
    }


    /* optional: slightly smaller gap — change to 1.0 for no gap at all */
    .two-lines p+p {
      margin-top: 0.15rem;
    }
  </style>
</head>

<body>
  <div class="wrapper">
    <div class="print-actions">
      <a href="javascript:window.print()" class="btn btn-outline-secondary btn-sm"><i class="fas fa-print"></i> Print</a>
    </div>
    <div class="ctrls">
      <div class="group"><label for="fs-name">Name</label><input id="fs-name" type="number" min="8" max="22" step="1"></div>
      <div class="group"><label for="fs-label">Label</label><input id="fs-label" type="number" min="8" max="18" step="1"></div>
      <div class="group"><label for="fs-val">Value</label><input id="fs-val" type="number" min="8" max="18" step="1"></div>
      <div class="group"><label for="qr-size">QR (mm)</label><input id="qr-size" type="number" min="20" max="40" step="1"></div>
      <div class="group"><label for="photo-w">Photo W (mm)</label><input id="photo-w" type="number" min="12" max="25" step="1"></div>
      <div class="group"><label for="photo-h">Photo H (mm)</label><input id="photo-h" type="number" min="16" max="32" step="1"></div>
      <button id="reset-fs" class="btn btn-sm btn-outline-secondary">Reset</button>
      <button id="dl-zip" class="btn btn-sm btn-success">Download ID ZIP</button>
    </div>
    <br>
    <?php if (!$student): ?>
      <div class="alert alert-warning m-0">No student found. Provide a valid id via <code>?id=</code>.</div>
    <?php else: ?>
      <div class="grid">
        <div class="card-iso">
          <div class="face">
            <div>
              
              <div class="brand-line">
                <div class="brand-right" style="margin-top:-10px; ">
                  <?php if ($ministryLogo !== ''): ?>
                    <img src="<?php echo htmlspecialchars($ministryLogo); ?>" alt="Ministry" style="margin-right:170px;">
                  <?php endif; ?>
                  <img src="<?php echo $__base; ?>img/SLGTI_logo.png" alt="SLGTI">
                </div>
              </div>
              <div style="margin-top: -3mm;">
                <div class="two-lines">
                  <div class="heading id-title" style="margin-bottom: 0.5mm;">Student Identity Card</div>
                  
                  <div class="inst-name" style="margin-bottom: 1mm;">Sri Lanka German Training Institute</div>
                  <p>Ministry of Education, Higher Education and Vocational Education</p>
                  <p>Vocational Education Division</p>
                </div>
                <div style="margin-bottom: 1mm;"></div>
                
              </div>

              <div class="rowline" style="margin-top:3mm;">
                <div class="photo"><img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="Photo"></div>
                <div class="fields">
                  <div class="name"><?php echo htmlspecialchars($student['student_fullname'] ?: $student['student_ininame']); ?></div>
                  <div class="chip">ID: <?php echo htmlspecialchars($student['student_id']); ?></div>
                  <div class="label">NIC</div>
                  <div class="val"><?php echo htmlspecialchars($student['student_nic']); ?></div>
                  <?php if ($enroll): ?>
                    <div class="label">Department</div>
                    <div class="val"><?php echo htmlspecialchars($enroll['department_name']); ?></div>
                    <div class="label">Course</div>
                    <div class="val course-name"><?php echo htmlspecialchars($enroll['course_name']); ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <div class="footer">
              
            </div>
            <div class="topline"></div>
          </div>
        </div>
        <div class="card-iso back">
          <div class="face">
            <div>
              
              <div class="back-head">Scan to Verify</div>
              <div class="rowline" style="margin-top:4mm; align-items:flex-start;">
                <div class="qrcol">
                  <div class="qrbox">
                    <div id="qr"></div>
                  </div>
                </div>
                <div class="fields" style="margin-top:-4mm;">
                  <div class="label">Instructions</div>
                  <div class="smalltxt" style="text-align: justify; margin-right: 5mm;" >This ID card belongs to SLGTI. If you find this ID card, please return it to SLGTI. Do not fold, bend, or punch the QR code. This ID card is the property of SLGTI and must be returned after completing the course</div>
                  <br>
                  <div class="label" style="margin-top:3mm;">Validity</div>

                  <div class="smalltxt">
                    Enroll Date: <?php echo htmlspecialchars($enrollDateFmt); ?> <br>Expire Date: <?php echo htmlspecialchars($expireDateFmt); ?>
                  </div>
                  <div class="sig" style="margin-top:-2mm; margin-right:10mm;">
                    <?php if ($principalSig !== ''): ?>
                      <img src="<?php echo htmlspecialchars($principalSig); ?>" style="width: 50%; margin-right: 5mm;" alt="Principal Signature">
                    <?php else: ?>
                      <div style="height:8mm"></div>
                    <?php endif; ?>
                    <div class="cap">Principal</div>
                  </div>
                </div>
              </div>
            </div>

            <div class="bar-bottom"></div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
  <script src="<?php echo $__base; ?>js/jquery-3.3.1.slim.min.js"></script>
  <script src="<?php echo $__base; ?>js/bootstrap.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/file-saver@2.0.5/dist/FileSaver.min.js"></script>
  <script>
    (function() {
      function renderQR() {
        var qre = document.getElementById('qr');
        if (!qre) return;
        qre.innerHTML = '';
        var box = qre.parentElement;
        var sz = Math.max(80, Math.min(box.clientWidth, box.clientHeight));
        var url = 'https://sis.slgti.ac.lk/search_student.php?mode=id&q=<?php echo rawurlencode($student ? $student['student_id'] : ''); ?>';
        if (url) {
          new QRCode(qre, {
            text: url,
            width: sz,
            height: sz,
            correctLevel: QRCode.CorrectLevel.M
          });
        }
      }
      window.renderQR = renderQR;
      renderQR();
      window.addEventListener('resize', renderQR);
    })();
  </script>
  <script>
    (function() {
      function getVar(name, fallback) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return v || fallback;
      }

      function setVar(name, value) {
        document.documentElement.style.setProperty(name, value);
      }

      function parseUnit(val, unit) {
        if (!val) return '';
        val = String(val).trim();
        return val.endsWith(unit) ? parseFloat(val) : parseFloat(val);
      }

      function initInputs() {
        document.getElementById('fs-name').value = parseInt(getVar('--fs-name', '12px'));
        document.getElementById('fs-label').value = parseInt(getVar('--fs-label', '10px'));
        document.getElementById('fs-val').value = parseInt(getVar('--fs-val', '10px'));
        document.getElementById('qr-size').value = parseUnit(getVar('--qr-size', '32mm'), 'mm');
        document.getElementById('photo-w').value = parseUnit(getVar('--photo-w', '18mm'), 'mm');
        document.getElementById('photo-h').value = parseUnit(getVar('--photo-h', '23mm'), 'mm');
      }

      function bindAuto() {
        // Font-size inputs (px)
        ['fs-name', 'fs-label', 'fs-val'].forEach(function(id) {
          var el = document.getElementById(id);
          if (!el) return;
          el.addEventListener('input', function() {
            setVar('--' + id.replace('fs-', 'fs-'), el.value + 'px');
          });
        });
        // Size inputs (mm)
        var qrs = document.getElementById('qr-size');
        qrs.addEventListener('input', function() {
          setVar('--qr-size', qrs.value + 'mm');
          if (window.renderQR) window.renderQR();
        });
        var pw = document.getElementById('photo-w');
        pw.addEventListener('input', function() {
          setVar('--photo-w', pw.value + 'mm');
        });
        var ph = document.getElementById('photo-h');
        ph.addEventListener('input', function() {
          setVar('--photo-h', ph.value + 'mm');
        });
      }

      function reset() {
        setVar('--fs-base', '8px');
        setVar('--fs-head', '18px');
        setVar('--fs-backhead', '14px');
        setVar('--fs-name', '12px');
        setVar('--fs-label', '10px');
        setVar('--fs-val', '10px');
        setVar('--qr-size', '32mm');
        setVar('--photo-w', '18mm');
        setVar('--photo-h', '23mm');
        initInputs();
        if (window.renderQR) window.renderQR();
      }

      function dataUrl(canvas) {
        return canvas.toDataURL('image/png');
      }
      async function renderCard(node) {
        return await html2canvas(node, {
          backgroundColor: '#ffffff',
          scale: 2,
          useCORS: true
        });
      }
      async function downloadZip() {
        var front = document.querySelector('.grid .card-iso:not(.back)');
        var back = document.querySelector('.grid .card-iso.back');
        if (!front || !back) return;
        var id = <?php echo json_encode($student ? $student['student_id'] : 'ID'); ?>;
        var zip = new JSZip();
        var c1 = await renderCard(front);
        var c2 = await renderCard(back);
        var b64_1 = dataUrl(c1).split(',')[1];
        var b64_2 = dataUrl(c2).split(',')[1];
        zip.file('ID_' + id + '_front.png', b64_1, {
          base64: true
        });
        zip.file('ID_' + id + '_back.png', b64_2, {
          base64: true
        });
        var blob = await zip.generateAsync({
          type: 'blob'
        });
        saveAs(blob, 'ID_' + id + '.zip');
      }
      initInputs();
      bindAuto();
      document.getElementById('reset-fs').addEventListener('click', reset);
      document.getElementById('dl-zip').addEventListener('click', function() {
        downloadZip();
      });
    })();
  </script>
</body>

</html>