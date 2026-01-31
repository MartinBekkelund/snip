<?php
/**
 * SN/P URL Shortener - Installation Script
 */
session_start();

if (file_exists(__DIR__ . '/api/config.php')) {
    $c = file_get_contents(__DIR__ . '/api/config.php');
    if (strpos($c, 'DB_CONFIGURED') !== false) {
        die('<div style="font-family:system-ui;max-width:500px;margin:100px auto;padding:2rem;background:#1a1a24;color:#fff;border-radius:12px;text-align:center"><h2 style="color:#22c55e">✓ Already Installed</h2><p style="color:#a1a1aa">SN/P is configured. Delete install.php for security.</p><a href="index.html" style="display:inline-block;margin-top:1rem;padding:.75rem 1.5rem;background:#31394E;color:#fff;text-decoration:none;border-radius:8px">Go to SN/P</a></div>');
    }
}

$step = $_POST['step'] ?? $_GET['step'] ?? 1;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 2:
            $r = testDb($_POST);
            if ($r['success']) { $_SESSION['db'] = $_POST; $step = 3; }
            else { $errors[] = $r['error']; $step = 2; }
            break;
        case 3:
            $r = complete($_POST);
            if ($r['success']) { $step = 4; }
            else { $errors[] = $r['error']; $step = 3; }
            break;
    }
}

function testDb($d) {
    $h = trim($d['db_host'] ?? '');
    $n = trim($d['db_name'] ?? '');
    $u = trim($d['db_user'] ?? '');
    $p = $d['db_pass'] ?? '';
    if (!$h || !$n || !$u) return ['success' => false, 'error' => 'All fields except password are required'];
    try {
        $pdo = new PDO("mysql:host=$h;charset=utf8mb4", $u, $p, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$n` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$n`");
        
        // Create tables directly instead of parsing SQL file
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS urls (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                short_code VARCHAR(100) NOT NULL UNIQUE,
                original_url TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NULL,
                click_count INT UNSIGNED DEFAULT 0,
                is_active BOOLEAN DEFAULT TRUE,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                INDEX idx_short_code (short_code),
                INDEX idx_created_at (created_at),
                INDEX idx_is_active (is_active)
            ) ENGINE=InnoDB
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS click_stats (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                url_id INT UNSIGNED NOT NULL,
                clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                referer TEXT NULL,
                country_code VARCHAR(2) NULL,
                FOREIGN KEY (url_id) REFERENCES urls(id) ON DELETE CASCADE,
                INDEX idx_url_id (url_id),
                INDEX idx_clicked_at (clicked_at)
            ) ENGINE=InnoDB
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS api_keys (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                api_key VARCHAR(64) NOT NULL UNIQUE,
                name VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_used_at TIMESTAMP NULL,
                is_active BOOLEAN DEFAULT TRUE,
                rate_limit INT UNSIGNED DEFAULT 100,
                INDEX idx_api_key (api_key)
            ) ENGINE=InnoDB
        ");
        
        return ['success' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

function complete($d) {
    $db = $_SESSION['db'] ?? [];
    if (!$db) return ['success' => false, 'error' => 'Database info missing'];
    $url = rtrim(trim($d['base_url'] ?? ''), '/') . '/';
    $pass = $d['admin_pass'] ?? '';
    if (!$url) return ['success' => false, 'error' => 'Base URL required'];
    if (strlen($pass) < 6) return ['success' => false, 'error' => 'Password must be at least 6 characters'];
    if ($pass !== ($d['admin_pass_confirm'] ?? '')) return ['success' => false, 'error' => 'Passwords do not match'];
    
    $config = "<?php\ndefine('DB_CONFIGURED', true);\ndefine('DB_HOST', '{$db['db_host']}');\ndefine('DB_NAME', '{$db['db_name']}');\ndefine('DB_USER', '{$db['db_user']}');\ndefine('DB_PASS', '{$db['db_pass']}');\ndefine('DB_CHARSET', 'utf8mb4');\ndefine('BASE_URL', '$url');\ndefine('SHORT_CODE_LENGTH', 6);\ndefine('ALLOWED_PROTOCOLS', ['http://', 'https://']);\ndefine('RATE_LIMIT_ENABLED', true);\ndefine('RATE_LIMIT_MAX_REQUESTS', 10);\ndefine('RATE_LIMIT_WINDOW_SECONDS', 60);\ndefine('RESERVED_CODES', ['admin', 'api', 'stats', 'login', 'logout', 'install']);\ndefine('CORS_ORIGIN', '*');\nfunction getDbConnection(): PDO { static \$p=null; if(\$p===null){ \$p=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET,DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]); } return \$p; }\nfunction jsonResponse(array \$d,int \$c=200):void{http_response_code(\$c);header('Content-Type:application/json');header('Access-Control-Allow-Origin:'.CORS_ORIGIN);echo json_encode(\$d);exit;}\nfunction errorResponse(string \$m,int \$c=400):void{jsonResponse(['success'=>false,'error'=>\$m],\$c);}\n";
    
    if (!file_put_contents(__DIR__ . '/api/config.php', $config)) {
        return ['success' => false, 'error' => 'Could not write config.php'];
    }
    
    $ap = __DIR__ . '/api/admin.php';
    if (file_exists($ap)) {
        $ac = file_get_contents($ap);
        $ac = preg_replace("/define\('ADMIN_PASSWORD',\s*'[^']*'\);/", "define('ADMIN_PASSWORD', '$pass');", $ac);
        file_put_contents($ap, $ac);
    }
    unset($_SESSION['db']);
    return ['success' => true];
}

function checkReqs() {
    return [
        ['name'=>'PHP Version','req'=>'7.4+','cur'=>PHP_VERSION,'ok'=>version_compare(PHP_VERSION,'7.4.0','>=')],
        ['name'=>'PDO Extension','req'=>'Enabled','cur'=>extension_loaded('pdo')?'Enabled':'Missing','ok'=>extension_loaded('pdo')],
        ['name'=>'PDO MySQL','req'=>'Enabled','cur'=>extension_loaded('pdo_mysql')?'Enabled':'Missing','ok'=>extension_loaded('pdo_mysql')],
        ['name'=>'Writable api/','req'=>'Writable','cur'=>is_writable(__DIR__.'/api')?'Writable':'Not writable','ok'=>is_writable(__DIR__.'/api')]
    ];
}

function detectUrl() {
    $p = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $h = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
    return "$p://$h$path";
}

$reqs = checkReqs();
$allOk = !in_array(false, array_column($reqs, 'ok'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install SN/P</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root{--bg:#0a0a0f;--card:#1a1a24;--text:#f5f5f7;--muted:#5a5a66;--accent:#31394E;--success:#22c55e;--error:#ef4444;--border:rgba(255,255,255,0.08)}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Outfit',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;padding:2rem;line-height:1.6}
        .container{max-width:600px;margin:0 auto}
        .logo{text-align:center;margin-bottom:2rem}.logo h1{font-family:'DM Serif Display',serif;font-size:2.5rem}.logo p{color:var(--muted)}
        .card{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:2rem;margin-bottom:1.5rem}
        .card-title{font-size:1.25rem;font-weight:600;margin-bottom:1.5rem;display:flex;align-items:center;gap:.75rem}
        .step-num{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;background:var(--accent);border-radius:50%;font-size:.875rem}
        .steps{display:flex;justify-content:center;gap:.5rem;margin-bottom:2rem}
        .step-dot{width:10px;height:10px;border-radius:50%;background:var(--border)}.step-dot.active{background:var(--accent)}.step-dot.done{background:var(--success)}
        table{width:100%}td{padding:.75rem 0;border-bottom:1px solid var(--border)}tr:last-child td{border:none}.status-ok{color:var(--success)}.status-fail{color:var(--error)}
        .form-group{margin-bottom:1.25rem}.form-label{display:block;font-size:.875rem;color:#8b8b96;margin-bottom:.5rem}
        .form-input{width:100%;padding:.875rem 1rem;background:#12121a;border:1px solid var(--border);border-radius:12px;color:var(--text);font-family:inherit;font-size:1rem}
        .form-input:focus{outline:none;border-color:var(--accent)}
        .form-hint{font-size:.8125rem;color:var(--muted);margin-top:.375rem}
        .btn{display:inline-flex;align-items:center;justify-content:center;padding:.875rem 1.5rem;font-family:inherit;font-size:1rem;font-weight:500;border-radius:12px;border:none;cursor:pointer;width:100%;text-decoration:none}
        .btn-primary{background:var(--accent);color:#fff}.btn-primary:hover{background:#818cf8}.btn-primary:disabled{opacity:.5;cursor:not-allowed}
        .btn-ghost{background:transparent;color:#8b8b96;border:1px solid var(--border)}.btn-ghost:hover{background:#12121a}
        .alert{padding:1rem;border-radius:12px;margin-bottom:1.5rem}.alert-error{background:rgba(239,68,68,.1);color:var(--error);border:1px solid rgba(239,68,68,.2)}
        .alert-success{background:rgba(34,197,94,.1);color:var(--success);border:1px solid rgba(34,197,94,.2)}
        .success-icon{width:80px;height:80px;margin:0 auto 1.5rem;background:rgba(34,197,94,.1);border-radius:50%;display:flex;align-items:center;justify-content:center}
        .success-content{text-align:center}.success-content h2{margin-bottom:.5rem}.success-content p{color:#8b8b96;margin-bottom:1.5rem}
        .success-links{display:flex;gap:1rem;justify-content:center}
        .warning-box{background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.2);border-radius:12px;padding:1rem;margin-top:1.5rem;font-size:.875rem;color:#f59e0b}
        .warning-box strong{display:block;margin-bottom:.25rem}
    </style>
</head>
<body>
<div class="container">
    <div class="logo"><h1>SN/P</h1><p>Installation Wizard</p></div>
    <div class="steps">
        <div class="step-dot <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : '' ?>"></div>
        <div class="step-dot <?= $step >= 2 ? ($step > 2 ? 'done' : 'active') : '' ?>"></div>
        <div class="step-dot <?= $step >= 3 ? ($step > 3 ? 'done' : 'active') : '' ?>"></div>
        <div class="step-dot <?= $step >= 4 ? 'active' : '' ?>"></div>
    </div>
    <?php if ($errors): ?><div class="alert alert-error"><?= htmlspecialchars($errors[0]) ?></div><?php endif; ?>
    
    <?php if ($step == 1): ?>
    <div class="card">
        <h2 class="card-title"><span class="step-num">1</span>System Requirements</h2>
        <table>
            <?php foreach ($reqs as $r): ?>
            <tr><td><?= $r['name'] ?></td><td style="text-align:right"><span class="<?= $r['ok'] ? 'status-ok' : 'status-fail' ?>"><?= $r['cur'] ?> <?= $r['ok'] ? '✓' : '✗' ?></span></td></tr>
            <?php endforeach; ?>
        </table>
        <?php if (!$allOk): ?><div class="alert alert-error" style="margin-top:1.5rem;margin-bottom:0">Some requirements are not met.</div><?php endif; ?>
    </div>
    <form method="get"><input type="hidden" name="step" value="2"><button type="submit" class="btn btn-primary" <?= !$allOk ? 'disabled' : '' ?>>Next: Database Setup</button></form>
    
    <?php elseif ($step == 2): ?>
    <div class="card">
        <h2 class="card-title"><span class="step-num">2</span>Database Connection</h2>
        <form method="post"><input type="hidden" name="step" value="2">
            <div class="form-group"><label class="form-label">Database Host</label><input type="text" name="db_host" class="form-input" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required><p class="form-hint">Usually "localhost"</p></div>
            <div class="form-group"><label class="form-label">Database Name</label><input type="text" name="db_name" class="form-input" value="<?= htmlspecialchars($_POST['db_name'] ?? 'url_shortener') ?>" required><p class="form-hint">Will be created if it doesn't exist</p></div>
            <div class="form-group"><label class="form-label">Username</label><input type="text" name="db_user" class="form-input" value="<?= htmlspecialchars($_POST['db_user'] ?? 'root') ?>" required></div>
            <div class="form-group"><label class="form-label">Password</label><input type="password" name="db_pass" class="form-input"><p class="form-hint">Leave empty if no password</p></div>
            <button type="submit" class="btn btn-primary">Test Connection & Continue</button>
        </form>
    </div>
    <a href="?step=1" class="btn btn-ghost">← Back</a>
    
    <?php elseif ($step == 3): ?>
    <div class="card">
        <h2 class="card-title"><span class="step-num">3</span>Settings</h2>
        <div class="alert alert-success">✓ Database connection successful! Tables created.</div>
        <form method="post"><input type="hidden" name="step" value="3">
            <div class="form-group"><label class="form-label">Base URL</label><input type="url" name="base_url" class="form-input" value="<?= htmlspecialchars($_POST['base_url'] ?? detectUrl()) ?>" required><p class="form-hint">URL where SN/P is installed</p></div>
            <div class="form-group"><label class="form-label">Admin Password</label><input type="password" name="admin_pass" class="form-input" minlength="6" required><p class="form-hint">At least 6 characters</p></div>
            <div class="form-group"><label class="form-label">Confirm Password</label><input type="password" name="admin_pass_confirm" class="form-input" minlength="6" required></div>
            <button type="submit" class="btn btn-primary">Complete Installation</button>
        </form>
    </div>
    
    <?php elseif ($step == 4): ?>
    <div class="card">
        <div class="success-content">
            <div class="success-icon"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div>
            <h2>Installation Complete!</h2>
            <p>SN/P is now ready to use.</p>
            <div class="success-links">
                <a href="index.html" class="btn btn-primary" style="width:auto">Open SN/P</a>
                <a href="admin.html" class="btn btn-ghost" style="width:auto">Admin Panel</a>
            </div>
            <div class="warning-box"><strong>⚠️ Important</strong>Delete install.php from the server for security.</div>
        </div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
