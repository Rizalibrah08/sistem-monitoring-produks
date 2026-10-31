# Aisha Monitor — Frontend Implementation Plan

## Approved context

The user approved frontend-first implementation following the PRD, SRS, and wireframes 01–48, with a healthcare-appropriate color palette. The existing DOCX files and wireframe images are reference materials and must not be modified.

**Goal:** A polished, responsive, interactive Indonesian-language frontend, running in Laravel with Inertia and React, using explicitly labeled demonstration data.

**Sources:**
- `docs/PRD Sistem Monitoring Pendaftaran Produk (2).docx`
- `docs/SRS Sistem Monitoring Pendaftaran Produk (1).docx`
- `Wireframe Sistem Monitoring Pendaftaran Produk (1)/`

## Architecture and decisions

- Laravel 13, Inertia React, TypeScript, Tailwind CSS, Lucide icons.
- Green herbal/teal brand, warm white surfaces, restrained amber and rose for warning/error statuses. Colors are the implementer's choice requested by the user, not an existing company brand specification.
- Components consume typed data and actions. A browser-memory preview provider supplies fixture data; no credentials, uploaded files, or private data are persisted to localStorage.
- Preview routes are available only in local/testing environments. The role selector is explicitly a demo tool, not authentication or backend RBAC.
- Routes map real page groups; tabs, dialogs, and native WhatsApp conversation examples are not forced into 48 separate web routes.
- Business state transitions are simulations. WhatsApp sends, PDF conversion, payment verification, TOPSIS scores, and audit integrity are not represented as real integrations.
- Required formats: PDF, JPG, PNG, DOCX, XLSX, maximum 20 MB per file. Client and inbox layouts support 360 px. UI language Indonesian, dates dd/mm/yyyy, timezone Asia/Jakarta.
- Sample ticket workflow: sample approval → signed/active contract → verified DP → pre-registration → accepted result → registration → NIE/closed ticket → outstanding final invoice.
- Existing open business decisions remain configurable/provisional and are identified in the README, rather than silently becoming production policy.

## Tasks

- [x] 1. Scaffold Laravel/Inertia, frontend test infrastructure, local-only preview routing.
- [x] 2. Typed fixtures, demo state/actions, tested document/payment/phase guard behavior.
- [x] 3. Healthcare design system, responsive app shell, navigation, search, role preview.
- [x] 4. Manager/Marketing/staff dashboards, priority queue, reports, escalations.
- [x] 5. Tickets, clients, prospects, creation and five ticket-detail tabs.
- [x] 6. Document upload/version history, QA/RA review, shared documents and checklist sync.
- [x] 7. Sample approval, contract preview/activation, invoice/proof/Finance workflows.
- [x] 8. BPOM submissions, pre-registration result, NIE and client token portal.
- [x] 9. Inbox, contacts, compose, notifications, attachment conversion and chatbot demo.
- [x] 10. Admin users, templates, audit and master-data tabs.
- [x] 11. Responsive/E2E/visual review, complete verification, usage documentation.

Each behavioral slice follows RED → GREEN → full-suite verification. Configuration and generated framework scaffolding are not treated as handwritten business behavior.

## Review focus

1. A role must not see irrelevant sensitive demo information; unknown backend permissions do not become claims of security.
2. Incomplete/conditional documents and missing payment/phase requirements block submission and explain why.
3. Expired/invalid client links disclose no ticket information.
4. Invalid file types, oversized files, empty revision reasons and stale versions preserve the user's work and display actionable errors.
5. Closing a ticket must not hide unpaid invoices or delete document history.

## Verification

- `npm run test`
- `npm run typecheck`
- `npm run build`
- `npm run test:e2e`
- `php artisan test`
- Desktop/mobile browser screenshots reviewed at 1440 px and 360 px.

## Deliberately not production-ready

Real authentication/RBAC, database persistence, private media storage, malware/content validation, WhatsApp gateway/webhooks, AHP/TOPSIS calculation, real Word/PDF generation, audit hash verification, scheduler, backups, and deployment remain backend/integration work.
