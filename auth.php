<?php
// auth.php - 内网免登录自动登录
// 依赖 db.php 已加载($pdo 可用, session 已启动)

function is_internal_ip($ip) {
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;
    }
    // 私有地址段(10/8, 172.16/12, 192.168/16)或保留地址段(含 127.0.0.1)视为内网
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}

function detect_os($ua) {
    if (preg_match('/Windows NT (\d+\.\d+)/', $ua, $m)) {
        return $m[1] === '10.0' ? 'Windows' : 'Windows' . str_replace('.', '', $m[1]);
    }
    if (preg_match('/Android (\d+)/', $ua, $m)) {
        return 'Android' . $m[1];
    }
    if (preg_match('/iPhone OS (\d+)_(\d+)/', $ua, $m)) {
        return 'iPhone' . $m[1];
    }
    if (preg_match('/iPad OS (\d+)_(\d+)/', $ua, $m)) {
        return 'iPad' . $m[1];
    }
    if (preg_match('/Mac OS X/', $ua)) {
        return 'MacOS';
    }
    if (stripos($ua, 'Linux') !== false) {
        return 'Linux';
    }
    return 'UnknownOS';
}

function detect_browser($ua) {
    if (stripos($ua, 'Edg/') !== false) return 'Edge';
    if (stripos($ua, 'OPR/') !== false || stripos($ua, 'Opera') !== false) return 'Opera';
    if (preg_match('/Chrome\/(\d+)/', $ua, $m)) return 'Chrome' . $m[1];
    if (stripos($ua, 'Firefox/') !== false) return 'Firefox';
    if (stripos($ua, 'Safari/') !== false) return 'Safari';
    return 'Browser';
}

function get_device_username() {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';

    // 方式一:反向解析客户端主机名(局域网设备名, 如 DESKTOP-ABC123)
    $hostname = @gethostbyaddr($remote);
    if ($hostname && $hostname !== $remote && !filter_var($hostname, FILTER_VALIDATE_IP)) {
        $name = explode('.', $hostname)[0];
        $name = preg_replace('/[^A-Za-z0-9_-]/', '', $name);
        if (mb_strlen($name) >= 2) {
            return $name;
        }
    }

    // 方式二:基于 User-Agent 生成设备描述(如 Windows-Chrome122)
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $name = detect_os($ua) . '-' . detect_browser($ua);
    $name = preg_replace('/[^A-Za-z0-9_-]/', '', $name);
    if (mb_strlen($name) >= 2) {
        return $name;
    }

    // 兜底
    return 'LANUser' . substr(md5($remote . $ua), 0, 6);
}

// 内网 IP 访问时自动创建/复用设备用户并登录, 返回是否成功
function auto_login_internal() {
    global $pdo;

    if (isset($_SESSION['user_id'])) {
        return true;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!is_internal_ip($ip)) {
        return false;
    }

    $base = get_device_username();
    $username = $base;
    $suffix = 1;
    $userId = null;

    while (true) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($row = $stmt->fetch()) {
            $userId = $row['id'];
            break;
        }
        // 设备用户无需手动密码, 生成随机密码
        $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        if ($stmt->execute([$username, password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT)])) {
            $userId = $pdo->lastInsertId();
            break;
        }
        // 并发冲突或非法名, 尝试加后缀
        $suffix++;
        $username = $base . '_' . $suffix;
        if ($suffix > 100) {
            $username = $base . '_' . uniqid();
        }
    }

    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['auto_internal'] = true;
    return true;
}