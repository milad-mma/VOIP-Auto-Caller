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

**Upgrading from the old v1 (callblaster) version:** the installer detects `/var/www/html/autocaller`, asks once (or `AUTO_YES=1`), then removes it completely — old files, the `callblaster` database/user, its `[callblaster]` dialplan entries, restores the original `issabel.conf`/`elastix.conf` that v1 had replaced, and resets the `/var/spool/asterisk` permissions v1 had opened to 777. Its audio files are imported into v2 automatically; old call history is not migrated (export it from the old panel first if you need it).

Offline install from the zip: `unzip autocaller-v2.zip && cd autocaller && sudo ./install.sh install`.

Open `http://SERVER-IP/autocaller` (or https, like your Issabel panel) and sign in as **`root`** (this app's own superuser, password typed during install) or as **`admin`** / any other user with your **Issabel panel** password.

Upgrade later with the same one-line command (config, database and audio are kept). Health check: `sudo /opt/autocaller/install.sh doctor`. Interactive uninstall that asks whether to keep the database/files: `sudo /opt/autocaller/install.sh uninstall`.

## First steps

1. **Settings → Outbound calling**: the default channel type is the **trunk pool**: add your trunks (one click imports them from Issabel), set each trunk's channel count, and the dialer places every call directly on a trunk with a free channel — Issabel outbound routes and their automatic trunk failover are bypassed, so a number rings exactly once and is re-dialed only by the campaign's retry rules; total concurrency = sum of channels. Alternatives: *Local* (goes through your Issabel outbound routes, including their failover) or one fixed SIP/PJSIP trunk; set the dial prefix if your outbound route needs one (e.g. `9`); set the caller ID; set the **global max concurrent calls** to at most your trunk's channel count; set calling hours.
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
* `sudo -u asterisk php /opt/autocaller/bin/console.php call:test 0912xxxxxxx [audio-name]` — places ONE call through the exact channel/caller-ID the dialer would use and prints Asterisk's answer (reason code). Run `asterisk -rvvv` in another terminal to see the dialplan. This is the fastest way to find out whether the problem is the outbound route / prefix / caller ID rather than the app.
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

**ارتقا از نسخه‌ی قدیمی (v1 / callblaster):** نصاب پوشه‌ی `/var/www/html/autocaller` را تشخیص می‌دهد، یک بار می‌پرسد (یا با `AUTO_YES=1` نمی‌پرسد) و کامل پاکش می‌کند: فایل‌ها، دیتابیس و یوزر `callblaster`، بلوک `[callblaster]` در dialplan، بازگرداندن `issabel.conf` اصلی که v1 جایگزین کرده بود، و اصلاح مجوز 777 که v1 روی `/var/spool/asterisk` گذاشته بود. فایل‌های صوتی قدیمی خودکار وارد v2 می‌شوند؛ تاریخچه‌ی تماس‌های قدیمی منتقل نمی‌شود (اگر لازم دارید قبل از نصب از پنل قدیمی اکسل بگیرید).

نصب آفلاین از zip: `unzip autocaller-v2.zip && cd autocaller && sudo ./install.sh install`
سپس `http://IP-سرور/autocaller` را باز کنید و با کاربر **`root`** (سوپریوزر خود برنامه، رمزی که هنگام نصب وارد کردید) یا با **`admin`**/هر کاربر دیگر با رمز **پنل Issabel** وارد شوید.
ارتقا با همان دستور یک‌خطی (تنظیمات، دیتابیس و فایل‌های صوتی حفظ می‌شوند)؛ بررسی سلامت: `sudo /opt/autocaller/install.sh doctor`.

### شروع کار
۱. **تنظیمات → تماس خروجی**: نوع کانال پیش‌فرض **استخر ترانک** است: ترانک‌ها را اضافه کنید (با یک کلیک از Issabel وارد می‌شوند)، تعداد کانال هر ترانک را بدهید؛ شماره‌گیر هر تماس را مستقیم از ترانکی با کانال آزاد می‌گیرد — مسیر خروجی Issabel و failover خودکارش دور زده می‌شود، هر شماره دقیقاً یک بار زنگ می‌خورد و فقط طبق قوانین تلاش مجدد کمپین دوباره گرفته می‌شود؛ ظرفیت هم‌زمان = مجموع کانال‌ها. گزینه‌های دیگر: *Local* (از Outbound Route خود Issabel با failoverش) یا یک ترانک ثابت؛ پیش‌شماره در صورت نیاز (مثلاً `9`)؛ کالر آی‌دی؛ **حداکثر تماس هم‌زمان سراسری** حداکثر به اندازه‌ی کانال‌های ترانک؛ ساعت کاری.
۲. **فایل‌های صوتی**: آپلود MP3/WAV (خودکار به فرمت Asterisk تبدیل می‌شود). «نام منطقی» همان چیزی است که در ستون `audio` فایل ورودی و API استفاده می‌کنید.
۳. **کمپین جدید**: فایل صوتی، کلیدهای IVR (مثلاً `1` → انتقال به `201`، `2` → لیست سیاه)، هم‌زمانی و تلاش مجدد، زمان‌بندی. سپس شماره‌ها را وارد کنید (فایل یا دستی) و **شروع** بزنید.
۴. داشبورد را ببینید، از صفحه‌ی کمپین خروجی اکسل بگیرید، یا نتایج را با API بخوانید.

نکته‌ی اکسل: ستون شماره را از نوع Text کنید تا صفر اول حذف نشود؛ اگر هم حذف شد سامانه خودش برمی‌گرداند.

### رفع اشکال
* `sudo ./install.sh doctor`
* `sudo -u asterisk php /opt/autocaller/bin/console.php call:test 0912xxxxxxx [نام-فایل-صوتی]` — یک تماس با همان کانال و کالر آی‌دی که شماره‌گیر استفاده می‌کند می‌گیرد و جواب Asterisk را چاپ می‌کند؛ هم‌زمان `asterisk -rvvv` را باز کنید. سریع‌ترین راه برای فهمیدن اینکه مشکل از Outbound Route / پیش‌شماره / کالر آی‌دی است یا از برنامه.
* لاگ‌ها: `storage/logs/dialer.log` (شماره‌گیر)، `agi.log` (IVR)، `app.log` (وب).
* تماسی گرفته نمی‌شود → وضعیت کمپین («در انتظار: خارج از ساعت تماس»)، سقف هم‌زمانی، و اینکه Outbound Route شماره‌ی `پیش‌شماره+شماره` را قبول می‌کند.

مجوز: GPL-3.0
