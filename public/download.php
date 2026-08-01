<?php
session_start();
$SESSIONS_DIR = __DIR__. "/sessions";
// 5. BLOCK DIRECT ACCESS - Must come from verify.php
if(!isset($_SESSION['session_ip']) ||!isset($_SESSION['session_pin']) ||!isset($_SESSION['sender_name'])){
    header("Location: index.php");
    exit;
}
$ip_safe = $_SESSION['session_ip'];
$pin_safe = $_SESSION['session_pin'];
$sender_name = $_SESSION['sender_name'];
$sender_ip = str_replace('_','.', $ip_safe);
$my_ip = $_SERVER['REMOTE_ADDR'];
$CLIENT_ID = $_SESSION['client_id']?? $_COOKIE['client_id']?? bin2hex(random_bytes(8));
setcookie('client_id', $CLIENT_ID, time() + 86400 * 30, "/");
// 1. UNIQUE DEVICE NAME
$user_agent = $_SERVER['HTTP_USER_AGENT'];
$device_type = (stripos($user_agent, 'mobile') !== false || stripos($user_agent, 'android') !== false || stripos($user_agent, 'iphone') !== false) ? 'Phone' : 'PC';
$my_name = $device_type. "-". substr(session_id(), 0, 6);
$my_base_name = $_SESSION['sender_name']; // original name from settings
$my = ['username'=>$my_base_name. '-'. $device_type, 'device'=>$device_type];
$session_file = $SESSIONS_DIR. "/". $ip_safe. "_". $pin_safe. ".json";
$session_folder = $SESSIONS_DIR. "/". $ip_safe. "_". $pin_safe;
if(!file_exists($session_file)){
    session_destroy();
    die("<script>alert('Session ended.'); window.location='index.php';</script>");
}
$session_data = json_decode(file_get_contents($session_file), true);
// Update my last seen
$session_data['users'][$CLIENT_ID] = ['name'=>$my['username'], 'device'=>$my['device'], 'ip' => $my_ip, 'last_seen' => time(), 'type' => $device_type, 'role'=> 'receiver'];
if(!isset($session_data['users'][$session_data['sender_id']])){
    $session_data['users'][$session_data['sender_id']] = ['name'=>$sender_name. '-'. $session_data['sender_device'], 'device'=>$session_data['sender_device'], 'ip' => $sender_ip, 'last_seen' => time(), 'type' => 'PC', 'role'=> 'sender'];
} else {
    $session_data['users'][$session_data['sender_id']]['last_seen'] = time(); // keep sender alive
}
file_put_contents($session_file, json_encode($session_data));
// HANDLE DISCONNECT
if(isset($_POST['disconnect'])){
    unset($session_data['users'][$CLIENT_ID]);
    file_put_contents($session_file, json_encode($session_data));
    session_destroy();
    header("Location: index.php"); exit;
}
// HANDLE FILE UPLOAD - for progress we use ajax, but fallback here
if(isset($_FILES['file']) && $_FILES['file']['error'] === 0){
    $filename = time(). "_". basename($_FILES['file']['name']);
    if(!is_dir($session_folder)) mkdir($session_folder, 0777, true);
    move_uploaded_file($_FILES['file']['tmp_name'], $session_folder. "/". $filename);
    $fp = fopen($session_file, "c+");
    if (flock($fp, LOCK_EX)) {
        $session_data = json_decode(stream_get_contents($fp), true)?? [];
        $session_data['files'][$filename] = ['from' => $CLIENT_ID, 'size' => $_FILES['file']['size'], 'time' => time()];
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($session_data));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    echo json_encode(['status' => 'ok']);
    exit; // <-- IMPORTANT
}
// HANDLE ACCEPT / REJECT
$action = $_POST['action']?? '';
$file = basename($_POST['file']?? '');
$file_path = $session_folder. "/". $file;
if($action !== '' && $file !== ''){
    if($action === 'accept' && file_exists($file_path)){
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($file_path).'"');
        header('Content-Length: '. filesize($file_path));
        readfile($file_path);
        unlink($file_path);
        unset($session_data['files'][$file]);
        file_put_contents($session_file, json_encode($session_data));
        exit;
    }
    if($action === 'reject'){
        $session_data['files'][$file]['rejected_by'][] = $CLIENT_ID;
        file_put_contents($session_file, json_encode($session_data));
        header("Location: download.php"); exit;
    }
    if($action === 'delete' && file_exists($file_path)){
        unlink($file_path);
        unset($session_data['files'][$file]);
        file_put_contents($session_file, json_encode($session_data));
        header("Location: download.php"); exit;
    }
}

$incoming_files = [];
$my_uploads = [];
if(isset($session_data['files'])){
    foreach($session_data['files'] as $fname => $meta){
        $file_path = $session_folder. "/". $fname;
        if(!file_exists($file_path)) continue;
        if($meta['from'] == $CLIENT_ID){
            $my_uploads[] = ['name' => $fname, 'size' => $meta['size']?? filesize($file_path)];
        } elseif(!in_array($CLIENT_ID, $meta['rejected_by']??[])){
            $incoming_files[] = [
                'name' => $fname,
                'size' => $meta['size']?? filesize($file_path),
                'from' => $meta['from']
            ];
        }
    }
}

// 4. Get online users
$online_users = [];
foreach($session_data['users'] as $id => $data){
    if(time() - $data['last_seen'] < 60){
        $online_users[] = ['name' => $data['name'], 'role' => $data['role'], 'ip' => $data['ip']??'Unknown', 'type' => $data['device']??$data['type']??'PC'];
    }
}
function formatBytes($bytes) {
    $units = ['B','KB','MB','GB']; $i = 0;
    while($bytes >= 1024 && $i < count($units)-1){ $bytes /= 1024; $i++; }
    return round($bytes,2).' '.$units[$i];
}
if(isset($_GET['ajax'])){
    session_write_close(); // release lock so page doesn't freeze
    $session_data = json_decode(@file_get_contents($session_file), true) ?? [];
    $incoming_files = []; $my_uploads = [];
    if(isset($session_data['files'])){
        foreach($session_data['files'] as $fname => $meta){
            $file_path = $session_folder. "/". $fname;
            if(!file_exists($file_path)) continue;
            if($meta['from'] == $CLIENT_ID){
                $my_uploads[] = ['name' => $fname, 'size' => $meta['size']?? @filesize($file_path)];
            } elseif(!in_array($CLIENT_ID, $meta['rejected_by']??[])){
                $incoming_files[] = ['name' => $fname, 'size' => $meta['size']?? @filesize($file_path), 'from' => $meta['from']];
            }
        }
    }
    ob_start();
    if(empty($my_uploads)) echo '<div class="empty">No files uploaded by you</div>';
    else foreach($my_uploads as $f){ echo '<div class="file-item"><div class="file-info"><div class="file-name">'.htmlspecialchars($f['name']).'</div><div class="file-meta">'.formatBytes($f['size']).'</div></div><form method="POST"><input type="hidden" name="file" value="'.$f['name'].'"><input type="hidden" name="action" value="delete"><button class="btn reject">Delete</button></form></div>'; }
    $my_uploads_html = ob_get_clean();
    ob_start();
    if(empty($incoming_files)) echo '<div class="empty">Waiting for files...</div>';
    else foreach($incoming_files as $f){ echo '<div class="file-item"><div class="file-info"><div class="file-name">'.htmlspecialchars($f['name']).'</div><div class="file-meta">From: '.htmlspecialchars($f['from']).'</div></div><form method="POST"><input type="hidden" name="file" value="'.$f['name'].'"><input type="hidden" name="action" value="accept"><button class="btn accept">Accept</button></form><form method="POST"><input type="hidden" name="file" value="'.$f['name'].'"><input type="hidden" name="action" value="reject"><button class="btn reject">Reject</button></form></div>'; }
    $incoming_html = ob_get_clean();
    header('Content-Type: application/json');
    echo json_encode(['my_uploads_html' => $my_uploads_html, 'incoming_html' => $incoming_html, 'incoming_files' => array_map(fn($f)=>['name'=>$f['name'],'from'=>$f['from']], $incoming_files)]);
    exit; // <-- This stops all the slow code below from running
}
function deleteFolder($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.','..']);
    foreach ($files as $file) {
        $path = $dir . "/" . $file;
        is_dir($path) ? deleteFolder($path) : unlink($path);
    }
    rmdir($dir);
}
if(isset($session_data) && isset($CLIENT_ID)){
    // Update last_seen for current user
    $session_data['users'][$CLIENT_ID]['last_seen'] = time();
    $all_offline_long = true;
    foreach($session_data['users'] as $uid => $u){
        // If anyone was seen in last 600 seconds = 10 minutes, don't delete
        if(time() - $u['last_seen'] < 600){
            $all_offline_long = false;
            break;
        }
    }
    if($all_offline_long){
        // Everyone gone for 10min. Safe to delete
        if(is_dir($session_folder)) deleteFolder($session_folder);
        if(file_exists($session_file)) unlink($session_file);
    } else {
        // Save updated last_seen back to file
        $fp = fopen($session_file, "c+");
        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($session_data));
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Download Files | UniFile</title>
<meta name="description" content="Download files shared with you securely through UniFile. Files are transferred directly on your local network.">
<meta name="robots" content="noindex, nofollow"> <!-- don't index downloads -->
<meta name="theme-color" content="#01E5C0">
<meta property="og:title" content="Download Files | UniFile">
<meta property="og:description" content="Securely download files shared on your local network.">
<meta property="og:image" content="https://unifile.infinityfreeapp.com/uni.png">
<link rel="manifest" href="/manifest.json">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="UniFile">
<link rel="icon" href="favicon.ico">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
:root{ --bg: #0a0f1e; --bg2: #16213E; --glass: rgba(255, 255, 255, 0.05); --text: #e6eefc; --muted: #94a3b8; --accent: #01E5C0; --danger: #EF4444; --ok: #10B981; }
*{ box-sizing:border-box; margin:0; padding:0; }
body{ font-family:'Poppins', sans-serif; background:linear-gradient(135deg, var(--bg) 0%, var(--bg2) 100%); color:var(--text); padding:20px; min-height:100vh; }
.container{ max-width:900px; margin:0 auto; }
/* 1. TOP HEADER BAR */
.topbar{ display:flex; justify-content:space-between; align-items:center; padding:20px 30px; border-radius:20px; background:var(--glass); backdrop-filter:blur(20px); border:1px solid rgba(255,255,255,0.1); margin-bottom:20px; flex-wrap:wrap; gap:15px; z-index: 100000000; }
.topbar-info{ display:flex; flex-direction:column; gap:4px; font-size:14px; }
.topbar-info b{ color:var(--accent); }
.disconnect-btn{ padding:10px 20px; border-radius:10px; border:none; background:var(--danger); color:#fff; font-weight:600; cursor:pointer; }
.card{ padding:30px; border-radius:24px; background:var(--glass); backdrop-filter:blur(20px); border:1px solid rgba(255,255,255,0.1); margin-bottom:20px; }
.card h2{ font-size:20px; margin-bottom:20px; }
/* 3. DRAG DROP + PROGRESS */
.dropzone{ border:2px dashed rgba(1,229,192,0.4); border-radius:16px; padding:40px 20px; text-align:center; cursor:pointer; transition:0.2s; }
.dropzone.dragover{ background:rgba(1,229,192,0.1); border-color:var(--accent); }
.dropzone input{ display:none; }
.upload-info{ display:flex; justify-content:space-between; margin-top:10px; font-size:13px; color:var(--muted); }
.progress-bar{ width:100%; height:8px; background:rgba(0,0,0,0.3); border-radius:10px; margin-top:10px; overflow:hidden; display:none; }
.progress-fill{ height:100%; width:0%; background:var(--accent); transition:width 0.2s; }
.file-item{ display:flex; justify-content:space-between; align-items:center; padding:16px; border-radius:16px; background:rgba(0,0,0,0.2); margin-bottom:12px; gap:10px; flex-wrap:wrap; }
.file-info{ flex:1; min-width:150px; }
.file-name{ font-weight:600; word-break:break-all; }
.file-meta{ font-size:12px; color:var(--muted); }
.btns{ display:flex; gap:10px; }
.btn{ padding:10px 16px; border-radius:10px; border:none; font-weight:600; cursor:pointer; font-size:14px; }
.btn.accept{ background:var(--ok); color:#fff; }
.btn.reject{ background:var(--danger); color:#fff; }
.user-item{ display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-radius:12px; background:rgba(0,0,0,0.2); margin-bottom:10px; }
.user-info{ display:flex; flex-direction:column; }
.user-ip{ font-size:11px; color:var(--muted); }
.role{ padding:4px 10px; border-radius:8px; background:var(--accent); color:#000; font-size:11px; font-weight:700; }
.empty{ text-align:center; color:var(--muted); padding:30px; }
.scroll-area{
    max-height: 520px; /* ~7 files * 70px each */
    overflow-y: auto;
    padding-right: 8px;
}
/* Custom Scrollbar */
.scroll-area::-webkit-scrollbar { width: 8px; }
.scroll-area::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); border-radius:10px; }
.scroll-area::-webkit-scrollbar-thumb { background: var(--accent); border-radius:10px; }
.scroll-area::-webkit-scrollbar-thumb:hover { background: #00c9a7; }
/* Firefox */
.scroll-area{ scrollbar-width: thin; scrollbar-color: var(--accent) rgba(0,0,0,0.2); }
/* 2. MOBILE: change buttons to check and X */
@media (max-width: 768px){
    body{ padding:15px; }
    .card{ padding:20px; }
    .topbar{ padding:15px 20px; }
    .file-item{ flex-direction:column; align-items:flex-start; }
    .btns{ width:100%; }
    .btn{ flex:1; font-size:18px; padding:12px; }
    .btn .txt{ display:none; } /* hide text on mobile */
    .btn.accept::after{ content:"✓"; }
    .btn.reject::after{ content:"✕"; }
}
</style>
</head>
<body>
<div class="container">
    <!-- 1. TOP BAR -->
    <div class="topbar">
        <div class="topbar-info">
            <div>You: <b><?=htmlspecialchars($my['username'])?></b></div>
            <div>Your IP: <?=htmlspecialchars($my_ip)?></div>
            <div>Sender IP: <?=htmlspecialchars($sender_ip)?></div>
        </div>
        <form method="POST"><button name="disconnect" class="disconnect-btn">Disconnect</button></form>
    </div>
    <!-- 2. MY UPLOADS -->
<div class="card">
    <h2>My Uploads</h2>
    <div class="scroll-area">
        <?php if(empty($my_uploads)):?>
            <div class="empty">No files uploaded by you</div>
        <?php else:?>
            <?php foreach($my_uploads as $f):?>
            <div class="file-item">
                <div class="file-info">
                    <div class="file-name"><?=htmlspecialchars($f['name'])?></div>
                    <div class="file-meta"><?=formatBytes($f['size'])?></div>
                </div>
                <div class="btns">
                    <form method="POST"><input type="hidden" name="file" value="<?=$f['name']?>"><input type="hidden" name="action" value="delete"><button class="btn reject"><span class="txt">Delete</span></button></form>
                </div>
            </div>
            <?php endforeach;?>
        <?php endif;?>
    </div>
</div>
<!-- 3. INCOMING FILES -->
<div class="card">
    <h2>Incoming Files</h2>
    <div class="scroll-area">
    <div id="incomingList">
    <?php if(empty($incoming_files)):?>
        <div class="empty">Waiting for files...</div>
    <?php else:?>
        <?php foreach($incoming_files as $f):?>
        <div class="file-item">
            <div class="file-info">
                <div class="file-name"><?=htmlspecialchars($f['name'])?></div>
                <div class="file-meta">From: <?=htmlspecialchars($f['from'])?> • <?=formatBytes($f['size'])?></div>
            </div>
            <div class="btns">
                <form method="POST"><input type="hidden" name="file" value="<?=$f['name']?>"><input type="hidden" name="action" value="accept"><button class="btn accept"><span class="txt">Accept</span></button></form>
                <form method="POST"><input type="hidden" name="file" value="<?=$f['name']?>"><input type="hidden" name="action" value="reject"><button class="btn reject"><span class="txt">Reject</span></button></form>
            </div>
        </div>
        <?php endforeach;?>
    <?php endif;?>
    </div>
</div>
        </div>
    <!-- 3. SEND FILES WITH PROGRESS -->
    <div class="card">
        <h2>Send Files</h2>
        <form id="uploadForm" enctype="multipart/form-data">
            <div class="dropzone" id="dropzone">
                <p style="font-size:18px;">📤 Drag & Drop files here</p>
                <p style="color:var(--muted); font-size:13px;">or click to browse</p>
                <input type="file" name="file" id="fileInput" multiple>
            </div>
            <div class="upload-info">
                <span id="fileCount">0 files selected</span>
                <span id="fileSize">0 MB</span>
            </div>
            <div class="progress-bar" id="progressBar"><div class="progress-fill" id="progressFill"></div></div>
            <div class="upload-info">
                <span id="eta">ETA: --</span>
                <span id="percent">0%</span>
            </div>
        </form>
    </div>
    <!-- 4. USERS ON NETWORK -->
    <div class="card">
        <h2>Users on This Network (<?=count($online_users)?>)</h2>
        <?php foreach($online_users as $u):?>
            <div class="user-item">
                <div class="user-info">
                    <div><?=htmlspecialchars($u['name'])?></div>
                    <div class="user-ip"><?=htmlspecialchars($u['ip'])?></div>
                </div>
                <div class="role"><?=$u['role']?></div>
            </div>
        <?php endforeach;?>
    </div>
<script>
const dropzone = document.getElementById('dropzone');
const fileInput = document.getElementById('fileInput');
const progressBar = document.getElementById('progressBar');
const progressFill = document.getElementById('progressFill');
let uploadQueue = [];
let isUploading = false;
let shownPopups = new Set(); // for sender
let shownPopupsDL = new Set(); // for receiver
// DRAG DROP
dropzone.onclick = () => fileInput.click();
dropzone.ondragover = (e) => { e.preventDefault(); dropzone.classList.add('dragover'); };
dropzone.ondragleave = () => dropzone.classList.remove('dragover');
dropzone.ondrop = (e) => { e.preventDefault(); dropzone.classList.remove('dragover'); addToQueue(e.dataTransfer.files); };
fileInput.onchange = () => addToQueue(fileInput.files);
// QUEUE SYSTEM
function addToQueue(files){
    if(files.length === 0) return;
    for(let file of files) uploadQueue.push(file);
    let totalSize = 0; for(let f of uploadQueue) totalSize += f.size;
    document.getElementById('fileCount').innerText = uploadQueue.length + " files in queue";
    document.getElementById('fileSize').innerText = (totalSize/1024/1024).toFixed(2) + " MB";
    processQueue();
}
async function processQueue(){
    if(isUploading || uploadQueue.length === 0) return;
    isUploading = true;
    progressBar.style.display = 'block';
    const file = uploadQueue.shift();
    document.getElementById('fileCount').innerText = `Uploading: ${file.name}`;
    await uploadFile(file);
    isUploading = false;
    if(uploadQueue.length > 0){ processQueue(); }
    else {
        progressBar.style.display = 'none';
        progressFill.style.width = "0%";
        document.getElementById('fileCount').innerText = "0 files selected";
    }
}
// UPLOAD WITH PROGRESS
function uploadFile(file){
    return new Promise((resolve) => {
        let formData = new FormData();
        formData.append('file', file);
        let xhr = new XMLHttpRequest();
        let startTime = Date.now();
        xhr.upload.onprogress = function(e){
            if(e.lengthComputable){
                let percent = Math.round((e.loaded / e.total) * 100);
                progressFill.style.width = percent + "%";
                document.getElementById('percent').innerText = percent + "%";
                let elapsed = (Date.now() - startTime) / 1000;
                let speed = e.loaded / elapsed;
                let remaining = speed > 0 ? Math.round((e.total - e.loaded) / speed) : 0;
                document.getElementById('eta').innerText = "ETA: " + remaining + "s";
            }
        };
        xhr.onload = function(){
            refreshAll(); // refresh lists after upload
            resolve();
        };
        xhr.open('POST', 'download.php');
        xhr.send(formData);
    });
}
function showToast(msg, type){
    alert(msg);
}
// REFRESH BOTH LISTS WITHOUT RELOAD
function refreshAll(){
    fetch('download.php?ajax=1').then(r=>r.json()).then(data=>{
        let myUploadsDiv = document.querySelector('.card:nth-child(2) .scroll-area');
        myUploadsDiv.innerHTML = data.my_uploads_html;
        let incomingDiv = document.getElementById('incomingList');
        incomingDiv.innerHTML = data.incoming_html;
        data.incoming_files.forEach(f => {
            if(!shownPopupsDL.has(f.name)){
                shownPopupsDL.add(f.name);
                showToast(`New file from ${f.from}: ${f.name}`, 'info');
            }
        });
    }).catch(e => console.log("Refresh err", e));
}
// Auto refresh every 2s
setInterval(refreshAll, 2000);
refreshAll(); // run once on load

if('serviceWorker' in navigator){
  navigator.serviceWorker.register('/service-worker.js')
  .then(() => console.log('UniFile SW Registered'))
  .catch(err => console.log('SW Error:', err));
}

</script>
</body>
</html>