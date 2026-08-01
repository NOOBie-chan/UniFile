<?php
session_start();
$SETTINGS_FILE = __DIR__ . "/settings.json";
$LOCK_FILE = __DIR__ . "/lock.json";
$CLIENT_ID = $_SESSION['client_id'] ?? $_COOKIE['client_id'] ?? bin2hex(random_bytes(8));
setcookie('client_id', $CLIENT_ID, time() + 86400 * 30, "/");

function getRealIP(){
    $ip = $_SERVER['SERVER_ADDR'] ?? $_SERVER['REMOTE_ADDR'];
    if($ip == '::1' || $ip == '127.0.0.1') $ip = gethostbyname(gethostname());
    return $ip;
}

// Ensure files exist
if(!file_exists($SETTINGS_FILE)) file_put_contents($SETTINGS_FILE, '{}');
if(!file_exists($LOCK_FILE)) file_put_contents($LOCK_FILE, '{"enabled":false,"pin":""}');

$settings = json_decode(file_get_contents($SETTINGS_FILE), true);
$lock = json_decode(file_get_contents($LOCK_FILE), true);

$device_name = gethostname() ?: "Device-" . substr($CLIENT_ID,0,4);
$default_username = "User-" . substr($CLIENT_ID,0,4);

if(!isset($settings[$CLIENT_ID])){
    $settings[$CLIENT_ID] = [
        'username' => $default_username,
        'device' => $device_name,
        'ip' => getRealIP(),
        'theme' => $_SESSION['theme'] ?? 'dark',
        'last_seen' => time()
    ];
}

// Handle POST
$message = ""; $error = "";
if(isset($_POST['save_profile'])){
    $settings[$CLIENT_ID]['username'] = trim($_POST['username']) ?: $default_username;
    $settings[$CLIENT_ID]['ip'] = getRealIP();
    $settings[$CLIENT_ID]['last_seen'] = time();
    file_put_contents($SETTINGS_FILE, json_encode($settings, JSON_PRETTY_PRINT));
    $message = "Profile updated successfully";
}

if(isset($_POST['save_prefs'])){
    $settings[$CLIENT_ID]['theme'] = $_POST['theme'];
    $_SESSION['theme'] = $_POST['theme'];
    file_put_contents($SETTINGS_FILE, json_encode($settings, JSON_PRETTY_PRINT));
    $message = "Preferences saved";
}

// App Lock Logic
if(isset($_POST['set_pin'])){
    $pin1 = $_POST['pin1']; $pin2 = $_POST['pin2'];
    if(strlen($pin1) == 4 && $pin1 === $pin2 && ctype_digit($pin1)){
        $lock['enabled'] = true;
        $lock['pin'] = password_hash($pin1, PASSWORD_DEFAULT);
        file_put_contents($LOCK_FILE, json_encode($lock));
        $message = "App Lock enabled. You will be asked for PIN on next visit.";
    } else {
        $error = "PINs do not match or not 4 digits";
    }
}
if(isset($_POST['disable_pin'])){
    $pin = $_POST['current_pin'];
    if(password_verify($pin, $lock['pin'])){
        $lock['enabled'] = false;
        $lock['pin'] = "";
        file_put_contents($LOCK_FILE, json_encode($lock));
        $message = "App Lock disabled";
    } else {
        $error = "Incorrect PIN";
    }
}

$my = $settings[$CLIENT_ID];
$theme = $my['theme'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UniFile Settings</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
:root{
    /* DARK THEME */
    --bg-dark: #0a0f1e;
    --bg-gradient-dark: linear-gradient(135deg, #0a0f1e 0%, #1a2a4a 50%, #0d1b2a 100%);
    --glass-dark: rgba(255, 255, 255, 0.06);
    --border-dark: rgba(255, 255, 255, 0.12);
    --text-dark: #e6eefc;
    --text-muted-dark: #94a3b8;
    --accent-dark: #01E5C0;
    --accent2-dark: #00ff88;
    --shadow-dark: rgba(1, 229, 192, 0.15);
    /* LIGHT THEME */
    --bg-light: #f0f4f9;
    --bg-gradient-light: linear-gradient(135deg, #e0e7ff 0%, #f0f4f9 50%, #dbeafe 100%);
    --glass-light: rgba(255, 255, 255, 0.55);
    --border-light: rgba(0, 0, 0, 0.08);
    --text-light: #1e293b;
    --text-muted-light: #64748b;
    --accent-light: #01E5C0;
    --accent2-light: #059669;
    --shadow-light: rgba(1, 229, 192, 0.1);
}
*{ box-sizing: border-box; }
body{
    margin: 0;
    font-family: 'Poppins', sans-serif;
    min-height: 100vh;
    transition: background 0.4s ease, color 0.4s ease;
    padding: 0;
    display: flex;
    flex-direction: column;
}
body.dark{ background: var(--bg-gradient-dark); color: var(--text-dark); }
body.light{ background: var(--bg-gradient-light); color: var(--text-light); }
.bg-blob{ position: fixed; border-radius: 50%; filter: blur(120px); z-index: -1; opacity: 0.3; animation: float 20s infinite ease-in-out; }
.blob1{ width: 400px; height: 400px; background: #01E5C0; top: -100px; left: -100px; }
.blob2{ width: 350px; height: 350px; background: #00ff88; bottom: -100px; right: -100px; animation-delay: -5s; }
@keyframes float{ 0%,100%{ transform: translateY(0) scale(1); } 50%{ transform: translateY(-30px) scale(1.1); } }
.container{ max-width: 900px; margin: 0 auto; padding: 40px 5%; flex: 1; width: 100%; }
.header{ display:flex; justify-content:space-between; align-items:center; margin-bottom:40px; flex-wrap: wrap; gap: 15px; }
.header h1{ font-size:28px; font-weight:700; margin: 0; display: flex; align-items: center; gap: 10px; }
.back-btn{ padding: 10px 18px; border-radius: 14px; border: 1px solid; background: transparent; color: inherit; cursor: pointer; font-weight: 600; text-decoration:none; transition: all 0.3s ease; backdrop-filter: blur(10px); }
body.dark .back-btn{ border-color: var(--border-dark); }
body.light .back-btn{ border-color: var(--border-light); }
.back-btn:hover{ transform: translateY(-2px); box-shadow: 0 6px 16px var(--shadow-dark); }
.card{ padding: 30px; border-radius: 24px; backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid; margin-bottom:30px; }
body.dark .card{ background: var(--glass-dark); border-color: var(--border-dark); box-shadow: 8px 8px 20px rgba(0,0,0,0.4), -8px -8px 20px rgba(255,255,255,0.05); }
body.light .card{ background: var(--glass-light); border-color: var(--border-light); box-shadow: 8px 8px 20px rgba(0,0,0,0.08), -8px -8px 20px rgba(255,255,255,0.8); }
.card h2{ font-size:20px; margin-bottom:20px; color: var(--accent-dark); display: flex; align-items: center; gap: 8px; }
body.light .card h2{ color: var(--accent-light); }
.form-group{ margin-bottom:18px; }
label{ display:block; margin-bottom:8px; font-size:14px; font-weight:600; }
body.dark label{ color: var(--text-muted-dark); }
body.light label{ color: var(--text-muted-light); }
input[type="text"], input[type="password"], select{
    width:100%; padding:14px; border-radius:14px; border:1px solid; background:transparent; color:inherit; font-size:15px; font-family:'Poppins';
    backdrop-filter: blur(10px); transition: all 0.3s ease;
}
input[type="password"]{ letter-spacing: 8px; text-align: center; }
input:focus, select:focus{ outline: none; border-color: var(--accent-dark); box-shadow: 0 0 0 3px rgba(1,229,192,0.2); }
body.dark input, body.dark select{ border-color: var(--border-dark); }
body.light input, body.light select{ border-color: var(--border-light); }
.btn{ background:var(--accent-dark); color:#000; border:none; padding:14px 20px; border-radius:14px; cursor:pointer; font-weight:700; width:100%; font-size:16px; transition:0.3s; }
.btn:hover{ transform:translateY(-2px); box-shadow:0 8px 20px var(--shadow-dark); }
.btn-red{ background:#EF4444; color:#fff; }
.btn-red:hover{ box-shadow:0 8px 20px rgba(239,68,68,0.3); }
.alert{ padding:14px; border-radius:14px; margin-bottom:25px; text-align:center; font-weight:600; backdrop-filter: blur(10px); animation: slideDown 0.3s ease; }
@keyframes slideDown{ from{ opacity:0; transform: translateY(-10px);} to{ opacity:1; transform: translateY(0);} }
.alert.success{ background:rgba(1,229,192,0.2); color:var(--accent-dark); border:1px solid var(--accent-dark); }
.alert.error{ background:rgba(239,68,68,0.2); color:#EF4444; border:1px solid #EF4444; }

/* FIX 1: PIN Grid - no more pinched middle */
.pin-grid{ display:grid; grid-template-columns:1fr 1fr; gap:15px; }
@media(max-width:600px){ .pin-grid{ grid-template-columns:1fr; } }

.info-text{ font-size:13px; margin-top:6px; line-height: 1.5; }
body.dark .info-text{ color: var(--text-muted-dark); }
body.light .info-text{ color: var(--text-muted-light); }

/* Footer */
.footer{
    text-align: center;
    padding: 25px 20px;
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    border-top: 1px solid;
    margin-top: auto;
}
body.dark .footer{ border-color: var(--border-dark); color: var(--text-muted-dark); }
body.light .footer{ border-color: var(--border-light); color: var(--text-muted-light); }
.footer-logo{
    width: 24px;
    height: 24px;
    border-radius: 6px;
    object-fit: cover;
}
</style>
</head>
<body class="<?php echo $theme; ?>">
<div class="bg-blob blob1"></div>
<div class="bg-blob blob2"></div>
<div class="container">
    <div class="header">
        <h1>⚙️ Settings</h1>
        <a href="index.php" class="back-btn">← Back to Home</a>
    </div>
    <?php if($message): ?><div class="alert success"><?=$message?></div><?php endif; ?>
    <?php if($error): ?><div class="alert error"><?=$error?></div><?php endif; ?>

    <div class="card">
        <h2>👤 Profile</h2>
        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" value="<?=htmlspecialchars($my['username'])?>" placeholder="Enter username">
            </div>
            <div class="form-group">
                <label>Device Name</label>
                <input type="text" value="<?=htmlspecialchars($my['device'])?>" disabled>
            </div>
            <div class="form-group">
                <label>Your LAN IP</label>
                <input type="text" value="<?=htmlspecialchars($my['ip'])?>" disabled>
            </div>
            <button class="btn" name="save_profile">Save Profile</button>
        </form>
    </div>

    <div class="card">
        <h2>🎨 App Preferences</h2>
        <form method="POST">
            <div class="form-group">
                <label>Theme</label>
                <select name="theme">
                    <option value="dark" <?=$my['theme']=='dark'?'selected':''?>>🌙 Dark</option>
                    <option value="light" <?=$my['theme']=='light'?'selected':''?>>☀️ Light</option>
                </select>
            </div>
            <button class="btn" name="save_prefs" style="margin-top:10px">Save Preferences</button>
        </form>
    </div>

    <div class="card">
        <h2>🔒 App Lock</h2>
        <?php if(!$lock['enabled']): ?>
        <form method="POST">
            <div class="info-text" style="margin-bottom:15px">Protect the app with a 4-digit PIN. You’ll be asked for it every time you open the app.</div>
            <div class="pin-grid">
                <div class="form-group">
                    <label>Enter 4 Digit PIN</label>
                    <input type="password" name="pin1" maxlength="4" pattern="\d{4}" required inputmode="numeric" placeholder="****">
                </div>
                <div class="form-group">
                    <label>Confirm PIN</label>
                    <input type="password" name="pin2" maxlength="4" pattern="\d{4}" required inputmode="numeric" placeholder="****">
                </div>
            </div>
            <button class="btn" name="set_pin">Enable App Lock</button>
        </form>
        <?php else: ?>
        <div class="info-text" style="margin-bottom:15px">App Lock is currently <b style="color:var(--accent-dark)">ON</b>. Enter current PIN to disable.</div>
        <form method="POST">
            <div class="form-group">
                <label>Current PIN</label>
                <input type="password" name="current_pin" maxlength="4" pattern="\d{4}" required inputmode="numeric" placeholder="****">
            </div>
            <button class="btn btn-red" name="disable_pin">Disable App Lock</button>
        </form>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>ℹ️ About</h2>
        <p class="info-text"><b>UniFile v1.0</b></p>
        <p class="info-text">Secure P2P file sharing on your local network. Files never leave your LAN.</p>
    </div>
</div>

<footer class="footer">
    <img src="icon.png" alt="Phosory Logo" class="footer-logo"/>
    Made by Phosory
</footer>

</body>
</html>