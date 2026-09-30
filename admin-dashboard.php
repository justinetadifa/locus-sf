<?php
declare(strict_types=1);

require __DIR__ . '/app/Support/web.php';

$context = sfc_web_context();
sfc_require_role('admin', sfc_path('/admin-login.php'));
$context = sfc_web_context();
sfc_render_head('Admin Dashboard | LOCUS-SF', $context, ['page' => 'admin-dashboard', 'role' => 'admin']);
sfc_render_header($context, 'admin');
?>
<main class="page-shell dashboard-page">
  <section class="site-shell page-intro-card">
    <div>
      <div class="eyebrow">Admin Dashboard</div>
      <div class="page-role-strip">
        <span class="page-role-pill is-role">Admin Workspace</span>
        <span class="page-role-pill">Curation + governance</span>
      </div>
      <h1>Platform oversight without the noise.</h1>
      <p>Manage candidate sites, validate planning evidence, monitor CLUP outcomes, and generate policy-ready investment priorities.</p>
    </div>
    <div class="intro-actions">
      <a href="<?= htmlspecialchars(sfc_path('/admin-properties.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-primary">Manage Candidate Sites</a>
      <a href="<?= htmlspecialchars(sfc_path('/reports.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-secondary">Open Reports</a>
    </div>
  </section>

  <section class="site-shell dashboard-root-grid" id="adminDashboardRoot">
    <div class="loading-panel">Loading admin dashboard...</div>
  </section>
</main>
<?php sfc_render_footer($context); ?>
