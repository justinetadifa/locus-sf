CREATE DATABASE IF NOT EXISTS sfcelerate_bizstart
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE sfcelerate_bizstart;

CREATE TABLE IF NOT EXISTS users (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  role VARCHAR(40) NOT NULL,
  name VARCHAR(140) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  identity_verification_status VARCHAR(40) NOT NULL DEFAULT 'unverified',
  identity_verified_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_users_email (email),
  KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_preferences (
  user_id INT NOT NULL PRIMARY KEY,
  notification_cadence VARCHAR(20) NOT NULL DEFAULT 'instant',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_preferences_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS properties (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  city VARCHAR(120) NOT NULL DEFAULT 'San Fernando, La Union',
  lat DECIMAL(10, 6) NOT NULL,
  lng DECIMAL(10, 6) NOT NULL,
  area DECIMAL(12, 4) NOT NULL,
  price BIGINT NOT NULL,
  price_per_sqm INT NOT NULL,
  status VARCHAR(80) NOT NULL,
  approval_state VARCHAR(40) NOT NULL DEFAULT 'approved',
  score INT NOT NULL DEFAULT 82,
  type VARCHAR(80) NOT NULL,
  corridor VARCHAR(80) NOT NULL,
  tags_json JSON NOT NULL,
  facilities_json JSON NOT NULL,
  road_access INT NOT NULL,
  image_url VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  barangay VARCHAR(120) NULL,
  owner_contact_json JSON NULL,
  documents_json JSON NULL,
  seller_user_id INT NULL,
  documents_reviewed_at TIMESTAMP NULL DEFAULT NULL,
  site_verified_at TIMESTAMP NULL DEFAULT NULL,
  last_confirmed_available_at TIMESTAMP NULL DEFAULT NULL,
  dist_to_road_km DECIMAL(8, 2) NULL,
  utility_status VARCHAR(40) NULL,
  zoning_score INT NULL,
  existing_land_use VARCHAR(180) NULL,
  zoning_classification VARCHAR(180) NULL,
  clup_allowed_uses_json JSON NULL,
  clup_conditional_uses_json JSON NULL,
  clup_restricted_uses_json JSON NULL,
  clup_source_reference VARCHAR(255) NULL,
  clup_verified_at TIMESTAMP NULL DEFAULT NULL,
  assessed_value_sqm INT NULL,
  readiness_notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_properties_seller_user (seller_user_id),
  KEY idx_properties_approval_state (approval_state),
  KEY idx_properties_last_confirmed_available (last_confirmed_available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clup_datasets (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  version_label VARCHAR(80) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'draft',
  source_agency VARCHAR(180) NULL,
  source_reference VARCHAR(255) NULL,
  source_url VARCHAR(500) NULL,
  effective_date DATE NULL,
  coordinate_reference_system VARCHAR(80) NULL,
  checksum_sha256 CHAR(64) NULL,
  notes TEXT NULL,
  activated_by_user_id INT NULL,
  activated_at TIMESTAMP NULL DEFAULT NULL,
  created_by_user_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_clup_dataset_version (name, version_label),
  KEY idx_clup_datasets_status (status),
  CONSTRAINT fk_clup_datasets_activated_by FOREIGN KEY (activated_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_clup_datasets_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clup_zones (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  dataset_id INT NOT NULL,
  zone_code VARCHAR(80) NOT NULL,
  name VARCHAR(180) NOT NULL,
  description TEXT NULL,
  geometry_type VARCHAR(40) NOT NULL DEFAULT 'Polygon',
  geometry_json JSON NULL,
  bbox_json JSON NULL,
  properties_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_clup_zone_code (dataset_id, zone_code),
  KEY idx_clup_zones_dataset (dataset_id),
  CONSTRAINT fk_clup_zones_dataset FOREIGN KEY (dataset_id) REFERENCES clup_datasets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clup_use_types (
  code VARCHAR(80) NOT NULL PRIMARY KEY,
  label VARCHAR(160) NOT NULL,
  category VARCHAR(100) NOT NULL,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clup_zone_use_rules (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  dataset_id INT NOT NULL,
  zone_id INT NOT NULL,
  use_code VARCHAR(80) NOT NULL,
  permission_status VARCHAR(30) NOT NULL,
  conditions_json JSON NULL,
  ordinance_reference VARCHAR(255) NULL,
  effective_date DATE NULL,
  expires_at DATE NULL,
  created_by_user_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_clup_zone_use_rule (zone_id, use_code),
  KEY idx_clup_rules_dataset (dataset_id),
  KEY idx_clup_rules_permission (permission_status),
  CONSTRAINT fk_clup_rules_dataset FOREIGN KEY (dataset_id) REFERENCES clup_datasets(id) ON DELETE CASCADE,
  CONSTRAINT fk_clup_rules_zone FOREIGN KEY (zone_id) REFERENCES clup_zones(id) ON DELETE CASCADE,
  CONSTRAINT fk_clup_rules_use FOREIGN KEY (use_code) REFERENCES clup_use_types(code) ON DELETE RESTRICT,
  CONSTRAINT fk_clup_rules_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clup_site_profiles (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  property_id INT NOT NULL,
  version_no INT NOT NULL,
  dataset_id INT NULL,
  zone_id INT NULL,
  existing_land_use VARCHAR(180) NULL,
  zoning_classification VARCHAR(180) NULL,
  allowed_uses_json JSON NULL,
  conditional_uses_json JSON NULL,
  restricted_uses_json JSON NULL,
  source_reference VARCHAR(255) NULL,
  source_document_path VARCHAR(500) NULL,
  review_status VARCHAR(30) NOT NULL DEFAULT 'draft',
  prepared_by_user_id INT NULL,
  submitted_at TIMESTAMP NULL DEFAULT NULL,
  reviewed_by_user_id INT NULL,
  reviewed_at TIMESTAMP NULL DEFAULT NULL,
  review_notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_clup_profile_version (property_id, version_no),
  KEY idx_clup_profiles_property_status (property_id, review_status),
  KEY idx_clup_profiles_dataset (dataset_id),
  CONSTRAINT fk_clup_profiles_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE RESTRICT,
  CONSTRAINT fk_clup_profiles_dataset FOREIGN KEY (dataset_id) REFERENCES clup_datasets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_clup_profiles_zone FOREIGN KEY (zone_id) REFERENCES clup_zones(id) ON DELETE RESTRICT,
  CONSTRAINT fk_clup_profiles_preparer FOREIGN KEY (prepared_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_clup_profiles_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clup_evidence_documents (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  profile_id INT NOT NULL,
  document_type VARCHAR(80) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  storage_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size BIGINT NOT NULL,
  checksum_sha256 CHAR(64) NOT NULL,
  uploaded_by_user_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_clup_evidence_profile (profile_id),
  UNIQUE KEY uniq_clup_evidence_checksum (profile_id, checksum_sha256),
  CONSTRAINT fk_clup_evidence_profile FOREIGN KEY (profile_id) REFERENCES clup_site_profiles(id) ON DELETE RESTRICT,
  CONSTRAINT fk_clup_evidence_uploader FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clup_review_events (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  profile_id INT NOT NULL,
  event_type VARCHAR(40) NOT NULL,
  from_status VARCHAR(30) NULL,
  to_status VARCHAR(30) NOT NULL,
  notes TEXT NULL,
  actor_user_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_clup_review_events_profile (profile_id, created_at),
  CONSTRAINT fk_clup_review_events_profile FOREIGN KEY (profile_id) REFERENCES clup_site_profiles(id) ON DELETE RESTRICT,
  CONSTRAINT fk_clup_review_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clup_evaluations (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  property_id INT NOT NULL,
  profile_id INT NULL,
  dataset_id INT NULL,
  proposed_use_code VARCHAR(80) NOT NULL,
  compliance_status VARCHAR(30) NOT NULL,
  suitability_score INT NULL,
  evidence_level VARCHAR(30) NOT NULL,
  engine_version VARCHAR(80) NOT NULL,
  input_snapshot_json JSON NOT NULL,
  result_snapshot_json JSON NOT NULL,
  evaluated_by_user_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_clup_evaluations_property (property_id, created_at),
  KEY idx_clup_evaluations_status (compliance_status),
  CONSTRAINT fk_clup_evaluations_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE RESTRICT,
  CONSTRAINT fk_clup_evaluations_profile FOREIGN KEY (profile_id) REFERENCES clup_site_profiles(id) ON DELETE RESTRICT,
  CONSTRAINT fk_clup_evaluations_dataset FOREIGN KEY (dataset_id) REFERENCES clup_datasets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_clup_evaluations_use FOREIGN KEY (proposed_use_code) REFERENCES clup_use_types(code) ON DELETE RESTRICT,
  CONSTRAINT fk_clup_evaluations_actor FOREIGN KEY (evaluated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clup_reports (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  report_number VARCHAR(80) NOT NULL,
  report_type VARCHAR(60) NOT NULL,
  title VARCHAR(255) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'generated',
  snapshot_json JSON NOT NULL,
  checksum_sha256 CHAR(64) NOT NULL,
  generated_by_user_id INT NULL,
  reviewed_by_user_id INT NULL,
  reviewed_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_clup_report_number (report_number),
  KEY idx_clup_reports_type_created (report_type, created_at),
  CONSTRAINT fk_clup_reports_generator FOREIGN KEY (generated_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_clup_reports_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS property_media (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  property_id INT NOT NULL,
  kind VARCHAR(40) NOT NULL DEFAULT 'image',
  source VARCHAR(255) NOT NULL,
  alt_text VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_property_media_property
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS property_due_diligence (
  property_id INT NOT NULL PRIMARY KEY,
  state_json JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_due_diligence_property
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vote_options (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(160) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  description VARCHAR(255) NULL,
  image_url VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_by_user_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_vote_options_slug (slug),
  KEY idx_vote_options_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS property_votes (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  property_id INT NOT NULL,
  vote_option_id INT NULL,
  voter_user_id INT NULL,
  label VARCHAR(160) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_property_votes_property (property_id),
  KEY idx_property_votes_label (label),
  KEY idx_property_votes_vote_option (vote_option_id),
  UNIQUE KEY uniq_property_votes_voter (property_id, voter_user_id),
  CONSTRAINT fk_property_votes_property
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_threads (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  property_id INT NOT NULL,
  investor_user_id INT NOT NULL,
  seller_user_id INT NULL,
  subject VARCHAR(190) NULL,
  last_message_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_message_threads_property_investor (property_id, investor_user_id),
  KEY idx_message_threads_seller (seller_user_id),
  KEY idx_message_threads_last_message (last_message_at),
  CONSTRAINT fk_message_threads_property
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS property_messages (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  thread_id INT NULL,
  property_id INT NOT NULL,
  sender_user_id INT NULL,
  recipient_user_id INT NULL,
  sender_name VARCHAR(120) NOT NULL,
  role VARCHAR(40) NOT NULL,
  text TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_property_messages_property (property_id),
  KEY idx_property_messages_thread (thread_id),
  KEY idx_property_messages_sender (sender_user_id),
  CONSTRAINT fk_property_messages_property
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS property_shortlists (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  investor_user_id INT NOT NULL,
  property_id INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_property_shortlists_investor_property (investor_user_id, property_id),
  KEY idx_property_shortlists_property (property_id),
  CONSTRAINT fk_property_shortlists_user
    FOREIGN KEY (investor_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_property_shortlists_property
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS property_document_requests (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  property_id INT NOT NULL,
  requester_user_id INT NULL,
  seller_user_id INT NULL,
  requester_name VARCHAR(140) NOT NULL,
  requester_role VARCHAR(40) NOT NULL,
  document_name VARCHAR(160) NOT NULL,
  note TEXT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'requested',
  response_note TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  resolved_at TIMESTAMP NULL DEFAULT NULL,
  KEY idx_document_requests_property (property_id),
  KEY idx_document_requests_seller (seller_user_id),
  KEY idx_document_requests_requester (requester_user_id),
  KEY idx_document_requests_status (status),
  CONSTRAINT fk_document_requests_property
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS visit_logs (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  property_id INT NOT NULL,
  thread_id INT NOT NULL,
  investor_user_id INT NOT NULL,
  seller_user_id INT NULL,
  investment_purpose VARCHAR(140) NOT NULL,
  primary_start_at DATETIME NOT NULL,
  primary_end_at DATETIME NOT NULL,
  secondary_start_at DATETIME NOT NULL,
  secondary_end_at DATETIME NOT NULL,
  counter_start_at DATETIME NULL,
  counter_end_at DATETIME NULL,
  confirmed_start_at DATETIME NULL,
  confirmed_end_at DATETIME NULL,
  started_at DATETIME NULL,
  visited_at DATETIME NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'proposed',
  field_audit_json JSON NULL,
  ground_truth_multiplier DECIMAL(5,2) NULL,
  activity_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_visit_logs_thread (thread_id),
  KEY idx_visit_logs_property_status (property_id, status),
  KEY idx_visit_logs_investor (investor_user_id),
  KEY idx_visit_logs_seller (seller_user_id),
  KEY idx_visit_logs_visited (visited_at),
  CONSTRAINT fk_visit_logs_property
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
  CONSTRAINT fk_visit_logs_thread
    FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
  CONSTRAINT fk_visit_logs_investor
    FOREIGN KEY (investor_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_visit_logs_seller
    FOREIGN KEY (seller_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  actor_user_id INT NULL,
  property_id INT NULL,
  thread_id INT NULL,
  document_request_id INT NULL,
  category VARCHAR(40) NOT NULL DEFAULT 'transactional',
  kind VARCHAR(80) NOT NULL DEFAULT 'update',
  priority VARCHAR(20) NOT NULL DEFAULT 'normal',
  tone VARCHAR(20) NOT NULL DEFAULT 'system',
  icon VARCHAR(24) NOT NULL DEFAULT 'signal',
  title VARCHAR(190) NOT NULL,
  body TEXT NOT NULL,
  action_label VARCHAR(60) NULL,
  action_url VARCHAR(255) NULL,
  meta_json JSON NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  read_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notifications_user_created (user_id, created_at),
  KEY idx_notifications_user_read (user_id, is_read),
  KEY idx_notifications_property (property_id),
  CONSTRAINT fk_notifications_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_notifications_actor
    FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_notifications_property
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  actor_id INT NULL,
  action_type VARCHAR(40) NOT NULL DEFAULT 'EDIT',
  entity_type VARCHAR(40) NOT NULL DEFAULT 'PROPERTY',
  entity_id INT NOT NULL,
  metadata JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_logs_created (created_at),
  KEY idx_audit_logs_entity (entity_type, entity_id),
  KEY idx_audit_logs_actor (actor_id),
  CONSTRAINT fk_audit_logs_actor
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_scenarios (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  property_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  created_by VARCHAR(120) NOT NULL DEFAULT 'Local Analyst',
  budget BIGINT NULL,
  sector VARCHAR(80) NULL,
  size DECIMAL(10, 2) NULL,
  weights_json JSON NULL,
  assumptions_json JSON NULL,
  results_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_investment_scenarios_property (property_id),
  CONSTRAINT fk_investment_scenarios_property
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS spatial_overlays (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  overlay_type VARCHAR(40) NOT NULL DEFAULT 'parcel',
  match_key VARCHAR(190) NULL,
  geometry_type VARCHAR(40) NOT NULL DEFAULT 'polygon',
  geometry_json JSON NULL,
  style_json JSON NULL,
  description TEXT NULL,
  property_id INT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_spatial_overlays_slug (slug),
  KEY idx_spatial_overlays_type_active (overlay_type, is_active),
  KEY idx_spatial_overlays_match_key (match_key),
  KEY idx_spatial_overlays_property (property_id),
  CONSTRAINT fk_spatial_overlays_property
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
