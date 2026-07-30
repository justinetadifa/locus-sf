<?php
declare(strict_types=1);

require __DIR__ . '/app/Support/web.php';

$context = sfc_web_context();
sfc_render_head('Voting Dashboard | LOCUS-SF', $context, ['page' => 'voting-dashboard', 'role' => $context['user']['role'] ?? 'guest']);
sfc_render_header($context, 'voting');
?>
<main class="page-shell voting-page">
  <section class="site-shell page-intro-card page-command-intro voting-command-intro">
    <div class="page-intro-copy">
      <div class="eyebrow">Voting Dashboard</div>
      <div class="page-role-strip">
        <span class="page-role-pill is-role">Demand Signal Board</span>
        <span class="page-role-pill">Resident + investor pulse</span>
      </div>
      <h1>See what each location is starting to ask for next.</h1>
      <p>Track area-based business demand through premium vote cards, clear breakdowns, and admin-curated options that make the signal easier to trust.</p>
    </div>
    <div class="intro-actions">
      <a href="<?= htmlspecialchars(sfc_path('/property-ranking.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-secondary">View Rankings</a>
      <a href="<?= htmlspecialchars(sfc_path('/investor-login.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-primary">Investor / Resident Access</a>
    </div>
  </section>

  <section class="site-shell voting-root-grid" id="votingDashboardRoot">
    <div class="loading-panel">Loading voting dashboard...</div>
  </section>
</main>
<?php sfc_render_footer($context); ?>
