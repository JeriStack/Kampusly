# Contributing to Kampusly

**Kampusly — Your Campus. Your Classes. Your Time.**

This guide defines the branching strategy, commit message conventions, pull request workflow, code review expectations, and CI quality gates for the Kampusly development team.

---

## 1. Branching Strategy

Kampusly uses a **short-lived feature branch workflow** targeting `main` through Pull Requests.

### Branch Types & Naming Rules

| Branch Pattern | Purpose | Base Branch | Merge Target |
| :--- | :--- | :--- | :--- |
| `main` | Stable, reviewed, release-ready code. Protected branch; direct pushes are prohibited. | — | — |
| `develop` / `development` | Active integration and development branches used to stage and test ongoing work before merging into `main`. | `main` | `main` |
| `feature/<scope>-<slug>` | Individual feature implementation (e.g., `feature/schedules-conflict-engine`, `feature/auth-session-login`). | `development` / `develop` / `main` | `development` / `develop` / `main` |
| `fix/<scope>-<slug>` | Non-urgent bug fixes during development (e.g., `fix/navbar-mobile-backdrop`). | `main` | `main` |
| `hotfix/<scope>-<slug>` | Urgent fixes to released code on `main` (e.g., `hotfix/api-cors-origin`). | `main` | `main` |
| `docs/<slug>` | Documentation, architecture diagrams, or OpenAPI specification updates. | `main` | `main` |
| `chore/<slug>` | Infrastructure, Docker, dependency, or CI/CD maintenance. | `main` | `main` |

**Naming Rules:**
- Use lowercase `kebab-case` only (`a-z`, `0-9`, `-`).
- Keep branches focused on a single vertical slice or task so pull requests remain small and reviewable.
- Delete feature/fix branches automatically after their pull request is merged.

---

## 2. Commit Message Convention

All commits must follow the **Conventional Commits 1.0** format:

```text
<type>(<scope>): <imperative summary>

[optional body explaining the "why" and key implementation decisions]

[optional footer(s) referencing issues or breaking changes]
```

### Allowed `<type>` Values
- `feat` — New user-facing or API capability within the approved scope
- `fix` — Bug fix
- `docs` — Documentation or OpenAPI specification changes
- `style` — Formatting, whitespace, or CSS token adjustments without logic changes
- `refactor` — Internal code restructuring without changing external behavior
- `test` — Adding or updating Vitest / PHPUnit tests
- `ci` — GitHub Actions workflows and quality checks
- `chore` — Build scripts, Docker/Nginx config, or dependency maintenance
- `perf` — Performance optimization
- `revert` — Reverting a previous commit

### Recommended `<scope>` Values
`frontend`, `backend`, `landing`, `auth`, `users`, `academic`, `schedules`, `announcements`, `notices`, `notifications`, `pdf`, `db`, `docker`, `nginx`, `ci`, `docs`

### Examples
```text
feat(landing): build responsive landing page with light/dark theme support
docs(phase-2): complete UI/UX and system design specification package
chore(env): initialize native PHP PSR-4 backend, Docker stack, and CI workflows
fix(schedules): enforce strict half-open time interval check on room conflicts
```

---

## 3. Pull Request & Code Review Workflow

1. **Before Opening a PR:**
   - Rebase your branch onto the latest `origin/main` (`git fetch origin && git rebase origin/main`).
   - Run all frontend and backend verification commands locally:
     ```bash
     npm --prefix frontend run lint
     npm --prefix frontend run format:check
     npm --prefix frontend run type-check
     npm --prefix frontend run test
     npm --prefix frontend run build
     composer --working-dir=backend check
     ```
   - Verify `git status` to ensure no `.env` files, credentials, or temporary debug files are staged.
2. **Pull Request Requirements:**
   - Complete all sections of `.github/PULL_REQUEST_TEMPLATE.md`.
   - Reference the corresponding Phase and Requirement IDs (`FR-*`, `NFR-*`).
   - Keep PRs focused; do not bundle unrelated refactoring or unapproved scope additions.
3. **Code Review Expectations:**
   - Every PR requires **at least 1 approving review** from another team member or lead before merging.
   - Reviewers verify:
     - Alignment with Phase 1 requirements and Phase 2 database/API/UI specifications.
     - Server-side RBAC enforcement (`Admin`, `Instructor`, `Student`) and prepared PDO statements.
     - Edge-case and error handling (loading, empty, validation `422`, conflict `409`, and error states).
     - Test coverage for new domain rules or API endpoints.
4. **Merge Strategy:**
   - Use **Squash and Merge** into `main` with a clean Conventional Commit title and summary body to maintain a linear, readable Git history.

---

## 4. Branch Protection & Required CI Checks

Configure the following branch protection rules on `main` in GitHub (`Settings -> Branches -> Branch protection rules`):
- **Require a pull request before merging** (minimum 1 approval; dismiss stale approvals when new commits are pushed).
- **Require status checks to pass before merging** (require branches to be up to date before merging):
  - `Vue 3 + TypeScript Quality & Build` (`.github/workflows/frontend-ci.yml`)
  - `Native PHP 8.3 Quality, Tests & OpenAPI Contract` (`.github/workflows/backend-ci.yml`)
  - `Secret Hygiene, Docker Compose & Nginx Validation` (`.github/workflows/repo-hygiene.yml`)
- **Require conversation resolution before merging.**
- **Do not allow force pushes or branch deletions** on `main`.

---

## 5. Resolving Merge Conflicts & Handling Failing Changes

### Resolving Merge Conflicts
1. Fetch the latest upstream state:
   ```bash
   git fetch origin
   git rebase origin/main
   ```
2. Resolve conflicts file-by-file, preserving both approved schema/API contracts and existing functionality.
3. Stage resolved files (`git add <file>`) and continue the rebase (`git rebase --continue`).
4. Re-run the full local verification suite (`npm --prefix frontend run build`, `composer --working-dir=backend check`) before pushing with `git push --force-with-lease`.

### Handling Incomplete or Failing Changes
- **Work in Progress:** Open a **Draft Pull Request** so CI runs early while signaling that the branch is not ready to merge.
- **Failing CI Checks:** Never bypass or disable failing status checks. Fix the underlying lint, type, static-analysis, or test failure on the feature branch.
- **Broken Merge on `main`:** If a regression reaches `main`, immediately revert the merge commit via a PR or push a `hotfix/*` PR with a regression test.

