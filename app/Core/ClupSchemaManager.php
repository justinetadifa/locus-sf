<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

final class ClupSchemaManager
{
    public static function ensure(PDO $pdo): void
    {
        foreach (self::statements() as $statement) {
            $pdo->exec($statement);
        }
        self::seedUseTypes($pdo);
    }

    private static function statements(): array
    {
        return [
            <<<'SQL'
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
)
SQL,
            <<<'SQL'
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
)
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS clup_use_types (
  code VARCHAR(80) NOT NULL PRIMARY KEY,
  label VARCHAR(160) NOT NULL,
  category VARCHAR(100) NOT NULL,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)
SQL,
            <<<'SQL'
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
)
SQL,
            <<<'SQL'
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
)
SQL,
            <<<'SQL'
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
)
SQL,
            <<<'SQL'
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
)
SQL,
            <<<'SQL'
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
)
SQL,
            <<<'SQL'
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
)
SQL,
        ];
    }

    private static function seedUseTypes(PDO $pdo): void
    {
        $items = [
            ['commercial', 'Commercial / Retail', 'commercial', 'Retail, service, and commercial-center development.'],
            ['logistics', 'Logistics / Warehousing', 'industrial', 'Warehousing, distribution, and logistics operations.'],
            ['hotel', 'Tourism / Hospitality', 'tourism', 'Hotels, resorts, and visitor-serving accommodation.'],
            ['bpo', 'Office / BPO', 'office', 'Office, business-process outsourcing, and knowledge-sector use.'],
            ['manufacturing', 'Light Manufacturing', 'industrial', 'Light industrial and manufacturing operations.'],
            ['mixed_use', 'Mixed-use Development', 'mixed_use', 'Integrated compatible residential, commercial, institutional, or office uses.'],
            ['hospital', 'Hospital / Healthcare Facility', 'institutional', 'Hospital, healthcare campus, and related medical-service use.'],
            ['university', 'University / Educational Institution', 'institutional', 'University, college, training-campus, and related educational use.'],
        ];
        $statement = $pdo->prepare(
            'INSERT INTO clup_use_types (code, label, category, description)
             VALUES (:code, :label, :category, :description)
             ON DUPLICATE KEY UPDATE label = VALUES(label), category = VALUES(category), description = VALUES(description)'
        );
        foreach ($items as [$code, $label, $category, $description]) {
            $statement->execute(compact('code', 'label', 'category', 'description'));
        }
    }
}
