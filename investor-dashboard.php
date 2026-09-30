<?php
declare(strict_types=1);

require __DIR__ . '/app/Support/web.php';

$context = sfc_web_context();
sfc_require_role('investor', sfc_path('/investor-login.php'));
$context = sfc_web_context();
sfc_render_head('Investor Dashboard | LOCUS-SF', $context, ['page' => 'investor-dashboard', 'role' => 'investor']);
sfc_render_header($context, 'investor');
?>
<main class="page-shell dashboard-page">
  <section class="site-shell page-intro-card">
    <div>
      <div class="eyebrow">Investor / Resident Dashboard</div>
      <div class="page-role-strip">
        <span class="page-role-pill is-role">Investor Workspace</span>
        <span class="page-role-pill">Explore + compare + signal read</span>
      </div>
      <h1>A calmer starting point for opportunity and demand.</h1>
      <p>Explore candidate investment areas, compare suitability, and review CLUP compliance before advancing an opportunity.</p>
    </div>
    <div class="intro-actions">
      <a href="<?= htmlspecialchars(sfc_path('/property-explorer.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-primary">Explore Candidate Sites</a>
      <a href="<?= htmlspecialchars(sfc_path('/compare-decision.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-secondary">Open Compare Board</a>
    </div>
  </section>

  <section class="site-shell dashboard-root-grid" id="investorDashboardRoot">
    <div class="loading-panel">Loading investor dashboard...</div>
  </section>
</main>
<?php sfc_render_footer($context); ?>
