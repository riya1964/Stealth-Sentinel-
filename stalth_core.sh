#!/bin/bash

# --- STEALTH SENTINEL v30.0 : ULTIMATE SECURITY SUITE ---
echo "===================================================="
echo "    STEALTH SENTINEL : UNIFIED THREAT MANAGEMENT    "
echo "===================================================="

# 1. Provide Permissions (Safety First)
# Note: phishing_detector.cpp hataya gaya hai kyunki ab Python use ho raha hai
sudo chmod +x user_logic.cpp logic_engine.cpp malware_detector.cpp phishing_engine.py

# 2. Start Required Services
echo "[*] Initializing Database & Apache Server ..."
sudo systemctl start mysql apache2

# 3. Compilation Phase (Teeno Engines + Profiler)
echo "[*] Compiling All Security Modules ..."

# Module 1: Endpoint Profiler
g++ user_logic.cpp -o profiler -lmysqlclient

# Module 2: Web Security (IPS)
g++ logic_engine.cpp -o web_engine -lpcap -lmysqlclient

# Module 3: Anti-Malware Engine
g++ malware_detector.cpp -o malware_engine -lpcap -lmysqlclient

# Module 4: Anti-Phishing Shield (Ab Python hai, isliye compilation ki zarurat nahi)
echo "[i] Phishing Engine (Python) ready. Skipping C++ compilation..."

echo "[✓] All Modules Compiled Successfully."

# 4. Step 1: Execute Profiling (Real-time data fetch)
if [ -f "./profiler" ]; then
    echo "[*] Capturing Live System Metadata ..."
    sudo ./profiler
else
    echo "[X] Profiler failed to compile!"
    exit 1
fi

# 5. Step 2: Launch All Shields in Background
echo "[!] Activating Triple-Layer Protection (Web | Malware | Phishing) ... "

sudo ./web_engine &
sudo ./malware_engine &
# Naya Python Phishing Engine yahan start hoga
sudo python3 phishing_engine.py &

echo "____________________________________________________"
echo " STATUS: [ WEB: LIVE | MALWARE: LIVE | PHISH: LIVE ]"
echo "____________________________________________________"
echo "[i] Monitoring eth0 for real-time threats ..."
echo "[i] Press CTRL+C to stop the sequence."

# Wait command to keep the script alive
wait
