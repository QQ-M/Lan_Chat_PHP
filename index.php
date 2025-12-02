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
        <div class="flex justify-center mt-10"><i class="fas fa-circle-notch fa-spin text-gray-400"></i></div>
    </main>

    <!-- 底部输入区 -->
    <footer class="bg-white border-t p-4">
        <div class="max-w-4xl mx-auto flex flex-col gap-2">
            
            <!-- 进度条区域 (默认隐藏) -->
            <div id="progress-container" class="hidden w-full bg-gray-200 rounded-full h-2.5 mb-2">
                <div id="progress-bar" class="bg-blue-600 h-2.5 rounded-full transition-all duration-300" style="width: 0%"></div>
                <div id="progress-text" class="text-xs text-center text-gray-500 mt-1">正在上传... 0%</div>
            </div>

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
                </label>

                <!-- 文本输入框 -->
                <div class="flex-1 relative">
                    <textarea id="message-input" rows="1" class="w-full bg-gray-100 border-0 rounded-2xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:bg-white transition resize-none max-h-32" placeholder="输入消息..."></textarea>
                </div>

                <!-- 发送按钮 -->
                <button id="send-btn" onclick="sendMessage()" class="bg-blue-600 hover:bg-blue-700 text-white p-3 rounded-full w-12 h-12 flex items-center justify-center shadow-lg transition transform active:scale-95 disabled:bg-gray-400 disabled:cursor-not-allowed">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </footer>

    <script>
        const chatWindow = document.getElementById('chat-window');
        const fileInput = document.getElementById('file-input');
        const msgInput = document.getElementById('message-input');
        // 获取进度条相关元素
        const progressContainer = document.getElementById('progress-container');
        const progressBar = document.getElementById('progress-bar');
        const progressText = document.getElementById('progress-text');
        const sendBtn = document.getElementById('send-btn');
        
        const currentUsername = "<?= $_SESSION['username'] ?>";
        let lastMsgCount = 0;
        let isUserScrolling = false;
        let isUploading = false; // 上传状态锁

        chatWindow.addEventListener('scroll', () => {
            if (chatWindow.scrollTop + chatWindow.clientHeight < chatWindow.scrollHeight - 50) {
                isUserScrolling = true;
            } else {
                isUserScrolling = false;
            }
        });

        function handleFileSelect(input) {
            const file = input.files[0];
            if (file) {
                document.getElementById('file-preview-area').classList.remove('hidden');
                document.getElementById('selected-file-name').innerHTML = `<i class="fas fa-file mr-1"></i> ${file.name} (${formatSize(file.size)})`;
            }
        }

        function clearFile() {
            fileInput.value = '';
            document.getElementById('file-preview-area').classList.add('hidden');
        }

        function formatSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // 使用 XMLHttpRequest 实现带进度的上传
        function sendMessage() {
            if (isUploading) return;

            const content = msgInput.value.trim();
            const file = fileInput.files[0];

            // 核心逻辑：只有当文本和文件都为空时才禁止发送，支持单独发文件
            if (!content && !file) return;

            const formData = new FormData();
            formData.append('content', content);
            if (file) {
                formData.append('file', file);
            }

            // UI更新：只有在有文件时才显示进度条并禁用按钮
            if (file) {
                isUploading = true;
                sendBtn.disabled = true;
                progressContainer.classList.remove('hidden');
                progressBar.style.width = '0%';
                progressText.innerText = '准备上传...';
            }

            const xhr = new XMLHttpRequest();

            // 监听上传进度事件
            xhr.upload.addEventListener("progress", function(e) {
                if (e.lengthComputable) {
                    const percentComplete = Math.round((e.loaded / e.total) * 100);
                    progressBar.style.width = percentComplete + '%';
                    progressText.innerText = `正在上传... ${percentComplete}%`;
                }
            });

            // 监听请求完成（无论成功失败）
            xhr.addEventListener("loadend", function() {
                isUploading = false;
                sendBtn.disabled = false;
                progressContainer.classList.add('hidden');
            });

            // 监听成功响应
            xhr.addEventListener("load", function() {
                if (xhr.status === 200) {
                    try {
                        const result = JSON.parse(xhr.responseText);
                        if (result.status === 'success') {
                            msgInput.value = '';
                            msgInput.style.height = 'auto';
                            clearFile();
                            isUserScrolling = false; // 强制滚动到底部
                            fetchMessages();
                        } else {
                            alert(result.msg || '发送失败');
                        }
                    } catch (e) {
                        console.error('JSON解析错误', e);
                        alert('服务器响应错误');
                    }
                } else {
                    alert('上传出错: ' + xhr.statusText);
                }
            });

            // 监听网络错误
            xhr.addEventListener("error", function() {
                alert("网络连接失败");
            });

            xhr.open("POST", "api.php?action=send_message");
            xhr.send(formData);
        }

        async function fetchMessages() {
            try {
                const res = await fetch('api.php?action=get_messages');
                const data = await res.json();
                
                if (data.length === lastMsgCount) return;

                chatWindow.innerHTML = ''; 

                data.forEach(msg => {
                    const isMe = msg.username === currentUsername;
                    
                    let fileContentHtml = '';
                    if (msg.file_path) {
                        fileContentHtml = `
                            <div>
                                ${msg.preview_html}
                                <div class="flex items-center gap-2 mt-1">
                                    <a href="${msg.file_path}" download="${msg.file_name}" class="text-xs text-blue-600 hover:underline bg-blue-50/50 px-2 py-1 rounded inline-flex items-center">
                                        <i class="fas fa-download mr-1"></i> ${msg.file_name}
                                    </a>
                                </div>
                            </div>
                        `;
                    }

                    let textHtml = '';
                    // 如果有文本，且后面有文件，则添加底部边距
                    if (msg.content && msg.content.trim() !== '') {
                        const mbClass = fileContentHtml ? 'mb-2' : '';
                        textHtml = `<div class="${mbClass}">${escapeHtml(msg.content)}</div>`;
                    }

                    // 如果既没文本也没文件路径（异常数据），不渲染
                    if (!textHtml && !fileContentHtml) return;

                    const html = `
                        <div class="flex flex-col ${isMe ? 'items-end' : 'items-start'}">
                            <span class="text-xs text-gray-500 mb-1 px-1">${isMe ? '' : msg.username}</span>
                            <div class="max-w-[75%] px-4 py-2 rounded-2xl shadow-sm text-sm break-words 
                                ${isMe ? 'bg-blue-500 text-white rounded-br-none' : 'bg-white text-gray-800 rounded-bl-none'}">
                                ${textHtml}
                                ${fileContentHtml}
                            </div>
                            <span class="text-[10px] text-gray-400 mt-1 px-1">${msg.created_at.substring(11, 16)}</span>
                        </div>
                    `;
                    chatWindow.innerHTML += html;
                });

                lastMsgCount = data.length;

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

        // 回车发送
        msgInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        
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