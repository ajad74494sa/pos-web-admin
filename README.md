# POS Web Admin

This repository contains the Laravel-based administration and point-of-sale application. It is based on Ultimate POS.

## Installation

See [readme.md](readme.md) for the application’s installation and documentation links. Configure database credentials, application keys, and integrations through your deployment environment; do not commit `.env` or other secrets.

## Repository contents

- Application source, modules, routes, and tests are version-controlled.
- Composer dependencies in `vendor/` are not checked in; install them with `composer install`.
- Environment-specific `.env` files and runtime contents of `storage/` (such as logs, sessions, and caches) are excluded from Git. Storage placeholder `.gitignore` files are tracked.

## Additional documentation

Project API and user guides are available in [`docs/`](docs/). The original product documentation links are listed in [readme.md](readme.md).
