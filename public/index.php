<?php
session_start();
// 1. LOAD LOCK + SETTINGS FILES
$LOCK_FILE = __DIR__ . "/lock.json";
$SETTINGS_FILE = __DIR__ . "/settings.json";
if(!file_exists($LOCK_FILE)) file_put_contents($LOCK_FILE, '{"enabled":false,"pin":""}');
if(!file_exists($SETTINGS_FILE)) file_put_contents($SETTINGS_FILE, '{}');
$lock = json_decode(file_get_contents($LOCK_FILE), true);
// 2. Light/Dark theme toggle
if(isset($_GET['theme'])){
    $_SESSION['theme'] = $_GET['theme'] === 'light' ? 'light' : 'dark';
}
$theme = $_SESSION['theme'] ?? 'dark';
// 3. APP LOCK CHECK - Must enter PIN before showing page
$show_lock = false;
$lock_error = "";
if($lock['enabled'] && !isset($_SESSION['unlocked'])){
    $show_lock = true;
    if(isset($_POST['unlock_pin'])){
        if(password_verify($_POST['unlock_pin'], $lock['pin'])){
            $_SESSION['unlocked'] = true;
            header("Location: index.php"); // refresh to show home
            exit;
        } else {
            $lock_error = "Incorrect PIN";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UniFile - Private Local File Sharing</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#3b82f6">
<script>
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/sw.js');
}
</script>
<style>
:root{
    /* DARK THEME */
    --bg-dark: #0a0f1e;
    --bg-gradient-dark: linear-gradient(135deg, #0a0f1e 0%, #1a2a4a 50%, #0d1b2a 100%);
    --glass-dark: rgba(255, 255, 255, 0.06);
    --border-dark: rgba(255, 255, 255, 0.12);
    --text-dark: #e6eefc;
    --text-muted-dark: #94a3b8;
    --accent-dark: #01E5C0; /* Changed to your UniFile accent */
    --accent2-dark: #00ff88;
    --shadow-dark: rgba(1, 229, 192, 0.15);
    /* LIGHT THEME */
    --bg-light: #f0f4f9;
    --bg-gradient-light: linear-gradient(135deg, #e0e7ff 0%, #f0f4f9 50%, #dbeafe 100%);
    --glass-light: rgba(255, 255, 255, 0.55);
    --border-light: rgba(0, 0, 0, 0.08);
    --text-light: #1e293b;
    --text-muted-light: #64748b;
    --accent-light: #01E5C0; /* Changed to your UniFile accent */
    --accent2-light: #059669;
    --shadow-light: rgba(1, 229, 192, 0.1);
}
body{
    margin: 0;
    font-family: 'Poppins', sans-serif;
    min-height: 100vh;
    transition: background 0.4s ease, color 0.4s ease;
    overflow-x: hidden;
}
body.dark{ background: var(--bg-gradient-dark); color: var(--text-dark); }
body.light{ background: var(--bg-gradient-light); color: var(--text-light); }
/* Animated background blobs */
.bg-blob{ position: fixed; border-radius: 50%; filter: blur(120px); z-index: -1; opacity: 0.4; animation: float 20s infinite ease-in-out; }
.blob1{ width: 400px; height: 400px; background: #01E5C0; top: -100px; left: -100px; }
.blob2{ width: 350px; height: 350px; background: #00ff88; bottom: -100px; right: -100px; animation-delay: -5s; }
@keyframes float{ 0%,100%{ transform: translateY(0) scale(1); } 50%{ transform: translateY(-30px) scale(1.1); } }
/* NAVBAR - Glassmorphism */
.navbar{ display: flex; align-items: center; justify-content: space-between; padding: 18px 5%; backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border-bottom: 1px solid; position: sticky; top: 0; z-index: 100; transition: all 0.3s ease; }
body.dark .navbar{ background: var(--glass-dark); border-color: var(--border-dark); }
body.light .navbar{ background: var(--glass-light); border-color: var(--border-light); }
.logo-wrap{ display: flex; align-items: center; gap: 12px; }
.logo-wrap img{ width: 52px; height: 42px; border-radius: 12px; box-shadow: 0 0 15px var(--shadow-dark); }
body.light .logo-wrap img{ box-shadow: 0 0 15px var(--shadow-light); }
.logo-text{ font-size: 1.6rem; font-weight: 700; background: linear-gradient(90deg, var(--accent-dark), var(--accent2-dark)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
body.light .logo-text{ background: linear-gradient(90deg, var(--accent-light), var(--accent2-light)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.theme-toggle{ padding: 10px 18px; border-radius: 14px; border: 1px solid; background: transparent; color: inherit; cursor: pointer; font-weight: 600; transition: all 0.3s ease; backdrop-filter: blur(10px); }
body.dark .theme-toggle{ border-color: var(--border-dark); }
body.light .theme-toggle{ border-color: var(--border-light); }
.theme-toggle:hover{ transform: translateY(-2px); box-shadow: 0 8px 20px var(--shadow-dark); }
body.light .theme-toggle:hover{ box-shadow: 0 8px 20px var(--shadow-light); }
/* HERO */
.container{ max-width: 1200px; margin: 0 auto; padding: 60px 5% 80px; text-align: center; }
h1{ font-size: clamp(2.2rem, 5vw, 3.5rem); margin-bottom: 15px; font-weight: 700; }
.subtitle{ font-size: 1.1rem; max-width: 650px; margin: 0 auto 50px; }
body.dark .subtitle{ color: var(--text-muted-dark); }
body.light .subtitle{ color: var(--text-muted-light); }
/* CARDS - Glass + Neumorphism */
.cards{ display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px; margin-top: 40px; }
.card{ padding: 40px 30px; border-radius: 24px; backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid; text-decoration: none; color: inherit; transition: all 0.4s cubic-bezier(0.2, 0.8, 0.2, 1); position: relative; overflow: hidden; }
body.dark .card{ background: var(--glass-dark); border-color: var(--border-dark); box-shadow: 8px 8px 20px rgba(0,0,0,0.4), -8px -8px 20px rgba(255,255,255,0.05); }
body.light .card{ background: var(--glass-light); border-color: var(--border-light); box-shadow: 8px 8px 20px rgba(0,0,0,0.08), -8px -8px 20px rgba(255,255,255,0.8); }
.card:hover{ transform: translateY(-10px) scale(1.02); }
body.dark .card:hover{ box-shadow: 0 20px 40px var(--shadow-dark); }
body.light .card:hover{ box-shadow: 0 20px 40px var(--shadow-light); }
.card-icon{ font-size: 3rem; margin-bottom: 20px; display: inline-block; }
.card h3{ font-size: 1.5rem; margin-bottom: 10px; font-weight: 600; }
.card p{ font-size: 0.95rem; line-height: 1.6; }
body.dark .card p{ color: var(--text-muted-dark); }
body.light .card p{ color: var(--text-muted-light); }
.card::before{ content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; border-radius: 24px; padding: 2px; background: linear-gradient(135deg, var(--accent-dark), var(--accent2-dark)); -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0); -webkit-mask-composite: xor; mask-composite: exclude; opacity: 0; transition: opacity 0.4s ease; }
body.light .card::before{ background: linear-gradient(135deg, var(--accent-light), var(--accent2-light)); }
.card:hover::before{ opacity: 1; }
/* LOCK SCREEN */
.lock-container{ display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px; }
.lock-box{ padding: 50px 40px; border-radius: 24px; backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid; text-align:center; max-width:420px; width:100%; }
body.dark .lock-box{ background: var(--glass-dark); border-color: var(--border-dark); box-shadow: 0 20px 50px rgba(0,0,0,0.5); }
body.light .lock-box{ background: var(--glass-light); border-color: var(--border-light); box-shadow: 0 20px 50px rgba(0,0,0,0.1); }
.lock-box h2{ font-size:2rem; margin-bottom:10px; }
.lock-box input{ width:100%; padding:16px; border-radius:14px; border:1px solid; background:transparent; color:inherit; font-size:22px; text-align:center; letter-spacing:12px; font-weight:700; margin:20px 0; }
body.dark .lock-box input{ border-color: var(--border-dark); }
body.light .lock-box input{ border-color: var(--border-light); }
.lock-btn{ width:100%; padding:14px; border-radius:14px; border:none; background:var(--accent-dark); color:#000; font-weight:700; font-size:16px; cursor:pointer; transition:0.3s; }
.lock-btn:hover{ transform:translateY(-2px); box-shadow:0 8px 20px var(--shadow-dark); }
.error{ color:#EF4444; margin-bottom:15px; font-weight:600; }
/* FOOTER */
footer{ text-align: center; padding: 30px 5%; font-size: 0.9rem; }
body.dark footer{ color: var(--text-muted-dark); }
body.light footer{ color: var(--text-muted-light); }
/* Mobile */
@media(max-width: 768px){ .navbar{ padding: 15px 5%; } .logo-text{ font-size: 1.3rem; } .container{ padding: 40px 5%; } }
</style>
</head>
<body class="<?php echo $theme; ?>">
<div class="bg-blob blob1"></div>
<div class="bg-blob blob2"></div>
<?php if($show_lock): ?>
    <!-- APP LOCK SCREEN -->
    <div class="lock-container">
        <form method="POST" class="lock-box">
            <div class="logo-text" style="font-size:2.2rem;margin-bottom:10px">UniFile</div>
            <h2>🔒 App Locked</h2>
            <p class="subtitle" style="margin-bottom:10px">Enter your 4 Digit PIN</p>
            <?php if($lock_error): ?><div class="error"><?=$lock_error?></div><?php endif; ?>
            <input type="password" name="unlock_pin" maxlength="4" pattern="\d{4}" required autofocus inputmode="numeric">
            <button class="lock-btn">Unlock</button>
        </form>
    </div>
<?php else: ?>
    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="logo-wrap">
            <img src="uni.png" alt="UniFile Logo">
            <span class="logo-text">UniFile</span>
        </div>
        <a href="?theme=<?php echo $theme === 'dark' ? 'light' : 'dark'; ?>">
            <button class="theme-toggle">
                <?php echo $theme === 'dark' ? '☀️ Light' : '🌙 Dark'; ?>
            </button>
        </a>
    </nav>
    <!-- MAIN CONTENT -->
    <div class="container">
        <h1>Private. Local. Instant.</h1>
        <p class="subtitle">Transfer files between any 2 devices on the same WiFi. No internet. No cloud. No account. No limits.</p>
        <div class="cards">
            <!-- SEND CARD -->
            <a href="upload.php" class="card">
                <div class="card-icon">📤</div>
                <h3>Send</h3>
                <p>Start a new session. Get your IP + PIN and send files instantly to any device on your network.</p>
            </a>
            <!-- RECEIVE CARD -->
            <a href="verify.php" class="card">
                <div class="card-icon">📥</div>
                <h3>Receive</h3>
                <p>Join a session by entering the Sender's IP and 4-digit PIN to receive files securely.</p>
            </a>
            <!-- SETTINGS CARD -->
            <a href="settings.php" class="card">
                <div class="card-icon">⚙️</div>
                <h3>Settings</h3>
                <p>Manage session timeout, notifications, theme preferences and app behavior.</p>
            </a>
        </div>
    </div>
    <footer>
        © <?php echo date('Y'); ?> UniFile. 100% Private. Files never leave your LAN.
    </footer>
<?php endif; ?>
</body>
</html>