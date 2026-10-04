# AutoCaller v2 — architecture notes

* **Compatibility floor: PHP 5.4** (Issabel 4 stock). Rules: no `??`, no scalar type hints, no `[]` destructuring, no `yield`/`finally`, no trailing commas in calls, no `::class`. `password_hash`, `random_bytes`, `hash_equals`, `array_column` are polyfilled in `app/lib/Polyfill.php`. Tested syntax with PHP 8.4 as well.
* **No framework, no Composer.** Autoloader in `app/bootstrap.php` maps class → file.
* **Database**: PDO/MySQL, utf8mb4 (falls back to utf8 on very old MariaDB), InnoDB; migrations in `sql/NNN_*.sql` applied by `bin/console.php migrate` (tracked in `schema_version`).
* **Dialer daemon** (`app/lib/Dialer.php`): single process, flock lock, AMI socket with non-blocking `stream_select` loop (200 ms ticks), heartbeat to `daemon_status` every 5 s, reconnect with backoff, `pcntl` graceful stop, in-flight recovery on restart, stale timeouts.
  * Originate: `Action: Originate, Async: true, ActionID: ac-<contact>-<attempt>-<rand>, Variable: AC_ATTEMPT/AC_CONTACT/AC_CAMPAIGN, Context: autocaller-ivr, Exten: s`.
  * `OriginateResponse` Reason: 4 answered, 5 busy, 3 no answer, 8 congestion, else failed. `Hangup` matched by `Uniqueid` (recorded both by the daemon and by the AGI, whichever comes first).
  * Local channels use `/n` so Asterisk never optimizes them away mid-call (otherwise `Hangup` for the IVR leg fires at transfer time).
* **AGI** (`app/lib/Agi.php`): minimal AGI protocol; IVR loop: `STREAM FILE` with escape digits → `WAIT FOR DIGIT` → per-key action. Transfer = `EXEC Goto context,exten,1` then script exit. `dnc`/`machine` verdicts are stamped by the AGI; `completed` (+duration) by the daemon on `Hangup`.
* **Security**: sessions (strict mode, httponly), bcrypt, CSRF (form field / header), login throttle table, role checks in controllers, all SQL parametrized, `escapeshellarg` for the few shell calls (sox/ffmpeg/asterisk), uploads renamed to `ac_<id>.wav`, storage outside web root, `config.php` 640.
* **Issabel integration**: manager user in `manager_custom.conf`, dialplan in `extensions_custom.conf` (both between `; BEGIN/END autocaller` markers, idempotent), Apache alias file `zz-autocaller.conf` (Issabel's own conf untouched), MySQL root password read from `/etc/issabel.conf` for: Issabel-user login (`acl` db), trunk list (`asterisk.trunks`), CDR enrichment (`asteriskcdrdb.cdr`).
* **Adding a migration**: create `sql/002_something.sql`, run `console.php migrate`.
* **Adding a language**: copy `app/lang/en.php`.
