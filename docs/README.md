# Kampusly Documentation

<div align="justify">

Central index for all Kampusly project documentation, organized by category so contributors and maintainers can quickly locate requirements, architecture diagrams, design specifications, database schemas, API contracts, and operational runbooks.

</div>

---

## Requirements & Traceability (`docs/requirements/`)

| Document | Description |
| :--- | :--- |
| [functional-requirements.md](./requirements/functional-requirements.md) | Core functional requirements across Admin, Instructor, and Student roles. |
| [non-functional-requirements.md](./requirements/non-functional-requirements.md) | Security, performance, reliability, and maintainability requirements. |
| [acceptance-criteria.md](./requirements/acceptance-criteria.md) | Verification and acceptance criteria for MVP features. |
| [traceability-matrix.md](./requirements/traceability-matrix.md) | End-to-end mapping across requirements, architecture, schema, and endpoints. |

---

## Architecture (`docs/architecture/`)

| Document | Description |
| :--- | :--- |
| [system-architecture.md](./architecture/system-architecture.md) | Modular monolith architecture, bounded contexts, and deployment topology. |
| [uml-diagrams.md](./architecture/uml-diagrams.md) | System UML diagrams (use-case, sequence, class, and state flows). |
| `architecture-decisions/` | Directory reserved for Architecture Decision Records (ADRs). |

---

## Design & UI/UX (`docs/design/`)

| Document | Description |
| :--- | :--- |
| [design-system.md](./design/design-system.md) | Visual design tokens, typography, color palette, and component guidelines. |
| [ui-ux-specification.md](./design/ui-ux-specification.md) | Wireframes, page layouts, user flows, and interaction specifications. |

---

## Database (`docs/database/`)

| Document | Description |
| :--- | :--- |
| [database-design.md](./database/database-design.md) | Relational schema design, entities, relationships, and constraints. |
| [migrations-and-seeding.md](./database/migrations-and-seeding.md) | Migration naming conventions, rollback workflow, and seeder policies. |

---

## API Contracts (`docs/api/`)

| File | Description |
| :--- | :--- |
| [openapi.yaml](./api/openapi.yaml) | Active OpenAPI 3.1.0 specification served at `/api/v1/openapi.yaml` and `/api/v1/docs`. |

---

## Runbooks (`docs/runbooks/`)

| Document | Description |
| :--- | :--- |
| [local-setup.md](./runbooks/local-setup.md) | Local development setup, Docker Compose workflow, host CLI commands, and troubleshooting. |

---

## Repository Root Guides

<div align="justify">

These standard governance and onboarding files remain at the repository root for GitHub auto-discovery:

</div>
- [README.md](../README.md) — Project overview, stack, quick start, and commands.
- [CONTRIBUTING.md](../CONTRIBUTING.md) — Branching strategy, commit conventions, and PR workflow.
- [CODE_OF_CONDUCT.md](../CODE_OF_CONDUCT.md) — Community standards and enforcement guidelines.
- [SECURITY.md](../SECURITY.md) — Vulnerability disclosure policy and security practices.
- [.github/PULL_REQUEST_TEMPLATE.md](../.github/PULL_REQUEST_TEMPLATE.md) — Pull request checklist template.
