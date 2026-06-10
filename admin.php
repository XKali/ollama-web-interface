<?php
session_start();
require 'db.php';

// Protection Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_user') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $confirm = $_POST['password_confirm'] ?? '';
        
        if ($password !== $confirm) {
            $_SESSION['admin_error'] = "Les mots de passe ne correspondent pas (Création).";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $role = $_POST['role'] === 'admin' ? 'admin' : 'user';
            
            $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->execute([$username, $hashed, $role]);
            $_SESSION['admin_success'] = "Utilisateur ajouté.";
        }
        header('Location: admin.php');
        exit;
    }
    
   if ($_POST['action'] === 'edit_user') {
        $id = (int)$_POST['id'];
        $username = trim($_POST['username']);
        $role = $_POST['role'] === 'admin' ? 'admin' : 'user';
        
        // Determine if username changed
        $st = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $st->execute([$id]);
        $old_username = $st->fetchColumn();

        $confirm = $_POST['password_confirm'] ?? '';

        if (!empty($_POST['password']) && $_POST['password'] !== $confirm) {
             $_SESSION['admin_error'] = "Les mots de passe ne correspondent pas (Édition).";
        } else {
            $update_query = "UPDATE users SET username = ?, role = ? WHERE id = ?";
            $params = [$username, $role, $id];

            if (!empty($_POST['password'])) {
                $update_query = "UPDATE users SET username = ?, role = ?, password = ? WHERE id = ?";
                $params = [$username, $role, password_hash($_POST['password'], PASSWORD_DEFAULT), $id];
            }
            
            // Prevent lowering core admin role
            if ($id === 1) {
                $role = 'admin';
                if (!empty($_POST['password'])) {
                    $update_query = "UPDATE users SET username = ?, password = ? WHERE id = ?";
                    $params = [$username, password_hash($_POST['password'], PASSWORD_DEFAULT), $id];
                } else {
                    $update_query = "UPDATE users SET username = ? WHERE id = ?";
                    $params = [$username, $id];
                }
            }
            
            $pdo->prepare($update_query)->execute($params);

            if ($old_username && $old_username !== $username) {
                $pdo->prepare("UPDATE logs_ia SET username = ? WHERE username = ?")->execute([$username, $old_username]);
                $pdo->prepare("UPDATE logs_login SET username = ? WHERE username = ?")->execute([$username, $old_username]);
            }
            $_SESSION['admin_success'] = "Utilisateur mis à jour.";
        }
        header('Location: admin.php');
        exit;
    }

    if ($_POST['action'] === 'delete_user') {
        $id = (int)$_POST['id'];
        if ($id !== 1 && $id !== $_SESSION['user_id']) { // Protect core admin and self
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['admin_success'] = "Utilisateur supprimé.";
        }
        header('Location: admin.php');
        exit;
    }

    if ($_POST['action'] === 'update_settings') {
        $new_ai_name = $_POST['ai_name'] ?? 'LABREGERE_AI';
        $new_ollama_url = rtrim($_POST['ollama_url'] ?? '', '/');
        $new_sd_url = rtrim($_POST['sd_url'] ?? '', '/');

        if (isset($_FILES['ai_logo_file']) && $_FILES['ai_logo_file']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['ai_logo_file']['name'], PATHINFO_EXTENSION);
            if (!is_dir('uploads')) mkdir('uploads', 0777, true);
            $filename = 'uploads/logo_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['ai_logo_file']['tmp_name'], $filename);
            
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('ai_logo', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$filename]);
        }

        $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('ai_name', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$new_ai_name]);
        $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('ollama_url', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$new_ollama_url]);
        $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('sd_url', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$new_sd_url]);

        $_SESSION['admin_success'] = "Configuration système mise à jour.";
        header('Location: admin.php');
        exit;
    }
}

$error = '';
$success = '';
if (isset($_SESSION['admin_error'])) { $error = $_SESSION['admin_error']; unset($_SESSION['admin_error']); }
if (isset($_SESSION['admin_success'])) { $success = $_SESSION['admin_success']; unset($_SESSION['admin_success']); }

$sd_url = $settings['sd_url'] ?? 'http://127.0.0.1:7860';

// Fetch users
$users = $pdo->query("SELECT id, username, role FROM users")->fetchAll(PDO::FETCH_ASSOC);

// Fetch logins
$logins = $pdo->query("SELECT * FROM logs_login ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

// Fetch queries
$queries = $pdo->query("SELECT * FROM logs_ia ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html style="height: 100%; margin: 0;">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($ai_name) ?> - Admin Console</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />
</head>
<body class="bg-[#050505] text-[#d1d5db] font-sans h-full flex flex-col overflow-hidden">
  
  <header class="h-16 border-b border-white/5 bg-black/40 backdrop-blur-xl flex items-center px-6 shrink-0 z-20">
    <div class="flex items-center gap-4">
      <h1 class="text-sm font-medium tracking-tight text-white flex items-center gap-2">
         <span class="material-symbols-outlined text-[18px] text-cyan-400">admin_panel_settings</span> <?= htmlspecialchars($ai_name) ?> Admin Console
      </h1>
    </div>
    <div class="ml-auto">
        <a href="index.php" class="text-xs text-white/50 hover:text-white px-4 py-2 border border-white/10 rounded-lg hover:bg-white/5 transition-colors">Retour au Workspace</a>
    </div>
  </header>

  <div class="flex-1 overflow-y-auto p-10 relative">
     <div class="absolute top-[-10%] right-[-10%] w-[400px] h-[400px] bg-red-500/5 rounded-full blur-[100px] pointer-events-none"></div>
     <div class="absolute bottom-[-10%] left-[-5%] w-[300px] h-[300px] bg-cyan-500/5 rounded-full blur-[100px] pointer-events-none"></div>

     <div class="max-w-7xl mx-auto space-y-10 relative z-10">

        <?php if ($error): ?>
          <div class="p-4 bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl text-sm font-mono shadow-lg">
            <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div class="p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-xl text-sm font-mono shadow-lg">
            <?= htmlspecialchars($success) ?>
          </div>
        <?php endif; ?>
        
        <!-- SECTION SYSTEM SETTINGS -->
        <section class="bg-black/40 border border-white/10 rounded-2xl p-6 backdrop-blur-sm shadow-2xl">
           <h2 class="text-xs font-mono uppercase tracking-widest text-pink-400 mb-6 flex items-center gap-2">
              <span class="material-symbols-outlined text-[16px]">settings</span> Configuration Système
           </h2>
           <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
              <input type="hidden" name="action" value="update_settings">
              <div>
                 <label class="block text-[10px] text-white/40 uppercase mb-1 font-mono">Nom IA</label>
                 <input type="text" name="ai_name" value="<?= htmlspecialchars($ai_name) ?>" class="w-full bg-black/50 border border-white/10 rounded-lg px-4 py-3 text-sm focus:border-pink-500/50 outline-none text-white font-mono">
              </div>
              <div>
                 <label class="block text-[10px] text-white/40 uppercase mb-1 font-mono">Image Logo (optionnel)</label>
                 <input type="file" name="ai_logo_file" accept="image/*" class="w-full bg-black/50 border border-white/10 rounded-lg px-4 py-2 text-sm focus:border-pink-500/50 outline-none text-white font-mono text-white/60">
                 <?php if(!empty($ai_logo)): ?>
                   <p class="text-[9px] text-emerald-500 mt-1 uppercase tracking-wider font-bold truncate">✓ Actif: <?= htmlspecialchars(basename($ai_logo)) ?></p>
                 <?php endif; ?>
              </div>
              <div>
                 <label class="block text-[10px] text-white/40 uppercase mb-1 font-mono">URL Serveur Ollama</label>
                 <input type="text" name="ollama_url" value="<?= htmlspecialchars($ollama_url) ?>" placeholder="http://127.0.0.1:11434" class="w-full bg-black/50 border border-white/10 rounded-lg px-4 py-3 text-sm focus:border-pink-500/50 outline-none text-white font-mono">
              </div>
              <div>
                 <label class="block text-[10px] text-white/40 uppercase mb-1 font-mono">URL Image Server (SD)</label>
                 <input type="text" name="sd_url" value="<?= htmlspecialchars($sd_url) ?>" placeholder="http://127.0.0.1:7860" class="w-full bg-black/50 border border-white/10 rounded-lg px-4 py-3 text-sm focus:border-pink-500/50 outline-none text-white font-mono">
              </div>
              <div class="md:col-span-2 lg:col-span-4 flex justify-end">
                 <button type="submit" class="bg-white text-black font-bold text-xs uppercase tracking-widest rounded-lg px-8 py-3 hover:bg-pink-400 transition-colors">
                    Sauvegarder Configuration
                 </button>
              </div>
           </form>
        </section>

         <!-- SECTION UTILISATEURS -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-8">
           <!-- Formulaire Ajout / Edition -->
           <div class="bg-white/5 border border-white/10 rounded-2xl p-6 backdrop-blur-sm h-min">
              <?php
                 $editUser = null;
                 if (isset($_GET['edit_id'])) {
                     $st = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                     $st->execute([$_GET['edit_id']]);
                     $editUser = $st->fetch(PDO::FETCH_ASSOC);
                 }
              ?>
              
              <?php if($editUser): ?>
              <h2 class="text-xs font-mono uppercase tracking-widest text-emerald-400 mb-6 flex items-center justify-between">
                  Édition Accès 
                  <a href="admin.php" class="text-[10px] text-white/40 hover:text-white">Annuler</a>
              </h2>
              <form method="POST" class="space-y-4">
                 <input type="hidden" name="action" value="edit_user">
                 <input type="hidden" name="id" value="<?= $editUser['id'] ?>">
                 <div>
                    <label class="block text-[10px] text-white/40 uppercase mb-1 font-mono">Username</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($editUser['username']) ?>" class="w-full bg-black/50 border border-white/10 rounded-lg px-3 py-2 text-sm focus:border-emerald-500/50 outline-none" required>
                 </div>
                 <div>
                    <label class="block text-[10px] text-white/40 uppercase mb-1 font-mono">Nouveau Password</label>
                    <input type="password" name="password" placeholder="Vide: ne pas modifier" class="w-full bg-black/50 border border-white/10 rounded-lg px-3 py-2 text-sm focus:border-emerald-500/50 outline-none">
                 </div>
                 <div>
                    <label class="block text-[10px] text-white/40 uppercase mb-1 font-mono">Confirmer Password</label>
                    <input type="password" name="password_confirm" placeholder="Confirmer" class="w-full bg-black/50 border border-white/10 rounded-lg px-3 py-2 text-sm focus:border-emerald-500/50 outline-none">
                 </div>
                 <div>
                    <label class="block text-[10px] text-white/40 uppercase mb-1 font-mono">Role</label>
                    <select name="role" class="w-full bg-black/50 border border-white/10 rounded-lg px-3 py-2 text-sm focus:border-emerald-500/50 outline-none text-white">
                        <option value="user" <?= $editUser['role'] === 'user' ? 'selected' : '' ?>>USER (Opérateur)</option>
                        <option value="admin" <?= $editUser['role'] === 'admin' ? 'selected' : '' ?>>ADMIN (Accès total)</option>
                    </select>
                 </div>
                 <button type="submit" class="w-full mt-4 bg-emerald-500 text-black font-bold text-xs uppercase tracking-widest rounded-lg py-3 hover:bg-emerald-400 transition-colors">
                    Modifier
                 </button>
              </form>

              <?php else: ?>
              <h2 class="text-xs font-mono uppercase tracking-widest text-cyan-400 mb-6">Nouvel Accès</h2>
              <form method="POST" class="space-y-4">
                 <input type="hidden" name="action" value="add_user">
                 <div>
                    <label class="block text-[10px] text-white/40 uppercase mb-1 font-mono">Username</label>
                    <input type="text" name="username" class="w-full bg-black/50 border border-white/10 rounded-lg px-3 py-2 text-sm focus:border-cyan-500/50 outline-none" required>
                 </div>
                 <div>
                     <label class="block text-[10px] text-white/40 uppercase mb-1 font-mono">Password</label>
                     <input type="password" name="password" class="w-full bg-black/50 border border-white/10 rounded-lg px-3 py-2 text-sm focus:border-cyan-500/50 outline-none" required>
                 </div>
                 <div>
                     <label class="block text-[10px] text-white/40 uppercase mb-1 font-mono">Confirmer Password</label>
                     <input type="password" name="password_confirm" class="w-full bg-black/50 border border-white/10 rounded-lg px-3 py-2 text-sm focus:border-cyan-500/50 outline-none" required>
                 </div>
                 <div>
                    <label class="block text-[10px] text-white/40 uppercase mb-1 font-mono">Role</label>
                    <select name="role" class="w-full bg-black/50 border border-white/10 rounded-lg px-3 py-2 text-sm focus:border-cyan-500/50 outline-none text-white">
                        <option value="user">USER (Opérateur classique)</option>
                        <option value="admin">ADMIN (Accès total)</option>
                    </select>
                 </div>
                 <button type="submit" class="w-full mt-4 bg-white text-black font-bold text-xs uppercase tracking-widest rounded-lg py-3 hover:bg-cyan-400 transition-colors">
                    Autoriser
                 </button>
              </form>
              <?php endif; ?>
           </div>
           
           <!-- Liste Utilisateurs -->
           <div class="bg-white/5 border border-white/10 rounded-2xl p-6 backdrop-blur-sm lg:col-span-2">
              <h2 class="text-xs font-mono uppercase tracking-widest text-white/40 mb-6">Registre des utilisateurs</h2>
              <div class="overflow-x-auto">
                 <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead>
                       <tr class="text-[10px] text-white/30 uppercase font-mono border-b border-white/10">
                          <th class="pb-3 px-4 font-normal">ID</th>
                          <th class="pb-3 px-4 font-normal">Username</th>
                          <th class="pb-3 px-4 font-normal">Role / Clearance</th>
                          <th class="pb-3 px-4 font-normal text-right">Action</th>
                       </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                       <?php foreach($users as $u): ?>
                       <tr class="hover:bg-white/[0.02]">
                          <td class="py-3 px-4 text-white/40 font-mono text-xs">#<?= $u['id'] ?></td>
                          <td class="py-3 px-4 font-medium text-white"><?= htmlspecialchars($u['username']) ?></td>
                          <td class="py-3 px-4">
                             <?php if($u['role'] === 'admin'): ?>
                                <span class="bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 px-2 py-1 rounded text-[10px] uppercase tracking-widest">Admin</span>
                             <?php else: ?>
                                <span class="bg-white/5 text-white/50 border border-white/10 px-2 py-1 rounded text-[10px] uppercase tracking-widest">User</span>
                             <?php endif; ?>
                          </td>
                          <td class="py-3 px-4 text-right flex justify-end gap-2">
                             <a href="?edit_id=<?= $u['id'] ?>" class="text-emerald-400/50 hover:text-emerald-400 p-1">
                                 <span class="material-symbols-outlined text-[16px]">edit</span>
                             </a>
                             <?php if($u['id'] != 1 && $u['id'] != $_SESSION['user_id']): ?>
                             <form method="POST" class="inline" onsubmit="return confirm('Révoquer cet accès définitivement ?')">
                                 <input type="hidden" name="action" value="delete_user">
                                 <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                 <button type="submit" class="text-red-400/50 hover:text-red-400 p-1"><span class="material-symbols-outlined text-[16px]">delete</span></button>
                             </form>
                             <?php endif; ?>
                          </td>
                       </tr>
                       <?php endforeach; ?>
                    </tbody>
                 </table>
              </div>
           </div>
        </section>

        <!-- SECTION LOGS -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-8">
           <!-- Logs Connexions -->
           <div class="bg-white/5 border border-white/10 rounded-2xl p-6 backdrop-blur-sm h-96 flex flex-col">
              <h2 class="text-xs font-mono uppercase tracking-widest text-emerald-500 mb-4 shrink-0 flex items-center justify-between">
                  Logs d'Authentification <span class="text-white/20 text-[10px]">MariaDB Table: logs_login</span>
              </h2>
              <div class="overflow-y-auto flex-1 text-xs">
                 <table class="w-full text-left font-mono whitespace-nowrap">
                    <tbody class="divide-y divide-white/5">
                       <?php foreach($logins as $l): ?>
                       <tr class="hover:bg-white/[0.02]">
                          <td class="py-2 text-white/30"><?= date('H:i:s', strtotime($l['created_at'])) ?></td>
                          <td class="py-2 px-2 text-white/60"><?= htmlspecialchars($l['username'] ?: 'N/A') ?></td>
                          <td class="py-2 px-2 text-white/40"><?= htmlspecialchars($l['ip_address']) ?></td>
                          <td class="py-2 text-right">
                             <?php if($l['status'] === 'success'): ?>
                                <span class="text-emerald-400">SUCCESS</span>
                             <?php else: ?>
                                <span class="text-red-400">FAILED</span>
                             <?php endif; ?>
                          </td>
                       </tr>
                       <?php endforeach; ?>
                    </tbody>
                 </table>
              </div>
           </div>

           <!-- Logs Modeles IA -->
           <div class="bg-white/5 border border-white/10 rounded-2xl p-6 backdrop-blur-sm h-96 flex flex-col">
              <h2 class="text-xs font-mono uppercase tracking-widest text-indigo-400 mb-4 shrink-0 flex items-center justify-between">
                  Télémétrie Modèles IA <span class="text-white/20 text-[10px]">MariaDB Table: logs_ia</span>
              </h2>
              <div class="overflow-y-auto flex-1 text-xs">
                 <table class="w-full text-left whitespace-nowrap">
                    <tbody class="divide-y divide-white/5">
                       <?php foreach($queries as $q): ?>
                       <tr class="hover:bg-white/[0.02] group">
                          <td class="py-2 font-mono text-white/30 text-[10px]"><?= date('d/m H:i', strtotime($q['created_at'])) ?></td>
                          <td class="py-2 px-3">
                             <div class="flex items-center gap-2">
                                <span class="text-white font-medium"><?= htmlspecialchars($q['username'] ?: 'system') ?></span>
                                <span class="text-[9px] uppercase font-mono px-1.5 py-0.5 rounded border border-white/10 text-white/40"><?= htmlspecialchars($q['model']) ?></span>
                                <?php if($q['media_type'] !== 'text'): ?>
                                   <span class="text-[9px] uppercase font-mono px-1.5 py-0.5 rounded border border-indigo-400/30 text-indigo-400 bg-indigo-400/10"><?= htmlspecialchars($q['media_type']) ?></span>
                                <?php endif; ?>
                             </div>
                             <p class="text-white/50 text-xs mt-1 truncate max-w-xs block"><?= htmlspecialchars($q['prompt'] ?: 'Requête média pure') ?></p>
                          </td>
                       </tr>
                       <?php endforeach; ?>
                    </tbody>
                 </table>
              </div>
           </div>
        </section>

     </div>
  </div>
</body>
</html>
