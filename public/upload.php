<?php
session_start();
// error_reporting(E_ALL); // uncomment to debug
ob_start();

$SESSIONS_DIR = __DIR__. "/sessions";
if(!is_dir($SESSIONS_DIR)) mkdir($SESSIONS_DIR, 0755, true);

$CLIENT_ID = $_SESSION['client_id']?? $_COOKIE['client_id']?? bin2hex(random_bytes(8));
setcookie('client_id', $CLIENT_ID, time() + 86400 * 30, "/");

$SETTINGS_FILE = __DIR__. "/settings.json";
if(!file_exists($SETTINGS_FILE)) file_put_contents($SETTINGS_FILE, '{}');
$settings = json_decode(file_get_contents($SETTINGS_FILE), true);
$my = $settings[$CLIENT_ID]?? ['username'=>'Sender', 'device'=>'PC'];
$my['device'] = getDeviceName();
$my['username'] = $my['username']. '('. $my['device']. ')';

function getRealIP(){
    // FIXED: No exec/shell_exec. Works on InfinityFree
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR']?? $_SERVER['REMOTE_ADDR']?? '127.0.0.1';
    if(strpos($ip, ',')!== false) $ip = explode(',', $ip)[0]; // for cloudflare/proxy
    $ip = trim($ip);
    return str_replace('.', '_', $ip);
}

function getDeviceName(){
    $ua = $_SERVER['HTTP_USER_AGENT']?? '';
    $platform = "PC";
    if(preg_match('/android/i', $ua)) $platform = "Android";
    elseif(preg_match('/iphone|ipad|ipod/i', $ua)) $platform = "iPhone";
    elseif(preg_match('/windows/i', $ua)) $platform = "Windows";
    elseif(preg_match('/macintosh|mac os/i', $ua)) $platform = "Mac";
    elseif(preg_match('/linux/i', $ua)) $platform = "Linux";
    if(preg_match('/mobile/i', $ua)) $platform.= " Phone";
    else $platform.= " PC";
    return $platform;
}
$my['device'] = getDeviceName();

// CREATE SESSION
if(!isset($_SESSION['session_ip']) ||!is_dir($SESSIONS_DIR."/".$_SESSION['session_ip']."_".$_SESSION['session_pin'])){
    $_SESSION['session_ip'] = getRealIP();
    $_SESSION['session_pin'] = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    $session_data = [
        'sender_id' => $CLIENT_ID,
        'sender_name' => $settings[$CLIENT_ID]['username']?? 'Sender',
        'sender_device' => $my['device'],
        'created' => time(),
        'expires' => time() + 3600,
        'files' => [],
        'receivers' => []
    ];
    $session_data['users'][$CLIENT_ID] = ['name'=>$my['username'], 'device'=>$my['device'], 'role'=>'sender', 'last_seen'=>time()];
    $session_file = $SESSIONS_DIR. "/". $_SESSION['session_ip']. "_". $_SESSION['session_pin']. ".json";
    file_put_contents($session_file, json_encode($session_data));
    $folder = $SESSIONS_DIR. "/". $_SESSION['session_ip']. "_". $_SESSION['session_pin'];
    if(!is_dir($folder)) mkdir($folder, 0755, true);
}

$session_ip = $_SESSION['session_ip'];
$session_pin = $_SESSION['session_pin'];
$session_folder = $SESSIONS_DIR. "/". $session_ip. "_". $session_pin;
$session_file = $SESSIONS_DIR. "/". $session_ip. "_". $session_pin. ".json";
$session_data = file_exists($session_file)? json_decode(file_get_contents($session_file), true) : [];

// AJAX HANDLER
if(isset($_GET['action'])){
    header('Content-Type: application/json');
    ob_clean();
    $session_data = file_exists($session_file)? json_decode(file_get_contents($session_file), true) : [];

    if($_GET['action'] == 'upload'){
        if(empty($_FILES)){ echo json_encode(['status'=>'error','msg'=>'File too big. Max 10MB on InfinityFree']); exit; }
        if(!isset($_FILES['file'])){ echo json_encode(['status'=>'error','msg'=>'No file key']); exit; }
        if($_FILES['file']['error']!== UPLOAD_ERR_OK){ echo json_encode(['status'=>'error','msg'=>'Upload error: '.$_FILES['file']['error']]); exit; }

        $name = basename($_FILES['file']['name']);
        $target = $session_folder. "/". $name;
        $i = 1;
        while(file_exists($target)){
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $base = pathinfo($name, PATHINFO_FILENAME);
            $target = $session_folder. "/". $base."_".$i.".".$ext;
            $name = $base."_".$i.".".$ext;
            $i++;
        }
        if(move_uploaded_file($_FILES['file']['tmp_name'], $target)){
            $fp = fopen($session_file, "c+");
            if (flock($fp, LOCK_EX)) {
                $session_data = json_decode(stream_get_contents($fp), true)?? [];
                $session_data['files'][$name] = ['from' => $CLIENT_ID, 'size' => $_FILES['file']['size'], 'time' => time()];
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode($session_data));
                fflush($fp);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
            echo json_encode(['status'=>'ok','file'=>$name]);
            exit;
        } else {
            echo json_encode(['status'=>'error','msg'=>'Cannot save. Check sessions folder permission 755']);
        }
        exit;
    }

    if($_GET['action'] == 'get_files'){
        $files = is_dir($session_folder)? array_diff(scandir($session_folder), ['.', '..']) : [];
        echo json_encode(['files'=>array_values($files), 'count'=>count($files)]); exit;
    }

    if($_GET['action'] == 'get_users'){
        $users = [];
        $sender_display = $session_data['sender_name']. '-'. $session_data['sender_device'];
        $users[$session_data['sender_id']] = ['name'=>$sender_display, 'device'=>$session_data['sender_device'], 'role'=>'sender'];
        if(isset($session_data['users'])){
            foreach($session_data['users'] as $id => $u){
                if($id!= $session_data['sender_id']){
                    $receiver_display = $u['name']. '-'. $u['device'];
                    $users[$id] = ['name'=>$receiver_display, 'device'=>$u['device'], 'role'=>$u['role']??'receiver'];
                }
            }
        }
        echo json_encode(['users'=>$users]); exit;
    }

    if($_GET['action'] == 'get_incoming'){
        $incoming = [];
        if(isset($session_data['files'])){
            foreach($session_data['files'] as $fname => $meta){
                if($meta['from']!= $CLIENT_ID && file_exists($session_folder."/".$fname)){
                    $incoming[] = ['name' => $fname, 'size' => $meta['size'], 'from' => $meta['from']];
                }
            }
        }
        echo json_encode(['incoming' => $incoming]); exit;
    }
    exit;
}

// DISCONNECT - Only delete sender files
if(isset($_GET['disconnect'])){
    if(is_dir($session_folder) && isset($session_data['files'])){
        foreach($session_data['files'] as $fname => $meta){
            if($meta['from'] == $CLIENT_ID){
                $f = $session_folder."/".$fname;
                if(file_exists($f)) unlink($f);
            }
        }
        foreach($session_data['files'] as $fname => $meta){
            if(!file_exists($session_folder."/".$fname)) unset($session_data['files'][$fname]);
        }
        file_put_contents($session_file, json_encode($session_data));
    }
    unset($_SESSION['session_ip'], $_SESSION['session_pin']);
    header("Location: index.php"); exit;
}

// DELETE FILE
if(isset($_GET['delete'])){
    $file = basename($_GET['delete']);
    $path = $session_folder. "/". $file;
    if(file_exists($path)) unlink($path);
    if(isset($session_data['files'][$file])){ unset($session_data['files'][$file]); file_put_contents($session_file, json_encode($session_data)); }
    header("Location: upload.php"); exit;
}

// ACCEPT / REJECT from popup
if(isset($_GET['accept']) || isset($_GET['reject'])){
    $file = basename($_GET['accept']?? $_GET['reject']);
    $path = $session_folder. "/". $file;
    if(isset($_GET['accept']) && file_exists($path)){
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.$file.'"');
        header('Content-Length: '. filesize($path));
        readfile($path);
        unlink($path);
        exit;
    }
    if(file_exists($path)) unlink($path);
    if(isset($session_data['files'][$file])){ unset($session_data['files'][$file]); file_put_contents($session_file, json_encode($session_data)); }
    header("Location: upload.php"); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Upload Files | UniFile</title>
<meta name="description" content="Select files and share them instantly with devices on your local network using UniFile. Fast, secure, no internet needed.">
<meta name="robots" content="noindex, follow">
<meta name="theme-color" content="#01E5C0">

<meta property="og:title" content="Upload Files | UniFile">
<meta property="og:description" content="Select files and share them instantly on your LAN.">
<meta property="og:image" content="https://unifile.infinityfreeapp.com/uni.png">

<link rel="icon" href="favicon.ico">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
:root{ --bg: #0a0f1e; --bg2: #16213E; --glass: rgba(255, 255, 255, 0.05); --text: #e6eefc; --muted: #94a3b8; --accent: #01E5C0; --danger: #EF4444; --ok: #10B981; }
*{ box-sizing:border-box; }
body{ margin:0; font-family:'Poppins', sans-serif; background:linear-gradient(135deg, var(--bg) 0%, var(--bg2) 100%); color:var(--text); }
.header{ display:flex; justify-content:space-between; align-items:center; padding:16px 5%; backdrop-filter:blur(20px); background:var(--glass); position:sticky; top:0; z-index: 1000;}
.header-info{ display:flex; gap:20px; flex-wrap:wrap; font-size:14px; }
.info-item b{ color:var(--accent); }
.disconnect-btn{ background:var(--danger); color:#fff; border:none; padding:10px 18px; border-radius:12px; cursor:pointer; font-weight:600; text-decoration:none; }
.container{ max-width:900px; margin:0 auto; padding:40px 5%; }
.card{ padding:30px; border-radius:20px; background:var(--glass); backdrop-filter:blur(20px); margin-bottom:30px; }
.card h2{ font-size:20px; margin-bottom:20px; }
.drop-zone{ border:2px dashed rgba(1,229,192,0.3); border-radius:20px; padding:50px; text-align:center; cursor:pointer; }
.drop-zone:hover{ border-color:var(--accent); background:rgba(1,229,192,0.05); }
.drop-zone.dragover{ border-color:var(--accent); background:rgba(1,229,192,0.1); }
.upload-btn{ background:var(--accent); color:#000; border:none; padding:14px 20px; border-radius:14px; cursor:pointer; font-weight:700; width:100%; margin-top:15px; font-size:16px; }
.upload-btn:disabled{ opacity:0.6; cursor:not-allowed; }
.progress-wrap{ display:none; margin-top:20px; }
.uploading-file{ display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; font-weight:600; color:var(--accent); }
.cancel-btn{ background:var(--danger); color:#fff; border:none; padding:4px 10px; border-radius:6px; cursor:pointer; font-size:12px; }
.progress-bar{ height:10px; background:rgba(0,0,0,0.3); border-radius:10px; overflow:hidden; }
.progress{ height:100%; width:0%; background:var(--accent); transition:width 0.2s; }
.progress-info{ display:flex; justify-content:space-between; font-size:13px; margin-top:8px; color:var(--muted); }
.status{ text-align:center; margin-top:15px; font-size:16px; font-weight:600; }
.file-item,.user-item{ display:flex; justify-content:space-between; align-items:center; padding:14px; border-radius:14px; margin-bottom:10px; background:rgba(0,0,0,0.2); }
.delete-btn{ background:var(--danger); color:#fff; border:none; padding:6px 12px; border-radius:8px; cursor:pointer; font-size:12px; text-decoration:none; }
.tag{ font-size:11px; padding:3px 8px; border-radius:6px; background:var(--accent); color:#000; font-weight:700; }
.queue-info{ font-size:12px; color:var(--muted); text-align:center; margin-top:8px; }
.popup{ position:fixed; top:80px; right:20px; width:340px; padding:20px; border-radius:16px; background:var(--glass); backdrop-filter:blur(20px); border:1px solid var(--accent); animation:slideIn 0.3s; box-shadow:0 10px 30px rgba(0,0,0,0.3); z-index: 1000000000; }
@keyframes slideIn{ from{ transform:translateX(400px); } to{ transform:translateX(0); } }
.popup h3{ margin:0 0 8px 0; font-size:16px; }
.popup p{ margin:0 0 12px 0; font-size:13px; color:var(--muted); }
.popup-btns{ display:flex; gap:10px; }
.pbtn{ flex:1; padding:10px; border-radius:10px; border:none; font-weight:600; cursor:pointer; text-decoration:none; text-align:center; }
.pbtn.accept{ background:var(--ok); color:#fff; }
.pbtn.reject{ background:var(--danger); color:#fff; }
.scroll-area{ max-height: 520px; overflow-y: auto; padding-right: 8px; }
.scroll-area::-webkit-scrollbar { width: 8px; }
.scroll-area::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); border-radius:10px; }
.scroll-area::-webkit-scrollbar-thumb { background: var(--accent); border-radius:10px; }
.scroll-area::-webkit-scrollbar-thumb:hover { background: #00c9a7; }
.scroll-area{ scrollbar-width: thin; scrollbar-color: var(--accent) rgba(0,0,0,0.2); }
</style>
</head>
<body>
<div class="header">
    <div class="header-info">
        <div class="info-item"><b>You:</b> <?=$my['username']?> (<?=$my['device']?>)</div>
        <div class="info-item"><b>IP:</b> <?=str_replace('_', '.', $session_ip)?></div>
        <div class="info-item"><b>Code:</b> <?=$session_pin?></div>
    </div>
    <a href="upload.php?disconnect=1" class="disconnect-btn">Disconnect</a>
</div>
<div class="container">
    <div class="card">
        <h2>📤 Upload Files</h2>
        <div class="drop-zone" id="dropZone">
            <p style="font-size:40px">📁</p>
            <p><b>Drag & Drop files here</b></p>
            <input type="file" id="fileInput" multiple style="display:none">
        </div>
        <button class="upload-btn" id="uploadBtn">Upload Files (0)</button>
        <div class="queue-info" id="queueInfo"></div>
        <div class="progress-wrap" id="progressWrap">
            <div class="uploading-file">
                <span id="uploadingFile"></span>
                <button class="cancel-btn" id="cancelBtn">Cancel</button>
            </div>
            <div class="progress-bar"><div class="progress" id="progressBar"></div></div>
            <div class="progress-info">
                <span id="progressPercent">0%</span>
                <span id="progressSize">0 MB / 0 MB</span>
                <span id="progressETA">ETA: --</span>
            </div>
            <div class="status" id="status"></div>
        </div>
    </div>
    <div class="card">
        <div class="scroll-area">
            <h2>📄 Uploaded Files (<span id="uploadedCount">0</span>)</h2>
            <div id="fileList">Loading...</div>
        </div>
    </div>
    <div class="card">
        <h2>👥 Users on this Session</h2>
        <div id="userList">Loading...</div>
    </div>
</div>
<div id="popupContainer"></div>
<script>
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const uploadBtn = document.getElementById('uploadBtn');
const cancelBtn = document.getElementById('cancelBtn');
let uploadQueue = [];
let isUploading = false;
let currentXHR = null;
let lastIncoming = '';
function updateButtonCount(){ uploadBtn.textContent = `Upload Files (${uploadQueue.length})`; }
function updateUploadedCount(count){ document.getElementById('uploadedCount').textContent = count; }
dropZone.onclick = () => fileInput.click();
dropZone.ondragover = e => { e.preventDefault(); dropZone.classList.add('dragover'); };
dropZone.ondragleave = () => dropZone.classList.remove('dragover');
dropZone.ondrop = e => { e.preventDefault(); dropZone.classList.remove('dragover'); addToQueue(e.dataTransfer.files); };
fileInput.onchange = e => { addToQueue(e.target.files); };
function addToQueue(files){
    for(let file of files) uploadQueue.push(file);
    updateButtonCount();
    document.getElementById('queueInfo').textContent = uploadQueue.length > 0? `${uploadQueue.length} file(s) in queue` : '';
}
cancelBtn.onclick = () => { if(currentXHR) currentXHR.abort(); resetProgress(); isUploading = false; processQueue(); };
uploadBtn.onclick = () => { if(uploadQueue.length === 0) return alert("Select files first"); processQueue(); };
function resetProgress(){
    document.getElementById('progressWrap').style.display = 'none';
    document.getElementById('progressBar').style.width = '0%';
    document.getElementById('progressPercent').textContent = '0%';
    document.getElementById('status').innerHTML = '';
}
async function processQueue(){
    if(isUploading || uploadQueue.length === 0) return;
    isUploading = true;
    uploadBtn.disabled = true;
    const file = uploadQueue.shift();
    updateButtonCount();
    document.getElementById('queueInfo').textContent = uploadQueue.length > 0? `${uploadQueue.length} file(s) remaining` : '';
    document.getElementById('uploadingFile').textContent = `Uploading: ${file.name}`;
    document.getElementById('progressWrap').style.display = 'block';
    await new Promise((resolve) => {
        const formData = new FormData();
        formData.append('file', file, file.name);
        currentXHR = new XMLHttpRequest();
        const startTime = Date.now();
        currentXHR.upload.onprogress = e => {
            if(e.lengthComputable){
                const percent = Math.round((e.loaded / e.total) * 100);
                document.getElementById('progressBar').style.width = percent + '%';
                document.getElementById('progressPercent').textContent = percent + '%';
                const loadedMB = (e.loaded / 1024 / 1024).toFixed(2);
                const totalMB = (e.total / 1024 / 1024).toFixed(2);
                document.getElementById('progressSize').textContent = `${loadedMB} MB / ${totalMB} MB`;
                const timeElapsed = (Date.now() - startTime) / 1000;
                const speed = e.loaded / timeElapsed;
                const eta = speed > 0? Math.round((e.total - e.loaded) / speed) : 0;
                document.getElementById('progressETA').textContent = `ETA: ${eta}s`;
            }
        };
        currentXHR.onload = function() {
            let xhr = this;
            let res;
            try{ res = JSON.parse(xhr.responseText); } catch(e){}
            if(res && res.status == 'ok'){
                document.getElementById('status').innerHTML = '✓ Uploaded';
                document.getElementById('status').style.color = 'var(--accent)';
                loadFiles();
            } else if(res) {
                document.getElementById('status').innerHTML = '✗ ' + res.msg;
                document.getElementById('status').style.color = 'var(--danger)';
            }
            currentXHR = null;
            setTimeout(() => {
                resetProgress();
                isUploading = false;
                uploadBtn.disabled = false;
                resolve();
                processQueue();
            }, 500);
        };
        currentXHR.onabort = () => { resetProgress(); isUploading = false; processQueue(); };
        currentXHR.onerror = () => {
            document.getElementById('status').innerHTML = '✗ Network Error';
            setTimeout(() => { resetProgress(); isUploading = false; processQueue(); }, 500);
        };
        currentXHR.open('POST', 'upload.php?action=upload');
        currentXHR.timeout = 0;
        currentXHR.send(formData);
    });
}
function addFileToList(name){
    let div = document.createElement('div');
    div.className = 'file-item';
    div.innerHTML = `<div>📄 ${name}</div><a href="upload.php?delete=${encodeURIComponent(name)}" class="delete-btn">Delete</a>`;
    let list = document.getElementById('fileList');
    if(list.innerHTML.includes('No files')) list.innerHTML = '';
    list.prepend(div);
}
async function loadFiles(){
    const res = await fetch('upload.php?action=get_files');
    const data = await res.json();
    updateUploadedCount(data.count);
    let html = data.files.length? '' : '<p style="color:var(--muted)">No files uploaded yet</p>';
    data.files.forEach(f => { html += `<div class="file-item"><div>📄 ${f}</div><a href="upload.php?delete=${encodeURIComponent(f)}" class="delete-btn">Delete</a></div>`; });
    document.getElementById('fileList').innerHTML = html;
}
async function loadUsers(){
    const res = await fetch('upload.php?action=get_users');
    const data = await res.json();
    let html = '';
    for(let id in data.users){
        let u = data.users[id];
        html += `<div class="user-item"><div><b>${u.name}</b> <span style="font-size:12px; color:var(--muted)">(${u.device})</span></div><span class="tag">${id=='<?=$CLIENT_ID?>'? 'You' : u.role}</span></div>`;
    }
    document.getElementById('userList').innerHTML = html;
}
let shownPopups = new Set();
async function checkIncoming(){
    const res = await fetch('upload.php?action=get_incoming');
    const data = await res.json();
    data.incoming.forEach(f => {
        let key = f.name + f.size;
        if(!shownPopups.has(key)){
            shownPopups.add(key);
            let popup = document.createElement('div');
            popup.className = 'popup';
            popup.id = 'popup-' + key;
            popup.innerHTML = `
                <h3>🔔 New File From Receiver!</h3>
                <p><b>${f.name}</b><br>${(f.size/1024/1024).toFixed(2)} MB</p>
                <div class="popup-btns">
                    <a href="upload.php?accept=${encodeURIComponent(f.name)}" class="pbtn accept" onclick="closePopup('${key}')">Accept</a>
                    <a href="upload.php?reject=${encodeURIComponent(f.name)}" class="pbtn reject" onclick="closePopup('${key}')">Reject</a>
                </div>
            `;
            document.getElementById('popupContainer').appendChild(popup);
            setTimeout(()=>{ closePopup(key); }, 15000);
        }
    });
}
function closePopup(key){
    let el = document.getElementById('popup-' + key);
    if(el) el.remove();
    shownPopups.delete(key);
}
setInterval(loadFiles, 2000);
setInterval(loadUsers, 2000);
setInterval(checkIncoming, 2000);
loadFiles(); loadUsers();
</script>
</body>
</html>