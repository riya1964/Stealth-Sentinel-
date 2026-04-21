<?php 
// Database Connection (Aapki config.php se integrated)
include 'config.php'; 

// Database settings (Agar config.php mein nahi hain toh yahan update karein)
$host = '127.0.0.1';
$db   = 'stealth_sentinel';
$user = 'root';
$pass = 'kali'; // Aapka password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
try {
     $pdo = new PDO($dsn, $user, $pass);
} catch (\PDOException $e) {
     die("Connection failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Stealth Sentinel Dashboard</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0d0d0d; color: #00ff41; margin: 0; padding: 20px; }
        h1 { text-align: center; color: #00ff41; text-shadow: 0 0 10px #00ff41; border-bottom: 2px solid #00ff41; padding-bottom: 10px; }
        .grid-container { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .card { background: #1a1a1a; padding: 20px; border: 1px solid #333; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.5); }
        .full-card { background: #1a1a1a; padding: 20px; margin-top: 20px; border: 1px solid #333; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 0.9em; }
        th { background: #333; color: #00ff41; padding: 12px; border-bottom: 2px solid #00ff41; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #222; color: #ccc; }
        .high-risk { color: #ff4d4d; font-weight: bold; text-shadow: 0 0 5px #ff4d4d; }
        .status-badge { padding: 5px 10px; border-radius: 4px; font-weight: bold; font-size: 0.8em; }
        .active { background: #004d1a; color: #00ff41; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; transition: 0.3s; }
        .btn-start { background: #00ff41; color: black; }
        .btn-stop { background: #ff4d4d; color: white; margin-left: 10px; }
        .btn:hover { opacity: 0.8; transform: scale(1.05); }
    </style>
</head>
<body>
    <h1>STEALTH SENTINEL: CONTROL CENTER</h1>

    <div class="grid-container">
        <div class="card">
            <h2>🛡️ Security Status</h2>
            <?php
            $u_stmt = $pdo->query("SELECT * FROM user WHERE user_id = 1");
            $user_info = $u_stmt->fetch();
            ?>
            <p><strong>Device:</strong> <?php echo $user_info['name'] ?? 'Riya_Sentinel'; ?></p>
            <p><strong>IP Address:</strong> <?php echo $user_info['ip'] ?? '10.0.2.15'; ?></p>
            <p><strong>Proxy Status:</strong> <span class="status-badge active"><?php echo $user_info['proxy_status'] ?? 'MONITORED'; ?></span></p>
            <p><strong>Anti-Malware:</strong> <?php echo $user_info['anti_malware_status'] ?? 'Active'; ?></p>
        </div>

        <div class="card">
            <h2>⚙️ System Control</h2>
            <form action="control.php" method="POST">
                <p>System Engine Status: <strong>Running</strong></p>
                <button name="action" value="start" class="btn btn-start">START CORE ENGINE</button>
                <button name="action" value="stop" class="btn btn-stop">KILL CORE ENGINE</button>
            </form>
        </div>
    </div>

    <div class="full-card">
        <h2>🚨 Live Threat Monitoring</h2>
        <table>
            <tr>
                <th>Time</th>
                <th>Attacker IP / MAC</th>
                <th>Scan Type</th>
                <th>Method / Tool</th>
                <th>Size (B)</th>
                <th>Risk</th>
                <th>Action</th>
            </tr>
            <?php
            // JOIN query to get data from both attack and attacker tables
            $query = "SELECT a.*, att.mac, att.service_version 
                      FROM attack a 
                      LEFT JOIN attacker att ON a.port = a.port -- Logic for demo join
                      ORDER BY a.time_of_performing_attack DESC LIMIT 15";
            
            $stmt = $pdo->query($query);
            while ($row = $stmt->fetch()) {
                $risk_class = ($row['risk_level'] == 'High') ? 'high-risk' : '';
                echo "<tr>
                        <td>{$row['time_of_performing_attack']}</td>
                        <td><strong>IP:</strong> {$row['ip']}<br><small>MAC: {$row['mac']}</small></td>
                        <td>{$row['scan_type']}<br><small>Target: {$row['target_url']}</small></td>
                        <td>{$row['request_method']}<br><small>via {$row['tool_used']}</small></td>
                        <td>{$row['pkg_size']}</td>
                        <td class='$risk_class'>{$row['risk_level']}</td>
                        <td><span style='color:#00ff41'>[{$row['action_taken']}]</span></td>
                      </tr>";
            }
            ?>
        </table>
    </div>
</body>
</html>
