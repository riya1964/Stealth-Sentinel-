#!/bin/bash

# ══ STEALTH SENTINEL v30.0 : ULTIMATE SECURITY SUITE ══
echo "════════════════════════════════════════════════════"
echo "       STEALTH SENTINEL : UNIFIED THREAT MANAGEMENT"
echo "════════════════════════════════════════════════════"

DB_USER="root"
DB_PASS="kali"
DB_NAME="stealth_sentinel"
LOG_DIR="/var/www/html/Stealth_Sentinel/logs"
PHISH_LOG="$LOG_DIR/phishing_alerts.log"

# ── MySQL helper ─────────────────────────────────────────────
db_query() {
    mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -se "$1" 2>/dev/null
}

# ── 1. Permissions ───────────────────────────────────────────
echo "[*] Setting permissions..."
sudo chmod +x user_logic.cpp logic_engine.cpp \
              malware_detector.cpp phishing_engine.py 2>/dev/null

# ── 2. Services start ────────────────────────────────────────
echo "[*] Initializing Database & Apache Server..."
sudo systemctl start mysql apache2

# ── 3. Blacklist/Whitelist tables banana ─────────────────────
echo "[*] Checking blacklist/whitelist tables..."

db_query "
CREATE TABLE IF NOT EXISTS blacklisting (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    domain     VARCHAR(255),
    ip         VARCHAR(45),
    reason     TEXT,
    added_on   DATETIME,
    added_by   VARCHAR(50) DEFAULT 'sentinel',
    UNIQUE KEY uniq_domain (domain),
    UNIQUE KEY uniq_ip (ip)
);"

db_query "
CREATE TABLE IF NOT EXISTS whitelisting (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    domain     VARCHAR(255),
    ip         VARCHAR(45),
    reason     TEXT,
    added_on   DATETIME,
    added_by   VARCHAR(50) DEFAULT 'sentinel',
    UNIQUE KEY uniq_domain (domain),
    UNIQUE KEY uniq_ip (ip)
);"

echo "[✓] Tables ready."

# ── 4. Default whitelist entries ─────────────────────────────
echo "[*] Loading default whitelist..."

add_whitelist() {
    local domain=$1
    local ip=$2
    local reason=$3
    db_query "INSERT IGNORE INTO whitelisting
              (domain, ip, reason, added_on, added_by)
              VALUES ('$domain','$ip','$reason',NOW(),'sentinel_core');"
}

# Local/private
add_whitelist "localhost"    "127.0.0.1"   "local machine"
add_whitelist "localhost"    "127.0.0.53"  "systemd-resolved"
add_whitelist ""             "192.168.1.1" "router gateway"
add_whitelist ""             "10.0.2.2"    "VirtualBox host"
add_whitelist ""             "10.0.2.3"    "VirtualBox DNS"

# Trusted domains
add_whitelist "google.com"       "" "trusted search engine"
add_whitelist "googleapis.com"   "" "Google APIs"
add_whitelist "gstatic.com"      "" "Google static"
add_whitelist "microsoft.com"    "" "Microsoft"
add_whitelist "windowsupdate.com" "" "Windows Update"
add_whitelist "apple.com"        "" "Apple"
add_whitelist "mozilla.com"      "" "Firefox browser"
add_whitelist "mozilla.net"      "" "Firefox updates"
add_whitelist "firefox.com"      "" "Firefox"
add_whitelist "ubuntu.com"       "" "Ubuntu updates"
add_whitelist "kali.org"         "" "Kali Linux"
add_whitelist "debian.org"       "" "Debian"
add_whitelist "phpmyadmin"       "" "local phpMyAdmin"
add_whitelist "msedge.net"       "" "Microsoft Edge"

echo "[✓] Whitelist loaded."

# ── 5. Apply existing blacklist to iptables + /etc/hosts ─────
echo "[*] Applying blacklist rules..."

# Blocked IPs → iptables
BLOCKED_IPS=$(db_query "SELECT ip FROM blacklisting WHERE ip IS NOT NULL AND ip != '';")
for ip in $BLOCKED_IPS; do
    # Whitelist mein hai? Skip karo
    WL=$(db_query "SELECT id FROM whitelisting WHERE ip='$ip' LIMIT 1;")
    if [ -z "$WL" ]; then
        sudo iptables -I INPUT -s "$ip" -j DROP 2>/dev/null
        echo "   [FW] Blocked IP: $ip"
    fi
done

# Blocked domains → /etc/hosts
BLOCKED_DOMAINS=$(db_query "SELECT domain FROM blacklisting WHERE domain IS NOT NULL AND domain != '';")
for domain in $BLOCKED_DOMAINS; do
    # /etc/hosts mein already hai? Skip karo
    if ! grep -q "127.0.0.1 $domain" /etc/hosts; then
        echo "127.0.0.1 $domain  # Stealth Sentinel blocked" | sudo tee -a /etc/hosts > /dev/null
        echo "   [DNS] Blocked domain: $domain"
    fi
done

echo "[✓] Blacklist rules applied."

# ── 6. Compile modules ───────────────────────────────────────
echo "[*] Compiling All Security Modules..."

g++ user_logic.cpp    -o profiler        -lmysqlclient 2>/dev/null
g++ logic_engine.cpp  -o web_engine      -lpcap -lmysqlclient 2>/dev/null
g++ malware_detector.cpp -o malware_engine -lpcap -lmysqlclient 2>/dev/null
echo "[i] Phishing Engine (Python) ready. Skipping C++ compilation..."
echo "[✓] All Modules Compiled Successfully."

# ── 7. Execute Profiler ──────────────────────────────────────
if [ -f "./profiler" ]; then
    echo "[*] Capturing Live System Metadata..."
    sudo ./profiler
else
    echo "[X] Profiler failed to compile!"
    exit 1
fi

# ── 8. Phishing log processor (background) ───────────────────
# Ye loop phishing_alerts.log padhta hai aur
# naye entries ko attack table mein save karta hai
process_phishing_log() {
    local processed_file="$LOG_DIR/phishing_processed.log"
    touch "$processed_file"

    while true; do
        if [ -f "$PHISH_LOG" ]; then
            while IFS= read -r line; do
                # Already processed? Skip
                if grep -qF "$line" "$processed_file"; then
                    continue
                fi

                # Parse pipe-separated log
                # Format: timestamp|src_ip|src_mac|url|domain|lex|infra|final|reasons|action|port|pkg_size
                IFS='|' read -r ts src_ip src_mac url domain \
                               lex infra final reasons action port pkg_size <<< "$line"

                if [ -n "$url" ]; then
                    risk="Medium"
                    taken="Observed"
                    if [ "$action" = "BLOCKED" ]; then
                        risk="High"
                        taken="Blocked"
                    fi

                    # Attack table mein insert
                    db_query "INSERT INTO attack
                        (pkg_type, pkg_size, port, tool_used,
                         target_url, time_of_performing_attack,
                         scan_type, risk_level, action_taken)
                        VALUES ('TCP_PACKET','$pkg_size','$port',
                                'Phishing-Engine','$url','$ts',
                                'Phishing-Scan','$risk','$taken');"

                    # Blacklist update — BLOCKED ho to
                    if [ "$action" = "BLOCKED" ]; then
                        db_query "INSERT IGNORE INTO blacklisting
                            (domain, ip, reason, added_on, added_by)
                            VALUES ('$domain','$src_ip',
                                    '$reasons','$ts','phishing_engine');"

                        # /etc/hosts mein add karo agar nahi hai
                        if ! grep -q "127.0.0.1 $domain" /etc/hosts; then
                            echo "127.0.0.1 $domain  # auto-blocked" \
                                | sudo tee -a /etc/hosts > /dev/null
                        fi
                    fi

                    # Processed mark karo
                    echo "$line" >> "$processed_file"
                fi
            done < "$PHISH_LOG"
        fi
        sleep 5  # har 5 second mein check karo
    done
}

echo "[*] Starting phishing log processor..."
process_phishing_log &

# ── 9. Launch all engines ────────────────────────────────────
echo "════════════════════════════════════════════════════"
echo " STATUS: [ WEB: LIVE | MALWARE: LIVE | PHISH: LIVE ]"
echo "════════════════════════════════════════════════════"
echo "[i] Monitoring eth0 for real-time threats..."
echo "[i] Press CTRL+C to stop the sequence."

sudo ./web_engine      &
sudo ./malware_engine  &
sudo python3 phishing_engine.py &

wait
