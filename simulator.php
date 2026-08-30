<?php
declare(strict_types=1);

require __DIR__ . '/app/Support/web.php';
$context = sfc_web_context();
sfc_render_head('Scenario Simulator | SFCelerate', $context, ['page' => 'scenario-simulator', 'role' => $context['user']['role'] ?? 'guest']);
sfc_render_header($context, 'simulator');
?>
<main class="page-shell dashboard-page">
  <section class="site-shell page-intro-card">
    <div>
      <div class="eyebrow">Scenario Simulator</div>
      <h1>Test investment ideas without bypassing the land-use rules.</h1>
      <p>Change the candidate site, proposed use, and enabling interventions. Every scenario is screened through CLUP compliance before attractiveness is considered.</p>
    </div>
    <div class="intro-actions"><a href="<?= htmlspecialchars(sfc_path('/reports.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-secondary">Open Reports</a></div>
  </section>
  <section class="site-shell dashboard-root-grid" id="scenarioSimulatorRoot"><div class="loading-panel">Loading simulator...</div></section>
</main>
<?php sfc_render_footer($context); ?>
