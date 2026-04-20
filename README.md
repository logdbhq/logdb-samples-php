# logdbhq/logdb-samples-php

A small PHP web app + handful of CLI scripts demonstrating the
[`logdbhq/logdb-php`](https://github.com/logdbhq/logdb-php) package end-to-end.

- **Visual style** ported from the Angular UI (`com.logdb.web.ui`): fixed
  60 px icon sidebar, white cards with subtle shadows, blue `#1890ff`
  accent, monospace console panel at the bottom.
- **Feature surface** inspired by the .NET TUI's tabs (Logs, Beats, Cache,
  Stress) — adapted to writes since the PHP package is writer-only in v0.1.
- **Single dependency**: `logdbhq/logdb-php`. No Symfony, no Slim, no
  htmx — front-controller routing is a 30-line `match` in `public/index.php`.
  The sample exists to show off the package, not framework boilerplate.

## What's in the box

| Path                              | Purpose                                                                 |
|-----------------------------------|-------------------------------------------------------------------------|
| `public/index.php`                | Front controller. Dispatches every request via `match` on path/method.  |
| `public/assets/style.css`         | Ant-Design-like palette + reusable `.card`, `.stats-card`, `.level-badge`, `.console-line` classes. |
| `public/assets/app.js`            | ~80 lines of vanilla JS: theme toggle, console collapse, dynamic kv rows. |
| `src/ClientFactory.php`           | Builds a single `LogDBClient` from `.env`, wires `onError` into `HistoryStore`. |
| `src/HistoryStore.php`            | In-process ring buffer (50 entries) of recent send results.             |
| `src/View.php`, `src/Env.php`     | Tiny helpers — render `*.phtml` and load `.env`. No third-party deps.   |
| `src/Controllers/*.php`           | One controller per screen. Each ~80 lines.                              |
| `templates/*.phtml`               | One template per screen + `layout.phtml` chrome + `partials/`.          |
| `examples-cli/psr3-quickstart.php`     | Smallest interesting program — PSR-3 `info()` + `warning()`.       |
| `examples-cli/exception-capture.php`   | `try/catch` with `['exception' => $e]` becoming `Log->stackTrace`. |
| `examples-cli/batch-100-logs.php`      | Batched-write benchmark via `LogEventBuilder`.                     |

## Setup

```bash
composer install
cp .env.example .env
# edit .env: set LOGDB_API_KEY (and LOGDB_ENDPOINT if not the default)
```

Requires **PHP 8.1+**, `ext-curl`, `ext-json`. No build step.

## Run the web app

```bash
php -S localhost:8080 -t public
# open http://localhost:8080
```

## Run the CLI examples

```bash
php examples-cli/psr3-quickstart.php
php examples-cli/exception-capture.php
php examples-cli/batch-100-logs.php
```

Each prints `OK` (or a probe tag) on success. The exception-capture script
emits a `trace=...` ID — search for that in LogDB to find the entry.

## Screens

| Route       | What it does                                                                                                                                                                                                                                          |
|-------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `/`         | **Dashboard** — three counters (total / succeeded / failed), four feature tiles, and a table of the last 10 sends.                                                                                                                                       |
| `/log`      | **Send a log** — the marquee screen. Full form: message, level, user/email, request context, free-form labels, dynamic typed-attribute rows. Submits via `LogEventBuilder` and surfaces the `LogResponseStatus` as a flash banner.                       |
| `/beat`     | **Send a heartbeat** — `LogBeatBuilder` with dynamic tag and field rows. Numeric field values stay numeric on the wire.                                                                                                                                 |
| `/cache`    | **Write to cache** — `LogCacheBuilder` with optional JSON-encode toggle and TTL hint.                                                                                                                                                                   |
| `/stress`   | **Stress test** — burst-write N entries (capped at 5000 for sanity), choose a level distribution, see elapsed ms + throughput + per-status breakdown.                                                                                                   |
| `/status`   | **Status** — current endpoint, application, environment, SDK version, PHP version, total counters, and the most recent SDK errors captured by `LogDBClientOptions->onError`.                                                                            |

The bottom **console panel** (visible on every screen) renders the last 15
sends in monospace, colored by status — closest visual cousin to the
collapsible console in `com.logdb.web.ui`.

## Theming

Click the moon icon at the bottom of the sidebar to toggle dark mode. The
choice persists in `localStorage`. Both palettes mirror what
`com.logdb.web.ui` uses (`--bg-primary`, `--accent-primary`, etc.).

## Caveats

- **Single process only.** `HistoryStore` lives in PHP memory. Under PHP-FPM
  each worker has its own buffer — fine for local play, not for production
  observability. The whole point of the sample is the SDK calls; the
  in-memory bookkeeping is just so you can see what just happened.
- **Forms POST + redirect.** No JavaScript forms, no SPA. Every action
  round-trips through the server so the request lifecycle is visible.
- **No authentication.** Don't deploy this. It's for local play.
- **Reader features missing.** The PHP package is writer-only in v0.1; a
  reader API is on the v0.2 roadmap. Until then the dashboard's "recent
  sends" is *what this process has sent*, not *what's in LogDB*.

## License

MIT
