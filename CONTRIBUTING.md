[Deutsch](CONTRIBUTING.de.md) · **English**

# Contributing

Thanks for your interest in contributing to this project.

Issues, pull requests and discussions are welcome in **English or German** — whichever you are more
comfortable with. The code, comments and commit messages in this repository are German; you are not
expected to match that.

## Setting up a development environment

```bash
git clone <repo-url>
cd dokuvault
composer install
npm install
cp .env.example .env
php artisan key:generate
# .env: enter your database credentials
php artisan migrate:fresh --seed   # creates demo data incl. the customer "Mustermann"
npm run dev
```

Demo logins after seeding: `admin` / `password` (admin), `techniker` / `password` (technician).

If you would rather not install PHP and Node at all, `docker compose up` gets you a running
instance — see the [README](README.md).

## Tests

```bash
php artisan test
```

The tests run against in-memory SQLite (see `phpunit.xml`), so no separate test database is needed.
Please make sure **all tests pass** before opening a pull request, and add tests for new features
(pattern: `tests/Feature/ComputerCrudTest.php`).

CI runs the same suite against PHP 8.2 and 8.3, each with SQLite and MariaDB.

## Code style

The project uses [Laravel Pint](https://laravel.com/docs/pint) (`pint.json`, Laravel preset):

```bash
./vendor/bin/pint --dirty
```

`--dirty` limits the run to the files you changed. CI checks with `./vendor/bin/pint --test` whether
the repository matches the standard — a contribution with different formatting turns the build red
rather than showing up later in someone else's diff.

## Translations

The interface is translatable. German is the source language: `__('Speichern')` returns the German
text when no translation exists, so nothing ever falls back to a placeholder.

- Wrap user-facing text in `__()`.
- Add the English wording to `lang/en.json`, keyed by the German text.
- Labels that come from `config/custom.php` are translated at the point of output, not in the config
  itself — the config is cached before the language is known.

A test makes sure no entry in `lang/en.json` is orphaned. A new language needs an entry in
`config/custom.php` under `locales` and a file `lang/<code>.json`.

## Changelog & versioning

The version is date-based (`YY.MM.DD`). Every change adds an entry `## YY.MM.DD` (today's date) at
the top of `changelog.md`, or extends today's entry — with the sections `### Added` / `### Changed` /
`### Fixed` / `### Internal`. Keep entries short: one or two sentences per point; the long reasoning
belongs in the commit message. The version shown in the footer is read from the topmost entry.

A release is a Git tag on the state that introduces the version, in the format `vYY.MM.DD` — so
`v26.08.02` for the changelog entry `## 26.08.02`. Older tags use other spellings; the format named
here is the one that counts.

```bash
git tag -a v26.08.02 -m "26.08.02" && git push github v26.08.02
```

What gets tagged is what someone should be able to install — not every changelog entry. Anyone
running DokuVault themselves can then pin a state instead of following `main`.

## Pull requests

- One topic per PR, with a short description (what and why).
- New object types follow the existing pattern (migration, model with `TracksChanges`, factory,
  policy, controller, FormRequest with the `BelongsToCustomer` rule, views, route, an entry in
  `config/custom.php` under `permissions` and, where applicable, `trashables`).
- Please do **not** report security issues as an issue — see [SECURITY.md](SECURITY.md).
