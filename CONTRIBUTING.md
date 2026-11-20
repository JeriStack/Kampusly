# Contributing to Kampusly

<div align="justify">

Thank you for contributing to **Kampusly** ([github.com/JeriStack/Kampusly](https://github.com/JeriStack/Kampusly)). This guide covers our branching rules, commit format, pull request workflow, and quality gates.

</div>

> **Community Standards & Security**
> - All contributors are expected to follow our [Code of Conduct](./CODE_OF_CONDUCT.md).
> - Never report security vulnerabilities in public issues or PRs — see [SECURITY.md](./SECURITY.md).

---

## Maintainers

- **Lead Maintainer:** Jeril Mark Estinsu
- **Core Maintainer:** Kevin Quijano

---

## 1. Local Setup

<div align="justify">

Follow [`docs/runbooks/local-setup.md`](./docs/runbooks/local-setup.md) to configure `.env` files, start Docker Compose, and apply database migrations.

</div>

---

## 2. Branching Strategy

<div align="justify">

Kampusly uses short-lived branches merged via Pull Requests. Direct pushes to `main` are prohibited.

</div>

| Branch Pattern | Purpose | Target |
| :--- | :--- | :--- |
| `main` | Stable, reviewed, release-ready code (protected). | — |
| `develop` / `development` | Integration branch for staging ongoing work before release. | `main` |
| `feature/<scope>-<slug>` | New feature work (e.g., `feature/schedules-conflict-engine`). | `development` / `main` |
| `fix/<scope>-<slug>` | Bug fixes (e.g., `fix/navbar-mobile-backdrop`). | `main` |
| `hotfix/<scope>-<slug>` | Urgent production fixes (e.g., `hotfix/api-cors-origin`). | `main` |
| `docs/<slug>` | Documentation or OpenAPI contract updates. | `main` |
| `chore/<slug>` | Docker, CI/CD, tooling, or dependency maintenance. | `main` |

**Rules:**
- Use lowercase `kebab-case` (`a-z`, `0-9`, `-`).
- Keep each branch focused on a single task so PRs stay small and reviewable.
- Delete branches after merge.

---

## 3. Commit Message Convention

All commits must follow **Conventional Commits 1.0**:

```text
<type>(<scope>): <imperative summary>

[optional body explaining the "why" and key decisions]
```

- **Types:** `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `ci`, `chore`, `perf`, `revert`
- **Common scopes:** `frontend`, `backend`, `landing`, `auth`, `users`, `academic`, `schedules`, `announcements`, `notices`, `notifications`, `pdf`, `db`, `docker`, `nginx`, `ci`, `docs`

**Examples:**
```text
feat(schedules): add room and instructor conflict validation
fix(auth): enforce active account check on session renewal
docs(database): align schema design spec with migrations 000600-000800
```

---

## 4. Pull Request & Review Process

### Before Opening a PR
1. Rebase onto the latest base branch:
   ```bash
   git fetch origin && git rebase origin/main
   ```
2. Run all verification checks locally:
   ```bash
   npm --prefix frontend run lint
   npm --prefix frontend run format:check
   npm --prefix frontend run type-check
   npm --prefix frontend run test
   npm --prefix frontend run build
   composer --working-dir=backend check
   ```
3. Check `git status` to ensure no `.env` files, secrets, or temporary artifacts are staged.

### PR Requirements
- Complete `.github/PULL_REQUEST_TEMPLATE.md` and reference relevant requirement IDs (`FR-*`, `NFR-*`) or issues.
- Keep schema and API changes aligned with [`docs/database/database-design.md`](./docs/database/database-design.md) and [`docs/api/openapi.yaml`](./docs/api/openapi.yaml).
- Open a **Draft PR** for work in progress.

### Code Review & Merging
- Every PR requires **at least 1 approving review** from a maintainer or teammate.
- Reviewers check requirements alignment, server-side RBAC enforcement, prepared PDO statements, error/edge-case handling, and test coverage.
- Use **Squash and Merge** with a clean Conventional Commit title.

---

## 5. Required CI Checks & Branch Protection

<div align="justify">

`main` requires all status checks to pass and all review conversations to be resolved before merging:

</div>

- `Vue 3 + TypeScript Quality & Build` (`.github/workflows/frontend-ci.yml`)
- `Native PHP 8.3 Quality, Tests & OpenAPI Contract` (`.github/workflows/backend-ci.yml`)
- `Secret Hygiene, Docker Compose & Nginx Validation` (`.github/workflows/repo-hygiene.yml`)

<div align="justify">

Never bypass failing checks. If a merge conflict occurs, rebase onto `origin/main`, resolve conflicts file-by-file, re-run the checks above, and push with `git push --force-with-lease`.

</div>
