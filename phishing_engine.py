import os, re, math
import mysql.connector
from datetime import datetime
from urllib.parse import urlparse
from scapy.all import sniff, DNS, DNSQR, IP, Ether, Raw
from threading import Thread

LOG_FILE = "/var/www/html/Stealth_Sentinel/logs/phishing_alerts.log"

# ── MySQL CONFIG ──────────────────────────────────────────────
DB_CONFIG = {
    "host":     "localhost",
    "user":     "root",
    "password": "toor",
    "database": "stealth_sentinel"
}

def write_log(msg):
    try:
        with open(LOG_FILE, "a") as f:
            f.write(msg + "\n")
    except Exception as e:
        print(f"   [LOG-ERR] {e}")

def get_db():
    return mysql.connector.connect(**DB_CONFIG)

# Whitelist check — DB se
def is_whitelisted_db(domain, ip):
    try:
        con = get_db()
        cur = con.cursor()
        cur.execute(
            "SELECT id FROM whitelisting WHERE domain=%s OR ip=%s LIMIT 1",
            (domain, ip)
        )
        result = cur.fetchone()
        con.close()
        return result is not None
    except:
        return False

# Blacklist mein add karo
def add_to_blacklist(domain, ip, reason):
    try:
        con = get_db()
        cur = con.cursor()
        cur.execute("""
            INSERT IGNORE INTO blacklisting
            (domain, ip, reason, added_on)
            VALUES (%s, %s, %s, %s)
        """, (domain, ip, reason, datetime.now().strftime("%Y-%m-%d %H:%M:%S")))
        con.commit()
        con.close()
        print(f"   [BL] {domain} added to blacklist ✓")
    except Exception as e:
        print(f"   [BL-ERR] {e}")

# Attack table mein save karo
def save_to_attack(url, domain, pkg_size, port, final, action):
    try:
        con = get_db()
        cur = con.cursor()
        risk  = "High"    if final >= 70 else "Medium"
        taken = "Blocked" if final >= 70 else "Observed"
        cur.execute("""
            INSERT INTO attack
            (pkg_type, pkg_size, port, tool_used,
             target_url, time_of_performing_attack,
             scan_type, risk_level, action_taken)
            VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s)
        """, (
            "TCP_PACKET", pkg_size, port,
            "Phishing-Engine", url[:255],
            datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
            "Phishing-Scan", risk, taken
        ))
        con.commit()
        con.close()
        print(f"   [DB] Saved to attack table ✓")
    except Exception as e:
        print(f"   [DB-ERR] {e}")

# ── LAYER 1: LEXICAL ANALYZER ────────────────────────────────
class URLAnalyzer:
    brands   = ['google','apple','microsoft','amazon','facebook',
                'instagram','paypal','hdfc','icici','sbi','paytm']
    legit    = ['google.com','apple.com','microsoft.com','amazon.com',
                'facebook.com','instagram.com','paytm.com',
                'googleapis.com','gstatic.com','goog']
    keywords = ['login','verify','account','update','banking',
                'secure','signin','confirm','wallet','otp']
    WHITELIST = [
        'msedge.net','microsoft.com','windowsupdate.com',
        'nel.goog','googleapis.com','gstatic.com','google.com',
        'apple.com','ocsp.','crl.','pki.','localhost',
        'dns.msftncsi.com','a-ring.','b-ring.',
        'firefox.com','mozilla.com','mozilla.net',
        'ubuntu.com','kali.org','debian.org'
    ]

    def is_whitelisted(self, domain):
        return any(w in domain for w in self.WHITELIST)

    def entropy(self, s):
        if not s: return 0
        return round(-sum((s.count(c)/len(s)) * math.log2(s.count(c)/len(s))
                         for c in set(s)), 2)

    def analyze(self, url):
        score, reasons = 0, []
        domain = urlparse(url).netloc.lower().split(':')[0]

        if not any(l in domain for l in self.legit):
            if any(b in domain for b in self.brands):
                score += 45; reasons.append("brand-spoof")

        if domain.count('-') > 2:
            score += 25; reasons.append("excess-hyphens")

        if "@" in url:
            score += 50; reasons.append("@-masking")

        if self.entropy(domain) > 4.0:
            score += 20; reasons.append("high-entropy")

        if any(k in url.lower() for k in self.keywords):
            score += 15; reasons.append("danger-keyword")

        if len(domain) > 40:
            score += 15; reasons.append("long-domain")

        return score, reasons

# ── LAYER 2: INFRASTRUCTURE HUNTER ──────────────────────────
class InfrastructureHunter:
    def analyze(self, domain):
        score, reasons = 0, []
        try:
            import whois as w_module
            w = w_module.whois(domain)
            created = w.creation_date
            if isinstance(created, list): created = created[0]
            if created:
                age = (datetime.now() - created).days
                if age < 14:
                    score += 65; reasons.append(f"new({age}d)")
                elif age < 90:
                    score += 30; reasons.append(f"young({age}d)")
        except:
            score += 15; reasons.append("whois-fail")
        return score, reasons

# ── MASTER ENGINE ────────────────────────────────────────────
class StealthSentinel:
    def __init__(self):
        self.lex             = URLAnalyzer()
        self.hunter          = InfrastructureHunter()
        self.blocked_ips     = set()
        self.blocked_domains = set()
        self.scanned         = 0
        self.threats         = 0

    # ── IP whitelist check (private/local IPs) ───────────────
    def is_ip_safe(self, ip):
        return (ip.startswith('127.')    or
                ip.startswith('192.168.') or
                ip.startswith('10.')      or
                ip.startswith('172.')     or
                ip in ('0.0.0.0', 'unknown'))

    # ── BLOCK: IP + Domain ───────────────────────────────────
    def block_ip_and_domain(self, ip, domain, reasons):
        # IP block — private/local IPs skip
        if not self.is_ip_safe(ip):
            if ip not in self.blocked_ips:
                os.system(f"sudo iptables -I INPUT -s {ip} -j DROP 2>/dev/null")
                self.blocked_ips.add(ip)
                print(f"   [FW]  iptables INPUT DROP {ip} ✓")
        else:
            print(f"   [SKIP] {ip} is private IP — only domain blocked")

        # Domain → /etc/hosts mein local alert page pe redirect
        if domain not in self.blocked_domains:
            os.system(
                f"echo '127.0.0.1 {domain}' | sudo tee -a /etc/hosts > /dev/null"
            )
            os.system("sudo systemctl restart systemd-resolved 2>/dev/null")
            self.blocked_domains.add(domain)
            print(f"   [DNS] {domain} → 127.0.0.1 (alert page) ✓")

            # Blacklist DB mein add karo
            add_to_blacklist(domain, ip, ','.join(reasons))

    # ── EVALUATE URL ─────────────────────────────────────────
    def evaluate_url(self, url, src_ip, src_mac="unknown",
                     pkg_size=0, port=80):
        domain = urlparse(url).netloc.lower().split(':')[0]
        if not domain: return

        # Static whitelist check
        if self.lex.is_whitelisted(domain): return

        # Already blocked domain → skip
        if domain in self.blocked_domains: return

        # DB whitelist check — sentinel_core.sh ne jo whitelist ki hai
        if is_whitelisted_db(domain, src_ip):
            print(f"\n [⚪] WHITELISTED | {domain} | {src_ip}")
            return

        self.scanned += 1
        lex_score,   lex_r   = self.lex.analyze(url)
        infra_score, infra_r = (self.hunter.analyze(domain)
                                if lex_score > 20 else (0, []))
        final   = min(lex_score + infra_score, 100)
        reasons = lex_r + infra_r

        if   final >= 70: action = "BLOCKED"
        elif final >= 40: action = "ALERT"
        else:             action = "SAFE"

        # ── TERMINAL OUTPUT ──────────────────────────────────
        icon = {"BLOCKED":"🔴","ALERT":"🟡","SAFE":"🟢"}[action]
        print(f"\n{'─'*55}")
        print(f" {icon} {action}  |  Score: {final}/100  |  #{self.scanned}")
        print(f" URL   : {url[:80]}")
        print(f" From  : {src_ip}  MAC: {src_mac}")
        if reasons:
            print(f" Flags : {', '.join(reasons)}")

        # ── ALERT/BLOCKED → DB + LOG ─────────────────────────
        if final >= 40:
            # Attack table mein save
            save_to_attack(url, domain, pkg_size, port, final, action)

            # Log file mein bhi likho
            log_line = (
                f"{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}|"
                f"{src_ip}|{src_mac}|{url}|{domain}|"
                f"{lex_score}|{infra_score}|{final}|"
                f"{','.join(reasons)}|{action}|{port}|{pkg_size}"
            )
            write_log(log_line)
            print(f"   [LOG] Written to phishing_alerts.log")

        # ── BLOCK ────────────────────────────────────────────
        if final >= 70:
            self.threats += 1
            self.block_ip_and_domain(src_ip, domain, reasons)
            print(f"   [!!] THREAT #{self.threats} BLOCKED → alert page active")

    # ── PACKET CALLBACKS ─────────────────────────────────────
    def http_callback(self, pkt):
        if not (pkt.haslayer(IP) and pkt.haslayer(Raw)): return
        try:
            payload  = pkt[Raw].load.decode('utf-8', errors='ignore')
            urls     = re.findall(r'http[s]?://[^\s"\'<>\r\n]+', payload)
            src_ip   = pkt[IP].src
            src_mac  = pkt[Ether].src if pkt.haslayer(Ether) else "unknown"
            pkg_size = len(pkt)
            for url in set(urls):
                self.evaluate_url(url, src_ip, src_mac, pkg_size, 80)
        except: pass

    def dns_callback(self, pkt):
        if not (pkt.haslayer(DNS) and pkt.haslayer(DNSQR)): return
        try:
            if pkt[DNSQR].qtype not in (1, 28): return
            domain  = pkt[DNSQR].qname.decode().rstrip('.')
            src_ip  = pkt[IP].src    if pkt.haslayer(IP)    else "unknown"
            src_mac = pkt[Ether].src if pkt.haslayer(Ether) else "unknown"
            self.evaluate_url(f"http://{domain}", src_ip, src_mac, 0, 53)
        except: pass

    # ── RUN ──────────────────────────────────────────────────
    def run(self):
        print("=" * 55)
        print("  🛡️  STEALTH SENTINEL — PHISHING ENGINE")
        print("=" * 55)
        print("  DB   : stealth_sentinel.attack + blacklisting")
        print("  Mode : HTTP(80) + DNS(53) dual sniff")
        print("  BL/WL: sentinel_core.sh handles blacklist/whitelist")
        print("  Press Ctrl+C to stop\n")

        t1 = Thread(
            target=lambda: sniff(
                filter="tcp port 80 or tcp port 8080",
                prn=self.http_callback, store=0),
            daemon=True)
        t2 = Thread(
            target=lambda: sniff(
                filter="udp port 53",
                prn=self.dns_callback, store=0),
            daemon=True)

        t1.start(); t2.start()

        try:
            t1.join(); t2.join()
        except KeyboardInterrupt:
            print(f"\n{'='*55}")
            print(f"  [*] Sentinel stopped.")
            print(f"  [*] Total scanned  : {self.scanned}")
            print(f"  [*] Threats blocked: {self.threats}")
            print(f"  [*] Blocked domains: {len(self.blocked_domains)}")
            print(f"{'='*55}")

if __name__ == "__main__":
    sentinel = StealthSentinel()
    sentinel.run()
