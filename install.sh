#!/bin/bash
#
# AutoCaller installer / upgrader / uninstaller for Issabel 4, Issabel 5 and Elastix.
#
# One-line install (downloads the latest source from GitHub):
#   curl -fsSL https://raw.githubusercontent.com/milad-mma/VOIP-Auto-Caller/main/install.sh | sudo bash -s install
# One-line COMPLETE removal (no questions, leaves no trace: files, database, AMI user, dialplan, Apache, service, cron):
#   curl -fsSL https://raw.githubusercontent.com/milad-mma/VOIP-Auto-Caller/main/install.sh | sudo bash -s purge
#
# From an extracted copy:
#   sudo ./install.sh              interactive menu
#   sudo ./install.sh install      install or upgrade (keeps config & data)
#   sudo ./install.sh purge        remove everything, no questions
#   sudo ./install.sh uninstall    remove, asking about database/files
#   sudo ./install.sh doctor       health check
#   ADMIN_PASS=secret sudo -E ./install.sh install   # non-interactive admin password
#
set -u
APP_DIR="/opt/autocaller"
WEB_PATH="/autocaller"
SVC="autocaller-dialer"
AST_USER="asterisk"
REPO="${AUTOCALLER_REPO:-milad-mma/VOIP-Auto-Caller}"
BRANCH="${AUTOCALLER_BRANCH:-main}"
STATE_FILE="$APP_DIR/.install-state"
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'

say()  { echo -e "${CYAN}==>${NC} $*"; }
ok()   { echo -e "    ${GREEN}✔${NC} $*"; }
warn() { echo -e "    ${YELLOW}!${NC} $*"; }
die()  { echo -e "${RED}ERROR:${NC} $*" >&2; exit 1; }
# read from the terminal even when the script itself arrives on stdin (curl | bash)
has_tty() { ( : </dev/tty ) >/dev/null 2>&1; }
ask()  { local __v; if has_tty; then read -r "$@" __v </dev/tty; else read -r "$@" __v; fi; echo "$__v"; }
asks() { local __v; if has_tty; then read -r -s "$@" __v </dev/tty; else read -r -s "$@" __v; fi; echo "$__v"; }

[ "$EUID" -eq 0 ] || die "run as root (sudo ./install.sh)"

# Where is the source tree? Next to this script, or download it.
SELF="${BASH_SOURCE[0]:-}"
SRC_DIR=""
if [ -n "$SELF" ] && [ -f "$SELF" ]; then
    SRC_DIR="$(cd "$(dirname "$(readlink -f "$SELF")")" && pwd)"
    [ -f "$SRC_DIR/app/bootstrap.php" ] || SRC_DIR=""
fi
TMP_SRC=""
cleanup_tmp() { [ -n "$TMP_SRC" ] && rm -rf "$TMP_SRC"; }
trap cleanup_tmp EXIT

fetch_source() {
    [ -n "$SRC_DIR" ] && return 0
    say "Downloading AutoCaller from github.com/$REPO ($BRANCH)"
    command -v curl >/dev/null 2>&1 || yum install -y curl >/dev/null 2>&1 || die "curl not found"
    command -v tar >/dev/null 2>&1 || die "tar not found"
    TMP_SRC="$(mktemp -d /tmp/autocaller-src.XXXXXX)"
    local url="https://codeload.github.com/$REPO/tar.gz/refs/heads/$BRANCH"
    if ! curl -fsSL --retry 3 "$url" -o "$TMP_SRC/src.tgz"; then
        url="https://github.com/$REPO/archive/refs/heads/$BRANCH.tar.gz"
        curl -fsSL --retry 3 "$url" -o "$TMP_SRC/src.tgz" || die "download failed ($url). Check internet/DNS access to github.com, or download the zip manually and run ./install.sh from it."
    fi
    tar -xzf "$TMP_SRC/src.tgz" -C "$TMP_SRC" || die "cannot extract archive"
    # the tree may be at the repo root or inside a sub folder (autocaller/)
    local found
    found="$(find "$TMP_SRC" -maxdepth 4 -name bootstrap.php -path '*/app/bootstrap.php' | head -1)"
    [ -n "$found" ] || die "source tree not found in the downloaded archive"
    SRC_DIR="$(cd "$(dirname "$found")/.." && pwd)"
    ok "source ready ($SRC_DIR)"
}

# ------------------------------------------------------------------ detection
detect_env() {
    OS_ID="unknown"; OS_VER=""
    if [ -f /etc/os-release ]; then . /etc/os-release; OS_ID="$ID"; OS_VER="${VERSION_ID%%.*}"; fi
    PBX="unknown"; PBX_CONF=""
    if [ -f /etc/issabel.conf ]; then PBX="Issabel"; PBX_CONF=/etc/issabel.conf
    elif [ -f /etc/elastix.conf ]; then PBX="Elastix"; PBX_CONF=/etc/elastix.conf; fi
    PBX_VER="$(rpm -q issabel-framework 2>/dev/null | grep -v 'not installed' || rpm -q elastix-framework 2>/dev/null | grep -v 'not installed' || true)"
    PHP_BIN="$(command -v php || true)"
    [ -n "$PHP_BIN" ] || die "php-cli not found. Issabel 4: yum install php-cli ; Issabel 5: dnf install php-cli"
    PHP_VER="$($PHP_BIN -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
    AST_BIN="$(command -v asterisk || echo /usr/sbin/asterisk)"
    HTTPD_USER="$(grep -hiE '^\s*User\s+' /etc/httpd/conf/httpd.conf /etc/httpd/conf.d/*.conf 2>/dev/null | tail -1 | awk '{print $2}')"
    [ -n "$HTTPD_USER" ] || HTTPD_USER="apache"
    MYSQL_BIN="$(command -v mysql || true)"
    [ -n "$MYSQL_BIN" ] || die "mysql client not found"
}

print_env() {
    say "Environment"
    ok "OS: $OS_ID $OS_VER   PBX: $PBX ${PBX_VER:+($PBX_VER)}"
    ok "PHP: $PHP_VER ($PHP_BIN)   Asterisk: $($AST_BIN -rx 'core show version' 2>/dev/null | head -1 | cut -c1-40)"
    ok "Apache user: $HTTPD_USER"
}

check_php() {
    $PHP_BIN -r 'exit(version_compare(PHP_VERSION, "5.4.0", ">=") ? 0 : 1);' || die "PHP >= 5.4 required (found $PHP_VER)"
    local missing=""
    for m in pdo_mysql mbstring json; do
        $PHP_BIN -m 2>/dev/null | grep -qi "^$m\$" || missing="$missing $m"
    done
    if [ -n "$missing" ]; then
        warn "missing PHP extensions:$missing"
        local pk=""
        for m in $missing; do
            case $m in
                pdo_mysql) pk="$pk php-pdo php-mysql php-mysqlnd";;
                mbstring)  pk="$pk php-mbstring";;
                json)      pk="$pk php-json";;
            esac
        done
        say "Installing:$pk"
        (command -v dnf >/dev/null && dnf install -y $pk) || yum install -y $pk || true
        for m in $missing; do
            $PHP_BIN -m 2>/dev/null | grep -qi "^$m\$" || die "PHP extension $m still missing. Install it and rerun."
        done
    fi
    ok "PHP extensions ok"
    if ! command -v sox >/dev/null 2>&1; then
        say "Installing sox (audio conversion)"
        if (command -v dnf >/dev/null && dnf install -y sox) || yum install -y sox; then
            mkdir -p "$APP_DIR"; echo "INSTALLED_SOX=1" >> "$STATE_FILE"
        else
            warn "sox not installed - only WAV/GSM uploads will work"
        fi
    fi
}

mysql_root() {
    MYSQL_ROOT_PW=""
    if [ -n "$PBX_CONF" ]; then
        MYSQL_ROOT_PW="$(grep -E '^\s*mysqlrootpwd\s*=' "$PBX_CONF" | head -1 | cut -d= -f2- | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
    fi
    if ! mysql_try; then
        MYSQL_ROOT_PW=""
        if mysql_try; then :; else
            echo -n "MySQL root password: "; MYSQL_ROOT_PW="$(asks)"; echo
            mysql_try || die "cannot connect to MySQL as root"
        fi
    fi
    ok "MySQL root access ok"
}
mysql_root_quiet() {
    # non-interactive best effort (purge): issabel.conf, then our config.php, then no password
    MYSQL_ROOT_PW=""
    if [ -n "${PBX_CONF:-}" ] && [ -f "$PBX_CONF" ]; then
        MYSQL_ROOT_PW="$(grep -E '^\s*mysqlrootpwd\s*=' "$PBX_CONF" | head -1 | cut -d= -f2- | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
        mysql_try && return 0
    fi
    if [ -f "$APP_DIR/config/config.php" ] && [ -n "${PHP_BIN:-}" ]; then
        MYSQL_ROOT_PW="$($PHP_BIN -r '$c=include "'"$APP_DIR"'/config/config.php"; echo isset($c["issabel"]["mysql_root"]) ? $c["issabel"]["mysql_root"] : "";' 2>/dev/null)"
        mysql_try && return 0
    fi
    MYSQL_ROOT_PW=""; mysql_try && return 0
    return 1
}
mysql_try() { "$MYSQL_BIN" -uroot ${MYSQL_ROOT_PW:+-p"$MYSQL_ROOT_PW"} -e 'SELECT 1' >/dev/null 2>&1; }
mysql_q()   { "$MYSQL_BIN" -uroot ${MYSQL_ROOT_PW:+-p"$MYSQL_ROOT_PW"} -e "$1"; }

rand() { tr -dc 'A-Za-z0-9' </dev/urandom | head -c "${1:-24}"; }

# ------------------------------------------------------------------ install
do_install() {
    detect_env; print_env; check_php; mysql_root; fetch_source
    UPGRADE=0; [ -f "$APP_DIR/config/config.php" ] && UPGRADE=1
    mkdir -p "$APP_DIR"; touch "$STATE_FILE"

    say "Copying files to $APP_DIR"
    mkdir -p "$APP_DIR"
    if [ "$SRC_DIR" != "$APP_DIR" ]; then
        for d in app bin public sql docs; do
            rm -rf "$APP_DIR/$d"; cp -a "$SRC_DIR/$d" "$APP_DIR/"
        done
        cp -a "$SRC_DIR/install.sh" "$APP_DIR/install.sh"
        [ -f "$SRC_DIR/README.md" ] && cp -a "$SRC_DIR/README.md" "$APP_DIR/"
        mkdir -p "$APP_DIR/config"
        [ -d "$APP_DIR/storage" ] || cp -a "$SRC_DIR/storage" "$APP_DIR/"
    fi
    for d in audio uploads tmp logs run; do mkdir -p "$APP_DIR/storage/$d"; done
    ok "files in place"

    if [ "$UPGRADE" -eq 0 ]; then
        say "Creating database"
        DB_PASS="$(rand 20)"
        mysql_q "CREATE DATABASE IF NOT EXISTS autocaller CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || mysql_q "CREATE DATABASE IF NOT EXISTS autocaller CHARACTER SET utf8 COLLATE utf8_unicode_ci;"
        if ! mysql_q "GRANT ALL PRIVILEGES ON autocaller.* TO 'autocaller'@'localhost' IDENTIFIED BY '$DB_PASS'; FLUSH PRIVILEGES;" 2>/dev/null; then
            mysql_q "CREATE USER 'autocaller'@'localhost' IDENTIFIED BY '$DB_PASS';" 2>/dev/null || mysql_q "ALTER USER 'autocaller'@'localhost' IDENTIFIED BY '$DB_PASS';"
            mysql_q "GRANT ALL PRIVILEGES ON autocaller.* TO 'autocaller'@'localhost'; FLUSH PRIVILEGES;"
        fi
        AMI_SECRET="$(rand 24)"
        APP_SECRET="$(rand 32)"
        say "Writing config/config.php"
        cat > "$APP_DIR/config/config.php" <<EOF
<?php
// AutoCaller configuration (generated by install.sh on $(date '+%Y-%m-%d'))
return array(
    'db' => array('host' => 'localhost', 'name' => 'autocaller', 'user' => 'autocaller', 'pass' => '$DB_PASS', 'socket' => ''),
    'ami' => array('host' => '127.0.0.1', 'port' => 5038, 'user' => 'autocaller', 'secret' => '$AMI_SECRET'),
    'app' => array(
        'base_url' => '$WEB_PATH',
        'secret' => '$APP_SECRET',
        'timezone' => '$(timedatectl 2>/dev/null | awk '/Time zone/{print $3}' | grep . || echo Asia/Tehran)',
        'lang' => 'fa',
        'storage' => '$APP_DIR/storage',
        'asterisk_bin' => '$AST_BIN',
        'php_bin' => '$PHP_BIN',
        'debug' => false,
    ),
    'issabel' => array('conf_file' => '${PBX_CONF:-/etc/issabel.conf}', 'mysql_root' => '$(printf '%s' "$MYSQL_ROOT_PW" | sed "s/'/\\\\'/g")'),
);
EOF
        ok "database autocaller + config written"
    else
        AMI_SECRET="$($PHP_BIN -r 'define("APP_ROOT","'"$APP_DIR"'"); $c=include "'"$APP_DIR"'/config/config.php"; echo $c["ami"]["secret"];')"
        ok "upgrade: existing config and database kept"
    fi

    say "Asterisk manager user (manager_custom.conf)"
    MC=/etc/asterisk/manager_custom.conf
    touch "$MC"
    sed -i '/; BEGIN autocaller/,/; END autocaller/d' "$MC"
    cat >> "$MC" <<EOF
; BEGIN autocaller
[autocaller]
secret = $AMI_SECRET
deny = 0.0.0.0/0.0.0.0
permit = 127.0.0.1/255.255.255.255
read = call,command,cdr,dialplan,system
write = originate,command,call,system
writetimeout = 5000
; END autocaller
EOF
    if ! grep -qE '^\s*#include\s+manager_custom.conf' /etc/asterisk/manager.conf 2>/dev/null; then
        echo "#include manager_custom.conf ; added by autocaller" >> /etc/asterisk/manager.conf
        echo "ADDED_MANAGER_INCLUDE=1" >> "$STATE_FILE"
        warn "added '#include manager_custom.conf' to manager.conf"
    fi
    grep -qE '^\s*enabled\s*=\s*yes' /etc/asterisk/manager.conf || warn "manager.conf: 'enabled = yes' not found in [general] - AMI may be disabled"
    ok "AMI user 'autocaller' configured"

    say "Dialplan (extensions_custom.conf)"
    EC=/etc/asterisk/extensions_custom.conf
    touch "$EC"
    sed -i '/; BEGIN autocaller/,/; END autocaller/d' "$EC"
    cat >> "$EC" <<EOF

; BEGIN autocaller
[autocaller-ivr]
exten => s,1,NoOp(AutoCaller contact \${AC_CONTACT} attempt \${AC_ATTEMPT})
 same => n,Answer()
 same => n,AGI($APP_DIR/bin/agi.php)
 same => n,Hangup()
exten => h,1,NoOp(AutoCaller hangup \${AC_CONTACT})
; END autocaller
EOF
    ok "context [autocaller-ivr] registered"

    say "Permissions"
    chown -R $AST_USER:$AST_USER "$APP_DIR"
    find "$APP_DIR" -type d -exec chmod 755 {} \;
    find "$APP_DIR" -type f -exec chmod 644 {} \;
    chmod 750 "$APP_DIR/config"; chmod 640 "$APP_DIR/config/config.php"
    chmod -R 775 "$APP_DIR/storage"
    chmod 755 "$APP_DIR"/bin/*.php "$APP_DIR/install.sh"
    sed -i "1s|^#!.*|#!$PHP_BIN|" "$APP_DIR"/bin/*.php
    if [ "$HTTPD_USER" != "$AST_USER" ]; then
        if ! id -nG "$HTTPD_USER" 2>/dev/null | tr ' ' '\n' | grep -qx "$AST_USER"; then
            usermod -a -G $AST_USER "$HTTPD_USER" 2>/dev/null && { echo "ADDED_GROUP=$HTTPD_USER" >> "$STATE_FILE"; warn "apache runs as $HTTPD_USER: added to group $AST_USER"; }
        fi
        chmod g+r "$APP_DIR/config/config.php"; chmod g+rx "$APP_DIR/config"
    fi
    if command -v getenforce >/dev/null 2>&1 && [ "$(getenforce 2>/dev/null)" = "Enforcing" ]; then
        chcon -R -t httpd_sys_rw_content_t "$APP_DIR/storage" 2>/dev/null || true
        chcon -R -t httpd_sys_content_t "$APP_DIR/public" "$APP_DIR/app" 2>/dev/null || true
        setsebool -P httpd_can_network_connect_db on 2>/dev/null || true
        warn "SELinux is enforcing: contexts set best-effort; if the web UI fails check audit.log"
    fi
    ok "owner $AST_USER, storage writable"

    say "Database schema"
    su -s /bin/bash $AST_USER -c "cd $APP_DIR && $PHP_BIN bin/console.php migrate" || die "migration failed"

    say "Apache"
    cat > /etc/httpd/conf.d/zz-autocaller.conf <<EOF
# AutoCaller (generated by install.sh) - does not touch issabel.conf / elastix.conf
Alias $WEB_PATH $APP_DIR/public
<Directory "$APP_DIR/public">
    Options -Indexes +FollowSymLinks
    AllowOverride None
    <IfModule mod_authz_core.c>
        Require all granted
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Allow from all
    </IfModule>
    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteBase $WEB_PATH/
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^ index.php [L]
    </IfModule>
    <IfModule mod_php5.c>
        php_value upload_max_filesize 64M
        php_value post_max_size 64M
        php_value memory_limit 256M
    </IfModule>
    <IfModule mod_php7.c>
        php_value upload_max_filesize 64M
        php_value post_max_size 64M
        php_value memory_limit 256M
    </IfModule>
    <IfModule mod_php.c>
        php_value upload_max_filesize 64M
        php_value post_max_size 64M
        php_value memory_limit 256M
    </IfModule>
    <IfModule php_module>
        php_value upload_max_filesize 64M
        php_value post_max_size 64M
        php_value memory_limit 256M
    </IfModule>
</Directory>
<Directory "$APP_DIR/public/assets">
    <IfModule mod_expires.c>
        ExpiresActive On
        ExpiresDefault "access plus 1 day"
    </IfModule>
</Directory>
EOF
    # php-fpm reads .user.ini instead of php_value
    cat > "$APP_DIR/public/.user.ini" <<EOF
upload_max_filesize = 64M
post_max_size = 64M
memory_limit = 256M
EOF
    chown $AST_USER:$AST_USER "$APP_DIR/public/.user.ini"
    chmod 600 "$STATE_FILE"; chown root:root "$STATE_FILE"
    apachectl configtest >/dev/null 2>&1 || warn "apachectl configtest reported a problem (check: apachectl configtest)"
    (systemctl restart httpd 2>/dev/null || service httpd restart) && ok "httpd restarted"
    systemctl restart php-fpm 2>/dev/null || true

    say "Dialer service (systemd)"
    cat > /etc/systemd/system/$SVC.service <<EOF
[Unit]
Description=AutoCaller dialer daemon (AMI originate engine)
After=network.target asterisk.service mariadb.service mysqld.service
Wants=asterisk.service

[Service]
Type=simple
User=$AST_USER
Group=$AST_USER
WorkingDirectory=$APP_DIR
ExecStart=$PHP_BIN $APP_DIR/bin/dialer.php
Restart=always
RestartSec=5
KillSignal=SIGTERM
TimeoutStopSec=20
Nice=-5

[Install]
WantedBy=multi-user.target
EOF
    systemctl daemon-reload
    systemctl enable $SVC >/dev/null 2>&1
    systemctl restart $SVC && ok "$SVC started"

    cat > /etc/cron.d/autocaller <<EOF
# AutoCaller housekeeping
17 3 * * *  $AST_USER  cd $APP_DIR && $PHP_BIN bin/console.php cleanup >> $APP_DIR/storage/logs/cron.log 2>&1
*/5 * * * * root       systemctl is-active --quiet $SVC || systemctl start $SVC
EOF
    cat > /etc/logrotate.d/autocaller <<EOF
$APP_DIR/storage/logs/*.log {
    weekly
    rotate 6
    compress
    missingok
    notifempty
    copytruncate
    su $AST_USER $AST_USER
}
EOF
    ok "cron + logrotate installed"

    say "Reloading Asterisk manager + dialplan"
    $AST_BIN -rx "manager reload" >/dev/null 2>&1 || true
    $AST_BIN -rx "dialplan reload" >/dev/null 2>&1 || true
    sleep 2
    if su -s /bin/bash $AST_USER -c "cd $APP_DIR && $PHP_BIN bin/console.php ami:test" >/dev/null 2>&1; then
        ok "AMI login test passed"
    else
        warn "AMI login test failed - check /etc/asterisk/manager.conf (enabled=yes, port 5038, bindaddr) then: systemctl restart $SVC"
    fi

    if [ "$UPGRADE" -eq 0 ]; then
        say "Admin user"
        local pw="${ADMIN_PASS:-}"
        while [ -z "$pw" ]; do
            echo -n "Password for web user 'admin' (min 8 chars): "; pw="$(asks)"; echo
            [ ${#pw} -ge 8 ] || { warn "too short"; pw=""; }
        done
        su -s /bin/bash $AST_USER -c "cd $APP_DIR && $PHP_BIN bin/console.php user:create admin admin '$pw'" >/dev/null && ok "user admin created"
    fi

    IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
    echo
    echo -e "${GREEN}✔ AutoCaller installed.${NC}"
    echo -e "   Panel:   ${CYAN}http://${IP:-SERVER-IP}$WEB_PATH${NC}   (or https, same as your Issabel panel)"
    echo -e "   Login:   admin   (Issabel panel users can also sign in)"
    echo -e "   Service: systemctl status $SVC     Logs: $APP_DIR/storage/logs/"
    echo -e "   Health:  cd $APP_DIR && sudo -u $AST_USER php bin/console.php doctor"
    echo
}

# ------------------------------------------------------------------ removal
# remove_all DROP_DB(0|1) DELETE_FILES(0|1)  -> leaves the system as if AutoCaller was never installed
remove_all() {
    local drop_db="$1" del_files="$2"
    detect_env 2>/dev/null || true
    [ -f "$STATE_FILE" ] && . "$STATE_FILE" 2>/dev/null || true

    say "Stopping service"
    systemctl stop $SVC 2>/dev/null; systemctl disable $SVC 2>/dev/null
    pkill -f "$APP_DIR/bin/dialer.php" 2>/dev/null || true
    rm -f /etc/systemd/system/$SVC.service /etc/cron.d/autocaller /etc/logrotate.d/autocaller
    systemctl daemon-reload 2>/dev/null; systemctl reset-failed $SVC 2>/dev/null || true
    ok "service, cron, logrotate removed"

    say "Asterisk"
    sed -i '/; BEGIN autocaller/,/; END autocaller/d' /etc/asterisk/extensions_custom.conf /etc/asterisk/manager_custom.conf 2>/dev/null
    if [ "${ADDED_MANAGER_INCLUDE:-0}" = "1" ]; then
        sed -i '/^#include manager_custom.conf ; added by autocaller$/d' /etc/asterisk/manager.conf 2>/dev/null
    fi
    # kill any lingering AMI session of our user and reload
    "${AST_BIN:-asterisk}" -rx "manager reload" >/dev/null 2>&1 || true
    "${AST_BIN:-asterisk}" -rx "dialplan reload" >/dev/null 2>&1 || true
    ok "AMI user + dialplan context removed"

    say "Apache"
    rm -f /etc/httpd/conf.d/zz-autocaller.conf
    if [ -n "${ADDED_GROUP:-}" ]; then
        gpasswd -d "$ADDED_GROUP" $AST_USER >/dev/null 2>&1 && ok "removed $ADDED_GROUP from group $AST_USER"
    fi
    (systemctl restart httpd 2>/dev/null || service httpd restart >/dev/null 2>&1) && ok "httpd restarted"
    # PHP sessions created by our panel (cookie name ACSESSID) - session files are not distinguishable, leave them to expire

    if [ "$drop_db" = "1" ]; then
        say "Database"
        if mysql_root_quiet; then
            mysql_q "DROP DATABASE IF EXISTS autocaller;" 2>/dev/null
            mysql_q "DROP USER IF EXISTS 'autocaller'@'localhost';" 2>/dev/null || mysql_q "DROP USER 'autocaller'@'localhost';" 2>/dev/null || true
            mysql_q "FLUSH PRIVILEGES;" 2>/dev/null
            ok "database and user 'autocaller' dropped"
        else
            warn "could not connect to MySQL as root - drop the database manually: DROP DATABASE autocaller; DROP USER 'autocaller'@'localhost';"
        fi
    fi

    if [ "$del_files" = "1" ]; then
        say "Files"
        rm -rf "$APP_DIR" /var/www/html/autocaller /tmp/autocaller-src.* /tmp/autocaller.zip 2>/dev/null
        ok "$APP_DIR removed"
    else
        rm -f "$STATE_FILE"
        warn "$APP_DIR kept"
    fi
    if [ "${INSTALLED_SOX:-0}" = "1" ] && [ "$del_files" = "1" ]; then
        (command -v dnf >/dev/null && dnf remove -y sox >/dev/null 2>&1) || yum remove -y sox >/dev/null 2>&1 || true
        ok "sox (installed by us) removed"
    fi
    echo -e "${GREEN}✔ AutoCaller removed.${NC}"
}

do_purge() {
    echo -e "${RED}Removing AutoCaller completely (files, database, AMI user, dialplan, Apache, service) - no questions asked.${NC}"
    remove_all 1 1
}

do_uninstall() {
    echo -e "${RED}This removes AutoCaller (service, Apache alias, dialplan/AMI entries).${NC}"
    echo -n "Type YES to continue: "; c="$(ask)"; [ "$c" = "YES" ] || { echo "cancelled"; return; }
    echo -n "Drop the database 'autocaller' too? [y/N] "; d="$(ask)"; local db=0; [ "$d" = "y" ] || [ "$d" = "Y" ] && db=1
    echo -n "Delete $APP_DIR including uploaded audio files? [y/N] "; f="$(ask)"; local fl=0; [ "$f" = "y" ] || [ "$f" = "Y" ] && fl=1
    remove_all "$db" "$fl"
}

do_doctor() {
    detect_env; print_env
    [ -f "$APP_DIR/config/config.php" ] || die "not installed"
    su -s /bin/bash $AST_USER -c "cd $APP_DIR && $PHP_BIN bin/console.php doctor"
    systemctl status $SVC --no-pager 2>/dev/null | head -5
}

do_admin() {
    detect_env
    [ -f "$APP_DIR/config/config.php" ] || die "not installed"
    echo -n "Username: "; u="$(ask)"
    echo -n "Password (min 8): "; p="$(asks)"; echo
    su -s /bin/bash $AST_USER -c "cd $APP_DIR && $PHP_BIN bin/console.php user:create '$u' admin '$p'"
}

# ------------------------------------------------------------------ menu
case "${1:-}" in
    install|upgrade) do_install; exit 0;;
    purge|remove) do_purge; exit 0;;
    uninstall) do_uninstall; exit 0;;
    doctor) do_doctor; exit 0;;
    admin) do_admin; exit 0;;
esac
while true; do
    echo
    echo -e "${YELLOW}+--------------------------------------------+${NC}"
    echo -e "${YELLOW}|${NC}   ${GREEN}A U T O   C A L L E R${NC}  v2  (Issabel 4/5)  ${YELLOW}|${NC}"
    echo -e "${YELLOW}+--------------------------------------------+${NC}"
    echo -e "${YELLOW}|${NC} 1. Install / Upgrade                       ${YELLOW}|${NC}"
    echo -e "${YELLOW}|${NC} 2. Uninstall (asks about DB / files)       ${YELLOW}|${NC}"
    echo -e "${YELLOW}|${NC} 3. PURGE - remove everything, no questions ${YELLOW}|${NC}"
    echo -e "${YELLOW}|${NC} 4. Doctor (health check)                   ${YELLOW}|${NC}"
    echo -e "${YELLOW}|${NC} 5. Create / reset an admin user            ${YELLOW}|${NC}"
    echo -e "${YELLOW}|${NC} 6. Quit                                    ${YELLOW}|${NC}"
    echo -e "${YELLOW}+--------------------------------------------+${NC}"
    echo -n "Choice: "; ch="$(ask)"
    case "$ch" in
        1) do_install;; 2) do_uninstall;; 3) do_purge;; 4) do_doctor;; 5) do_admin;; 6) exit 0;; *) echo "?";;
    esac
done
