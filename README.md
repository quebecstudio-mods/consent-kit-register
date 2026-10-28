# Cookie Consent Kit register — `quebecstudio-mods/consent-kit-register`

The consent register shared by the Cookie Consent Kit integrations that keep
one: its schema, its storage and its queries.

| Integration | Package |
|---|---|
| Statamic 6 | `quebecstudio-mods/statamic-consent-kit` 6.x |

Requires PHP 8.2, `illuminate/database`, `illuminate/support` and
`quebecstudio-mods/consent-kit-core`. It holds no panel and no HTTP layer: an
integration decides what to record and how to show it, this keeps it.

`quebecstudio-mods/laravel-consent-kit` does not use it — it renders the
banner and leaves the register to the application. `craftcms-consent-kit` keeps
its own, in Craft CMS table and column conventions.

## Contents

| Path | What it is |
|---|---|
| `src/Connection.php` | Which database the register is kept in |
| `src/Register.php` | Storage and queries: store, page, find, purge |
| `database/migrations/` | The two tables, `consent_decisions` and `consent_presentations` |

## Schema

`consent_decisions` holds one row per reported answer: the site, the language,
the user when the site records one, what was pressed, where from, the
categories granted and the resulting outcome.

`consent_presentations` holds one row per distinct banner screen, keyed by the
fingerprint `Presentation` computes in the core. Decisions point at it, so a
screen shown a million times is stored once, and a stored decision can be
replayed against the exact wording it answered.

## Which database

`Connection::name()` resolves, in order:

1. `cookie-consent.register.connection`, when the site names one;
2. the site's own default connection, when it opens;
3. a SQLite file in `storage/cookie-consent/register.sqlite`, declared and
   migrated by `Connection::migrate()`.

## Using it

```php
use QuebecStudioMods\ConsentKit\Register\Connection;
use QuebecStudioMods\ConsentKit\Register\Register;

$register = new Register(new Connection());

$register->store([
    'siteHandle' => 'default',
    'userId' => null,
    'language' => 'en',
    'decidedAt' => '2026-09-23 14:02:11',
    'action' => 'save',
    'origin' => 'dialog',
    'categories' => ['necessary', 'analytics'],
    'outcome' => 'partial',
    'consentVersion' => 3,
    'presentationId' => $register->presentationId($presentation),
]);
```

Reading a page, either from plain filters or from a query the integration has
already filtered:

```php
['rows' => $rows, 'total' => $total] = $register->page(['site' => 'default'], page: 1, perPage: 50);

$rows = $register->rows($register->query()->where('d.outcome', 'all'), 1, 50, 'decidedAt', 'desc');
```

Retention is the core's: `Registry::cutoff()` gives the date beyond which a
record has outlived what it attests, and `$register->purge($cutoff)` deletes
those rows, then the screens no decision points at any more.

## Licence

Proprietary. See `LICENSE.md`.
