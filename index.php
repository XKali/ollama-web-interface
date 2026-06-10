<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require 'db.php';

$is_admin = ($_SESSION['role'] === 'admin');

// Utilisateur courant (avatar etc.)
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$_SESSION['user_id']]);
$currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

// Historique IA de l'utilisateur ou global si on veut
$stmt = $pdo->prepare("SELECT * FROM logs_ia WHERE username = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$_SESSION['username']]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html style="height: 100%; margin: 0;">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($ai_name) ?> Workspace</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />
  <style>
      body { margin: 0; height: 100%; display: flex; flex-direction: column; overflow: hidden; }
      .markdown-content p { margin-bottom: 1em; }
      .markdown-content pre { background-color: rgba(255,255,255,0.05); padding: 1em; border-radius: 0.5em; overflow-x: auto; font-family: monospace; }
      .markdown-content code { color: #22d3ee; }
  </style>
</head>
<body>
  <div class="h-full w-full flex flex-col bg-[#050505] text-[#d1d5db] font-sans overflow-hidden select-none">
    <header class="h-16 border-b border-white/5 bg-black/40 backdrop-blur-xl flex items-center justify-between px-6 shrink-0 relative z-20">
      <div class="flex items-center gap-4">
        <?php if (!empty($ai_logo)): ?>
          <img src="<?= htmlspecialchars($ai_logo) ?>" alt="Logo" class="max-w-6 max-h-6 object-contain rounded">
        <?php else: ?>
          <div class="w-3 h-3 rounded-full bg-cyan-500 shadow-[0_0_10px_rgba(6,182,212,0.5)]"></div>
        <?php endif; ?>
        <span class="font-mono text-xs tracking-widest text-cyan-500 uppercase"><?= htmlspecialchars($ai_name) ?> ONLINE</span>
        <div class="h-4 w-px bg-white/10 mx-2"></div>
        
        <!-- MODEL SELECTOR -->
        <select id="model-select" class="bg-[#111] border border-white/10 rounded-md px-2 py-1 text-sm font-medium tracking-tight text-white focus:outline-none focus:ring-1 focus:ring-cyan-500/50 appearance-none cursor-pointer">
           <option class="bg-[#111] text-white" value="mistral-nemo:latest">Loading models...</option>
        </select>
        <span class="material-symbols-outlined text-[14px] text-white/50 -ml-2 pointer-events-none">expand_more</span>
      </div>

      <div class="flex items-center gap-6">
        <div class="flex items-center gap-4 border border-white/10 p-1 pr-4 rounded-full bg-black/40">
           <div class="w-8 h-8 rounded-full overflow-hidden bg-white/5 border border-white/10 shrink-0">
               <?php if(!empty($currentUser['avatar'])): ?>
                   <img src="<?= htmlspecialchars($currentUser['avatar']) ?>" class="w-full h-full object-cover">
               <?php else: ?>
                   <span class="material-symbols-outlined text-[20px] text-white/40 flex items-center justify-center w-full h-full">person</span>
               <?php endif; ?>
           </div>
           <div class="flex flex-col">
             <span class="text-[10px] uppercase tracking-tighter text-white font-bold"><?= htmlspecialchars($_SESSION['username']) ?></span>
             <span class="text-[9px] font-mono text-emerald-500 lead-none">CONNECTED</span>
           </div>
        </div>
        
        <a href="profile.php" class="w-10 h-10 rounded-full border border-pink-500/30 bg-pink-500/10 hover:bg-pink-500/20 flex items-center justify-center text-xs font-mono transition-colors text-pink-400" title="User Profile">
           <span class="material-symbols-outlined text-[20px]">person</span>
        </a>

        <?php if($is_admin): ?>
        <a href="admin.php" class="w-10 h-10 rounded-full border border-cyan-500/30 bg-cyan-500/10 hover:bg-cyan-500/20 flex items-center justify-center text-xs font-mono transition-colors text-cyan-400" title="Admin Panel">
           <span class="material-symbols-outlined text-[20px]">admin_panel_settings</span>
        </a>
        <?php endif; ?>
        
        <a href="logout.php" class="w-10 h-10 rounded-full border border-white/10 bg-white/5 hover:bg-white/10 flex items-center justify-center text-xs font-mono transition-colors text-white/50 hover:text-white" title="Logout">
            <span class="material-symbols-outlined text-[18px]">logout</span>
        </a>
      </div>
    </header>

    <main class="flex flex-1 overflow-hidden relative z-10">
      <aside class="w-72 bg-black/20 border-r border-white/5 flex flex-col p-6 gap-8 z-20">
        <section class="flex-1 overflow-y-auto">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-[10px] uppercase tracking-[0.2em] text-white/30">Context Vault</h2>
            <button onclick="newChat()" class="text-[10px] uppercase tracking-[0.2em] text-cyan-400 hover:text-cyan-300 transition-colors flex items-center gap-1">
              <span class="material-symbols-outlined text-[14px]">add</span> New
            </button>
          </div>
          <div class="space-y-3" id="history-sidebar">
            <?php foreach ($history as $log): ?>
              <div class="p-3 rounded-lg border border-transparent hover:bg-white/5 transition-colors cursor-pointer group" onclick="loadHistory(<?= $log['id'] ?>)">
                <div class="flex justify-between items-start">
                    <p class="text-xs font-medium text-white/60 line-clamp-1 flex-1"><?= htmlspecialchars($log['prompt'] ?: 'Attaché: '.$log['media_type']) ?></p>
                    <?php if($log['media_type'] !== 'text'): ?>
                      <span class="material-symbols-outlined text-[12px] text-cyan-400/50">attachment</span>
                    <?php endif; ?>
                </div>
                <p class="text-[10px] text-white/30 mt-1"><?= htmlspecialchars($log['model']) ?> • <?= date('H:i', strtotime($log['created_at'])) ?></p>
              </div>
            <?php endforeach; ?>
            <?php if(empty($history)): ?>
               <p class="text-[10px] text-white/30 mt-1">Aucune archive</p>
            <?php endif; ?>
          </div>
        </section>

        <section class="mt-auto border-t border-white/10 pt-6">
           <!-- Info Telemetry -->
           <h3 class="text-[10px] uppercase tracking-[0.2em] text-white/30 mb-4">Hardware Telemetry</h3>
           <div class="space-y-4">
              <div>
                 <p class="text-[9px] uppercase tracking-widest text-white/40 font-mono mb-1">Host System</p>
                 <div class="flex items-center gap-2 text-xs font-mono text-cyan-400">
                    <div class="w-1.5 h-1.5 rounded-full bg-cyan-500 animate-pulse shadow-[0_0_8px_rgba(6,182,212,0.6)]"></div> Online
                 </div>
              </div>
              <div>
                 <p class="text-[9px] uppercase tracking-widest text-white/40 font-mono mb-1">Database</p>
                 <div class="flex items-center gap-2 text-xs font-mono text-emerald-400">
                    <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.6)]"></div> Stable
                 </div>
              </div>
              <div>
                 <p class="text-[9px] uppercase tracking-widest text-white/40 font-mono mb-1">Ollama Node</p>
                 <div class="text-[10px] font-mono text-white/70 truncate" title="<?= htmlspecialchars($ollama_url) ?>">
                    <?= htmlspecialchars($ollama_url) ?>
                 </div>
              </div>
           </div>
        </section>
      </aside>

      <section class="flex-1 flex flex-col bg-[#070709] relative">
        <div class="absolute top-[-10%] right-[-10%] w-[400px] h-[400px] bg-cyan-500/5 rounded-full blur-[100px] pointer-events-none"></div>
        <div class="absolute bottom-[-10%] left-[-5%] w-[300px] h-[300px] bg-indigo-500/5 rounded-full blur-[100px] pointer-events-none"></div>

        <div class="flex-1 p-10 overflow-y-auto flex flex-col gap-10 scroll-smooth relative z-10" id="chat-container">
          <div class="text-center mt-32 text-white/30" id="empty-state">
             <span class="material-symbols-outlined text-4xl mb-4 opacity-20">memory</span>
             <p class="text-xl font-medium tracking-tight text-white mb-2"><?= htmlspecialchars($ai_name) ?> Ready.</p>
             <p class="text-xs font-mono">Awaiting initial execution context.</p>
          </div>
        </div>

        <div class="p-8 bg-black/40 backdrop-blur-md border-t border-white/5 relative z-20">
          <form id="chat-form" class="max-w-4xl mx-auto flex items-end gap-4">
            <div class="flex-1 relative flex items-center bg-white/5 border border-white/10 rounded-xl px-2 transition-all focus-within:border-cyan-500/50 focus-within:ring-1 focus-within:ring-cyan-500/20 group">
              
              <label for="media-upload" id="media-btn" class="cursor-pointer p-3 opacity-50 hover:opacity-100 transition-opacity text-white hover:text-cyan-400 rounded-lg">
                <span class="material-symbols-outlined text-[20px]">attach_file</span>
              </label>
              <input type="file" id="media-upload" class="hidden" accept="image/*,audio/*,video/*" />
              
              <textarea 
                id="prompt"
                class="w-full bg-transparent px-3 py-4 text-sm text-white placeholder-white/20 focus:outline-none resize-none h-14"
                placeholder="Directive système, prompt pour image..."
              ></textarea>
              <div class="pr-4 flex items-center gap-3">
                <span class="text-[10px] font-mono text-white/20">ENTER</span>
              </div>
            </div>
            <button type="button" id="img-gen-btn" title="Générer une image" class="h-14 w-14 flex items-center justify-center bg-indigo-500/20 border border-indigo-500/50 text-indigo-400 rounded-xl hover:bg-indigo-500/40 transition-colors shrink-0">
               <span class="material-symbols-outlined text-[24px]">image</span>
            </button>
            <button type="submit" id="submit-btn" title="Générer texte/analyse (Ollama)" class="h-14 px-8 bg-white text-black font-bold text-xs uppercase tracking-widest rounded-xl hover:bg-cyan-400 transition-colors disabled:opacity-50 shrink-0">
              Transmit
            </button>
          </form>
          
          <div class="max-w-4xl mx-auto mt-2 hidden" id="file-name-display">
             <span class="text-[10px] font-mono text-cyan-400 bg-cyan-500/10 px-2 py-1 rounded inline-flex items-center gap-1">
                 <span class="material-symbols-outlined text-[12px]">description</span> <span id="file-name-text">filename.png</span>
                 <span class="material-symbols-outlined text-[12px] cursor-pointer hover:text-white ml-2" onclick="clearFile()">close</span>
             </span>
          </div>

        </div>
      </section>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  <script>
    const form = document.getElementById('chat-form');
    const promptInput = document.getElementById('prompt');
    const chatContainer = document.getElementById('chat-container');
    const submitBtn = document.getElementById('submit-btn');
    const modelSelect = document.getElementById('model-select');
    const mediaUpload = document.getElementById('media-upload');
    const fileDisplay = document.getElementById('file-name-display');
    const fileText = document.getElementById('file-name-text');
    const userAvatarUrl = <?= json_encode($currentUser['avatar'] ?? '') ?>;

    // Fetch Models
    fetch('api_models.php')
      .then(r => r.json())
      .then(data => {
          if(data.models && data.models.length > 0) {
              modelSelect.innerHTML = '';
              data.models.forEach(m => {
                  modelSelect.innerHTML += `<option value="${m.name}">${m.name}</option>`;
              });
          } else {
              modelSelect.innerHTML = `<option value="mistral-nemo:latest">mistral-nemo:latest</option>`;
          }
      }).catch(e => {
          modelSelect.innerHTML = `<option value="mistral-nemo:latest">mistral-nemo:latest</option>`;
      });

    // File handling UI
    mediaUpload.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            fileDisplay.classList.remove('hidden');
            fileText.textContent = e.target.files[0].name;
            document.getElementById('media-btn').classList.add('text-cyan-400', 'opacity-100');
        }
    });

    window.clearFile = function() {
        mediaUpload.value = '';
        fileDisplay.classList.add('hidden');
        document.getElementById('media-btn').classList.remove('text-cyan-400', 'opacity-100');
    };

    promptInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        if (this.value.trim() || mediaUpload.files.length > 0) form.dispatchEvent(new Event('submit'));
      }
    });

    function escapeHtml(unsafe) {
        return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
    }

    function newChat() {
       chatContainer.innerHTML = `
          <div class="text-center mt-32 text-white/30" id="empty-state">
             <span class="material-symbols-outlined text-4xl mb-4 opacity-20">memory</span>
             <p class="text-xl font-medium tracking-tight text-white mb-2"><?= htmlspecialchars($ai_name) ?> Ready.</p>
             <p class="text-xs font-mono">Awaiting initial execution context.</p>
          </div>
       `;
       clearFile();
       promptInput.value = '';
       promptInput.focus();
    }

    async function loadHistory(id) {
        try {
            const res = await fetch('api_get_log.php?id=' + id);
            const data = await res.json();
            if (data.status === 'success') {
                const log = data.data;
                const emptyState = document.getElementById('empty-state');
                if (emptyState) emptyState.remove();

                chatContainer.innerHTML = ''; // Clear chat

                let userMsg = log.prompt;
                if (log.media_type !== 'text') {
                    userMsg += `\n[Média type: ${log.media_type}]`;
                }

                const userAvatarHtml = userAvatarUrl 
                   ? `<img src="${escapeHtml(userAvatarUrl)}" class="w-full h-full object-cover">`
                   : `USR`;

                const userHtml = `
                <div class="max-w-3xl flex gap-6 group self-end w-full flex-row-reverse justify-end animate-fade-in">
                  <div class="w-8 h-8 rounded shrink-0 border border-white/20 bg-white/5 flex items-center justify-center font-mono text-[10px] text-white/40 overflow-hidden">
                     ${userAvatarHtml}
                  </div>
                  <div class="pt-1 w-full text-right">
                    <p class="text-sm leading-relaxed text-white/90">
                      ${escapeHtml(userMsg).replace(/\\n/g, '<br>')}
                    </p>
                  </div>
                </div>`;
                
                chatContainer.insertAdjacentHTML('beforeend', userHtml);
                
                let resContent = log.response ? marked.parse(log.response) : '<span class="text-red-400 font-mono">[ERROR] Réponse vide</span>';

                const modelHtml = `
                <div class="max-w-4xl flex gap-6 self-start w-full justify-start animate-fade-in">
                  <div class="w-8 h-8 rounded shrink-0 bg-cyan-500/20 border border-cyan-500/30 flex items-center justify-center">
                    <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                  </div>
                  <div class="flex-1">
                    <div class="mb-2 flex items-center gap-3">
                      <span class="text-[10px] font-mono text-cyan-400 tracking-widest uppercase">${escapeHtml(log.model)} Archive</span>
                      <span class="text-[9px] font-mono text-white/30 tracking-widest">${log.created_at}</span>
                      <div class="h-px flex-1 bg-gradient-to-r from-cyan-500/30 to-transparent"></div>
                    </div>
                    <div class="bg-white/[0.02] border border-white/5 rounded-2xl p-6 shadow-sm">
                      <div class="text-sm leading-relaxed text-slate-300 markdown-content">
                        ${resContent}
                      </div>
                    </div>
                  </div>
                </div>`;
                
                chatContainer.insertAdjacentHTML('beforeend', modelHtml);
                chatContainer.scrollTop = chatContainer.scrollHeight;
                
                // On mobile or small screens, might want to close sidebar, but no sidebar hide exists right now
            }
        } catch (error) {
            console.error(error);
        }
    }

    form.addEventListener('submit', (e) => executePost(e, 'text'));
    document.getElementById('img-gen-btn').addEventListener('click', (e) => executePost(e, 'image'));

    async function executePost(e, mode) {
      e.preventDefault();
      const promptText = promptInput.value.trim();
      const file = mediaUpload.files[0];
      
      if (!promptText && !file) return;

      let userMsg = promptText;
      if (file) userMsg += `\n[Fichier attaché: ${file.name}]`;

      const userAvatarHtml = userAvatarUrl 
         ? `<img src="${escapeHtml(userAvatarUrl)}" class="w-full h-full object-cover">`
         : `USR`;

      const userHtml = `
      <div class="max-w-3xl flex gap-6 group self-end w-full flex-row-reverse justify-end animate-fade-in">
        <div class="w-8 h-8 rounded shrink-0 border border-white/20 bg-white/5 flex items-center justify-center font-mono text-[10px] text-white/40 overflow-hidden">
           ${userAvatarHtml}
        </div>
        <div class="pt-1 w-full text-right">
          <p class="text-sm leading-relaxed text-white/90">
            ${escapeHtml(userMsg).replace(/\\n/g, '<br>')}
          </p>
        </div>
      </div>`;
      
      const emptyState = document.getElementById('empty-state');
      if (emptyState) emptyState.remove();

      chatContainer.insertAdjacentHTML('beforeend', userHtml);
      promptInput.value = '';
      chatContainer.scrollTop = chatContainer.scrollHeight;
      submitBtn.disabled = true;

      const loaderId = 'loader-' + Date.now();
      
      const modelName = mode === 'image' ? 'Image Diffuser' : modelSelect.value;
      const bgClass = mode === 'image' ? 'bg-indigo-500/10' : 'bg-cyan-500/10';
      const borderClass = mode === 'image' ? 'border-indigo-500/30' : 'border-cyan-500/30';
      const textClass = mode === 'image' ? 'text-indigo-500' : 'text-cyan-500';

      const loaderHtml = `
      <div id="${loaderId}" class="max-w-4xl flex gap-6 self-start w-full justify-start animate-pulse">
         <div class="w-8 h-8 rounded shrink-0 ${bgClass} border ${borderClass} flex items-center justify-center">
            <svg class="w-4 h-4 ${textClass}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
         </div>
         <div class="pt-1">
            <span class="font-mono text-[10px] uppercase tracking-widest ${textClass}/60">Processing with ${modelName}...</span>
         </div>
      </div>`;
      chatContainer.insertAdjacentHTML('beforeend', loaderHtml);
      
      const formData = new FormData();
      formData.append('prompt', promptText);
      formData.append('model', modelSelect.value);
      if (file) formData.append('media', file);

      clearFile();

      try {
        const endpoint = mode === 'image' ? 'api_image.php' : 'api.php';
        const response = await fetch(endpoint, { method: 'POST', body: formData });
        const data = await response.json();
        
        document.getElementById(loaderId).remove();
        
        let resContent = data.response ? marked.parse(data.response) : '<span class="text-red-400 font-mono">[ERROR] ' + escapeHtml(data.error) + '</span>';

        const textIconClass = mode === 'image' ? 'text-indigo-400' : 'text-cyan-400';
        const gradientClass = mode === 'image' ? 'from-indigo-500/30' : 'from-cyan-500/30';

        const modelHtml = `
        <div class="max-w-4xl flex gap-6 self-start w-full justify-start animate-fade-in">
          <div class="w-8 h-8 rounded shrink-0 ${bgClass.replace('10', '20')} border ${borderClass} flex items-center justify-center">
            <svg class="w-4 h-4 ${textIconClass}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
          </div>
          <div class="flex-1 overflow-hidden">
            <div class="mb-2 flex items-center gap-3">
              <span class="text-[10px] font-mono ${textIconClass} tracking-widest uppercase">${modelName} Stream</span>
              <div class="h-px flex-1 bg-gradient-to-r ${gradientClass} to-transparent"></div>
            </div>
            <div class="bg-white/[0.02] border border-white/5 rounded-2xl p-6 shadow-sm overflow-x-auto">
              <div class="text-sm leading-relaxed text-slate-300 markdown-content">
                ${resContent}
              </div>
            </div>
          </div>
        </div>`;
        
        chatContainer.insertAdjacentHTML('beforeend', modelHtml);
        chatContainer.scrollTop = chatContainer.scrollHeight;
      } catch (error) {
        document.getElementById(loaderId).remove();
        chatContainer.insertAdjacentHTML('beforeend', '<div class="text-red-400 font-mono text-xs">Erreur réseau critique.</div>');
      } finally {
        submitBtn.disabled = false;
        promptInput.focus();
      }
    }
  </script>
</body>
</html>
