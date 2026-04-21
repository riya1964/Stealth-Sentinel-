<?php
$conn = new mysqli("127.0.0.1", "root", "kali", "stealth_sentinel");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Stealth Sentinel Dashboard</title>
    <meta http-equiv="refresh" content="5">
    <style>
        body { background: #050505; color: #00ff41; font-family: 'Courier New', monospace; padding: 20px; }
        .box { border: 2px solid #00ff41; padding: 20px; background: #0a0a0a; box-shadow: 0 0 15px #004d1a; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #003300; border: 1px solid #00ff41; padding: 12px; }
        td { border: 1px solid #1a331a; padding: 10px; text-align: center; }
        /* Critical risk alerts */
        .critical { color: #ff0000; font-weight: bold; text-shadow: 0 0 5px #ff0000; }
        .high { color: #ffae00; font-weight: bold; }
    </style>
</head>
<body>
    <div class="box">
        <h1 style="text-align:center;">[ STEALTH SENTINEL: LIVE ATTACK LOGS ]</h1>
        <table>
            <thead>
                <tr>
                    <th>TIME</th>
                    <th>TARGET / IP</th>
                    <th>DETECTION LOGIC (TOOL)</th>
                    <th>PORT / TYPE</th>
                    <th>METHOD</th>
                    <th>RISK LEVEL</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Simple query to avoid JOIN issues
                $sql = "SELECT time_of_performing_attack, target_url, tool_used, port, request_method, risk_level 
                        FROM attack 
                        ORDER BY time_of_performing_attack DESC LIMIT 15";
                                     
                $res = $conn->query($sql);
                if ($res && $res->num_rows > 0) {
                    while($row = $res->fetch_assoc()) {
                        // Risk classes for better visibility
                        $riskClass = "";
                        if ($row['risk_level'] == 'CRITICAL') $riskClass = "class='critical'";
                        else if ($row['risk_level'] == 'High') $riskClass = "class='high'";

                        echo "<tr>
                                <td>{$row['time_of_performing_attack']}</td>
                                <td>{$row['target_url']}</td>
                                <td>{$row['tool_used']}</td>
                                <td>{$row['port']}</td>
                                <td>{$row['request_method']}</td>
                                <td $riskClass>{$row['risk_level']}</td>
                              </tr>";
                    }
                } else {
                    echo "<tr><td colspan='6'>Waiting for live traffic... (Check logic_engine)</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</body>
</html>
