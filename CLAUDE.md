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
  emails `PriceChangedMail` when price changes. Scheduled daily at 11:00
  (app timezone, `APP_TIMEZONE` in `.env`) in `routes/console.php`.
  Per-product work is wrapped in try/catch (service catches scraper process
  failures/timeouts, job catches everything else) so one broken product or a
  mail error cannot abort the rest of the run.
- `PriceChangedMail` renders `emails/price-changed.blade.php` as
  **markdown** (`Content(markdown: ...)`) — the template uses
  `<x-mail::message>` components, so `Content(view: ...)` throws
  "No hint path defined for [mail]" (bug fixed 2026-08-17; the daily job had
  never successfully sent a price-change email before that).
- Executes only if the **scheduler and queue worker containers** are running —
  see "Scheduler & queue" below. There is no cron inside the app container.

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
  - shared custom login page `App\Filament\Pages\Auth\Login` (see
    "Role-aware login" below), custom `Register` page
  - only shared resource: `App\Filament\Resources\TransactionResource`
  - routes: `/admin`, `/admin/login`, `/admin/register`,
    `/admin/transactions`, `/admin/transactions/create`
- `App\Providers\Filament\UserPanelProvider`
  - id: `user`, path: `''` (root domain)
  - custom login page `App\Filament\Pages\Auth\Login` (redirects to
    `/transactions` after login), custom `Register` page
  - resources auto-discovered from `app/Filament/User/Resources`
    (`BankUploadResource`, `CategoryRuleResource`) **plus**, registered
    explicitly from the shared `app/Filament/Resources` folder:
    `TransactionResource` and `TrackedProductResource` (Price Tracker is
    user-facing and would otherwise be registered in no panel, since the
    admin panel no longer auto-discovers the shared folder)
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

The same fallback exists for **registration**: Filament's default
`RegistrationResponse` also redirects to `Filament::getUrl()`, which means
`/register` would land on `/` (welcome page) and `/admin/register` would land
on `/admin` — an instant 403, because every self-registered account gets the
`user` role and `canAccessPanel('admin')` denies it. Fixed the same way:
`app/Filament/Auth/PostRegistrationResponse.php` (always redirects to
`/transactions` — both panels share the `web` guard, so the session is valid
there) bound to the `RegistrationResponse` contract in
`AppServiceProvider::register()`.

### Role-aware login & entry (2026-08-17)

After login, **admins always land on `/admin` and regular users on
`/transactions`, no matter which login form they used**:

- `App\Filament\Pages\Auth\Login` (registered on **both** panels) overrides
  `authenticate()`: Filament's base page rejects valid credentials when the
  account can't access the *current* panel (`attemptWhen` +
  `canAccessPanel`), which used to mean an admin typing correct credentials
  on `/login` got "invalid credentials". The override catches that case,
  re-validates the credentials itself, confirms the account can access at
  least one panel, and completes the login (both panels share the `web`
  guard). Wrong passwords are still rejected. Panel *entry* remains
  protected by `canAccessPanel()` via Filament's `Authenticate` middleware.
- `App\Filament\Auth\PostLoginResponse` routes by **role**, not by panel:
  admin → `/admin`, user → `/transactions`. A stored `url.intended` is
  honoured only if it belongs to the panel that role can enter (otherwise
  the redirect would 403 immediately after login).
- `routes/web.php` `/` is a role-aware entry point: guest → `/login`,
  user → `/transactions`, admin → `/admin` (the stock `welcome` view is no
  longer reachable).

### ⚠️ Fixed bug (2026-08-17): 419 "This page has expired" on the 5 dashboard widgets

The second symptom from the reproduction above (5 concurrent
`POST /livewire/update` → `419` right after landing on `/transactions`, and
Livewire's "This page has expired" confirm dialog) was **not CSRF at all**.
Session, cookies and `X-CSRF-TOKEN` were all valid — replaying the exact
widget requests with a freshly issued token still returned 419.

Root cause: on every Livewire update request,
`Livewire\Features\SupportReleaseTokens\ReleaseToken::verify()` first resolves
the component *name* from the snapshot back to a class via
`ComponentRegistry::getClass()`. If that lookup throws
`ComponentNotFoundException`, Livewire rethrows it as
`LivewireReleaseTokenMismatchException`, which renders as **419 Page
Expired** — a very misleading status. The 5 widgets
(`TransactionStatsWidget`, `ExpensesVsIncomeChart`, `ExpensesByMonthChart`,
`ExpensesByCategoryChart`, `ExpensesByTypeChart`) were only listed in
`ListTransactions::getHeaderWidgets()` and **not** in
`TransactionResource::getWidgets()`, and Filament's
`Panel::registerLivewireComponents()` registers resource widgets exclusively
from `$resource::getWidgets()`. So the widgets rendered fine on first page
load (mounted by class), but their names were unresolvable on every
subsequent Livewire request → exactly 5 × 419, on both panels, regardless of
session state.

Fix: added `TransactionResource::getWidgets()` returning the 5 widget
classes. Rule of thumb: any widget used in a resource page's
`getHeaderWidgets()`/`getFooterWidgets()` must also be returned from the
resource's `getWidgets()` (or be registered via the panel's
`->widgets([...])`), otherwise Livewire updates for it 419.

Also re-enabled `DisableBladeIconComponents` and `DispatchServingFilamentEvent`
in `UserPanelProvider`'s middleware (they were commented out, presumably
leftover debugging) so the user panel's middleware list matches
`AdminPanelProvider` and Filament's standard stack. They were not the cause
of the 419, but there is no reason for the two panels to differ here.

### Local dev gotcha: don't run `php artisan` directly on the host

`.env` has `DB_HOST=db` (the Docker Compose service name for MySQL) — this
only resolves *inside* the Docker network. Running `php artisan` (or
`composer dev`) directly on the host machine fails to connect to the
database (`SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo for
db failed`). Always use `docker compose exec app php artisan ...` (or the
`make artisan ...` / `make tinker` shortcuts) when the stack is run via
Docker, which is the actual way this project runs locally (`docker compose
ps` shows `app`, `db`, `nginx`, `redis`, `mailpit`, `scheduler`, `queue` —
nginx maps host port `8091` → container port `80`, matching
`APP_URL=http://slaja-trosak.local:8091`; redis publishes host port
`${REDIS_PORT_FORWARD:-6379}`, set to `6391` in `.env` because `6379` on
this machine is taken by `pandora-redis`).

## Scheduler & queue (how the daily price check actually runs)

Two dedicated compose services (added 2026-08-17 — before that, **nothing
executed the schedule or the queue**, so the daily price check never ran):

- `scheduler` — `php artisan schedule:work` (fires due scheduled tasks every
  minute; replaces a host cron for `schedule:run`).
- `queue` — `php artisan queue:work --tries=3 --timeout=650 --sleep=3`
  (`QUEUE_CONNECTION=database`). Invariant to keep:
  `DB_QUEUE_RETRY_AFTER` (660, in `.env`) > worker `--timeout` (650) >
  job `$timeout` (600 in `CheckProductPricesJob`), otherwise a long run gets
  picked up twice and users receive duplicate price-change emails.

**Gotcha:** `queue:work` keeps the whole app in memory — after changing any
code that a queued job touches, `docker compose restart queue`, or the
worker keeps executing the old code (bit us during the mail-markdown fix).

`APP_TIMEZONE=Europe/Belgrade` is set in `.env` — without it Laravel runs on
UTC and `dailyAt('11:00')` means 13:00 local time in summer.

Mail: `.env` uses `MAIL_MAILER=smtp` + `MAIL_HOST=mailpit` + `MAIL_PORT=1025`,
so locally sent mail lands in the Mailpit UI on `http://localhost:8025`.
(It previously pointed at the `log` mailer, so emails only ever appeared in
`storage/logs/`.) A real deployment needs real SMTP credentials here.

## Known gaps / things to watch

- Erste bank parser not implemented (`ParserFactory` throws for it).
- No automated tests yet covering panel access control
  (`tests/Feature`/`tests/Unit` are still the default Laravel skeleton) —
  worth adding a feature test asserting a `user`-role account gets 403 on
  `/admin` and an `admin`-role account gets 403 on `/` resources, to guard
  against regressing the `canAccessPanel()` fix above.

