<?php
session_start();
require 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$user]);
    $row = $stmt->fetch();

    // Supporte le texte clair pour l'admin par défaut, sinon password_verify pour les hashs bcrypt
    if ($row && ($pass === $row['password'] || password_verify($pass, $row['password']))) {
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['role'] = $row['role'];
        
        $pdo->prepare("INSERT INTO logs_login (username, status, ip_address) VALUES (?, 'success', ?)")->execute([$user, $ip]);
        
        header('Location: index.php');
        exit;
    } else {
        $pdo->prepare("INSERT INTO logs_login (username, status, ip_address) VALUES (?, 'failed', ?)")->execute([$user, $ip]);
        $error = "Identification échouée. Accès refusé ou utilisateur manquant.";
    }
}
?>
<!doctype html>
<html style="height: 100%; margin: 0;">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($ai_name) ?> - Secure Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />
</head>
<body class="min-h-screen flex items-center justify-center p-6 bg-[#050505] text-[#d1d5db] font-sans relative overflow-hidden">
  <div class="absolute top-[-20%] left-[-10%] w-[600px] h-[600px] bg-cyan-500/10 rounded-full blur-[120px] pointer-events-none"></div>
  <div class="absolute bottom-[-20%] right-[-10%] w-[600px] h-[600px] bg-indigo-500/10 rounded-full blur-[120px] pointer-events-none"></div>

  <div class="w-full max-w-md relative z-10">
    <div class="text-center mb-12">
      <div class="inline-flex items-center gap-3 mb-6">
        <div class="w-3 h-3 rounded-full bg-cyan-500 shadow-[0_0_10px_rgba(6,182,212,0.5)]"></div>
        <span class="font-mono text-[10px] tracking-widest text-cyan-500 uppercase">System Ready</span>
      </div>
      <?php if (!empty($ai_logo)): ?>
          <img src="<?= htmlspecialchars($ai_logo) ?>" alt="Logo" class="max-h-24 mx-auto mb-4 object-contain shadow-2xl rounded-xl">
      <?php else: ?>
          <h1 class="text-4xl font-medium mb-2 text-white tracking-tight leading-tight"><?= htmlspecialchars($ai_name) ?></h1>
      <?php endif; ?>
      <p class="text-white/40 text-xs font-mono tracking-widest uppercase">Secured Access Layer</p>
    </div>

    <form method="POST" class="space-y-8 bg-black/40 backdrop-blur-xl p-10 rounded-2xl border border-white/10 relative shadow-2xl">
      <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-cyan-500 to-indigo-500 rounded-t-2xl"></div>
      
      <?php if ($error): ?>
        <div class="p-3 bg-red-500/10 text-red-400 border border-red-500/20 rounded-lg text-sm font-mono">
          [ERROR] <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <div class="space-y-6">
        <div>
          <label class="block text-[10px] font-mono tracking-widest uppercase mb-2 text-white/40">Identification</label>
          <input type="text" name="username" value="admin" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 focus:outline-none focus:border-cyan-500/50 focus:ring-1 focus:ring-cyan-500/20 transition-all text-white font-mono placeholder:text-white/20" required />
        </div>
        <div>
          <label class="block text-[10px] font-mono tracking-widest uppercase mb-2 text-white/40">Passkey</label>
          <input type="password" name="password" value="admin" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 focus:outline-none focus:border-cyan-500/50 focus:ring-1 focus:ring-cyan-500/20 transition-all text-white font-mono placeholder:text-white/20" required />
        </div>
      </div>

      <div class="pt-4 flex flex-col sm:flex-row items-center justify-between gap-6">
        <span class="text-[10px] font-mono text-emerald-500 flex items-center gap-2">
          MARIADB_ONLINE [OK]
        </span>
        <button type="submit" class="flex items-center gap-2 bg-white text-black px-6 py-3 rounded-xl hover:bg-cyan-400 transition-colors disabled:opacity-50 font-bold text-xs uppercase tracking-widest w-full sm:w-auto justify-center">
          <span>Authenticate</span>
        </button>
      </div>
    </form>
  </div>
</body>
</html>
