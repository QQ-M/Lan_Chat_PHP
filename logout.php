<?php
require 'db.php';
require 'auth.php';
session_destroy();
// 内网访问直接重新自动登录(以设备名创建/复用用户), 外网才回登录页
auto_login_internal();
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
} else {
    header("Location: login.php");
}
exit;
?>