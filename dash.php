<?php
$host = "127.0.0.1";
$user = "root";
$pass = "kali";
$db = "stealth_sentinel";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetching logs
$sql = "SELECT * FROM attack ORDER BY time_of_performing_attack DESC LIMIT 20";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stealth Sentinel | SOC Dashboard</title>
    <style>
        body { background-color: #0a0a0a; color: #00ff00; font-family: 'Courier New', monospace; padding: 20px; }
        h1 { text-align: center; border-bottom: 2px solid #00ff00; padding-bottom: 10px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: rgba(0, 255, 0, 0.05); }
        th, td { border: 1px solid #333; padding: 12px; text-align: left; }
        th { background-color: #111; color: #00ff00; text-transform: uppercase; }

        /* Row Coloring Logic */
        .risk-critical { background-color: rgba(255, 0, 0, 0.3) !important; color: #ff9999; font-weight: bold; } /* Malware */
        .risk-high { background-color: rgba(255, 165, 0, 0.2) !important; color: #ffcc66; } /* Phishing */
        .risk-low { color: #00ff00; } /* Normal/Ping */

        .status-blocked { color: #ff4444; text-decoration: underline; font-weight: bold; }
        .blink { animation: blinker 1.5s linear infinite; }
        @keyframes blinker { 50% { opacity: 0; } }
    </style>
    <meta http-equiv="refresh" content="3"> </head>
<body>

    <h1>[ STEALTH SENTINEL: LIVE THREAT INTELLIGENCE ]</h1>

    <table>
        <thead>
            <tr>
                <th>Time</th>
                <th>Target / IP</th>
                <th>Detection Logic (Module)</th>
                <th>Risk Level</th>
                <th>Method</th>
                <th>Action Taken</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $result->fetch_assoc()): 
                // Determine Row Style based on Risk/Tool
                $rowClass = "risk-low";
                if ($row['risk_level'] == 'CRITICAL') $rowClass = "risk-critical";
                if ($row['pkg_type'] == 'PHISHING' || $row['risk_level'] == 'HIGH') $rowClass = "risk-high";
                
                $actionClass = ($row['action_taken'] != 'Logged') ? "status-blocked" : "";
            ?>
            <tr class="<?php echo $rowClass; ?>">
                <td><?php echo $row['time_of_performing_attack']; ?></td>
                <td><?php echo $row['target_url']; ?></td>
                <td>
                    <?php 
                        echo "<b>" . $row['tool_used'] . "</b>"; 
                        if($row['risk_level'] == 'CRITICAL') echo " <span class='blink'>⚠️</span>";
                    ?>
                </td>
                <td><?php echo $row['risk_level']; ?></td>
                <td><?php echo $row['request_method']; ?></td>
                <td class="<?php echo $actionClass; ?>"><?php echo $row['action_taken']; ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

</body>
</html>
