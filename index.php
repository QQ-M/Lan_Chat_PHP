<?php
require 'db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>局域网聊天室</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* 自定义滚动条 */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="bg-gray-100 h-screen flex flex-col">

    <!-- 顶部导航 -->
    <header class="bg-white shadow-sm p-4 flex justify-between items-center z-10">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white font-bold">
                <i class="fas fa-comments"></i>
            </div>
            <h1 class="font-bold text-gray-700">LanChat <span class="text-xs font-normal text-gray-400">Pro</span></h1>
        </div>
        <div class="flex items-center gap-4">
            <span class="text-sm text-gray-600">你好, <b><?= htmlspecialchars($_SESSION['username']) ?></b></span>
            <a href="logout.php" class="text-xs bg-red-100 text-red-600 px-3 py-1 rounded-full hover:bg-red-200 transition">退出</a>
        </div>
    </header>

    <!-- 聊天区域 -->
    <main class="flex-1 overflow-y-auto p-4 space-y-4" id="chat-window">
        <!-- 消息通过 JS 加载 -->
        <div class="flex justify-center mt-10"><i class="fas fa-circle-notch fa-spin text-gray-400"></i></div>
    </main>

    <!-- 底部输入区 -->
    <footer class="bg-white border-t p-4">
        <div class="max-w-4xl mx-auto flex flex-col gap-2">
            
            <!-- 文件预览提示区 -->
            <div id="file-preview-area" class="hidden flex items-center justify-between bg-blue-50 px-3 py-2 rounded text-xs text-blue-700 border border-blue-100">
                <span id="selected-file-name" class="truncate max-w-[80%]"></span>
                <button onclick="clearFile()" class="text-red-500 hover:text-red-700 font-bold">×</button>
            </div>

            <div class="flex gap-2 items-end">
                <!-- 文件上传按钮 -->
                <label class="cursor-pointer p-3 text-gray-500 hover:text-blue-500 hover:bg-gray-100 rounded-full transition relative group">
                    <i class="fas fa-paperclip text-xl"></i>
                    <input type="file" id="file-input" class="hidden" onchange="handleFileSelect(this)">
                    <!-- Tooltip -->
                    <span class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 text-xs bg-gray-800 text-white px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition pointer-events-none whitespace-nowrap">发文件</span>
                </label>

                <!-- 文本输入框 -->
                <div class="flex-1 relative">
                    <textarea id="message-input" rows="1" class="w-full bg-gray-100 border-0 rounded-2xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:bg-white transition resize-none max-h-32" placeholder="输入消息..."></textarea>
                </div>

                <!-- 发送按钮 -->
                <button onclick="sendMessage()" class="bg-blue-600 hover:bg-blue-700 text-white p-3 rounded-full w-12 h-12 flex items-center justify-center shadow-lg transition transform active:scale-95">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </footer>

    <script>
        const chatWindow = document.getElementById('chat-window');
        const fileInput = document.getElementById('file-input');
        const msgInput = document.getElementById('message-input');
        const currentUsername = "<?= $_SESSION['username'] ?>";
        let lastMsgCount = 0;
        let isUserScrolling = false;

        // 监听滚动，如果用户向上滚动查看历史，不自动滚动到底部
        chatWindow.addEventListener('scroll', () => {
            if (chatWindow.scrollTop + chatWindow.clientHeight < chatWindow.scrollHeight - 50) {
                isUserScrolling = true;
            } else {
                isUserScrolling = false;
            }
        });

        // 处理文件选择
        function handleFileSelect(input) {
            const file = input.files[0];
            if (file) {
                document.getElementById('file-preview-area').classList.remove('hidden');
                document.getElementById('selected-file-name').innerHTML = `<i class="fas fa-file mr-1"></i> ${file.name} (${(file.size/1024).toFixed(1)} KB)`;
            }
        }

        // 清除文件
        function clearFile() {
            fileInput.value = '';
            document.getElementById('file-preview-area').classList.add('hidden');
        }

        // 获取消息
        async function fetchMessages() {
            try {
                const res = await fetch('api.php?action=get_messages');
                const data = await res.json();
                
                if (data.length === lastMsgCount) return;

                // 只有在数据有变化时才重新渲染，这里为了简单直接清空，优化方案是只追加新消息
                // 为保证简单性，这里还是全量渲染，但在真实生产环境建议做增量更新
                chatWindow.innerHTML = ''; 

                data.forEach(msg => {
                    const isMe = msg.username === currentUsername;
                    
                    let fileContentHtml = '';
                    if (msg.file_path) {
                        fileContentHtml = `
                            <div class="mt-2">
                                ${msg.preview_html}
                                <a href="${msg.file_path}" download="${msg.file_name}" class="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline mt-1 bg-white/50 px-2 py-1 rounded">
                                    <i class="fas fa-download"></i> 下载: ${msg.file_name}
                                </a>
                            </div>
                        `;
                    }

                    const html = `
                        <div class="flex flex-col ${isMe ? 'items-end' : 'items-start'}">
                            <span class="text-xs text-gray-500 mb-1 px-1">${isMe ? '' : msg.username}</span>
                            <div class="max-w-[75%] px-4 py-2 rounded-2xl shadow-sm text-sm break-words 
                                ${isMe ? 'bg-blue-500 text-white rounded-br-none' : 'bg-white text-gray-800 rounded-bl-none'}">
                                ${msg.content ? `<div>${escapeHtml(msg.content)}</div>` : ''}
                                ${fileContentHtml}
                            </div>
                            <span class="text-[10px] text-gray-400 mt-1 px-1">${msg.created_at.substring(11, 16)}</span>
                        </div>
                    `;
                    chatWindow.innerHTML += html;
                });

                lastMsgCount = data.length;

                // 只有当用户没有向上查看历史时，才自动滚动
                if (!isUserScrolling) {
                    chatWindow.scrollTop = chatWindow.scrollHeight;
                }

            } catch (e) {
                console.error(e);
            }
        }

        function escapeHtml(text) {
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        async function sendMessage() {
            const content = msgInput.value.trim();
            const file = fileInput.files[0];

            if (!content && !file) return;

            const formData = new FormData();
            formData.append('content', content);
            if (file) {
                formData.append('file', file);
            }

            try {
                const res = await fetch('api.php?action=send_message', {
                    method: 'POST',
                    body: formData
                });
                const result = await res.json();
                
                if (result.status === 'success') {
                    msgInput.value = '';
                    clearFile();
                    isUserScrolling = false; // 发送消息强制滚动到底部
                    fetchMessages();
                } else {
                    alert(result.msg || '发送失败');
                }
            } catch (e) {
                alert('发送错误');
            }
        }

        // 回车发送 (Shift+Enter 换行)
        msgInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        
        // 文本框自适应高度
        msgInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
            if(this.value === '') this.style.height = 'auto';
        });

        setInterval(fetchMessages, 1000);
        fetchMessages();
    </script>
</body>
</html>