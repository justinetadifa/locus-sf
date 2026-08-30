<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function sfc_web_context(): array
{
    static $context = null;
    if ($context !== null) {
        return $context;
    }

    $config = require dirname(__DIR__) . '/config.php';
    $appName = (string) ($config['app']['name'] ?? 'SFCelerate');
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $basePath = rtrim(str_replace('/index.php', '', $scriptName), '/');
    if (preg_match('#/(admin-dashboard|seller-dashboard|investor-dashboard|admin-login|seller-login|investor-login|property-explorer|property-ranking|voting-dashboard|property-details|compare-decision|admin-properties|admin-showcase|offer-board|city-pipeline|simulator|reports|logout)\.php$#', $scriptName, $matches) === 1) {
        $basePath = substr($scriptName, 0, -strlen($matches[0]));
    }
    $basePath = $basePath === '' ? '' : $basePath;
    $assetBase = ($basePath === '' ? '' : $basePath) . '/assets';
    $apiBase = ($basePath === '' ? '' : $basePath) . '/api';

    $context = [
        'appName' => $appName,
        'basePath' => $basePath,
        'assetBase' => $assetBase,
        'apiBase' => $apiBase,
        'mapTileUrl' => (string) ($config['services']['maps']['tile_url'] ?? 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'mapAttribution' => (string) ($config['services']['maps']['tile_attribution'] ?? '&copy; OpenStreetMap contributors'),
        'user' => sfc_current_user(),
    ];

    return $context;
}

function sfc_role_label(?string $role): string
{
    return match ($role) {
        'admin' => 'Admin',
        'seller' => 'Seller',
        'investor' => 'Investor / Resident',
        default => 'Guest',
    };
}

function sfc_path(string $path): string
{
    $context = sfc_web_context();
    $basePath = $context['basePath'];
    return ($basePath === '' ? '' : $basePath) . $path;
}

function sfc_asset_version(string $relativePath): string
{
    $fullPath = dirname(__DIR__, 2) . '/assets/' . ltrim($relativePath, '/');
    $mtime = @filemtime($fullPath);
    return $mtime ? '?v=' . rawurlencode((string) $mtime) : '';
}

function sfc_icon(string $name): string
{
    $icons = [
        'home' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 11.5 12 5l8 6.5V20a1 1 0 0 1-1 1h-4.5v-6h-5v6H5a1 1 0 0 1-1-1z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 21v-6h6v6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>',
        'explorer' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="m16 16 4 4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
        'compare' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 5v14M17 5v14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M10 8h4M10 12h6M10 16h3" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
        'ranking' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 19V11M12 19V7M17 19V4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M4 19h16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
        'vote' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4.5" y="6" width="15" height="12" rx="2.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="m9 11 2.5 2.5L16 9" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'admin' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5 5 7v5.5c0 4.2 2.9 6.9 7 8 4.1-1.1 7-3.8 7-8V7z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9.5 12 11 13.5l3.5-3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'inventory' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M8 9h8M8 13h8M8 17h5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
        'seller' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6h12l1.5 3.5L12 20 4.5 9.5z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 6 12 20 15 6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>',
        'investor' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v16M7 9l5-5 5 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 20h12" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
        'logout' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 7.5 19 12l-5 4.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M19 12H9M11 5H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'bell' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4.5a4 4 0 0 0-4 4v2.2c0 1.2-.4 2.4-1.2 3.3L5.5 15.5h13l-1.3-1.5a4.9 4.9 0 0 1-1.2-3.3V8.5a4 4 0 0 0-4-4Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M10 18a2 2 0 0 0 4 0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
        'lock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M8 10V8a4 4 0 0 1 8 0v2" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
        'menu' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 8h14M5 12h14M5 16h14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
        'offer' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 7.5h12a1.5 1.5 0 0 1 1.5 1.5v7A1.5 1.5 0 0 1 18 17.5H6A1.5 1.5 0 0 1 4.5 16V9A1.5 1.5 0 0 1 6 7.5Z" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M8 12h8M12 7.5v10" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
        'pipeline' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 18V9.5h4V18M10 18V6h4v12M15 18v-8.5h4V18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 18h16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
        'showcase' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5 14.6 9l5.9.8-4.3 4.2 1.1 5.9L12 17.3 6.7 19.9l1.1-5.9-4.3-4.2L9.4 9z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>',
        'spark' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>',
        'insights' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 18V7M10 18V10M16 18V5M22 18H2" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
    ];

    return $icons[$name] ?? $icons['spark'];
}

function sfc_render_head(string $title, array $context, array $bodyData = []): void
{
    $pageName = (string) ($bodyData['page'] ?? '');
    $bodyAttributes = [];
    foreach ($bodyData as $key => $value) {
        $bodyAttributes[] = sprintf('data-%s="%s"', htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8'), htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'));
    }
    $clientConfig = [
        'appName' => $context['appName'],
        'basePath' => $context['basePath'],
        'apiBase' => $context['apiBase'],
        'assetBase' => $context['assetBase'],
        'mapTileUrl' => $context['mapTileUrl'] ?? 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        'mapAttribution' => $context['mapAttribution'] ?? '&copy; OpenStreetMap contributors',
        'role' => $context['user']['role'] ?? 'guest',
        'user' => $context['user'],
        'csrfToken' => sfc_csrf_token(),
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <base href="<?= htmlspecialchars(($context['basePath'] === '' ? '/' : $context['basePath'] . '/'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="icon" type="image/png" sizes="32x32" href="<?= htmlspecialchars($context['assetBase'], ENT_QUOTES, 'UTF-8') ?>/images/webLogoSfc-favicon.png?v=8">
  <link rel="icon" type="image/png" sizes="16x16" href="<?= htmlspecialchars($context['assetBase'], ENT_QUOTES, 'UTF-8') ?>/images/webLogoSfc-favicon.png?v=8">
  <link rel="shortcut icon" href="<?= htmlspecialchars($context['assetBase'], ENT_QUOTES, 'UTF-8') ?>/images/webLogoSfc-favicon.png?v=8">
  <link rel="apple-touch-icon" sizes="180x180" href="<?= htmlspecialchars($context['assetBase'], ENT_QUOTES, 'UTF-8') ?>/images/webLogoSfc-favicon.png?v=8">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
  <?php if (in_array($pageName, ['property-explorer', 'property-explorer-terminal', 'property-details'], true)): ?>
  <link rel="stylesheet" href="https://unpkg.com/maplibre-gl@4.5.0/dist/maplibre-gl.css">
  <?php endif; ?>
  <link rel="stylesheet" href="<?= htmlspecialchars($context['assetBase'], ENT_QUOTES, 'UTF-8') ?>/css/portal.css<?= htmlspecialchars(sfc_asset_version('css/portal.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body <?= implode(' ', $bodyAttributes) ?>>
<script>
  window.SFC_APP_CONFIG = <?= json_encode($clientConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
<div class="studio-transition" id="studioTransition" aria-hidden="true">
  <div class="studio-transition-line"></div>
</div>
<?php
}

function sfc_render_header(array $context, string $active = ''): void
{
    $user = $context['user'];
    $role = $user['role'] ?? 'guest';
    $isLanding = $active === 'landing';
    $dashboardHref = null;
    $dashboardKey = null;
    $navItems = match ($role) {
        'admin' => [
            ['href' => sfc_path('/property-ranking.php'), 'label' => 'Priority Board', 'icon' => 'ranking', 'key' => 'ranking'],
            ['href' => sfc_path('/property-explorer.php'), 'label' => 'Map Explorer', 'icon' => 'explorer', 'key' => 'explorer'],
            ['href' => sfc_path('/simulator.php'), 'label' => 'Simulator', 'icon' => 'insights', 'key' => 'simulator'],
            ['href' => sfc_path('/reports.php'), 'label' => 'Reports', 'icon' => 'inventory', 'key' => 'reports'],
        ],
        'seller' => [
            ['href' => sfc_path('/property-ranking.php'), 'label' => 'Rankings', 'icon' => 'ranking', 'key' => 'ranking'],
            ['href' => sfc_path('/property-explorer.php'), 'label' => 'Market', 'icon' => 'explorer', 'key' => 'explorer'],
        ],
        'investor' => [
            ['href' => sfc_path('/property-ranking.php'), 'label' => 'Priority Board', 'icon' => 'ranking', 'key' => 'ranking'],
            ['href' => sfc_path('/property-explorer.php'), 'label' => 'Map Explorer', 'icon' => 'explorer', 'key' => 'explorer'],
            ['href' => sfc_path('/compare-decision.php'), 'label' => 'Compare', 'icon' => 'compare', 'key' => 'compare'],
        ],
        default => [
            ['href' => sfc_path('/index.php'), 'label' => 'Home', 'icon' => 'home', 'key' => 'landing'],
            ['href' => sfc_path('/property-ranking.php'), 'label' => 'Priority Board', 'icon' => 'ranking', 'key' => 'ranking'],
            ['href' => sfc_path('/property-explorer.php'), 'label' => 'Map Explorer', 'icon' => 'explorer', 'key' => 'explorer'],
            ['href' => sfc_path('/reports.php'), 'label' => 'Reports', 'icon' => 'inventory', 'key' => 'reports'],
        ],
    };
    if ($role === 'guest' && $isLanding) {
        $navItems = [
            ['href' => sfc_path('/index.php'), 'label' => 'Home', 'icon' => 'home', 'key' => 'landing'],
            ['href' => sfc_path('/property-ranking.php'), 'label' => 'Priority Board', 'icon' => 'ranking', 'key' => 'ranking'],
            ['href' => sfc_path('/property-explorer.php'), 'label' => 'Map Explorer', 'icon' => 'explorer', 'key' => 'explorer'],
            ['href' => sfc_path('/reports.php'), 'label' => 'Reports', 'icon' => 'inventory', 'key' => 'reports'],
        ];
    }
    if ($role === 'admin') {
        $dashboardHref = sfc_path('/admin-dashboard.php');
        $dashboardKey = 'admin';
    } elseif ($role === 'seller') {
        $dashboardHref = sfc_path('/seller-dashboard.php');
        $dashboardKey = 'seller';
    } elseif ($role === 'investor') {
        $dashboardHref = sfc_path('/investor-dashboard.php');
        $dashboardKey = 'investor';
    }
    $moreItems = [
        [
            'href' => sfc_path('/simulator.php'),
            'label' => 'Scenario Simulator',
            'description' => 'Test investment uses and enabling interventions through the CLUP gate.',
            'icon' => 'insights',
            'key' => 'simulator',
        ],
        [
            'href' => sfc_path('/city-pipeline.php'),
            'label' => 'City Pipeline',
            'description' => 'Planned, approved, and not-yet-built city projects.',
            'icon' => 'pipeline',
            'key' => 'city-pipeline',
        ],
    ];
    if ($role === 'admin') {
        $moreItems[] = [
            'href' => sfc_path('/admin-showcase.php'),
            'label' => 'Showcase Studio',
            'description' => 'Admin-only CRUD for Offer Board and City Pipeline.',
            'icon' => 'showcase',
            'key' => 'admin-showcase',
        ];
    }
    $brandSubtitle = $isLanding ? 'Curated City Investment Board' : 'San Fernando Opportunity Platform';
    $moreLabel = $isLanding ? 'Collections' : 'More';
    $guestCtaLabel = $isLanding ? 'Choose Workspace' : 'Enter Platform';
    ?>
  <header class="site-header">
    <div class="site-shell nav-shell">
      <div class="brand-link">
        <button
          type="button"
          class="brand-mark"
          data-city-brief-trigger
          aria-haspopup="dialog"
          aria-controls="cityBriefModal"
          aria-label="Open San Fernando city brief"
        >
          <img src="<?= htmlspecialchars($context['assetBase'], ENT_QUOTES, 'UTF-8') ?>/images/webLogoSfc.png" alt="SFCelerate" class="brand-logo">
        </button>
        <a href="<?= htmlspecialchars(sfc_path('/index.php'), ENT_QUOTES, 'UTF-8') ?>" class="brand-copy brand-home-link">
          <span class="brand-title">SFCelerate</span>
          <span class="brand-subtitle"><?= htmlspecialchars($brandSubtitle, ENT_QUOTES, 'UTF-8') ?></span>
        </a>
      </div>

      <div class="nav-center">
        <nav class="top-nav" aria-label="Primary navigation">
          <?php foreach ($navItems as $item): ?>
            <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>" class="nav-link <?= $active === $item['key'] ? 'active' : '' ?>">
              <span class="nav-link-icon"><?= sfc_icon($item['icon']) ?></span>
              <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
            </a>
          <?php endforeach; ?>
        </nav>
      </div>

      <div class="nav-actions">
        <div class="portal-menu portal-menu-compact" data-sfc-menu>
          <button type="button" class="btn-shell btn-shell-secondary portal-menu-trigger more-menu-trigger <?= in_array($active, ['offer-board', 'city-pipeline', 'admin-showcase'], true) ? 'is-active' : '' ?>" data-sfc-menu-toggle aria-expanded="false" aria-controls="moreMenuPanel">
            <span class="btn-shell-icon"><?= sfc_icon('menu') ?></span>
            <span><?= htmlspecialchars($moreLabel, ENT_QUOTES, 'UTF-8') ?></span>
          </button>
          <div class="portal-menu-panel more-menu-panel" id="moreMenuPanel">
            <?php foreach ($moreItems as $item): ?>
              <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>" class="portal-entry <?= $active === $item['key'] ? 'is-active' : '' ?>">
                <span class="portal-entry-icon"><?= sfc_icon($item['icon']) ?></span>
                <span class="portal-entry-copy">
                  <strong><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                  <span><?= htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8') ?></span>
                </span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php if ($user !== null): ?>
          <a
            href="<?= htmlspecialchars($dashboardHref ?? sfc_path('/index.php'), ENT_QUOTES, 'UTF-8') ?>"
            class="session-chip session-chip-link <?= $active === $dashboardKey ? 'active' : '' ?>"
            aria-label="<?= htmlspecialchars(sfc_role_label($role) . ' dashboard', ENT_QUOTES, 'UTF-8') ?>"
          >
            <span class="session-chip-icon"><?= sfc_icon($role === 'admin' ? 'admin' : ($role === 'seller' ? 'seller' : 'investor')) ?></span>
            <span class="session-chip-text"><?= htmlspecialchars(sfc_role_label($role), ENT_QUOTES, 'UTF-8') ?></span>
          </a>
          <button
            type="button"
            class="btn-shell btn-shell-secondary notification-trigger notification-trigger-compact"
            data-notification-trigger
            aria-expanded="false"
            aria-controls="notificationDrawer"
            aria-label="Signals"
            title="Signals"
          >
            <span class="btn-shell-icon"><?= sfc_icon('bell') ?></span>
            <span class="notification-trigger-text">Signals</span>
            <span class="notification-trigger-badge" data-notification-badge hidden>0</span>
          </button>
          <form method="post" action="<?= htmlspecialchars(sfc_path('/logout.php'), ENT_QUOTES, 'UTF-8') ?>" class="header-logout-form">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(sfc_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <button
              type="submit"
              class="btn-shell btn-shell-secondary header-logout-trigger"
              aria-label="Logout"
              title="Logout"
            >
              <span class="btn-shell-icon"><?= sfc_icon('logout') ?></span>
              <span class="header-logout-text">Logout</span>
            </button>
          </form>
        <?php else: ?>
          <div class="portal-menu" data-sfc-menu>
            <button type="button" class="btn-shell btn-shell-primary portal-menu-trigger" data-sfc-menu-toggle aria-expanded="false" aria-controls="portalMenuPanel">
              <span class="btn-shell-icon"><?= sfc_icon('lock') ?></span>
              <span><?= htmlspecialchars($guestCtaLabel, ENT_QUOTES, 'UTF-8') ?></span>
            </button>
            <div class="portal-menu-panel" id="portalMenuPanel">
              <a href="<?= htmlspecialchars(sfc_path('/investor-login.php'), ENT_QUOTES, 'UTF-8') ?>" class="portal-entry portal-entry-primary">
                <span class="portal-entry-icon"><?= sfc_icon('investor') ?></span>
                <span class="portal-entry-copy">
                  <strong>Investor / Resident</strong>
                  <span>Explore candidate areas and compare CLUP-screened opportunities.</span>
                </span>
              </a>
              <a href="<?= htmlspecialchars(sfc_path('/admin-login.php'), ENT_QUOTES, 'UTF-8') ?>" class="portal-entry">
                <span class="portal-entry-icon"><?= sfc_icon('admin') ?></span>
                <span class="portal-entry-copy">
                  <strong>Admin</strong>
                  <span>Manage candidate sites, CLUP screening, scenarios, and reports.</span>
                </span>
              </a>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </header>
<?php
}

function sfc_render_footer(array $context): void
{
    $cityBriefImage = $context['assetBase'] . '/images/sfcView.png';
    $cityStoryCards = [
        [
            'index' => '01',
            'title' => 'District character can live here.',
            'summary' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Keep this for civic identity, walkability, and place character.',
        ],
        [
            'index' => '02',
            'title' => 'Growth rhythm can be explained clearly.',
            'summary' => 'Donec id elit non mi porta gravida at eget metus. Use this slot for corridor movement, new activity, or city momentum.',
        ],
        [
            'index' => '03',
            'title' => 'Visitor energy can support the story.',
            'summary' => 'Maecenas faucibus mollis interdum. Add tourism cues, food culture, and destination appeal without overwhelming the screen.',
        ],
    ];
    $cityInvestmentTips = [
        [
            'label' => 'Step 01',
            'title' => 'Start with movement, not hype.',
            'summary' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Focus first on where people, traffic, and daily routines already flow.',
        ],
        [
            'label' => 'Step 02',
            'title' => 'Check daily-use demand before narrative.',
            'summary' => 'Aenean eu leo quam. Use this card for utility, visibility, service demand, and tenant practicality.',
        ],
        [
            'label' => 'Step 03',
            'title' => 'Move when proof starts compounding.',
            'summary' => 'Etiam porta sem malesuada magna mollis euismod. Add your real readiness advice, timing logic, or negotiation cues here.',
        ],
    ];
    $citySpotCards = [
        [
            'eyebrow' => 'Signature postcard',
            'badge' => 'Coastal lens',
            'metric' => 'Golden hour',
            'title' => 'Poro Point Coastline',
            'summary' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Use this feature card for your strongest tourism or city identity story.',
            'meta_primary' => 'Waterfront edge',
            'meta_secondary' => 'Sunset circuit',
            'footer_label' => 'Featured frame',
            'footer_value' => 'Preview story',
            'image' => $context['assetBase'] . '/images/sfcView.png',
            'alt' => 'Scenic view of San Fernando coastline',
            'tone' => 'tone-cream',
            'featured' => true,
        ],
        [
            'eyebrow' => 'City postcard',
            'badge' => 'Heritage feel',
            'metric' => 'Walkable core',
            'title' => 'Heritage Core Walk',
            'summary' => 'Donec id elit non mi porta gravida at eget metus. Aenean lacinia bibendum nulla sed consectetur.',
            'meta_primary' => 'Cafe route',
            'meta_secondary' => 'Culture cue',
            'footer_label' => 'City lane',
            'footer_value' => 'Add details',
            'image' => $context['assetBase'] . '/images/sfcpanoramicView.png',
            'alt' => 'Panoramic heritage view in San Fernando',
            'tone' => 'tone-sky',
            'featured' => false,
        ],
        [
            'eyebrow' => 'Lifestyle card',
            'badge' => 'Local taste',
            'metric' => 'Evening energy',
            'title' => 'Food And Market Loop',
            'summary' => 'Praesent commodo cursus magna, vel scelerisque nisl consectetur et. Cras mattis consectetur purus sit amet fermentum.',
            'meta_primary' => 'Dining lane',
            'meta_secondary' => 'Visitor pull',
            'footer_label' => 'Local signal',
            'footer_value' => 'Add details',
            'image' => $context['assetBase'] . '/images/LaFinns.png',
            'alt' => 'Food and market scene in San Fernando',
            'tone' => 'tone-sand',
            'featured' => false,
        ],
        [
            'eyebrow' => 'Scenic escape',
            'badge' => 'Quiet pocket',
            'metric' => 'Slow pace',
            'title' => 'Garden View Escape',
            'summary' => 'Maecenas faucibus mollis interdum. Nulla vitae elit libero, a pharetra augue.',
            'meta_primary' => 'Stay moment',
            'meta_secondary' => 'Soft luxury',
            'footer_label' => 'Stay card',
            'footer_value' => 'Add details',
            'image' => $context['assetBase'] . '/images/FerarenProperty.png',
            'alt' => 'Garden property view in San Fernando',
            'tone' => 'tone-forest',
            'featured' => false,
        ],
        [
            'eyebrow' => 'Urban perspective',
            'badge' => 'Civic frame',
            'metric' => 'Daylight read',
            'title' => 'City Outlook Deck',
            'summary' => 'Etiam porta sem malesuada magna mollis euismod. Vestibulum id ligula porta felis euismod semper.',
            'meta_primary' => 'Photo stop',
            'meta_secondary' => 'Context view',
            'footer_label' => 'Outlook card',
            'footer_value' => 'Add details',
            'image' => $context['assetBase'] . '/images/FabroBldg.png',
            'alt' => 'Urban building view in San Fernando',
            'tone' => 'tone-nocturne',
            'featured' => false,
        ],
    ];
    ?>
  <div class="modal-shell city-brief-shell" id="cityBriefModal" hidden>
    <div class="modal-card city-brief-modal" role="dialog" aria-modal="true" aria-labelledby="cityBriefTitle">
      <div class="city-brief-stage">
        <div class="city-brief-backdrop" aria-hidden="true">
          <img data-city-image-src="<?= htmlspecialchars($cityBriefImage, ENT_QUOTES, 'UTF-8') ?>" alt="" decoding="async">
        </div>
        <div class="city-brief-toolbar">
          <div class="city-brief-toolbar-group">
            <span class="city-brief-chip">San Fernando Brief</span>
            <span class="city-brief-chip is-soft">Logo Activated View</span>
          </div>
          <button type="button" class="modal-close city-brief-close" data-city-brief-dismiss>Close</button>
        </div>

        <section class="city-brief-hero">
          <div class="city-brief-hero-copy">
            <div class="city-brief-prelude">
              <span class="city-brief-kicker">San Fernando, La Union</span>
              <span class="city-brief-prelude-line">Calm city intelligence</span>
            </div>
            <h2 id="cityBriefTitle">San Fernando, framed as a city you can actually read.</h2>
            <p>This layer is now built to feel cleaner, smoother, and more premium. Use it for city details, tourism cues, and practical investing guidance without turning the experience into visual noise.</p>
            <div class="city-brief-actions">
              <a href="<?= htmlspecialchars(sfc_path('/property-ranking.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-primary">Open Investment Board</a>
              <a href="<?= htmlspecialchars(sfc_path('/property-explorer.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-secondary">Explore the City</a>
            </div>
            <div class="city-brief-stat-grid" aria-label="Brief overview">
              <article class="city-brief-stat-card">
                <span>City story</span>
                <strong>District context</strong>
                <p>Place, rhythm, and civic identity.</p>
              </article>
              <article class="city-brief-stat-card">
                <span>Visitor mood</span>
                <strong>Tourism cues</strong>
                <p>Scenic, cultural, and local highlights.</p>
              </article>
              <article class="city-brief-stat-card">
                <span>Investor lens</span>
                <strong>Practical guidance</strong>
                <p>Clarity before decisions get made.</p>
              </article>
            </div>
          </div>

          <article class="city-brief-spotlight">
            <div class="city-brief-spotlight-media">
              <img data-city-image-src="<?= htmlspecialchars($cityBriefImage, ENT_QUOTES, 'UTF-8') ?>" alt="San Fernando city view" decoding="async">
            </div>
            <div class="city-brief-spotlight-caption">
              <span class="panel-kicker">Signature View</span>
              <h3>Commerce, coastline, and city rhythm in one cleaner frame.</h3>
              <p>Use this space for your strongest city image and one short premium summary that sets the tone for everything below.</p>
              <div class="city-brief-spotlight-meta">
                <span>Source: sfcView.png</span>
                <span>Replace with real city copy later</span>
              </div>
            </div>
          </article>
        </section>

        <section class="city-brief-content">
          <article class="city-brief-panel city-brief-story-panel">
            <div class="city-brief-panel-head">
              <div>
                <div class="panel-kicker">City Details</div>
                <h3>The city story, reduced to the signals that matter.</h3>
              </div>
              <span class="city-brief-panel-pill">Placeholder copy</span>
            </div>
            <div class="city-brief-story-grid">
              <?php foreach ($cityStoryCards as $storyCard): ?>
                <div class="city-brief-story-card">
                  <span><?= htmlspecialchars((string) $storyCard['index'], ENT_QUOTES, 'UTF-8') ?></span>
                  <strong><?= htmlspecialchars((string) $storyCard['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                  <p><?= htmlspecialchars((string) $storyCard['summary'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
              <?php endforeach; ?>
            </div>
          </article>

          <aside class="city-brief-panel city-brief-tips-panel">
            <div class="city-brief-panel-head">
              <div>
                <div class="panel-kicker">How To Invest</div>
                <h3>Calm investing guidance that can later hold your real advice.</h3>
              </div>
              <span class="city-brief-panel-pill is-accent">Quiet strategy</span>
            </div>
            <div class="city-brief-tip-list">
              <?php foreach ($cityInvestmentTips as $tipCard): ?>
                <article class="city-brief-tip-card">
                  <span><?= htmlspecialchars((string) $tipCard['label'], ENT_QUOTES, 'UTF-8') ?></span>
                  <strong><?= htmlspecialchars((string) $tipCard['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                  <p><?= htmlspecialchars((string) $tipCard['summary'], ENT_QUOTES, 'UTF-8') ?></p>
                </article>
              <?php endforeach; ?>
            </div>
          </aside>
        </section>

        <section class="city-brief-gallery-band">
          <div class="city-brief-gallery-head">
            <div>
              <div class="panel-kicker">City Postcards</div>
              <h3>Picture-led tourist and city-spot cards that feel like a premium postcard wall.</h3>
            </div>
            <p>This lane is for scenic highlights, tourist spots, culture, food, and city mood. Every image, title, and story here is ready to be replaced with your real San Fernando guide later.</p>
          </div>
          <div class="city-brief-gallery-grid">
            <?php foreach ($citySpotCards as $spot): ?>
              <article class="city-postcard <?= !empty($spot['featured']) ? 'is-featured' : '' ?> <?= htmlspecialchars((string) ($spot['tone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <div class="city-postcard-media">
                  <img data-city-image-src="<?= htmlspecialchars((string) $spot['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($spot['alt'] ?? $spot['title']), ENT_QUOTES, 'UTF-8') ?>" loading="lazy" decoding="async">
                  <div class="city-postcard-topline">
                    <span class="city-postcard-badge"><?= htmlspecialchars((string) $spot['badge'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="city-postcard-badge is-quiet"><?= htmlspecialchars((string) $spot['metric'], ENT_QUOTES, 'UTF-8') ?></span>
                  </div>
                </div>
                <div class="city-postcard-body">
                  <div class="city-postcard-copy">
                    <span><?= htmlspecialchars((string) $spot['eyebrow'], ENT_QUOTES, 'UTF-8') ?></span>
                    <h4><?= htmlspecialchars((string) $spot['title'], ENT_QUOTES, 'UTF-8') ?></h4>
                    <p><?= htmlspecialchars((string) $spot['summary'], ENT_QUOTES, 'UTF-8') ?></p>
                  </div>
                  <div class="city-postcard-meta">
                    <span><?= htmlspecialchars((string) $spot['meta_primary'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span><?= htmlspecialchars((string) $spot['meta_secondary'], ENT_QUOTES, 'UTF-8') ?></span>
                  </div>
                  <div class="city-postcard-footer">
                    <span><?= htmlspecialchars((string) $spot['footer_label'], ENT_QUOTES, 'UTF-8') ?></span>
                    <strong><?= htmlspecialchars((string) $spot['footer_value'], ENT_QUOTES, 'UTF-8') ?></strong>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
      </div>
    </div>
  </div>
  <script src="https://unpkg.com/maplibre-gl@4.5.0/dist/maplibre-gl.js"></script>
  <script type="module" src="<?= htmlspecialchars($context['assetBase'], ENT_QUOTES, 'UTF-8') ?>/js/portal.js<?= htmlspecialchars(sfc_asset_version('js/portal.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
<?php
}
