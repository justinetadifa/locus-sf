<?php
declare(strict_types=1);

require __DIR__ . '/app/Support/web.php';
$context = sfc_web_context();
sfc_render_head('Investment Reports | LOCUS-SF', $context, ['page' => 'decision-reports', 'role' => $context['user']['role'] ?? 'guest']);
sfc_render_header($context, 'reports');
?>
<main class="page-shell reports-page">
  <header class="report-header">
    <div>
      <h1>Investment Reports</h1>
      <p>Review candidate rankings, land-use verification, and supporting evidence.</p>
    </div>
    <button type="button" class="report-print-button no-print" id="printDecisionReport">Print / Save PDF</button>
  </header>
  <section id="decisionReportsRoot" aria-label="Investment report"><div class="loading-panel">Loading reports...</div></section>
</main>
<?php sfc_render_footer($context); ?>
