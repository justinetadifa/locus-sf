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
            sfc_register_seller($_POST);
            header('Location: ' . sfc_path('/seller-dashboard.php'));
            exit;
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
        }
    } else {
        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        if (sfc_login('seller', $email, $password)) {
            header('Location: ' . sfc_path('/seller-dashboard.php'));
            exit;
        }
        $error = 'Invalid seller credentials. Use the seeded seller account below or create a new seller workspace.';
    }
}

sfc_render_head('Seller Login | LOCUS-SF', $context, ['page' => 'seller-login', 'role' => 'seller']);
sfc_render_header($context);
?>
<main class="page-shell auth-page auth-page-seller">
  <section class="site-shell auth-stage">
    <div class="auth-visual" style="--auth-image:url('<?= htmlspecialchars($sceneImage, ENT_QUOTES, 'UTF-8') ?>')">
      <div class="auth-visual-copy">
        <span class="auth-role-chip">Seller Access</span>
        <h1>Present each property with clarity and confidence.</h1>
        <p>Enter a focused seller workspace built for sharper submissions, stronger listing stories, and cleaner inquiry visibility.</p>
      </div>
      <div class="auth-signal-row" aria-label="Seller portal highlights">
        <span class="auth-signal-pill">Listing control</span>
        <span class="auth-signal-pill">Readiness updates</span>
        <span class="auth-signal-pill">Investor replies</span>
      </div>
      <div class="auth-scene-orbit" aria-hidden="true">
        <span></span>
        <span></span>
        <span></span>
      </div>
      <div class="auth-visual-stack">
        <article class="auth-floating-card auth-floating-card-accent">
          <span>Seller lane</span>
          <strong>Submit, manage, respond</strong>
          <p>Keep listings polished, pricing believable, and updates visible without touching the admin side.</p>
        </article>
        <article class="auth-floating-card">
          <span>Best use</span>
          <strong>Sharper submissions win attention</strong>
          <p>Strong photos, precise descriptions, and realistic pricing make the portfolio feel more investor-ready.</p>
        </article>
      </div>
    </div>

    <div class="auth-surface">
      <div class="auth-brand-line">LOCUS-SF | Seller</div>
      <div class="auth-role-switch">
        <a href="<?= htmlspecialchars(sfc_path('/investor-login.php'), ENT_QUOTES, 'UTF-8') ?>" class="auth-role-switch-link">Investor</a>
        <a href="<?= htmlspecialchars(sfc_path('/seller-login.php'), ENT_QUOTES, 'UTF-8') ?>" class="auth-role-switch-link is-active">Seller</a>
        <a href="<?= htmlspecialchars(sfc_path('/admin-login.php'), ENT_QUOTES, 'UTF-8') ?>" class="auth-role-switch-link">Admin</a>
      </div>
      <div class="auth-surface-head">
        <span class="panel-chip">Seller portal</span>
        <h2><?= $mode === 'signup' ? 'Create seller account' : 'Seller login' ?></h2>
        <p><?= $mode === 'signup' ? 'Create your seller workspace, submit your identity details, and move into the admin approval queue.' : 'Use your seller account to manage listings, trust tasks, and investor conversations.' ?></p>
      </div>
      <div class="auth-switch">
        <a href="<?= htmlspecialchars(sfc_path('/seller-login.php'), ENT_QUOTES, 'UTF-8') ?>" class="auth-switch-link <?= $mode === 'login' ? 'is-active' : '' ?>">Login</a>
        <a href="<?= htmlspecialchars(sfc_path('/seller-login.php?mode=signup'), ENT_QUOTES, 'UTF-8') ?>" class="auth-switch-link <?= $mode === 'signup' ? 'is-active' : '' ?>">Create account</a>
      </div>
      <article class="auth-identity-card">
        <div class="auth-identity-head">
          <span>Current lane</span>
          <strong>Seller listing studio</strong>
        </div>
        <div class="auth-identity-grid">
          <div><span>Primary flow</span><strong><?= $mode === 'signup' ? 'Register / Verify / Publish' : 'Submit / Update / Reply' ?></strong></div>
          <div><span>Access</span><strong><?= $mode === 'signup' ? 'Pending until approved' : 'Owned listings + requests' ?></strong></div>
          <div><span>Reading mode</span><strong>Trust + response rhythm</strong></div>
        </div>
      </article>
      <?php if ($error !== ''): ?><div class="auth-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <form method="post" class="auth-form">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(sfc_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="mode" value="<?= htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') ?>">
        <?php if ($mode === 'signup'): ?>
        <label class="form-shell">
          <span>Full name</span>
          <input type="text" name="name" class="input-shell" value="<?= htmlspecialchars((string) ($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" autocomplete="name" required>
        </label>
        <label class="form-shell">
          <span>Legal or business name</span>
          <input type="text" name="legal_name" class="input-shell" value="<?= htmlspecialchars((string) ($_POST['legal_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </label>
        <label class="form-shell">
          <span>Seller type</span>
          <select name="seller_type" class="input-shell">
            <?php $sellerType = (string) ($_POST['seller_type'] ?? 'individual'); ?>
            <option value="individual" <?= $sellerType === 'individual' ? 'selected' : '' ?>>Individual owner</option>
            <option value="company" <?= $sellerType === 'company' ? 'selected' : '' ?>>Company / Developer</option>
            <option value="broker" <?= $sellerType === 'broker' ? 'selected' : '' ?>>Broker / Authorized representative</option>
          </select>
        </label>
        <label class="form-shell">
          <span>Display name</span>
          <input type="text" name="display_name" class="input-shell" value="<?= htmlspecialchars((string) ($_POST['display_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Optional public-facing seller label">
        </label>
        <label class="form-shell">
          <span>Phone</span>
          <input type="text" name="phone" class="input-shell" value="<?= htmlspecialchars((string) ($_POST['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </label>
        <label class="form-shell">
          <span>Email</span>
          <input type="email" name="email" class="input-shell" value="<?= htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" autocomplete="username" required>
        </label>
        <label class="form-shell">
          <span>Address line</span>
          <input type="text" name="address_line" class="input-shell" value="<?= htmlspecialchars((string) ($_POST['address_line'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </label>
        <label class="form-shell">
          <span>Barangay</span>
          <input type="text" name="barangay" class="input-shell" value="<?= htmlspecialchars((string) ($_POST['barangay'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <label class="form-shell">
          <span>City</span>
          <input type="text" name="city" class="input-shell" value="<?= htmlspecialchars((string) ($_POST['city'] ?? 'San Fernando, La Union'), ENT_QUOTES, 'UTF-8') ?>" required>
        </label>
        <label class="form-shell">
          <span>Government ID / license no.</span>
          <input type="text" name="government_id_no" class="input-shell" value="<?= htmlspecialchars((string) ($_POST['government_id_no'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        </label>
        <label class="form-shell">
          <span>Business registration no.</span>
          <input type="text" name="business_registration_no" class="input-shell" value="<?= htmlspecialchars((string) ($_POST['business_registration_no'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <label class="form-shell">
          <span>Authority on the property</span>
          <input type="text" name="authorization_basis" class="input-shell" value="<?= htmlspecialchars((string) ($_POST['authorization_basis'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Owner, exclusive broker, authorized representative" required>
        </label>
        <label class="form-shell">
          <span>Password</span>
          <input type="password" name="password" class="input-shell" autocomplete="new-password" required>
        </label>
        <label class="form-shell">
          <span>Confirm password</span>
          <input type="password" name="confirm_password" class="input-shell" autocomplete="new-password" required>
        </label>
        <button type="submit" class="btn-shell btn-shell-primary btn-full">Create Seller Workspace</button>
        <?php else: ?>
        <label class="form-shell">
          <span>Email</span>
          <input type="email" name="email" class="input-shell" value="<?= htmlspecialchars((string) ($_POST['email'] ?? 'seller@sfcelerate.local'), ENT_QUOTES, 'UTF-8') ?>" required>
        </label>
        <label class="form-shell">
          <span>Password</span>
          <input type="password" name="password" class="input-shell" value="Seller123!" required>
        </label>
        <button type="submit" class="btn-shell btn-shell-primary btn-full">Enter Seller Dashboard</button>
        <?php endif; ?>
      </form>
      <div class="auth-form-note">
        <?php if ($mode === 'signup'): ?>
        New seller accounts enter a verification queue before they can publish listings.
        <?php else: ?>
        Seeded seller access: <strong>seller@sfcelerate.local</strong> / <strong>Seller123!</strong>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>
<?php sfc_render_footer($context); ?>
