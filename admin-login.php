<?php
declare(strict_types=1);

require __DIR__ . '/app/Support/web.php';

$context = sfc_web_context();
$sceneImage = $context['assetBase'] . '/images/sfcpanoramicView.png';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!sfc_verify_csrf_request()) {
        $error = 'Your security token expired. Refresh the page and try again.';
    } else {
        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        if (sfc_login('admin', $email, $password)) {
            header('Location: ' . sfc_path('/admin-dashboard.php'));
            exit;
        }
        $error = 'Invalid admin credentials. Use the local demo account below.';
    }
}

sfc_render_head('Admin Login | SFCelerate', $context, ['page' => 'admin-login', 'role' => 'admin']);
sfc_render_header($context);
?>
<main class="page-shell auth-page auth-page-admin">
  <section class="site-shell auth-stage auth-stage-admin">
    <div class="auth-visual auth-visual-admin" style="--auth-image:url('<?= htmlspecialchars($sceneImage, ENT_QUOTES, 'UTF-8') ?>')">
      <div class="auth-visual-copy auth-visual-copy-admin">
        <span class="auth-role-chip auth-role-chip-admin">Admin Workspace</span>
        <h1>Clear access to the platform's operational core.</h1>
        <p>Sign in to govern candidate-site evidence, CLUP validation, prioritization, scenarios, and policy reports.</p>
      </div>
      <div class="auth-admin-visual-treatment" aria-hidden="true"></div>
    </div>

    <div class="auth-surface auth-surface-admin">
      <div class="auth-brand-line auth-brand-line-admin">SFCelerate</div>
      <div class="auth-role-switch">
        <a href="<?= htmlspecialchars(sfc_path('/investor-login.php'), ENT_QUOTES, 'UTF-8') ?>" class="auth-role-switch-link">Investor</a>
        <a href="<?= htmlspecialchars(sfc_path('/admin-login.php'), ENT_QUOTES, 'UTF-8') ?>" class="auth-role-switch-link is-active">Admin</a>
      </div>
      <div class="auth-surface-head auth-surface-head-admin">
        <h2>Admin login</h2>
        <p>Use your administrator credentials to continue to the platform dashboard.</p>
      </div>
      <?php if ($error !== ''): ?><div class="auth-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <form method="post" class="auth-form auth-form-admin">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(sfc_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <label class="form-shell" for="adminEmail">
          <span>Email address</span>
          <input id="adminEmail" type="email" name="email" class="input-shell" value="admin@sfcelerate.local" autocomplete="username" autocapitalize="off" spellcheck="false" inputmode="email" required>
        </label>
        <label class="form-shell" for="adminPassword">
          <span>Password</span>
          <input id="adminPassword" type="password" name="password" class="input-shell" value="Admin123!" autocomplete="current-password" required>
        </label>
        <button type="submit" class="btn-shell btn-shell-primary btn-full auth-admin-submit">Continue to dashboard</button>
      </form>
      <div class="auth-admin-secondary">
        <a href="mailto:support@sfcelerate.local?subject=Admin%20Access%20Support" class="auth-admin-secondary-link">Contact support</a>
        <a href="<?= htmlspecialchars(sfc_path('/index.php'), ENT_QUOTES, 'UTF-8') ?>" class="auth-admin-secondary-link">Back to platform</a>
      </div>
      <div class="auth-form-note auth-form-note-admin">
        Demo access: <strong>admin@sfcelerate.local</strong> / <strong>Admin123!</strong>
      </div>
    </div>
  </section>
</main>
<?php sfc_render_footer($context); ?>
