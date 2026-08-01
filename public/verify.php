<?php
session_start();
$SESSIONS_DIR = __DIR__. "/sessions";
if(!is_dir($SESSIONS_DIR)) mkdir($SESSIONS_DIR, 0777, true);

$error = '';
$success = false;

// HANDLE FORM SUBMIT
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $ip_raw = trim($_POST['ip']);
    $pin = trim($_POST['pin']);

    $ip_clean = str_replace('_', '.', $ip_raw);
    $ip_safe = str_replace('.', '_', $ip_clean);
    $pin_safe = preg_replace('/[^0-9]/', '', $pin);

    if(empty($ip_safe) || empty($pin_safe) || strlen($pin_safe) != 6){
        $error = 'Invalid IP or PIN. PIN must be 6 digits';
    } else {
        $session_file = $SESSIONS_DIR. "/". $ip_safe. "_". $pin_safe. ".json";
        $session_folder = $SESSIONS_DIR. "/". $ip_safe. "_". $pin_safe;

        if(!file_exists($session_file)){
            $error = "Session not found. Check IP: $ip_clean and PIN: $pin_safe. Make sure sender is online.";
        } else {
            $session_data = json_decode(file_get_contents($session_file), true);

            if($session_data['expires'] < time()){
                if(is_dir($session_folder)) {
                    array_map('unlink', glob("$session_folder/*"));
                    rmdir($session_folder);
                }
                unlink($session_file);
                $error = 'Session expired. Ask sender to refresh upload.php for new PIN';
            } else {
                // Save to session
                $_SESSION['session_ip'] = $ip_safe;
                $_SESSION['session_pin'] = $pin_safe;
                $_SESSION['sender_name'] = $session_data['sender_name'];

                // Redirect
                header("Location: download.php");
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Join Session - UniFile</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
:root{ --bg: #0a0f1e; --bg2: #16213E; --glass: rgba(255, 255, 255, 0.05); --text: #e6eefc; --muted: #94a3b8; --accent: #01E5C0; --danger: #EF4444; }
*{ box-sizing:border-box; }
body{ margin:0; font-family:'Poppins', sans-serif; background:linear-gradient(135deg, var(--bg) 0%, var(--bg2) 100%); color:var(--text); display:flex; justify-content:center; align-items:center; min-height:100vh; padding:20px; }
.card{ width:100%; max-width:450px; padding:40px; border-radius:24px; background:var(--glass); backdrop-filter:blur(20px); border:1px solid rgba(255,255,255,0.1); }
h1{ text-align:center; font-size:28px; margin-bottom:10px; }
p{ text-align:center; color:var(--muted); margin-bottom:30px; }
.input-group{ margin-bottom:20px; }
label{ display:block; margin-bottom:8px; font-weight:600; font-size:14px; }
input{ width:100%; padding:16px; border-radius:14px; border:1px solid rgba(255,255,255,0.1); background:rgba(0,0,0,0.3); color:var(--text); font-size:16px; font-family:'Poppins'; }
input:focus{ outline:none; border-color:var(--accent); }
button{ width:100%; padding:16px; border-radius:14px; border:none; background:var(--accent); color:#000; font-size:18px; font-weight:700; cursor:pointer; }
button:hover{ opacity:0.9; }
.alert{ padding:14px; border-radius:12px; margin-bottom:20px; text-align:center; }
.alert.error{ background:rgba(239,68,68,0.2); color:var(--danger); }
</style>
</head>
<body>

<div class="card">
    <h1>🔗 Join Session</h1>
    <p>Enter the IP and 6-digit code shown on the sender's screen</p>

    <?php if($error): ?>
        <div class="alert error"><?=htmlspecialchars($error)?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <div class="input-group">
            <label>Sender IP Address</label>
            <input type="text" autocomplete="off" name="ip" placeholder="e.g. 192.168.1.100" required value="<?=htmlspecialchars($_POST['ip']??'')?>">
        </div>
        <div class="input-group">
            <label>6 Digit Session Code</label>
            <input type="text" name="pin" autocomplete="off" placeholder="123456" required maxlength="6">
        </div>
        <button type="submit">Join Session</button>
    </form>
</div>

</body>
</html>