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

            mysql -uroot -p"${rootpasswd}" -e "CREATE DATABASE IF NOT EXISTS callblaster;"
            mysql -uroot -p"${rootpasswd}" -e "CREATE USER IF NOT EXISTS 'callblaster'@'localhost' IDENTIFIED BY 'callblaster';"
            mysql -uroot -p"${rootpasswd}" -e "GRANT ALL PRIVILEGES ON callblaster.* TO 'callblaster'@'localhost';"

            command -v unzip >/dev/null 2>&1 || yum install -y unzip

            cd /var/www/html/ || exit 1
            wget -q "https://raw.githubusercontent.com/${REPO}/main/autocaller.zip" -O autocaller.zip
            if [ ! -s autocaller.zip ]; then
                echo -e "${RED}Download failed. Check your internet connection or repo URL.${NC}"
                sleep 3
                continue
            fi

            rm -rf "$APP_DIR"
            unzip -o -q autocaller.zip
            rm -f autocaller.zip

            chown -R asterisk:asterisk "$APP_DIR" 2>/dev/null
            find "$APP_DIR" -type d -exec chmod 775 {} \;
            find "$APP_DIR" -type f -exec chmod 664 {} \;
            # مسیرهایی که برنامه در حین اجرا روشون می‌نویسه (تنظیمات، کنترل کمپین، آپلود فایل، لاگ)
            chmod -R 775 "$APP_DIR/files" "$APP_DIR/logs" "$APP_DIR/callFiles" "$APP_DIR/tmp" 2>/dev/null
            chmod 664 "$APP_DIR/config.ini" "$APP_DIR/control.ini" 2>/dev/null
            # SELinux (روی CentOS/Rocky که Issabel/Elastix معمولاً روشه) - اجازه‌ی نوشتن به آپاچی می‌ده
            if command -v chcon >/dev/null 2>&1; then
                chcon -R -t httpd_sys_rw_content_t "$APP_DIR" 2>/dev/null
            fi
            chmod -R 777 /var/spool/asterisk

            # دایال‌پلن (فقط یک‌بار اضافه می‌شه، در نصب مجدد تکراری نمی‌شه)
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

            # کانفیگ Apache اختصاصی
            [ -f /etc/httpd/conf.d/issabel.conf ] && mv -f /etc/httpd/conf.d/issabel.conf /etc/httpd/conf.d/issabel.conf.bak
            [ -f /etc/httpd/conf.d/elastix.conf ] && mv -f /etc/httpd/conf.d/elastix.conf /etc/httpd/conf.d/elastix.conf.bak
            wget -q "https://raw.githubusercontent.com/${REPO}/main/issabel.conf" -O /etc/httpd/conf.d/issabel.conf
            wget -q "https://raw.githubusercontent.com/${REPO}/main/elastix.conf" -O /etc/httpd/conf.d/elastix.conf

            service httpd restart 2>/dev/null || systemctl restart httpd 2>/dev/null
            service asterisk restart 2>/dev/null || systemctl restart asterisk 2>/dev/null

            echo -e "${GREEN}Installation complete.${NC}"
            echo -e "${GREEN}Control panel: http://[server-ip]/autocaller${NC}"
            sleep 3
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

            mysql -uroot -p"${rootpasswd}" -e "DROP DATABASE IF EXISTS callblaster;" 2>/dev/null
            mysql -uroot -p"${rootpasswd}" -e "DROP USER IF EXISTS 'callblaster'@'localhost';" 2>/dev/null

            rm -rf "$APP_DIR"
            rm -f /var/www/html/autocaller.zip

            sed -i '/\[callblaster\]/,+1d' /etc/asterisk/extensions.conf 2>/dev/null
            sed -i '/\[callblaster\]/,+1d' /etc/asterisk/extensions_custom.conf 2>/dev/null

            [ -f /etc/httpd/conf.d/issabel.conf.bak ] && mv -f /etc/httpd/conf.d/issabel.conf.bak /etc/httpd/conf.d/issabel.conf
            [ -f /etc/httpd/conf.d/elastix.conf.bak ] && mv -f /etc/httpd/conf.d/elastix.conf.bak /etc/httpd/conf.d/elastix.conf

            service httpd restart 2>/dev/null || systemctl restart httpd 2>/dev/null
            service asterisk restart 2>/dev/null || systemctl restart asterisk 2>/dev/null

            echo -e "${GREEN}Uninstall complete.${NC}"
            sleep 3
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
