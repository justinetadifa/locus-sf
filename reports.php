<?php
declare(strict_types=1);

require __DIR__ . '/app/Support/web.php';
$context = sfc_web_context();
sfc_render_head('Decision Reports | LOCUS-SF', $context, ['page' => 'decision-reports', 'role' => $context['user']['role'] ?? 'guest']);
sfc_render_header($context, 'reports');
?>
<main class="page-shell dashboard-page">
  <section class="site-shell page-intro-card no-print">
    <div>
      <div class="eyebrow">Decision Reports</div>
      <h1>Policy-ready rankings with CLUP evidence attached.</h1>
      <p>Review compliance distribution, suitability, and recommended LGU actions before generating a printable priority report.</p>
    </div>
    <div class="intro-actions"><button type="button" class="btn-shell btn-shell-primary" id="printDecisionReport">Print / Save PDF</button></div>
  </section>
  <section class="site-shell dashboard-root-grid" id="decisionReportsRoot"><div class="loading-panel">Loading reports...</div></section>
</main>
<?php sfc_render_footer($context); ?>
