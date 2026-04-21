Stealth Sentinel 

A High-Performance Native Framework for Network Threat Mitigation
Stealth Sentinel is an advanced Intrusion Prevention System (IPS) engineered to provide real-time Deep Packet Inspection (DPI) across multiple network layers. Built natively in C++ and Python, it moves beyond traditional firewalls by inspecting packet payloads for malicious strings like SQL Injection, XSS, and Reverse Shells.

📊 Real-World Performance Results

During live testing in a Kali Linux environment, the system demonstrated commercial-grade efficacy:
Total Threats Detected: 16,545+ events captured and logged to a central MySQL database.
Attackers Blocked: 702 unique attackers successfully identified and mitigated via iptables and MAC-level filtering.
Phishing Accuracy: Successfully scored and redirected malicious domains with 100/100 accuracy (e.g., brand-spoofing detection).
Latency: Achieved sub-millisecond detection for signature-matched threats.

🚀 Key Modules
Web IPS Engine (C++): Acts as a Web Application Firewall (WAF) to inspect TCP/UDP/ICMP traffic for malicious payloads.
Anti-Phishing Shield (Python): Utilizes a dual-layer lexical and infrastructure analysis model (Shannon Entropy) to block brand-spoofing and domain anomalies in real-time.
Anti-Malware Engine (C++): Monitors outbound traffic for behavioral patterns associated with Command & Control (C2) communications and reverse shells.
SOC Command Center (PHP/MySQL): A unified dashboard for forensic visualization and real-time security auditing.

🛠️ Tech Stack
Languages: C++ (System-level), Python 3.13 (Scapy), Bash Scripting.
Database: MySQL 8.0 for persistent threat logging.
Network Hooks: libpcap for raw packet capture and iptables for kernel-level packet filtering.

📂 Project Structure
logic_engine.cpp: Core Web IPS and payload normalization.
phishing_engine.py: DNS sniffing and entropy-based URL scoring.
malware_detector.cpp: Signature-based detection for malicious binaries and C2 links.
sentinel_core.sh: The unified orchestrator that initializes the database and launches all engines.

🛡️ Academic Context
This project was developed as part of the B.Tech curriculum at Unitedworld Institute of Technology (UIT), Karnavati University. It emphasizes the shift from passive detection to active prevention in modern cyber warfare.
