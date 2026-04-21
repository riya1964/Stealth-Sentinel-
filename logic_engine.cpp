#include <iostream>
#include <pcap.h>
#include <netinet/ip.h>
#include <netinet/tcp.h>
#include <netinet/udp.h>
#include <netinet/ip_icmp.h>
#include <netinet/ether.h>
#include <arpa/inet.h>
#include <mysql/mysql.h>
#include <string>
#include <map>
#include <cstdlib>
#include <cstring>
#include <algorithm>
#include <unistd.h>

using namespace std;

MYSQL* conn;
map<string, int>  ip_track;
map<string, bool> is_blocked;

// ── DB se whitelist check ────────────────────────────────────
bool is_whitelisted(const string& ip) {
    string query = "SELECT id FROM whitelisting WHERE ip='" + ip + "' LIMIT 1";
    if (mysql_query(conn, query.c_str())) return false;
    MYSQL_RES* res = mysql_store_result(conn);
    bool found = (res && mysql_num_rows(res) > 0);
    if (res) mysql_free_result(res);
    return found;
}

// ── DB se blacklist check ────────────────────────────────────
bool is_blacklisted(const string& ip) {
    string query = "SELECT id FROM blacklisting WHERE ip='" + ip + "' LIMIT 1";
    if (mysql_query(conn, query.c_str())) return false;
    MYSQL_RES* res = mysql_store_result(conn);
    bool found = (res && mysql_num_rows(res) > 0);
    if (res) mysql_free_result(res);
    return found;
}

// ── Blacklist mein add karo ──────────────────────────────────
void add_to_blacklist(const string& ip, const string& reason) {
    string query = "INSERT IGNORE INTO blacklisting "
                   "(ip, reason, added_on, added_by) VALUES ('"
                   + ip + "','" + reason + "', NOW(), 'logic_engine')";
    mysql_query(conn, query.c_str());
}

// ── Attack table mein log karo ───────────────────────────────
void log_to_db(int port, string type, int size, string tool,
               string url, string meth, string risk, string act) {
    string sql = "INSERT INTO attack (port, pkg_type, pkg_size, tool_used, "
                 "target_url, request_method, risk_level, "
                 "time_of_performing_attack, action_taken) VALUES ('"
                 + to_string(port) + "','" + type   + "','"
                 + to_string(size) + "','" + tool   + "','"
                 + url             + "','" + meth   + "','"
                 + risk            + "', NOW(), '"   + act + "')";
    mysql_query(conn, sql.c_str());
}

// ── Attack signature detection ───────────────────────────────
string analyze_payload(const char* payload, int len) {
    if (len <= 0) return "Clean";
    string data(payload, len);
    string low = data;
    transform(low.begin(), low.end(), low.begin(), ::tolower);

    if (low.find("../")      != string::npos ||
        low.find("etc/passwd") != string::npos)
        return "Path Traversal Attack";

    if (low.find("/bin/bash") != string::npos ||
        low.find(";")         != string::npos)
        return "Command Injection Attempt";

    if (low.find("select")   != string::npos &&
        low.find("union")    != string::npos)
        return "SQL Injection Attempt";

    if (low.find("<script>")  != string::npos ||
        low.find("alert(")   != string::npos)
        return "XSS Attack";

    if (low.find("burp")      != string::npos ||
        low.find("intruder")  != string::npos)
        return "Burp Suite Activity";

    if (low.find("nmap")      != string::npos ||
        low.find("masscan")   != string::npos)
        return "Port Scanner Detected";

    return "Clean";
}

// ── Packet handler ───────────────────────────────────────────
void packet_handler(u_char* args,
                    const struct pcap_pkthdr* header,
                    const u_char* packet) {

    struct ether_header* eth = (struct ether_header*)packet;
    struct ip* iph           = (struct ip*)(packet + 14);

    if (header->len < 14) return;

    // Source + Dest IP
    char sip[INET_ADDRSTRLEN], dip[INET_ADDRSTRLEN];
    inet_ntop(AF_INET, &(iph->ip_src), sip, INET_ADDRSTRLEN);
    inet_ntop(AF_INET, &(iph->ip_dst), dip, INET_ADDRSTRLEN);
    string sip_str(sip), dip_str(dip);

    // MAC address
    char smac[18];
    sprintf(smac, "%02x:%02x:%02x:%02x:%02x:%02x",
            eth->ether_shost[0], eth->ether_shost[1],
            eth->ether_shost[2], eth->ether_shost[3],
            eth->ether_shost[4], eth->ether_shost[5]);
    string smac_str(smac);

    // Loopback + self traffic filter
    if (sip_str == "127.0.0.1" || dip_str == "127.0.0.1") return;
    if (sip_str == dip_str) return;

    // ── Whitelist check ──────────────────────────────────────
    if (is_whitelisted(sip_str)) {
        printf("[⚪] WHITELISTED | %-15s → %s\n",
               sip_str.c_str(), dip_str.c_str());
        fflush(stdout);
        return;
    }

    // ── Already blocked → skip ───────────────────────────────
    if (is_blocked[sip_str]) return;

    // Default values
    string pkg_type = "UNKNOWN";
    string tool     = "Normal Traffic";
    string risk     = "Low";
    string action   = "Logged";
    string method   = "N/A";
    int    port     = 0;

    // ════════════════════════════════════════════════════════
    // ICMP LOGIC
    // ════════════════════════════════════════════════════════
    if (iph->ip_p == IPPROTO_ICMP) {
        struct icmphdr* icmp =
            (struct icmphdr*)(packet + 14 + (iph->ip_hl * 4));

        pkg_type = "ICMP";
        method   = "ICMP";

        if (icmp->type == ICMP_ECHO) {
            tool   = "Ping Request";
            risk   = "Low";
            action = "Logged";
            printf("[?] ICMP PING  | From: %-15s → %s\n",
                   sip_str.c_str(), dip_str.c_str());
        } else if (icmp->type == ICMP_ECHOREPLY) {
            tool   = "Ping Reply";
            risk   = "Low";
            action = "Logged";
            printf("[?] ICMP REPLY | From: %-15s → %s\n",
                   sip_str.c_str(), dip_str.c_str());
        } else {
            tool   = "ICMP Other";
            risk   = "Medium";
            action = "Logged";
            printf("[?] ICMP TYPE:%d | From: %-15s → %s\n",
                   icmp->type, sip_str.c_str(), dip_str.c_str());
        }

        // STEP 1: Entry pehle
        ip_track[sip_str]++;
        log_to_db(port, pkg_type, header->len, tool,
                  sip_str, method, risk, action);

    // ════════════════════════════════════════════════════════
    // UDP LOGIC
    // ════════════════════════════════════════════════════════
    } else if (iph->ip_p == IPPROTO_UDP) {
        struct udphdr* udp =
            (struct udphdr*)(packet + 14 + (iph->ip_hl * 4));

        pkg_type = "UDP";
        port     = ntohs(udp->dest);
        method   = "UDP";

        // DNS traffic
        if (port == 53 || ntohs(udp->source) == 53) {
            tool   = "DNS Query";
            risk   = "Low";
            action = "Logged";
        } else {
            tool   = "UDP Traffic/Scan";
            risk   = "Medium";
            action = "Logged";
        }

        printf("[?] UDP  | Port: %-5d | From: %-15s → %s\n",
               port, sip_str.c_str(), dip_str.c_str());

        // STEP 1: Entry pehle
        ip_track[sip_str]++;
        log_to_db(port, pkg_type, header->len, tool,
                  sip_str, method, risk, action);

    // ════════════════════════════════════════════════════════
    // TCP LOGIC
    // ════════════════════════════════════════════════════════
    } else if (iph->ip_p == IPPROTO_TCP) {
        struct tcphdr* tcph =
            (struct tcphdr*)(packet + 14 + (iph->ip_hl * 4));

        pkg_type = "TCP_PACKET";
        port     = ntohs(tcph->dest);

        // MySQL traffic skip (3306)
        if (port == 3306) return;

        // Payload analysis
        int payload_offset = 14 + (iph->ip_hl * 4) + (tcph->doff * 4);
        int payload_len    = header->len - payload_offset;

        if (payload_len <= 0) {
            tool   = "Potential Port Scan";
            risk   = "Medium";
            action = "Logged";
        } else {
            const char* payload =
                (const char*)(packet + payload_offset);
            tool = analyze_payload(payload, payload_len);

            if (tool != "Clean") {
                risk   = "CRITICAL";
                action = "Logged";
            }

            // HTTP method detect
            string p_str(payload, (payload_len > 5) ? 5 : payload_len);
            if      (p_str.find("GET")  != string::npos) method = "GET";
            else if (p_str.find("POST") != string::npos) method = "POST";
            else                                          method = "TCP";
        }

        printf("[+] TCP  | Port: %-5d | Risk: %-8s | Tool: %-25s | From: %-15s → %s\n",
               port, risk.c_str(), tool.c_str(),
               sip_str.c_str(), dip_str.c_str());

        // STEP 1: Entry pehle
        ip_track[sip_str]++;
        log_to_db(port, pkg_type, header->len, tool,
                  sip_str, method, risk, action);
    }

    // ════════════════════════════════════════════════════════
    // BLOCK LOGIC — Entry ke BAAD
    // ════════════════════════════════════════════════════════
    bool should_block = false;
    string block_reason = "";

    // Reason 1: 25+ packets
    if (ip_track[sip_str] >= 25) {
        should_block  = true;
        block_reason  = "Packet flood (25+ packets)";
    }

    // Reason 2: CRITICAL attack signature
    if (tool == "Path Traversal Attack"      ||
        tool == "Command Injection Attempt"  ||
        tool == "SQL Injection Attempt"      ||
        tool == "XSS Attack"                 ||
        tool == "Port Scanner Detected"      ||
        tool == "Burp Suite Activity") {
        should_block = true;
        block_reason = tool;
    }

    // Reason 3: Already in blacklist DB
    if (!should_block && is_blacklisted(sip_str)) {
        should_block = true;
        block_reason = "In blacklist DB";
    }

    if (should_block && !is_blocked[sip_str]) {
        // Whitelist double check
        if (is_whitelisted(sip_str)) {
            printf("[⚪] WHITELIST PROTECTION | %s not blocked\n",
                   sip_str.c_str());
            fflush(stdout);
            return;
        }

        printf("\n[🔴 ALERT] Blocking %s | Reason: %s\n",
               sip_str.c_str(), block_reason.c_str());

        // STEP 2: Block karo
        string ipt_ip  = "sudo iptables -I INPUT 1 -s "
                         + sip_str + " -j DROP";
        string ipt_mac = "sudo iptables -I INPUT 1 -m mac --mac-source "
                         + smac_str + " -j DROP";
        system(ipt_ip.c_str());
        system(ipt_mac.c_str());
        is_blocked[sip_str] = true;

        // STEP 3: DB update — action Blocked
        string upd = "UPDATE attack SET action_taken='Blocked' "
                     "WHERE target_url='" + sip_str + "' "
                     "AND action_taken='Logged' "
                     "ORDER BY attack_id DESC LIMIT 5";
        mysql_query(conn, upd.c_str());

        // STEP 4: Blacklist mein add karo
        add_to_blacklist(sip_str, block_reason);

        printf("[✅ BLOCKED] %s banned | MAC: %s\n\n",
               sip_str.c_str(), smac_str.c_str());
    }

    fflush(stdout);
}

// ── MAIN ─────────────────────────────────────────────────────
int main() {
    // MySQL connect
    conn = mysql_init(NULL);
    if (!mysql_real_connect(conn, "127.0.0.1", "root", "kali",
                            "stealth_sentinel", 0, NULL, 0)) {
        printf("[✗] DB Connection Failed: %s\n", mysql_error(conn));
        return 1;
    }
    printf("[✓] MySQL Connected → stealth_sentinel\n");

    // Purani iptables rules clear karo
    system("sudo iptables -F");
    printf("[✓] iptables flushed\n");

    // Already blocked IPs memory mein load karo
    if (!mysql_query(conn,
        "SELECT ip FROM blacklisting WHERE ip IS NOT NULL AND ip != ''")) {
        MYSQL_RES* res = mysql_store_result(conn);
        MYSQL_ROW  row;
        int count = 0;
        while ((row = mysql_fetch_row(res))) {
            if (row[0]) {
                is_blocked[string(row[0])] = true;
                // iptables mein bhi apply karo
                string cmd = "sudo iptables -I INPUT 1 -s "
                             + string(row[0]) + " -j DROP 2>/dev/null";
                system(cmd.c_str());
                count++;
            }
        }
        mysql_free_result(res);
        printf("[✓] Loaded %d blocked IPs from blacklist\n", count);
    }

    // Pcap setup
    char errbuf[PCAP_ERRBUF_SIZE];
    pcap_t* handle = pcap_open_live("any", BUFSIZ, 1, 1, errbuf);
    if (!handle) {
        printf("[✗] pcap failed: %s\n", errbuf);
        return 1;
    }

    printf("\n════════════════════════════════════════════════\n");
    printf("  🛡️  STEALTH SENTINEL v26.0 — LOGIC ENGINE\n");
    printf("  Monitoring: ICMP + UDP + TCP (all ports)\n");
    printf("  Block after: 25 packets OR critical attack\n");
    printf("  DB: stealth_sentinel.attack + blacklisting\n");
    printf("════════════════════════════════════════════════\n\n");

    pcap_loop(handle, 0, packet_handler, NULL);
    return 0;
}
