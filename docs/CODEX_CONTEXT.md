# LOCUS-SF: Codex Handoff Context

## Purpose

This document is the working handoff/context file for future Codex sessions.

Use it when a new chat or a new account needs fast context on:

- what the system is
- what has already been built
- how the UI is organized
- what APIs exist
- what integrations are already wired in
- which files matter most for future work

## Product Identity

LOCUS-SF is a San Fernando, La Union geospatial investment decision-support platform.

The current flagship capability is the CLUP Compliance and Suitability Engine. Every recommendation must be screened for land-use compatibility and return `PASS`, `CONDITIONAL`, or `FAIL`, together with a suitability score, explanation, and recommended LGU action. CLUP is a hard recommendation gate, not a decorative KPI.

Parcel-level CLUP evidence can be maintained by admins from the Area Intelligence Dossier. The stored profile includes existing land use, zoning classification, allowed/conditional/restricted uses, source reference, and verification timestamp. Interfaces distinguish `INFERRED`, `RECORDED`, and `VERIFIED` evidence. Inferred and recorded results remain preliminary until the LGU verifies them.

It is not just a generic property listing site. The product direction is:

- city investment intelligence
- property discovery and ranking
- community demand signaling
- due diligence and readiness tracking
- curated opportunity presentation
- role-based workflows for admin, seller, and investor/resident

The current homepage direction is a premium editorial "city thesis" interface:

- floating glass header
- cinematic dark hero
- oversized editorial typography
- live investment lens switching
- thesis cards and spatial trigger nodes
- premium preview sections for ranked opportunities, Offer Board, City Pipeline, and voting signals

## Stack and Runtime

- Apache via XAMPP
- PHP
- MySQL
- vanilla JavaScript
- CSS
- JSON seed/fallback data

Important runtime notes:

- this is no longer the old React/Vite/Node/Prisma stack
- the app is now PHP + MySQL with server-rendered PHP pages and client-side JS enhancement
- the local workspace path in this environment is `C:\xampp\htdocs\sfcelerate-bizstart`

## Roles in the System

### Guest

- can browse the public homepage
- can access public-facing opportunity pages
- can enter the platform through login pages

### Admin

- manages listings and platform inventory
- manages voting options
- manages showcase content
- sees admin dashboard
- can upload and curate Offer Board and City Pipeline entries
- can publish/unpublish showcase records

### Seller

- has a seller workspace
- manages owned or submitted listings
- tracks readiness and listing activity

### Investor / Resident

- explores properties
- compares opportunities
- votes on what the city needs
- uses ranking/explorer/detail flows

## Current Major Pages

### Public / shared pages

- `index.php`: premium editorial homepage / city thesis landing page
- `property-ranking.php`: live property ranking view
- `property-explorer.php`: explorer terminal with map and filters
- `property-details.php`: property detail / command-center style page
- `compare-decision.php`: compare and decision support page
- `voting-dashboard.php`: demand/voting view
- `offer-board.php`: curated offer showcase
- `city-pipeline.php`: curated future-development showcase

### Auth / role entry pages

- `admin-login.php`
- `seller-login.php`
- `investor-login.php`

### Role workspaces

- `admin-dashboard.php`
- `seller-dashboard.php`
- `investor-dashboard.php`

### Admin CRUD pages

- `admin-properties.php`: listing/property CRUD
- `admin-showcase.php`: Offer Board + City Pipeline CRUD studio

## Core Product Features Already Built

### 1. Property inventory and ranking

- property records are stored in MySQL and surfaced through PHP APIs
- properties can be ranked through investment lenses
- ranking logic is exposed on the homepage and the dedicated ranking page
- property cards show score, status, price, area, readiness, trust signals, and supporting context

### 2. Investment lens / thesis system

- the UI supports multiple investment lenses such as university, logistics, hospital, retail/commercial
- the homepage hero can switch lens focus live
- the landing hero also uses spatial trigger nodes such as Poro Point, City Center, and Civic Belt
- the homepage includes a presentation-style orbit mode that cycles through lens/node combinations

### 3. Property explorer

- dedicated explorer page with real map support
- Leaflet + OpenStreetMap integration is already wired
- property filtering/search is supported
- location search can use LocationIQ when configured
- there is a map fallback path when external services are unavailable

### 4. Property detail / command center

- property-specific data can be loaded through `property.php` and `property-command-center.php`
- the product supports a richer dossier/command-center style view per property

### 5. Compare and decision support

- investors can compare shortlisted properties
- decision-support UI exists to help choose between options

### 6. Voting / demand signaling

- investors/residents can vote on what a location needs
- vote options are admin-managed
- voting results feed into market demand signals
- the homepage now includes a premium voting-signal preview section

### 7. Due diligence

- property due diligence is stored and surfaced through the due-diligence API
- readiness/trust posture is part of the broader opportunity framing
- the system already uses document completeness, verification, and field-audit style signals in its UI language

### 8. Messaging and requests

- property messaging endpoints exist
- document request workflow exists
- visit log workflow exists
- notification endpoints/repositories exist

### 9. Shortlist / cart

- shortlist/cart support exists through API
- compare workflow builds on this behavior

### 10. Scenario saving

- scenario endpoints and repository support are implemented

### 11. Google Earth export

- Google Earth export/search support exists through `api/google-earth.php`

### 12. Audit and platform infrastructure

- audit log endpoint/repository exists
- notification repository exists
- supporting repositories exist for messages, scenarios, visits, documents, and users

## Showcase Module: New Feature Set Already Added

This is one of the most important recent additions.

### Naming direction

The old rough ideas were:

- "Bidding"
- "Not yet built"

These were replaced with better product names:

- `Offer Board`
- `City Pipeline`

### Product meaning

`Offer Board`

- admin-curated timed opportunities
- image-led premium cards
- can show countdowns, offer windows, or current offer labels
- not yet a full live user-bidding engine
- currently admin-managed CRUD with public display

`City Pipeline`

- admin-curated future-facing board
- used for planned, approved, under-construction, or not-yet-built establishments/properties/projects
- meant to surface what is coming next in the city

### Navigation model

- these new features are intentionally not in the main nav bar
- they live under the `More` / hamburger-style menu in the header

### Admin restriction

- only admin can create/edit/delete/publish showcase items
- sellers do not manage these boards

### Pages added

- `offer-board.php`
- `city-pipeline.php`
- `admin-showcase.php`

### Backend added

- `app/Repositories/ShowcaseRepository.php`
- `api/showcase.php`
- `api/showcase-item.php`

### Database/schema support added

- schema updates for showcase content
- seeding/bootstrapping support for showcase items

### Frontend support added

- homepage previews for both boards
- dedicated public board pages
- admin CRUD studio

## Homepage Redesign: Latest UI Direction

Another major recent change is the homepage transformation.

The old homepage was functionally useful but visually weaker.

The new homepage direction is:

- premium
- cinematic
- editorial
- "investment command desk"
- softer white shell outside
- dark thesis stage inside

### Hero now includes

- large editorial headline
- live focus summary
- ticker-style city intelligence strip
- lens selector chips
- orbit toggle
- proof cards
- analyst note
- thesis score card
- featured opportunity card
- spatial trigger dock

### Lower homepage sections now include

- top ranked opportunities preview
- voting signal preview
- role/workspace entry panel
- Offer Board preview
- City Pipeline preview
- city thesis / why-San-Fernando editorial panels
- upgraded final CTA

### Main files driving the homepage redesign

- `index.php`
- `assets/js/portal.js`
- `assets/css/portal.css`
- `app/Support/web.php`

## UI / Design Language

The current UI direction should be preserved in future work.

### Design intent

- not generic real-estate template design
- not plain admin dashboard styling
- should feel like a premium city-intelligence product

### Repeated visual patterns

- large rounded cards
- glassmorphism / translucent panels in dark hero areas
- strong typography hierarchy
- white/pearl outer shell with dark hero stage
- pill chips for state, filters, and labels
- image-led cards for showcase content
- gradual reveal and motion polish

### Important naming/UX decisions

- `Offer Board` and `City Pipeline` are the preferred names
- the homepage is framed as a city investment thesis, not just a list of properties
- admin-only showcase CRUD is a product rule, not just a UI detail

## API Surface

Current API directory:

- `audit-logs.php`
- `barangay.php`
- `bootstrap.php`
- `cart.php`
- `document-requests.php`
- `due-diligence.php`
- `external-ai-summary.php`
- `external-market.php`
- `external-news.php`
- `external-weather.php`
- `google-earth.php`
- `health.php`
- `location-search.php`
- `messages.php`
- `notifications.php`
- `properties.php`
- `property-command-center.php`
- `property.php`
- `scenarios.php`
- `showcase-item.php`
- `showcase.php`
- `visit-logs.php`
- `vote-options.php`
- `votes.php`

### API categories

#### Core property/data APIs

- `bootstrap.php`
- `properties.php`
- `property.php`
- `property-command-center.php`
- `barangay.php`

#### Voting and demand APIs

- `votes.php`
- `vote-options.php`

#### Due diligence / workflow APIs

- `due-diligence.php`
- `document-requests.php`
- `visit-logs.php`
- `messages.php`
- `notifications.php`
- `audit-logs.php`

#### Comparison / scenario / shortlist APIs

- `cart.php`
- `scenarios.php`

#### Showcase APIs

- `showcase.php`
- `showcase-item.php`

#### External/integration APIs

- `location-search.php`
- `external-market.php`
- `external-news.php`
- `external-weather.php`
- `external-ai-summary.php`
- `google-earth.php`

#### Health/bootstrap utilities

- `health.php`

## Repository Layer

Current repositories in `app/Repositories`:

- `AuditLogRepository.php`
- `DocumentRequestRepository.php`
- `MessageRepository.php`
- `NotificationRepository.php`
- `PropertyRepository.php`
- `ScenarioRepository.php`
- `ShortlistRepository.php`
- `ShowcaseRepository.php`
- `SpatialOverlayRepository.php`
- `UserRepository.php`
- `VisitLogRepository.php`
- `VoteOptionRepository.php`

These are the main backend data-access points a future Codex session should inspect before making structural changes.

## External Integrations and Fallback Behavior

Configured in `app/config.php` and service support code under `app/Support`.

### Integrated or prepared services

- OpenStreetMap tiles
- Leaflet maps
- LocationIQ
- Alpha Vantage
- NewsAPI
- OpenWeather
- Gemini
- OpenRouter
- Cloudinary

### Current design principle for external services

- integrations are optional
- the app should still function locally without all API keys
- seeded/fallback data and file caching are already part of the system design
- Cloudinary uploads fall back to local storage when unavailable

### Important support file

- `app/Support/ExternalServices.php`

## Data, Schema, and Bootstrapping

### Important database/support files

- `database/schema.sql`
- `database/seed.sql`
- `database/setup.sql`
- `app/Core/SchemaManager.php`
- `app/Support/AutoSeeder.php`

### Seed/fallback content sources

- `data/meta.json`
- `data/properties.json`
- `data/sample-data.json`

### Behavior

- the app can be initialized from SQL import
- the app also supports schema creation/bootstrap behavior in PHP
- first successful requests can seed core sample data when needed

## Most Important Frontend Files

### Global frontend control

- `assets/js/portal.js`
- `assets/js/api.js`
- `assets/css/portal.css`

### Layout/chrome

- `app/Support/web.php`

### Most important page templates

- `index.php`
- `property-ranking.php`
- `property-explorer.php`
- `property-details.php`
- `compare-decision.php`
- `voting-dashboard.php`
- `offer-board.php`
- `city-pipeline.php`
- `admin-properties.php`
- `admin-showcase.php`

## Most Important Backend Files

- `app/bootstrap.php`
- `app/config.php`
- `app/Core/Database.php`
- `app/Core/SchemaManager.php`
- `app/Support/helpers.php`
- `app/Support/ExternalServices.php`
- `app/Repositories/PropertyRepository.php`
- `app/Repositories/ShowcaseRepository.php`

## Current State of the System

At the moment, the project already includes:

- PHP/MySQL migration from the old stack
- role-based login and dashboards
- property ranking
- property explorer with map support
- property detail/command-center support
- compare/decision flow
- voting and vote-option CRUD
- due diligence
- messaging
- document request and visit-log infrastructure
- shortlist/cart support
- scenarios
- external services with fallback behavior
- Google Earth export support
- admin property CRUD
- new showcase module with Offer Board and City Pipeline
- admin-only Showcase Studio CRUD
- redesigned premium homepage with live thesis behavior

## Guidance for Future Codex Sessions

If a future Codex session needs to continue work, it should assume:

- the product is already substantially built
- the next work is usually refinement, extension, consistency, and polish, not greenfield setup
- the homepage visual direction should be treated as the new quality bar
- legacy marketplace-oriented modules remain in the repository but are intentionally de-emphasized in primary navigation
- Priority Board, Map Explorer, Area Intelligence Dossier, Compare Board, Scenario Simulator, and Reports must keep CLUP status visible
- admin-only showcase control is intentional and should be preserved unless explicitly changed

## Best Files to Open First in a New Session

Open these first for fast orientation:

- `README.md`
- `docs/CODEX_CONTEXT.md`
- `app/Support/web.php`
- `assets/js/portal.js`
- `assets/css/portal.css`
- `index.php`
- `app/Repositories/ShowcaseRepository.php`
- `api/showcase.php`
- `api/showcase-item.php`
- `database/schema.sql`

## Pasteable Prompt for a New Codex Chat

Use this if you want to bootstrap a new Codex chat quickly:

```text
This project is LOCUS-SF, a PHP/MySQL + vanilla JS city-opportunity platform running in XAMPP. It is not a generic property listing site; it is positioned as a San Fernando investment-intelligence platform with role-based flows for admin, seller, and investor/resident. Core features already implemented include property ranking, explorer/map workflow, property detail/command-center support, compare/decision flow, voting and vote-option CRUD, due diligence, shortlist/cart, scenarios, messaging, document requests, visit logs, notifications, Google Earth export, and optional external integrations with graceful fallbacks.

Recent major additions include a premium redesigned homepage and a new showcase module. The showcase module uses the names Offer Board and City Pipeline, lives under the More menu, and is admin-only for CRUD. Public pages exist for offer-board.php and city-pipeline.php, and admin-showcase.php is the CRUD studio. The homepage now has a cinematic editorial "city thesis" design with live lens switching, orbit behavior, ranked opportunity previews, showcase previews, and voting signal previews.

Before changing architecture, inspect README.md, docs/CODEX_CONTEXT.md, app/Support/web.php, assets/js/portal.js, assets/css/portal.css, index.php, app/Repositories/ShowcaseRepository.php, and the API files in /api.
```
