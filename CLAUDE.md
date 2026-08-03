# slaja-trosak — Project Notes

Personal Laravel project. Combines personal expense tracking (bank statement
imports) with a product price tracker. Not part of the Vimey codebase — global
`~/.claude/CLAUDE.md` git-flow/branching rules do not apply here; this is a
solo project with simple git usage.

## Stack

- Laravel 13, PHP 8.3
- Filament 4 (admin UI, two panels — see below)
- SQLite for local dev (`database/database.sqlite`)
- `phpoffice/phpspreadsheet` for Excel export/import
- Python scripts (`python/*.py`) invoked via `Illuminate\Support\Facades\Process`
  for bank statement parsing helpers and price scraping
- Docker setup available (nginx + php) but local dev typically uses
  `composer dev` (runs `php artisan serve` + queue listener + `pail` + vite
  concurrently via `concurrently`)

## Domain overview

### 1. Bank statement import & transactions
- `BankType` enum: `intesa`, `aik`, `otp`, `erste` (Erste parser not implemented
  yet — throws `InvalidArgumentException` in `ParserFactory`).
- Upload flow: `BankUploadService::handle()`
  - stores file on `private` disk
  - picks parser via `App\Services\Parsers\ParserFactory`
    (`IntesaParser`, `AikParser`, `OtpParser`)
  - deduplicates against existing transactions using a
    `date|amount|description` key (`raw` column)
  - applies category rules via `CategoryRuleService::applyRulesToTransactions()`
  - bulk inserts `Transaction` rows inside a DB transaction
  - tracks upload status: `processing` → `done` / `failed`
- `CategoryRuleService` auto-suggests keyword-based category rules from
  transaction descriptions (groups by common prefix / extracts payee after
  long reference numbers) and can apply them retroactively to uncategorized
  transactions.
- `TransactionCategory` enum: 12 categories with `label()` and `color()` for
  Filament badges.
- `TransactionComment` — users can comment on transactions (modal action in
  `TransactionResource`).

### 2. Price tracking
- `TrackedProduct` model — user-tracked product URLs.
- `PriceCheckService::check()` shells out to `python/price_scraper.py`,
  parses JSON output, updates `current_price` / `status` (`ok`/`failed`),
  records `ProductPriceHistory`.
- `CheckProductPricesJob` (queued) — chunks through all tracked products,
  emails `PriceChangedMail` when price changes. Scheduled daily at 11:00 in
  `routes/console.php`.

## Users & roles

- `UserRole` enum: `admin`, `user`. Stored on `users.role`, cast on the model.
- `User::isAdmin()` / `User::isUser()` helpers — used throughout Filament
  resources/widgets to scope queries (admins see all users' data, regular
  users see only their own) and to hide/restrict admin-only UI (e.g.
  `TransactionResource::canCreate()` returns `false` for admins — admins
  don't manually add transactions).

## Filament panels — admin vs. user separation

Two panels registered in `bootstrap/providers.php`:

- `App\Providers\Filament\AdminPanelProvider`
  - id: `admin`, path: `admin`, marked `->default()`
  - default Filament login (`->login()`), custom `Register` page
  - only shared resource: `App\Filament\Resources\TransactionResource`
  - routes: `/admin`, `/admin/login`, `/admin/register`,
    `/admin/transactions`, `/admin/transactions/create`
- `App\Providers\Filament\UserPanelProvider`
  - id: `user`, path: `''` (root domain)
  - custom login page `App\Filament\Pages\Auth\Login` (redirects to
    `/transactions` after login), custom `Register` page
  - resources auto-discovered from `app/Filament/User/Resources`
    (`BankUploadResource`, `CategoryRuleResource`) **plus** the shared
    `TransactionResource`
  - `homeUrl` → `/transactions`
  - routes: `/login`, `/register`, `/transactions`,
    `/transactions/create`, plus discovered user resources

**Shared resource pattern**: `TransactionResource` lives in
`app/Filament/Resources/` (not `Filament/User/Resources`) specifically so it
can be registered explicitly in *both* panels' `->resources([...])` array —
it is not auto-discovered by either panel. If you add a new resource that
should appear in both panels, follow this same pattern (put it in the shared
`Filament/Resources` folder and register it explicitly in both providers),
rather than duplicating the resource class.

### ⚠️ Fixed bug (2026-08-03): panel access was not role-restricted

`User::canAccessPanel()` used to unconditionally `return true`. Routes were
correctly split (`/admin/*` vs `/`), but there was no actual authorization
tying a user's role to a specific panel — any authenticated user (admin or
regular) could log into *either* panel, since Filament's login pages
auto-redirect an already-authenticated user into the panel they're visiting.
All the `isAdmin()`/`isUser()` checks in resources only affected *what data
was shown*, not *whether the panel could be entered at all*.

Fix applied in `app/Models/User.php`:

```php
public function canAccessPanel(Panel $panel): bool
{
    return match ($panel->getId()) {
        'admin' => $this->isAdmin(),
        'user'  => $this->isUser(),
        default => false,
    };
}
```

If you introduce a third panel or rename a panel id, update this match
accordingly — a panel id not listed here now defaults to `false` (deny),
which is the safe default; don't change it to `true` without thinking about
the blast radius.

**When debugging login/redirect issues between panels**: always start with
`php artisan route:list | grep -i -E "login|admin"` to confirm route
registration before suspecting session/cookie config. Session/auth config
(`config/session.php`, `config/auth.php`) is currently stock Laravel — single
`web` guard shared by both panels, so a session is valid for both; panel
*entry* is what `canAccessPanel()` controls, not authentication itself.

### ⚠️ Fixed bug (2026-08-03): login redirected to homepage instead of /transactions

Symptom: after logging in on `/login` (user panel), the user landed on `/`
(the plain `welcome` view) instead of `/transactions`. Manually navigating to
`/transactions` afterwards then showed Livewire's "This page has expired"
dialog (repeated `419` responses on `POST /livewire/update`, one per
dashboard widget).

Root cause: `App\Filament\Pages\Auth\Login::getRedirectUrl()` was dead code.
Filament 4's base `Login` page (`vendor/filament/filament/src/Auth/Pages/Login.php`)
no longer calls `getRedirectUrl()` at all — the post-login redirect is fully
delegated to `app(Filament\Auth\Http\Responses\Contracts\LoginResponse::class)`,
whose default implementation is:

```php
// Filament\Auth\Http\Responses\LoginResponse
public function toResponse($request): RedirectResponse|Redirector
{
    return redirect()->intended(Filament::getUrl());
}
```

`Filament::getUrl()` for the `user` panel resolves to `/` because that panel
has no Dashboard page registered (`->pages([])`) and its path is `''`. So no
matter what a custom `Login` page's `getRedirectUrl()` returned, it was
ignored — every login redirected to the panel's (non-existent) dashboard,
which fell back to the homepage.

Fix: bind a custom `LoginResponseContract` implementation instead of
overriding the page method:

- `app/Filament/Auth/PostLoginResponse.php` — redirects to `/transactions`
  specifically when `Filament::getCurrentPanel()->getId() === 'user'`, and
  falls back to the default `Filament::getUrl()` behavior for any other
  panel (so `admin` still goes to its own dashboard normally).
- Bound in `AppServiceProvider::register()`:
  `$this->app->bind(LoginResponse::class, PostLoginResponse::class);`

If you need per-panel post-login redirects again in the future (e.g. a third
panel), extend `PostLoginResponse`'s match/if logic — don't add a
`getRedirectUrl()` override to a page class, it won't be called.

The second symptom (419 on widget requests) was observed in the same
reproduction but not fully root-caused beyond "was likely a side effect of
bouncing through `/` before landing on `/transactions`". If it recurs after
this fix, check the browser Network tab for the failing
`POST /livewire/update` request headers (`X-CSRF-TOKEN` vs. current session
cookie) — the `TransactionResource` list page has 5 dashboard widgets
(`TransactionStatsWidget`, `ExpensesVsIncomeChart`, `ExpensesByMonthChart`,
`ExpensesByCategoryChart`, `ExpensesByTypeChart`) that each fire their own
lazy-loaded Livewire request on mount, which is consistent with 5 concurrent
`419` responses seen in nginx logs.

### Local dev gotcha: don't run `php artisan` directly on the host

`.env` has `DB_HOST=db` (the Docker Compose service name for MySQL) — this
only resolves *inside* the Docker network. Running `php artisan` (or
`composer dev`) directly on the host machine fails to connect to the
database (`SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo for
db failed`). Always use `docker compose exec app php artisan ...` (or the
`make artisan ...` / `make tinker` shortcuts) when the stack is run via
Docker, which is the actual way this project runs locally (`docker compose
ps` shows `app`, `db`, `nginx`, `redis`, `mailpit` — nginx maps host port
`8091` → container port `80`, matching `APP_URL=http://slaja-trosak.local:8091`).

## Known gaps / things to watch

- Erste bank parser not implemented (`ParserFactory` throws for it).
- `routes/web.php` has a leftover `/debug-session` debug route — remove once
  no longer needed for auth/session debugging.
- No automated tests yet covering panel access control
  (`tests/Feature`/`tests/Unit` are still the default Laravel skeleton) —
  worth adding a feature test asserting a `user`-role account gets 403 on
  `/admin` and an `admin`-role account gets 403 on `/` resources, to guard
  against regressing the `canAccessPanel()` fix above.

