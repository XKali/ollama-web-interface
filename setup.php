<?php
session_start();
if (file_exists(__DIR__ . '/config.php')) {
    header('Location: index.php');
    exit;
}

$error = '';

if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'test_ollama') {
    header('Content-Type: application/json');
    $url = rtrim($_POST['ollama_url'] ?? '', '/');
    if(empty($url)) {
        echo json_encode(['status' => 'error', 'message' => 'L\'URL est vide.']);
        exit;
    }
    
    $ch = curl_init($url . '/api/tags');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($resp && $code == 200) {
        echo json_encode(['status' => 'success', 'message' => 'Connexion Ollama OK (Code 200)']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erreur de connexion (Code HTTP: ' . $code . ')']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_action'])) {
    $db_host = $_POST['db_host'] ?? 'localhost';
    $db_name = $_POST['db_name'] ?? 'localhost';
    $db_user = $_POST['db_user'] ?? 'root';
    $db_pass = $_POST['db_pass'] ?? '';
    
    $admin_user = trim($_POST['admin_user'] ?? '');
    $admin_mail = trim($_POST['admin_mail'] ?? '');
    $admin_pass = $_POST['admin_pass'] ?? '';
    $admin_pass_confirm = $_POST['admin_pass_confirm'] ?? '';
    
    $ollama_url = rtrim($_POST['ollama_url'] ?? 'http://127.0.0.1:11434', '/');
    $sd_url = rtrim($_POST['sd_url'] ?? 'http://127.0.0.1:7860', '/');

    if ($admin_pass !== $admin_pass_confirm) {
        $error = "Les mots de passe administrateur ne correspondent pas.";
    } elseif (empty($admin_user) || empty($admin_pass)) {
        $error = "Le nom d'utilisateur et mot de passe administrateur sont requis.";
    } else {
        try {
            $pdo = new PDO("mysql:host=$db_host;charset=utf8", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name`");
            $pdo->exec("USE `$db_name`");
            
            $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
                setting_key VARCHAR(50) PRIMARY KEY,
                setting_value TEXT
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(100),
                password VARCHAR(255) NOT NULL,
                role VARCHAR(20) DEFAULT 'user',
                avatar TEXT
            )");
            
            $pdo->exec("CREATE TABLE IF NOT EXISTS logs_ia (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50),
                model VARCHAR(100),
                prompt TEXT,
                response TEXT,
                media_type VARCHAR(20) DEFAULT 'text',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS logs_login (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50),
                status VARCHAR(20),
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            $ai_name = $_POST['ai_name'] ?? 'Company Name';
            $ai_logo = '';

            if (isset($_FILES['ai_logo_file']) && $_FILES['ai_logo_file']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['ai_logo_file']['name'], PATHINFO_EXTENSION);
                if (!is_dir('uploads')) mkdir('uploads', 0777, true);
                $filename = 'uploads/logo_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['ai_logo_file']['tmp_name'], $filename);
                $ai_logo = $filename;
            }

            $stmtSetting = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
            $stmtSetting->execute(['ai_name', $ai_name]);
            $stmtSetting->execute(['ai_logo', $ai_logo]);
            $stmtSetting->execute(['ollama_url', $ollama_url]);
            $stmtSetting->execute(['sd_url', $sd_url]);

            $hashed = password_hash($admin_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')");
            $stmt->execute([$admin_user, $admin_mail, $hashed]);
            
            $config_content = "<?php\n";
            $config_content .= "\$db_host = '" . addslashes($db_host) . "';\n";
            $config_content .= "\$db_name = '" . addslashes($db_name) . "';\n";
            $config_content .= "\$db_user = '" . addslashes($db_user) . "';\n";
            $config_content .= "\$db_pass = '" . addslashes($db_pass) . "';\n";
            $config_content .= "?>";
            
            if (file_put_contents(__DIR__ . '/config.php', $config_content)) {
                header("Location: login.php");
                exit;
            } else {
                $error = "Impossible d'écrire le fichier config.php. Vérifiez les droits du dossier.";
            }
            
        } catch(PDOException $e) {
            $error = "Erreur MariaDB : " . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html style="height: 100%; margin: 0;">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Company Name - System Setup</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />
</head>
<body class="min-h-screen p-6 md:p-12 bg-[#050505] text-[#d1d5db] font-sans relative overflow-x-hidden overflow-y-auto">
  <div class="absolute top-[-20%] left-[-10%] w-[600px] h-[600px] bg-cyan-500/10 rounded-full blur-[120px] pointer-events-none"></div>
  <div class="absolute bottom-[-20%] right-[-10%] w-[600px] h-[600px] bg-indigo-500/10 rounded-full blur-[120px] pointer-events-none"></div>

  <div class="w-full max-w-4xl mx-auto relative z-10 py-10">
    <div class="mb-12">
      <div class="inline-flex items-center gap-3 mb-6">
        <div class="w-3 h-3 rounded-full bg-cyan-500 shadow-[0_0_10px_rgba(6,182,212,0.5)]"></div>
        <span class="font-mono text-[10px] tracking-widest text-cyan-500 uppercase">Initialization Protocol</span>
      </div>
      <h1 class="text-4xl font-medium mb-2 text-white tracking-tight leading-loose">Company Name Setup</h1>
      <p class="text-white/40 text-xs font-mono tracking-widest uppercase">First-run configuration</p>
    </div>

    <form method="POST" enctype="multipart/form-data" class="space-y-8 animate-fade-in">
      <?php if ($error): ?>
        <div class="p-4 bg-red-500/10 text-red-400 border border-red-500/20 rounded-xl text-xs font-mono">
          [ERROR] <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        
        <!-- SECTION IA SYSTEM CONFIG -->
        <div class="bg-black/40 backdrop-blur-xl p-8 rounded-2xl border border-white/10 shadow-2xl md:col-span-2">
           <h2 class="text-xs font-mono uppercase tracking-widest text-pink-400 mb-6 flex items-center gap-2"><span class="material-symbols-outlined text-[16px]">psychology</span> Nom & Identité IA</h2>
           <div class="space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-[10px] uppercase mb-1 text-white/40 font-mono">Nom de l'IA (Ex: COMPANY_AI)</label>
                    <input type="text" name="ai_name" value="COMPANY_AI" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm focus:border-pink-500/50 outline-none focus:ring-1 focus:ring-pink-500/20 text-white font-mono" required />
                  </div>
                  <div>
                    <label class="block text-[10px] uppercase mb-1 text-white/40 font-mono">Image Logo (Optionnel)</label>
                    <input type="file" name="ai_logo_file" accept="image/*" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm focus:border-pink-500/50 outline-none focus:ring-1 focus:ring-pink-500/20 text-white font-mono text-white/60" />
                  </div>
              </div>
           </div>
        </div>

        <!-- SECTION DATABASE -->
        <div class="bg-black/40 backdrop-blur-xl p-8 rounded-2xl border border-white/10 shadow-2xl">
           <h2 class="text-xs font-mono uppercase tracking-widest text-cyan-400 mb-6 flex items-center gap-2"><span class="material-symbols-outlined text-[16px]">database</span> MariaDB SQL Server</h2>
           <div class="space-y-4">
              <div>
                <label class="block text-[10px] uppercase mb-1 text-white/40 font-mono">Hôte M.A.S</label>
                <input type="text" name="db_host" value="localhost" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm focus:border-cyan-500/50 outline-none focus:ring-1 focus:ring-cyan-500/20 text-white font-mono" required />
              </div>
              <div>
                <label class="block text-[10px] uppercase mb-1 text-white/40 font-mono">Nom Base de Données</label>
                <input type="text" name="db_name" value="company_ai" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm focus:border-cyan-500/50 outline-none focus:ring-1 focus:ring-cyan-500/20 text-white font-mono" required />
              </div>
              <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-[10px] uppercase mb-1 text-white/40 font-mono">Utilisateur</label>
                    <input type="text" name="db_user" value="root" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm focus:border-cyan-500/50 outline-none focus:ring-1 focus:ring-cyan-500/20 text-white font-mono" required />
                  </div>
                  <div>
                    <label class="block text-[10px] uppercase mb-1 text-white/40 font-mono">Mot de Passe</label>
                    <input type="password" name="db_pass" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm focus:border-cyan-500/50 outline-none focus:ring-1 focus:ring-cyan-500/20 text-white font-mono" />
                  </div>
              </div>
           </div>
        </div>

        <!-- SECTION ADMIN -->
        <div class="bg-black/40 backdrop-blur-xl p-8 rounded-2xl border border-white/10 shadow-2xl">
           <h2 class="text-xs font-mono uppercase tracking-widest text-emerald-400 mb-6 flex items-center gap-2"><span class="material-symbols-outlined text-[16px]">admin_panel_settings</span> Compte Administrateur</h2>
           <div class="space-y-4">
              <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-[10px] uppercase mb-1 text-white/40 font-mono">Username</label>
                    <input type="text" name="admin_user" placeholder="admin" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm focus:border-emerald-500/50 outline-none focus:ring-1 focus:ring-emerald-500/20 text-white font-mono" required />
                  </div>
                  <div>
                    <label class="block text-[10px] uppercase mb-1 text-white/40 font-mono">Email</label>
                    <input type="email" name="admin_mail" placeholder="admin@company.ai" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm focus:border-emerald-500/50 outline-none focus:ring-1 focus:ring-emerald-500/20 text-white font-mono" required />
                  </div>
              </div>
              <div>
                <label class="block text-[10px] uppercase mb-1 text-white/40 font-mono">Mot de Passe</label>
                <input type="password" name="admin_pass" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm focus:border-emerald-500/50 outline-none focus:ring-1 focus:ring-emerald-500/20 text-white font-mono" required />
              </div>
              <div>
                <label class="block text-[10px] uppercase mb-1 text-white/40 font-mono">Confirmer Mot de Passe</label>
                <input type="password" name="admin_pass_confirm" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm focus:border-emerald-500/50 outline-none focus:ring-1 focus:ring-emerald-500/20 text-white font-mono" required />
              </div>
           </div>
        </div>

      </div>

      <!-- SECTION OLLAMA & SD -->
      <div class="bg-black/40 backdrop-blur-xl p-8 rounded-2xl border border-white/10 shadow-2xl mt-8">
         <h2 class="text-xs font-mono uppercase tracking-widest text-indigo-400 mb-6 flex items-center gap-2"><span class="material-symbols-outlined text-[16px]">router</span> Noeuds IA (LLM & Images)</h2>
         <div class="space-y-6">
             <div class="flex flex-col sm:flex-row items-end gap-4">
                <div class="flex-1 w-full">
                  <label class="block text-[10px] uppercase mb-1 text-white/40 font-mono">URL Serveur Ollama (LLM)</label>
                  <input type="text" id="ollama_url" name="ollama_url" value="http://127.0.0.1:11434" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm focus:border-indigo-500/50 outline-none focus:ring-1 focus:ring-indigo-500/20 text-white font-mono" required />
                </div>
                <button type="button" onclick="testOllama()" class="h-[46px] px-6 bg-white/5 border border-white/10 text-white rounded-xl hover:bg-white/10 transition-colors font-mono text-xs uppercase flex items-center gap-2 w-full sm:w-auto justify-center">
                  Tester IP
                </button>
             </div>
             <div id="ollama_status" class="text-[10px] font-mono h-4 -mt-2"></div>

             <div class="flex-1 w-full">
                <label class="block text-[10px] uppercase mb-1 text-white/40 font-mono">URL Image Server (Stable Diffusion)</label>
                <input type="text" id="sd_url" name="sd_url" value="http://127.0.0.1:7860" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm focus:border-indigo-500/50 outline-none focus:ring-1 focus:ring-indigo-500/20 text-white font-mono" required />
             </div>
         </div>
      </div>

      <div class="pt-8">
        <button type="submit" class="w-full h-14 bg-white text-black font-bold text-xs uppercase tracking-widest rounded-xl hover:bg-cyan-400 transition-colors">
          Initialiser le système
        </button>
      </div>

    </form>
  </div>

  <script>
    async function testOllama() {
      const url = document.getElementById('ollama_url').value;
      const statusEl = document.getElementById('ollama_status');
      statusEl.innerHTML = '<span class="text-indigo-400 animate-pulse">Contacting Node...</span>';
      
      const fd = new FormData();
      fd.append('ajax_action', 'test_ollama');
      fd.append('ollama_url', url);
      
      try {
        const res = await fetch('setup.php', { method: 'POST', body: fd });
        const data = await res.json();
        if(data.status === 'success') {
          statusEl.innerHTML = '<span class="text-emerald-400 flex items-center gap-1"><span class="material-symbols-outlined text-[12px]">check_circle</span> ' + data.message + '</span>';
        } else {
          statusEl.innerHTML = '<span class="text-red-400 flex items-center gap-1"><span class="material-symbols-outlined text-[12px]">error</span> ' + data.message + '</span>';
        }
      } catch(e) {
        statusEl.innerHTML = '<span class="text-red-400">Erreur réseau. Le domaine est-il joignable ?</span>';
      }
    }
  </script>
</body>
</html>
