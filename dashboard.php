<?php
// Database Connection
$conn = new mysqli("127.0.0.1", "root", "kali", "stealth_sentinel");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1. Data Fetching for Analytics
$blocked_res = $conn->query("SELECT count(*) as total FROM attack WHERE action_taken LIKE '%Block%'");
$blocked_count = $blocked_res->fetch_assoc()['total'];

$logged_res = $conn->query("SELECT count(*) as total FROM attack WHERE action_taken = 'Logged'");
$logged_count = $logged_res->fetch_assoc()['total'];

$total_attacks = $blocked_count + $logged_count;

// 2. Security Score Calculation (%)
if ($total_attacks > 0) {
    $security_score = round(($blocked_count / $total_attacks) * 100, 1);
} else {
    $security_score = 100; // Default when no attacks detected
}

// 3. Dynamic Color for Security Score
$scoreColor = "#00ff41"; // Green
if ($security_score < 60) $scoreColor = "#ff4444"; // Red
else if ($security_score < 90) $scoreColor = "#ffae00"; // Orange
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stealth Sentinel | SOC Dashboard</title>
    <meta http-equiv="refresh" content="5">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* CSS for Fixed Screen & High Visibility */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            background: #050505; color: #00ff41; 
            font-family: 'Courier New', monospace; 
            height: 100vh; overflow: hidden; /* No Scroll */
            padding: 15px;
        }

        .dashboard-wrapper { display: flex; flex-direction: column; height: 100%; gap: 15px; }

        .header { text-align: center; border-bottom: 2px solid #00ff41; padding-bottom: 10px; }
        .blink { animation: blinker 1.5s linear infinite; font-size: 0.8em; }
        @keyframes blinker { 50% { opacity: 0.3; } }

        /* Stats Row */
        .stats-row { display: flex; gap: 15px; height: 18%; }
        .stat-box { 
            flex: 1; border: 1px solid #00ff41; background: #0a0a0a;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            box-shadow: 0 0 10px #004d1a;
        }
        .stat-num { font-size: 2.2em; font-weight: bold; }

        /* Main Content Grid */
        .main-content { display: flex; gap: 15px; height: 70%; }
        
        .table-container { 
            flex: 2; border: 1px solid #00ff41; background: #0a0a0a; 
            padding: 15px; display: flex; flex-direction: column;
        }
        
        .chart-container { 
            flex: 1; border: 1px solid #00ff41; background: #0a0a0a; 
            padding: 15px; display: flex; flex-direction: column; align-items: center;
        }

        /* Table Styling */
        table { width: 100%; border-collapse: collapse; font-size: 0.9em; }
        th { background: #003300; border: 1px solid #00ff41; padding: 10px; text-align: left; }
        td { border: 1px solid #1a331a; padding: 8px; }

        /* Logic to highlight High Priority */
        .critical-row { color: #ff0000; font-weight: bold; background: rgba(255,0,0,0.15); border-left: 5px solid red; }
        .high-row { color: #ffae00; border-left: 5px solid #ffae00; }
    </style>
</head>
<body>

<div class="dashboard-wrapper">
    <div class="header">
        <h1>[ STEALTH SENTINEL : COMMAND CENTER ]</h1>
        <p class="blink">● LIVE PROTECTION ENGINE v24.0 ACTIVE</p>
    </div>

    <div class="stats-row">
        <div class="stat-box">
            <span class="stat-num"><?php echo $total_attacks; ?></span>
            <small>TOTAL THREATS DETECTED</small>
        </div>
        <div class="stat-box" style="color: <?php echo $scoreColor; ?>; border-color: <?php echo $scoreColor; ?>;">
            <span class="stat-num"><?php echo $security_score; ?>%</span>
            <small>SECURITY SCORE</small>
        </div>
        <div class="stat-box" style="color: #ff4444; border-color: #ff4444;">
            <span class="stat-num"><?php echo $blocked_count; ?></span>
            <small>ATTACKERS BLOCKED</small>
        </div>
    </div>

    <div class="main-content">
        <div class="table-container">
            <h3 style="margin-bottom:10px; color:#ff4444;">⚠️ CRITICAL SECURITY ALERTS</h3>
            <table>
                <thead>
                    <tr>
                        <th>SOURCE IP</th>
                        <th>THREAT / TOOL</th>
                        <th>RISK</th>
                        <th>DEFENSE ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // SMART QUERY: Pehle Critical/High Attacks, phir Time
                    $sql = "SELECT target_url, tool_used, risk_level, action_taken 
                            FROM attack 
                            ORDER BY 
                                CASE 
                                    WHEN risk_level = 'CRITICAL' THEN 1 
                                    WHEN risk_level = 'High' THEN 2 
                                    ELSE 3 
                                END ASC, 
                                time_of_performing_attack DESC 
                            LIMIT 10"; // Fits perfectly in screen
                    
                    $res = $conn->query($sql);
                    if ($res && $res->num_rows > 0) {
                        while($row = $res->fetch_assoc()) {
                            $rowClass = "";
                            if($row['risk_level'] == 'CRITICAL') $rowClass = "class='critical-row'";
                            else if($row['risk_level'] == 'High') $rowClass = "class='high-row'";
                            
                            echo "<tr $rowClass>
                                    <td>{$row['target_url']}</td>
                                    <td>{$row['tool_used']}</td>
                                    <td>{$row['risk_level']}</td>
                                    <td>{$row['action_taken']}</td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4' style='text-align:center;'>Monitoring clear traffic...</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="chart-container">
            <h3>THREAT DISTRIBUTION</h3>
            <div style="width: 100%; height: 250px; margin-top: 15px;">
                <canvas id="securityChart"></canvas>
            </div>
            <p style="font-size: 0.7em; margin-top: 10px; color: #888;">Live ratio of Mitigation vs. Observation</p>
        </div>
    </div>
</div>

<script>
    const ctx = document.getElementById('securityChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Blocked/Mitigated', 'Logged/Safe'],
            datasets: [{
                data: [<?php echo $blocked_count; ?>, <?php echo $logged_count; ?>],
                backgroundColor: ['#ff4444', '#00ff41'],
                borderColor: '#050505',
                borderWidth: 2
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { color: '#00ff41', font: { family: 'Courier New' } } }
            }
        }
    });
</script>

</body>
</html>              
