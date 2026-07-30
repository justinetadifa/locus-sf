USE sfcelerate_bizstart;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE notifications;
TRUNCATE TABLE user_preferences;
TRUNCATE TABLE property_shortlists;
TRUNCATE TABLE property_votes;
TRUNCATE TABLE property_document_requests;
TRUNCATE TABLE visit_logs;
TRUNCATE TABLE property_messages;
TRUNCATE TABLE message_threads;
TRUNCATE TABLE investment_scenarios;
TRUNCATE TABLE spatial_overlays;
TRUNCATE TABLE property_due_diligence;
TRUNCATE TABLE property_media;
TRUNCATE TABLE vote_options;
TRUNCATE TABLE properties;
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO users (
  id, role, name, email, password_hash, identity_verification_status, identity_verified_at
) VALUES
  (1, 'admin', 'SFC Admin', 'admin@sfcelerate.local', '$2y$10$bjmoP9kI8cj05QgidqJ4LuA3wwainBr2mGNISIAq3rwN1fznDS2rq', 'verified', '2026-03-15 09:30:00'),
  (2, 'seller', 'Seller Studio', 'seller@sfcelerate.local', '$2y$10$UAfzvRYqvlwOIKQvm9LZOuwjrI/GcC5CsnQmxKY4RXXRAhOpCDIGq', 'verified', '2026-03-16 10:00:00'),
  (3, 'investor', 'Investor Resident Hub', 'investor@sfcelerate.local', '$2y$10$/LAguT1IF4Uh5AT4TQQtTeukBI5DDktSbVTGKFctsOjm/CnF2Znoa', 'unverified', NULL),
  (4, 'investor', 'Maria Santos', 'maria.santos@sfcelerate.local', '$2y$10$/LAguT1IF4Uh5AT4TQQtTeukBI5DDktSbVTGKFctsOjm/CnF2Znoa', 'unverified', NULL);

INSERT INTO user_preferences (user_id, notification_cadence) VALUES
  (1, 'instant'),
  (2, 'instant'),
  (3, 'instant'),
  (4, 'instant');

INSERT INTO vote_options (id, title, slug, description, image_url, is_active, sort_order, created_by_user_id) VALUES
  (1, '7/11', '7-11', 'Investor voting option for 7/11.', 'assets/images/vote-7-11.svg', 1, 1, 1),
  (2, 'PRINTING SHOP', 'printing-shop', 'Investor voting option for PRINTING SHOP.', 'assets/images/vote-printing-shop.svg', 1, 2, 1),
  (3, 'CAFE', 'cafe', 'Investor voting option for CAFE.', 'assets/images/vote-cafe.svg', 1, 3, 1),
  (4, 'RESORT AND TOURISM', 'resort-and-tourism', 'Investor voting option for RESORT AND TOURISM.', NULL, 1, 4, 1),
  (5, 'RESTAURANT OR FOOD PARK', 'restaurant-or-food-park', 'Investor voting option for RESTAURANT OR FOOD PARK.', NULL, 1, 5, 1),
  (6, 'PHARMACY', 'pharmacy', 'Investor voting option for PHARMACY.', 'assets/images/vote-pharmacy.svg', 1, 6, 1),
  (7, 'CLINIC OR DIAGNOSTICS', 'clinic-or-diagnostics', 'Investor voting option for CLINIC OR DIAGNOSTICS.', NULL, 1, 7, 1),
  (8, 'WAREHOUSE OR LOGISTICS', 'warehouse-or-logistics', 'Investor voting option for WAREHOUSE OR LOGISTICS.', NULL, 1, 8, 1),
  (9, 'OFFICE OR BPO', 'office-or-bpo', 'Investor voting option for OFFICE OR BPO.', NULL, 1, 9, 1),
  (10, 'HARDWARE AND CONSTRUCTION SUPPLY', 'hardware-and-construction-supply', 'Investor voting option for HARDWARE AND CONSTRUCTION SUPPLY.', NULL, 1, 10, 1),
  (11, 'GROCERY OR MINI MART', 'grocery-or-mini-mart', 'Investor voting option for GROCERY OR MINI MART.', NULL, 1, 11, 1);

INSERT INTO properties (
  id, name, city, lat, lng, area, price, price_per_sqm, status, approval_state, score, type, corridor,
  tags_json, facilities_json, road_access, image_url, description, barangay, owner_contact_json,
  documents_json, seller_user_id, documents_reviewed_at, site_verified_at, last_confirmed_available_at,
  dist_to_road_km, utility_status, zoning_score, assessed_value_sqm, readiness_notes
) VALUES
  (1, 'Fabro Building Prime Lot', 'San Fernando, La Union', 16.6195, 120.3205, 8.5, 75000000, 882, 'Available', 'approved', 91, 'logistics', 'highway', '["Highway Access","Commercial Zone","High Traffic"]', '["SM City","Provincial Capitol","Hospital"]', 95, 'assets/images/FabroBldg.png', 'Prime commercial lot with excellent highway access', 'Catbangen', '{"name":"Fabro Holdings","email":"fabroholdings@example.com","phone":"+63 917 000 0101","responseSla":"24 HOURS"}', '{"title_copy":"reviewed","tax_declaration":"reviewed","survey_plan":"reviewed","zoning_clearance":"reviewed","site_photos":"reviewed","hazard_report":"submitted"}', 2, '2026-03-24 09:00:00', '2026-03-22 13:30:00', '2026-03-30 08:15:00', 0.25, 'full_ready', 94, 811, 'Logistics-ready frontage with strong highway adjacency and mature utility coverage.'),
  (2, 'LaFinns Beach Resort Land', 'San Fernando, La Union', 16.618, 120.3195, 12.3, 95000000, 772, 'Available', 'approved', 88, 'hotel', 'coastal', '["Beachfront","Tourism Hub","Scenic Views"]', '["Beach Access","Resort Area","Airport 15km"]', 85, 'assets/images/LaFinns.png', 'Beachfront property perfect for resort development', 'Poro', '{"name":"LaFinns Resort Group","email":"lafinns@example.com","phone":"+63 917 000 0102","responseSla":"48 HOURS"}', '{"title_copy":"submitted","tax_declaration":"submitted","survey_plan":"submitted","zoning_clearance":"reviewed","site_photos":"reviewed","hazard_report":"requested"}', 2, NULL, '2026-03-18 16:10:00', '2026-03-29 11:20:00', 0.60, 'power_water', 88, 710, 'Tourism parcel is attractive but still needs hazard documentation and full service confirmation.'),
  (3, 'Feraren Commercial Complex', 'San Fernando, La Union', 16.621, 120.322, 6.7, 58000000, 866, 'Reserved', 'approved', 86, 'commercial', 'downtown', '["City Center","Retail Zone","Foot Traffic"]', '["Mall","Banks","Restaurants"]', 90, 'assets/images/FerarenProperty.png', 'Downtown commercial property with high foot traffic', 'Barangay II', '{"name":"Feraren Realty","email":"feraren@example.com","phone":"+63 917 000 0103","responseSla":"12 HOURS"}', '{"title_copy":"reviewed","tax_declaration":"reviewed","survey_plan":"submitted","zoning_clearance":"submitted","site_photos":"reviewed","hazard_report":"submitted"}', 2, '2026-03-11 15:45:00', NULL, '2026-03-12 10:30:00', 0.18, 'full_ready', 90, 797, 'Downtown location is institutionally strong, but final legal review is still uneven.'),
  (4, 'Property 1 - Industrial Zone', 'San Fernando, La Union', 16.6225, 120.3235, 15.2, 110000000, 724, 'Available', 'pending_review', 90, 'logistics', 'highway', '["Industrial","Warehouse Ready","Wide Lot"]', '["Port 5km","Highway","Power Station"]', 92, 'assets/images/Property1.png', 'Large industrial lot ideal for logistics operations', 'Pagdalagan', '{"name":"Northlink Industrial Assets","email":"northlink@example.com","phone":"+63 917 000 0104","responseSla":"24 HOURS"}', '{"title_copy":"submitted","tax_declaration":"submitted","survey_plan":"requested","zoning_clearance":"requested","site_photos":"submitted","hazard_report":"missing"}', 2, NULL, NULL, '2026-03-27 14:00:00', 0.42, 'partial', 78, 666, 'Industrial site still sits in pending review while documents are being assembled.'),
  (5, 'Property 3 - Tech Park Site', 'San Fernando, La Union', 16.617, 120.3185, 9.8, 82000000, 837, 'Available', 'approved', 87, 'bpo', 'highway', '["BPO Zone","Fiber Ready","Modern"]', '["University 2km","IT Park","Transport Hub"]', 88, 'assets/images/Property3.png', 'Modern site ready for BPO or tech development', 'Madaydegdeg', '{"name":"Innovate Land Corp","email":"innovate@example.com","phone":"+63 917 000 0105","responseSla":"18 HOURS"}', '{"title_copy":"submitted","tax_declaration":"submitted","survey_plan":"submitted","zoning_clearance":"submitted","site_photos":"reviewed","hazard_report":"submitted"}', 2, NULL, '2026-03-20 09:45:00', '2026-03-25 09:15:00', 0.35, 'full_ready', 86, 770, 'Tech-oriented site benefits from fiber-ready positioning but still needs economic benchmark confirmation.'),
  (6, 'Property 4 - Residential Development', 'San Fernando, La Union', 16.619, 120.3175, 7.5, 65000000, 867, 'Available', 'draft', 82, 'commercial', 'downtown', '["Residential","Subdivision Ready","Utilities"]', '["Schools","Shopping","Parks"]', 87, 'assets/images/Property4.png', 'Perfect for residential subdivision development', NULL, '{"name":"Residential Estates PH","email":"residential@example.com","phone":"+63 917 000 0106","responseSla":"24 HOURS"}', '{"title_copy":"missing","tax_declaration":"missing","survey_plan":"missing","zoning_clearance":"missing","site_photos":"submitted","hazard_report":"missing"}', 2, NULL, NULL, '2026-03-10 08:00:00', 1.10, 'limited', 61, 798, 'Draft listing with incomplete planning and legal inputs.'),
  (7, 'Property 5 - Mixed Use Complex', 'San Fernando, La Union', 16.62, 120.321, 11.2, 92000000, 821, 'Available', 'approved', 89, 'commercial', 'highway', '["Mixed Use","High ROI","Prime Location"]', '["Highway","Commercial District","Transit"]', 93, 'assets/images/Property5.png', 'Mixed-use development site with excellent returns', NULL, '{"name":"MixedUse Ventures","email":"mixeduse@example.com","phone":"+63 917 000 0107","responseSla":"24 HOURS"}', '{"title_copy":"reviewed","tax_declaration":"reviewed","survey_plan":"reviewed","zoning_clearance":"reviewed","site_photos":"reviewed","hazard_report":"reviewed"}', 2, '2026-03-19 10:10:00', '2026-03-17 14:20:00', '2026-03-29 17:00:00', 0.22, 'full_ready', 92, 755, 'Mixed-use site is one of the strongest all-around readiness cases in the seed set.'),
  (8, 'Property 6 - Coastal Development', 'San Fernando, La Union', 16.6165, 120.3165, 14.8, 125000000, 845, 'Available', 'approved', 84, 'hotel', 'coastal', '["Waterfront","Resort","Premium"]', '["Beach","Marina Potential","Scenic"]', 82, 'assets/images/Property6.png', 'Premium coastal property for luxury resort', 'Apaleng', '{"name":"Coastal Horizon Development","email":"coastal@example.com","phone":"+63 917 000 0108","responseSla":"48 HOURS"}', '{"title_copy":"submitted","tax_declaration":"submitted","survey_plan":"requested","zoning_clearance":"submitted","site_photos":"reviewed","hazard_report":"requested"}', 2, NULL, NULL, '2026-03-24 07:50:00', 0.85, 'partial', 76, 777, 'Coastal luxury play has upside, but infrastructure and legal risk remain visible.'),
  (9, 'Property 8 - Agriculture to Commercial', 'San Fernando, La Union', 16.6235, 120.3245, 18.5, 135000000, 730, 'Available', 'rejected', 85, 'logistics', 'highway', '["Large Lot","Convertible","Investment"]', '["Highway Access","Rural-Urban Edge"]', 85, 'assets/images/Property8.png', 'Large convertible lot at urban expansion zone', NULL, '{"name":"Expansion Belt Assets","email":"expansion@example.com","phone":"+63 917 000 0109","responseSla":"36 HOURS"}', '{"title_copy":"submitted","tax_declaration":"requested","survey_plan":"requested","zoning_clearance":"missing","site_photos":"submitted","hazard_report":"missing"}', 2, NULL, NULL, '2026-03-09 12:30:00', 0.55, 'limited', 58, 672, 'Listing rejected pending stronger institutional and zoning support.'),
  (10, 'Property 10 - Business Park Ready', 'San Fernando, La Union', 16.6175, 120.319, 10.5, 88000000, 838, 'Available', 'approved', 90, 'commercial', 'downtown', '["Business Park","Office Ready","Premium"]', '["CBD","Banks","Hotels Nearby"]', 91, 'assets/images/Property10.png', 'Ready for business park or office development', 'Barangay IV', '{"name":"Business Park Holdings","email":"businesspark@example.com","phone":"+63 917 000 0110","responseSla":"12 HOURS"}', '{"title_copy":"reviewed","tax_declaration":"reviewed","survey_plan":"submitted","zoning_clearance":"reviewed","site_photos":"reviewed","hazard_report":"submitted"}', 2, '2026-03-21 11:30:00', '2026-03-21 15:00:00', '2026-03-30 09:40:00', 0.14, 'full_ready', 93, 771, 'Business-park parcel is highly legible for office-led development and performs well across all pillars.');

INSERT INTO property_media (id, property_id, kind, source, alt_text, sort_order) VALUES
  (1, 1, 'image', 'assets/images/FabroBldg.png', 'Fabro Building Prime Lot listing image', 0),
  (2, 2, 'image', 'assets/images/LaFinns.png', 'LaFinns Beach Resort Land listing image', 0),
  (3, 3, 'image', 'assets/images/FerarenProperty.png', 'Feraren Commercial Complex listing image', 0),
  (4, 4, 'image', 'assets/images/Property1.png', 'Property 1 - Industrial Zone listing image', 0),
  (5, 5, 'image', 'assets/images/Property3.png', 'Property 3 - Tech Park Site listing image', 0),
  (6, 6, 'image', 'assets/images/Property4.png', 'Property 4 - Residential Development listing image', 0),
  (7, 7, 'image', 'assets/images/Property5.png', 'Property 5 - Mixed Use Complex listing image', 0),
  (8, 8, 'image', 'assets/images/Property6.png', 'Property 6 - Coastal Development listing image', 0),
  (9, 9, 'image', 'assets/images/Property8.png', 'Property 8 - Agriculture to Commercial listing image', 0),
  (10, 10, 'image', 'assets/images/Property10.png', 'Property 10 - Business Park Ready listing image', 0);

INSERT INTO property_due_diligence (property_id, state_json) VALUES
  (1, '{"title":true,"zoning":true,"survey":true,"rightofway":true,"utilities":true,"hazards":true,"environment":false,"permits":true,"tax":true,"valuation":true}'),
  (2, '{"title":true,"zoning":true,"survey":true,"rightofway":true,"utilities":true,"hazards":false,"environment":false,"permits":true,"tax":true,"valuation":false}'),
  (3, '{"title":true,"zoning":true,"survey":true,"rightofway":true,"utilities":true,"hazards":false,"environment":false,"permits":true,"tax":true,"valuation":true}'),
  (4, '{"title":true,"zoning":false,"survey":false,"rightofway":true,"utilities":true,"hazards":false,"environment":false,"permits":false,"tax":true,"valuation":false}'),
  (5, '{"title":true,"zoning":true,"survey":true,"rightofway":true,"utilities":true,"hazards":false,"environment":false,"permits":true,"tax":true,"valuation":true}'),
  (6, '{"title":false,"zoning":false,"survey":false,"rightofway":false,"utilities":true,"hazards":false,"environment":false,"permits":false,"tax":false,"valuation":false}'),
  (7, '{"title":true,"zoning":true,"survey":true,"rightofway":true,"utilities":true,"hazards":true,"environment":true,"permits":true,"tax":true,"valuation":true}'),
  (8, '{"title":true,"zoning":true,"survey":false,"rightofway":true,"utilities":true,"hazards":false,"environment":false,"permits":true,"tax":true,"valuation":false}'),
  (9, '{"title":true,"zoning":false,"survey":false,"rightofway":true,"utilities":false,"hazards":false,"environment":false,"permits":false,"tax":true,"valuation":false}'),
  (10, '{"title":true,"zoning":true,"survey":true,"rightofway":true,"utilities":true,"hazards":true,"environment":false,"permits":true,"tax":true,"valuation":true}');

INSERT INTO property_shortlists (id, investor_user_id, property_id) VALUES
  (1, 4, 1),
  (2, 4, 2);

INSERT INTO message_threads (id, property_id, investor_user_id, seller_user_id, subject, last_message_at) VALUES
  (1, 1, 4, 2, 'Fabro Lot Investor Thread', CURRENT_TIMESTAMP),
  (2, 2, 4, 2, 'LaFinns Resort Land Discussion', CURRENT_TIMESTAMP);

INSERT INTO property_messages (id, thread_id, property_id, sender_user_id, recipient_user_id, sender_name, role, text) VALUES
  (1, 1, 1, 4, 2, 'Maria Santos', 'investor', 'Requesting title documents and road-right-of-way confirmation.'),
  (2, 1, 1, 2, 4, 'Seller Studio', 'seller', 'Title copy is ready. We can share the survey plan and tax declaration next.'),
  (3, 1, 1, 1, NULL, 'SFC Admin', 'admin', 'Traffic and logistics fit remain strong. Due diligence is the current blocker.'),
  (4, 2, 2, 1, NULL, 'SFC Admin', 'admin', 'Tourism growth assumptions look attractive, but we need hazard screening.'),
  (5, 2, 2, 2, 4, 'Seller Studio', 'seller', 'Flood and environmental reports can be shared after the initial site visit.');

INSERT INTO visit_logs (
  id, property_id, thread_id, investor_user_id, seller_user_id, investment_purpose,
  primary_start_at, primary_end_at, secondary_start_at, secondary_end_at,
  counter_start_at, counter_end_at, confirmed_start_at, confirmed_end_at,
  started_at, visited_at, status, field_audit_json, ground_truth_multiplier, activity_json
) VALUES
  (
    1, 1, 1, 4, 2, 'University Campus',
    '2026-04-12 09:00:00', '2026-04-12 11:00:00', '2026-04-13 13:00:00', '2026-04-13 15:00:00',
    NULL, NULL, '2026-04-12 09:00:00', '2026-04-12 11:00:00',
    NULL, NULL, 'confirmed', NULL, NULL,
    '[{"kind":"proposed","title":"Site Visit Proposed","summary":"Maria Santos proposed primary and secondary windows for a University Campus thesis.","actorRole":"investor","actorName":"Maria Santos","status":"proposed","createdAt":"2026-04-01 09:05:00","primaryWindow":{"startAt":"2026-04-12 09:00:00","endAt":"2026-04-12 11:00:00"},"secondaryWindow":{"startAt":"2026-04-13 13:00:00","endAt":"2026-04-13 15:00:00"},"purpose":"University Campus"},{"kind":"confirmed","title":"Ground Truth Scheduled","summary":"Seller Studio confirmed the primary site visit window.","actorRole":"seller","actorName":"Seller Studio","status":"confirmed","createdAt":"2026-04-01 10:15:00","confirmedWindow":{"startAt":"2026-04-12 09:00:00","endAt":"2026-04-12 11:00:00"}}]'
  ),
  (
    2, 2, 2, 4, 2, 'Resort Due Diligence',
    '2026-03-21 09:00:00', '2026-03-21 11:00:00', '2026-03-22 13:00:00', '2026-03-22 15:00:00',
    NULL, NULL, '2026-03-22 13:00:00', '2026-03-22 15:00:00',
    '2026-03-22 13:05:00', '2026-03-22 15:20:00', 'visited',
    '{"neighborhood_vibe":4,"utility_proximity":4,"expansion_feasibility":5,"notes":"Visual check confirmed strong tourism frontage with expansion room beyond the current resort envelope."}',
    1.11,
    '[{"kind":"proposed","title":"Site Visit Proposed","summary":"Maria Santos proposed a resort-focused visit with backup windows.","actorRole":"investor","actorName":"Maria Santos","status":"proposed","createdAt":"2026-03-18 09:10:00","primaryWindow":{"startAt":"2026-03-21 09:00:00","endAt":"2026-03-21 11:00:00"},"secondaryWindow":{"startAt":"2026-03-22 13:00:00","endAt":"2026-03-22 15:00:00"},"purpose":"Resort Due Diligence"},{"kind":"confirmed","title":"Ground Truth Scheduled","summary":"Seller Studio confirmed the secondary site visit window.","actorRole":"seller","actorName":"Seller Studio","status":"confirmed","createdAt":"2026-03-18 10:40:00","confirmedWindow":{"startAt":"2026-03-22 13:00:00","endAt":"2026-03-22 15:00:00"}},{"kind":"in_progress","title":"Ground Truth In Motion","summary":"Seller Studio marked the walkthrough as underway.","actorRole":"seller","actorName":"Seller Studio","status":"in_progress","createdAt":"2026-03-22 13:05:00"},{"kind":"visited","title":"Visit Completed","summary":"Seller Studio marked the field walkthrough complete.","actorRole":"seller","actorName":"Seller Studio","status":"visited","createdAt":"2026-03-22 15:20:00"},{"kind":"field_audit","title":"Field Audit Submitted","summary":"Maria Santos submitted the ground-truth audit. IAI multiplier is now 1.11.","actorRole":"investor","actorName":"Maria Santos","status":"visited","createdAt":"2026-03-22 16:00:00","fieldAudit":{"neighborhood_vibe":4,"utility_proximity":4,"expansion_feasibility":5,"notes":"Visual check confirmed strong tourism frontage with expansion room beyond the current resort envelope."},"groundTruthMultiplier":1.11}]'
  );

INSERT INTO property_document_requests (
  id, property_id, requester_user_id, seller_user_id, requester_name, requester_role,
  document_name, note, status, response_note, resolved_at
) VALUES
  (1, 1, 4, 2, 'Maria Santos', 'investor', 'Certified true copy of title', 'Please share the latest annotated title copy and any lien disclosures.', 'fulfilled', 'Title copy and supporting annotation notes were prepared for the next review call.', '2026-03-24 15:30:00'),
  (2, 1, 4, 2, 'Maria Santos', 'investor', 'Survey plan with road right-of-way', 'Need the current survey and right-of-way sketch before site visit.', 'in_review', 'Survey plan is being cross-checked with the municipal engineer.', NULL),
  (3, 2, 1, 2, 'SFC Admin', 'admin', 'Hazard and flood screening report', 'Required before the listing can be marked fully reviewed for tourism investors.', 'requested', NULL, NULL);

INSERT INTO investment_scenarios (property_id, name, created_by, budget, sector, size, weights_json, assumptions_json, results_json) VALUES
  (1, 'Fabro Logistics Base Case', 'Maria Santos', 80000000, 'logistics', 8.5, '{"access":30,"facilities":25,"area":20,"price":15,"sector":10}', '{"horizon":5,"risk":"balanced","capex":12000000}', '{"recommendation":"strong fit","readiness":"high"}'),
  (2, 'LaFinns Tourism Upside', 'Denise Lim', 110000000, 'hotel', 10, '{"access":20,"facilities":20,"area":20,"price":10,"sector":30}', '{"horizon":7,"risk":"aggressive","capex":25000000}', '{"recommendation":"tourism-led upside","readiness":"medium"}');

INSERT INTO notifications (
  id, user_id, actor_user_id, property_id, thread_id, document_request_id, category, kind, priority, tone, icon,
  title, body, action_label, action_url, meta_json, is_read, read_at, created_at
) VALUES
  (1, 1, 2, 4, NULL, NULL, 'transactional', 'listing_submitted', 'high', 'system', 'shield', 'Listing Awaiting Review', 'Property 1 - Industrial Zone is still pending review and needs moderation before it can go live.', 'Review', '/admin-properties.php?edit=4', NULL, 0, NULL, '2026-04-01 08:10:00'),
  (2, 1, 4, 2, NULL, NULL, 'intelligence', 'market_heat', 'normal', 'trend', 'trend', 'Market Heat Update', 'Voting for logistics around Poro just spiked by 20%. Momentum is building in the demand queue.', 'Open Voting', '/voting-dashboard.php?property=2', NULL, 0, NULL, '2026-03-31 16:20:00'),
  (3, 2, 4, 1, 1, NULL, 'transactional', 'new_inquiry', 'high', 'info', 'chat', 'New Inquiry', 'Maria Santos is asking about the expansion potential and title packet for Fabro Building Prime Lot.', 'View', '/property-details.php?id=1', NULL, 0, NULL, '2026-04-01 07:42:00'),
  (4, 2, 1, 2, NULL, 3, 'operational', 'due_diligence_request', 'normal', 'system', 'file', 'Due Diligence Update', 'Hazard and flood screening is still requested for LaFinns Beach Resort Land. The tourism listing is waiting on supporting files.', 'Resolve', '/property-details.php?id=2', NULL, 0, NULL, '2026-03-31 11:15:00'),
  (5, 3, 2, 1, 1, NULL, 'transactional', 'seller_reply', 'high', 'info', 'chat', 'Seller Reply', 'Seller Studio replied on Fabro Building Prime Lot and is ready to share the survey plan next.', 'Open Thread', '/property-details.php?id=1', NULL, 0, NULL, '2026-04-01 08:30:00'),
  (6, 3, 1, 2, NULL, NULL, 'operational', 'site_visit_reminder', 'normal', 'system', 'site', 'Site Visit Reminder', 'Bring survey, hazard, and right-of-way notes before the next site visit at Poro.', 'Review Checklist', '/property-details.php?id=2', NULL, 0, NULL, '2026-03-31 09:05:00'),
  (7, 3, 2, 1, NULL, NULL, 'operational', 'due_diligence_progress', 'normal', 'system', 'pulse', 'Due Diligence Progress', 'Fabro Building Prime Lot has moved to 80% completion. Legal readiness is improving, but environmental validation is still open.', 'Review', '/property-details.php?id=1', NULL, 1, '2026-03-29 10:15:00', '2026-03-28 15:20:00'),
  (8, 4, 2, 1, 1, NULL, 'transactional', 'seller_reply', 'high', 'info', 'chat', 'Seller Reply', 'Seller Studio replied on Fabro Building Prime Lot and is ready to share the survey plan next.', 'Open Thread', '/property-details.php?id=1', NULL, 0, NULL, '2026-04-01 08:30:00'),
  (9, 4, 1, 2, NULL, NULL, 'operational', 'site_visit_reminder', 'normal', 'system', 'site', 'Site Visit Reminder', 'Bring survey, hazard, and right-of-way notes before the next site visit at Poro.', 'Review Checklist', '/property-details.php?id=2', NULL, 0, NULL, '2026-03-31 09:05:00'),
  (10, 4, 2, 1, NULL, NULL, 'operational', 'due_diligence_progress', 'normal', 'system', 'pulse', 'Due Diligence Progress', 'Fabro Building Prime Lot has moved to 80% completion. Legal readiness is improving, but environmental validation is still open.', 'Review', '/property-details.php?id=1', NULL, 1, '2026-03-29 10:15:00', '2026-03-28 15:20:00');

INSERT INTO audit_logs (
  id, actor_id, action_type, entity_type, entity_id, metadata, created_at
) VALUES
  (1, 1, 'APPROVE', 'PROPERTY', 4, '{"eventType":"LISTING_APPROVAL","targetLabel":"PROP_ID: #SFLU-004","summary":"Approval state changed from Pending Review to Approved.","badge":"VERIFIED","streamGroup":"moderation","changedFields":["approvalState"],"before":{"approvalState":"pending_review","name":"Property 1 - Industrial Zone"},"after":{"approvalState":"approved","name":"Property 1 - Industrial Zone"}}', '2026-04-02 10:24:12'),
  (2, 1, 'EDIT', 'PROPERTY', 1, '{"eventType":"DATA_EDIT","targetLabel":"PROP_ID: #SFLU-001","summary":"Price Per Sqm changed from PHP 847 / sqm to PHP 882 / sqm.","streamGroup":"financials","changedFields":["pricePerSqm","price"],"before":{"price":72000000,"pricePerSqm":847,"name":"Fabro Building Prime Lot"},"after":{"price":75000000,"pricePerSqm":882,"name":"Fabro Building Prime Lot"}}', '2026-04-02 09:15:01'),
  (3, 1, 'DELETE', 'MESSAGE', 1, '{"eventType":"MSG_RESOLVE","targetLabel":"THREAD: #1","summary":"Flagged inappropriate content and cleared the thread for review.","badge":"MODERATED","streamGroup":"moderation","changedFields":["messageCount"],"before":{"messageCount":3},"after":{"messageCount":0,"messagesCleared":3}}', '2026-04-02 08:05:44'),
  (4, 4, 'EDIT', 'VOTE', 2, '{"eventType":"VOTE_SIGNAL","targetLabel":"PROP_ID: #SFLU-002","summary":"Vote pulse moved to Warehouse Or Logistics for LaFinns Beach Resort Land.","streamGroup":"all","changedFields":["votes","selectedVoteOptionId"],"before":{"votes":{"WAREHOUSE OR LOGISTICS":2}},"after":{"votes":{"WAREHOUSE OR LOGISTICS":3},"selectedVoteOptionId":8}}', '2026-04-01 17:18:22');
