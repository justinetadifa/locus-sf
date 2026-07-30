<?php
declare(strict_types=1);

require __DIR__ . '/app/Support/web.php';

$context = sfc_web_context();
sfc_render_head('Investment Priority Board | LOCUS-SF', $context, ['page' => 'property-ranking', 'role' => $context['user']['role'] ?? 'guest']);
sfc_render_header($context, 'ranking');
?>
<main class="page-shell ranking-page ranking-page-shell">
  <section class="site-shell ranking-site-shell page-intro-card page-command-intro ranking-command-intro ranking-intro-surface">
    <div class="page-intro-copy">
      <div class="eyebrow">Investment Priority Board</div>
      <div class="page-role-strip">
        <span class="page-role-pill is-role">Decision Board</span>
        <span class="page-role-pill">IAI + CLUP-gated prioritization</span>
      </div>
      <h1>Prioritize sites that are attractive, suitable, and land-use compliant.</h1>
      <p>Every recommendation combines investment attractiveness with a visible CLUP gate, corridor strategy, readiness, and an explainable LGU action.</p>
    </div>
    <div class="intro-actions">
      <a href="<?= htmlspecialchars(sfc_path('/property-explorer.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-primary">Open Explorer</a>
      <a href="<?= htmlspecialchars(sfc_path('/compare-decision.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-secondary">Compare Selection</a>
    </div>
  </section>

  <section class="site-shell ranking-site-shell ranking-root-grid" id="rankingPageRoot">
    <div class="loading-panel">Loading rankings...</div>
  </section>
</main>
<?php sfc_render_footer($context); ?>
