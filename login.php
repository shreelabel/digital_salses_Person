<?php
declare(strict_types=1);

/**
 * Pixel-Perfect Reference Login Page.
 * Exact layout, 4 rounded corners, matching text, 50-50 split.
 */
if (!defined('SLC_ROOT')) {
  require __DIR__ . '/src/bootstrap.php';
}

use SLC\Core\Auth;
use SLC\Core\CSRF;
use SLC\Core\Session;
use SLC\Core\Config;
use SLC\Core\Database;

Session::start();

// Logout handler
if (isset($_GET['logout'])) {
  Auth::logout();
}

// Redirect if already logged in
if (Auth::check()) {
  $base = Config::basePath() ?: str_replace('\\', '/', rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/'));
  header('Location: ' . $base . '/');
  exit;
}

$error = null;
$justInstalled = isset($_GET['installed']);
$justLoggedOut = isset($_GET['logged_out']) || isset($_GET['logout']);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  CSRF::guard('POST');
  $email = (string) ($_POST['email'] ?? '');
  $password = (string) ($_POST['password'] ?? '');
  $dbReady = Database::isReady();
  if (!$dbReady) {
    $error = 'Database is not installed yet. Please run setup.php first.';
  } else {
    $result = Auth::attempt($email, $password);
    if ($result['ok'] ?? false) {
      $base = Config::basePath() ?: str_replace('\\', '/', rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/'));
      header('Location: ' . $base . '/');
      exit;
    }
    $error = $result['error'] ?? 'Invalid email or password.';
  }
}

$csrfToken = CSRF::token();
$base = Config::basePath() ?: str_replace('\\', '/', rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/'));
$assetBase = $base . '/public/assets';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Welcome Back — Shree Label Creation Digital Sales Person</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
    rel="stylesheet">
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    body {
      min-height: 100vh;
      background: #090a0f;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      color: #1e293b;
    }

    /* MAIN COMBINED CONTAINER */
    .wrapper {
      width: 100%;
      max-width: 1100px;
      height: 670px;
      background: transparent;
      display: flex;
      align-items: stretch;
      border: none;
    }

    /* LEFT HERO PANEL (ONLY LEFT SIDE ROUNDED) */
    .hero-panel {
      flex: 1;
      width: 50%;
      height: 100%;
      background: #0b0d13;
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      border-radius: 24px 0 0 24px;
      box-shadow: -15px 35px 95px rgba(0, 0, 0, 0.65);
    }

    .hero-img-bg {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: left center;
      display: block;
      border-radius: 24px 0 0 24px !important;
    }

    /* TOP-LEFT BRAND OVERLAY ON HERO IMAGE */
    .brand-overlay {
      position: absolute;
      top: 32px;
      left: 32px;
      z-index: 10;
      display: flex;
      align-items: center;
      gap: 10px;
      background: rgba(11, 13, 19, 0.92);
      padding: 6px 14px;
      border-radius: 12px;
      backdrop-filter: blur(8px);
      border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .brand-icon-box {
      width: 32px;
      height: 32px;
      background: #e28743;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #ffffff;
      font-weight: 800;
    }

    .brand-title {
      font-size: 15px;
      font-weight: 800;
      color: #ffffff;
      line-height: 1.1;
      letter-spacing: 0.5px;
    }

    .brand-sub {
      font-size: 9px;
      font-weight: 700;
      color: #e28743;
      letter-spacing: 1.2px;
      text-transform: uppercase;
    }

    /* RIGHT FORM PANEL (ONLY RIGHT SIDE ROUNDED) */
    .form-panel {
      flex: 1;
      width: 50%;
      height: 100%;
      background: #ffffff;
      border-radius: 0 24px 24px 0;
      padding: 34px 46px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      position: relative;
      box-shadow: 15px 35px 95px rgba(0, 0, 0, 0.45);
    }

    .lang-select-wrap {
      text-align: right;
    }

    .lang-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 7px 14px;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      background: #ffffff;
      font-size: 13px;
      font-weight: 600;
      color: #475569;
      cursor: pointer;
    }

    .form-header {
      text-align: center;
      margin-top: 4px;
      margin-bottom: 22px;
    }

    /* BRAND TITLE ABOVE WELCOME BACK */
    .brand-right-banner {
      display: inline-flex;
      flex-direction: column;
      align-items: center;
      margin-bottom: 12px;
    }

    .brand-right-main {
      font-size: 21px;
      font-weight: 800;
      color: #0f172a;
      letter-spacing: -0.3px;
      line-height: 1.1;
    }

    .brand-right-sub {
      font-size: 11.5px;
      font-weight: 700;
      color: #e28743;
      letter-spacing: 1px;
      text-transform: uppercase;
      margin-top: 2px;
    }

    .form-header h1 {
      font-size: 27px;
      font-weight: 800;
      color: #0f172a;
      letter-spacing: -0.5px;
      margin-top: 4px;
    }

    .form-header p {
      font-size: 13.5px;
      color: #64748b;
      margin-top: 4px;
    }

    .alert-box {
      padding: 12px 16px;
      border-radius: 12px;
      font-size: 13px;
      margin-bottom: 16px;
      font-weight: 600;
    }

    .alert-error {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #dc2626;
    }

    .alert-success {
      background: #f0fdf4;
      border: 1px solid #bbf7d0;
      color: #16a34a;
    }

    .form-group {
      margin-bottom: 16px;
    }

    .form-group label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: #334155;
      margin-bottom: 8px;
    }

    .input-icon-wrap {
      position: relative;
    }

    .input-icon-wrap svg {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      width: 18px;
      height: 18px;
      color: #94a3b8;
    }

    .input-icon-wrap input {
      width: 100%;
      padding: 12px 14px 12px 44px;
      border: 1px solid #cbd5e1;
      border-radius: 10px;
      font-size: 14px;
      color: #0f172a;
      background: #ffffff;
      transition: border-color 0.2s, box-shadow 0.2s;
    }

    .input-icon-wrap input:focus {
      outline: none;
      border-color: #e28743;
      box-shadow: 0 0 0 3px rgba(226, 135, 67, 0.15);
    }

    .pwd-toggle {
      position: absolute;
      right: 14px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: #94a3b8;
      user-select: none;
    }

    .forgot-link {
      display: block;
      text-align: right;
      font-size: 12.5px;
      font-weight: 600;
      color: #e28743;
      text-decoration: none;
      margin-top: 8px;
    }

    .btn-submit {
      width: 100%;
      padding: 13px;
      background: #e28743;
      border: none;
      border-radius: 10px;
      color: #ffffff;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: background 0.2s;
      margin-top: 6px;
    }

    .btn-submit:hover {
      background: #d47632;
    }

    .divider {
      display: flex;
      align-items: center;
      text-align: center;
      color: #94a3b8;
      font-size: 12px;
      margin: 20px 0;
    }

    .divider::before,
    .divider::after {
      content: '';
      flex: 1;
      border-bottom: 1px solid #e2e8f0;
    }

    .divider span {
      padding: 0 12px;
    }

    .social-btns {
      display: flex;
      gap: 12px;
    }

    .btn-social {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 11px;
      border: 1px solid #cbd5e1;
      border-radius: 10px;
      background: #ffffff;
      font-size: 13px;
      font-weight: 600;
      color: #334155;
      cursor: pointer;
      transition: background 0.2s;
    }

    .btn-social:hover {
      background: #f8fafc;
    }

    .form-footer {
      text-align: center;
      font-size: 13px;
      color: #64748b;
      margin-top: 18px;
    }

    .form-footer a {
      color: #e28743;
      font-weight: 700;
      text-decoration: none;
    }

    .credit-text {
      text-align: center;
      font-size: 11px;
      color: #94a3b8;
      margin-top: 14px;
    }

    @media (max-width: 900px) {
      .hero-panel {
        display: none;
      }

      .wrapper {
        max-width: 480px;
      }
    }
  </style>
</head>

<body>

  <div class="wrapper">
    <!-- LEFT HERO PANEL (Exact Reference Artwork, 50% Sizing, 4 Rounded Corners Outer Box) -->
    <div class="hero-panel">
      <!-- Brand header overlay -->
      <div class="brand-overlay">
        <div class="brand-icon-box">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M18 20V10M12 20V4M6 20v-6" />
          </svg>
        </div>
        <div>
          <div class="brand-title">SHREE LABEL CREATION</div>
          <div class="brand-sub">DIGITAL SALES PERSON</div>
        </div>
      </div>

      <!-- AI Hero Artwork -->
      <img class="hero-img-bg" src="<?= htmlspecialchars($assetBase) ?>/images/user_hero_artwork.jpg"
        alt="Shree Label Creation Digital Sales Person Hero">
    </div>

    <!-- RIGHT FORM PANEL (Exact Reference UI) -->
    <div class="form-panel">
      <div class="lang-select-wrap">
        <button class="lang-btn" type="button">🌐 English ▾</button>
      </div>

      <div class="form-body">
        <div class="form-header">
          <!-- BRAND TITLE ABOVE WELCOME BACK -->
          <div class="brand-right-banner">
            <div class="brand-right-main">Shree Label Creation</div>
            <div class="brand-right-sub">Digital Sales Person</div>
          </div>

          <h1>Welcome Back!</h1>
          <p>Login to your sales account</p>
        </div>

        <?php if ($justInstalled): ?>
          <div class="alert-box alert-success">Installation complete. Please sign in.</div>
        <?php endif; ?>
        <?php if ($justLoggedOut): ?>
          <div class="alert-box alert-success">Signed out successfully.</div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="alert-box alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">

          <div class="form-group">
            <label for="email">Email Address</label>
            <div class="input-icon-wrap">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                <polyline points="22,6 12,13 2,6" />
              </svg>
              <input id="email" name="email" type="email" placeholder="Enter your email" required autofocus>
            </div>
          </div>

          <div class="form-group">
            <label for="password">Password</label>
            <div class="input-icon-wrap">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
              </svg>
              <input id="password" name="password" type="password" placeholder="Enter your password" required>
              <span class="pwd-toggle" onclick="togglePassword()">👁</span>
            </div>
            <a href="#" class="forgot-link">Forgot Password?</a>
          </div>

          <button type="submit" class="btn-submit">
            <span>→ Login</span>
          </button>
        </form>

        <div class="divider">
          <span>or continue with</span>
        </div>

        <div class="social-btns">
          <button class="btn-social" type="button">
            <svg width="18" height="18" viewBox="0 0 24 24">
              <path fill="#4285F4"
                d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
              <path fill="#34A853"
                d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
              <path fill="#FBBC05"
                d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" />
              <path fill="#EA4335"
                d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" />
            </svg>
            <span>Login with Google</span>
          </button>

          <button class="btn-social" type="button">
            <svg width="18" height="18" viewBox="0 0 23 23">
              <path fill="#f35325" d="M1 1h10v10H1z" />
              <path fill="#81bc06" d="M12 1h10v10H1z" />
              <path fill="#05a6f0" d="M1 12h10v10H1z" />
              <path fill="#ffba08" d="M12 12h10v10H1z" />
            </svg>
            <span>Login with Microsoft</span>
          </button>
        </div>

        <div class="form-footer">
          Don't have an account? <a href="#">Contact Admin</a>
        </div>
      </div>

      <div class="credit-text">
        @Shree Label Creation 2026 || @ Developed by : Mriganka Bhusan Debnath
      </div>
    </div>
  </div>

  <script>
    function togglePassword() {
      const input = document.getElementById('password');
      if (input.type === 'password') {
        input.type = 'text';
      } else {
        input.type = 'password';
      }
    }
  </script>

</body>

</html>