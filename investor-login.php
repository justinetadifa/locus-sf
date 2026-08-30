<?php
declare(strict_types=1);

require __DIR__ . '/app/Support/web.php';

$context = sfc_web_context();
$sceneImage = $context['assetBase'] . '/images/sfcpanoramicView.png';
$mode = ($_GET['mode'] ?? $_POST['mode'] ?? 'login') === 'signup' ? 'signup' : 'login';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!sfc_verify_csrf_request()) {
        $error = 'Your security token expired. Refresh the page and try again.';
    } elseif ($mode === 'signup') {
        try {
            sfc_register_investor(
                (string) ($_POST['name'] ?? ''),
                (string) ($_POST['email'] ?? ''),
                (string) ($_POST['password'] ?? ''),
                (string) ($_POST['confirm_password'] ?? '')
            );
            header('Location: ' . sfc_path('/investor-dashboard.php'));
            exit;
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
        }
    } else {
        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        if (sfc_login('investor', $email, $password)) {
            header('Location: ' . sfc_path('/investor-dashboard.php'));
            exit;
        }
        $error = 'Invalid investor credentials. Use the seeded account below or create a new investor profile.';
    }
}

sfc_render_head('Investor Login | SFCelerate', $context, ['page' => 'investor-login', 'role' => 'investor']);
sfc_render_header($context);
?>
<main class="page-shell auth-page auth-page-investor">
  <section class="site-shell auth-stage auth-stage-investor-refined">
    <div class="auth-visual auth-visual-investor-refined" style="--auth-image:url('<?= htmlspecialchars($sceneImage, ENT_QUOTES, 'UTF-8') ?>')">
      <div class="auth-visual-copy auth-visual-copy-investor-refined">
        <span class="auth-role-chip auth-role-chip-investor-refined">Investor / Resident</span>
        <h1>Local opportunities, presented with more calm and clarity.</h1>
        <p>Sign in to review properties, compare options, and follow demand signals in a workspace designed for focused decision-making.</p>
      </div>
      <div class="auth-investor-visual-treatment" aria-hidden="true"></div>
    </div>

    <div class="auth-surface auth-surface-investor-refined">
      <div class="auth-brand-line auth-brand-line-investor-refined">SFCelerate</div>
      <div class="auth-role-switch">
        <a href="<?= htmlspecialchars(sfc_path('/investor-login.php'), ENT_QUOTES, 'UTF-8') ?>" class="auth-role-switch-link is-active">Investor</a>
        <a href="<?= htmlspecialchars(sfc_path('/admin-login.php'), ENT_QUOTES, 'UTF-8') ?>" class="auth-role-switch-link">Admin</a>
      </div>
      <div class="auth-surface-head auth-surface-head-investor-refined">
        <h2><?= $mode === 'signup' ? 'Create investor account' : 'Investor / Resident login' ?></h2>
        <p><?= $mode === 'signup' ? 'Create your account to save candidate areas and CLUP-screened comparisons.' : 'Use your account to continue your shortlist, suitability reviews, and comparisons.' ?></p>
      </div>
      <div class="auth-switch auth-switch-investor-refined">
        <a href="<?= htmlspecialchars(sfc_path('/investor-login.php'), ENT_QUOTES, 'UTF-8') ?>" class="auth-switch-link <?= $mode === 'login' ? 'is-active' : '' ?>">Login</a>
        <a href="<?= htmlspecialchars(sfc_path('/investor-login.php?mode=signup'), ENT_QUOTES, 'UTF-8') ?>" class="auth-switch-link <?= $mode === 'signup' ? 'is-active' : '' ?>">Create account</a>
      </div>
      <?php if ($error !== ''): ?><div class="auth-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <form method="post" class="auth-form auth-form-investor-refined">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(sfc_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="mode" value="<?= htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') ?>">
        <?php if ($mode === 'signup'): ?>
        <label class="form-shell">
          <span>Full name</span>
          <input type="text" id="investorName" name="name" class="input-shell" value="<?= htmlspecialchars((string) ($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" autocomplete="name" required>
        </label>
        <?php endif; ?>
        <label class="form-shell">
          <span>Email address</span>
          <input type="email" id="investorEmail" name="email" class="input-shell" value="<?= htmlspecialchars((string) ($_POST['email'] ?? ($mode === 'signup' ? '' : 'investor@sfcelerate.local')), ENT_QUOTES, 'UTF-8') ?>" autocomplete="username" autocapitalize="off" spellcheck="false" inputmode="email" required>
        </label>
        <label class="form-shell">
          <span>Password</span>
          <input type="password" id="investorPassword" name="password" class="input-shell" value="<?= $mode === 'signup' ? '' : 'Investor123!' ?>" autocomplete="<?= $mode === 'signup' ? 'new-password' : 'current-password' ?>" required>
        </label>
        <?php if ($mode === 'signup'): ?>
        <label class="form-shell">
          <span>Confirm password</span>
          <input type="password" id="investorConfirmPassword" name="confirm_password" class="input-shell" autocomplete="new-password" required>
        </label>
        <?php endif; ?>
        <button type="submit" class="btn-shell btn-shell-primary btn-full auth-investor-submit"><?= $mode === 'signup' ? 'Create account' : 'Continue to dashboard' ?></button>
      </form>
      <div class="auth-investor-secondary">
        <a href="mailto:support@sfcelerate.local?subject=Investor%20Access%20Support" class="auth-investor-secondary-link">Need help?</a>
        <a href="<?= htmlspecialchars(sfc_path('/property-ranking.php'), ENT_QUOTES, 'UTF-8') ?>" class="auth-investor-secondary-link">Browse rankings first</a>
      </div>
      <div class="auth-form-note auth-form-note-investor-refined">
        <?php if ($mode === 'signup'): ?>
        Investor accounts keep their own candidate-site shortlist and comparison activity.
        <?php else: ?>
        Seeded investor access: <strong>investor@sfcelerate.local</strong> / <strong>Investor123!</strong>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>
<?php sfc_render_footer($context); ?>
