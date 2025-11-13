
# Contributing to umami Connect

Thank you for considering contributing! Your help in making this plugin better is appreciated.

## Quick Start

1. **Fork the repository**
2. Clone your fork and create a new branch from `main`:
   ```bash
   git clone https://github.com/YOUR-USERNAME/umami-wp-connect.git
   cd umami-wp-connect
   git checkout -b feature/your-feature-name
   ```
3. Make your changes
4. Push to your fork and open a pull request against `main`

---

## Code Style

### PHP
- Follow WordPress Coding Standards
- Use tabs for indentation (WordPress convention)
- Run PHPCS locally: `phpcs --standard=WordPress .` (see `.github/workflows/lint.yml` for inline config)
- PHPCS errors are blocking; warnings are advisory

### JavaScript
- Follow WordPress JavaScript Coding Standards
- Declare `wp` as a global when using WordPress APIs
- Use ESLint (see inline config in `.github/workflows/lint.yml`)

### General
- Write clear, descriptive variable and function names
- Add inline comments for complex logic
- Keep functions focused and single-purpose

---

## Commit Messages

Use **conventional commits** for clarity:

- `feat: add custom event tracking for forms`
- `fix: resolve autotrack issue with dynamic links`
- `chore: update dependencies`
- `docs: improve README installation steps`
- `refactor: simplify render_block filter`
- `test: add workflow for structure check`

**Format:** `<type>(<scope>): <description>`

---

## Pull Request Guidelines

- **Target branch:** `main`
- **Title:** Use conventional commit format (e.g., `feat: add consent gate option`)
- **Description:**
  - What changed and why
  - Related issues (e.g., `Closes #42`)
  - Screenshots or GIFs if UI-related
  - Breaking changes (if any)
- **Keep PRs focused:** one feature or fix per PR
- **CI checks:** All GitHub Actions workflows (Lint, Security, Syntax, Structure) must pass before merge
- **Respond to reviews:** address feedback promptly

---

## Branch Naming

- `feature/` — new features (e.g., `feature/consent-gate`)
- `bugfix/` — bug fixes (e.g., `bugfix/autotrack-links`)
- `chore/` — maintenance, tooling, CI (e.g., `chore/update-phpcs`)
- `docs/` — documentation only (e.g., `docs/readme-badges`)

---

## Need Help?

- Open an [issue](https://github.com/ceviixx/umami-wp-connect/issues) if you're stuck or have questions
- Join our [Discord](https://discord.gg/f46SeUS3jn) for quick help

---

## Code of Conduct

Be respectful, friendly, and constructive. Let's build something great together.