<?php
require 'db.php';

// 检查是否登录
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => '未登录']);
    exit;
}

$action = $_GET['action'] ?? '';

// --- 获取消息 ---
if ($action == 'get_messages') {
    // 联表查询获取用户名
    $stmt = $pdo->query("
        SELECT m.*, u.username 
        FROM messages m 
        JOIN users u ON m.user_id = u.id 
        ORDER BY m.created_at ASC
    ");
    $messages = $stmt->fetchAll();

    // 处理预览逻辑
    foreach ($messages as &$msg) {
        $msg['preview_html'] = ''; // 默认无预览
        
        if ($msg['file_path']) {
            $ext = strtolower(pathinfo($msg['file_path'], PATHINFO_EXTENSION));
            $fullPath = __DIR__ . '/' . $msg['file_path'];

            // 1. 图片预览
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $msg['preview_html'] = "<img src='{$msg['file_path']}' class='max-w-[200px] max-h-[200px] rounded border border-gray-200 mt-2'>";
            } 
            // 2. 文本预览 (只读前50字)
            elseif (in_array($ext, ['txt', 'md', 'log', 'css', 'js', 'html', 'php', 'json'])) {
                if (file_exists($fullPath)) {
                    $content = file_get_contents($fullPath, false, null, 0, 150); // 读取多一点防止截断乱码，稍后截取
                    // 简单的编码检测，防止乱码
                    if (!mb_check_encoding($content, 'UTF-8')) {
                         $content = mb_convert_encoding($content, 'UTF-8', 'GBK, GB2312, ASCII');
                    }
                    $previewText = mb_substr($content, 0, 100, 'UTF-8');
                    $msg['preview_html'] = "<div class='bg-gray-50 p-2 text-xs font-mono text-gray-600 border border-gray-200 rounded mt-2 break-all'>📄 " . htmlspecialchars($previewText) . "...</div>";
                }
            } 
            // 3. 不支持预览
            else {
                $msg['preview_html'] = "<div class='text-xs text-gray-400 italic mt-1'>❌ 显示此文件不支持预览</div>";
            }
        }
    }
    
    echo json_encode($messages);
    exit;
}

// --- 发送消息 ---
if ($action == 'send_message' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $content = trim($_POST['content'] ?? '');
    $user_id = $_SESSION['user_id'];
    
    $filePath = null;
    $fileName = null;
    $fileType = null;

    // 处理文件上传
    if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $originalName = $_FILES['file']['name'];
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $newName = uniqid() . '.' . $ext;
        $destination = $uploadDir . $newName;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $destination)) {
            $filePath = $destination;
            $fileName = $originalName;
            $fileType = $ext;
        }
    }

    if ($content || $filePath) {
        $stmt = $pdo->prepare("INSERT INTO messages (user_id, content, file_path, file_name, file_type) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $content, $filePath, $fileName, $fileType]);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'msg' => '内容不能为空']);
    }
    exit;
}
?>