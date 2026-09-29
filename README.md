# AutoCaller v2 — Outbound campaign dialer for Issabel 4 / Issabel 5 / Elastix

[ورژن فارسی پایین‌تر ↓](#فارسی)

AutoCaller turns your Issabel/Elastix PBX into an outbound campaign system: upload a list of numbers and an audio message, and it calls every number, plays the message, captures the key the callee presses (transfer to an agent, opt-out, tag the answer…) and reports the real outcome of every call (answered / no answer / busy / failed / answering machine, talk time, hangup cause).

## Highlights

* **Real call outcomes** via the Asterisk Manager Interface (AMI `Originate` + `OriginateResponse`/`Hangup` events), optional CDR enrichment — not just "call file created".
* **Many campaigns at once**, each with its own concurrency, pacing, priority, retry rules, calling hours, days and holidays; a global concurrency ceiling protects your trunks.
* **Scheduling**: start/end date-time per campaign, daily calling window, weekdays, holiday calendar.
* **Full IVR**: keys 0-9, `*`, `#` → transfer to extension/queue, replay, hang up, add to do-not-call, play another file, record an answer tag. Optional answering-machine detection (`AMD`).
* **Do-not-call list** (manual, import, API, or self-service via IVR key).
* **Imports** CSV and Excel `.xlsx` directly (no libraries), auto-detects columns, normalizes Iranian numbers (`+98`, `0098`, missing leading zero, Persian digits), skips duplicates and DNC numbers, keeps extra columns for the export.
* **Exports** Excel/CSV with all results; **reports** by day/hour/campaign/keys.
* **REST API** with per-user API keys (create campaigns, single calls, poll results, DNC).
* **Security**: real login (local users with roles *admin / operator / viewer* **or** your Issabel panel users), bcrypt, CSRF tokens, login throttling, prepared statements everywhere, no shell interpolation of user input, audit log.
* **Runs on stock PHP 5.4 (Issabel 4) through PHP 8.x (Issabel 5)**; no Composer, no CDN, works fully offline; Persian (RTL) and English UI.
* **One-command installer** that detects Issabel 4/5/Elastix, reads the MySQL root password from `/etc/issabel.conf`, creates the AMI user and dialplan in the `_custom.conf` files (never touches Issabel's own config), adds an Apache alias (never replaces `issabel.conf`), installs a systemd service with watchdog, logrotate and nightly cleanup. Clean uninstall included.

## Requirements

* Issabel 4 (CentOS 7, PHP 5.4+), Issabel 5 (Rocky 8, PHP 7.4) or Elastix 2.5+; Asterisk 11–20.
* A SIP/PJSIP trunk that can place outbound calls (an outbound route in Issabel).
* `php-cli`, `php-pdo`/`php-mysql(nd)`, `php-mbstring` (the installer installs missing ones), `sox` for MP3 conversion (installed automatically when possible).

## Install (one line)

```bash
curl -fsSL https://raw.githubusercontent.com/milad-mma/VOIP-Auto-Caller/main/install.sh | sudo bash -s install
```

## Remove completely (one line, no questions — as if it was never installed)

```bash
curl -fsSL https://raw.githubusercontent.com/milad-mma/VOIP-Auto-Caller/main/install.sh | sudo bash -s purge
```

Purge stops and deletes the service, cron and logrotate entries, removes the AMI user and dialplan context from the `_custom.conf` files (and the `#include` line only if the installer added it), removes the Apache alias, drops the `autocaller` database and MySQL user, removes the Apache user from the `asterisk` group if the installer added it, deletes `/opt/autocaller` (config, audio, logs) and, if `sox` was installed by the installer, removes it too.

Offline install from the zip: `unzip autocaller-v2.zip && cd autocaller && sudo ./install.sh install`.

Open `http://SERVER-IP/autocaller` (or https, like your Issabel panel) and sign in as `admin` with the password you typed, or with any Issabel panel user.

Upgrade later with the same one-line command (config, database and audio are kept). Health check: `sudo /opt/autocaller/install.sh doctor`. Interactive uninstall that asks whether to keep the database/files: `sudo /opt/autocaller/install.sh uninstall`.

## First steps

1. **Settings → Outbound calling**: choose *Local* (uses your Issabel outbound routes and trunk failover — recommended) or a specific SIP/PJSIP trunk; set the dial prefix if your outbound route needs one (e.g. `9`); set the caller ID; set the **global max concurrent calls** to at most your trunk's channel count; set calling hours.
2. **Audio files**: upload MP3/WAV — it is converted to the format Asterisk plays natively. The *logical name* is what you reference in import files (`audio` column) and the API.
3. **Campaigns → New campaign**: pick audio, IVR keys (e.g. `1` → transfer to `201`, `2` → do-not-call), concurrency and retries, optional schedule. Then import numbers (CSV/XLSX or paste) and press **Start**.
4. Watch the dashboard (live calls, progress), export results from the campaign page, or read them from the API.

Quick single calls (e.g. from a CRM) go through **Quick call** or `POST /api/v1/calls`.

## How it works

```
Web UI / API  ──►  MySQL (campaigns, contacts, attempts)
                        ▲               │
                        │               ▼
              bin/agi.php (IVR)   bin/dialer.php (systemd service)
                        ▲               │  AMI Originate (async)
                        │               ▼
                     Asterisk  ◄── OriginateResponse / Hangup events
```

* The dialer daemon picks pending contacts of running campaigns (priority, schedule, concurrency, pacing, DNC), originates `Local/<number>@from-internal/n` (or a trunk channel) with the `autocaller-ivr` context, and marks the outcome from `OriginateResponse` (answered / no answer / busy / congestion / failed) and `Hangup` (duration, cause).
* When the callee answers, Asterisk runs `AGI(bin/agi.php)`: plays the message, collects DTMF, executes the configured key action (Goto to an extension/queue, DNC, tags…). Optional `AMD()` before playback.
* Retries are scheduled per campaign rules; the daemon recovers in-flight calls after a restart and times out stale ones.

## Files

```
/opt/autocaller
├── public/          web root (aliased as /autocaller)
├── app/             lib/, controllers/, views/, lang/
├── bin/             dialer.php (service), agi.php (IVR), console.php (CLI)
├── sql/             migrations
├── config/          config.php (generated, chmod 640)
└── storage/         audio/ uploads/ tmp/ logs/ run/
```

CLI: `sudo -u asterisk php bin/console.php migrate | user:create <name> [role] | user:passwd | setting | ami:test | dialer:status | cleanup | doctor`

## API

Create a key under **API keys** and send it as header `X-API-Key`. Base URL: `http(s)://SERVER/autocaller/api/v1`.

| Method | Path | Purpose |
|---|---|---|
| GET | `/ping`, `/status` | health / daemon state |
| GET | `/audio` | list audio files |
| POST | `/calls` `{phone, audio, transfer?, name?}` | dial one number now |
| GET | `/calls/{id}` | outcome of that call |
| GET | `/campaigns?status=` | list campaigns |
| POST | `/campaigns` `{name, audio, contacts:[…], ivr:{…}, start:true, …}` | create (+start) a campaign |
| GET | `/campaigns/{id}` | campaign + stats |
| POST | `/campaigns/{id}/contacts` `{contacts:[{phone,name,audio}]}` | add numbers |
| GET | `/campaigns/{id}/contacts?status=&page=&updated_since=` | results |
| POST | `/campaigns/{id}/start|pause|resume|stop` | control |
| GET/POST/DELETE | `/dnc` | do-not-call list |

Example:

```bash
curl -X POST http://pbx/autocaller/api/v1/campaigns -H "X-API-Key: ack_…" -H "Content-Type: application/json" -d '{
  "name":"Promo","audio":"promo1","start":true,"concurrent":3,
  "work_start":"09:00","work_end":"20:00",
  "ivr":{"digits":{"1":{"action":"transfer","target":"201","tag":"interested"},"2":{"action":"dnc"}}},
  "contacts":[{"phone":"09121234567","name":"Ali"},{"phone":"+989351234567","audio":"promo2"}]}'
```

## Troubleshooting

* `sudo ./install.sh doctor` — checks PHP, DB, AMI login, dialplan, daemon heartbeat.
* Dialer log: `storage/logs/dialer.log`; IVR log: `storage/logs/agi.log`; web: `storage/logs/app.log`.
* `asterisk -rvvv` and `manager show connected` to confirm the daemon is logged in.
* No calls placed → check campaign status "waiting: outside calling hours", global max concurrent, and that the outbound route accepts `<prefix><number>`.
* AMD marks everything as machine → disable AMD or tune `/etc/asterisk/amd.conf`.

License: GPL-3.0. Based on the original Callblaster idea, rewritten from scratch.

---

# فارسی

## AutoCaller نسخه ۲ — سامانه‌ی تماس خودکار (کمپین خروجی) برای Issabel 4 و 5 و Elastix

لیست شماره‌ها و یک فایل صوتی می‌دهید؛ سامانه با همه تماس می‌گیرد، پیام را پخش می‌کند، کلیدی که مخاطب می‌زند را می‌گیرد (انتقال به اپراتور، لغو اشتراک، ثبت پاسخ…) و **نتیجه‌ی واقعی** هر تماس را گزارش می‌دهد (پاسخ‌داده / بی‌پاسخ / مشغول / ناموفق / منشی تلفنی، مدت مکالمه، علت قطع).

### امکانات
* وضعیت واقعی تماس از AMI (نه فقط «فایل تماس ساخته شد»)، با تکمیل از CDR.
* چند کمپین هم‌زمان با سقف هم‌زمانی مجزا و سقف سراسری برای محافظت از ترانک؛ اولویت، فاصله بین تماس‌ها، تلاش مجدد هوشمند.
* زمان‌بندی: تاریخ شروع/پایان، بازه‌ی ساعتی روزانه، روزهای هفته، تقویم تعطیلات.
* IVR کامل کلیدهای ۰ تا ۹ و * و #: انتقال به داخلی/صف، پخش مجدد، قطع، افزودن به لیست سیاه، پخش فایل دیگر، ثبت برچسب پاسخ. تشخیص منشی تلفنی اختیاری.
* لیست سیاه (عدم تماس) با ورود دستی، فایل، API یا خودِ مخاطب از طریق کلید IVR.
* ورود مستقیم CSV و **اکسل xlsx**، تشخیص خودکار ستون‌ها، اصلاح خودکار شماره‌های ایرانی (+98، صفر اول، ارقام فارسی)، حذف تکراری و لیست سیاه.
* خروجی اکسل/CSV و گزارش‌های روزانه/ساعتی/کمپین/کلیدها.
* REST API با کلید اختصاصی هر کاربر.
* امنیت: لاگین واقعی با نقش‌های مدیر/اپراتور/بیننده یا کاربران پنل Issabel، CSRF، قفل تلاش ورود، prepared statement، بدون تزریق ورودی به شل، لاگ رخدادها.
* روی PHP 5.4 (Issabel 4) تا 8.x (Issabel 5) بدون هیچ وابستگی خارجی و کاملاً آفلاین. رابط فارسی و انگلیسی.

### نصب (یک خط)
```bash
curl -fsSL https://raw.githubusercontent.com/milad-mma/VOIP-Auto-Caller/main/install.sh | sudo bash -s install
```
### حذف کامل (یک خط، بدون هیچ سؤالی — انگار هیچ‌وقت نصب نبوده)
```bash
curl -fsSL https://raw.githubusercontent.com/milad-mma/VOIP-Auto-Caller/main/install.sh | sudo bash -s purge
```
سرویس، cron، logrotate، یوزر AMI و کانتکست dialplan (فقط بلوک خودمان در فایل‌های `_custom.conf`)، فایل Apache، دیتابیس و یوزر MySQL، عضویت گروه apache (اگر نصاب اضافه کرده باشد)، کل `/opt/autocaller` و حتی `sox` (اگر نصاب نصبش کرده باشد) پاک می‌شوند.

نصب آفلاین از zip: `unzip autocaller-v2.zip && cd autocaller && sudo ./install.sh install`
سپس `http://IP-سرور/autocaller` را باز کنید و با کاربر `admin` (رمزی که وارد کردید) یا هر کاربر پنل Issabel وارد شوید.
ارتقا با همان دستور یک‌خطی (تنظیمات، دیتابیس و فایل‌های صوتی حفظ می‌شوند)؛ بررسی سلامت: `sudo /opt/autocaller/install.sh doctor`.

### شروع کار
۱. **تنظیمات → تماس خروجی**: نوع کانال *Local* (استفاده از Outbound Route خود Issabel — پیشنهادی) یا یک ترانک مشخص؛ پیش‌شماره در صورت نیاز (مثلاً `9`)؛ کالر آی‌دی؛ **حداکثر تماس هم‌زمان سراسری** حداکثر به اندازه‌ی کانال‌های ترانک؛ ساعت کاری.
۲. **فایل‌های صوتی**: آپلود MP3/WAV (خودکار به فرمت Asterisk تبدیل می‌شود). «نام منطقی» همان چیزی است که در ستون `audio` فایل ورودی و API استفاده می‌کنید.
۳. **کمپین جدید**: فایل صوتی، کلیدهای IVR (مثلاً `1` → انتقال به `201`، `2` → لیست سیاه)، هم‌زمانی و تلاش مجدد، زمان‌بندی. سپس شماره‌ها را وارد کنید (فایل یا دستی) و **شروع** بزنید.
۴. داشبورد را ببینید، از صفحه‌ی کمپین خروجی اکسل بگیرید، یا نتایج را با API بخوانید.

نکته‌ی اکسل: ستون شماره را از نوع Text کنید تا صفر اول حذف نشود؛ اگر هم حذف شد سامانه خودش برمی‌گرداند.

### رفع اشکال
* `sudo ./install.sh doctor`
* لاگ‌ها: `storage/logs/dialer.log` (شماره‌گیر)، `agi.log` (IVR)، `app.log` (وب).
* تماسی گرفته نمی‌شود → وضعیت کمپین («در انتظار: خارج از ساعت تماس»)، سقف هم‌زمانی، و اینکه Outbound Route شماره‌ی `پیش‌شماره+شماره` را قبول می‌کند.

مجوز: GPL-3.0
