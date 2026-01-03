<!-- BLOCK#1 START DON'T CHANGE THE ORDER -->
<?php
session_start();
$title = "Devices | Network Settings";
include_once("../config.php");
include_once("../head.php");
include_once("../menu.php");
?>
<!--END DON'T CHANGE THE ORDER-->

<!--BLOCK#2 START YOUR CODE HERE -->
<?php
// Simple in-session storage (replace with DB if needed)
if (!isset($_SESSION['device_settings'])) {
  $_SESSION['device_settings'] = [
    'name' => '',
    'protocol' => 'http',
    'ip' => '',
    'port' => '80',
    'username' => '',
    'password' => '',
    'hik_connect_url' => 'https://www.hik-connect.com/'
  ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $ds = &$_SESSION['device_settings'];
  $ds['name'] = trim($_POST['name'] ?? '');
  $ds['protocol'] = in_array($_POST['protocol'] ?? 'http', ['http','https']) ? $_POST['protocol'] : 'http';
  $ds['ip'] = trim($_POST['ip'] ?? '');
  $ds['port'] = trim($_POST['port'] ?? '');
  $ds['username'] = trim($_POST['username'] ?? '');
  $ds['password'] = trim($_POST['password'] ?? '');
  $ds['device_sn'] = trim($_POST['device_sn'] ?? '');
  $ds['verify_code'] = trim($_POST['verify_code'] ?? '');
  $ds['hik_connect_url'] = trim($_POST['hik_connect_url'] ?? 'https://www.hik-connect.com/');
  echo '<div class="alert alert-success mx-3 mt-3">Settings saved in session. You can now open the device or Hik-Connect.</div>';
}

$settings = $_SESSION['device_settings'];
$deviceUrl = '';
if (!empty($settings['ip'])) {
  $p = $settings['protocol'] ?: 'http';
  $port = $settings['port'] ? (":" . preg_replace('/[^0-9]/','',$settings['port'])) : '';
  $deviceUrl = $p . "://" . $settings['ip'] . $port;
}
?>

<style>
  /* ============================================
     DEVICES MENU - BLUE THEME & PROPER ALIGNMENT
     ============================================ */
  
  /* Color Theme Variables */
  :root {
    --blue-primary: #2563eb;
    --blue-dark: #1e40af;
    --blue-light: #3b82f6;
    --white: #ffffff;
    --text-dark: #1e293b;
    --gray-border: #e2e8f0;
    --gray-light: #f8fafc;
  }
  
  /* Container - Proper Alignment */
  .container-fluid {
    max-width: 100%;
    width: 100%;
    margin-left: auto;
    margin-right: auto;
    padding-left: 20px;
    padding-right: 20px;
    box-sizing: border-box;
  }
  
  @media (min-width: 1400px) {
    .container-fluid {
      padding-left: 30px;
      padding-right: 30px;
    }
  }
  
  @media (min-width: 992px) and (max-width: 1399px) {
    .container-fluid {
      padding-left: 20px;
      padding-right: 20px;
    }
  }
  
  @media (min-width: 768px) and (max-width: 991.98px) {
    .container-fluid {
      padding-left: 15px;
      padding-right: 15px;
    }
  }
  
  @media (max-width: 767.98px) {
    .container-fluid {
      padding-left: 12px;
      padding-right: 12px;
    }
  }
  
  @media (max-width: 575.98px) {
    .container-fluid {
      padding-left: 10px;
      padding-right: 10px;
    }
  }
  
  /* Card - Blue Theme */
  .card {
    border: 1px solid var(--gray-border);
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    background-color: var(--white);
    color: var(--text-dark);
  }
  
  .card-header {
    background: linear-gradient(135deg, var(--blue-primary) 0%, var(--blue-dark) 100%);
    color: var(--white);
    border: none;
    border-radius: 12px 12px 0 0;
    padding: 1rem 1.5rem;
  }
  
  .card-header h5,
  .card-header .text-muted {
    color: var(--white) !important;
  }
  
  .card-header .text-muted {
    opacity: 0.9;
  }
  
  .card-body {
    color: var(--text-dark);
    background: var(--white);
    padding: 1.5rem;
  }
  
  /* Form Controls - Blue Theme */
  .form-control {
    border: 1.5px solid var(--gray-border);
    border-radius: 8px;
    background: var(--white);
    color: var(--text-dark);
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
  }
  
  .form-control:focus {
    border-color: var(--blue-primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    outline: none;
    background: var(--white);
    color: var(--text-dark);
  }
  
  label,
  .form-label {
    color: var(--text-dark);
    font-weight: 600;
    margin-bottom: 0.5rem;
  }
  
  .form-text {
    color: #64748b;
  }
  
  /* Buttons - Blue Theme */
  .btn-primary {
    background: linear-gradient(135deg, var(--blue-primary) 0%, var(--blue-dark) 100%);
    border: none;
    box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
    color: var(--white);
    border-radius: 8px;
    padding: 0.5rem 1rem;
    font-weight: 600;
  }
  
  .btn-outline-secondary,
  .btn-outline-info,
  .btn-outline-primary {
    border: 1.5px solid var(--gray-border);
    border-radius: 8px;
    padding: 0.5rem 1rem;
    color: var(--text-dark);
    background: var(--white);
  }
  
  .btn-outline-secondary:hover,
  .btn-outline-info:hover,
  .btn-outline-primary:hover {
    border-color: var(--blue-primary);
    background: var(--blue-primary);
    color: var(--white);
  }
  
  .btn-outline-secondary.disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
  
  /* Alert - Blue Theme */
  .alert-success {
    background-color: #d1fae5;
    border-color: #10b981;
    color: #065f46;
    border-radius: 8px;
  }
  
  .alert-warning {
    background-color: #fef3c7;
    border-color: #f59e0b;
    color: #92400e;
    border-radius: 8px;
  }
  
  /* Form Row Alignment */
  .form-row {
    margin-left: -7.5px;
    margin-right: -7.5px;
  }
  
  .form-row > [class*="col-"] {
    padding-left: 7.5px;
    padding-right: 7.5px;
  }
  
  /* Responsive adjustments */
  @media (max-width: 767.98px) {
    .form-row {
      margin-left: -5px;
      margin-right: -5px;
    }
    
    .form-row > [class*="col-"] {
      padding-left: 5px;
      padding-right: 5px;
    }
  }
  
  /* Gap utility for buttons */
  .gap-2 {
    gap: 0.5rem;
  }
  
  /* Iframe container */
  .border.rounded {
    border-color: var(--gray-border) !important;
  }
</style>

<div class="container-fluid mt-4">
  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
      <div>
        <h5 class="mb-0"><i class="fas fa-network-wired"></i> Network Settings</h5>
        <small class="text-muted">Configure your Access Control device and open its web UI or Hik-Connect</small>
      </div>
    </div>
    <div class="card-body">
      <form method="post" class="mb-3">
        <div class="form-row">
          <div class="form-group col-md-4">
            <label for="name">Device Name</label>
            <input type="text" class="form-control" id="name" name="name" placeholder="Front Gate Controller" value="<?php echo htmlspecialchars($settings['name']); ?>">
          </div>
          <div class="form-group col-md-2">
            <label for="protocol">Protocol</label>
            <select class="form-control" id="protocol" name="protocol">
              <option value="http" <?php echo $settings['protocol']==='http'?'selected':''; ?>>http</option>
              <option value="https" <?php echo $settings['protocol']==='https'?'selected':''; ?>>https</option>
            </select>
          </div>
          <div class="form-group col-md-3">
            <label for="ip">Device IP / Host</label>
            <input type="text" class="form-control" id="ip" name="ip" placeholder="192.168.1.100" value="<?php echo htmlspecialchars($settings['ip']); ?>">
          </div>
          <div class="form-group col-md-1">
            <label for="port">Port</label>
            <input type="text" class="form-control" id="port" name="port" placeholder="80" value="<?php echo htmlspecialchars($settings['port']); ?>">
          </div>
          <div class="form-group col-md-2">
            <label for="username">Username</label>
            <input type="text" class="form-control" id="username" name="username" placeholder="admin" value="<?php echo htmlspecialchars($settings['username']); ?>">
          </div>
          <div class="form-group col-md-2">
            <label for="password">Password</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="••••••" value="<?php echo htmlspecialchars($settings['password']); ?>">
          </div>
          <div class="form-group col-md-3">
            <label for="device_sn">Device Serial No (optional)</label>
            <input type="text" class="form-control" id="device_sn" name="device_sn" placeholder="DS-K... / SN" value="<?php echo htmlspecialchars($settings['device_sn'] ?? ''); ?>">
            <small class="form-text text-muted">For cloud-bound devices, used with Hik‑Connect account.</small>
          </div>
          <div class="form-group col-md-3">
            <label for="verify_code">Verification Code (optional)</label>
            <input type="text" class="form-control" id="verify_code" name="verify_code" placeholder="Verification code" value="<?php echo htmlspecialchars($settings['verify_code'] ?? ''); ?>">
          </div>
          <div class="form-group col-md-6">
            <label for="hik_connect_url">Hik‑Connect URL</label>
            <input type="url" class="form-control" id="hik_connect_url" name="hik_connect_url" placeholder="https://www.hik-connect.com/" value="<?php echo htmlspecialchars($settings['hik_connect_url']); ?>">
            <small class="form-text text-muted">Opens in a new tab. Many vendor sites block embedding for security.</small>
          </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Settings</button>
      </form>

      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary mr-2 mb-2 <?php echo $deviceUrl? '':'disabled'; ?>" href="<?php echo $deviceUrl ?: '#'; ?>" target="_blank" rel="noopener">
          <i class="fas fa-external-link-alt"></i> Open Device Web UI
        </a>
        <a class="btn btn-outline-info mb-2" href="<?php echo htmlspecialchars($settings['hik_connect_url']); ?>" target="_blank" rel="noopener">
          <i class="fas fa-qrcode"></i> Open Hik‑Connect Portal
        </a>
        <button class="btn btn-outline-primary mb-2" type="button" data-toggle="collapse" data-target="#embedHikConnect" aria-expanded="false" aria-controls="embedHikConnect">
          <i class="fas fa-window-maximize"></i> Try Embed Hik‑Connect (Beta)
        </button>
      </div>

      <div class="collapse mt-3" id="embedHikConnect">
        <div class="alert alert-warning">
          <strong>Heads up:</strong> Many cloud portals block embedding (X‑Frame‑Options/CSP). If the frame stays blank, use the button above to open in a new tab.
        </div>
        <div class="border rounded" style="height:70vh; overflow:hidden;">
          <iframe id="hikFrame" src="<?php echo htmlspecialchars($settings['hik_connect_url']); ?>" style="width:100%; height:100%; border:0; background:#fff;" referrerpolicy="no-referrer" sandbox="allow-forms allow-scripts allow-same-origin allow-popups"></iframe>
        </div>
      </div>

      <hr style="border-color: var(--gray-border);">
      <p class="mb-1" style="color: var(--text-dark);"><strong>Notes</strong></p>
      <ul class="mb-0" style="color: var(--text-dark);">
        <li>Ensure the Access Control device is reachable from this server/network.</li>
        <li>If using HTTPS on the device, make sure its certificate is trusted by your browser.</li>
        <li>Hik‑Connect requires a vendor account login and the device to be bound to that account.</li>
        <li>Direct cloud access without login typically requires vendor APIs and keys; we can integrate if you provide those.</li>
      </ul>
    </div>
  </div>
</div>

<!--END OF YOUR CODE-->

<!--BLOCK#3 START DON'T CHANGE THE ORDER-->
<?php include_once("../footer.php"); ?>
<!--END DON'T CHANGE THE ORDER-->
