#!/bin/bash
#
# Auto Caller — Installer
# imapro.ir
#
set -u

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

APP_DIR="/var/www/html/autocaller"
REPO="milad-mma/VOIP-Auto-Caller"

if [[ $EUID -ne 0 ]]; then
   echo "This script must be run as root"
   sleep 1
   exec sudo "$0" "$@"
fi

clear

while true; do
    clear
    echo -e "${YELLOW}+--------------------------------------------------+${NC}"
    echo -e "${GREEN}|         A U T O   C A L L E R                    ${NC}${YELLOW}|${NC}"
    echo -e "${BLUE}|                                         ver 1.0  |${NC}"
    echo -e "${BLUE}|${NC}                imapro.ir                          ${BLUE}|${NC}"
    echo -e "${BLUE}|            ---------------------------           |${NC}"
    echo -e "${BLUE}|                      ${GREEN}Main Menu${BLUE}                   |${NC}"
    echo -e "${GREEN}|     ---------------------------------------      |${NC}"
    echo -e "${BLUE}|${YELLOW} 1.${NC} ${CYAN}INSTALL${NC}                                       ${BLUE}|${NC}"
    echo -e "${BLUE}|${YELLOW} 2.${NC} ${RED}UNINSTALL (remove everything)${NC}                 ${BLUE}|${NC}"
    echo -e "${BLUE}|${YELLOW} 3.${NC} ${RED}QUIT${NC}                                           ${BLUE}|${NC}"
    echo -e "${YELLOW}+--------------------------------------------------+${NC}"
    echo ""
    read -p "Enter option number: " choice

    case $choice in
        1)
            echo -e "${GREEN}Starting installation...${NC}"
            echo "Please enter MySQL root password: "
            read -s rootpasswd
            echo ""

            RESULT=$(mysqlshow --user=root --password="${rootpasswd}" asterisk 2>/dev/null | grep -v Wildcard | grep -o asterisk)
            if [ "$RESULT" != "asterisk" ]; then
                echo -e "${RED}MySQL password is incorrect, or 'asterisk' database not found.${NC}"
                sleep 3
                continue
            fi
            echo -e "${GREEN}MySQL credentials verified.${NC}"

            echo -e "${CYAN}[1/6] Creating database and user...${NC}"
            mysql -uroot -p"${rootpasswd}" -e "CREATE DATABASE IF NOT EXISTS callblaster;"
            mysql -uroot -p"${rootpasswd}" -e "CREATE USER IF NOT EXISTS 'callblaster'@'localhost' IDENTIFIED BY 'callblaster';"
            mysql -uroot -p"${rootpasswd}" -e "GRANT ALL PRIVILEGES ON callblaster.* TO 'callblaster'@'localhost';"
            echo -e "${GREEN}      done.${NC}"

            command -v unzip >/dev/null 2>&1 || { echo -e "${CYAN}[2/6] Installing unzip...${NC}"; yum install -y unzip; }

            echo -e "${CYAN}[2/6] Downloading application files...${NC}"
            cd /var/www/html/ || exit 1
            wget -q "https://raw.githubusercontent.com/${REPO}/main/autocaller.zip" -O autocaller.zip
            if [ ! -s autocaller.zip ]; then
                echo -e "${RED}Download failed. Check your internet connection or repo URL.${NC}"
                sleep 3
                continue
            fi
            echo -e "${GREEN}      done.${NC}"

            echo -e "${CYAN}[3/6] Extracting files to ${APP_DIR}...${NC}"
            rm -rf "$APP_DIR"
            unzip -o -q autocaller.zip
            rm -f autocaller.zip
            echo -e "${GREEN}      done.${NC}"

            echo -e "${CYAN}[4/6] Setting file ownership and permissions...${NC}"
            chown -R asterisk:asterisk "$APP_DIR" 2>/dev/null
            find "$APP_DIR" -type d -exec chmod 775 {} \;
            find "$APP_DIR" -type f -exec chmod 664 {} \;
            # مسیرهایی که برنامه در حین اجرا روشون می‌نویسه (تنظیمات، کنترل کمپین، آپلود فایل، لاگ)
            chmod -R 775 "$APP_DIR/files" "$APP_DIR/logs" "$APP_DIR/callFiles" "$APP_DIR/tmp" 2>/dev/null
            chmod 664 "$APP_DIR/config.ini" "$APP_DIR/control.ini" 2>/dev/null
            # callblaster.php مستقیم توسط Asterisk (AGI) به‌عنوان اسکریپت اجرا می‌شود، پس باید execute داشته باشد
            chmod 755 "$APP_DIR/callblaster.php" 2>/dev/null
            # SELinux (روی CentOS/Rocky که Issabel/Elastix معمولاً روشه) - اجازه‌ی نوشتن به آپاچی می‌ده
            if command -v chcon >/dev/null 2>&1; then
                chcon -R -t httpd_sys_rw_content_t "$APP_DIR" 2>/dev/null
            fi
            chmod -R 777 /var/spool/asterisk
            echo -e "${GREEN}      done.${NC}"

            echo -e "${CYAN}[5/6] Registering Asterisk dialplan...${NC}"
            grep -q "\[callblaster\]" /etc/asterisk/extensions.conf 2>/dev/null || {
                {
                    echo "[callblaster]"
                    echo "exten => 333,1,AGI(${APP_DIR}/callblaster.php)"
                } >> /etc/asterisk/extensions.conf
            }
            grep -q "\[callblaster\]" /etc/asterisk/extensions_custom.conf 2>/dev/null || {
                {
                    echo ""
                    echo "[callblaster]"
                    echo "exten => 333,1,AGI(${APP_DIR}/callblaster.php)"
                } >> /etc/asterisk/extensions_custom.conf
            }
            echo -e "${GREEN}      done.${NC}"

            echo -e "${CYAN}[6/6] Configuring Apache and restarting services...${NC}"
            # کانفیگ Apache اختصاصی
            [ -f /etc/httpd/conf.d/issabel.conf ] && mv -f /etc/httpd/conf.d/issabel.conf /etc/httpd/conf.d/issabel.conf.bak
            [ -f /etc/httpd/conf.d/elastix.conf ] && mv -f /etc/httpd/conf.d/elastix.conf /etc/httpd/conf.d/elastix.conf.bak
            wget -q "https://raw.githubusercontent.com/${REPO}/main/issabel.conf" -O /etc/httpd/conf.d/issabel.conf
            wget -q "https://raw.githubusercontent.com/${REPO}/main/elastix.conf" -O /etc/httpd/conf.d/elastix.conf

            service httpd restart 2>/dev/null || systemctl restart httpd 2>/dev/null
            service asterisk restart 2>/dev/null || systemctl restart asterisk 2>/dev/null
            echo -e "${GREEN}      done.${NC}"

            # از این به بعد خود اسکریپت رو داخل پوشه‌ی پروژه نگه می‌داریم، نه توی /root
            SCRIPT_PATH="$(readlink -f "$0" 2>/dev/null || echo "$0")"
            if [ "$SCRIPT_PATH" != "$APP_DIR/install.sh" ]; then
                cp -f "$SCRIPT_PATH" "$APP_DIR/install.sh" 2>/dev/null
                chmod 755 "$APP_DIR/install.sh" 2>/dev/null
                rm -f -- "$SCRIPT_PATH"
            fi

            echo ""
            echo -e "${GREEN}✔ Installation complete.${NC}"
            echo -e "${GREEN}Control panel: http://[server-ip]/autocaller${NC}"
            echo -e "${YELLOW}From now on, manage this app with: cd ${APP_DIR} && ./install.sh${NC}"
            echo ""
            read -p "Press Enter to return to the menu..." _
            ;;

        2)
            echo -e "${RED}This will permanently remove all files, the database, and related config.${NC}"
            read -p "Type YES to confirm: " confirm
            if [ "$confirm" != "YES" ]; then
                echo -e "${YELLOW}Cancelled.${NC}"
                sleep 2
                continue
            fi

            echo "Please enter MySQL root password: "
            read -s rootpasswd
            echo ""

            echo -e "${CYAN}Removing database...${NC}"
            mysql -uroot -p"${rootpasswd}" -e "DROP DATABASE IF EXISTS callblaster;" 2>/dev/null
            mysql -uroot -p"${rootpasswd}" -e "DROP USER IF EXISTS 'callblaster'@'localhost';" 2>/dev/null

            echo -e "${CYAN}Removing application files...${NC}"
            rm -rf "$APP_DIR"
            rm -f /var/www/html/autocaller.zip

            echo -e "${CYAN}Removing dialplan entries...${NC}"
            sed -i '/\[callblaster\]/,+1d' /etc/asterisk/extensions.conf 2>/dev/null
            sed -i '/\[callblaster\]/,+1d' /etc/asterisk/extensions_custom.conf 2>/dev/null

            echo -e "${CYAN}Restoring Apache config and restarting services...${NC}"
            [ -f /etc/httpd/conf.d/issabel.conf.bak ] && mv -f /etc/httpd/conf.d/issabel.conf.bak /etc/httpd/conf.d/issabel.conf
            [ -f /etc/httpd/conf.d/elastix.conf.bak ] && mv -f /etc/httpd/conf.d/elastix.conf.bak /etc/httpd/conf.d/elastix.conf

            service httpd restart 2>/dev/null || systemctl restart httpd 2>/dev/null
            service asterisk restart 2>/dev/null || systemctl restart asterisk 2>/dev/null

            echo ""
            echo -e "${GREEN}✔ Uninstall complete.${NC}"
            SCRIPT_PATH="$(readlink -f "$0" 2>/dev/null || echo "$0")"
            echo -e "${YELLOW}Removing this installer script (${SCRIPT_PATH})...${NC}"
            echo ""
            read -p "Press Enter to exit..." _
            rm -f -- "$SCRIPT_PATH"
            exit 0
            ;;

        3)
            echo -e "${RED}Exiting...${NC}"
            exit 0
            ;;
        *)
            echo -e "${YELLOW}Invalid option.${NC}"
            sleep 1
            ;;
    esac
done
