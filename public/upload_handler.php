<?php
session_start();
$session_folder = DIR . "/sessions/" . $_SESSION['session_ip'] . "_" . $_SESSION['session_pin'];
if(!is_dir($session_folder)) mkdir($session_folder, 0777, true);
foreach($_FILES['files']['tmp_name'] as $key => $tmp_name){
    $name = basename($_FILES['files']['name'][$key]);
    move_uploaded_file($tmp_name, $session_folder . "/" . $name);
}
echo "ok";
?>