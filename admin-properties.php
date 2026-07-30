<?php
declare(strict_types=1);

require __DIR__ . '/app/Support/web.php';

$context = sfc_web_context();
sfc_require_role('admin', sfc_path('/admin-login.php'));
$context = sfc_web_context();
sfc_render_head('Admin Listings | LOCUS-SF', $context, ['page' => 'admin-properties', 'role' => 'admin']);
sfc_render_header($context, 'admin-properties');
?>
<main class="page-shell admin-listings-page">
  <section class="site-shell page-intro-card">
    <div>
      <div class="eyebrow">Admin Listings</div>
      <div class="page-role-strip">
        <span class="page-role-pill is-role">Admin Workspace</span>
        <span class="page-role-pill">Inventory + trust controls</span>
      </div>
      <h1>Manage the live property inventory with more discipline.</h1>
      <p>Create, edit, and monitor listings inside a cleaner admin listing control surface.</p>
    </div>
    <div class="intro-actions">
      <button type="button" class="btn-shell btn-shell-primary" id="adminAddProperty">Add Property</button>
      <a href="<?= htmlspecialchars(sfc_path('/property-ranking.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-secondary">View Rankings</a>
    </div>
  </section>

  <section class="site-shell admin-properties-root-grid" id="adminPropertiesRoot">
    <div class="loading-panel">Loading admin listings...</div>
  </section>
</main>

<!-- ═══════════════════════════════════════════════
     EDIT / ADD PROPERTY MODAL — 4-Tab Interface
═══════════════════════════════════════════════ -->
<div class="modal-shell" id="propertyCrudModal" hidden>
  <div class="modal-card property-crud-modal" style="max-width:680px;border-radius:28px;padding:0;display:flex;flex-direction:column;max-height:92vh;overflow:hidden;background:#fff;box-shadow:0 32px 80px rgba(0,0,0,.22);">

    <!-- ── Header ── -->
    <div style="display:flex;align-items:flex-start;justify-content:space-between;padding:26px 28px 18px;border-bottom:1px solid #f1f5f9;flex-shrink:0;">
      <div>
        <div style="font-size:10px;font-weight:800;letter-spacing:.2em;text-transform:uppercase;color:#6366f1;margin-bottom:4px;">Admin Listing Editor</div>
        <h3 style="font-size:1.1rem;font-weight:800;color:#0f172a;margin:0;letter-spacing:-.02em;" id="crudModalTitle">Edit Property</h3>
        <p style="font-size:11px;color:#94a3b8;margin:3px 0 0;font-weight:500;" id="crudModalSubtitle">Complete all tabs before saving.</p>
      </div>
      <div style="display:flex;align-items:center;gap:5px;">
        <span id="crud-dot-0" style="width:7px;height:7px;border-radius:50%;background:#6366f1;display:inline-block;transition:all .25s;"></span>
        <span id="crud-dot-1" style="width:7px;height:7px;border-radius:50%;background:#e2e8f0;display:inline-block;transition:all .25s;"></span>
        <span id="crud-dot-2" style="width:7px;height:7px;border-radius:50%;background:#e2e8f0;display:inline-block;transition:all .25s;"></span>
        <span id="crud-dot-3" style="width:7px;height:7px;border-radius:50%;background:#e2e8f0;display:inline-block;transition:all .25s;"></span>
        <button type="button" data-modal-close="propertyCrudModal" style="margin-left:8px;width:30px;height:30px;border-radius:9px;background:#f1f5f9;border:none;cursor:pointer;font-size:14px;color:#64748b;display:flex;align-items:center;justify-content:center;" title="Close">&times;</button>
      </div>
    </div>

    <!-- ── Tab Switcher ── -->
    <div style="padding:14px 28px 0;flex-shrink:0;">
      <div style="display:flex;background:#f8fafc;border-radius:14px;padding:3px;gap:2px;">
        <button type="button" onclick="crudTab(0)" id="crud-tab-0" style="flex:1;padding:7px 4px;border-radius:11px;border:none;font-size:10px;font-weight:700;cursor:pointer;background:#fff;color:#4f46e5;box-shadow:0 1px 4px rgba(0,0,0,.07);transition:all .18s;">&#128196; Basic Info</button>
        <button type="button" onclick="crudTab(1)" id="crud-tab-1" style="flex:1;padding:7px 4px;border-radius:11px;border:none;font-size:10px;font-weight:600;cursor:pointer;background:transparent;color:#64748b;transition:all .18s;">&#128205; Location</button>
        <button type="button" onclick="crudTab(2)" id="crud-tab-2" style="flex:1;padding:7px 4px;border-radius:11px;border:none;font-size:10px;font-weight:600;cursor:pointer;background:transparent;color:#64748b;transition:all .18s;">&#128178; Financials</button>
        <button type="button" onclick="crudTab(3)" id="crud-tab-3" style="flex:1;padding:7px 4px;border-radius:11px;border:none;font-size:10px;font-weight:600;cursor:pointer;background:transparent;color:#64748b;transition:all .18s;">&#128737; Verification</button>
      </div>
    </div>

    <!-- ── Form ── -->
    <form id="propertyCrudForm" style="flex:1;overflow:hidden;display:flex;flex-direction:column;">
      <input type="hidden" id="crudPropertyId">

      <div style="flex:1;overflow-y:auto;padding:18px 28px 4px;" id="crud-panels-wrap">

        <!-- PANEL 0: BASIC INFO -->
        <div id="crud-panel-0" style="display:block;">
          <div style="display:grid;gap:13px;">

            <div>
              <label style="font-size:10px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:#64748b;display:block;margin-bottom:5px;">Property Name</label>
              <input class="input-shell" id="crudPropertyName" required
                style="width:100%;box-sizing:border-box;border-radius:11px;border:1px solid #e2e8f0;background:#f8fafc;padding:10px 13px;font-size:13px;font-weight:500;"
                oninput="document.getElementById('crudModalSubtitle').textContent = this.value || 'Complete all tabs before saving.'" />
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
              <div>
                <label style="font-size:10px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:#64748b;display:block;margin-bottom:5px;">City</label>
                <input class="input-shell" id="crudCity" value="San Fernando, La Union" required
                  style="width:100%;box-sizing:border-box;border-radius:11px;border:1px solid #e2e8f0;background:#f8fafc;padding:10px 13px;font-size:13px;font-weight:500;" />
              </div>
              <div>
                <label style="font-size:10px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:#64748b;display:block;margin-bottom:5px;">Barangay</label>
                <input class="input-shell" id="crudBarangay"
                  style="width:100%;box-sizing:border-box;border-radius:11px;border:1px solid #e2e8f0;background:#f8fafc;padding:10px 13px;font-size:13px;font-weight:500;" />
              </div>
            </div>

            <div>
              <label style="font-size:10px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:#64748b;display:block;margin-bottom:7px;">Property Type</label>
              <div id="seg-crud-type" style="display:flex;flex-wrap:wrap;gap:7px;">
                <button type="button" onclick="crudSeg('seg-crud-type','crudPropertyType',this,'commercial')" class="crud-seg-btn" data-val="commercial" style="padding:6px 13px;border-radius:999px;border:1px solid #6366f1;background:#eef2ff;color:#4338ca;font-size:11px;font-weight:700;cursor:pointer;">Commercial</button>
                <button type="button" onclick="crudSeg('seg-crud-type','crudPropertyType',this,'logistics')" class="crud-seg-btn" data-val="logistics" style="padding:6px 13px;border-radius:999px;border:1px solid #e2e8f0;background:#fff;color:#64748b;font-size:11px;font-weight:600;cursor:pointer;">Logistics</button>
                <button type="button" onclick="crudSeg('seg-crud-type','crudPropertyType',this,'hotel')" class="crud-seg-btn" data-val="hotel" style="padding:6px 13px;border-radius:999px;border:1px solid #e2e8f0;background:#fff;color:#64748b;font-size:11px;font-weight:600;cursor:pointer;">Resort / Tourism</button>
                <button type="button" onclick="crudSeg('seg-crud-type','crudPropertyType',this,'bpo')" class="crud-seg-btn" data-val="bpo" style="padding:6px 13px;border-radius:999px;border:1px solid #e2e8f0;background:#fff;color:#64748b;font-size:11px;font-weight:600;cursor:pointer;">Office / BPO</button>
                <button type="button" onclick="crudSeg('seg-crud-type','crudPropertyType',this,'manufacturing')" class="crud-seg-btn" data-val="manufacturing" style="padding:6px 13px;border-radius:999px;border:1px solid #e2e8f0;background:#fff;color:#64748b;font-size:11px;font-weight:600;cursor:pointer;">Manufacturing</button>
              </div>
              <select class="input-shell" id="crudPropertyType" style="position:absolute;opacity:0;pointer-events:none;width:1px;height:1px;" aria-hidden="true">
                <option value="commercial">Commercial</option><option value="logistics">Logistics</option><option value="hotel">Resort / Tourism</option><option value="bpo">Office / BPO</option><option value="manufacturing">Manufacturing</option>
              </select>
            </div>

            <div>
              <label style="font-size:10px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:#64748b;display:block;margin-bottom:7px;">City Corridor</label>
              <div id="seg-crud-corridor" style="display:flex;gap:7px;">
                <button type="button" onclick="crudSeg('seg-crud-corridor','crudCorridor',this,'highway')" class="crud-seg-btn" data-val="highway" style="padding:6px 13px;border-radius:999px;border:1px solid #6366f1;background:#eef2ff;color:#4338ca;font-size:11px;font-weight:700;cursor:pointer;">Highway</button>
                <button type="button" onclick="crudSeg('seg-crud-corridor','crudCorridor',this,'downtown')" class="crud-seg-btn" data-val="downtown" style="padding:6px 13px;border-radius:999px;border:1px solid #e2e8f0;background:#fff;color:#64748b;font-size:11px;font-weight:600;cursor:pointer;">Downtown</button>
                <button type="button" onclick="crudSeg('seg-crud-corridor','crudCorridor',this,'coastal')" class="crud-seg-btn" data-val="coastal" style="padding:6px 13px;border-radius:999px;border:1px solid #e2e8f0;background:#fff;color:#64748b;font-size:11px;font-weight:600;cursor:pointer;">Coastal</button>
              </div>
              <select class="input-shell" id="crudCorridor" style="position:absolute;opacity:0;pointer-events:none;width:1px;height:1px;" aria-hidden="true">
                <option value="highway">Highway</option><option value="downtown">Downtown</option><option value="coastal">Coastal</option>
              </select>
            </div>

            <div>
              <label style="font-size:10px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:#64748b;display:block;margin-bottom:5px;">Description</label>
              <textarea class="input-shell input-textarea" id="crudDescription" required rows="3"
                style="width:100%;box-sizing:border-box;border-radius:11px;border:1px solid #e2e8f0;background:#f8fafc;padding:10px 13px;font-size:13px;resize:none;"></textarea>
            </div>

          </div>
        </div><!-- /panel-0 -->

        <!-- PANEL 1: LOCATION / ASSETS -->
        <div id="crud-panel-1" style="display:none;">
          <div style="display:grid;gap:13px;">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
              <div>
                <label style="font-size:10px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:#64748b;display:block;margin-bottom:5px;">Tags</label>
                <input class="input-shell" id="crudTags" placeholder="Investor Ready, Strategic Location"
                  style="width:100%;box-sizing:border-box;border-radius:11px;border:1px solid #e2e8f0;background:#f8fafc;padding:10px 13px;font-size:13px;" />
              </div>
              <div>
                <label style="font-size:10px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:#64748b;display:block;margin-bottom:5px;">Facilities</label>
                <input class="input-shell" id="crudFacilities" placeholder="Highway Access, Utilities"
                  style="width:100%;box-sizing:border-box;border-radius:11px;border:1px solid #e2e8f0;background:#f8fafc;padding:10px 13px;font-size:13px;" />
              </div>
            </div>

            <div>
              <label style="font-size:10px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:#64748b;display:block;margin-bottom:5px;">Upload Image</label>
              <input type="file" class="input-shell" id="crudImage" accept="image/*"
                style="width:100%;box-sizing:border-box;border-radius:11px;border:1px solid #e2e8f0;background:#f8fafc;padding:9px 13px;font-size:13px;" />
            </div>

            <div>
              <label style="font-size:10px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:#64748b;display:block;margin-bottom:5px;">Fallback Image Path</label>
              <input class="input-shell" id="crudImagePath" value="assets/images/Property10.png"
                style="width:100%;box-sizing:border-box;border-radius:11px;border:1px solid #e2e8f0;background:#f8fafc;padding:10px 13px;font-size:13px;" />
            </div>

            <div style="border-radius:14px;border:1px dashed #c7d2fe;background:#eef2ff;padding:14px;display:flex;gap:12px;align-items:flex-start;">
              <span style="font-size:18px;flex-shrink:0;">&#128205;</span>
              <div>
                <p style="font-size:11px;font-weight:700;color:#4338ca;margin:0 0 3px;">Map Coordinates</p>
                <p style="font-size:11px;color:#6366f1;margin:0;">Coordinates are pinned via the property explorer map editor. Use Tags to reference nearby anchors.</p>
              </div>
            </div>

          </div>
        </div><!-- /panel-1 -->

        <!-- PANEL 2: FINANCIALS & METRICS -->
        <div id="crud-panel-2" style="display:none;">
          <div style="display:grid;gap:13px;">

            <div>
              <label style="font-size:10px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:#64748b;display:block;margin-bottom:5px;">Price (PHP)</label>
              <div style="position:relative;">
                <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:14px;font-weight:700;color:#94a3b8;">&#x20B1;</span>
                <input type="number" class="input-shell" id="crudPrice" required
                  style="width:100%;box-sizing:border-box;border-radius:11px;border:1px solid #e2e8f0;background:#f8fafc;padding:10px 13px 10px 26px;font-size:13px;font-weight:600;padding-right:118px;"
                  oninput="crudFormatPrice(this.value)" />
                <span id="crud-price-badge" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);padding:3px 9px;border-radius:7px;background:#f0fdf4;border:1px solid #bbf7d0;font-size:10px;font-weight:800;color:#15803d;white-space:nowrap;"></span>
              </div>
            </div>

            <div>
              <label style="font-size:10px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:#64748b;display:block;margin-bottom:7px;">Land Area</label>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div style="border-radius:12px;border:1px solid #c7d2fe;background:#eef2ff;padding:12px;">
                  <div style="font-size:9px;font-weight:800;letter-spacing:.13em;text-transform:uppercase;color:#6366f1;margin-bottom:5px;">Hectares (ha)</div>
                  <input type="number" step="0.0001" min="0.0001" class="input-shell" id="crudLandArea" inputmode="decimal" required
                    style="width:100%;box-sizing:border-box;border-radius:8px;border:1px solid #c7d2fe;background:#fff;padding:7px 10px;font-size:13px;font-weight:700;"
                    oninput="crudSyncArea(this.value,'ha')" />
                </div>
                <div style="border-radius:12px;border:1px solid #e2e8f0;background:#f8fafc;padding:12px;">
                  <div style="font-size:9px;font-weight:800;letter-spacing:.13em;text-transform:uppercase;color:#94a3b8;margin-bottom:5px;">Sq. Meters (sqm)</div>
                  <input type="number" step="1" id="crudLandAreaSqm"
                    style="width:100%;box-sizing:border-box;border-radius:8px;border:1px solid #e2e8f0;background:#fff;padding:7px 10px;font-size:13px;font-weight:700;"
                    oninput="crudSyncArea(this.value,'sqm')" />
                </div>
              </div>
              <div style="display:flex;align-items:center;gap:6px;margin-top:7px;padding:7px 11px;border-radius:9px;background:#f1f5f9;border:1px solid #e2e8f0;">
                <small class="form-helper" id="crudLandAreaHint" style="font-size:11px;color:#64748b;font-weight:500;">Use hectares directly. Sqm entries convert automatically on save.</small>
              </div>
              <input type="hidden" id="crudLandAreaUnit" value="ha" />
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
              <div>
                <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                  <label style="font-size:10px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:#64748b;">Market Score</label>
                  <span id="crud-score-display" style="font-size:10px;font-weight:800;color:#6366f1;background:#eef2ff;padding:2px 7px;border-radius:5px;">82</span>
                </div>
                <input type="number" min="40" max="100" class="input-shell" id="crudScore" value="82"
                  oninput="document.getElementById('crud-score-display').textContent = this.value"
                  style="width:100%;box-sizing:border-box;border-radius:11px;border:1px solid #e2e8f0;background:#f8fafc;padding:10px 13px;font-size:13px;" />
              </div>
              <div>
                <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                  <label style="font-size:10px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:#64748b;">Road Access</label>
                  <span id="crud-access-display" style="font-size:10px;font-weight:800;color:#7c3aed;background:#f5f3ff;padding:2px 7px;border-radius:5px;">85</span>
                </div>
                <input type="number" min="40" max="100" class="input-shell" id="crudAccess" value="85"
                  oninput="document.getElementById('crud-access-display').textContent = this.value"
                  style="width:100%;box-sizing:border-box;border-radius:11px;border:1px solid #e2e8f0;background:#f8fafc;padding:10px 13px;font-size:13px;" />
              </div>
            </div>

          </div>
        </div><!-- /panel-2 -->

        <!-- PANEL 3: VERIFICATION -->
        <div id="crud-panel-3" style="display:none;">
          <div style="display:grid;gap:13px;">

            <div>
              <label style="font-size:10px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:#64748b;display:block;margin-bottom:7px;">Listing Status</label>
              <div id="seg-crud-status" style="display:flex;gap:7px;flex-wrap:wrap;">
                <button type="button" onclick="crudSeg('seg-crud-status','crudStatus',this,'Available')" class="crud-seg-btn" data-val="Available" style="padding:8px 14px;border-radius:999px;border:1px solid #10b981;background:#ecfdf5;color:#065f46;font-size:11px;font-weight:700;cursor:pointer;">&#9679; Available</button>
                <button type="button" onclick="crudSeg('seg-crud-status','crudStatus',this,'Reserved')" class="crud-seg-btn" data-val="Reserved" style="padding:8px 14px;border-radius:999px;border:1px solid #e2e8f0;background:#fff;color:#64748b;font-size:11px;font-weight:600;cursor:pointer;">&#9680; Reserved</button>
                <button type="button" onclick="crudSeg('seg-crud-status','crudStatus',this,'Under Review')" class="crud-seg-btn" data-val="Under Review" style="padding:8px 14px;border-radius:999px;border:1px solid #e2e8f0;background:#fff;color:#64748b;font-size:11px;font-weight:600;cursor:pointer;">&#9680; Under Review</button>
                <button type="button" onclick="crudSeg('seg-crud-status','crudStatus',this,'Negotiating')" class="crud-seg-btn" data-val="Negotiating" style="padding:8px 14px;border-radius:999px;border:1px solid #e2e8f0;background:#fff;color:#64748b;font-size:11px;font-weight:600;cursor:pointer;">&#9672; Negotiating</button>
              </div>
              <select class="input-shell" id="crudStatus" style="position:absolute;opacity:0;pointer-events:none;width:1px;height:1px;" aria-hidden="true">
                <option value="Available">Available</option><option value="Reserved">Reserved</option><option value="Under Review">Under Review</option><option value="Negotiating">Negotiating</option>
              </select>
            </div>

            <div>
              <label style="font-size:10px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:#64748b;display:block;margin-bottom:7px;">Approval State</label>
              <div id="seg-crud-approval" style="display:flex;gap:7px;flex-wrap:wrap;">
                <button type="button" onclick="crudSeg('seg-crud-approval','crudApprovalState',this,'draft')" class="crud-seg-btn" data-val="draft" style="padding:8px 14px;border-radius:999px;border:1px solid #e2e8f0;background:#fff;color:#64748b;font-size:11px;font-weight:600;cursor:pointer;">Draft</button>
                <button type="button" onclick="crudSeg('seg-crud-approval','crudApprovalState',this,'pending_review')" class="crud-seg-btn" data-val="pending_review" style="padding:8px 14px;border-radius:999px;border:1px solid #e2e8f0;background:#fff;color:#64748b;font-size:11px;font-weight:600;cursor:pointer;">&#9711; Pending</button>
                <button type="button" onclick="crudSeg('seg-crud-approval','crudApprovalState',this,'approved')" class="crud-seg-btn" data-val="approved" style="padding:8px 14px;border-radius:999px;border:1px solid #6366f1;background:#eef2ff;color:#4338ca;font-size:11px;font-weight:700;cursor:pointer;">&#10003; Approved</button>
                <button type="button" onclick="crudSeg('seg-crud-approval','crudApprovalState',this,'rejected')" class="crud-seg-btn" data-val="rejected" style="padding:8px 14px;border-radius:999px;border:1px solid #e2e8f0;background:#fff;color:#64748b;font-size:11px;font-weight:600;cursor:pointer;">&#10007; Rejected</button>
                <button type="button" onclick="crudSeg('seg-crud-approval','crudApprovalState',this,'archived')" class="crud-seg-btn" data-val="archived" style="padding:8px 14px;border-radius:999px;border:1px solid #e2e8f0;background:#fff;color:#64748b;font-size:11px;font-weight:600;cursor:pointer;">Archived</button>
              </div>
              <select class="input-shell" id="crudApprovalState" style="position:absolute;opacity:0;pointer-events:none;width:1px;height:1px;" aria-hidden="true">
                <option value="draft">Draft</option><option value="pending_review">Pending Review</option><option value="approved">Approved</option><option value="rejected">Rejected</option><option value="archived">Archived</option>
              </select>
            </div>

            <div>
              <label style="font-size:10px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:#64748b;display:block;margin-bottom:7px;">Seller Verification</label>
              <div id="seg-crud-seller" style="display:flex;gap:7px;">
                <button type="button" onclick="crudSeg('seg-crud-seller','crudSellerIdentityStatus',this,'unverified')" class="crud-seg-btn" data-val="unverified" style="flex:1;padding:9px;border-radius:11px;border:1px solid #6366f1;background:#eef2ff;color:#4338ca;font-size:11px;font-weight:700;cursor:pointer;">&#9888; Unverified</button>
                <button type="button" onclick="crudSeg('seg-crud-seller','crudSellerIdentityStatus',this,'pending')" class="crud-seg-btn" data-val="pending" style="flex:1;padding:9px;border-radius:11px;border:1px solid #e2e8f0;background:#fff;color:#64748b;font-size:11px;font-weight:600;cursor:pointer;">&#9711; Pending</button>
                <button type="button" onclick="crudSeg('seg-crud-seller','crudSellerIdentityStatus',this,'verified')" class="crud-seg-btn" data-val="verified" style="flex:1;padding:9px;border-radius:11px;border:1px solid #e2e8f0;background:#fff;color:#64748b;font-size:11px;font-weight:600;cursor:pointer;">&#10003; Verified</button>
              </div>
              <select class="input-shell" id="crudSellerIdentityStatus" style="position:absolute;opacity:0;pointer-events:none;width:1px;height:1px;" aria-hidden="true">
                <option value="unverified">Unverified</option><option value="pending">Pending</option><option value="verified">Verified</option>
              </select>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
              <div>
                <label style="font-size:10px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:#64748b;display:block;margin-bottom:7px;">Documents Reviewed</label>
                <div id="seg-crud-docs" style="display:flex;gap:7px;">
                  <button type="button" onclick="crudYesNo('seg-crud-docs','crudDocumentsReviewed',this,'1',true)" class="crud-seg-btn" data-val="1" style="flex:1;padding:9px;border-radius:11px;border:1px solid #10b981;background:#ecfdf5;color:#065f46;font-size:11px;font-weight:700;cursor:pointer;">&#10003; Yes</button>
                  <button type="button" onclick="crudYesNo('seg-crud-docs','crudDocumentsReviewed',this,'0',false)" class="crud-seg-btn" data-val="0" style="flex:1;padding:9px;border-radius:11px;border:1px solid #e2e8f0;background:#fff;color:#64748b;font-size:11px;font-weight:600;cursor:pointer;">&#10007; No</button>
                </div>
                <select class="input-shell" id="crudDocumentsReviewed" style="position:absolute;opacity:0;pointer-events:none;width:1px;height:1px;" aria-hidden="true">
                  <option value="0">No</option><option value="1">Yes</option>
                </select>
              </div>
              <div>
                <label style="font-size:10px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:#64748b;display:block;margin-bottom:7px;">Site Verified</label>
                <div id="seg-crud-site" style="display:flex;gap:7px;">
                  <button type="button" onclick="crudYesNo('seg-crud-site','crudSiteVerified',this,'1',true)" class="crud-seg-btn" data-val="1" style="flex:1;padding:9px;border-radius:11px;border:1px solid #10b981;background:#ecfdf5;color:#065f46;font-size:11px;font-weight:700;cursor:pointer;">&#10003; Yes</button>
                  <button type="button" onclick="crudYesNo('seg-crud-site','crudSiteVerified',this,'0',false)" class="crud-seg-btn" data-val="0" style="flex:1;padding:9px;border-radius:11px;border:1px solid #e2e8f0;background:#fff;color:#64748b;font-size:11px;font-weight:600;cursor:pointer;">&#10007; No</button>
                </div>
                <select class="input-shell" id="crudSiteVerified" style="position:absolute;opacity:0;pointer-events:none;width:1px;height:1px;" aria-hidden="true">
                  <option value="0">No</option><option value="1">Yes</option>
                </select>
              </div>
            </div>

            <div>
              <label style="font-size:10px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:#64748b;display:block;margin-bottom:5px;">Last Confirmed Available</label>
              <input type="datetime-local" class="input-shell" id="crudLastConfirmedAvailableAt"
                style="width:100%;box-sizing:border-box;border-radius:11px;border:1px solid #e2e8f0;background:#f8fafc;padding:10px 13px;font-size:13px;" />
            </div>

            <fieldset style="border:1px solid #e2e8f0;border-radius:14px;padding:14px;margin:0;">
              <legend style="font-size:9px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:#64748b;padding:0 5px;">Document Checklist</legend>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-top:6px;">
                <?php foreach ([
                  ['crudDocTitleCopy',      'Title Copy'],
                  ['crudDocTaxDeclaration', 'Tax Declaration'],
                  ['crudDocSurveyPlan',     'Survey Plan'],
                  ['crudDocZoningClearance','Zoning Clearance'],
                  ['crudDocSitePhotos',     'Site Photos'],
                  ['crudDocHazardReport',   'Hazard Report'],
                ] as [$id, $label]): ?>
                <div>
                  <label style="font-size:10px;font-weight:700;color:#64748b;display:block;margin-bottom:3px;"><?= $label ?></label>
                  <select class="input-shell" id="<?= $id ?>" style="width:100%;border-radius:9px;border:1px solid #e2e8f0;background:#f8fafc;padding:6px 10px;font-size:12px;">
                    <option value="missing">Missing</option>
                    <option value="requested">Requested</option>
                    <option value="submitted">Submitted</option>
                    <option value="reviewed">Reviewed</option>
                  </select>
                </div>
                <?php endforeach; ?>
              </div>
            </fieldset>

            <p class="auth-form-note" style="font-size:11px;color:#94a3b8;margin:0;">Seller verification updates the linked seller account. Approval state and document review control listing trust badges and visibility.</p>

          </div>
        </div><!-- /panel-3 -->

      </div><!-- /crud-panels-wrap -->

      <!-- Fixed Footer -->
      <div style="display:flex;align-items:center;justify-content:space-between;padding:13px 28px;border-top:1px solid #f1f5f9;background:#fff;flex-shrink:0;">
        <div style="display:flex;gap:7px;align-items:center;">
          <button type="button" id="crud-btn-prev" onclick="crudNav(-1)"
            style="display:none;align-items:center;gap:5px;padding:8px 14px;border-radius:11px;border:1px solid #e2e8f0;background:#fff;color:#475569;font-size:11px;font-weight:600;cursor:pointer;">&larr; Back</button>
          <button type="button" class="btn-shell btn-shell-secondary" data-modal-close="propertyCrudModal"
            style="padding:8px 14px;border-radius:11px;font-size:11px;">Cancel</button>
        </div>
        <div style="display:flex;align-items:center;gap:7px;">
          <span id="crud-step-counter" style="font-size:10px;color:#94a3b8;font-weight:500;">Step 1 of 4</span>
          <button type="button" id="crud-btn-next" onclick="crudNav(1)"
            style="display:flex;align-items:center;gap:5px;padding:8px 16px;border-radius:11px;background:#4f46e5;color:#fff;border:none;font-size:11px;font-weight:700;cursor:pointer;">Next &rarr;</button>
          <button type="submit" id="crud-btn-save"
            style="display:none;align-items:center;gap:5px;padding:8px 16px;border-radius:11px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;border:none;font-size:11px;font-weight:700;cursor:pointer;">&#10003; Save Property</button>
        </div>
      </div>

    </form>
  </div>
</div>

<!-- Delete Modal (unchanged) -->
<div class="modal-shell" id="propertyDeleteModal" hidden>
  <div class="modal-card compact-modal">
    <div class="modal-head">
      <div>
        <div class="panel-kicker">Delete Listing</div>
        <h3 class="section-title">Remove this property?</h3>
      </div>
      <button type="button" class="modal-close" data-modal-close="propertyDeleteModal">Close</button>
    </div>
    <p class="panel-text" id="deletePropertyLabel">This will remove the selected property from MySQL.</p>
    <div class="crud-actions">
      <button type="button" class="btn-shell btn-shell-secondary" data-modal-close="propertyDeleteModal">Cancel</button>
      <button type="button" class="btn-shell btn-shell-danger" id="confirmDeleteProperty">Delete Property</button>
    </div>
  </div>
</div>

<!-- Tab / Segmented Control JS -->
<script>
(function () {
  var _tab = 0, _total = 4;

  window.crudTab = function (idx) {
    for (var i = 0; i < _total; i++) {
      var p = document.getElementById('crud-panel-' + i);
      var t = document.getElementById('crud-tab-' + i);
      var d = document.getElementById('crud-dot-' + i);
      if (p) p.style.display = (i === idx) ? 'block' : 'none';
      if (t) {
        t.style.background  = (i === idx) ? '#fff' : 'transparent';
        t.style.color       = (i === idx) ? '#4f46e5' : '#64748b';
        t.style.fontWeight  = (i === idx) ? '700' : '600';
        t.style.boxShadow   = (i === idx) ? '0 1px 4px rgba(0,0,0,.07)' : 'none';
      }
      if (d) d.style.background = (i === idx) ? '#6366f1' : '#e2e8f0';
    }
    _tab = idx;
    _updateFooter();
  };

  window.crudNav = function (dir) {
    var n = _tab + dir;
    if (n >= 0 && n < _total) crudTab(n);
  };

  function _updateFooter() {
    var prev    = document.getElementById('crud-btn-prev');
    var nxt     = document.getElementById('crud-btn-next');
    var save    = document.getElementById('crud-btn-save');
    var counter = document.getElementById('crud-step-counter');
    if (counter) counter.textContent = 'Step ' + (_tab + 1) + ' of ' + _total;
    if (prev)  prev.style.display  = _tab > 0              ? 'flex'  : 'none';
    if (nxt)   nxt.style.display   = _tab < _total - 1     ? 'flex'  : 'none';
    if (save)  save.style.display  = _tab === _total - 1   ? 'flex'  : 'none';
  }

  window.crudSeg = function (groupId, selectId, btn, val) {
    var grp = document.getElementById(groupId);
    if (!grp) return;
    grp.querySelectorAll('.crud-seg-btn').forEach(function (b) {
      b.style.background = '#fff'; b.style.color = '#64748b';
      b.style.border = '1px solid #e2e8f0'; b.style.fontWeight = '600';
    });
    btn.style.background = '#eef2ff'; btn.style.color = '#4338ca';
    btn.style.border = '1px solid #6366f1'; btn.style.fontWeight = '700';
    var sel = document.getElementById(selectId);
    if (sel) sel.value = val;
  };

  window.crudYesNo = function (groupId, selectId, btn, val, isYes) {
    var grp = document.getElementById(groupId);
    if (!grp) return;
    grp.querySelectorAll('.crud-seg-btn').forEach(function (b) {
      b.style.background = '#fff'; b.style.color = '#64748b';
      b.style.border = '1px solid #e2e8f0'; b.style.fontWeight = '600';
    });
    if (isYes) {
      btn.style.background = '#ecfdf5'; btn.style.color = '#065f46'; btn.style.border = '1px solid #10b981';
    } else {
      btn.style.background = '#fff1f2'; btn.style.color = '#9f1239'; btn.style.border = '1px solid #fca5a5';
    }
    btn.style.fontWeight = '700';
    var sel = document.getElementById(selectId);
    if (sel) sel.value = val;
  };

  window.crudFormatPrice = function (rawVal) {
    var badge = document.getElementById('crud-price-badge');
    if (!badge) return;
    var n = parseFloat(rawVal);
    if (!rawVal || isNaN(n)) { badge.textContent = ''; return; }
    var fmt;
    if      (n >= 1e9)  fmt = '\u20B1 ' + (n / 1e9).toFixed(2) + 'B PHP';
    else if (n >= 1e6)  fmt = '\u20B1 ' + (n / 1e6).toFixed(1) + 'M PHP';
    else if (n >= 1e3)  fmt = '\u20B1 ' + (n / 1e3).toFixed(1) + 'K PHP';
    else                fmt = '\u20B1 ' + n.toLocaleString() + ' PHP';
    badge.textContent = fmt;
  };

  window.crudSyncArea = function (val, fromUnit) {
    var n = parseFloat(val) || 0;
    var sqmEl = document.getElementById('crudLandAreaSqm');
    var haEl  = document.getElementById('crudLandArea');
    var hint  = document.getElementById('crudLandAreaHint');
    if (fromUnit === 'ha') {
      var sqm = Math.round(n * 10000);
      if (sqmEl) sqmEl.value = sqm || '';
      if (hint && n > 0) hint.textContent = n + ' ha = ' + sqm.toLocaleString() + ' sqm';
    } else {
      var ha = +(n / 10000).toFixed(4);
      if (haEl) haEl.value = ha || '';
      if (hint && n > 0) hint.textContent = n.toLocaleString() + ' sqm = ' + ha + ' ha';
    }
  };

  // Auto-sync segmented buttons when fillCrudForm sets select values.
  // fillCrudForm in portal.js sets .value on the hidden selects.
  // We observe the modal opening and re-sync.
  function _syncSegsFromSelects() {
    var pairs = [
      ['crudPropertyType',        'seg-crud-type'],
      ['crudCorridor',            'seg-crud-corridor'],
      ['crudStatus',              'seg-crud-status'],
      ['crudApprovalState',       'seg-crud-approval'],
      ['crudSellerIdentityStatus','seg-crud-seller'],
    ];
    pairs.forEach(function (pair) {
      var sel = document.getElementById(pair[0]);
      var grp = document.getElementById(pair[1]);
      if (!sel || !grp) return;
      var val = sel.value;
      grp.querySelectorAll('.crud-seg-btn').forEach(function (b) {
        b.style.background = '#fff'; b.style.color = '#64748b';
        b.style.border = '1px solid #e2e8f0'; b.style.fontWeight = '600';
      });
      var match = grp.querySelector('[data-val="' + val + '"]');
      if (match) {
        match.style.background = '#eef2ff'; match.style.color = '#4338ca';
        match.style.border = '1px solid #6366f1'; match.style.fontWeight = '700';
      }
    });
    // Yes/No pairs
    var ynPairs = [
      ['crudDocumentsReviewed', 'seg-crud-docs'],
      ['crudSiteVerified',      'seg-crud-site'],
    ];
    ynPairs.forEach(function (pair) {
      var sel = document.getElementById(pair[0]);
      var grp = document.getElementById(pair[1]);
      if (!sel || !grp) return;
      var isYes = sel.value === '1';
      grp.querySelectorAll('.crud-seg-btn').forEach(function (b) {
        b.style.background = '#fff'; b.style.color = '#64748b';
        b.style.border = '1px solid #e2e8f0'; b.style.fontWeight = '600';
      });
      var match = grp.querySelector('[data-val="' + sel.value + '"]');
      if (match) {
        if (isYes) {
          match.style.background = '#ecfdf5'; match.style.color = '#065f46'; match.style.border = '1px solid #10b981';
        } else {
          match.style.background = '#fff1f2'; match.style.color = '#9f1239'; match.style.border = '1px solid #fca5a5';
        }
        match.style.fontWeight = '700';
      }
    });
    // Refresh live displays
    var priceEl = document.getElementById('crudPrice');
    if (priceEl) crudFormatPrice(priceEl.value);
    var haEl = document.getElementById('crudLandArea');
    if (haEl && haEl.value) crudSyncArea(haEl.value, 'ha');
    var scoreEl = document.getElementById('crudScore');
    var sdEl = document.getElementById('crud-score-display');
    if (scoreEl && sdEl) sdEl.textContent = scoreEl.value;
    var accEl = document.getElementById('crudAccess');
    var adEl = document.getElementById('crud-access-display');
    if (accEl && adEl) adEl.textContent = accEl.value;
  }

  var _modal = document.getElementById('propertyCrudModal');
  if (_modal) {
    new MutationObserver(function () {
      if (!_modal.hidden) { crudTab(0); _syncSegsFromSelects(); }
    }).observe(_modal, { attributes: true, attributeFilter: ['hidden'] });
  }

  _updateFooter();
})();
</script>
<?php sfc_render_footer($context); ?>
