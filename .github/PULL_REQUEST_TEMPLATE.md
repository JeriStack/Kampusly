## Summary

Briefly describe what this pull request changes and why.

## Related Phase / Requirement IDs

- Phase: `Phase ___`
- Requirement / Task ID(s): `FR-___`, `NFR-___`

## Type of Change

- [ ] `feat` — New feature within approved MVP scope
- [ ] `fix` — Bug fix
- [ ] `docs` — Documentation update
- [ ] `refactor` — Code restructuring without behavior change
- [ ] `test` — Added or updated automated tests
- [ ] `ci` / `chore` — Tooling, infrastructure, or CI/CD configuration

## Verification & Quality Checklist

- [ ] **Scope Adherence:** Changes strictly follow the approved Phase 1 & Phase 2 specifications (no unapproved features introduced).
- [ ] **Security & RBAC:** Server-side authorization and input validation are enforced where applicable; no secrets or `.env` files are committed.
- [ ] **Frontend Checks Passed:** `npm --prefix frontend run lint`, `format:check`, `type-check`, `test`, and `build` succeed.
- [ ] **Backend Checks Passed:** `composer --working-dir=backend check` (`lint`, `cs`, `stan`, `test`) succeeds.
- [ ] **Database & API Contract:** Any schema or endpoint changes match `docs/phase-2/phase-2-database-design.md` and `docs/phase-2/phase-2-api-contract.yaml`.

