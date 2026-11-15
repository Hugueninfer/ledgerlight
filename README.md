# Ledgerlight

[![CI](https://github.com/Hugueninfer/ledgerlight/actions/workflows/ci.yml/badge.svg)](https://github.com/Hugueninfer/ledgerlight/actions/workflows/ci.yml)

**Ledgerlight — AI you can verify.** A multi-tenant accounts-payable SaaS: an LLM extracts vendor invoice fields along with a confidence score, humans review only the uncertain ones, and approved invoices are exported to a deliberately unreliable accounting API.

**Status:** under construction.

## Docs

- [Design spec](docs/superpowers/specs/2026-11-15-ledgerlight-design.md)
- [Roadmap](docs/superpowers/plans/2026-11-15-roadmap.md)

## Getting started

Prerequisites: Docker with Compose v2.24+ and `make`. Node 22 and pnpm 9 are needed only to run the web lint and test targets on the host.

```bash
bash scripts/setup.sh   # enable git hooks
make setup              # env file, images, dependencies, app key, migrations, demo data
make up                 # start the stack
```

The app is served on <http://localhost:8088> (set `LEDGERLIGHT_HTTP_PORT` to change the port, and update `APP_URL` and `SANCTUM_STATEFUL_DOMAINS` in `api/.env` to match). Caddy routes `/api/*` and `/sanctum/*` to the Laravel API and everything else to the Nuxt app, so both share one origin.
