# CLUP Source and Decision Governance

Last evidence review: **July 15, 2026**

Machine-readable registry: [`data/clup-source-registry.json`](../data/clup-source-registry.json)

## Purpose

This document governs how LOCUS-SF may use Comprehensive Land Use Plan (CLUP), zoning, amendment, parcel, and locational-clearance evidence. Its purpose is to make the compliance engine useful without presenting preliminary software analysis as an official government determination.

LOCUS-SF is a decision-support platform. It does not issue a locational clearance, zoning compliance certificate, building permit, land conversion approval, variance, exception, or other LGU authorization.

The governing notice for every result and export is:

> LOCUS-SF results are preliminary planning screens. Final zoning compatibility and locational clearance require review and action by the authorized LGU offices.

## Current Evidence Baseline

The following official documents were located and inspected:

| Evidence | Verified source | Current limitation |
| --- | --- | --- |
| Base zoning ordinance | [City Ordinance 2018-06](https://archives.sanfernandocity.gov.ph/upload/202009033E4NGDW9OUBLGLXKCYOT105312AM.pdf) | It relates to the 2015-2024 CLUP horizon. Current operative status and the complete amendment chain are not certified in this repository. |
| Parcel amendment | [City Ordinance 2020-21](https://archives.sanfernandocity.gov.ph/upload/20210531D6ACAFUWW6FMOJEIK85U142958PM.pdf) | Lot/title geometry, effectivity evidence, and provincial-review evidence have not been imported. |
| Parcel amendment | [City Ordinance 2025-16](https://archives.sanfernandocity.gov.ph/upload/20260305YLL3UF2JB63LJ5SNXUR7121425PM.pdf) | The source contains conflicting area figures; cadastral geometry and effectivity evidence have not been imported. |
| Official clearance workflow | [CPDO Citizens' Charter service](https://cc.sanfernandocity.gov.ph/2024/05/23/issuance-of-locational-clearances-zoning-compliance-certificate-for-buildings-structures-application/) | The workflow requires authorized review and site inspection that software cannot replace. |
| CLUP/ZO update activity | [Official City update announcement](https://www.sanfernandocity.gov.ph/city-government-holds-the-first-localized-handa-pilipinas-program-of-the-philippines/) and [Executive Orders index](https://www.sanfernandocity.gov.ph/executive-order/) | Update activity is not evidence of adoption, approval, effectivity, or publication of a replacement plan. |

The official [SP eArchives](https://archives.sanfernandocity.gov.ph/) is the source archive for the identified ordinances.

### Critical map limitation

**No authenticated official zoning map, zoning GIS dataset, georeferenced zoning raster, parcel zoning geometry, cadastral crosswalk, or authenticated overlay map has been imported into LOCUS-SF.**

City Ordinance 2018-06 states that duly authenticated Official Zoning Maps are integral to the ordinance. Until those maps are obtained and authenticated, the engine cannot establish a parcel's legal zoning from a Leaflet marker, OpenStreetMap, a road corridor, a barangay label, a geocoded address, or any locally drawn polygon.

OpenStreetMap and other basemaps may support navigation and visualization only. They must never be described as the official zoning map.

## What the Current Sources Establish

City Ordinance 2018-06 establishes an official textual zoning framework containing base zones, overlay zones, zone-specific uses, development controls, variance and exception provisions, administrative procedures, and a Local Zoning Board of Appeals.

The ordinance was enacted on June 20, 2018 and approved on June 29, 2018. It expressly connects its purpose to the City's CLUP for 2015-2024. The end of that planning horizon is a material currency warning, but it does not alone prove that the ordinance was repealed or ceased to operate.

Two official amendment copies were located:

- City Ordinance 2020-21 concerns Lot 17979, TCT FP-1573, Barangay Narra Oeste, with a stated area of 4.3079 hectares, changing Non-Strategic Agricultural Zone to Residential Zone 1.
- City Ordinance 2025-16 concerns Lots 15557, 15558, 15559, 15560, 15561, 15605, and 15606 under CAD 539-D in Barangay Namtutan, changing Agricultural Zone to Commercial Zone 1.

Neither amendment may be applied to every property in its barangay. Exact lot, title, cadastral, and authenticated geometry matching is required.

The two located amendments are not proof that the amendment record is complete.

## What Has Not Been Verified

The repository does not currently contain certified copies of:

- the complete adopted CLUP volumes;
- the adopting resolution and complete approval history;
- the duly authenticated Official Zoning Maps referenced by the ordinance;
- an official zoning GIS dataset with coordinate reference system and metadata;
- cadastral parcels and an authoritative lot/title crosswalk;
- all overlay and hazard maps with dates and legal provenance;
- the complete amendment and supersession register;
- the provincial review or approval record applicable to the component city;
- complete posting, publication, and effectivity records;
- a complete register of variances, exceptions, and Local Zoning Board of Appeals decisions;
- applicable Department of Agrarian Reform land-use conversion decisions; or
- evidence that the CLUP/ZO update underway through 2026 has been adopted and made effective.

No parcel-level result may conceal these missing records.

## Source Hierarchy

When evidence conflicts, use this order for product governance while requesting an official clarification:

1. Certified signed enactment and its signed operative provisions.
2. Duly authenticated ordinance maps and attachments incorporated by the enactment.
3. Certified amendment, supersession, approval, effectivity, posting, and publication records.
4. Written certification or case-specific decision from the authorized office.
5. Official Citizens' Charter and official agency guidance describing procedure.
6. Official announcements, session accounts, planning documents, and policy statements.

Items lower in this hierarchy may explain context but cannot silently modify an enacted rule. A draft, meeting account, investment-priority statement, or planning-team executive order must never be loaded as an active zoning rule.

Only official government sources should enter the authoritative registry. Secondary articles, property advertisements, crowd-sourced maps, investor presentations, and AI-generated summaries may be displayed as contextual intelligence only and must remain outside the compliance evidence chain.

## Evidence States

Every candidate-site evaluation should expose separate evidence states rather than compressing all confidence into one score.

| State | Meaning |
| --- | --- |
| `SOURCE_VERIFIED` | The official source document and relevant text were inspected. This alone does not establish parcel zoning. |
| `RULE_CHAIN_VERIFIED` | Base ordinance, all applicable amendments and supersession records, approval, and effectivity evidence are complete for the evaluation date. |
| `MAP_VERIFIED` | The applicable duly authenticated Official Zoning Map and its version are present. |
| `PARCEL_MATCHED` | The candidate site is matched to an authoritative lot/title/cadastral record and authenticated geometry. |
| `OVERLAYS_VERIFIED` | All applicable official overlay and hazard layers, dates, and rule sources are present. |
| `LGU_VERIFIED` | The authorized office has completed the case-specific review or issued the relevant written determination. |

The system must retain these as independent flags. For example, `SOURCE_VERIFIED` must not be promoted to `PARCEL_MATCHED` merely because a property marker appears inside a visually similar area.

## Decision Status Governance

### `UNVERIFIED`

`UNVERIFIED` is the mandatory default whenever evidence required to determine the parcel, rule version, exact proposed use, amendment, overlay, or current legal status is missing.

The public three-status display may still summarize planning fit, but it must not disguise `UNVERIFIED` as `PASS`, `CONDITIONAL`, or `FAIL`. If the product must preserve a three-status chart, evidence status must be shown as an equally prominent, blocking field.

### `PASS`

A preliminary `PASS` requires all of the following:

- the current rule package is verified;
- the authenticated official map is loaded;
- the candidate parcel is authoritatively matched;
- the exact proposed use is evaluated, not only a broad investment category;
- all applicable amendments and overlays are evaluated;
- the use is affirmatively allowed; and
- no known restriction or unmet mandatory condition defeats the screen.

`PASS` never means that a permit or locational clearance has been issued.

### `CONDITIONAL`

A preliminary `CONDITIONAL` result is appropriate only when the applicable ordinance allows the exact use subject to identifiable conditions or when a mandatory supporting clearance remains outstanding.

The following must not be converted into an automatic `CONDITIONAL` result:

- a prohibited use that might seek a variance;
- a request for an exception;
- proposed rezoning or reclassification;
- a possible future CLUP amendment;
- a pending Department of Agrarian Reform land-use conversion; or
- general investor interest in a strategic corridor.

These are separate legal or discretionary workflows, not conditions the engine can presume will be approved.

### `FAIL`

A preliminary `FAIL` requires a verified current rule that expressly prohibits or excludes the exact proposed use for the authenticated parcel and applicable overlays.

When the map, parcel match, or operative rule chain is uncertain, use `UNVERIFIED`; do not assert a legally conclusive failure.

## Suitability and Compliance Must Remain Separate

Suitability may consider access, utilities, market demand, investment priorities, strategic corridors, surrounding development, risk, and other planning factors. Compliance must evaluate the enacted zone, exact use, conditions, overlays, development controls, amendments, and other legally required clearances.

A high suitability score cannot override zoning incompatibility. Likewise, an allowed use does not automatically deserve a high investment-priority score.

Recommended ordering logic is:

1. Establish the evidence state.
2. Evaluate preliminary compliance only when evidence allows it.
3. Apply the compliance gate.
4. Calculate suitability and attractiveness separately.
5. Rank only within the class of sites that passed the evidence and compliance gates.

## Known Source Conflicts

### Citizens' Charter ordinance reference

The 2024 CPDO Citizens' Charter service page cites City Zoning Ordinance 2001-007. The official SP archive contains City Ordinance 2018-06 and amendments from 2020 and 2025.

The software must not silently choose between these records. It must flag the conflict and request a certified current-ordinance and amendment-chain statement from CPDO or OSSP, including repeal, supersession, effectivity, posting, publication, and provincial-review records.

### City Ordinance 2025-16 area

Official records contain three affected-area figures:

- 1.933 hectares in the signed operative provisions;
- 2.1917 hectares in the ordinance explanatory note; and
- 2.258394 hectares in the [official March 11, 2025 SP session account](https://sp.sanfernandocity.gov.ph/11th-regular-session-cy-2025-march-11-2025/).

The registry retains 1.933 hectares as the value stated in the signed operative provisions and preserves the other values as unresolved contradictions. Parcel geometry derived from this amendment remains blocked until the LGU supplies a certified cadastral plan or written clarification.

### Expired horizon and ongoing update

The 2018 ordinance refers to the 2015-2024 CLUP. Official City records show that an update was commissioned in 2024 and planning-team work continued through 2026. No official public adoption and effectivity package for a replacement was verified as of July 15, 2026.

LOCUS-SF must therefore show both facts: the source ordinance exists, and its current operative status and supporting map set still require certification.

## Official Locational-Clearance Boundary

The official CPDO workflow includes documentary evaluation, compatibility checking, site inspection and report, fee assessment where applicable, authorized decision processing, and certificate release. Requirements may include title or tax evidence, plans, barangay clearance, highway clearance, environmental compliance documents, structural materials, and authority from the landowner depending on the application.

The official sources are:

- [Buildings/structures service](https://cc.sanfernandocity.gov.ph/2024/05/23/issuance-of-locational-clearances-zoning-compliance-certificate-for-buildings-structures-application/)
- [Requirements PDF](https://cc.sanfernandocity.gov.ph/wp-content/uploads/2024/05/Issuance-of-Locational-ClearancesZoning-Compliance-Certificate-for-Buildings-Structures-REQUIREMENTS-CPDO.pdf)
- [Steps PDF](https://cc.sanfernandocity.gov.ph/wp-content/uploads/2024/05/Issuance-of-Locational-ClearancesZoning-Compliance-Certificate-for-Buildings-Structures-STEPS-CPDO.pdf)
- [New-business service](https://cc.sanfernandocity.gov.ph/2024/05/23/issuance-of-locational-clearances-zoning-compliance-certificates-for-new-business-applicant/)
- [New-business steps PDF](https://cc.sanfernandocity.gov.ph/wp-content/uploads/2024/05/Issuance-of-Locational-ClearancesZoning-Compliance-Certificates-for-New-Business-Applicant-STEPS-CPDO.pdf)

The official City directory lists CPDO at [City Departments](https://www.sanfernandocity.gov.ph/city-departments/). Contact details must be checked on that page before display or use.

## National Governance Context

LOCUS-SF must interpret local evidence consistently with, but never replace it by, the national framework:

- [Executive Order No. 72, s. 1993](https://lawphil.net/executive/execord/eo1993/eo_72_1993.html) addresses CLUP implementation through zoning ordinances and provincial review and approval for component cities and municipalities.
- [Republic Act No. 7160](https://lawphil.net/statutes/repacts/ra1991/ra_7160_1991.html) establishes relevant LGU powers concerning land-use planning, zoning, and reclassification.
- [Republic Act No. 8509](https://lawphil.net/statutes/repacts/ra1998/ra_8509_1998.html) establishes San Fernando as a component city under the Province of La Union.
- [Republic Act No. 11201](https://lawphil.net/statutes/repacts/ra2019/ra_11201_2019.html) establishes DHSUD and transfers relevant planning and zoning functions formerly associated with HLURB.
- [DHSUD CLUP manuals and guidebooks](https://dhsud.gov.ph/resources/manuals-guidebooks/) provide planning, sectoral-analysis, GIS, and model-zoning guidance.
- [DHSUD Memorandum Circular No. 2021-005](https://dhsud.gov.ph/wp-content/uploads/Laws_Issuances/06_Memorandum_Circulars/2021/Memorandum_Circular_No.2021-005.pdf) provides revised CLUP and Zoning Ordinance review and approval guidance.

The DHSUD Model Zoning Ordinance is guidance, not San Fernando's enacted zoning law. Its example use tables must not be copied into the engine as local legal rules unless the City's enacted ordinance contains the same rule.

Local zoning reclassification must also remain distinct from Department of Agrarian Reform land-use conversion. One must not be displayed as proof of the other.

## Required Evidence Acquisition

Before LOCUS-SF claims parcel-accurate compliance, obtain certified or otherwise authenticated copies of:

1. Complete adopted CLUP volumes and adopting resolution.
2. City Ordinance 2018-06 and every amendment, repeal, and supersession record.
3. Provincial review and approval resolution applicable to the component city.
4. Posting, publication, and effectivity evidence for the base ordinance and amendments.
5. Duly authenticated Official Zoning Maps referenced by the ordinance.
6. Official GIS data, where available, with coordinate reference system, scale, production date, custodian, accuracy statement, and authentication.
7. Official base-zone and overlay layers, including the hazard and special overlays applicable to the ordinance.
8. Cadastral parcels and an authoritative lot, title, tax declaration, and parcel-geometry crosswalk.
9. Certified metes-and-bounds or cadastral clarification for amendments with textual or area conflicts.
10. Complete variance, exception, and Local Zoning Board of Appeals decision registers.
11. Applicable Department of Agrarian Reform conversion records.
12. Written confirmation of whether the 2025-2026 CLUP/ZO update remains a draft or has been adopted, approved, published, and made effective.

Primary verification routes are CPDO, the Office of the Secretary to the Sangguniang Panlungsod, and the appropriate Province of La Union offices.

## Source Ingestion Requirements

Every production source package must store:

- source authority and custodian;
- direct official document URL;
- official document number and title;
- document, enactment, approval, effectivity, publication, archive-upload, and registry-verification dates as separate fields;
- cryptographic file checksum;
- plan horizon and map version;
- map authentication, scale, CRS, accuracy, and metadata where applicable;
- amendment and supersession relationships;
- parcel identifiers affected by amendments;
- extraction method and human verifier;
- unresolved contradictions; and
- the date on which the source was last revalidated.

An archive upload date must never be substituted for an enactment or effectivity date.

If a source disappears or changes, retain the checksum and provenance record, mark the link state, and require revalidation before rules are changed.

## Rule-Model Requirements

The rule engine must model the ordinance at a level that preserves legal meaning:

- base zone;
- overlay zones;
- exact ordinance-native use or activity;
- allowed, conditional, and restricted treatment;
- explicit conditions and documentary dependencies;
- density, bulk, setback, height, and other development controls where applicable;
- special-law or agency clearance dependencies;
- parcel-specific amendments;
- temporal validity and applicable rule version; and
- distinct variance, exception, rezoning, reclassification, and land-conversion workflows.

Broad investment labels such as "tourism," "logistics," or "mixed use" must be resolved into the exact proposed activities before a compliance screen is possible. One project may contain several uses requiring separate evaluation.

## Interface and Report Requirements

Priority Board, Map Explorer, Area Intelligence Dossier, Compare Board, Reports, and Scenario Simulator should all show:

- screening status;
- evidence status;
- rule-package version;
- source document links;
- parcel-match status;
- official-map status;
- applicable amendment and overlay status;
- unresolved contradictions;
- suitability score separately from compliance;
- recommended official next action; and
- the decision-support-only notice.

Reports must state whether the result was based on inferred, recorded, source-verified, map-verified, parcel-matched, or LGU-verified evidence. A color badge alone is insufficient.

The recommended LGU action should be concrete, such as "request CPDO zoning verification using the title and survey plan," rather than implying that LOCUS-SF has approved the proposal.

## Change Control

The current registry should be reviewed immediately when certified records are received or an updated CLUP or Zoning Ordinance is officially adopted. While the update remains active, review official sources at least quarterly.

No draft rule package may replace the active package merely because it is newer. Activation requires:

1. official adopted and enacted copies;
2. applicable provincial review and approval;
3. effectivity, posting, and publication evidence;
4. duly authenticated Official Zoning Maps;
5. the complete amendment and supersession chain; and
6. retained provenance and file checksums.

All changes to compliance rules should be reviewable through an audit log that records the previous rule, replacement rule, source package, reviewer, date, reason, and affected past evaluations.

## Minimum Quality Checks

Before a rule package is enabled, verify that:

- every active rule cites an official source and exact provision;
- every mapped zone traces to an authenticated official map version;
- every parcel-specific amendment requires an exact identifier match;
- all conflicting values remain visible until officially resolved;
- missing evidence returns `UNVERIFIED`;
- strategic-corridor scoring cannot override compliance;
- a variance or reclassification path never produces an automatic approval;
- reports preserve source and evidence state;
- the UI never calls a software result a permit, clearance, certificate, or official determination; and
- regression tests cover rule-version dates, parcel boundaries, overlays, amendments, and evidence degradation.

This governance model should remain stricter than the user interface. Visual polish must never obscure uncertainty in the underlying legal or geospatial evidence.
