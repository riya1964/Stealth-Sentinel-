#include <iostream>
#include <mysql/mysql.h>
#include <string>
#include <cstdlib>
#include <cstdio>
#include <algorithm>

using namespace std;

// Function to fetch real output from Linux Terminal
string fetch_info(const char* cmd) {
    char buffer[256];
    string result = "";
    FILE* pipe = popen(cmd, "r");
    if (!pipe) return "N/A";
    while (fgets(buffer, sizeof(buffer), pipe) != NULL) {
        result += buffer;
    }
    pclose(pipe);
    // Remove newlines
    result.erase(remove(result.begin(), result.end(), '\n'), result.end());
    return result.empty() ? "Unknown" : result;
}

int main() {
    MYSQL* conn = mysql_init(NULL);
    if (!mysql_real_connect(conn, "127.0.0.1", "root", "kali", "stealth_sentinel", 0, NULL, 0)) {
        cout << "Database Connection Failed!" << endl;
        return 1;
    }

    cout << "\n[*] EXFILTRATING REAL-TIME ENDPOINT DATA..." << endl;

    // --- REAL SYSTEM COMMANDS ---
    
    // 1. Get Real Local IP
    string ip = fetch_info("hostname -I | awk '{print $1}'");

    // 2. Get Real MAC Address
    string mac = fetch_info("cat /sys/class/net/$(ip route show default | awk '/default/ {print $5}')/address");

    // 3. Get Current Logged-in Username
    string name = fetch_info("whoami");

    // 4. Get Exact OS Version (e.g., Kali GNU/Linux 2025.x)
    string os_version = fetch_info("grep 'PRETTY_NAME' /etc/os-release | cut -d'\"' -f2");

    // 5. Get Apache/Service Version
    string service_version = fetch_info("apache2 -v | head -n 1 | cut -d'/' -f2 | awk '{print $1}'");

    // 6. Check if Task Manager (htop/top) is running
    string task_manager = fetch_info("pgrep -x 'htop' > /dev/null && echo 'Active' || echo 'Inactive'");

    // 7. Proxy Status (Check if environment variable is set)
    string proxy_status = fetch_info("[ -z \"$http_proxy\" ] && echo 'Direct (No Proxy)' || echo 'Proxy Active'");

    // 8. Device Type (VirtualBox detection)
    string device_type = fetch_info("sudo dmidecode -s system-product-name | grep -i 'VirtualBox' > /dev/null && echo 'Virtual Machine' || echo 'Physical Hardware'");

    // SQL Insert Query
    string sql = "INSERT INTO user (ip, mac, name, os_version, service_version, task_manager_status, device_type, proxy_status) VALUES ('"
                 + ip + "', '" + mac + "', '" + name + "', '" + os_version + "', '" + service_version + "', '" + task_manager + "', '" + device_type + "', '" + proxy_status + "')";

    if (mysql_query(conn, sql.c_str())) {
        fprintf(stderr, "[❌] DB Error: %s\n", mysql_error(conn));
    } else {
        cout << "[✅] REAL DATA LOGGED: " << name << " | " << os_version << " | " << ip << endl;
    }

    mysql_close(conn);
    return 0;
}
