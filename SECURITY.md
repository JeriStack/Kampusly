# Security Policy

## Supported Versions

Security fixes are applied to the latest code on the `main` branch of [JeriStack/Kampusly](https://github.com/JeriStack/Kampusly).

| Version / Branch | Supported |
| :--- | :---: |
| `main` (latest) | Yes |
| Older snapshots / tags | No |

---

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues, pull requests, or discussions.**

Instead, report vulnerabilities privately to our security contact:
- **Security Contact:** [jerilpalin@gmail.com](mailto:jerilpalin@gmail.com)
- **Lead Maintainer:** Jeril Mark Estinsu
- **Core Maintainer:** Kevin Quijano

### What to Include
To help us investigate and resolve the issue quickly, please include:
- A clear description of the vulnerability and its potential impact.
- Step-by-step reproduction instructions or a minimal proof of concept.
- Affected endpoints, files, or components (e.g., auth/session handling, RBAC, SQL queries, file uploads, frontend XSS).
- Any suggested remediation or mitigation, if known.

---

## Response & Disclosure Process

- **Acknowledgment:** We aim to acknowledge receipt of your report within **48 hours**.
- **Triage & Updates:** We will assess severity and provide status updates as we work on a fix.
- **Coordinated Disclosure:** Please allow us reasonable time to develop, verify, and release a patch before publicly disclosing details.

---

## Security Practices for Contributors

Contributors must adhere to the following baseline security controls:
- **No Committed Secrets:** Never commit `.env` files, database passwords, SMTP credentials, API tokens, or private keys (enforced by `.github/workflows/repo-hygiene.yml`).
- **Prepared Statements:** Use parameterized PDO statements for all database queries; never interpolate user input into SQL.
- **Server-Side RBAC:** Enforce role-based access control (`admin`, `instructor`, `student`) on the backend for every protected route.
- **Session & CSRF Protection:** Preserve `HttpOnly`, `SameSite`, and CSRF token validation on all state-changing endpoints.
- **Input Validation & Output Encoding:** Validate and sanitize user-supplied data (including announcement markup and file uploads) and never trust client-only checks.
- **Local Binding in Development:** Keep Docker Compose development ports bound to `127.0.0.1`.
