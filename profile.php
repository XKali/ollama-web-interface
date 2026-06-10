<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require 'db.php';

$error = '';
$success = '';

$user_id = $_SESSION['user_id'];

// Get current user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_profile') {
            $new_username = mb_substr(trim($_POST['username'] ?? ''), 0, 50);
            $new_password = $_POST['password'] ?? '';
            $confirm_password = $_POST['password_confirm'] ?? '';

            if (empty($new_username)) {
                $error = "Le pseudo ne peut pas être vide.";
            } else {
                // Check if username is taken by someone else
                $st = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $st->execute([$new_username, $user_id]);
                if ($st->fetch()) {
                    $error = "Ce nom d'utilisateur est déjà pris.";
                } elseif (!empty($new_password) && $new_password !== $confirm_password) {
                    $error = "Les mots de passe ne correspondent pas.";
                } else {
                    $old_username = $currentUser['username'];
                    $avatar_url = $currentUser['avatar'] ?? null;
                    
                    if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
                        $ext = pathinfo($_FILES['avatar_file']['name'], PATHINFO_EXTENSION);
                        if (!is_dir('uploads')) mkdir('uploads', 0777, true);
                        $filename = 'uploads/avatar_' . $user_id . '_' . time() . '.' . $ext;
                        move_uploaded_file($_FILES['avatar_file']['tmp_name'], $filename);
                        $avatar_url = $filename;
                    }

                    $update_query = "UPDATE users SET username = ?, avatar = ?";
                    $params = [$new_username, $avatar_url];

                    if (!empty($new_password)) {
                        $update_query .= ", password = ?";
                        $params[] = password_hash($new_password, PASSWORD_DEFAULT);
                    }
                    $update_query .= " WHERE id = ?";
                    $params[] = $user_id;

                    $pdo->prepare($update_query)->execute($params);

                    // UPDATE LOGS for username sync
                    if ($new_username !== $old_username) {
                        $pdo->prepare("UPDATE logs_ia SET username = ? WHERE username = ?")->execute([$new_username, $old_username]);
                        $pdo->prepare("UPDATE logs_login SET username = ? WHERE username = ?")->execute([$new_username, $old_username]);
                    }

                    $_SESSION['username'] = $new_username;
                    $success = "Profil mis à jour avec succès.";
                    
                    // refresh current user data
                    $stmt->execute([$user_id]);
                    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }
        } elseif ($_POST['action'] === 'delete_account') {
             // Admin should not delete themselves if they are the only admin
             if ($currentUser['role'] === 'admin') {
                 $st = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
                 $adminCount = $st->fetchColumn();
                 if ($adminCount <= 1) {
                     $error = "Vous êtes le seul administrateur. Impossible de supprimer ce compte.";
                 } else {
                     $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
                     $pdo->prepare("DELETE FROM logs_ia WHERE username = ?")->execute([$currentUser['username']]);
                     header('Location: logout.php');
                     exit;
                 }
             } else {
                 $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
                 $pdo->prepare("DELETE FROM logs_ia WHERE username = ?")->execute([$currentUser['username']]);
                 header('Location: logout.php');
                 exit;
             }
        }
    }
}
?>
<!doctype html>
<html style="height: 100%; margin: 0;">
<head>
  <meta charset="UTF-8" />
  <title>Profil - <?= htmlspecialchars($ai_name) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />
</head>
<body class="min-h-screen bg-[#050505] text-[#d1d5db] font-sans relative overflow-x-hidden p-6 md:p-12">
  <div class="absolute top-[-20%] left-[-10%] w-[600px] h-[600px] bg-pink-500/10 rounded-full blur-[120px] pointer-events-none"></div>

  <div class="max-w-2xl mx-auto relative z-10">
    <div class="flex items-center justify-between mb-10">
       <div>
         <h1 class="text-3xl font-medium text-white tracking-tight flex items-center gap-3">
            <span class="material-symbols-outlined text-pink-400 text-4xl">person</span> Profil Utilisateur
         </h1>
         <p class="text-white/40 text-[10px] uppercase tracking-widest font-mono mt-1">Données Personnelles & Sécurité</p>
       </div>
       <a href="index.php" class="w-10 h-10 rounded-full bg-white/5 border border-white/10 flex items-center justify-center hover:bg-white/10 transition text-white/50 hover:text-white">
          <span class="material-symbols-outlined text-[20px]">close</span>
       </a>
    </div>

    <?php if ($error): ?>
      <div class="mb-6 p-4 bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl text-sm font-mono">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="mb-6 p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-xl text-sm font-mono">
        <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>

    <div class="bg-black/40 backdrop-blur-xl border border-white/10 rounded-2xl p-8 mb-8 shadow-2xl">
      <form method="POST" enctype="multipart/form-data" class="space-y-6">
        <input type="hidden" name="action" value="update_profile" />
        
        <div class="flex items-center gap-6 mb-8">
            <div class="w-20 h-20 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center overflow-hidden shrink-0">
               <?php if(!empty($currentUser['avatar'])): ?>
                   <img src="<?= htmlspecialchars($currentUser['avatar']) ?>" class="w-full h-full object-cover" />
               <?php else: ?>
                   <span class="material-symbols-outlined text-4xl text-white/20">face</span>
               <?php endif; ?>
            </div>
            <div class="flex-1">
                <label class="block text-[10px] font-mono tracking-widest uppercase text-white/40 mb-2">Fichier Avatar</label>
                <input type="file" name="avatar_file" accept="image/*" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 focus:border-pink-500/50 outline-none focus:ring-1 focus:ring-pink-500/20 text-white font-mono text-sm text-white/50" />
            </div>
        </div>

        <div>
          <label class="block text-[10px] font-mono tracking-widest uppercase text-white/40 mb-2">Pseudo</label>
          <input type="text" name="username" value="<?= htmlspecialchars($currentUser['username']) ?>" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 focus:border-pink-500/50 outline-none focus:ring-1 focus:ring-pink-500/20 text-white font-mono text-sm" required />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-[10px] font-mono tracking-widest uppercase text-white/40 mb-2">Nouveau Mot de Passe</label>
              <input type="password" name="password" placeholder="Laisser vide pour ne pas modifier" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 focus:border-pink-500/50 outline-none focus:ring-1 focus:ring-pink-500/20 text-white font-mono text-sm" />
            </div>
            <div>
              <label class="block text-[10px] font-mono tracking-widest uppercase text-white/40 mb-2">Confirmer Mot de Passe</label>
              <input type="password" name="password_confirm" placeholder="Confirmer" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 focus:border-pink-500/50 outline-none focus:ring-1 focus:ring-pink-500/20 text-white font-mono text-sm" />
            </div>
        </div>

        <div class="pt-4 border-t border-white/5 mt-6">
           <button type="submit" class="bg-white text-black font-bold uppercase tracking-widest text-xs px-6 py-3 rounded-xl hover:bg-pink-400 transition">
             Enregistrer
           </button>
        </div>
      </form>
    </div>

    <div class="bg-red-500/5 border border-red-500/20 rounded-2xl p-8">
      <h3 class="text-red-400 font-mono uppercase tracking-widest text-xs mb-2">Zone Dangereuse</h3>
      <p class="text-white/40 text-sm mb-6">La suppression de votre compte effacera toutes vos discussions et données associées de manière irréversible.</p>
      
      <form method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer définitivement votre compte ?');">
        <input type="hidden" nameaction" value="delete_account" />
        <input type="hidden" name="action" value="delete_account" />
        <button type="submit" class="bg-red-500/10 text-red-500 border border-red-500/30 hover:bg-red-500 hover:text-white px-6 py-3 rounded-xl transition text-xs font-bold uppercase tracking-widest">
           Supprimer le compte
        </button>
      </form>
    </div>

  </div>
</body>
</html>
