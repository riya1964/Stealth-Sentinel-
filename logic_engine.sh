#include <iostream>
#include <string>
#include <memory>
#include <mysql_driver.h>
#include <mysql_connection.h>
#include <cppconn/prepared_statement.h>
#include <pcap.h>
#include <netinet/ip.h>
#include <netinet/tcp.h>
#include <netinet/ether.h>
#include <arpa/inet.h>

using namespace std;

const string db_host = "tcp://127.0.0.1:3306";
const string db_user = "root";
const string db_pass = "kali"; 
const string db_name = "stealth_sentinel";

void log_full_capture(string s_ip, string s_mac, int port, int p_size, string method, string service, string target) {
    try {
        sql::mysql::MySQL_Driver *driver = sql::mysql::get_mysql_driver_instance();
        unique_ptr<sql::Connection> con(driver->connect(db_host, db_user, db_pass));
        con->setSchema(db_name);

        // Fill Attack Table
        unique_ptr<sql::PreparedStatement> pstmt(con->prepareStatement(
            "INSERT INTO attack(pkg_type, pkg_size, port, tool_used, scan_type, request_method, risk_level, action_taken, target_url, time_of_performing_attack) "
            "VALUES ('TCP', ?, ?, 'Nmap Scanner', 'Deep Capture', ?, 'High', 'Blocked', ?, NOW())"));
        pstmt->setInt(1, p_size);
        pstmt->setInt(2, port);
        pstmt->setString(3, method);
        pstmt->setString(4, target);
        pstmt->executeUpdate();

        // Fill Attacker Table
        unique_ptr<sql::PreparedStatement> astmt(con->prepareStatement(
            "INSERT INTO attacker(ip, mac, os_version, service_version, device_type, location) VALUES (?, ?, 'Kali-2026', ?, 'PC', 'Gandhinagar, India') "
            "ON DUPLICATE KEY UPDATE mac=VALUES(mac), service_version=VALUES(service_version), location=VALUES(location)"));
        astmt->setString(1, s_ip);
        astmt->setString(2, s_mac);
        astmt->setString(3, service);
        astmt->executeUpdate();

        cout << "[FIRE] Data Pushed to DB for IP: " << s_ip << endl;
    } catch (sql::SQLException &e) { cerr << "DB Error: " << e.what() << endl; }
}

void process_packet(u_char *args, const struct pcap_pkthdr *header, const u_char *packet) {
    int link_type = *(int *)args;
    int offset = (link_type == 113) ? 16 : 14; 

    struct ip *ip_header = (struct ip *)(packet + offset);
    if (ip_header->ip_p == IPPROTO_TCP) {
        string src_ip = inet_ntoa(ip_header->ip_src);
        
        // MAC Extraction
        char mac_str[18] = "08:00:27:61:94:4c"; 
        if (offset == 14) {
            struct ether_header *eth = (struct ether_header *)packet;
            snprintf(mac_str, sizeof(mac_str), "%02x:%02x:%02x:%02x:%02x:%02x",
                     eth->ether_shost[0], eth->ether_shost[1], eth->ether_shost[2],
                     eth->ether_shost[3], eth->ether_shost[4], eth->ether_shost[5]);
        }

        int ip_hl = ip_header->ip_hl << 2;
        struct tcphdr *tcp_header = (struct tcphdr *)(packet + offset + ip_hl);
        int port = ntohs(tcp_header->th_dport);
        int pkg_size = header->len; 

        // Payload Analysis (Method & Target URL)
        int tcp_hl = tcp_header->th_off << 2;
        const char *payload = (const char *)(packet + offset + ip_hl + tcp_hl);
        int payload_len = header->caplen - (offset + ip_hl + tcp_hl);
        string data = (payload_len > 0) ? string(payload, (payload_len > 100 ? 100 : payload_len)) : "";

        string method = "TCP_SYN";
        string target = "http://10.0.2.15/";
        if (data.find("GET") != string::npos) method = "GET";
        if (data.find("Host: ") != string::npos) {
            size_t host_pos = data.find("Host: ");
            target = "http://" + data.substr(host_pos + 6, data.find("\r\n", host_pos) - (host_pos + 6));
        }

        // Service Version Mapping
        string service = "Unknown";
        if (port == 80) service = "Apache/2.4.58 (Unix)";
        else if (port == 22) service = "OpenSSH/9.2p1";
        else if (port == 3306) service = "MariaDB/10.11.6";

        log_full_capture(src_ip, mac_str, port, pkg_size, method, service, target);
    }
}

int main() {
    char errbuf[PCAP_ERRBUF_SIZE];
    pcap_t *handle = pcap_open_live("any", BUFSIZ, 1, 10, errbuf); 
    int link_type = pcap_datalink(handle);
    cout << "[SYSTEM] Sentinel Bramhastra ACTIVE..." << endl;
    pcap_loop(handle, 0, process_packet, (u_char *)&link_type);
    return 0;
}
