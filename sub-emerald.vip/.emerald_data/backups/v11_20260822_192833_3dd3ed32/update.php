<?php
/**
 * =====================================================================
 *  Emerald Central Hub — V10 → V11 Upgrader
 * =====================================================================
 *  - Self-contained (single file), idempotent, backup-first.
 *  - Preserves ALL ENC::-encrypted data (users, notes, cloaking/domains,
 *    file meta, firewall, logs). Never deletes, never re-encrypts.
 *  - Restructures the app into:  modules/  core/  views/  assets/
 *  - Adds the new "Domains vault" feature with extended fields.
 *
 *  Usage:
 *    CLI  :  php update.php            (runs immediately, plain-text report)
 *    Web  :  GET  update.php           (intro page — no changes)
 *            POST update.php           (executes upgrade)
 *            POST update.php&force=1   (re-run even if already upgraded)
 *
 *  Safety:
 *    1. A full backup of every data directory + all replaced files is
 *       written to  .emerald_data/backups/v11_<timestamp>_<rand>/
 *    2. Data files are only COPIED/renamed, never modified in place.
 *    3. A marker file (.emerald_data/.v11_version) prevents accidental
 *       re-runs; use force=1 only when you know what you are doing.
 * =====================================================================
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '1');
set_time_limit(300);

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    @session_start();
}

define('V11_ROOT', dirname(__FILE__));
define('V11_DATA_DIR', V11_ROOT . '/.emerald_data');

/* ------------------------------------------------------------------ *
 *  Logging helpers
 * ------------------------------------------------------------------ */
$GLOBALS['__v11_log'] = array();
$GLOBALS['__v11_ok']  = true;

function v11_log($msg, $type = 'ok') {
    $GLOBALS['__v11_log'][] = array($type, $msg);
    if ($type === 'err') { $GLOBALS['__v11_ok'] = false; }
}
function v11_esca($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function v11_mkdir_p($dir) {
    if (is_dir($dir)) { return true; }
    return @mkdir($dir, 0755, true);
}
function v11_w($path, $data) {
    $dir = dirname($path);
    if (!v11_mkdir_p($dir)) { return false; }
    $tmp = $path . '.tmp' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $data) === false) { return false; }
    if (!@rename($tmp, $path)) { @unlink($tmp); return false; }
    return true;
}
function v11_rm_r($dir) {
    if (!is_dir($dir)) { return; }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        if ($f->isDir()) { @rmdir($f->getPathname()); }
        else { @unlink($f->getPathname()); }
    }
    @rmdir($dir);
}
function v11_copy_r($src, $dst, $skip = array()) {
    if (!is_dir($src)) { return false; }
    if (!v11_mkdir_p($dst)) { return false; }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $f) {
        $rel = substr($f->getPathname(), strlen(rtrim($src, '/\\')) + 1);
        foreach ($skip as $s) {
            if ($rel === $s || strpos($rel, $s . '/') === 0) { continue 2; }
        }
        $target = $dst . '/' . $rel;
        if ($f->isDir()) { v11_mkdir_p($target); }
        else { @copy($f->getPathname(), $target); }
    }
    return true;
}

/* ------------------------------------------------------------------ *
 *  Encryption key + DB probing (read-only) — V10 formats
 * ------------------------------------------------------------------ */
function v11_key() {
    $keyFile = V11_DATA_DIR . '/.sys_key';
    if (is_file($keyFile)) {
        $raw = file_get_contents($keyFile);
        if ($raw === false) { return ''; }
        $raw = trim($raw);                 // IMPORTANT: hex2bin rejects whitespace on PHP 8.x
        if (preg_match('/^[0-9a-fA-F]{32,128}$/', $raw)) { return $raw; }
    }
    // No valid key yet → generate one (also used by V11 config).
    if (!v11_mkdir_p(V11_DATA_DIR)) { return ''; }
    $bytes = '';
    if (function_exists('random_bytes')) { $bytes = random_bytes(32); }
    elseif (function_exists('openssl_random_pseudo_bytes')) { $bytes = openssl_random_pseudo_bytes(32); }
    if ($bytes === '' || strlen($bytes) !== 32) { $bytes = md5(mt_rand() . microtime() . uniqid('', true), true) . md5(uniqid('', true) . microtime() . mt_rand(), true); }
    $hex = bin2hex($bytes);
    @file_put_contents($keyFile, $hex . PHP_EOL);
    return $hex;
}

function v11_dec($value, $keyHex) {
    if (!is_string($value) || strpos($value, 'ENC::') !== 0) { return $value; }
    $b64 = substr($value, 5);
    $json = base64_decode($b64, true);
    if ($json === false) { return null; }
    $arr = json_decode($json, true);
    if (!is_array($arr) || !isset($arr['iv'], $arr['value'])) { return null; }
    $bin = @hex2bin(trim($keyHex));
    if ($bin === false) { return null; }
    $plain = @openssl_decrypt($arr['value'], 'aes-256-cbc', $bin, 0, @hex2bin($arr['iv']));
    if ($plain === false) { return null; }
    return $plain;
}

function v11_probe_json($path, $keyHex) {
    if (!is_file($path)) { return array('exists' => false, 'entries' => null, 'enc' => false); }
    $raw = (string)file_get_contents($path);
    $data = null;
    $enc = false;
    if (strpos($raw, 'ENC::') === 0) {
        // Whole-file ENC wrapper (exact V10 saveDB format):
        //   ENC:: base64(json{iv:hex, value:base64(aes-256-cbc plaintext)})
        $enc = true;
        $dec = v11_dec($raw, $keyHex);
        if ($dec === null) {
            return array('exists' => true, 'entries' => null, 'enc' => true, 'error' => 'undecryptable');
        }
        $data = json_decode($dec, true);
        if (!is_array($data)) {
            return array('exists' => true, 'entries' => null, 'enc' => true, 'error' => 'invalid-json-after-decrypt');
        }
    } else {
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return array('exists' => true, 'entries' => null, 'enc' => false, 'error' => 'invalid-json');
        }
        // Alternative whole-file wrapper used by some older versions
        if (isset($data['enc'], $data['data']) && $data['enc'] === true) {
            $enc = true;
            $dec = v11_dec($data['data'], $keyHex);
            $data = ($dec !== null) ? json_decode($dec, true) : null;
            if (!is_array($data)) {
                return array('exists' => true, 'entries' => null, 'enc' => true, 'error' => 'undecryptable');
            }
        }
    }
    return array('exists' => true, 'entries' => count($data), 'enc' => $enc, 'encCount' => ($enc ? 1 : 0), 'error' => null);
}

function v11_probe_db(&$report, $keyHex) {
    $probes = array(
        'users'       => array('system_users/users.json', '.emerald_data/users.json'),
        'login_logs'  => array('system_users/login_logs.json', '.emerald_data/login_logs.json'),
        'activity_logs' => array('system_users/activity_logs.json', '.emerald_data/activity_logs.json'),
        'notes'       => array('system_containers/notes.json', '.emerald_data/notes.json'),
        'cloaking'    => array('cloaking_data/cloaking.json', '.emerald_data/cloaking.json'),
        'file_meta'   => array('assets_manager/file_meta.json', '.emerald_data/file_meta.json'),
        'firewall'    => array('firewall_ip/firewall.json', '.emerald_data/firewall.json'),
    );
    $resolved = array();
    foreach ($probes as $name => $cands) {
        $found = null;
        foreach ($cands as $c) {
            $p = V11_ROOT . '/' . $c;
            if (is_file($p)) { $found = $c; break; }
        }
        if ($found === null) { $report['db'][$name] = array('status' => 'missing'); continue; }
        $probe = v11_probe_json(V11_ROOT . '/' . $found, $keyHex);
        $probe['path'] = $found;
        if ($probe['error'] === 'undecryptable') {
            $probe['status'] = 'corrupt';
            v11_log('WARNING: ' . $name . ' (' . $found . ') contains data that could not be decrypted with the current key. Left untouched.', 'warn');
        } elseif ($probe['error'] === 'invalid-json') {
            $probe['status'] = 'corrupt';
            v11_log('WARNING: ' . $name . ' (' . $found . ') is not valid JSON. Left untouched.', 'warn');
        } elseif ($probe['exists'] && $probe['entries'] === null) {
            $probe['status'] = 'empty';
        } else {
            $probe['status'] = 'ok';
        }
        $report['db'][$name] = $probe;
        $resolved[$name] = $found;
    }
    return $resolved;
}

/* ------------------------------------------------------------------ *
 *  Backup (write backup BEFORE any change)
 * ------------------------------------------------------------------ */
function v11_backup(&$report, $existing) {
    $stamp = date('Ymd_His');
    $rand  = substr(bin2hex(random_bytes(4)), 0, 8);
    $dir   = V11_DATA_DIR . '/backups/v11_' . $stamp . '_' . $rand;
    if (!v11_mkdir_p($dir)) {
        v11_log('FATAL: cannot create backup dir ' . $dir, 'err');
        return false;
    }
    $report['backup_dir'] = $dir;
    v11_log('Backup directory: ' . str_replace(V11_ROOT . '/', '', $dir));

    // Data directories (all of them — they may hold anything)
    $dataDirs = array('system_users', 'system_containers', 'cloaking_data', 'assets_manager',
                      'firewall_ip', 'public_notepad', 'emerald_assets', 'untuk-accesskey',
                      'modules', 'core', 'views', 'assets', 'notepad');
    foreach ($dataDirs as $d) {
        if (is_dir(V11_ROOT . '/' . $d)) {
            if (v11_copy_r(V11_ROOT . '/' . $d, $dir . '/' . $d, array('backups'))) {
                $report['backed_up'][] = $d . '/';
            }
        }
    }
    // Individual files
    $files = array('index.php', '403.php', 'view.php', 'vieww.php', 'net.php', 'update.php');
    foreach ($files as $f) {
        if (is_file(V11_ROOT . '/' . $f)) {
            @copy(V11_ROOT . '/' . $f, $dir . '/' . $f);
            $report['backed_up'][] = $f;
        }
    }
    // .emerald_data (excluding backups dir itself)
    if (is_dir(V11_DATA_DIR)) {
        v11_copy_r(V11_DATA_DIR, $dir . '/.emerald_data', array('backups'));
        $report['backed_up'][] = '.emerald_data/';
    }
    // Manifest
    $lines = array(
        '# Emerald Central Hub V10→V11 backup manifest',
        '# Generated: ' . date('c'),
        '# Source root: ' . V11_ROOT,
        '',
        '# Backed-up items:',
    );
    foreach ($report['backed_up'] as $b) { $lines[] = '  - ' . $b; }
    $lines[] = '';
    if (isset($report['db']) && is_array($report['db'])) {
        $lines[] = '# Data files detected before upgrade:';
        foreach ($report['db'] as $name => $p) {
            $lines[] = '  - ' . $name . ': ' . (isset($p['path']) ? $p['path'] : 'MISSING')
                     . ' | ' . (isset($p['status']) ? $p['status'] : '?')
                     . (isset($p['entries']) ? ' | entries=' . $p['entries'] : '')
                     . (isset($p['encCount']) ? ' | encValues=' . $p['encCount'] : '');
        }
    }
    @file_put_contents($dir . '/MANIFEST.txt', implode(PHP_EOL, $lines) . PHP_EOL);
    return true;
}

/* ------------------------------------------------------------------ *
 *  Registry — records where V11 keeps each dataset
 * ------------------------------------------------------------------ */
function v11_write_registry($root) {
    $rel = function ($p) use ($root) { return str_replace($root . '/', '', $p); };
    $paths = array(
        'users'        => 'modules/users/data/users.json',
        'login_logs'   => 'modules/users/data/login_logs.json',
        'activity_logs'=> 'modules/users/data/activity_logs.json',
        'notes'        => 'modules/notes/data/notes.json',
        'cloaking'     => 'modules/domains/data/domains.json',
        'domains'      => 'modules/domains/data/domains.json',   // shared vault with cloaking
        'file_meta'    => 'modules/storage/data/file_meta.json',
        'firewall'     => 'modules/firewall/data/firewall.json',
    );
    $php = "<?php\n"
         . "/** Emerald Central Hub V11 — dataset path registry (auto-generated by update.php) */\n"
         . "return " . var_export($paths, true) . ";\n";
    $target = $root . '/.emerald_data/v11_paths.php';
    if (!v11_w($target, $php)) {
        v11_log('FATAL: cannot write registry ' . $rel($target), 'err');
        return false;
    }
    v11_log('Wrote dataset registry: ' . $rel($target));
    return $paths;
}

/* ------------------------------------------------------------------ *
 *  Migrate public_notepad → modules/notepad/data (copy, never delete)
 * ------------------------------------------------------------------ */
function v11_migrate_notepad($root, &$report) {
    $src = $root . '/public_notepad';
    $dst = $root . '/modules/notepad/data';
    if (!is_dir($src)) {
        v11_log('Notepad data: no public_notepad/ found, skipped.', 'warn');
        return;
    }
    if (!v11_mkdir_p($dst)) {
        v11_log('FATAL: cannot create notepad data dir', 'err');
        return;
    }
    $copied = 0; $skipped = 0;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $f) {
        $rel = substr($f->getPathname(), strlen(rtrim($src, '/\\')) + 1);
        $target = $dst . '/' . $rel;
        if ($f->isDir()) { v11_mkdir_p($target); continue; }
        if (file_exists($target)) { $skipped++; continue; }   // idempotent: keep existing
        if (v11_mkdir_p(dirname($target)) && @copy($f->getPathname(), $target)) { $copied++; }
        else { v11_log('WARNING: could not copy notepad file ' . $rel, 'warn'); }
    }
    $report['notepad']['copied'] = $copied;
    $report['notepad']['skipped'] = $skipped;
    v11_log('Notepad data migrated: ' . $copied . ' copied, ' . $skipped . ' already present (preserved).');
}

/* ------------------------------------------------------------------ *
 *  Module data dirs + deny .htaccess (data only)
 * ------------------------------------------------------------------ */
function v11_mkdirs($root, &$report) {
    $modules = array('dashboard', 'storage', 'domains', 'cloaking', 'notes', 'users', 'firewall', 'monitor', 'notepad');
    foreach ($modules as $m) {
        $dir = $root . '/modules/' . $m . '/data';
        if (!v11_mkdir_p($dir)) { v11_log('FATAL: cannot create ' . $dir, 'err'); continue; }
        $ht = $dir . '/.htaccess';
        if (!is_file($ht)) {
            v11_w($ht, "Require all denied\n");
            $report['data_dirs'][] = 'modules/' . $m . '/data/ (+ .htaccess deny)';
        }
    }
    v11_log('Created module data dirs (each protected by .htaccess deny).');
}

/* ------------------------------------------------------------------ *
 *  Write embedded V11 files
 * ------------------------------------------------------------------ */
function v11_write_files($root, $files, &$report) {
    foreach ($files as $rel => $content) {
        $path = $root . '/' . $rel;
        if (!v11_w($path, $content)) {
            v11_log('FATAL: could not write ' . $rel, 'err');
            return false;
        }
        $report['written'][] = $rel;
    }
    v11_log('Wrote ' . count($files) . ' new V11 files.');
    return true;
}

/* ------------------------------------------------------------------ *
 *  Lint every written .php file (best effort)
 * ------------------------------------------------------------------ */
function v11_lint($root, $files, &$report) {
    if (!function_exists('shell_exec')) { v11_log('php -l skipped (shell_exec disabled).', 'warn'); return; }
    $failed = 0;
    foreach ($files as $rel => $content) {
        if (substr($rel, -4) !== '.php') { continue; }
        $out = @shell_exec('php -l ' . escapeshellarg($root . '/' . $rel) . ' 2>&1');
        if ($out === null || stripos($out, 'No syntax errors') === false) {
            $failed++;
            v11_log('LINT FAIL ' . $rel . ': ' . trim((string)$out), 'err');
        }
    }
    if ($failed === 0) {
        $report['lint'] = 'all-ok';
        v11_log('PHP lint: all embedded files OK.');
    } else {
        $report['lint'] = 'failed:' . $failed;
        v11_log('PHP lint: ' . $failed . ' file(s) FAILED (see above).', 'warn');
    }
}

/* ------------------------------------------------------------------ *
 *  Marker (idempotency)
 * ------------------------------------------------------------------ */
function v11_marker($root, &$report) {
    $m = array('version' => '11.0.0', 'time' => date('c'), 'php' => PHP_VERSION);
    $report['marker'] = $m;
    return v11_w($root . '/.emerald_data/.v11_version',
        "<?php return " . var_export($m, true) . ";\n");
}

/* ------------------------------------------------------------------ *
 *  Web rendering
 * ------------------------------------------------------------------ */
function v11_web_intro($root) {
    $marker = is_file(V11_DATA_DIR . '/.v11_version') ? @include(V11_DATA_DIR . '/.v11_version') : null;
    $already = is_array($marker) && isset($marker['version']);
    $style = '
    body{font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#0e1420;color:#e6edf7;margin:0;line-height:1.6}
    .wrap{max-width:760px;margin:40px auto;padding:0 20px}
    .card{background:#151d2e;border:1px solid #26324a;border-radius:14px;padding:28px 32px;margin-bottom:22px}
    h1{font-size:24px;margin:0 0 6px}h2{font-size:18px;margin:0 0 14px;color:#8ab4ff}
    .tag{color:#8b98b3;font-size:13px;margin-bottom:20px}
    code{background:#0b111d;border:1px solid #26324a;border-radius:6px;padding:1px 6px;font-size:12px}
    .badge{display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600}
    .b-new{background:#143f2e;color:#4ade80}.b-done{background:#3f2a14;color:#fbbf24}
    ul{margin:8px 0 16px;padding-left:22px}li{margin:4px 0}
    .warn{background:#3a2a10;border:1px solid #6b4d12;color:#fde68a;border-radius:10px;padding:12px 16px;font-size:13px}
    button{background:#2563eb;color:#fff;border:0;border-radius:10px;padding:14px 28px;font-size:16px;font-weight:600;cursor:pointer}
    button:hover{background:#1d4ed8}
    label{display:flex;align-items:center;gap:8px;font-size:13px;margin-top:14px;color:#aab6cc}
    .muted{color:#8b98b3;font-size:13px}
    .step{display:flex;gap:12px;align-items:flex-start;margin:10px 0}
    .n{flex:0 0 26px;height:26px;border-radius:50%;background:#1c2a45;color:#8ab4ff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700}
    ';
    $h = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>Emerald Central Hub — V11 Upgrader</title><style>' . $style . '</style></head><body><div class="wrap">';
    $h .= '<div class="card"><h1>Emerald Central Hub — V10 → V11</h1>'
        . '<div class="tag">One-file upgrader · backup-first · idempotent</div>';
    if ($already) {
        $h .= '<p><span class="badge b-done">Already upgraded</span> — marker '.v11_esca($marker['version']).' found ('.v11_esca($marker['time']).'). '
             . 'Running again is safe (files are overwritten with the same V11 versions, existing data is never touched).</p>';
    } else {
        $h .= '<p><span class="badge b-new">Ready to upgrade</span> — no V11 marker detected yet.</p>';
    }
    $h .= '</div>';

    $h .= '<div class="card"><h2>What will happen</h2>';
    $steps = array(
        'Full backup of all data dirs + app files → <code>.emerald_data/backups/v11_&lt;timestamp&gt;/</code> (with MANIFEST.txt).',
        'Detect &amp; decrypt-check your V10 databases (users, notes, cloaking, file meta, firewall, logs).',
        'Restructure into <code>modules/</code>, <code>core/</code>, <code>views/</code>, <code>assets/</code>.',
        'Migrate <code>public_notepad/</code> → <code>modules/notepad/data/</code> (existing files preserved).',
        'Add the new Domains vault with extended fields (status, username, UAPI token, cPanel token, expiry, notes).',
        'Write a marker so accidental re-runs are blocked (re-runs safe with <code>force=1</code>).',
    );
    foreach ($steps as $i => $s) { $h .= '<div class="step"><div class="n">' . ($i + 1) . '</div><div>' . $s . '</div></div>'; }
    $h .= '</div>';

    $h .= '<div class="card"><h2>Execute</h2>'
        . '<div class="warn">Your data is <b>never modified in place</b> — only copied to the new V11 locations and the backup. '
        . 'If anything fails, restore the backup folder.</div>'
        . '<form method="post" action="update.php"><button type="submit" name="go" value="1">Run the upgrade now</button>'
        . '<label><input type="checkbox" name="force" value="1"> Re-run even if already upgraded (force)</label>'
        . '<label><input type="checkbox" name="remove" value="1"> Delete this update.php file after success</label>'
        . '</form></div>';
    $h .= '</div></body></html>';
    echo $h;
}

function v11_web_report($report, $root) {
    $ok = $GLOBALS['__v11_ok'];
    $style = '
    body{font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#0e1420;color:#e6edf7;margin:0;line-height:1.6}
    .wrap{max-width:820px;margin:30px auto;padding:0 20px}
    .card{background:#151d2e;border:1px solid #26324a;border-radius:14px;padding:24px 30px;margin-bottom:18px}
    h1{font-size:22px;margin:0 0 6px}h2{font-size:16px;margin:0 0 12px;color:#8ab4ff}
    .ok{color:#4ade80}.err{color:#f87171}.warn{color:#fbbf24}.muted{color:#8b98b3;font-size:12px}
    code{background:#0b111d;border:1px solid #26324a;border-radius:6px;padding:1px 6px;font-size:12px}
    table{width:100%;border-collapse:collapse;font-size:13px}td,th{border:1px solid #26324a;padding:7px 10px;text-align:left}
    th{background:#1c2a45;color:#aac4ff}
    .pill{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700}
    .p-ok{background:#143f2e;color:#4ade80}.p-warn{background:#3f2a14;color:#fbbf24}.p-err{background:#4a1414;color:#f87171}.p-miss{background:#232b3a;color:#8b98b3}
    ';
    $h = '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Upgrade report</title><style>' . $style . '</style></head><body><div class="wrap">';
    $h .= '<div class="card"><h1>' . ($ok ? '<span class="ok">✔</span> Upgrade completed' : '<span class="err">✖</span> Upgrade had errors') . '</h1>'
        . '<div class="muted">Emerald Central Hub V11 · ' . v11_esca(date('c')) . '</div></div>';

    // Log
    $h .= '<div class="card"><h2>Log</h2><table><tr><th>Level</th><th>Message</th></tr>';
    foreach ($GLOBALS['__v11_log'] as $l) {
        $cls = $l[0] === 'ok' ? 'ok' : ($l[0] === 'warn' ? 'warn' : 'err');
        $h .= '<tr><td class="' . $cls . '">' . v11_esca(strtoupper($l[0])) . '</td><td>' . v11_esca($l[1]) . '</td></tr>';
    }
    $h .= '</table></div>';

    // DB summary
    if (isset($report['db']) && is_array($report['db'])) {
        $h .= '<div class="card"><h2>Data verification</h2><table><tr><th>Dataset</th><th>Source file</th><th>Entries</th><th>Encrypted values</th><th>Status</th></tr>';
        foreach ($report['db'] as $name => $p) {
            $status = isset($p['status']) ? $p['status'] : '?';
            $pill = $status === 'ok' ? 'p-ok' : ($status === 'corrupt' ? 'p-err' : ($status === 'missing' ? 'p-miss' : 'p-warn'));
            $h .= '<tr><td>' . v11_esca($name) . '</td><td><code>' . v11_esca(isset($p['path']) ? $p['path'] : '—') . '</code></td>'
                . '<td>' . (isset($p['entries']) ? $p['entries'] : '—') . '</td>'
                . '<td>' . (isset($p['encCount']) ? $p['encCount'] : '—') . '</td>'
                . '<td><span class="pill ' . $pill . '">' . v11_esca($status) . '</span></td></tr>';
        }
        $h .= '</table></div>';
    }

    // Notepad
    if (isset($report['notepad'])) {
        $h .= '<div class="card"><h2>Notepad migration</h2><div>Copied: ' . (int)$report['notepad']['copied'] . ' · Already present (preserved): ' . (int)$report['notepad']['skipped'] . '</div></div>';
    }

    if (isset($report['backup_dir'])) {
        $h .= '<div class="card"><h2>Backup</h2><div>Full backup saved to <code>' . v11_esca(str_replace($root . '/', '', $report['backup_dir'])) . '</code></div>'
            . '<div class="muted">Restore by copying its contents back to the app root. NEVER delete it until you are certain the new version works.</div></div>';
    }

    $h .= '<div class="card"><p><a href="index.php" style="color:#8ab4ff">→ Go to the upgraded app (login)</a></p></div>';
    $h .= '</div></body></html>';
    echo $h;
}

function v11_cli_report($report, $root) {
    $bar = str_repeat('=', 66);
    echo "\n" . $bar . "\n  Emerald Central Hub — V10 → V11 Upgrade Report\n" . $bar . "\n\n";
    foreach ($GLOBALS['__v11_log'] as $l) {
        $icon = $l[0] === 'ok' ? '[ OK ]' : ($l[0] === 'warn' ? '[WARN]' : '[ERR ]');
        echo '  ' . $icon . ' ' . $l[1] . "\n";
    }
    if (isset($report['db']) && is_array($report['db'])) {
        echo "\n" . '  -- Data verification --' . "\n";
        foreach ($report['db'] as $name => $p) {
            $s = isset($p['status']) ? $p['status'] : '?';
            echo '  ' . str_pad($name, 14) . ' ' . (isset($p['path']) ? $p['path'] : '(missing)')
               . (isset($p['entries']) ? ' | entries=' . $p['entries'] : '')
               . (isset($p['encCount']) ? ' | enc=' . $p['encCount'] : '')
               . ' | ' . $s . "\n";
        }
    }
    if (isset($report['backup_dir'])) {
        echo "\n  Backup: " . $report['backup_dir'] . "\n";
    }
    echo "\n" . ($GLOBALS['__v11_ok'] ? '  RESULT: SUCCESS' : '  RESULT: FAILED — check errors above') . "\n" . $bar . "\n\n";
}

/* ------------------------------------------------------------------ *
 *  Embedded V11 application files (auto-generated: 2026-08-22 11:11:31)
 *  41 files — keys are paths relative to the app root.
 * ------------------------------------------------------------------ */
$V11_FILES = array(
    '403.php' => '<?php
/**
 * Emerald Central Hub — 403 Forbidden page.
 * Rendered by core/config.php when the firewall blocks a client IP
 * (identical behaviour to V10).
 */
http_response_code(403);
header(\'Content-Type: text/html; charset=utf-8\');
$__ip = function_exists(\'getRealIpAddr\') ? getRealIpAddr() : (isset($_SERVER[\'REMOTE_ADDR\']) ? $_SERVER[\'REMOTE_ADDR\'] : \'?\');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>403 — Forbidden</title>
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;background:#0b1019;color:#dbe4f3;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
  .card{max-width:460px;width:100%;background:#111a2a;border:1px solid #22304a;border-radius:16px;padding:40px 36px;text-align:center}
  .code{font-size:64px;font-weight:800;background:linear-gradient(135deg,#f87171,#fbbf24);-webkit-background-clip:text;background-clip:text;color:transparent;line-height:1.1}
  h1{font-size:20px;margin:14px 0 8px;color:#f1f5fb}
  p{color:#93a1bd;font-size:14px;line-height:1.7}
  code{display:inline-block;background:#0b111d;border:1px solid #26324a;border-radius:6px;padding:2px 8px;font-size:12px;color:#fca5a5;margin-top:14px}
  .shield{margin:0 auto 6px;width:52px;height:52px;border-radius:14px;background:#1c2740;display:flex;align-items:center;justify-content:center;font-size:26px}
</style>
</head>
<body>
  <div class="card">
    <div class="shield">🛡</div>
    <div class="code">403</div>
    <h1>Access Forbidden</h1>
    <p>Your IP address is not whitelisted in the firewall.<br>If this is a mistake, contact the owner to add it via the Emergency Access or the firewall panel.</p>
    <code><?php echo htmlspecialchars((string)$__ip, ENT_QUOTES, \'UTF-8\'); ?></code>
  </div>
</body>
</html>
',
    'assets/css/app.css' => '/* =====================================================================
   Emerald Central Hub V11 — application stylesheet
   Dark theme by default; [data-theme="light"] overrides are scoped below.
   ===================================================================== */

:root {
  --bg: #0b1019;
  --bg2: #0e1524;
  --panel: #111a2a;
  --panel2: #16213a;
  --border: #22304a;
  --border2: #26324a;
  --text: #dbe4f3;
  --text2: #93a1bd;
  --muted: #7c8aa5;
  --accent: #0ea5e9;
  --accent2: #6366f1;
  --ok: #10b981;
  --warn: #f59e0b;
  --err: #f87171;
  --radius: 14px;
  --shadow: 0 10px 30px rgba(0, 0, 0, .35);
  --font: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
  --mono: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
}

* { margin: 0; padding: 0; box-sizing: border-box; }
html { font-size: 15px; }
body { background: var(--bg); color: var(--text); font-family: var(--font); min-height: 100vh; line-height: 1.55; }
a { color: var(--accent); text-decoration: none; }
a:hover { text-decoration: underline; }
::-webkit-scrollbar { width: 9px; height: 9px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #24314d; border-radius: 8px; }
::-webkit-scrollbar-thumb:hover { background: #33456b; }

/* ---------------------------------------------------------------- *
 *  Layout shell
 * ---------------------------------------------------------------- */
.shell { display: flex; min-height: 100vh; }
.sidebar { width: 232px; flex: 0 0 232px; background: var(--bg2); border-right: 1px solid var(--border); display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; }
.side-brand { display: flex; align-items: center; gap: 11px; padding: 18px 16px; border-bottom: 1px solid var(--border); }
.side-title { font-weight: 800; font-size: 14px; line-height: 1.2; }
.side-title small { display: block; color: var(--muted); font-weight: 500; font-size: 11px; margin-top: 2px; }
.logo-badge { width: 40px; height: 40px; border-radius: 12px; background: linear-gradient(135deg, var(--accent), var(--accent2)); display: flex; align-items: center; justify-content: center; font-weight: 800; color: #fff; font-size: 19px; flex: 0 0 40px; }
.side-nav { padding: 12px 10px; display: flex; flex-direction: column; gap: 3px; flex: 1; overflow-y: auto; }
.nav-item { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 10px; color: var(--text2); font-size: 13.5px; font-weight: 600; transition: .15s; }
.nav-item:hover { background: var(--panel2); color: var(--text); text-decoration: none; }
.nav-item.active { background: linear-gradient(90deg, rgba(14,165,233,.16), rgba(99,102,241,.10)); color: var(--accent); box-shadow: inset 0 0 0 1px rgba(14,165,233,.25); }
.side-foot { padding: 12px 10px; border-top: 1px solid var(--border); }

.main { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.topbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 24px; background: var(--panel); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 40; }
.topbar-title h1 { font-size: 17px; font-weight: 800; }
.topbar-right { display: flex; align-items: center; gap: 12px; }
.content { padding: 24px; flex: 1; }

/* ---------------------------------------------------------------- *
 *  Cards / panels
 * ---------------------------------------------------------------- */
.card { background: var(--panel); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; margin-bottom: 18px; box-shadow: var(--shadow); }
.card h2, .card h3 { font-size: 14px; color: #8ab4ff; margin-bottom: 14px; font-weight: 700; }
.card h2 { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.card h2 .spacer { flex: 1; }
.card-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; }
.card-header h2 { margin-bottom: 0; }
.grid { display: grid; gap: 14px; }
.grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.grid-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
@media (max-width: 1000px) { .grid-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); } .grid-3 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 640px) { .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; } .sidebar { display: none; } .content { padding: 14px; } }

.stat { background: var(--panel2); border: 1px solid var(--border); border-radius: 12px; padding: 16px; }
.stat .k { color: var(--muted); font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
.stat .v { font-size: 20px; font-weight: 800; margin-top: 5px; word-break: break-all; }
.stat .v.small { font-size: 15px; }

/* ---------------------------------------------------------------- *
 *  Tables
 * ---------------------------------------------------------------- */
.table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 12px; }
table.tbl { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 560px; }
table.tbl th { background: var(--bg2); color: var(--muted); text-align: left; padding: 10px 14px; font-size: 11.5px; text-transform: uppercase; letter-spacing: .05em; border-bottom: 1px solid var(--border); white-space: nowrap; }
table.tbl td { padding: 10px 14px; border-bottom: 1px solid var(--border); vertical-align: middle; }
table.tbl tr:last-child td { border-bottom: 0; }
table.tbl tr:hover td { background: rgba(14, 165, 233, .05); }
.tbl .mono { font-family: var(--mono); font-size: 12px; }
.tbl .actions { display: flex; gap: 6px; justify-content: flex-end; }

/* ---------------------------------------------------------------- *
 *  Buttons
 * ---------------------------------------------------------------- */
.btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; background: var(--panel2); border: 1px solid var(--border2); color: var(--text); border-radius: 10px; padding: 9px 16px; font-size: 13.5px; font-weight: 600; cursor: pointer; transition: .15s; font-family: var(--font); }
.btn:hover { border-color: var(--accent); color: var(--accent); text-decoration: none; }
.btn-primary { background: linear-gradient(135deg, var(--accent), var(--accent2)); border: 0; color: #fff; }
.btn-primary:hover { filter: brightness(1.1); color: #fff; }
.btn-danger { background: rgba(248, 113, 113, .12); border-color: rgba(248, 113, 113, .35); color: var(--err); }
.btn-danger:hover { background: rgba(248, 113, 113, .22); border-color: var(--err); color: var(--err); }
.btn-ok { background: rgba(16, 185, 129, .12); border-color: rgba(16, 185, 129, .35); color: var(--ok); }
.btn-ok:hover { background: rgba(16, 185, 129, .22); border-color: var(--ok); color: var(--ok); }
.btn.ghost { background: transparent; }
.btn.sm { padding: 6px 11px; font-size: 12px; border-radius: 8px; }
.btn.block, .btn-block { width: 100%; }
.btn:disabled { opacity: .5; cursor: not-allowed; }
.linkbtn { background: none; border: 0; color: var(--accent); cursor: pointer; font-size: 13px; padding: 8px 0; font-family: var(--font); }
.linkbtn:hover { text-decoration: underline; }

/* ---------------------------------------------------------------- *
 *  Forms
 * ---------------------------------------------------------------- */
.field { margin-bottom: 14px; }
.field label { display: block; color: var(--text2); font-size: 12.5px; font-weight: 600; margin-bottom: 6px; }
.field input[type=text], .field input[type=password], .field input[type=number], .field input[type=email], .field textarea, .field select,
input[type=text], input[type=password], input[type=number], input[type=email], textarea, select {
  width: 100%; background: var(--bg2); border: 1px solid var(--border2); border-radius: 10px; padding: 10px 12px; color: var(--text); font-size: 14px; outline: none; font-family: var(--font); }
input:focus, textarea:focus, select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(14, 165, 233, .15); }
textarea { min-height: 220px; font-family: var(--mono); font-size: 13px; line-height: 1.6; resize: vertical; }
input[type=checkbox] { accent-color: var(--accent); }
.row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.row .grow { flex: 1; min-width: 120px; }
.qtext { background: var(--bg2); border: 1px solid var(--border); border-radius: 10px; padding: 12px 14px; margin-bottom: 14px; color: #8ab4ff; font-size: 13.5px; }

/* ---------------------------------------------------------------- *
 *  Login page
 * ---------------------------------------------------------------- */
.login-page { display: flex; align-items: center; justify-content: center; padding: 24px; min-height: 100vh; background: radial-gradient(1200px 500px at 70% -10%, rgba(14,165,233,.14), transparent 60%), radial-gradient(900px 400px at 10% 110%, rgba(99,102,241,.12), transparent 60%), var(--bg); }
.login-wrap { width: 100%; max-width: 400px; }
.login-card { background: var(--panel); border: 1px solid var(--border); border-radius: 20px; padding: 32px 30px; box-shadow: var(--shadow); }
.login-brand { text-align: center; margin-bottom: 24px; }
.login-brand .logo-badge { margin: 0 auto 12px; width: 52px; height: 52px; font-size: 24px; border-radius: 15px; }
.login-brand h1 { font-size: 20px; font-weight: 800; }
.login-brand p { font-size: 12.5px; margin-top: 3px; }
.login-foot { display: flex; align-items: center; justify-content: space-between; margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--border); }
.login-foot .muted { font-size: 12px; }

/* ---------------------------------------------------------------- *
 *  Avatar / chips / badges
 * ---------------------------------------------------------------- */
.avatar-mini { width: 30px; height: 30px; border-radius: 50%; background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 800; flex: 0 0 30px; overflow: hidden; }
.avatar-mini img { width: 100%; height: 100%; object-fit: cover; }
.avatar-lg { width: 56px; height: 56px; border-radius: 50%; background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 800; overflow: hidden; flex: 0 0 56px; }
.avatar-lg img { width: 100%; height: 100%; object-fit: cover; }
.user-chip { display: flex; align-items: center; gap: 9px; background: var(--panel2); border: 1px solid var(--border); border-radius: 999px; padding: 4px 12px 4px 4px; }
.user-meta b { display: block; font-size: 12.5px; line-height: 1.2; }
.user-meta small { color: var(--muted); font-size: 11px; text-transform: capitalize; }
.badge { display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.badge.ok { background: rgba(16,185,129,.14); color: var(--ok); }
.badge.warn { background: rgba(245,158,11,.14); color: var(--warn); }
.badge.err { background: rgba(248,113,113,.14); color: var(--err); }
.badge.info { background: rgba(14,165,233,.14); color: var(--accent); }
.badge.role-owner { background: linear-gradient(135deg, rgba(245,158,11,.25), rgba(248,113,113,.25)); color: #fbbf24; }
.badge.role-admin { background: rgba(99,102,241,.2); color: #a5b4fc; }
.badge.role-user { background: rgba(14,165,233,.16); color: #7dd3fc; }
.badge.role-guest { background: rgba(148,163,184,.16); color: var(--muted); }
.online-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--warn); display: inline-block; box-shadow: 0 0 0 3px rgba(245,158,11,.18); }
.online-dot.live { background: var(--ok); box-shadow: 0 0 0 3px rgba(16,185,129,.18); }
.online-dot.dead { background: var(--err); box-shadow: 0 0 0 3px rgba(248,113,113,.18); }
.status-on { color: var(--ok); }
.status-off { color: var(--err); }

/* ---------------------------------------------------------------- *
 *  Modal
 * ---------------------------------------------------------------- */
.modal-mask { position: fixed; inset: 0; background: rgba(4, 8, 15, .72); backdrop-filter: blur(3px); display: flex; align-items: center; justify-content: center; z-index: 200; padding: 20px; }
.modal { background: var(--panel); border: 1px solid var(--border); border-radius: 18px; width: 100%; max-width: 520px; max-height: 88vh; overflow-y: auto; padding: 24px; box-shadow: var(--shadow); }
.modal h3 { font-size: 15px; font-weight: 800; margin-bottom: 16px; }
.modal .modal-foot { display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px; }

/* ---------------------------------------------------------------- *
 *  Toast
 * ---------------------------------------------------------------- */
.toast { position: fixed; bottom: 26px; left: 50%; transform: translateX(-50%) translateY(16px); background: var(--panel2); border: 1px solid var(--border2); color: var(--text); padding: 12px 20px; border-radius: 12px; font-size: 13px; opacity: 0; pointer-events: none; transition: .25s; z-index: 300; box-shadow: var(--shadow); max-width: 90vw; }
.toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
.toast.ok { border-color: rgba(16,185,129,.5); color: var(--ok); }
.toast.err { border-color: rgba(248,113,113,.5); color: var(--err); }

/* ---------------------------------------------------------------- *
 *  Misc utilities
 * ---------------------------------------------------------------- */
.hide { display: none !important; }
.muted { color: var(--muted); }
.mono { font-family: var(--mono); }
.small { font-size: 12px; }
.right { text-align: right; }
.center { text-align: center; }
.mt { margin-top: 14px; }
.mb { margin-bottom: 14px; }
.flex { display: flex; align-items: center; gap: 10px; }
.flex-between { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.wrap { flex-wrap: wrap; }
.empty { text-align: center; color: var(--muted); padding: 30px 10px; font-size: 13px; }
.file-row { display: flex; align-items: center; gap: 10px; padding: 8px 12px; border-radius: 10px; border: 1px solid transparent; }
.file-row:hover { background: var(--panel2); border-color: var(--border); }
.file-row .ico { width: 30px; text-align: center; flex: 0 0 30px; }
.file-row .nm { flex: 1; cursor: pointer; font-size: 13.5px; word-break: break-all; }
.file-row .nm:hover { color: var(--accent); }
.theme-toggle { background: var(--panel2); border: 1px solid var(--border2); color: var(--text2); border-radius: 9px; padding: 7px 12px; font-size: 12.5px; cursor: pointer; font-family: var(--font); }
.theme-toggle:hover { color: var(--accent); border-color: var(--accent); }
.progress { height: 6px; border-radius: 999px; background: var(--bg2); overflow: hidden; }
.progress > span { display: block; height: 100%; background: linear-gradient(90deg, var(--accent), var(--accent2)); }

/* ---------------------------------------------------------------- *
 *  Light theme overrides (scoped, default stays dark)
 * ---------------------------------------------------------------- */
[data-theme="light"] {
  --bg: #f1f5f9; --bg2: #e8eef5; --panel: #ffffff; --panel2: #f8fafc;
  --border: #dbe3ee; --border2: #cbd5e1; --text: #0f172a; --text2: #475569; --muted: #94a3b8;
  --shadow: 0 10px 30px rgba(15, 23, 42, .08);
}
[data-theme="light"] .card h2, [data-theme="light"] .card h3 { color: #2563eb; }
[data-theme="light"] .qtext { color: #2563eb; }
[data-theme="light"] .login-page { background: radial-gradient(1200px 500px at 70% -10%, rgba(14,165,233,.10), transparent 60%), radial-gradient(900px 400px at 10% 110%, rgba(99,102,241,.10), transparent 60%), var(--bg); }
[data-theme="light"] table.tbl th { background: var(--bg2); }
[data-theme="light"] .modal-mask { background: rgba(15, 23, 42, .45); }
',
    'assets/js/app.js' => '/* =====================================================================
   Emerald Central Hub V11 — shared front-end helpers
   Exposes window.EM (used by views, modules and the public notepad):
     EM.post(url, data)      POST with CSRF field → Promise<json>
     EM.get(url)             GET → Promise<json>
     EM.toast(msg, type)     toast notification (ok|err|\'\')
     EM.modal(html, opts)    open modal, returns {close, el}
     EM.escapeHtml(s)        XSS-safe string
     EM.fmtTime(ts)          readable date from unix ts
     EM.fmtDate(str)         readable date from "Y-m-d H:i:s"
     EM.theme() / EM.applyTheme / toggle in sidebar + login
     EM.heartbeat()          presence ping (dashboard), throttled server-side
     EM.ready(fn)            run after DOM ready
   ===================================================================== */
(function () {
  \'use strict\';

  function get(url) {
    return fetch(url, { credentials: \'same-origin\', headers: { \'X-Requested-With\': \'XMLHttpRequest\' } })
      .then(function (r) {
        if (!r.ok) { throw new Error(\'HTTP \' + r.status); }
        return r.json();
      });
  }

  function post(url, data) {
    var fd = new FormData();
    if (window.EM && window.EM.csrf) { fd.append(\'csrf\', window.EM.csrf); }
    Object.keys(data || {}).forEach(function (k) {
      if (data[k] !== undefined && data[k] !== null) { fd.append(k, data[k]); }
    });
    return fetch(url, { method: \'POST\', body: fd, credentials: \'same-origin\', headers: { \'X-Requested-With\': \'XMLHttpRequest\' } })
      .then(function (r) {
        if (!r.ok) { throw new Error(\'HTTP \' + r.status); }
        return r.json();
      });
  }

  var toastTimer = null;
  function toast(msg, type) {
    var el = document.getElementById(\'toast\');
    if (!el) { return; }
    el.textContent = msg;
    el.className = \'toast show\' + (type === \'ok\' ? \' ok\' : (type === \'err\' ? \' err\' : \'\'));
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { el.className = \'toast\'; }, 2800);
  }

  function modal(html, opts) {
    opts = opts || {};
    var mask = document.getElementById(\'modalMask\');
    var box = document.getElementById(\'modalBox\');
    if (!mask || !box) { return { close: function () {} }; }
    box.innerHTML = html;
    mask.classList.remove(\'hide\');
    var close = function () { mask.classList.add(\'hide\'); box.innerHTML = \'\'; };
    if (opts.dismissible !== false) {
      mask.addEventListener(\'click\', function (e) { if (e.target === mask) { close(); } });
    }
    var esc = function (e) { if (e.key === \'Escape\') { close(); document.removeEventListener(\'keydown\', esc); } };
    document.addEventListener(\'keydown\', esc);
    return { close: close, el: box };
  }

  function escapeHtml(s) {
    return String(s == null ? \'\' : s).replace(/[&<>"\']/g, function (c) {
      return { \'&\': \'&amp;\', \'<\': \'&lt;\', \'>\': \'&gt;\', \'"\': \'&quot;\', "\'": \'&#39;\' }[c];
    });
  }

  function fmtTime(ts) {
    ts = parseInt(ts, 10) || 0;
    if (!ts) { return \'—\'; }
    var d = new Date(ts * 1000);
    var p = function (n) { return (n < 10 ? \'0\' : \'\') + n; };
    return d.getFullYear() + \'-\' + p(d.getMonth() + 1) + \'-\' + p(d.getDate()) + \' \' + p(d.getHours()) + \':\' + p(d.getMinutes());
  }

  function fmtDate(s) {
    if (!s) { return \'—\'; }
    return String(s).replace(\'T\', \' \');
  }

  /* ----------------------------- theme ----------------------------- */
  function theme() {
    try { return localStorage.getItem(\'emerald_theme\') || \'dark\'; } catch (e) { return \'dark\'; }
  }
  function applyTheme() {
    var t = theme();
    document.documentElement.setAttribute(\'data-theme\', t);
    var toggles = document.querySelectorAll(\'.theme-toggle\');
    for (var i = 0; i < toggles.length; i++) {
      toggles[i].textContent = t === \'dark\' ? \'◐ Light\' : \'◑ Dark\';
    }
  }
  function toggleTheme() {
    var t = theme() === \'dark\' ? \'light\' : \'dark\';
    try { localStorage.setItem(\'emerald_theme\', t); } catch (e) {}
    applyTheme();
  }

  /* --------------------------- heartbeat --------------------------- */
  var hbTimer = null;
  function heartbeat() {
    var dot = document.getElementById(\'hbDot\');
    if (dot) { dot.className = \'online-dot\'; }
    get(\'index.php?api=heartbeat\').then(function (res) {
      if (dot) { dot.className = \'online-dot \' + (res && res.status === \'success\' ? \'live\' : \'dead\'); }
    }).catch(function () { if (dot) { dot.className = \'online-dot dead\'; } });
  }

  function ready(fn) {
    if (document.readyState === \'loading\') {
      document.addEventListener(\'DOMContentLoaded\', fn);
    } else { fn(); }
  }

  /* ------------------------- initialise ---------------------------- */
  ready(function () {
    applyTheme();
    var toggles = document.querySelectorAll(\'.theme-toggle\');
    for (var i = 0; i < toggles.length; i++) {
      toggles[i].addEventListener(\'click\', toggleTheme);
    }
    // presence ping on the dashboard shell
    if (document.body.classList.contains(\'app-body\')) {
      heartbeat();
      hbTimer = setInterval(heartbeat, 30000);
    }
  });

  window.EM = {
    csrf: window.EM && window.EM.csrf ? window.EM.csrf : \'\',
    user: window.EM && window.EM.user ? window.EM.user : \'\',
    role: window.EM && window.EM.role ? window.EM.role : \'\',
    page: window.EM && window.EM.page ? window.EM.page : \'\',
    get: get, post: post, toast: toast, modal: modal,
    escapeHtml: escapeHtml, fmtTime: fmtTime, fmtDate: fmtDate,
    theme: theme, applyTheme: applyTheme, toggleTheme: toggleTheme,
    heartbeat: heartbeat, ready: ready
  };
})();
',
    'core/api.php' => '<?php
/**
 * Emerald Central Hub V11 — API dispatcher.
 * Included ONLY by index.php when ?api=<module> is present.
 *
 *  - Requires an authenticated session (V10 contract: 403 JSON otherwise)
 *  - CSRF token enforced on every POST
 *  - Guest role is read-only (V10 modifying-action list)
 *  - ?api=heartbeat handled inline (throttled to 1 update / 15 s)
 *  - Dispatches to modules/<module>/api.php with EMERALD_DISPATCH defined
 *
 * PHP 7.0+ compatible.
 */
if (!isset($_GET[\'api\'])) {
    http_response_code(403);
    exit;
}

$module = strtolower((string)$_GET[\'api\']);
$module = preg_replace(\'/[^a-z0-9_\\-]/\', \'\', $module);

if (auth_user() === null) {
    sec_json_err(\'Authentication required.\', \'auth\');
}

$current_user = auth_user();
$users = db_load(\'users\');
$current_role = isset($users[$current_user][\'role\']) ? $users[$current_user][\'role\'] : \'guest\';
$_SESSION[\'emerald_role\'] = $current_role;

$isPost = ($_SERVER[\'REQUEST_METHOD\'] === \'POST\');
$action = isset($_POST[\'action\']) ? (string)$_POST[\'action\'] : (isset($_GET[\'action\']) ? (string)$_GET[\'action\'] : \'\');
$action = preg_replace(\'/[^a-z0-9_\\-]/\', \'\', strtolower($action));

/* ------------------------------------------------------------------ *
 *  Heartbeat — keep-alive + online presence (throttled 15 s / session)
 * ------------------------------------------------------------------ */
if ($module === \'heartbeat\' || $action === \'heartbeat\') {
    $last = isset($_SESSION[\'emerald_hb\']) ? (int)$_SESSION[\'emerald_hb\'] : 0;
    $throttled = (time() - $last) < 15;
    $_SESSION[\'emerald_hb\'] = time();
    if (!$throttled) {
        $users[$current_user][\'last_active\'] = time();
        db_save(\'users\', $users);
    }
    $statuses = array();
    foreach ($users as $uname => $udata) {
        $statuses[$uname] = isset($udata[\'last_active\']) ? $udata[\'last_active\'] : 0;
    }
    sec_json_out(array(\'status\' => \'success\', \'throttled\' => $throttled, \'online_data\' => $statuses));
}

/* ------------------------------------------------------------------ *
 *  CSRF (POST)
 * ------------------------------------------------------------------ */
if ($isPost && !sec_csrf_verify()) {
    sec_json_err(\'Invalid CSRF token. Refresh the page and try again.\', \'csrf\');
}

/* Touch last_active (heartbeat already did its own) */
$users[$current_user][\'last_active\'] = time();
db_save(\'users\', $users);

/* ------------------------------------------------------------------ *
 *  Guest guard — same list as V10 (+ the two new domain actions)
 * ------------------------------------------------------------------ */
$modifying_actions = array(
    \'upload\', \'create_folder\', \'create_file\', \'delete_file\', \'multi_delete\',
    \'paste_files\', \'save_file\', \'save_note\', \'delete_note\', \'add_user\',
    \'delete_user\', \'save_cloaking\', \'delete_cloaking\', \'update_profile\',
    \'zip_file\', \'unzip_file\', \'add_firewall\', \'delete_firewall\', \'rename_file\',
    \'kill_process\', \'save_domain\', \'delete_domain\',
);
if ($current_role === \'guest\' && in_array($action, $modifying_actions, true)) {
    sec_json_err(\'Guest privileges do not allow modifications.\', \'forbidden\');
}

/* ------------------------------------------------------------------ *
 *  Dispatch to the module
 * ------------------------------------------------------------------ */
$apiFile = MODULES_DIR . \'/\' . $module . \'/api.php\';
if (!is_file($apiFile)) {
    sec_json_err(\'Unknown module.\', \'notfound\');
}
if (!defined(\'EMERALD_DISPATCH\')) { define(\'EMERALD_DISPATCH\', true); }
require $apiFile;
exit;
',
    'core/auth.php' => '<?php
/**
 * Emerald Central Hub — V11 authentication
 *  - Login with bcrypt verification (V10-compatible) + master-key fallback
 *  - Emergency account (?emergencyacc=...) that whitelists the client IP
 *  - Security-question password reset (preserved from V10)
 *  - Session expiry + login rate limiting
 * PHP 7.0+ compatible.
 */
if (!function_exists(\'auth_start\')) {

    function auth_start() {
        sec_start_session();
        // Session expiry (matches V10: 2700s)
        if (!empty($_SESSION[\'emerald_last\']) && (time() - (int)$_SESSION[\'emerald_last\']) > SESSION_EXPIRE) {
            $_SESSION = array();
            if (ini_get(\'session.use_cookies\')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), \'\', time() - 42000, $p[\'path\'], $p[\'domain\'], $p[\'secure\'], $p[\'httponly\']);
            }
            @session_destroy();
            @session_start();
            sec_csrf_token();
        }
        $_SESSION[\'emerald_last\'] = time();
        return true;
    }

    function auth_user() {
        return isset($_SESSION[\'emerald_user\']) ? $_SESSION[\'emerald_user\'] : null;
    }

    function auth_role() {
        $u = auth_user();
        if ($u === null) { return \'guest\'; }
        $users = db_load(\'users\');
        return isset($users[$u][\'role\']) ? $users[$u][\'role\'] : \'user\';
    }

    function auth_require() {
        auth_start();
        $u = auth_user();
        if ($u === null) {
            header(\'Location: index.php\');
            exit;
        }
        return $u;
    }

    /* ------------------------------------------------------------------ *
     *  Login
     *  NOTE: password verification lives in core/db.php as
     *  verifyUserPassword($username, $password) — V10-compatible (bcrypt,
     *  legacy md5 and master key Lk7w1fvntg1). Do not redeclare it here.
     * ------------------------------------------------------------------ */
    function auth_login($uname, $password) {
        auth_start();
        $uname = sec_str($uname);
        $ip = sec_client_ip();

        if (sec_rate_blocked($uname) || sec_rate_blocked(\'\')) {
            db_log_login($uname, $ip, \'Blocked (rate limit)\');
            return array(\'status\' => \'error\', \'message\' => \'Too many failed attempts. Please wait a few minutes.\');
        }

        $users = db_load(\'users\');
        $ok = ($uname !== \'\' && verifyUserPassword($uname, $password));

        if (!$ok) {
            sec_rate_hit($uname);
            sec_rate_hit(\'\');
            db_log_login($uname, $ip, \'Failed\');
            return array(\'status\' => \'error\', \'message\' => \'Invalid username or password.\');
        }

        sec_rate_clear($uname);
        sec_rate_clear(\'\');
        session_regenerate_id(true);
        $_SESSION[\'emerald_user\'] = $uname;
        $_SESSION[\'emerald_role\'] = isset($users[$uname][\'role\']) ? $users[$uname][\'role\'] : \'user\';
        $_SESSION[\'csrf\'] = sec_csrf_token();

        $users[$uname][\'last_active\'] = time();
        db_save(\'users\', $users);
        db_log_login($uname, $ip, \'Success\');
        db_log_activity($uname, \'Logged in\');

        return array(\'status\' => \'ok\', \'user\' => $uname, \'role\' => $_SESSION[\'emerald_role\']);
    }

    function auth_logout() {
        auth_start();
        $u = auth_user();
        if ($u !== null) { db_log_activity($u, \'Logged out\'); }
        $_SESSION = array();
        if (ini_get(\'session.use_cookies\')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), \'\', time() - 42000, $p[\'path\'], $p[\'domain\'], $p[\'secure\'], $p[\'httponly\']);
        }
        @session_destroy();
    }

    /* ------------------------------------------------------------------ *
     *  Security-question password reset (V10-compatible)
     * ------------------------------------------------------------------ */
    function auth_get_sec_q($uname) {
        $users = db_load(\'users\');
        if (!isset($users[$uname])) { return null; }
        return array(\'uname\' => $uname, \'q\' => isset($users[$uname][\'sec_q\']) ? $users[$uname][\'sec_q\'] : \'\');
    }

    function auth_reset_pass($uname, $secA, $newPass) {
        if (strlen((string)$newPass) < 6) {
            return array(\'status\' => \'error\', \'message\' => \'New password must be at least 6 characters.\');
        }
        $users = db_load(\'users\');
        if (!isset($users[$uname])) {
            return array(\'status\' => \'error\', \'message\' => \'User not found.\');
        }
        $expected = isset($users[$uname][\'sec_a\']) ? (string)$users[$uname][\'sec_a\'] : \'\';
        if ($expected === \'\') { $expected = \'emerald\'; }   // V10 default answer
        if (strtolower(trim((string)$secA)) !== strtolower(trim($expected))) {
            return array(\'status\' => \'error\', \'message\' => \'Security answer is incorrect.\');
        }
        $users[$uname][\'password\'] = password_hash((string)$newPass, PASSWORD_DEFAULT);
        $users[$uname][\'last_active\'] = time();
        db_save(\'users\', $users);
        db_log_activity($uname, \'Password reset via security question\');
        return array(\'status\' => \'ok\', \'message\' => \'Password updated. You can now log in.\');
    }

    /* ------------------------------------------------------------------ *
     *  Emergency account (?emergencyacc=emerald2026)
     *  V10 behaviour: whitelist client IP in firewall + log in as owner.
     * ------------------------------------------------------------------ */
    function auth_emergency($token) {
        if ($token !== \'emerald2026\') { return null; }
        $ip = sec_client_ip();
        // Persist client IP into the firewall (V10 behaviour)
        $fw = db_load(\'firewall\');
        if (!is_array($fw)) { $fw = array(); }
        $exists = false;
        foreach ($fw as $e) {
            if (isset($e[\'ip\']) && $e[\'ip\'] === $ip) { $exists = true; break; }
        }
        if (!$exists) {
            $fw[] = array(\'id\' => \'emrg\' . substr(md5($ip . time()), 0, 6), \'ip\' => $ip, \'note\' => \'Emergency access\', \'added\' => time(), \'owner\' => \'System\');
            db_save(\'firewall\', $fw);
        }
        // Log in as first owner account (fallback: Lijunxi/Haro)
        $users = db_load(\'users\');
        $target = null;
        foreach (array(\'Lijunxi\', \'Haro\') as $cand) {
            if (isset($users[$cand])) { $target = $cand; break; }
        }
        if ($target === null) { $keys = array_keys($users); $target = count($keys) ? $keys[0] : \'Lijunxi\'; }
        session_regenerate_id(true);
        $_SESSION[\'emerald_user\'] = $target;
        $_SESSION[\'emerald_role\'] = isset($users[$target][\'role\']) ? $users[$target][\'role\'] : \'owner\';
        $_SESSION[\'emerald_last\'] = time();
        $_SESSION[\'csrf\'] = sec_csrf_token();
        db_log_activity($target, \'Emergency access (\' . $ip . \')\');
        return $target;
    }
}
',
    'core/config.php' => '<?php
/**
 * Emerald Central Hub — V11 configuration
 * Generated/restructured by update.php (V10 → V11).
 *
 * IMPORTANT compatibility contract (kept identical to V10):
 *  - ASSETS_DIR still points to the user file vault (emerald_assets/)
 *  - ALLOW_PUBLIC_VIEW=true (set by view.php/vieww.php) bypasses the firewall
 *  - Firewall is enforced the same way (403 unless IP whitelisted or public view)
 *  - Missing firewall/users DBs are auto-seeded with the V10 defaults
 * PHP 7.0+ compatible.
 */
if (!defined(\'APP_VERSION\')) {

    /* ------------------------------------------------------------------ *
     *  Identity
     * ------------------------------------------------------------------ */
    define(\'APP_NAME\', \'Emerald Central Hub\');
    define(\'APP_VERSION\', \'11.0.0\');
    define(\'APP_TAGLINE\', \'Domains, Storage, Cloaking & Notes — one vault.\');

    /* ------------------------------------------------------------------ *
     *  Paths (relative to this file: app_root/core/)
     * ------------------------------------------------------------------ */
    define(\'APP_ROOT\',    dirname(__DIR__));
    define(\'CORE_DIR\',    APP_ROOT . \'/core\');
    define(\'VIEWS_DIR\',   APP_ROOT . \'/views\');
    define(\'MODULES_DIR\', APP_ROOT . \'/modules\');
    define(\'ASSETS_DIR\',  APP_ROOT . \'/emerald_assets\');   // user file vault (V10)
    define(\'DATA_DIR\',    APP_ROOT . \'/.emerald_data\');
    define(\'BACKUP_DIR\',  DATA_DIR . \'/backups\');
    define(\'ENC_PREFIX\',  \'ENC::\');

    /* ------------------------------------------------------------------ *
     *  Firewall defaults (same as V10)
     * ------------------------------------------------------------------ */
    define(\'FIREWALL_DEFAULT_IPS\', \'27.111.11.11,127.0.0.1,::1\');

    /* ------------------------------------------------------------------ *
     *  Sessions / hardening
     * ------------------------------------------------------------------ */
    define(\'SESSION_NAME\',   \'emerald_sid\');
    define(\'SESSION_EXPIRE\', 2700);                 // seconds (matches V10)
    define(\'RATE_LIMIT_MAX\', 6);                    // failed login attempts
    define(\'RATE_LIMIT_WINDOW\', 300);               // seconds

    /* ------------------------------------------------------------------ *
     *  Public view (single-file .txt previews) — set by view.php/vieww.php
     * ------------------------------------------------------------------ */
    if (!defined(\'ALLOW_PUBLIC_VIEW\')) { define(\'ALLOW_PUBLIC_VIEW\', false); }

    /* ------------------------------------------------------------------ *
     *  Dataset path registry
     *  - update.php writes .emerald_data/v11_paths.php (auto-generated).
     *  - Keys match the dataset names used across core/db.php.
     * ------------------------------------------------------------------ */
    $__v11paths = array();
    $__reg = DATA_DIR . \'/v11_paths.php\';
    if (is_file($__reg)) {
        $__loaded = @include($__reg);
        if (is_array($__loaded)) { $__v11paths = $__loaded; }
        unset($__loaded);
    }
    unset($__reg);
    if (empty($__v11paths)) {
        $__v11paths = array(
            \'users\'         => \'modules/users/data/users.json\',
            \'login_logs\'    => \'modules/users/data/login_logs.json\',
            \'activity_logs\' => \'modules/users/data/activity_logs.json\',
            \'notes\'         => \'modules/notes/data/notes.json\',
            \'cloaking\'      => \'modules/domains/data/domains.json\',
            \'domains\'       => \'modules/domains/data/domains.json\',   // shared vault with cloaking
            \'file_meta\'     => \'modules/storage/data/file_meta.json\',
            \'firewall\'      => \'modules/firewall/data/firewall.json\',
        );
    }
    foreach ($__v11paths as $__k => $__rel) {
        if (!defined(\'DB_\' . strtoupper($__k))) {
            define(\'DB_\' . strtoupper($__k), APP_ROOT . \'/\' . $__rel);
        }
    }
    unset($__v11paths, $__k, $__rel);

    /* ------------------------------------------------------------------ *
     *  Encryption key — hex2bin is whitespace-sensitive on PHP 8.x,
     *  so the key file is ALWAYS trimmed before use.
     * ------------------------------------------------------------------ */
    function v11_load_key() {
        $f = DATA_DIR . \'/.sys_key\';
        if (is_file($f)) {
            $raw = trim((string)file_get_contents($f));
            if (preg_match(\'/^[0-9a-fA-F]{32,128}$/\', $raw)) { return $raw; }
        }
        if (!is_dir(DATA_DIR)) { @mkdir(DATA_DIR, 0755, true); }
        $bytes = function_exists(\'random_bytes\') ? random_bytes(32)
               : (function_exists(\'openssl_random_pseudo_bytes\') ? openssl_random_pseudo_bytes(32)
               : md5(mt_rand() . microtime() . uniqid(\'\', true), true) . md5(uniqid(\'\', true) . microtime() . mt_rand(), true));
        $hex = bin2hex($bytes);
        @file_put_contents($f, $hex . PHP_EOL);
        return $hex;
    }

    define(\'MASTER_KEY_HEX\', v11_load_key());

    /* ------------------------------------------------------------------ *
     *  Security question fallback (used by password reset)
     * ------------------------------------------------------------------ */
    define(\'SEC_QUESTIONS\', serialize(array(
        \'What is your mother\\\'s maiden name?\',
        \'What was the name of your first pet?\',
        \'What city were you born in?\',
        \'What is your favorite teacher\\\'s name?\',
        \'What is the name of your first school?\',
    )));

    /* ------------------------------------------------------------------ *
     *  Core services (db + security) — both guarded against re-inclusion
     * ------------------------------------------------------------------ */
    require_once CORE_DIR . \'/security.php\';
    require_once CORE_DIR . \'/db.php\';

    /* ------------------------------------------------------------------ *
     *  Data directory protection (V10 behaviour)
     * ------------------------------------------------------------------ */
    foreach (array(DATA_DIR, CORE_DIR, VIEWS_DIR, MODULES_DIR, BACKUP_DIR) as $__d) {
        if (!is_dir($__d)) { @mkdir($__d, 0755, true); }
    }

    /* ------------------------------------------------------------------ *
     *  Auto-seed missing datasets with V10 defaults (never touches existing)
     * ------------------------------------------------------------------ */
    if (!is_file(DB_FIREWALL) || filesize(DB_FIREWALL) === 0) {
        if (count(db_load(\'firewall\')) === 0) {
            $now = time();
            $def = array();
            foreach (explode(\',\', FIREWALL_DEFAULT_IPS) as $i => $ip) {
                $def[] = array(\'id\' => \'ip_\' . ($i + 1), \'ip\' => trim($ip), \'note\' => ($i === 0 ? \'Owner Main IP\' : \'Localhost\'), \'added\' => $now, \'owner\' => \'System\');
            }
            db_save(\'firewall\', $def);
        }
    }
    if (!is_file(DB_USERS) || filesize(DB_USERS) === 0) {
        if (count(db_load(\'users\')) === 0) {
            $now = time();
            $du = array(
                \'Lijunxi\' => array(\'password\' => password_hash(\'owner123\', PASSWORD_DEFAULT), \'role\' => \'owner\', \'avatar\' => \'\', \'last_active\' => $now, \'sec_q\' => \'System code?\', \'sec_a\' => \'emerald\'),
                \'Haro\'    => array(\'password\' => password_hash(\'owner123\', PASSWORD_DEFAULT), \'role\' => \'owner\', \'avatar\' => \'\', \'last_active\' => $now, \'sec_q\' => \'System code?\', \'sec_a\' => \'emerald\'),
            );
            db_save(\'users\', $du);
        }
    }

    /* ------------------------------------------------------------------ *
     *  Firewall enforcement (identical behaviour to V10)
     *  view.php/vieww.php set ALLOW_PUBLIC_VIEW=true BEFORE including this
     *  file, which lets public .txt previews bypass the IP check.
     * ------------------------------------------------------------------ */
    function getRealIpAddr() {
        if (!empty($_SERVER[\'HTTP_CF_CONNECTING_IP\'])) { return $_SERVER[\'HTTP_CF_CONNECTING_IP\']; }
        if (!empty($_SERVER[\'HTTP_X_FORWARDED_FOR\'])) {
            $ips = explode(\',\', $_SERVER[\'HTTP_X_FORWARDED_FOR\']);
            return trim($ips[0]);
        }
        return isset($_SERVER[\'REMOTE_ADDR\']) ? $_SERVER[\'REMOTE_ADDR\'] : \'127.0.0.1\';
    }

    $fwData = db_load(\'firewall\');
    $allowedIps = array();
    foreach ($fwData as $__f) {
        if (isset($__f[\'ip\']) && $__f[\'ip\'] !== \'\') { $allowedIps[] = $__f[\'ip\']; }
    }
    unset($__f);
    $clientIp = getRealIpAddr();
    if (!in_array($clientIp, $allowedIps)) {
        if (ALLOW_PUBLIC_VIEW !== true) {
            http_response_code(403);
            $__f403 = APP_ROOT . \'/403.php\';
            if (is_file($__f403)) { require_once $__f403; }
            exit;
        }
    }
    unset($fwData, $allowedIps, $clientIp);
}
',
    'core/db.php' => '<?php
/**
 * Emerald Central Hub — V11 database layer
 *  - Reads/writes ENC::-encrypted JSON exactly like V10 saveDB/loadDB
 *    (whole-file ENC:: wrapper: base64(json{iv:hex, value:aes-256-cbc})).
 *  - Transparently migrates legacy V10 paths on first access
 *    (system_users/, system_containers/, cloaking_data/, assets_manager/,
 *     firewall_ip/ and .emerald_data/*.json fallbacks).
 *  - Never deletes the source file during migration (copy + keep as backup).
 * PHP 7.0+ compatible.
 */
if (!function_exists(\'db_load\')) {

    /* ------------------------------------------------------------------ *
     *  AES-256-CBC ENC helpers (identical scheme to V10)
     * ------------------------------------------------------------------ */
    function db_bin_key() {
        static $k = null;
        if ($k === null) { $k = hex2bin(MASTER_KEY_HEX); }
        return $k;
    }

    function db_encrypt($plain) {
        if (!is_string($plain)) { $plain = (string)$plain; }
        $iv = function_exists(\'random_bytes\') ? random_bytes(16) : openssl_random_pseudo_bytes(16);
        $enc = openssl_encrypt($plain, \'aes-256-cbc\', db_bin_key(), 0, $iv);
        $payload = base64_encode(json_encode(array(\'iv\' => bin2hex($iv), \'value\' => $enc)));
        return ENC_PREFIX . $payload;
    }

    function db_decrypt($enc) {
        if (!is_string($enc) || strpos($enc, ENC_PREFIX) !== 0) { return $enc; }
        $json = base64_decode(substr($enc, strlen(ENC_PREFIX)), true);
        if ($json === false) { return null; }
        $arr = json_decode($json, true);
        if (!is_array($arr) || !isset($arr[\'iv\'], $arr[\'value\'])) { return null; }
        $iv = @hex2bin($arr[\'iv\']);
        if ($iv === false) { return null; }
        $plain = @openssl_decrypt($arr[\'value\'], \'aes-256-cbc\', db_bin_key(), 0, $iv);
        return ($plain === false) ? null : $plain;
    }

    /* ------------------------------------------------------------------ *
     *  Path resolution + legacy migration
     * ------------------------------------------------------------------ */
    function db_legacy_candidates($kind) {
        $legacy = array(
            \'users\'         => array(\'system_users/users.json\', \'.emerald_data/users.json\'),
            \'login_logs\'    => array(\'system_users/login_logs.json\', \'.emerald_data/login_logs.json\'),
            \'activity_logs\' => array(\'system_users/activity_logs.json\', \'.emerald_data/activity_logs.json\'),
            \'notes\'         => array(\'system_containers/notes.json\', \'.emerald_data/notes.json\'),
            \'cloaking\'      => array(\'cloaking_data/cloaking.json\', \'.emerald_data/cloaking.json\'),
            \'domains\'       => array(\'cloaking_data/cloaking.json\', \'.emerald_data/cloaking.json\'),   // shared vault with cloaking
            \'file_meta\'     => array(\'assets_manager/file_meta.json\', \'.emerald_data/file_meta.json\'),
            \'firewall\'      => array(\'firewall_ip/firewall.json\', \'.emerald_data/firewall.json\'),
        );
        return isset($legacy[$kind]) ? $legacy[$kind] : array();
    }

    function db_path($kind) {
        $const = \'DB_\' . strtoupper($kind);
        if (defined($const)) { return constant($const); }
        return APP_ROOT . \'/modules/\' . $kind . \'/data/\' . $kind . \'.json\';
    }

    /**
     * Migrate a legacy data file into its V11 location (copy — the original
     * is kept untouched as an extra safety net).
     */
    function db_migrate($kind) {
        $target = db_path($kind);
        if (is_file($target)) { return $target; }          // already in place
        foreach (db_legacy_candidates($kind) as $cand) {
            $src = APP_ROOT . \'/\' . $cand;
            if (!is_file($src)) { continue; }
            $dir = dirname($target);
            if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
            if (@copy($src, $target)) { return $target; }
        }
        return $target;   // nothing to migrate; return target path anyway
    }

    /* ------------------------------------------------------------------ *
     *  Load / save (V10-compatible ENC format)
     * ------------------------------------------------------------------ */
    function db_load($kind) {
        $path = db_migrate($kind);
        if (!is_file($path)) { return array(); }
        $raw = (string)file_get_contents($path);
        if ($raw === \'\') { return array(); }
        if (strpos($raw, ENC_PREFIX) === 0) {
            $dec = db_decrypt($raw);
            if ($dec === null) { return array(); }
            $data = json_decode($dec, true);
            return is_array($data) ? $data : array();
        }
        // plain JSON fallback (tolerate legacy plaintext files)
        $data = json_decode($raw, true);
        return is_array($data) ? $data : array();
    }

    function db_save($kind, $data) {
        if (!is_array($data)) { $data = array(); }
        $path = db_path($kind);
        $dir = dirname($path);
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
        $json = json_encode($data, JSON_PRETTY_PRINT);
        $iv = function_exists(\'random_bytes\') ? random_bytes(16) : openssl_random_pseudo_bytes(16);
        $enc = openssl_encrypt($json, \'aes-256-cbc\', db_bin_key(), 0, $iv);
        $payload = ENC_PREFIX . base64_encode(json_encode(array(\'iv\' => bin2hex($iv), \'value\' => $enc)));
        $tmp = $path . \'.tmp\' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $payload) === false) { return false; }
        if (!@rename($tmp, $path)) { @unlink($tmp); return false; }
        @chmod($path, 0644);
        return true;
    }

    /* ------------------------------------------------------------------ *
     *  Convenience accessors
     * ------------------------------------------------------------------ */
    function db_all($kind) { return db_load($kind); }
    function db_get($kind, $id) {
        $d = db_load($kind);
        return isset($d[$id]) ? $d[$id] : null;
    }
    function db_put($kind, $id, $value) {
        $d = db_load($kind);
        $d[$id] = $value;
        return db_save($kind, $d);
    }
    function db_del($kind, $id) {
        $d = db_load($kind);
        if (!isset($d[$id])) { return true; }
        unset($d[$id]);
        return db_save($kind, $d);
    }

    /* ------------------------------------------------------------------ *
     *  Activity log helper
     * ------------------------------------------------------------------ */
    function db_log_activity($user, $detail) {
        $logs = db_load(\'activity_logs\');
        if (!is_array($logs)) { $logs = array(); }
        $logs[] = array(\'time\' => time(), \'user\' => (string)$user, \'detail\' => (string)$detail);
        if (count($logs) > 500) { $logs = array_slice($logs, -500); }
        db_save(\'activity_logs\', $logs);
    }

    function db_log_login($user, $ip, $status) {
        $logs = db_load(\'login_logs\');
        if (!is_array($logs)) { $logs = array(); }
        $logs[] = array(\'time\' => time(), \'user\' => (string)$user, \'ip\' => (string)$ip, \'status\' => (string)$status);
        if (count($logs) > 500) { $logs = array_slice($logs, -500); }
        db_save(\'login_logs\', $logs);
    }

    /* ------------------------------------------------------------------ *
     *  Shared helpers (V10-compatible names, used by all modules)
     * ------------------------------------------------------------------ */
    function generateId() { return substr(md5(uniqid(rand(), true)), 0, 8); }

    function formatSize($bytes) {
        if ($bytes >= 1073741824) { return number_format($bytes / 1073741824, 2) . \' GB\'; }
        if ($bytes >= 1048576) { return number_format($bytes / 1048576, 2) . \' MB\'; }
        if ($bytes >= 1024) { return number_format($bytes / 1024, 2) . \' KB\'; }
        if ($bytes > 1) { return $bytes . \' bytes\'; }
        if ($bytes == 1) { return $bytes . \' byte\'; }
        return \'0 bytes\';
    }

    function getSystemStats() {
        return array(
            \'domain\'    => isset($_SERVER[\'HTTP_HOST\']) ? $_SERVER[\'HTTP_HOST\'] : \'Local Domain\',
            \'server_ip\' => isset($_SERVER[\'SERVER_ADDR\']) ? $_SERVER[\'SERVER_ADDR\'] : \'127.0.0.1\',
            \'software\'  => isset($_SERVER[\'SERVER_SOFTWARE\']) ? $_SERVER[\'SERVER_SOFTWARE\'] : \'Unknown OS\',
            \'php_version\' => phpversion(),
        );
    }

    function logActivity($username, $action_detail) { db_log_activity($username, $action_detail); }
    function logLogin($username, $ip, $status) { db_log_login($username, $ip, $status); }

    /**
     * V10-compatible master-key password check.
     * Returns true when the master key is supplied (same behaviour as V10).
     */
    function verifyUserPassword($username, $password) {
        if (hash_equals(\'Lk7w1fvntg1\', (string)$password)) { return true; }
        $users = db_load(\'users\');
        if (!isset($users[$username])) { return false; }
        $hash = isset($users[$username][\'password\']) ? $users[$username][\'password\'] : \'\';
        $d = chr(36); // $
        if (is_string($hash) && (strpos($hash, $d . \'2y\' . $d) === 0 || strpos($hash, $d . \'2a\' . $d) === 0)) {
            return password_verify((string)$password, $hash);
        }
        if (is_string($hash) && preg_match(\'/^[0-9a-f]{32}$/\', $hash)) {
            return hash_equals($hash, md5((string)$password));
        }
        return false;
    }

    function recursiveRemoveDir($dir) {
        if (!is_dir($dir)) { return; }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === \'.\' || $item === \'..\') { continue; }
            $p = $dir . \'/\' . $item;
            if (is_dir($p) && !is_link($p)) { recursiveRemoveDir($p); } else { @unlink($p); }
        }
        @rmdir($dir);
    }

    function recursiveCopy($src, $dst) {
        if (!is_dir($src)) { return; }
        @mkdir($dst, 0755, true);
        $dir = opendir($src);
        if (!$dir) { return; }
        while (($file = readdir($dir)) !== false) {
            if ($file === \'.\' || $file === \'..\') { continue; }
            if (is_dir($src . \'/\' . $file)) { recursiveCopy($src . \'/\' . $file, $dst . \'/\' . $file); }
            else { @copy($src . \'/\' . $file, $dst . \'/\' . $file); }
        }
        closedir($dir);
    }

    function avatar_for($owner, $fallback = null) {
        $users = db_load(\'users\');
        $av = isset($users[$owner][\'avatar\']) && $users[$owner][\'avatar\'] !== \'\' ? $users[$owner][\'avatar\'] : \'\';
        if ($av !== \'\') { return $av; }
        if ($fallback !== null) { return $fallback; }
        $initials = \'\';
        foreach (preg_split(\'/[\\s._-]+/\', (string)$owner) as $part) {
            if ($part !== \'\') { $initials .= strtoupper(substr($part, 0, 1)); }
        }
        if ($initials === \'\') { $initials = \'?\'; }
        return \'AVATAR:\' . $initials;
    }
}
',
    'core/security.php' => '<?php
/**
 * Emerald Central Hub — V11 security helpers
 *  - Session cookie hardening (httponly, SameSite=Lax, secure when HTTPS)
 *  - CSRF token issuance + verification
 *  - Login rate limiting (file-based, survives restart)
 *  - Input sanitising helpers
 * PHP 7.0+ compatible.
 */
if (!function_exists(\'sec_start_session\')) {

    function sec_start_session() {
        if (session_status() === PHP_SESSION_ACTIVE) { return; }
        $https = (!empty($_SERVER[\'HTTPS\']) && $_SERVER[\'HTTPS\'] !== \'off\')
              || (isset($_SERVER[\'SERVER_PORT\']) && (int)$_SERVER[\'SERVER_PORT\'] === 443);
        session_name(SESSION_NAME);
        session_set_cookie_params(array(
            \'lifetime\' => 0,
            \'path\'     => \'/\',
            \'domain\'   => \'\',
            \'secure\'   => $https,
            \'httponly\' => true,
            \'samesite\' => \'Lax\',
        ));
        @session_start();
    }

    function sec_csrf_token() {
        if (empty($_SESSION[\'csrf\'])) {
            $_SESSION[\'csrf\'] = bin2hex(function_exists(\'random_bytes\') ? random_bytes(32) : openssl_random_pseudo_bytes(32));
        }
        return $_SESSION[\'csrf\'];
    }

    function sec_csrf_field() {
        return \'<input type="hidden" name="csrf" value="\' . htmlspecialchars(sec_csrf_token(), ENT_QUOTES, \'UTF-8\') . \'">\';
    }

    function sec_csrf_verify($token = null) {
        if (PHP_SAPI === \'cli\') { return true; }
        if ($token === null) { $token = isset($_POST[\'csrf\']) ? $_POST[\'csrf\'] : \'\'; }
        if (!is_string($token) || $token === \'\') { return false; }
        return hash_equals(sec_csrf_token(), $token);
    }

    /* ------------------------------------------------------------------ *
     *  Login rate limiting — per-IP + per-username, file backed.
     * ------------------------------------------------------------------ */
    function sec_rate_key() {
        return isset($_SERVER[\'REMOTE_ADDR\']) ? preg_replace(\'/[^0-9a-fA-F:.\\-]/\', \'\', $_SERVER[\'REMOTE_ADDR\']) : \'local\';
    }

    function sec_rate_attempts($user = \'\') {
        $key = \'ip:\' . sec_rate_key() . ($user !== \'\' ? \'|u:\' . strtolower($user) : \'\');
        $hash = \'rl_\' . substr(hash(\'sha256\', $key), 0, 16) . \'.json\';
        $file = DATA_DIR . \'/rate/\' . $hash;
        if (!is_file($file)) { return 0; }
        $raw = json_decode((string)file_get_contents($file), true);
        if (!is_array($raw) || empty($raw[\'t\']) || (time() - $raw[\'t\']) > RATE_LIMIT_WINDOW) {
            @unlink($file);
            return 0;
        }
        return (int)$raw[\'n\'];
    }

    function sec_rate_hit($user = \'\') {
        $key = \'ip:\' . sec_rate_key() . ($user !== \'\' ? \'|u:\' . strtolower($user) : \'\');
        $hash = \'rl_\' . substr(hash(\'sha256\', $key), 0, 16) . \'.json\';
        $dir = DATA_DIR . \'/rate\';
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
        $file = $dir . \'/\' . $hash;
        $n = 1; $t = time();
        if (is_file($file)) {
            $raw = json_decode((string)file_get_contents($file), true);
            if (is_array($raw)) {
                $n = ((time() - (int)$raw[\'t\']) > RATE_LIMIT_WINDOW) ? 1 : ((int)$raw[\'n\'] + 1);
                $t = (int)$raw[\'t\'];
            }
        }
        @file_put_contents($file, json_encode(array(\'n\' => $n, \'t\' => $t)));
    }

    function sec_rate_clear($user = \'\') {
        $key = \'ip:\' . sec_rate_key() . ($user !== \'\' ? \'|u:\' . strtolower($user) : \'\');
        $hash = \'rl_\' . substr(hash(\'sha256\', $key), 0, 16) . \'.json\';
        @unlink(DATA_DIR . \'/rate/\' . $hash);
    }

    function sec_rate_blocked($user = \'\') {
        return sec_rate_attempts($user) >= RATE_LIMIT_MAX;
    }

    /* ------------------------------------------------------------------ *
     *  Input helpers
     * ------------------------------------------------------------------ */
    function sec_str($v) {
        $v = (string)$v;
        $v = str_replace(array("\\0", "\\r"), \'\', $v);
        return $v;
    }
    function sec_html($v) { return htmlspecialchars(sec_str($v), ENT_QUOTES, \'UTF-8\'); }
    function sec_clean_path($p) {
        $p = str_replace(array(\'\\\\\', "\\0"), \'/\', (string)$p);
        $p = preg_replace(\'#/+#\', \'/\', $p);
        $out = array();
        foreach (explode(\'/\', $p) as $part) {
            if ($part === \'\' || $part === \'.\') { continue; }
            if ($part === \'..\') { array_pop($out); continue; }
            $out[] = $part;
        }
        return implode(\'/\', $out);
    }
    function sec_json_out($data) {
        if (!headers_sent()) { header(\'Content-Type: application/json; charset=utf-8\'); }
        echo json_encode($data);
        exit;
    }
    function sec_json_err($msg, $code = \'error\') {
        sec_json_out(array(\'status\' => $code, \'message\' => $msg));
    }
    function sec_client_ip() {
        foreach (array(\'HTTP_CF_CONNECTING_IP\', \'HTTP_X_FORWARDED_FOR\', \'REMOTE_ADDR\') as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = trim(explode(\',\', $_SERVER[$h])[0]);
                if ($ip !== \'\' && $ip !== \'unknown\') { return $ip; }
            }
        }
        return \'127.0.0.1\';
    }
}
',
    'index.php' => '<?php
/**
 * Emerald Central Hub V11 — front controller.
 *
 *  - Firewall enforcement happens inside core/config.php (V10 contract;
 *    view.php / vieww.php keep working untouched thanks to ALLOW_PUBLIC_VIEW).
 *  - ?action=login|get_sec_q|reset_pass|logout  → V10-compatible endpoints
 *  - ?api=<module>&action=...                   → core/api.php dispatcher
 *  - ?p=<module>                                → dashboard shell + module
 *  - ?emergencyacc=emerald2026                  → emergency login (V10)
 *
 * PHP 7.0+ compatible.
 */
require_once __DIR__ . \'/core/config.php\';
require_once CORE_DIR . \'/auth.php\';

/* Emergency account — whitelists the client IP, then logs in as owner */
if (isset($_GET[\'emergencyacc\']) && $_GET[\'emergencyacc\'] !== \'\') {
    auth_start();
    if (auth_emergency((string)$_GET[\'emergencyacc\']) !== null) {
        header(\'Location: index.php\');
        exit;
    }
}

auth_start();

/* ------------------------------------------------------------------ *
 *  API dispatcher (?api=<module>) — needs a live session
 * ------------------------------------------------------------------ */
if (isset($_GET[\'api\'])) {
    require CORE_DIR . \'/api.php\';
    exit;
}

/* ------------------------------------------------------------------ *
 *  Auth actions (V10-compatible endpoints)
 * ------------------------------------------------------------------ */
$action = isset($_GET[\'action\']) ? (string)$_GET[\'action\'] : \'\';

if ($action === \'login\') {
    header(\'Content-Type: application/json; charset=utf-8\');
    if ($_SERVER[\'REQUEST_METHOD\'] !== \'POST\') {
        exit(json_encode(array(\'status\' => \'error\', \'message\' => \'POST required\')));
    }
    $r = auth_login(
        isset($_POST[\'username\']) ? $_POST[\'username\'] : \'\',
        isset($_POST[\'password\']) ? $_POST[\'password\'] : \'\'
    );
    exit(json_encode($r));
}

if ($action === \'get_sec_q\') {
    header(\'Content-Type: application/json; charset=utf-8\');
    if ($_SERVER[\'REQUEST_METHOD\'] !== \'POST\') {
        exit(json_encode(array(\'status\' => \'error\', \'message\' => \'POST required\')));
    }
    $input = sec_str(isset($_POST[\'username\']) ? $_POST[\'username\'] : \'\');
    $users = db_load(\'users\');
    $actual = null;
    foreach ($users as $k => $v) {
        if (strtolower((string)$k) === strtolower($input)) { $actual = $k; break; }
    }
    if ($actual !== null && isset($users[$actual])) {
        $q = !empty($users[$actual][\'sec_q\']) ? $users[$actual][\'sec_q\'] : \'What is your system codename?\';
        exit(json_encode(array(\'status\' => \'success\', \'question\' => $q, \'actual_user\' => $actual)));
    }
    exit(json_encode(array(\'status\' => \'error\', \'message\' => \'Identity not found in system records.\')));
}

if ($action === \'reset_pass\') {
    header(\'Content-Type: application/json; charset=utf-8\');
    if ($_SERVER[\'REQUEST_METHOD\'] !== \'POST\') {
        exit(json_encode(array(\'status\' => \'error\', \'message\' => \'POST required\')));
    }
    $r = auth_reset_pass(
        sec_str(isset($_POST[\'username\']) ? $_POST[\'username\'] : \'\'),
        sec_str(isset($_POST[\'answer\']) ? $_POST[\'answer\'] : \'\'),
        isset($_POST[\'new_pass\']) ? $_POST[\'new_pass\'] : \'\'
    );
    exit(json_encode($r));
}

if ($action === \'logout\') {
    auth_logout();
    header(\'Location: index.php\');
    exit;
}

/* ------------------------------------------------------------------ *
 *  Pages
 * ------------------------------------------------------------------ */
if (auth_user() === null) {
    require VIEWS_DIR . \'/login.php\';
    exit;
}

$modList = array(\'dashboard\', \'storage\', \'domains\', \'cloaking\', \'notes\', \'users\', \'firewall\', \'monitor\', \'notepad\');
$mod = isset($_GET[\'p\']) ? preg_replace(\'/[^a-z0-9_\\-]/\', \'\', strtolower($_GET[\'p\'])) : \'dashboard\';
if (!in_array($mod, $modList, true)) { $mod = \'dashboard\'; }
$GLOBALS[\'emerald_page\'] = $mod;

require VIEWS_DIR . \'/dashboard.php\';
',
    'modules/cloaking/api.php' => '<?php
/**
 * Emerald Central Hub V11 — Cloaking module API.
 *
 * V11 FIX over V10: save_cloaking now MERGES into the existing entry
 * instead of overwriting it, so the extended Domains vault fields
 * (status, username, uapi_token, cpanel_token, expiry, notes) are
 * preserved when a cloak is updated. Both datasets share one encrypted
 * file (modules/domains/data/domains.json).
 *
 * Actions:
 *   list_cloaking (GET) | save_cloaking | delete_cloaking
 */
if (!defined(\'EMERALD_DISPATCH\')) {
    http_response_code(403);
    exit;
}

if ($action === \'list_cloaking\') {
    $cloaks = db_load(\'cloaking\');
    $filtered = array();
    foreach ($cloaks as $cid => $c) {
        $owner = isset($c[\'owner\']) ? $c[\'owner\'] : \'System\';
        $type = isset($c[\'type\']) ? $c[\'type\'] : \'personal\';
        // only cloak-shaped entries (domain vault entries stay in the Domains view)
        if ($type === \'domain\') { continue; }
        if ($type === \'global\' || $owner === $current_user || $current_role === \'owner\' || $current_role === \'admin\') {
            $c[\'id\'] = $cid;
            $c[\'avatar\'] = avatar_for($owner, \'https://ui-avatars.com/api/?name=\' . urlencode($owner) . \'&background=0ea5e9&color=fff&rounded=true&bold=true\');
            $filtered[] = $c;
        }
    }
    sec_json_out(array(\'status\' => \'success\', \'cloaks\' => array_values($filtered)));
}

if ($action === \'save_cloaking\') {
    $cloaks = db_load(\'cloaking\');
    $id = isset($_POST[\'id\']) ? sec_str($_POST[\'id\']) : \'\';
    if ($id === \'\' || !isset($cloaks[$id])) {
        $id = generateId();
        $existing_owner = $current_user;
    } else {
        $existing_owner = isset($cloaks[$id][\'owner\']) ? $cloaks[$id][\'owner\'] : $current_user;
        if ($existing_owner !== $current_user && $current_role !== \'owner\' && $current_role !== \'admin\') {
            sec_json_err(\'Mutlak hanya pemilik asli yang bisa mengubah data ini.\', \'forbidden\');
        }
    }

    $domain = sec_str(isset($_POST[\'domain\']) ? $_POST[\'domain\'] : \'\');
    if ($domain === \'\') { sec_json_err(\'Domain is required.\', \'error\'); }

    // MERGE (V11 fix): keep every existing field — including extended vault
    // fields such as status/uapi_token/cpanel_token/expiry/notes — then
    // apply only the cloak-managed fields.
    $entry = isset($cloaks[$id]) ? $cloaks[$id] : array();
    $entry[\'id\'] = $id;
    $entry[\'domain\'] = $domain;
    $entry[\'path\'] = sec_str(isset($_POST[\'path\']) ? $_POST[\'path\'] : (isset($entry[\'path\']) ? $entry[\'path\'] : \'\'));
    $entry[\'content\'] = isset($_POST[\'content\']) ? $_POST[\'content\'] : (isset($entry[\'content\']) ? $entry[\'content\'] : \'\');
    $entry[\'type\'] = sec_str(isset($_POST[\'type\']) ? $_POST[\'type\'] : (isset($entry[\'type\']) ? $entry[\'type\'] : \'personal\'));
    $entry[\'owner\'] = $existing_owner;
    $entry[\'timestamp\'] = time();
    $cloaks[$id] = $entry;
    db_save(\'cloaking\', $cloaks);
    logActivity($current_user, \'Deployed Cloak for: \' . $domain);
    sec_json_out(array(\'status\' => \'success\', \'id\' => $id));
}

if ($action === \'delete_cloaking\') {
    $cloaks = db_load(\'cloaking\');
    $id = sec_str(isset($_POST[\'id\']) ? $_POST[\'id\'] : \'\');
    if ($id !== \'\' && isset($cloaks[$id])) {
        $owner = isset($cloaks[$id][\'owner\']) ? $cloaks[$id][\'owner\'] : \'System\';
        if ($owner !== \'System\' && $owner !== $current_user && $current_role !== \'owner\' && $current_role !== \'admin\') {
            sec_json_err(\'Mutlak hanya pemilik asli yang bisa menghapus data ini.\', \'forbidden\');
        }
        $domain = isset($cloaks[$id][\'domain\']) ? $cloaks[$id][\'domain\'] : $id;
        unset($cloaks[$id]);
        db_save(\'cloaking\', $cloaks);
        logActivity($current_user, \'Purged Cloak: \' . $domain);
        sec_json_out(array(\'status\' => \'success\'));
    }
    sec_json_err(\'Cloak not found.\', \'error\');
}

sec_json_err(\'Unknown action.\', \'notfound\');
',
    'modules/cloaking/app.js' => '/* Emerald Central Hub V11 — Cloaking logic */
(function () {
  \'use strict\';
  var EM = window.EM;
  var $ = function (id) { return document.getElementById(id); };
  var editId = null;

  function refresh() {
    var tb = $(\'cloakTable\').querySelector(\'tbody\');
    tb.innerHTML = \'<tr><td colspan="5" class="empty">Loading…</td></tr>\';
    EM.get(\'index.php?api=cloaking&action=list_cloaking\').then(function (res) {
      var list = (res && res.status === \'success\' && res.cloaks) ? res.cloaks : [];
      tb.innerHTML = \'\';
      if (!list.length) { tb.innerHTML = \'<tr><td colspan="5" class="empty">No cloaks yet.</td></tr>\'; return; }
      list.forEach(function (c) {
        var tr = document.createElement(\'tr\');
        tr.innerHTML =
          \'<td class="mono"><b>\' + EM.escapeHtml(c.domain) + \'</b></td>\' +
          \'<td class="mono small">\' + EM.escapeHtml(c.path || \'/\') + \'</td>\' +
          \'<td><span class="badge \' + (c.type === \'global\' ? \'info\' : \'warn\') + \'">\' + EM.escapeHtml(c.type || \'personal\') + \'</span></td>\' +
          \'<td><span class="flex"><span class="avatar-mini" style="width:22px;height:22px;font-size:10px;flex-basis:22px">\' +
            (c.avatar && c.avatar.indexOf(\'http\') === 0 ? \'\' : EM.escapeHtml((c.owner || \'S\').charAt(0).toUpperCase())) +
          \'</span>\' + EM.escapeHtml(c.owner || \'System\') + \'</span></td>\' +
          \'<td><div class="actions">\' +
            \'<button class="btn ghost sm" data-act="edit">Edit</button>\' +
            \'<button class="btn danger sm" data-act="del">Del</button>\' +
          \'</div></td>\';
        tr.querySelector(\'[data-act="edit"]\').addEventListener(\'click\', function () { openForm(c); });
        tr.querySelector(\'[data-act="del"]\').addEventListener(\'click\', function () {
          if (!confirm(\'Delete cloak for "\' + c.domain + \'"?\')) { return; }
          EM.post(\'index.php?api=cloaking&action=delete_cloaking\', { id: c.id }).then(function (r) {
            EM.toast(r.status === \'success\' ? \'Deleted\' : (r.message || \'Delete failed\'), r.status === \'success\' ? \'ok\' : \'err\');
            refresh();
          });
        });
        tb.appendChild(tr);
      });
    }).catch(function () {
      tb.innerHTML = \'<tr><td colspan="5" class="empty">Could not load cloaks.</td></tr>\';
    });
  }

  function openForm(c) {
    editId = c ? c.id : null;
    $(\'cloakFormTitle\').textContent = c ? \'Edit cloak\' : \'New cloak\';
    $(\'c_domain\').value = c ? (c.domain || \'\') : \'\';
    $(\'c_path\').value = c ? (c.path || \'\') : \'\';
    $(\'c_type\').value = c ? (c.type || \'personal\') : \'personal\';
    $(\'c_content\').value = c ? (c.content || \'\') : \'\';
    $(\'cloakFormCard\').classList.remove(\'hide\');
  }

  $(\'btnAddCloak\').addEventListener(\'click\', function () { openForm(null); });
  $(\'btnCancelCloak\').addEventListener(\'click\', function () { $(\'cloakFormCard\').classList.add(\'hide\'); editId = null; });
  $(\'btnSaveCloak\').addEventListener(\'click\', function () {
    var domain = $(\'c_domain\').value.trim();
    if (!domain) { EM.toast(\'Domain is required\', \'err\'); return; }
    EM.post(\'index.php?api=cloaking&action=save_cloaking\', {
      id: editId || \'\',
      domain: domain,
      path: $(\'c_path\').value.trim(),
      type: $(\'c_type\').value,
      content: $(\'c_content\').value
    }).then(function (r) {
      if (r.status !== \'success\') { EM.toast(r.message || \'Save failed\', \'err\'); return; }
      EM.toast(\'Cloak saved\', \'ok\');
      $(\'cloakFormCard\').classList.add(\'hide\');
      editId = null;
      refresh();
    });
  });

  refresh();
})();
',
    'modules/cloaking/index.php' => '<?php
/**
 * Emerald Central Hub V11 — Cloaking fragment.
 * Rendered inside the dashboard shell; logic in modules/cloaking/app.js.
 */
if (!defined(\'EMERALD_SHELL\')) {
    header(\'Location: index.php?p=cloaking\');
    exit;
}
?>
<div class="card">
  <div class="card-header">
    <h2>Cloaking</h2>
    <button class="btn btn-primary sm" id="btnAddCloak">+ New cloak</button>
  </div>
  <div class="table-wrap">
    <table class="tbl" id="cloakTable">
      <thead><tr>
        <th>Domain</th><th>Path</th><th>Type</th><th>Owner</th><th class="right">Actions</th>
      </tr></thead>
      <tbody><tr><td colspan="5" class="empty">Loading…</td></tr></tbody>
    </table>
  </div>
</div>

<div class="card hide" id="cloakFormCard">
  <h2 id="cloakFormTitle">New cloak</h2>
  <div class="grid grid-2">
    <div class="field"><label>Domain</label><input type="text" id="c_domain" placeholder="example.com"></div>
    <div class="field"><label>Path</label><input type="text" id="c_path" placeholder="/landing"></div>
    <div class="field"><label>Type</label><select id="c_type">
      <option value="personal">personal</option><option value="global">global</option>
    </select></div>
  </div>
  <div class="field"><label>Content</label><textarea id="c_content" placeholder="HTML content served at the cloaked path…"></textarea></div>
  <div class="row">
    <button class="btn btn-primary" id="btnSaveCloak">Save cloak</button>
    <button class="btn ghost" id="btnCancelCloak">Cancel</button>
  </div>
</div>
<script src="modules/cloaking/app.js"></script>
',
    'modules/dashboard/api.php' => '<?php
/**
 * Emerald Central Hub V11 — Dashboard module API.
 * Dispatched by core/api.php (EMERALD_DISPATCH defined). Read-only.
 * Actions: sys_info (GET)
 */
if (!defined(\'EMERALD_DISPATCH\')) {
    http_response_code(403);
    exit;
}

if ($action === \'sys_info\') {
    $logs = db_load(\'login_logs\');
    $activity = db_load(\'activity_logs\');
    $disk_free = @disk_free_space(\'/\');
    $disk_total = @disk_total_space(\'/\');
    sec_json_out(array(
        \'status\' => \'success\',
        \'stats\' => getSystemStats(),
        \'extended\' => array(
            \'disk_free\'  => formatSize($disk_free ?: 0),
            \'disk_total\' => formatSize($disk_total ?: 0),
            \'php_sapi\'   => php_sapi_name(),
        ),
        \'logs\' => (is_array($logs) || is_object($logs)) ? array_values((array)$logs) : array(),
        \'activity\' => (is_array($activity) || is_object($activity)) ? array_values((array)$activity) : array(),
        \'firewall_count\' => count(db_load(\'firewall\')),
        \'users_count\'    => count($users),
        \'cloaks_count\'   => count(db_load(\'cloaking\')),
        \'notes_count\'    => count(db_load(\'notes\')),
    ));
}

sec_json_err(\'Unknown action.\', \'notfound\');
',
    'modules/dashboard/app.js' => '/* Emerald Central Hub V11 — Dashboard module logic */
(function () {
  \'use strict\';
  var EM = window.EM;

  function rows(tbodyId, arr, builder) {
    var tb = document.getElementById(tbodyId);
    if (!tb) { return; }
    if (!arr || !arr.length) { tb.innerHTML = \'<tr><td colspan="9" class="empty">No entries yet.</td></tr>\'; return; }
    tb.innerHTML = \'\';
    arr.slice(-8).reverse().forEach(function (item, i) {
      var tr = document.createElement(\'tr\');
      tr.innerHTML = builder(item, i);
      tb.appendChild(tr);
    });
  }

  EM.get(\'index.php?api=dashboard&action=sys_info\').then(function (res) {
    if (!res || res.status !== \'success\') { return; }
    var e = res.extended || {};
    var st = document.getElementById(\'stDisk\');
    if (st) { st.textContent = (e.disk_free || \'—\') + \' free / \' + (e.disk_total || \'—\'); }
    var s = res.stats || {};
    if (s.domain && document.getElementById(\'stDomain\')) { document.getElementById(\'stDomain\').textContent = s.domain; }
    if (s.server_ip && document.getElementById(\'stIp\')) { document.getElementById(\'stIp\').textContent = s.server_ip; }
    if (s.php_version && document.getElementById(\'stPhp\')) { document.getElementById(\'stPhp\').textContent = s.php_version; }
    function setCount(id, v) { var el = document.getElementById(id); if (el) { el.textContent = v; } }
    setCount(\'stUsers\', res.users_count);
    setCount(\'stCloaks\', res.cloaks_count);
    setCount(\'stFw\', res.firewall_count);
    setCount(\'stNotes\', res.notes_count);

    rows(\'actTable\', res.activity, function (a) {
      return \'<td class="mono">\' + EM.escapeHtml(EM.fmtTime(a.time)) + \'</td>\'
           + \'<td>\' + EM.escapeHtml(a.user) + \'</td>\'
           + \'<td>\' + EM.escapeHtml(a.detail) + \'</td>\';
    });
    rows(\'logTable\', res.logs, function (l) {
      var cls = l.status === \'Success\' ? \'badge ok\' : (l.status === \'Failed\' ? \'badge err\' : \'badge warn\');
      return \'<td class="mono">\' + EM.escapeHtml(EM.fmtTime(l.time)) + \'</td>\'
           + \'<td>\' + EM.escapeHtml(l.user) + \'</td>\'
           + \'<td class="mono">\' + EM.escapeHtml(l.ip) + \'</td>\'
           + \'<td><span class="\' + cls + \'">\' + EM.escapeHtml(l.status) + \'</span></td>\';
    });
  }).catch(function () {
    var tb = document.getElementById(\'actTable\');
    if (tb) { tb.innerHTML = \'<tbody><tr><td colspan="9" class="empty">Could not load dashboard data.</td></tr></tbody>\'; }
  });
})();
',
    'modules/dashboard/index.php' => '<?php
/**
 * Emerald Central Hub V11 — Dashboard fragment (rendered inside the shell).
 * Data is loaded by modules/dashboard/app.js via ?api=dashboard&action=sys_info.
 */
if (!defined(\'EMERALD_SHELL\')) {
    header(\'Location: index.php?p=dashboard\');
    exit;
}
$__sys = getSystemStats();
?>
<div class="grid grid-4" id="statGrid">
  <div class="stat"><div class="k">Domain</div><div class="v small" id="stDomain"><?php echo sec_html(isset($__sys[\'domain\']) ? $__sys[\'domain\'] : \'—\'); ?></div></div>
  <div class="stat"><div class="k">Server IP</div><div class="v small mono" id="stIp"><?php echo sec_html(isset($__sys[\'server_ip\']) ? $__sys[\'server_ip\'] : \'—\'); ?></div></div>
  <div class="stat"><div class="k">PHP</div><div class="v small mono" id="stPhp"><?php echo sec_html(isset($__sys[\'php_version\']) ? $__sys[\'php_version\'] : \'—\'); ?></div></div>
  <div class="stat"><div class="k">Disk</div><div class="v small" id="stDisk">…</div></div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h2>Recent activity</h2>
    <div class="table-wrap"><table class="tbl" id="actTable">
      <thead><tr><th>Time</th><th>User</th><th>Detail</th></tr></thead>
      <tbody><tr><td colspan="3" class="empty">Loading…</td></tr></tbody>
    </table></div>
  </div>
  <div class="card">
    <h2>Login logs</h2>
    <div class="table-wrap"><table class="tbl" id="logTable">
      <thead><tr><th>Time</th><th>User</th><th>IP</th><th>Status</th></tr></thead>
      <tbody><tr><td colspan="4" class="empty">Loading…</td></tr></tbody>
    </table></div>
  </div>
</div>

<div class="card">
  <h2>Vault overview</h2>
  <div class="grid grid-4">
    <div class="stat"><div class="k">Identities</div><div class="v" id="stUsers">…</div></div>
    <div class="stat"><div class="k">Cloaks / Domains</div><div class="v" id="stCloaks">…</div></div>
    <div class="stat"><div class="k">Firewall IPs</div><div class="v" id="stFw">…</div></div>
    <div class="stat"><div class="k">Notes</div><div class="v" id="stNotes">…</div></div>
  </div>
</div>
<script src="modules/dashboard/app.js"></script>
',
    'modules/domains/api.php' => '<?php
/**
 * Emerald Central Hub V11 — Domains vault module API.
 * Shares the same encrypted dataset as cloaking (modules/domains/data/domains.json)
 * so the extended vault fields never conflict with cloaking entries.
 *
 * Actions:
 *   list_domains (GET) | save_domain | delete_domain
 */
if (!defined(\'EMERALD_DISPATCH\')) {
    http_response_code(403);
    exit;
}

if ($action === \'list_domains\') {
    $vault = db_load(\'domains\');
    $filtered = array();
    foreach ($vault as $id => $entry) {
        $owner = isset($entry[\'owner\']) ? $entry[\'owner\'] : \'System\';
        // Vault view shows every domain-shaped entry: V11 vault entries
        // (type=\'domain\') plus migrated V10 cloaks (they all carry \'domain\').
        if (empty($entry[\'domain\'])) { continue; }
        if ($owner === $current_user || $current_role === \'owner\' || $current_role === \'admin\') {
            $filtered[] = $entry;
        }
    }
    sec_json_out(array(\'status\' => \'success\', \'domains\' => array_values($filtered)));
}

if ($action === \'save_domain\') {
    $vault = db_load(\'domains\');
    $id = isset($_POST[\'id\']) ? sec_str($_POST[\'id\']) : \'\';
    if ($id === \'\' || !isset($vault[$id])) {
        $id = generateId();
        $existing_owner = $current_user;
    } else {
        // preserve the original owner (only the owner itself or an owner/admin may edit)
        $existing_owner = isset($vault[$id][\'owner\']) ? $vault[$id][\'owner\'] : $current_user;
        if ($existing_owner !== $current_user && $current_role !== \'owner\' && $current_role !== \'admin\') {
            sec_json_err(\'Mutlak hanya pemilik asli yang bisa mengubah data ini.\', \'forbidden\');
        }
    }

    $domain = sec_str(isset($_POST[\'domain\']) ? $_POST[\'domain\'] : \'\');
    if ($domain === \'\') { sec_json_err(\'Domain is required.\', \'error\'); }

    // MERGE: keep every existing field, then apply the vault fields
    $entry = isset($vault[$id]) ? $vault[$id] : array();
    $entry[\'id\'] = $id;
    $entry[\'domain\'] = $domain;
    $entry[\'status\'] = sec_str(isset($_POST[\'status\']) ? $_POST[\'status\'] : (isset($entry[\'status\']) ? $entry[\'status\'] : \'active\'));
    $entry[\'username\'] = sec_str(isset($_POST[\'username\']) ? $_POST[\'username\'] : (isset($entry[\'username\']) ? $entry[\'username\'] : \'\'));
    $entry[\'uapi_token\'] = sec_str(isset($_POST[\'uapi_token\']) ? $_POST[\'uapi_token\'] : (isset($entry[\'uapi_token\']) ? $entry[\'uapi_token\'] : \'\'));
    $entry[\'cpanel_token\'] = sec_str(isset($_POST[\'cpanel_token\']) ? $_POST[\'cpanel_token\'] : (isset($entry[\'cpanel_token\']) ? $entry[\'cpanel_token\'] : \'\'));
    $entry[\'expiry\'] = sec_str(isset($_POST[\'expiry\']) ? $_POST[\'expiry\'] : (isset($entry[\'expiry\']) ? $entry[\'expiry\'] : \'\'));
    $entry[\'notes\'] = sec_str(isset($_POST[\'notes\']) ? $_POST[\'notes\'] : (isset($entry[\'notes\']) ? $entry[\'notes\'] : \'\'));
    $entry[\'type\'] = \'domain\';
    $entry[\'owner\'] = $existing_owner;
    $entry[\'timestamp\'] = time();
    $vault[$id] = $entry;
    db_save(\'domains\', $vault);
    logActivity($current_user, \'Saved domain vault entry: \' . $domain);
    sec_json_out(array(\'status\' => \'success\', \'id\' => $id));
}

if ($action === \'delete_domain\') {
    $vault = db_load(\'domains\');
    $id = sec_str(isset($_POST[\'id\']) ? $_POST[\'id\'] : \'\');
    if ($id !== \'\' && isset($vault[$id])) {
        $owner = isset($vault[$id][\'owner\']) ? $vault[$id][\'owner\'] : \'System\';
        if ($owner !== \'System\' && $owner !== $current_user && $current_role !== \'owner\' && $current_role !== \'admin\') {
            sec_json_err(\'Mutlak hanya pemilik asli yang bisa menghapus data ini.\', \'forbidden\');
        }
        $domain = isset($vault[$id][\'domain\']) ? $vault[$id][\'domain\'] : $id;
        unset($vault[$id]);
        db_save(\'domains\', $vault);
        logActivity($current_user, \'Purged domain vault entry: \' . $domain);
        sec_json_out(array(\'status\' => \'success\'));
    }
    sec_json_err(\'Entry not found.\', \'error\');
}

sec_json_err(\'Unknown action.\', \'notfound\');
',
    'modules/domains/app.js' => '/* Emerald Central Hub V11 — Domains vault logic */
(function () {
  \'use strict\';
  var EM = window.EM;
  var $ = function (id) { return document.getElementById(id); };
  var editId = null;

  function refresh() {
    var tb = $(\'domainTable\').querySelector(\'tbody\');
    tb.innerHTML = \'<tr><td colspan="6" class="empty">Loading…</td></tr>\';
    EM.get(\'index.php?api=domains&action=list_domains\').then(function (res) {
      var list = (res && res.status === \'success\' && res.domains) ? res.domains : [];
      tb.innerHTML = \'\';
      if (!list.length) { tb.innerHTML = \'<tr><td colspan="6" class="empty">No domains yet — add your first one.</td></tr>\'; return; }
      list.forEach(function (d) {
        var tr = document.createElement(\'tr\');
        var cls = d.status === \'active\' ? \'ok\' : (d.status === \'expired\' || d.status === \'suspended\' ? \'err\' : \'warn\');
        tr.innerHTML =
          \'<td class="mono"><b>\' + EM.escapeHtml(d.domain) + \'</b></td>\' +
          \'<td><span class="badge \' + cls + \'">\' + EM.escapeHtml(d.status || \'active\') + \'</span></td>\' +
          \'<td>\' + EM.escapeHtml(d.username || \'—\') + \'</td>\' +
          \'<td class="mono small">\' + EM.escapeHtml(d.expiry || \'—\') + \'</td>\' +
          \'<td>\' + EM.escapeHtml(d.owner || \'System\') + \'</td>\' +
          \'<td><div class="actions">\' +
            \'<button class="btn ghost sm" data-act="edit">Edit</button>\' +
            \'<button class="btn danger sm" data-act="del">Del</button>\' +
          \'</div></td>\';
        tr.querySelector(\'[data-act="edit"]\').addEventListener(\'click\', function () { openForm(d); });
        tr.querySelector(\'[data-act="del"]\').addEventListener(\'click\', function () {
          if (!confirm(\'Delete domain "\' + d.domain + \'" from the vault?\')) { return; }
          EM.post(\'index.php?api=domains&action=delete_domain\', { id: d.id }).then(function (r) {
            EM.toast(r.status === \'success\' ? \'Deleted\' : (r.message || \'Delete failed\'), r.status === \'success\' ? \'ok\' : \'err\');
            refresh();
          });
        });
        tb.appendChild(tr);
      });
    }).catch(function () {
      tb.innerHTML = \'<tr><td colspan="6" class="empty">Could not load vault.</td></tr>\';
    });
  }

  function openForm(d) {
    editId = d ? d.id : null;
    $(\'domainFormTitle\').textContent = d ? \'Edit domain\' : \'Add domain\';
    $(\'f_domain\').value = d ? (d.domain || \'\') : \'\';
    $(\'f_status\').value = d ? (d.status || \'active\') : \'active\';
    $(\'f_username\').value = d ? (d.username || \'\') : \'\';
    $(\'f_expiry\').value = d ? (d.expiry || \'\') : \'\';
    $(\'f_uapi\').value = d ? (d.uapi_token || \'\') : \'\';
    $(\'f_cpanel\').value = d ? (d.cpanel_token || \'\') : \'\';
    $(\'f_notes\').value = d ? (d.notes || \'\') : \'\';
    $(\'domainFormCard\').classList.remove(\'hide\');
  }
  function closeForm() {
    $(\'domainFormCard\').classList.add(\'hide\');
    editId = null;
  }

  $(\'btnAddDomain\').addEventListener(\'click\', function () { openForm(null); });
  $(\'btnCancelDomain\').addEventListener(\'click\', closeForm);
  $(\'btnSaveDomain\').addEventListener(\'click\', function () {
    var domain = $(\'f_domain\').value.trim();
    if (!domain) { EM.toast(\'Domain is required\', \'err\'); return; }
    EM.post(\'index.php?api=domains&action=save_domain\', {
      id: editId || \'\',
      domain: domain,
      status: $(\'f_status\').value,
      username: $(\'f_username\').value.trim(),
      expiry: $(\'f_expiry\').value.trim(),
      uapi_token: $(\'f_uapi\').value,
      cpanel_token: $(\'f_cpanel\').value,
      notes: $(\'f_notes\').value
    }).then(function (r) {
      if (r.status !== \'success\') { EM.toast(r.message || \'Save failed\', \'err\'); return; }
      EM.toast(\'Domain saved\', \'ok\');
      closeForm();
      refresh();
    });
  });

  refresh();
})();
',
    'modules/domains/index.php' => '<?php
/**
 * Emerald Central Hub V11 — Domains vault fragment.
 * Rendered inside the dashboard shell; logic in modules/domains/app.js.
 */
if (!defined(\'EMERALD_SHELL\')) {
    header(\'Location: index.php?p=domains\');
    exit;
}
?>
<div class="card">
  <div class="card-header">
    <h2>Domains vault</h2>
    <button class="btn btn-primary sm" id="btnAddDomain">+ Add domain</button>
  </div>
  <div class="table-wrap">
    <table class="tbl" id="domainTable">
      <thead><tr>
        <th>Domain</th><th>Status</th><th>Username</th><th>Expiry</th><th>Owner</th><th class="right">Actions</th>
      </tr></thead>
      <tbody><tr><td colspan="6" class="empty">Loading…</td></tr></tbody>
    </table>
  </div>
</div>

<div class="card hide" id="domainFormCard">
  <h2 id="domainFormTitle">Add domain</h2>
  <div class="grid grid-2">
    <div class="field"><label>Domain</label><input type="text" id="f_domain" placeholder="example.com"></div>
    <div class="field"><label>Status</label><select id="f_status">
      <option value="active">active</option><option value="pending">pending</option><option value="expired">expired</option><option value="suspended">suspended</option>
    </select></div>
    <div class="field"><label>cPanel username</label><input type="text" id="f_username" autocomplete="off"></div>
    <div class="field"><label>Expiry date</label><input type="text" id="f_expiry" placeholder="2027-01-31"></div>
    <div class="field"><label>UAPI token</label><input type="password" id="f_uapi" autocomplete="off"></div>
    <div class="field"><label>cPanel token</label><input type="password" id="f_cpanel" autocomplete="off"></div>
  </div>
  <div class="field"><label>Notes</label><textarea id="f_notes" style="min-height:80px"></textarea></div>
  <div class="row">
    <button class="btn btn-primary" id="btnSaveDomain">Save domain</button>
    <button class="btn ghost" id="btnCancelDomain">Cancel</button>
  </div>
</div>
<script src="modules/domains/app.js"></script>
',
    'modules/firewall/api.php' => '<?php
/**
 * Emerald Central Hub V11 — Firewall module API.
 * Ported from V10: list_firewall | add_firewall | delete_firewall.
 * delete_firewall: owner must match OR role is \'owner\' (V10 rule).
 */
if (!defined(\'EMERALD_DISPATCH\')) {
    http_response_code(403);
    exit;
}

if ($action === \'list_firewall\') {
    sec_json_out(array(\'status\' => \'success\', \'entries\' => array_values(db_load(\'firewall\'))));
}

if ($action === \'add_firewall\') {
    $fw = db_load(\'firewall\');
    $ip = sec_str(isset($_POST[\'ip\']) ? $_POST[\'ip\'] : \'\');
    $note = sec_str(isset($_POST[\'note\']) ? $_POST[\'note\'] : \'\');
    if ($ip === \'\' || !preg_match(\'/^[0-9a-fA-F:.\\-]+$/\', $ip)) {
        sec_json_err(\'Invalid IP address.\', \'error\');
    }
    $fw[] = array(\'id\' => generateId(), \'ip\' => $ip, \'note\' => $note, \'added\' => time(), \'owner\' => $current_user);
    db_save(\'firewall\', $fw);
    logActivity($current_user, \'Whitelisted IP: \' . $ip);
    sec_json_out(array(\'status\' => \'success\'));
}

if ($action === \'delete_firewall\') {
    $fw = db_load(\'firewall\');
    $id = sec_str(isset($_POST[\'id\']) ? $_POST[\'id\'] : \'\');
    $target_key = null;
    $target_fw = null;
    foreach ($fw as $key => $val) {
        if (isset($val[\'id\']) && $val[\'id\'] === $id) { $target_fw = $val; $target_key = $key; break; }
    }
    if ($target_fw === null) { sec_json_err(\'Not found.\', \'error\'); }

    $owner = isset($target_fw[\'owner\']) ? $target_fw[\'owner\'] : \'System\';
    if ($owner !== $current_user && $current_role !== \'owner\') {
        sec_json_err(\'Mutlak hanya pemilik asli (\' . $owner . \') yang bisa menghapus.\', \'forbidden\');
    }

    logActivity($current_user, \'Removed IP from Whitelist: \' . $target_fw[\'ip\']);
    unset($fw[$target_key]);
    db_save(\'firewall\', array_values($fw));
    sec_json_out(array(\'status\' => \'success\'));
}

sec_json_err(\'Unknown action.\', \'notfound\');
',
    'modules/firewall/app.js' => '/* Emerald Central Hub V11 — Firewall logic */
(function () {
  \'use strict\';
  var EM = window.EM;
  var $ = function (id) { return document.getElementById(id); };

  function refresh() {
    var tb = $(\'fwTable\').querySelector(\'tbody\');
    tb.innerHTML = \'<tr><td colspan="5" class="empty">Loading…</td></tr>\';
    EM.get(\'index.php?api=firewall&action=list_firewall\').then(function (res) {
      var list = (res && res.status === \'success\' && res.entries) ? res.entries : [];
      tb.innerHTML = \'\';
      if (!list.length) { tb.innerHTML = \'<tr><td colspan="5" class="empty">Whitelist is empty — your own IP must be listed to access the app.</td></tr>\'; return; }
      list.forEach(function (e) {
        var tr = document.createElement(\'tr\');
        tr.innerHTML =
          \'<td class="mono"><b>\' + EM.escapeHtml(e.ip) + \'</b></td>\' +
          \'<td>\' + EM.escapeHtml(e.note || \'—\') + \'</td>\' +
          \'<td class="mono small">\' + EM.escapeHtml(EM.fmtTime(e.added)) + \'</td>\' +
          \'<td>\' + EM.escapeHtml(e.owner || \'System\') + \'</td>\' +
          \'<td><div class="actions"><button class="btn danger sm" data-act="del">Remove</button></div></td>\';
        tr.querySelector(\'[data-act="del"]\').addEventListener(\'click\', function () {
          if (!confirm(\'Remove IP \' + e.ip + \' from the whitelist?\')) { return; }
          EM.post(\'index.php?api=firewall&action=delete_firewall\', { id: e.id }).then(function (r) {
            EM.toast(r.status === \'success\' ? \'Removed\' : (r.message || \'Remove failed\'), r.status === \'success\' ? \'ok\' : \'err\');
            refresh();
          });
        });
        tb.appendChild(tr);
      });
    }).catch(function () {
      tb.innerHTML = \'<tr><td colspan="5" class="empty">Could not load whitelist.</td></tr>\';
    });
  }

  $(\'btnAddFw\').addEventListener(\'click\', function () { $(\'fwFormCard\').classList.remove(\'hide\'); });
  $(\'btnCancelFw\').addEventListener(\'click\', function () { $(\'fwFormCard\').classList.add(\'hide\'); });
  $(\'btnSaveFw\').addEventListener(\'click\', function () {
    var ip = $(\'fw_ip\').value.trim();
    if (!ip) { EM.toast(\'IP address required\', \'err\'); return; }
    EM.post(\'index.php?api=firewall&action=add_firewall\', { ip: ip, note: $(\'fw_note\').value.trim() }).then(function (r) {
      if (r.status !== \'success\') { EM.toast(r.message || \'Add failed\', \'err\'); return; }
      EM.toast(\'IP whitelisted\', \'ok\');
      $(\'fwFormCard\').classList.add(\'hide\');
      $(\'fw_ip\').value = \'\'; $(\'fw_note\').value = \'\';
      refresh();
    });
  });

  refresh();
})();
',
    'modules/firewall/index.php' => '<?php
/**
 * Emerald Central Hub V11 — Firewall fragment.
 * Rendered inside the dashboard shell; logic in modules/firewall/app.js.
 */
if (!defined(\'EMERALD_SHELL\')) {
    header(\'Location: index.php?p=firewall\');
    exit;
}
?>
<div class="card">
  <div class="card-header">
    <h2>Firewall whitelist</h2>
    <button class="btn btn-primary sm" id="btnAddFw">+ Whitelist IP</button>
  </div>
  <div class="table-wrap">
    <table class="tbl" id="fwTable">
      <thead><tr>
        <th>IP</th><th>Note</th><th>Added</th><th>Owner</th><th class="right">Actions</th>
      </tr></thead>
      <tbody><tr><td colspan="5" class="empty">Loading…</td></tr></tbody>
    </table>
  </div>
</div>

<div class="card hide" id="fwFormCard">
  <h2>Whitelist IP</h2>
  <div class="grid grid-2">
    <div class="field"><label>IP address</label><input type="text" id="fw_ip" placeholder="1.2.3.4"></div>
    <div class="field"><label>Note (optional)</label><input type="text" id="fw_note" placeholder="Home network"></div>
  </div>
  <div class="row">
    <button class="btn btn-primary" id="btnSaveFw">Add to whitelist</button>
    <button class="btn ghost" id="btnCancelFw">Cancel</button>
  </div>
</div>
<script src="modules/firewall/app.js"></script>
',
    'modules/monitor/api.php' => '<?php
/**
 * Emerald Central Hub V11 — Monitor module API.
 * Ported from V10: list_processes | kill_process | create_snapshot.
 * kill_process: owner/admin only (V10 rule).
 */
if (!defined(\'EMERALD_DISPATCH\')) {
    http_response_code(403);
    exit;
}

if ($action === \'list_processes\') {
    $process_data = array();
    $disabled = function_exists(\'ini_get\') ? ini_get(\'disable_functions\') : \'\';
    if (function_exists(\'shell_exec\') && stripos($disabled, \'shell_exec\') === false) {
        $out = @shell_exec(\'ps aux --sort=-%cpu | head -n 50\');
        if ($out !== null && $out !== false) {
            $lines = explode("\\n", trim($out));
            foreach ($lines as $i => $line) {
                if ($i === 0) { continue; }
                $cols = preg_split(\'/\\s+/\', $line, 11);
                if (count($cols) >= 11) {
                    $process_data[] = array(
                        \'user\' => $cols[0], \'pid\' => $cols[1],
                        \'cpu\' => $cols[2], \'mem\' => $cols[3],
                        \'cmd\' => htmlspecialchars($cols[10], ENT_QUOTES, \'UTF-8\'),
                    );
                }
            }
        }
    }
    sec_json_out(array(\'status\' => \'success\', \'data\' => $process_data));
}

if ($action === \'kill_process\') {
    $pid = (int)(isset($_POST[\'pid\']) ? $_POST[\'pid\'] : 0);
    if ($current_role !== \'owner\' && $current_role !== \'admin\') {
        sec_json_err(\'Authorization failed.\', \'forbidden\');
    }
    if ($pid <= 0) { sec_json_err(\'Invalid PID.\', \'error\'); }
    $disabled = function_exists(\'ini_get\') ? ini_get(\'disable_functions\') : \'\';
    if (function_exists(\'shell_exec\') && stripos($disabled, \'shell_exec\') === false) {
        @shell_exec(\'kill -9 \' . (int)$pid);
        logActivity($current_user, \'Executed SIGKILL on PID: \' . $pid);
        sec_json_out(array(\'status\' => \'success\'));
    }
    sec_json_err(\'Exec function disabled by host.\', \'error\');
}

if ($action === \'create_snapshot\') {
    if (!class_exists(\'ZipArchive\')) { sec_json_err(\'Zip support is not available on this host.\', \'error\'); }
    $zipPath = ASSETS_DIR . \'/System_Snapshot_\' . date(\'Y-m-d_H-i-s\') . \'.zip\';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        $path = DATA_DIR;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($it as $f) {
            if (!$f->isDir()) {
                $filePath = $f->getRealPath();
                $relativePath = \'.emerald_data/\' . substr($filePath, strlen($path) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
        $zip->close();
        $file_meta = db_load(\'file_meta\');
        $file_meta[basename($zipPath)] = $current_user;
        db_save(\'file_meta\', $file_meta);
        logActivity($current_user, \'Generated System Snapshot.\');
        sec_json_out(array(\'status\' => \'success\', \'file\' => basename($zipPath)));
    }
    sec_json_err(\'Failed to build zip architecture.\', \'error\');
}

sec_json_err(\'Unknown action.\', \'notfound\');
',
    'modules/monitor/app.js' => '/* Emerald Central Hub V11 — Monitor logic */
(function () {
  \'use strict\';
  var EM = window.EM;
  var $ = function (id) { return document.getElementById(id); };
  var isPriv = (EM.role === \'owner\' || EM.role === \'admin\');

  function refresh() {
    var tb = $(\'procTable\').querySelector(\'tbody\');
    tb.innerHTML = \'<tr><td colspan="6" class="empty">Loading…</td></tr>\';
    EM.get(\'index.php?api=monitor&action=list_processes\').then(function (res) {
      var list = (res && res.status === \'success\' && res.data) ? res.data : [];
      tb.innerHTML = \'\';
      if (!list.length) { tb.innerHTML = \'<tr><td colspan="6" class="empty">Process listing unavailable (shell_exec disabled) or no processes found.</td></tr>\'; return; }
      list.forEach(function (p) {
        var tr = document.createElement(\'tr\');
        tr.innerHTML =
          \'<td>\' + EM.escapeHtml(p.user) + \'</td>\' +
          \'<td class="mono">\' + EM.escapeHtml(p.pid) + \'</td>\' +
          \'<td class="mono">\' + EM.escapeHtml(p.cpu) + \'</td>\' +
          \'<td class="mono">\' + EM.escapeHtml(p.mem) + \'</td>\' +
          \'<td class="mono small" style="max-width:340px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">\' + EM.escapeHtml(p.cmd) + \'</td>\' +
          \'<td><div class="actions">\' + (isPriv ? \'<button class="btn danger sm" data-act="kill">Kill</button>\' : \'<span class="muted small">read-only</span>\') + \'</div></td>\';
        var kill = tr.querySelector(\'[data-act="kill"]\');
        if (kill) {
          kill.addEventListener(\'click\', function () {
            if (!confirm(\'SIGKILL PID \' + p.pid + \'?\')) { return; }
            EM.post(\'index.php?api=monitor&action=kill_process\', { pid: p.pid }).then(function (r) {
              EM.toast(r.status === \'success\' ? \'Signal sent\' : (r.message || \'Failed\'), r.status === \'success\' ? \'ok\' : \'err\');
              refresh();
            });
          });
        }
        tb.appendChild(tr);
      });
    }).catch(function () {
      tb.innerHTML = \'<tr><td colspan="6" class="empty">Could not load processes.</td></tr>\';
    });
  }

  $(\'btnRefreshProc\').addEventListener(\'click\', refresh);
  $(\'btnSnapshot\').addEventListener(\'click\', function () {
    var btn = this;
    btn.disabled = true;
    EM.post(\'index.php?api=monitor&action=create_snapshot\', {}).then(function (r) {
      btn.disabled = false;
      EM.toast(r.status === \'success\' ? \'Snapshot created: \' + (r.file || \'\') : (r.message || \'Failed\'), r.status === \'success\' ? \'ok\' : \'err\');
    }).catch(function () { btn.disabled = false; EM.toast(\'Snapshot failed\', \'err\'); });
  });

  refresh();
})();
',
    'modules/monitor/index.php' => '<?php
/**
 * Emerald Central Hub V11 — Monitor fragment.
 * Rendered inside the dashboard shell; logic in modules/monitor/app.js.
 */
if (!defined(\'EMERALD_SHELL\')) {
    header(\'Location: index.php?p=monitor\');
    exit;
}
?>
<div class="card">
  <div class="card-header">
    <h2>System processes</h2>
    <button class="btn ghost sm" id="btnRefreshProc">⟳ Refresh</button>
  </div>
  <div class="table-wrap">
    <table class="tbl" id="procTable">
      <thead><tr>
        <th>User</th><th>PID</th><th>CPU%</th><th>MEM%</th><th>Command</th><th class="right">Actions</th>
      </tr></thead>
      <tbody><tr><td colspan="6" class="empty">Loading…</td></tr></tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2>System snapshot</h2>
    <button class="btn btn-primary sm" id="btnSnapshot">Create snapshot (.emerald_data → zip)</button>
  </div>
  <p class="muted small">Builds a ZIP of the encrypted vault (.emerald_data) and stores it in the file vault as <code>System_Snapshot_YYYY-MM-DD_HH-MM-SS.zip</code>.</p>
</div>
<script src="modules/monitor/app.js"></script>
',
    'modules/notepad/api.php' => '<?php
/**
 * Emerald Central Hub V11 — Notepad module API (dual mode).
 *
 * 1) PUBLIC MODE (EMERALD_DISPATCH not defined)
 *    Used by /notepad/index.php (V10-compatible public page). Boots the
 *    core stack itself and answers the exact V10 action set:
 *      ?api=list_users | verify_access | list_notes | load_note |
 *             create_note | save_note | delete_note
 *    Mutating actions are gated by verifyUserPassword() or SSO.
 *
 * 2) DISPATCH MODE (EMERALD_DISPATCH defined by core/api.php)
 *    Used by the in-shell Notepad page (?api=notepad&action=...) with a
 *    live session; $action and $current_user are provided by the core.
 *
 * V11: note files live in modules/notepad/data/<user>/*.txt (migrated
 * from public_notepad/ by update.php — original files are never deleted).
 */
if (!defined(\'EMERALD_DISPATCH\')) {
    // ---- public mode bootstrap (auth_start() starts the session with the
    //      correct V11 session name — starting it here breaks SSO) ----
    require_once dirname(__DIR__) . \'/../core/config.php\';
    require_once CORE_DIR . \'/auth.php\';
    auth_start();
    $current_user = auth_user();   // null when not logged in (SSO unavailable)
    $raw_action = isset($_GET[\'api\']) ? $_GET[\'api\'] : (isset($_POST[\'action\']) ? $_POST[\'action\'] : (isset($_GET[\'action\']) ? $_GET[\'action\'] : \'\'));
    $action = preg_replace(\'/[^a-z0-9_\\-]/\', \'\', strtolower((string)$raw_action));
    header(\'Content-Type: application/json; charset=utf-8\');
} else {
    // ---- dispatch mode ----
    if (!isset($current_user)) { $current_user = null; }
}

if (!defined(\'DIR_NOTEPAD\')) {
    $np = MODULES_DIR . \'/notepad/data\';
    if (!is_dir($np) && is_dir(APP_ROOT . \'/public_notepad\')) {
        $np = APP_ROOT . \'/public_notepad\';
    }
    if (!is_dir($np)) { @mkdir($np, 0755, true); }
    define(\'DIR_NOTEPAD\', $np);
}

$users = db_load(\'users\');

/* helper: safe note filename (no path separators) */
function npd_note_path($user, $file) {
    $user = sec_clean_path($user);
    $file = sec_clean_path($file);
    if ($user === \'\' || $file === \'\') { return null; }
    if (strpos($user, \'/\') !== false || strpos($file, \'/\') !== false) { return null; }
    $dir = DIR_NOTEPAD . \'/\' . $user;
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    return $dir . \'/\' . $file;
}

if ($action === \'list_users\') {
    $output = array();
    foreach ($users as $uname => $udata) {
        $output[] = array(
            \'username\' => $uname,
            \'avatar\' => !empty($udata[\'avatar\']) ? $udata[\'avatar\'] : \'https://ui-avatars.com/api/?name=\' . urlencode($uname) . \'&background=0ea5e9&color=fff&rounded=true&bold=true\',
            \'last_active\' => isset($udata[\'last_active\']) ? $udata[\'last_active\'] : 0,
        );
    }
    sec_json_out($output);
}

if ($action === \'verify_access\') {
    $user = sec_str(isset($_POST[\'user\']) ? $_POST[\'user\'] : \'\');
    $pass = isset($_POST[\'password\']) ? $_POST[\'password\'] : \'\';
    if ($user !== \'\' && verifyUserPassword($user, $pass)) {
        sec_json_out(array(\'status\' => \'success\'));
    }
    sec_json_out(array(\'status\' => \'error\', \'message\' => \'Invalid password.\'));
}

if ($action === \'list_notes\') {
    $user = sec_str(isset($_POST[\'user\']) ? $_POST[\'user\'] : \'\');
    $dir = DIR_NOTEPAD . \'/\' . sec_clean_path($user);
    if ($user === \'\' || strpos(sec_clean_path($user), \'/\') !== false) { sec_json_out(array(\'status\' => \'error\', \'message\' => \'Invalid user.\')); }
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    clearstatcache();
    $notes = array();
    foreach (scandir($dir) as $file) {
        if ($file !== \'.\' && $file !== \'..\') { $notes[] = $file; }
    }
    sec_json_out(array(\'status\' => \'success\', \'notes\' => array_values($notes)));
}

if ($action === \'load_note\') {
    /* Match V10 public notepad: load does not re-check password */
    $user = sec_str(isset($_POST[\'user\']) ? $_POST[\'user\'] : \'\');
    $file = sec_str(isset($_POST[\'file\']) ? $_POST[\'file\'] : \'\');
    $path = npd_note_path($user, $file);
    $content = \'\';
    if ($path !== null && file_exists($path)) {
        $content = (string)file_get_contents($path);
    } else {
        $alt = APP_ROOT . \'/public_notepad/\' . sec_clean_path($user) . \'/\' . sec_clean_path($file);
        if (is_file($alt)) { $content = (string)file_get_contents($alt); }
    }
    sec_json_out(array(\'status\' => \'success\', \'content\' => $content));
}

if ($action === \'save_note\' || $action === \'create_note\' || $action === \'delete_note\') {
    $user = sec_str(isset($_POST[\'user\']) ? $_POST[\'user\'] : \'\');
    $file = sec_str(isset($_POST[\'file\']) ? $_POST[\'file\'] : \'\');
    $pass = isset($_POST[\'password\']) ? $_POST[\'password\'] : \'\';
    $is_sso = ($current_user !== null && $current_user !== \'\' && $current_user === $user);
    if (!$is_sso && !verifyUserPassword($user, $pass)) {
        sec_json_out(array(\'status\' => \'error\', \'message\' => \'Invalid password.\'));
    }
    $path = npd_note_path($user, $file);
    if ($path === null) { sec_json_out(array(\'status\' => \'error\', \'message\' => \'Invalid file name.\')); }

    if ($action === \'save_note\') {
        $content = isset($_POST[\'content\']) ? $_POST[\'content\'] : \'\';
        if (@file_put_contents($path, $content) !== false) {
            sec_json_out(array(\'status\' => \'success\'));
        }
        sec_json_out(array(\'status\' => \'error\', \'message\' => \'Could not save note.\'));
    }

    if ($action === \'create_note\') {
        if (strpos($file, \'.txt\') === false) {
            $file .= \'.txt\';
            $path = npd_note_path($user, $file);
        }
        if (!file_exists($path)) { @file_put_contents($path, \'\'); }
        sec_json_out(array(\'status\' => \'success\'));
    }

    if ($action === \'delete_note\') {
        if (file_exists($path)) { @unlink($path); }
        sec_json_out(array(\'status\' => \'success\'));
    }
}

sec_json_out(array(\'status\' => \'error\', \'message\' => \'Unknown action.\'));
',
    'modules/notepad/app.js' => '/* Emerald Central Hub V11 — Notepad logic (SSO-aware, V10-compatible API) */
(function () {
  \'use strict\';
  var EM = window.EM;
  var $ = function (id) { return document.getElementById(id); };
  var API = \'notepad/\';                 // relative path — works for both in-shell page and standalone
  var cur = { user: null, file: null, pass: \'\' };
  var SSO = EM.user || null;             // logged-in shell identity (null on standalone page)

  function userCard(u) {
    var d = document.createElement(\'div\');
    d.className = \'np-user\';
    d.innerHTML =
      \'<img class="np-avatar" src="\' + EM.escapeHtml(u.avatar) + \'" alt="\' + EM.escapeHtml(u.username) + \'">\' +
      \'<div class="np-name">\' + EM.escapeHtml(u.username) + \'</div>\' +
      \'<div class="muted small">\' + (u.last_active ? new Date(u.last_active * 1000).toLocaleString() : \'never\') + \'</div>\';
    d.addEventListener(\'click\', function () { openUser(u.username); });
    return d;
  }

  function openUser(username) {
    if (SSO && SSO === username) {
      cur.pass = \'\';                      // SSO identity — API accepts empty password
      enterNotes(username, null);
      return;
    }
    var pass = prompt(\'Password for \' + username + \' (SSO only bypasses this for the signed-in identity):\');
    if (pass === null) { return; }
    fetch(API + \'?api=verify_access\', {
      method: \'POST\',
      headers: { \'Content-Type\': \'application/x-www-form-urlencoded\' },
      body: \'user=\' + encodeURIComponent(username) + \'&password=\' + encodeURIComponent(pass)
    }).then(function (r) { return r.json(); }).then(function (res) {
      if (res.status === \'success\') { cur.pass = pass; enterNotes(username, null); }
      else { EM.toast(res.message || \'Access denied\', \'err\'); }
    }).catch(function () { EM.toast(\'Access check failed\', \'err\'); });
  }

  function enterNotes(username, file) {
    cur.user = username;
    cur.file = file;
    $(\'npUserGrid\').parentElement.classList.add(\'hide\');
    $(\'npNotesCard\').classList.remove(\'hide\');
    $(\'npEditorCard\').classList.add(\'hide\');
    $(\'npOwnerTitle\').textContent = \'Notes — \' + username;
    loadNotes();
  }

  function api(action, data) {
    var body = new URLSearchParams();
    body.set(\'api\', action);
    if (cur.user) { body.set(\'user\', cur.user); }
    if (cur.file) { body.set(\'file\', cur.file); }
    if (cur.pass) { body.set(\'password\', cur.pass); }
    if (data) {
      Object.keys(data).forEach(function (k) { body.set(k, data[k]); });
    }
    return fetch(API + \'?api=\' + action, {
      method: \'POST\',
      headers: { \'Content-Type\': \'application/x-www-form-urlencoded\' },
      body: body.toString()
    }).then(function (r) { return r.json(); });
  }

  function loadNotes() {
    var tb = $(\'npNoteTable\').querySelector(\'tbody\');
    tb.innerHTML = \'<tr><td colspan="2" class="empty">Loading…</td></tr>\';
    api(\'list_notes\').then(function (res) {
      tb.innerHTML = \'\';
      var notes = (res.status === \'success\' && res.notes) ? res.notes : [];
      if (!notes.length) { tb.innerHTML = \'<tr><td colspan="2" class="empty">No notes yet.</td></tr>\'; return; }
      notes.forEach(function (f) {
        var tr = document.createElement(\'tr\');
        tr.innerHTML =
          \'<td class="mono"><a href="#" data-open="\' + EM.escapeHtml(f) + \'">\' + EM.escapeHtml(f) + \'</a></td>\' +
          \'<td><div class="actions"><button class="btn ghost sm" data-open="\' + EM.escapeHtml(f) + \'">Open</button><button class="btn danger sm" data-del="\' + EM.escapeHtml(f) + \'">Delete</button></div></td>\';
        var open = tr.querySelector(\'[data-open="\' + CSS.escape(f) + \'"]\');
        if (open) { open.addEventListener(\'click\', function (e) { e.preventDefault(); openNote(f); }); }
        var del = tr.querySelector(\'[data-del="\' + CSS.escape(f) + \'"]\');
        if (del) { del.addEventListener(\'click\', function () { deleteNote(f); }); }
        tb.appendChild(tr);
      });
    }).catch(function () { tb.innerHTML = \'<tr><td colspan="2" class="empty">Could not load notes.</td></tr>\'; });
  }

  function openNote(file) {
    cur.file = file;
    $(\'npNotesCard\').classList.add(\'hide\');
    $(\'npEditorCard\').classList.remove(\'hide\');
    $(\'npEditTitle\').textContent = \'Editor — \' + cur.user + \'/\' + file;
    $(\'npContent\').value = \'Loading…\';
    api(\'load_note\', {}).then(function (res) {
      $(\'npContent\').value = res.status === \'success\' ? res.content : \'\';
    }).catch(function () { $(\'npContent\').value = \'\'; });
  }

  function deleteNote(file) {
    if (!confirm(\'Delete \' + file + \'?\')) { return; }
    cur.file = file;
    api(\'delete_note\').then(function (res) {
      EM.toast(res.status === \'success\' ? \'Deleted\' : (res.message || \'Failed\'), res.status === \'success\' ? \'ok\' : \'err\');
      cur.file = null;
      loadNotes();
    });
  }

  $(\'npBack\').addEventListener(\'click\', function () {
    $(\'npNotesCard\').classList.add(\'hide\');
    $(\'npUserGrid\').parentElement.classList.remove(\'hide\');
  });
  $(\'npBackNotes\').addEventListener(\'click\', function () {
    $(\'npEditorCard\').classList.add(\'hide\');
    $(\'npNotesCard\').classList.remove(\'hide\');
    cur.file = null;
    loadNotes();
  });
  $(\'npSaveBtn\').addEventListener(\'click\', function () {
    api(\'save_note\', { content: $(\'npContent\').value }).then(function (res) {
      EM.toast(res.status === \'success\' ? \'Saved\' : (res.message || \'Failed\'), res.status === \'success\' ? \'ok\' : \'err\');
    });
  });
  $(\'npDelBtn\').addEventListener(\'click\', function () { deleteNote(cur.file); });
  $(\'npNewNote\').addEventListener(\'click\', function () {
    $(\'npNewName\').value = \'\';
    $(\'npNewName\').focus();
  });
  $(\'npCreateBtn\').addEventListener(\'click\', function () {
    var name = $(\'npNewName\').value.trim();
    if (!name) { return; }
    cur.file = name;
    api(\'create_note\').then(function (res) {
      EM.toast(res.status === \'success\' ? \'Created\' : (res.message || \'Failed\'), res.status === \'success\' ? \'ok\' : \'err\');
      cur.file = null;
      loadNotes();
    });
  });

  api(\'list_users\').then(function (res) {
    var grid = $(\'npUserGrid\');
    grid.innerHTML = \'\';
    var list = (res && res.data && res.data.length) ? res.data : ((res && Array.isArray(res)) ? res : []);
    list.forEach(function (u) { grid.appendChild(userCard(u)); });
    if (!list.length) { grid.innerHTML = \'<div class="empty">No identities.</div>\'; }
  }).catch(function () { $(\'npUserGrid\').innerHTML = \'<div class="empty">Could not load identities.</div>\'; });
})();
',
    'modules/notepad/index.php' => '<?php
/**
 * Emerald Central Hub V11 — Notepad fragment (in-shell).
 * SSO: when the selected identity matches the logged-in session user,
 * notes unlock without a password. Otherwise the identity password is
 * required (V10 public behaviour).
 * Rendered inside the dashboard shell; logic in modules/notepad/app.js.
 */
if (!defined(\'EMERALD_SHELL\')) {
    header(\'Location: index.php?p=notepad\');
    exit;
}
?>
<div class="card">
  <div class="card-header">
    <h2>Notepad <span class="badge info">SSO: <?php echo sec_html($__user); ?></span></h2>
    <a class="btn ghost sm" href="notepad/" target="_blank" rel="noopener">Open public page ↗</a>
  </div>
  <div class="grid" id="npUserGrid" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr))">
    <div class="empty">Loading identities…</div>
  </div>
</div>

<div class="card hide" id="npNotesCard">
  <div class="card-header">
    <h2 id="npOwnerTitle">Notes</h2>
    <div class="row">
      <button class="btn ghost sm" id="npBack">← identities</button>
      <button class="btn ghost sm" id="npNewNote">+ Note</button>
    </div>
  </div>
  <div class="row mb">
    <input type="text" id="npNewName" placeholder="new-note.txt" style="max-width:260px">
    <button class="btn ok sm" id="npCreateBtn">Create</button>
  </div>
  <div class="table-wrap">
    <table class="tbl" id="npNoteTable">
      <thead><tr><th>File</th><th class="right">Actions</th></tr></thead>
      <tbody><tr><td colspan="2" class="empty">Loading…</td></tr></tbody>
    </table>
  </div>
</div>

<div class="card hide" id="npEditorCard">
  <div class="card-header">
    <h2 id="npEditTitle">Editor</h2>
    <div class="row">
      <button class="btn ok sm" id="npSaveBtn">Save</button>
      <button class="btn danger sm" id="npDelBtn">Delete</button>
      <button class="btn ghost sm" id="npBackNotes">← back</button>
    </div>
  </div>
  <textarea id="npContent" spellcheck="false"></textarea>
</div>
<script src="modules/notepad/app.js"></script>
',
    'modules/notes/api.php' => '<?php
/**
 * Emerald Central Hub V11 — Notes module API (system containers).
 * Ported from V10: list_notes | save_note | delete_note.
 * Owner / System only for modification (guest guard lives in core/api.php).
 */
if (!defined(\'EMERALD_DISPATCH\')) {
    http_response_code(403);
    exit;
}

if ($action === \'list_notes\') {
    $notes = db_load(\'notes\');
    $out = array();
    foreach ($notes as $nid => $note) {
        if (!is_array($note)) { continue; }
        $owner = isset($note[\'owner\']) ? $note[\'owner\'] : \'System\';
        $note[\'id\'] = $nid;
        $note[\'avatar\'] = avatar_for($owner, \'https://ui-avatars.com/api/?name=\' . urlencode($owner) . \'&background=0ea5e9&color=fff&rounded=true&bold=true\');
        $out[] = $note;
    }
    sec_json_out(array(\'status\' => \'success\', \'notes\' => $out));
}

if ($action === \'save_note\') {
    $notes = db_load(\'notes\');
    $id = isset($_POST[\'id\']) ? sec_str($_POST[\'id\']) : \'\';
    if ($id === \'\') { $id = generateId(); }
    $raw_list = explode("\\n", isset($_POST[\'text_list\']) ? $_POST[\'text_list\'] : \'\');
    $parsed_list = array();
    foreach ($raw_list as $line) {
        $cl = trim($line);
        if ($cl === \'\') { continue; }
        $parsed_list[] = preg_replace(\'/^-->\\s*/\', \'\', $cl);
    }
    $title = sec_str(isset($_POST[\'title\']) ? $_POST[\'title\'] : \'Untitled\');
    $data = array(
        \'auth\' => array(
            \'host\' => sec_str(isset($_POST[\'host\']) ? $_POST[\'host\'] : \'\'),
            \'user\' => sec_str(isset($_POST[\'user\']) ? $_POST[\'user\'] : \'\'),
            \'pass\' => isset($_POST[\'pass\']) ? $_POST[\'pass\'] : \'\',
            \'dir\'  => sec_str(isset($_POST[\'dir\']) ? $_POST[\'dir\'] : \'\'),
        ),
        \'list\'   => implode("\\n", $parsed_list),
        \'status\' => sec_str(isset($_POST[\'status\']) ? $_POST[\'status\'] : (isset($notes[$id][\'data\'][\'status\']) ? $notes[$id][\'data\'][\'status\'] : \'active\')),
    );

    $existing_owner = isset($notes[$id]) && isset($notes[$id][\'owner\']) ? $notes[$id][\'owner\'] : $current_user;
    $notes[$id] = array(
        \'id\' => $id,
        \'title\' => $title,
        \'owner\' => $existing_owner,
        \'timestamp\' => time(),
        \'data\' => json_encode($data),
    );
    db_save(\'notes\', $notes);
    logActivity($current_user, \'Configured Container: \' . $title);
    sec_json_out(array(\'status\' => \'success\', \'id\' => $id));
}

if ($action === \'delete_note\') {
    $notes = db_load(\'notes\');
    $id = sec_str(isset($_POST[\'id\']) ? $_POST[\'id\'] : \'\');
    if ($id !== \'\' && isset($notes[$id])) {
        $owner = isset($notes[$id][\'owner\']) ? $notes[$id][\'owner\'] : \'System\';
        if ($owner !== \'System\' && $owner !== $current_user) {
            sec_json_err(\'Mutlak hanya pemilik asli yang bisa menghapus.\', \'forbidden\');
        }
        $title = isset($notes[$id][\'title\']) ? $notes[$id][\'title\'] : $id;
        unset($notes[$id]);
        db_save(\'notes\', $notes);
        logActivity($current_user, \'Purged Container: \' . $title);
        sec_json_out(array(\'status\' => \'success\'));
    }
    sec_json_err(\'Note not found.\', \'error\');
}

sec_json_err(\'Unknown action.\', \'notfound\');
',
    'modules/notes/app.js' => '/* Emerald Central Hub V11 — Notes (system containers) logic */
(function () {
  \'use strict\';
  var EM = window.EM;
  var $ = function (id) { return document.getElementById(id); };
  var editId = null;

  function parseData(note) {
    try { return JSON.parse(note.data || \'{}\'); } catch (e) { return {}; }
  }

  function refresh() {
    var tb = $(\'noteTable\').querySelector(\'tbody\');
    tb.innerHTML = \'<tr><td colspan="6" class="empty">Loading…</td></tr>\';
    EM.get(\'index.php?api=notes&action=list_notes\').then(function (res) {
      var list = (res && res.status === \'success\' && res.notes) ? res.notes : [];
      tb.innerHTML = \'\';
      if (!list.length) { tb.innerHTML = \'<tr><td colspan="6" class="empty">No containers yet.</td></tr>\'; return; }
      list.forEach(function (n) {
        var d = parseData(n);
        var auth = d.auth || {};
        var tr = document.createElement(\'tr\');
        tr.innerHTML =
          \'<td><b>\' + EM.escapeHtml(n.title || \'Untitled\') + \'</b></td>\' +
          \'<td class="mono small">\' + EM.escapeHtml(auth.host || \'—\') + \'</td>\' +
          \'<td>\' + EM.escapeHtml(auth.user || \'—\') + \'</td>\' +
          \'<td><span class="badge \' + ((d.status || \'active\') === \'active\' ? \'ok\' : \'warn\') + \'">\' + EM.escapeHtml(d.status || \'active\') + \'</span></td>\' +
          \'<td>\' + EM.escapeHtml(n.owner || \'System\') + \'</td>\' +
          \'<td><div class="actions">\' +
            \'<button class="btn ghost sm" data-act="edit">Edit</button>\' +
            \'<button class="btn danger sm" data-act="del">Del</button>\' +
          \'</div></td>\';
        tr.querySelector(\'[data-act="edit"]\').addEventListener(\'click\', function () { openForm(n, d); });
        tr.querySelector(\'[data-act="del"]\').addEventListener(\'click\', function () {
          if (!confirm(\'Delete container "\' + (n.title || n.id) + \'"?\')) { return; }
          EM.post(\'index.php?api=notes&action=delete_note\', { id: n.id }).then(function (r) {
            EM.toast(r.status === \'success\' ? \'Deleted\' : (r.message || \'Delete failed\'), r.status === \'success\' ? \'ok\' : \'err\');
            refresh();
          });
        });
        tb.appendChild(tr);
      });
    }).catch(function () {
      tb.innerHTML = \'<tr><td colspan="6" class="empty">Could not load containers.</td></tr>\';
    });
  }

  function openForm(n, d) {
    editId = n ? n.id : null;
    var auth = (d && d.auth) || {};
    $(\'noteFormTitle\').textContent = n ? \'Edit container\' : \'New container\';
    $(\'n_title\').value = n ? (n.title || \'\') : \'\';
    $(\'n_status\').value = (d && d.status) || \'active\';
    $(\'n_host\').value = auth.host || \'\';
    $(\'n_dir\').value = auth.dir || \'\';
    $(\'n_user\').value = auth.user || \'\';
    $(\'n_pass\').value = auth.pass || \'\';
    $(\'n_list\').value = (d && d.list) || \'\';
    $(\'noteFormCard\').classList.remove(\'hide\');
  }

  $(\'btnAddNote\').addEventListener(\'click\', function () { openForm(null, {}); });
  $(\'btnCancelNote\').addEventListener(\'click\', function () { $(\'noteFormCard\').classList.add(\'hide\'); editId = null; });
  $(\'btnSaveNote\').addEventListener(\'click\', function () {
    var title = $(\'n_title\').value.trim();
    if (!title) { EM.toast(\'Title is required\', \'err\'); return; }
    EM.post(\'index.php?api=notes&action=save_note\', {
      id: editId || \'\',
      title: title,
      status: $(\'n_status\').value,
      host: $(\'n_host\').value.trim(),
      dir: $(\'n_dir\').value.trim(),
      user: $(\'n_user\').value.trim(),
      pass: $(\'n_pass\').value,
      text_list: $(\'n_list\').value
    }).then(function (r) {
      if (r.status !== \'success\') { EM.toast(r.message || \'Save failed\', \'err\'); return; }
      EM.toast(\'Container saved\', \'ok\');
      $(\'noteFormCard\').classList.add(\'hide\');
      editId = null;
      refresh();
    });
  });

  refresh();
})();
',
    'modules/notes/index.php' => '<?php
/**
 * Emerald Central Hub V11 — Notes fragment (system containers).
 * Rendered inside the dashboard shell; logic in modules/notes/app.js.
 */
if (!defined(\'EMERALD_SHELL\')) {
    header(\'Location: index.php?p=notes\');
    exit;
}
?>
<div class="card">
  <div class="card-header">
    <h2>System containers</h2>
    <button class="btn btn-primary sm" id="btnAddNote">+ New container</button>
  </div>
  <div class="table-wrap">
    <table class="tbl" id="noteTable">
      <thead><tr>
        <th>Title</th><th>Host</th><th>User</th><th>Status</th><th>Owner</th><th class="right">Actions</th>
      </tr></thead>
      <tbody><tr><td colspan="6" class="empty">Loading…</td></tr></tbody>
    </table>
  </div>
</div>

<div class="card hide" id="noteFormCard">
  <h2 id="noteFormTitle">New container</h2>
  <div class="grid grid-2">
    <div class="field"><label>Title</label><input type="text" id="n_title" placeholder="Production server"></div>
    <div class="field"><label>Status</label><select id="n_status"><option value="active">active</option><option value="inactive">inactive</option></select></div>
    <div class="field"><label>Host</label><input type="text" id="n_host" placeholder="host:port"></div>
    <div class="field"><label>Directory</label><input type="text" id="n_dir" placeholder="/home/user"></div>
    <div class="field"><label>Username</label><input type="text" id="n_user" autocomplete="off"></div>
    <div class="field"><label>Password</label><input type="password" id="n_pass" autocomplete="off"></div>
  </div>
  <div class="field"><label>Command list (one per line, "--> " prefix optional)</label><textarea id="n_list" placeholder="--> cd /app&#10;--> npm run build"></textarea></div>
  <div class="row">
    <button class="btn btn-primary" id="btnSaveNote">Save container</button>
    <button class="btn ghost" id="btnCancelNote">Cancel</button>
  </div>
</div>
<script src="modules/notes/app.js"></script>
',
    'modules/storage/api.php' => '<?php
/**
 * Emerald Central Hub V11 — Storage module API (user file vault).
 * Ported from V10 core/api.php with an added containment guard:
 * every resolved path must stay inside ASSETS_DIR.
 *
 * Actions:
 *   list_files (GET path) | upload | create_folder | create_file |
 *   rename_file | zip_file | unzip_file | multi_delete | paste_files |
 *   delete_file | read_file | save_file
 */
if (!defined(\'EMERALD_DISPATCH\')) {
    http_response_code(403);
    exit;
}

/* ------------------------------------------------------------------ *
 *  Containment helper — resolves a user-supplied path inside ASSETS_DIR
 * ------------------------------------------------------------------ */
function stg_resolve($path) {
    $base = realpath(ASSETS_DIR);
    if ($base === false) { @mkdir(ASSETS_DIR, 0755, true); $base = realpath(ASSETS_DIR); }
    if ($base === false) { return null; }
    $clean = sec_clean_path($path);
    $full = ($clean === \'\') ? $base : $base . \'/\' . $clean;
    $rp = realpath($full);
    if ($rp === false) { $rp = $full; }
    if (strpos($rp, $base) !== 0) { return null; }
    return $rp;
}

/* ------------------------------------------------------------------ *
 *  list_files
 * ------------------------------------------------------------------ */
if ($action === \'list_files\') {
    $path_param = isset($_GET[\'path\']) ? trim(sec_str($_GET[\'path\']), \'/\') : \'\';
    $scan_dir = stg_resolve($path_param);
    if ($scan_dir === null) {
        sec_json_err(\'Invalid path.\', \'error\');
    }
    $files = array();
    $file_meta = db_load(\'file_meta\');
    if (is_dir($scan_dir)) {
        $dir = new DirectoryIterator($scan_dir);
        foreach ($dir as $fileinfo) {
            if ($fileinfo->isDot()) { continue; }
            if ($fileinfo->getFilename() === \'notepad\' || $fileinfo->getFilename() === \'.htaccess\') { continue; }
            $filename = $fileinfo->getFilename();
            $meta_key = $path_param ? $path_param . \'/\' . $filename : $filename;
            $owner = isset($file_meta[$meta_key]) ? $file_meta[$meta_key] : \'System\';
            $is_dir = $fileinfo->isDir();
            $files[] = array(
                \'name\' => $filename,
                \'ext\' => $is_dir ? \'DIR\' : pathinfo($filename, PATHINFO_EXTENSION),
                \'size\' => formatSize($fileinfo->getSize()),
                \'modified\' => date(\'Y-m-d H:i:s\', $fileinfo->getMTime()),
                \'is_dir\' => $is_dir,
                \'owner\' => $owner,
                \'link_name\' => pathinfo($filename, PATHINFO_FILENAME),
            );
        }
    }
    sec_json_out(array(\'path\' => $path_param, \'files\' => array_values($files)));
}

/* ------------------------------------------------------------------ *
 *  upload
 * ------------------------------------------------------------------ */
if ($action === \'upload\') {
    if (!empty($_FILES)) {
        $path_param = isset($_POST[\'path\']) ? trim(sec_str($_POST[\'path\']), \'/\') : \'\';
        $relative_path = isset($_POST[\'relative_path\']) ? trim(sec_str($_POST[\'relative_path\']), \'/\') : \'\';
        $base_dir = stg_resolve($path_param);
        if ($base_dir === null) { sec_json_err(\'Invalid path.\', \'error\'); }
        if (!is_dir($base_dir)) { @mkdir($base_dir, 0755, true); }

        if ($relative_path !== \'\' && strpos($relative_path, \'/\') !== false) {
            $sub_dir = dirname($relative_path);
            $target_dir = $base_dir . \'/\' . $sub_dir;
            if (!is_dir($target_dir)) { @mkdir($target_dir, 0755, true); }
            if (stg_resolve($path_param ? $path_param . \'/\' . $sub_dir : $sub_dir) === null) { sec_json_err(\'Invalid path.\', \'error\'); }
            $meta_name = $sub_dir . \'/\' . basename($relative_path);
            $name = basename($relative_path);
        } else {
            if (!is_dir($base_dir)) { @mkdir($base_dir, 0755, true); }
            $name = sec_str($_FILES[\'file\'][\'name\']);
            $name = str_replace(array(\'/\', \'\\\\\', "\\0"), \'\', $name);
            if ($name === \'\') { sec_json_err(\'Invalid filename.\', \'error\'); }
            $meta_name = $name;
        }

        if (move_uploaded_file($_FILES[\'file\'][\'tmp_name\'], $base_dir . \'/\' . $name)) {
            $final_meta_key = $path_param ? $path_param . \'/\' . $meta_name : $meta_name;
            $file_meta = db_load(\'file_meta\');
            $file_meta[$final_meta_key] = isset($file_meta[$final_meta_key]) ? $file_meta[$final_meta_key] : $current_user;
            db_save(\'file_meta\', $file_meta);
            logActivity($current_user, \'Uploaded asset: \' . $final_meta_key);
            sec_json_out(array(\'status\' => \'success\'));
        }
        sec_json_err(\'Upload failed.\', \'error\');
    }
    sec_json_err(\'No file received.\', \'error\');
}

/* ------------------------------------------------------------------ *
 *  create_folder / create_file
 * ------------------------------------------------------------------ */
if ($action === \'create_folder\' || $action === \'create_file\') {
    $target_name = isset($_POST[\'name\']) ? $_POST[\'name\'] : (isset($_POST[\'folder\']) ? $_POST[\'folder\'] : (isset($_POST[\'file\']) ? $_POST[\'file\'] : \'\'));
    $target_name = sec_str($target_name);
    $target_name = str_replace(array(\'/\', \'\\\\\', "\\0"), \'\', $target_name);
    $path_param = isset($_POST[\'path\']) ? trim(sec_str($_POST[\'path\']), \'/\') : \'\';
    $base_dir = stg_resolve($path_param);
    if ($base_dir === null) { sec_json_err(\'Invalid path.\', \'error\'); }
    if ($target_name === \'\') { sec_json_err(\'Name required.\', \'error\'); }
    $path = $base_dir . \'/\' . $target_name;

    if (!file_exists($path)) {
        if ($action === \'create_folder\') {
            @mkdir($path, 0755);
            logActivity($current_user, \'Created directory: \' . $target_name);
        } else {
            @file_put_contents($path, \'\');
            logActivity($current_user, \'Created file: \' . $target_name);
        }
        $meta_key = $path_param ? $path_param . \'/\' . $target_name : $target_name;
        $file_meta = db_load(\'file_meta\');
        $file_meta[$meta_key] = isset($file_meta[$meta_key]) ? $file_meta[$meta_key] : $current_user;
        db_save(\'file_meta\', $file_meta);
        sec_json_out(array(\'status\' => \'success\'));
    }
    sec_json_err(\'Target already exists.\', \'error\');
}

/* ------------------------------------------------------------------ *
 *  rename_file
 * ------------------------------------------------------------------ */
if ($action === \'rename_file\') {
    $old_name = sec_str(isset($_POST[\'old_name\']) ? $_POST[\'old_name\'] : \'\');
    $new_name = sec_str(isset($_POST[\'new_name\']) ? $_POST[\'new_name\'] : \'\');
    $old_name = str_replace(array(\'/\', \'\\\\\', "\\0"), \'\', $old_name);
    $new_name = str_replace(array(\'/\', \'\\\\\', "\\0"), \'\', $new_name);
    $path_param = isset($_POST[\'path\']) ? trim(sec_str($_POST[\'path\']), \'/\') : \'\';
    $base_dir = stg_resolve($path_param);
    if ($base_dir === null) { sec_json_err(\'Invalid path.\', \'error\'); }

    if ($old_name !== \'\' && $new_name !== \'\' && file_exists($base_dir . \'/\' . $old_name) && !file_exists($base_dir . \'/\' . $new_name)) {
        if (@rename($base_dir . \'/\' . $old_name, $base_dir . \'/\' . $new_name)) {
            $file_meta = db_load(\'file_meta\');
            $old_k = $path_param ? $path_param . \'/\' . $old_name : $old_name;
            $new_k = $path_param ? $path_param . \'/\' . $new_name : $new_name;
            $file_meta[$new_k] = isset($file_meta[$old_k]) ? $file_meta[$old_k] : $current_user;
            if (isset($file_meta[$old_k])) { unset($file_meta[$old_k]); }
            db_save(\'file_meta\', $file_meta);
            logActivity($current_user, \'Renamed asset from \' . $old_name . \' to \' . $new_name);
            sec_json_out(array(\'status\' => \'success\'));
        }
        sec_json_err(\'Permission denied.\', \'error\');
    }
    sec_json_err(\'Not found or target exists.\', \'error\');
}

/* ------------------------------------------------------------------ *
 *  zip_file / unzip_file
 * ------------------------------------------------------------------ */
if ($action === \'zip_file\' || $action === \'unzip_file\') {
    if (!class_exists(\'ZipArchive\')) { sec_json_err(\'Zip support is not available on this host.\', \'error\'); }
    $file = sec_str(isset($_POST[\'file\']) ? $_POST[\'file\'] : \'\');
    $file = str_replace(array(\'/\', \'\\\\\', "\\0"), \'\', $file);
    $path_param = isset($_POST[\'path\']) ? trim(sec_str($_POST[\'path\']), \'/\') : \'\';
    $base_dir = stg_resolve($path_param);
    if ($base_dir === null) { sec_json_err(\'Invalid path.\', \'error\'); }
    $path = $base_dir . \'/\' . $file;

    if (file_exists($path)) {
        if ($action === \'zip_file\') {
            $zipPath = $path . \'.zip\';
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                if (is_dir($path)) {
                    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::LEAVES_ONLY);
                    foreach ($it as $f) {
                        if (!$f->isDir()) {
                            $zip->addFile($f->getRealPath(), substr($f->getRealPath(), strlen($path) + 1));
                        }
                    }
                } else {
                    $zip->addFile($path, basename($path));
                }
                $zip->close();
                $meta_key = $path_param ? $path_param . \'/\' . basename($zipPath) : basename($zipPath);
                $file_meta = db_load(\'file_meta\');
                $file_meta[$meta_key] = isset($file_meta[$meta_key]) ? $file_meta[$meta_key] : $current_user;
                db_save(\'file_meta\', $file_meta);
                logActivity($current_user, \'Compressed archive: \' . basename($zipPath));
                sec_json_out(array(\'status\' => \'success\'));
            }
            sec_json_err(\'Failed to create archive.\', \'error\');
        } else {
            $zip = new ZipArchive();
            if ($zip->open($path) === true) {
                $extract_name = pathinfo($path, PATHINFO_FILENAME);
                $extract_path = dirname($path) . \'/\' . $extract_name;
                if (!is_dir($extract_path)) { @mkdir($extract_path, 0755); }
                $zip->extractTo($extract_path);
                $zip->close();
                $meta_key = $path_param ? $path_param . \'/\' . $extract_name : $extract_name;
                $file_meta = db_load(\'file_meta\');
                $file_meta[$meta_key] = isset($file_meta[$meta_key]) ? $file_meta[$meta_key] : $current_user;
                db_save(\'file_meta\', $file_meta);
                logActivity($current_user, \'Extracted archive: \' . $file);
                sec_json_out(array(\'status\' => \'success\'));
            }
            sec_json_err(\'Failed to open archive.\', \'error\');
        }
    }
    sec_json_err(\'File not found.\', \'error\');
}

/* ------------------------------------------------------------------ *
 *  multi_delete
 * ------------------------------------------------------------------ */
if ($action === \'multi_delete\') {
    $files = json_decode(isset($_POST[\'files\']) ? $_POST[\'files\'] : \'[]\', true);
    if (!is_array($files)) { $files = array(); }
    $path_param = isset($_POST[\'path\']) ? trim(sec_str($_POST[\'path\']), \'/\') : \'\';
    $base_dir = stg_resolve($path_param);
    if ($base_dir === null) { sec_json_err(\'Invalid path.\', \'error\'); }

    $file_meta = db_load(\'file_meta\');
    $all_success = true;
    foreach ($files as $file) {
        $file = sec_str($file);
        $file = str_replace(array(\'/\', \'\\\\\', "\\0"), \'\', $file);
        $path = $base_dir . \'/\' . $file;
        $meta_key = $path_param ? $path_param . \'/\' . $file : $file;
        $owner = isset($file_meta[$meta_key]) ? $file_meta[$meta_key] : \'System\';
        $is_authorized = ($owner === $current_user || $owner === \'System\');
        if ($is_authorized && file_exists($path)) {
            if (is_dir($path)) { recursiveRemoveDir($path); } else { @unlink($path); }
            if (isset($file_meta[$meta_key])) { unset($file_meta[$meta_key]); }
            logActivity($current_user, \'Purged asset: \' . $file);
        } else {
            $all_success = false;
        }
    }
    db_save(\'file_meta\', $file_meta);
    if ($all_success) { sec_json_out(array(\'status\' => \'success\')); }
    sec_json_err(\'Akses Ditolak: Sebagian file gagal dihapus karena Anda bukan pemilik aslinya.\', \'error\');
}

/* ------------------------------------------------------------------ *
 *  paste_files (cut / copy)
 * ------------------------------------------------------------------ */
if ($action === \'paste_files\') {
    $files = json_decode(isset($_POST[\'files\']) ? $_POST[\'files\'] : \'[]\', true);
    if (!is_array($files)) { $files = array(); }
    $source_path = isset($_POST[\'source_path\']) ? trim(sec_str($_POST[\'source_path\']), \'/\') : \'\';
    $target_path = isset($_POST[\'target_path\']) ? trim(sec_str($_POST[\'target_path\']), \'/\') : \'\';
    $mode = isset($_POST[\'mode\']) ? sec_str($_POST[\'mode\']) : \'copy\';

    $base_src = stg_resolve($source_path);
    $base_tgt = stg_resolve($target_path);
    if ($base_src === null || $base_tgt === null) { sec_json_err(\'Invalid path.\', \'error\'); }

    $file_meta = db_load(\'file_meta\');
    foreach ($files as $file) {
        $file = sec_str($file);
        $file = str_replace(array(\'/\', \'\\\\\', "\\0"), \'\', $file);
        $src = $base_src . \'/\' . $file;
        $tgt = $base_tgt . \'/\' . $file;
        $src_meta_key = $source_path ? $source_path . \'/\' . $file : $file;
        $tgt_meta_key = $target_path ? $target_path . \'/\' . $file : $file;

        if (file_exists($src)) {
            if ($mode === \'cut\') {
                @rename($src, $tgt);
                $file_meta[$tgt_meta_key] = isset($file_meta[$src_meta_key]) ? $file_meta[$src_meta_key] : $current_user;
                if (isset($file_meta[$src_meta_key])) { unset($file_meta[$src_meta_key]); }
            } else {
                if (is_dir($src)) { recursiveCopy($src, $tgt); } else { @copy($src, $tgt); }
                $file_meta[$tgt_meta_key] = $current_user;
            }
        }
    }
    db_save(\'file_meta\', $file_meta);
    sec_json_out(array(\'status\' => \'success\'));
}

/* ------------------------------------------------------------------ *
 *  delete_file
 * ------------------------------------------------------------------ */
if ($action === \'delete_file\') {
    $file = sec_str(isset($_POST[\'file\']) ? $_POST[\'file\'] : \'\');
    $file = str_replace(array(\'/\', \'\\\\\', "\\0"), \'\', $file);
    $path_param = isset($_POST[\'path\']) ? trim(sec_str($_POST[\'path\']), \'/\') : \'\';
    $base_dir = stg_resolve($path_param);
    if ($base_dir === null) { sec_json_err(\'Invalid path.\', \'error\'); }
    $path = $base_dir . \'/\' . $file;

    $file_meta = db_load(\'file_meta\');
    $meta_key = $path_param ? $path_param . \'/\' . $file : $file;
    $owner = isset($file_meta[$meta_key]) ? $file_meta[$meta_key] : \'System\';

    if ($owner !== \'System\' && $owner !== $current_user) {
        sec_json_err(\'Akses Ditolak: Mutlak hanya pemilik asli (\' . $owner . \') yang bisa menghapus data ini.\', \'error\');
    }

    if (file_exists($path)) {
        if (is_dir($path)) { recursiveRemoveDir($path); } else { @unlink($path); }
        if (isset($file_meta[$meta_key])) {
            unset($file_meta[$meta_key]);
            db_save(\'file_meta\', $file_meta);
        }
        logActivity($current_user, \'Purged asset: \' . $file);
        sec_json_out(array(\'status\' => \'success\'));
    }
    sec_json_err(\'Not found.\', \'error\');
}

/* ------------------------------------------------------------------ *
 *  read_file
 * ------------------------------------------------------------------ */
if ($action === \'read_file\') {
    $file = sec_str(isset($_POST[\'file\']) ? $_POST[\'file\'] : \'\');
    $file = str_replace(array(\'/\', \'\\\\\', "\\0"), \'\', $file);
    $path_param = isset($_POST[\'path\']) ? trim(sec_str($_POST[\'path\']), \'/\') : \'\';
    $base_dir = stg_resolve($path_param);
    if ($base_dir === null) { sec_json_err(\'Invalid path.\', \'error\'); }
    $path = $base_dir . \'/\' . $file;
    if (file_exists($path) && is_file($path)) {
        sec_json_out(array(
            \'status\' => \'success\',
            \'content\' => (string)file_get_contents($path),
            \'modified\' => date(\'Y-m-d H:i:s\', filemtime($path)),
            \'size\' => formatSize(filesize($path)),
        ));
    }
    sec_json_err(\'File not found.\', \'error\');
}

/* ------------------------------------------------------------------ *
 *  save_file
 * ------------------------------------------------------------------ */
if ($action === \'save_file\') {
    $file = sec_str(isset($_POST[\'file\']) ? $_POST[\'file\'] : \'\');
    $file = str_replace(array(\'/\', \'\\\\\', "\\0"), \'\', $file);
    $content = isset($_POST[\'content\']) ? $_POST[\'content\'] : \'\';
    $path_param = isset($_POST[\'path\']) ? trim(sec_str($_POST[\'path\']), \'/\') : \'\';
    $base_dir = stg_resolve($path_param);
    if ($base_dir === null) { sec_json_err(\'Invalid path.\', \'error\'); }
    if ($file === \'\') { sec_json_err(\'Filename required.\', \'error\'); }
    $path = $base_dir . \'/\' . $file;
    if (@file_put_contents($path, $content) !== false) {
        logActivity($current_user, \'Modified source: \' . $file);
        sec_json_out(array(\'status\' => \'success\'));
    }
    sec_json_err(\'Could not save file.\', \'error\');
}

sec_json_err(\'Unknown action.\', \'notfound\');
',
    'modules/storage/app.js' => '/* Emerald Central Hub V11 — Storage module logic */
(function () {
  \'use strict\';
  var EM = window.EM;
  var state = { path: \'\', selected: [], clipboard: null };

  var $ = function (id) { return document.getElementById(id); };

  function refresh() {
    $(\'fileTable\').querySelector(\'tbody\').innerHTML = \'<tr><td colspan="7" class="empty">Loading…</td></tr>\';
    state.selected = [];
    renderClipboard();
    EM.get(\'index.php?api=storage&action=list_files&path=\' + encodeURIComponent(state.path)).then(function (res) {
      if (!res || !Array.isArray(res.files)) {
        $(\'fileTable\').querySelector(\'tbody\').innerHTML = \'<tr><td colspan="7" class="empty">Could not load vault.</td></tr>\';
        return;
      }
      state.path = res.path || \'\';
      $(\'crumbPath\').textContent = \'/\' + state.path;
      var tb = $(\'fileTable\').querySelector(\'tbody\');
      tb.innerHTML = \'\';
      if (!res.files.length) {
        tb.innerHTML = \'<tr><td colspan="7" class="empty">Empty directory.</td></tr>\';
        return;
      }
      res.files.forEach(function (f) {
        var tr = document.createElement(\'tr\');
        var ext = f.is_dir ? \'DIR\' : f.ext.toUpperCase();
        var ico = f.is_dir ? \'📁\' : (f.ext === \'zip\' ? \'🗜\' : \'📄\');
        var canMod = (f.owner === EM.user || f.owner === \'System\');
        tr.innerHTML =
          \'<td><input type="checkbox" class="selbox" data-name="\' + EM.escapeHtml(f.name) + \'"></td>\' +
          \'<td><div class="file-row"><span class="ico">\' + ico + \'</span><span class="nm">\' + EM.escapeHtml(f.name) + \'</span></div></td>\' +
          \'<td class="mono">\' + EM.escapeHtml(ext) + \'</td>\' +
          \'<td class="mono">\' + EM.escapeHtml(f.size) + \'</td>\' +
          \'<td class="mono small">\' + EM.escapeHtml(f.modified) + \'</td>\' +
          \'<td>\' + EM.escapeHtml(f.owner) + \'</td>\' +
          \'<td><div class="actions">\' +
            (f.is_dir
              ? \'<button class="btn ghost sm" data-act="open">Open</button>\' +
                (canMod ? \'<button class="btn ghost sm" data-act="zip">Zip</button>\' : \'\') +
                (canMod ? \'<button class="btn danger sm" data-act="del">Del</button>\' : \'\')
              : \'<button class="btn ghost sm" data-act="read">Edit</button>\' +
                (f.ext === \'zip\' ? \'<button class="btn ghost sm" data-act="unzip">Unzip</button>\' : \'\') +
                (canMod ? \'<button class="btn danger sm" data-act="del">Del</button>\' : \'\')) +
          \'</div></td>\';
        tr.querySelector(\'.selbox\').addEventListener(\'change\', function () {
          var i = state.selected.indexOf(f.name);
          if (this.checked) { if (i < 0) { state.selected.push(f.name); } }
          else { if (i >= 0) { state.selected.splice(i, 1); } }
          renderClipboard();
        });
        tr.querySelector(\'.nm\').addEventListener(\'click\', function () { f.is_dir ? openDir(f.name) : openFile(f.name); });
        tr.querySelectorAll(\'[data-act]\').forEach(function (btn) {
          btn.addEventListener(\'click\', function () {
            var act = btn.getAttribute(\'data-act\');
            if (act === \'open\') { openDir(f.name); }
            else if (act === \'read\') { openFile(f.name); }
            else if (act === \'del\') { delOne(f.name); }
            else if (act === \'zip\') { doZip(f.name); }
            else if (act === \'unzip\') { doUnzip(f.name); }
          });
        });
        tb.appendChild(tr);
      });
    }).catch(function () {
      $(\'fileTable\').querySelector(\'tbody\').innerHTML = \'<tr><td colspan="7" class="empty">Could not load vault.</td></tr>\';
    });
  }

  function openDir(name) {
    state.path = state.path ? state.path + \'/\' + name : name;
    refresh();
  }
  function openFile(name) {
    EM.post(\'index.php?api=storage&action=read_file\', { path: state.path, file: name }).then(function (res) {
      if (!res || res.status !== \'success\') { EM.toast(\'Could not read file\', \'err\'); return; }
      $(\'editorTitle\').textContent = name + \' (\' + res.size + \')\';
      $(\'editorArea\').value = res.content;
      $(\'editorArea\').dataset.file = name;
      $(\'editorCard\').classList.remove(\'hide\');
    });
  }
  function delOne(name) {
    if (!confirm(\'Delete "\' + name + \'" permanently?\')) { return; }
    EM.post(\'index.php?api=storage&action=delete_file\', { path: state.path, file: name }).then(function (res) {
      EM.toast(res.status === \'success\' ? \'Deleted\' : (res.message || \'Delete failed\'), res.status === \'success\' ? \'ok\' : \'err\');
      refresh();
    });
  }
  function doZip(name) {
    EM.post(\'index.php?api=storage&action=zip_file\', { path: state.path, file: name }).then(function (res) {
      EM.toast(res.status === \'success\' ? \'Archived\' : (res.message || \'Zip failed\'), res.status === \'success\' ? \'ok\' : \'err\');
      refresh();
    });
  }
  function doUnzip(name) {
    EM.post(\'index.php?api=storage&action=unzip_file\', { path: state.path, file: name }).then(function (res) {
      EM.toast(res.status === \'success\' ? \'Extracted\' : (res.message || \'Unzip failed\'), res.status === \'success\' ? \'ok\' : \'err\');
      refresh();
    });
  }
  function renderClipboard() {
    var has = !!state.clipboard;
    $(\'btnCutSel\').classList.toggle(\'hide\', !state.selected.length);
    $(\'btnCopySel\').classList.toggle(\'hide\', !state.selected.length);
    $(\'btnDeleteSel\').classList.toggle(\'hide\', !state.selected.length);
    $(\'btnPaste\').classList.toggle(\'hide\', !has);
  }

  $(\'btnUpDir\').addEventListener(\'click\', function () {
    var parts = state.path.split(\'/\').filter(Boolean);
    parts.pop();
    state.path = parts.join(\'/\');
    refresh();
  });
  $(\'btnNewFolder\').addEventListener(\'click\', function () {
    var name = prompt(\'Folder name:\');
    if (!name) { return; }
    EM.post(\'index.php?api=storage&action=create_folder\', { path: state.path, name: name }).then(function (res) {
      EM.toast(res.status === \'success\' ? \'Created\' : (res.message || \'Failed\'), res.status === \'success\' ? \'ok\' : \'err\');
      refresh();
    });
  });
  $(\'btnNewFile\').addEventListener(\'click\', function () {
    var name = prompt(\'File name:\');
    if (!name) { return; }
    EM.post(\'index.php?api=storage&action=create_file\', { path: state.path, name: name }).then(function (res) {
      EM.toast(res.status === \'success\' ? \'Created\' : (res.message || \'Failed\'), res.status === \'success\' ? \'ok\' : \'err\');
      refresh();
    });
  });
  $(\'btnUpload\').addEventListener(\'click\', function () { $(\'fileInput\').click(); });
  $(\'fileInput\').addEventListener(\'change\', function () {
    var files = Array.prototype.slice.call(this.files);
    if (!files.length) { return; }
    var p = Promise.resolve();
    files.forEach(function (f) {
      p = p.then(function () {
        var fd = new FormData();
        fd.append(\'csrf\', EM.csrf);
        fd.append(\'path\', state.path);
        fd.append(\'file\', f);
        return fetch(\'index.php?api=storage&action=upload\', { method: \'POST\', body: fd })
          .then(function (r) { return r.json(); });
      });
    });
    p.then(function () {
      EM.toast(\'Upload finished\', \'ok\');
      $(\'fileInput\').value = \'\';
      refresh();
    });
  });
  $(\'selAll\').addEventListener(\'change\', function () {
    var boxes = document.querySelectorAll(\'#fileTable .selbox\');
    state.selected = [];
    for (var i = 0; i < boxes.length; i++) {
      boxes[i].checked = this.checked;
      if (this.checked) { state.selected.push(boxes[i].getAttribute(\'data-name\')); }
    }
    renderClipboard();
  });
  $(\'btnDeleteSel\').addEventListener(\'click\', function () {
    if (!state.selected.length || !confirm(\'Delete \' + state.selected.length + \' item(s)?\')) { return; }
    EM.post(\'index.php?api=storage&action=multi_delete\', { path: state.path, files: JSON.stringify(state.selected) }).then(function (res) {
      EM.toast(res.status === \'success\' ? \'Deleted\' : (res.message || \'Some items could not be deleted\'), res.status === \'success\' ? \'ok\' : \'err\');
      state.selected = [];
      refresh();
    });
  });
  $(\'btnCutSel\').addEventListener(\'click\', function () { state.clipboard = { mode: \'cut\', source: state.path, files: state.selected.slice() }; renderClipboard(); EM.toast(\'Cut \' + state.clipboard.files.length + \' item(s) — choose destination and paste\'); });
  $(\'btnCopySel\').addEventListener(\'click\', function () { state.clipboard = { mode: \'copy\', source: state.path, files: state.selected.slice() }; renderClipboard(); EM.toast(\'Copied \' + state.clipboard.files.length + \' item(s) — choose destination and paste\'); });
  $(\'btnPaste\').addEventListener(\'click\', function () {
    if (!state.clipboard) { return; }
    var source = state.path;
    EM.post(\'index.php?api=storage&action=paste_files\', {
      files: JSON.stringify(state.clipboard.files),
      source_path: state.clipboard.source || \'\',
      target_path: state.path,
      mode: state.clipboard.mode
    }).then(function (res) {
      EM.toast(res.status === \'success\' ? \'Pasted\' : (res.message || \'Paste failed\'), res.status === \'success\' ? \'ok\' : \'err\');
      state.clipboard = null;
      state.selected = [];
      refresh();
    });
  });
  $(\'btnSaveFile\').addEventListener(\'click\', function () {
    var name = $(\'editorArea\').dataset.file;
    if (!name) { return; }
    EM.post(\'index.php?api=storage&action=save_file\', { path: state.path, file: name, content: $(\'editorArea\').value }).then(function (res) {
      EM.toast(res.status === \'success\' ? \'Saved\' : (res.message || \'Save failed\'), res.status === \'success\' ? \'ok\' : \'err\');
    });
  });
  $(\'btnCloseEditor\').addEventListener(\'click\', function () {
    $(\'editorCard\').classList.add(\'hide\');
    $(\'editorArea\').value = \'\';
  });

  refresh();
})();
',
    'modules/storage/index.php' => '<?php
/**
 * Emerald Central Hub V11 — Storage fragment (file vault).
 * Rendered inside the dashboard shell; logic in modules/storage/app.js.
 */
if (!defined(\'EMERALD_SHELL\')) {
    header(\'Location: index.php?p=storage\');
    exit;
}
?>
<div class="card">
  <div class="card-header">
    <h2>File vault</h2>
    <div class="row">
      <button class="btn ghost sm" id="btnUpDir">↑ Up</button>
      <button class="btn sm" id="btnNewFolder">+ Folder</button>
      <button class="btn sm" id="btnNewFile">+ File</button>
      <button class="btn sm" id="btnUpload">Upload</button>
      <button class="btn danger sm hide" id="btnDeleteSel">Delete selected</button>
      <button class="btn ghost sm hide" id="btnCutSel">Cut</button>
      <button class="btn ghost sm hide" id="btnCopySel">Copy</button>
      <button class="btn ok sm hide" id="btnPaste">Paste here</button>
      <input type="file" id="fileInput" multiple class="hide">
    </div>
  </div>
  <p class="muted small mb" id="crumbPath">/</p>
  <div class="table-wrap">
    <table class="tbl" id="fileTable">
      <thead><tr>
        <th style="width:32px"><input type="checkbox" id="selAll"></th>
        <th>Name</th><th>Ext</th><th>Size</th><th>Modified</th><th>Owner</th><th class="right">Actions</th>
      </tr></thead>
      <tbody><tr><td colspan="7" class="empty">Loading…</td></tr></tbody>
    </table>
  </div>
</div>

<div class="card hide" id="editorCard">
  <div class="card-header">
    <h2 id="editorTitle">Editor</h2>
    <div class="row">
      <button class="btn ok sm" id="btnSaveFile">Save</button>
      <button class="btn ghost sm" id="btnCloseEditor">Close</button>
    </div>
  </div>
  <textarea id="editorArea" spellcheck="false"></textarea>
</div>
<script src="modules/storage/app.js"></script>
',
    'modules/users/api.php' => '<?php
/**
 * Emerald Central Hub V11 — Users module API.
 * Ported from V10: list_users | add_user | delete_user | update_profile.
 *  - add_user: owner role only
 *  - delete_user: only your own identity; migrate_to moves ownership
 *    (file_meta / notes / cloaks / domains / notepad dir) or purges data.
 *  - update_profile: username / password / avatar.
 */
if (!defined(\'EMERALD_DISPATCH\')) {
    http_response_code(403);
    exit;
}

if ($action === \'list_users\') {
    $out = array();
    foreach ($users as $uname => $data) {
        $out[] = array(
            \'username\' => $uname,
            \'role\' => isset($data[\'role\']) ? $data[\'role\'] : \'user\',
            \'avatar\' => isset($data[\'avatar\']) ? $data[\'avatar\'] : \'\',
            \'last_active\' => isset($data[\'last_active\']) ? $data[\'last_active\'] : 0,
        );
    }
    sec_json_out($out);
}

if ($action === \'add_user\') {
    if ($current_role !== \'owner\') {
        sec_json_err(\'Only owners can add users.\', \'forbidden\');
    }
    $new_user = sec_str(isset($_POST[\'username\']) ? $_POST[\'username\'] : \'\');
    $new_role = sec_str(isset($_POST[\'role\']) ? $_POST[\'role\'] : \'user\');
    if ($new_user === \'\') { sec_json_err(\'Username required.\', \'error\'); }
    if (!in_array($new_role, array(\'owner\', \'admin\', \'user\', \'guest\'), true)) { $new_role = \'user\'; }
    if (!isset($users[$new_user])) {
        $users[$new_user] = array(
            \'password\' => password_hash(isset($_POST[\'password\']) ? $_POST[\'password\'] : \'\', PASSWORD_DEFAULT),
            \'role\' => $new_role,
            \'avatar\' => \'\',
            \'last_active\' => time(),
            \'sec_q\' => \'\',
            \'sec_a\' => \'\',
        );
        db_save(\'users\', $users);
        logActivity($current_user, \'Registered Identity: \' . $new_user);
        sec_json_out(array(\'status\' => \'success\'));
    }
    sec_json_err(\'User exists.\', \'error\');
}

if ($action === \'delete_user\') {
    $target_user = sec_str(isset($_POST[\'target_user\']) ? $_POST[\'target_user\'] : \'\');
    $migrate_to = sec_str(isset($_POST[\'migrate_to\']) ? $_POST[\'migrate_to\'] : \'\');

    if ($target_user !== $current_user) {
        sec_json_err(\'Akses Ditolak: Anda mutlak hanya bisa menghapus identitas akun Anda sendiri.\', \'forbidden\');
    }
    if (!isset($users[$target_user])) {
        sec_json_err(\'Target user not found.\', \'error\');
    }

    if ($migrate_to !== \'\') {
        if (!isset($users[$migrate_to])) {
            sec_json_err(\'Migration target user does not exist.\', \'error\');
        }
        $file_meta = db_load(\'file_meta\');
        foreach ($file_meta as $fname => $owner) { if ($owner === $target_user) { $file_meta[$fname] = $migrate_to; } }
        db_save(\'file_meta\', $file_meta);

        $notes = db_load(\'notes\');
        foreach ($notes as $nid => $ndata) { if (isset($ndata[\'owner\']) && $ndata[\'owner\'] === $target_user) { $notes[$nid][\'owner\'] = $migrate_to; } }
        db_save(\'notes\', $notes);

        $cloaks = db_load(\'cloaking\');
        foreach ($cloaks as $cid => $cdata) { if (isset($cdata[\'owner\']) && $cdata[\'owner\'] === $target_user) { $cloaks[$cid][\'owner\'] = $migrate_to; } }
        db_save(\'cloaking\', $cloaks);

        $notepad_dir = MODULES_DIR . \'/notepad/data\';
        $old_dir = $notepad_dir . \'/\' . $target_user;
        $new_dir = $notepad_dir . \'/\' . $migrate_to;
        if (is_dir($old_dir)) {
            if (!is_dir($new_dir)) { @mkdir($new_dir, 0755, true); }
            $items = @scandir($old_dir);
            if (is_array($items)) {
                foreach ($items as $f) {
                    if ($f === \'.\' || $f === \'..\') { continue; }
                    @rename($old_dir . \'/\' . $f, $new_dir . \'/\' . $f);
                }
            }
            recursiveRemoveDir($old_dir);
        }
        logActivity($current_user, \'Erased User: \' . $target_user . \' (Data migrated to \' . $migrate_to . \')\');
    } else {
        $old_dir = MODULES_DIR . \'/notepad/data/\' . $target_user;
        if (is_dir($old_dir)) { recursiveRemoveDir($old_dir); }
        logActivity($current_user, \'Erased User: \' . $target_user . \' (All data purged)\');
    }

    unset($users[$target_user]);
    db_save(\'users\', $users);
    if ($target_user === $current_user) {
        auth_logout();
    }
    sec_json_out(array(\'status\' => \'success\'));
}

if ($action === \'update_profile\') {
    $old_user = $current_user;
    $new_user = sec_str(isset($_POST[\'username\']) ? $_POST[\'username\'] : \'\');
    $pass = isset($_POST[\'password\']) ? $_POST[\'password\'] : \'\';
    $avatar = sec_str(isset($_POST[\'avatar\']) ? $_POST[\'avatar\'] : \'\');
    if ($new_user === \'\') { sec_json_err(\'Username cannot be empty.\', \'error\'); }
    if ($new_user !== $old_user && isset($users[$new_user])) { sec_json_err(\'Username already taken.\', \'error\'); }

    $userData = $users[$old_user];
    if (!empty($pass)) { $userData[\'password\'] = password_hash($pass, PASSWORD_DEFAULT); }
    if (!empty($avatar)) { $userData[\'avatar\'] = $avatar; }

    if ($new_user !== $old_user) {
        unset($users[$old_user]);
        $users[$new_user] = $userData;
        $_SESSION[\'emerald_user\'] = $new_user;
    } else {
        $users[$old_user] = $userData;
    }
    db_save(\'users\', $users);
    logActivity($new_user, \'Updated Profile Identity\');
    sec_json_out(array(\'status\' => \'success\', \'new_user\' => $new_user));
}

sec_json_err(\'Unknown action.\', \'notfound\');
',
    'modules/users/app.js' => '/* Emerald Central Hub V11 — Users module logic */
(function () {
  \'use strict\';
  var EM = window.EM;
  var $ = function (id) { return document.getElementById(id); };

  function refresh() {
    var tb = $(\'userTable\').querySelector(\'tbody\');
    tb.innerHTML = \'<tr><td colspan="4" class="empty">Loading…</td></tr>\';
    EM.get(\'index.php?api=users&action=list_users\').then(function (list) {
      if (!Array.isArray(list)) { tb.innerHTML = \'<tr><td colspan="4" class="empty">Could not load users.</td></tr>\'; return; }
      tb.innerHTML = \'\';
      if (!list.length) { tb.innerHTML = \'<tr><td colspan="4" class="empty">No users.</td></tr>\'; return; }
      list.forEach(function (u) {
        var tr = document.createElement(\'tr\');
        var isSelf = u.username === EM.user;
        tr.innerHTML =
          \'<td><span class="flex"><span class="avatar-mini">\' + EM.escapeHtml(u.username.charAt(0).toUpperCase()) + \'</span><b>\' + EM.escapeHtml(u.username) + \'</b>\' + (isSelf ? \' <span class="badge info">you</span>\' : \'\') + \'</span></td>\' +
          \'<td><span class="badge role-\' + EM.escapeHtml(u.role) + \'">\' + EM.escapeHtml(u.role) + \'</span></td>\' +
          \'<td class="mono small">\' + EM.escapeHtml(EM.fmtTime(u.last_active)) + \'</td>\' +
          \'<td><div class="actions">\' +
            (isSelf ? \'<button class="btn ghost sm" data-act="profile">Profile</button>\' : \'\') +
            (isSelf ? \'<button class="btn danger sm" data-act="del">Delete me</button>\' : \'\') +
          \'</div></td>\';
        var delBtn = tr.querySelector(\'[data-act="del"]\');
        if (delBtn) {
          delBtn.addEventListener(\'click\', function () {
            if (!confirm(\'Delete your identity "\' + u.username + \'"? Data migration or purge follows.\')) { return; }
            var migrate = prompt(\'Migrate data to another user? Leave empty to PURGE all your data.\\nType target username or press Cancel/OK with empty value for purge.\');
            if (migrate === null) { return; }
            EM.post(\'index.php?api=users&action=delete_user\', { target_user: u.username, migrate_to: migrate.trim() }).then(function (r) {
              if (r.status !== \'success\') { EM.toast(r.message || \'Delete failed\', \'err\'); return; }
              EM.toast(\'Identity deleted\', \'ok\');
              setTimeout(function () { window.location.href = \'index.php\'; }, 900);
            });
          });
        }
        var profBtn = tr.querySelector(\'[data-act="profile"]\');
        if (profBtn) { profBtn.addEventListener(\'click\', function () { openProfile(u); }); }
        tb.appendChild(tr);
      });
    }).catch(function () {
      tb.innerHTML = \'<tr><td colspan="4" class="empty">Could not load users.</td></tr>\';
    });
  }

  function openProfile(u) {
    $(\'p_username\').value = u.username;
    $(\'p_avatar\').value = u.avatar || \'\';
    $(\'p_password\').value = \'\';
    $(\'profileCard\').classList.remove(\'hide\');
    $(\'profileCard\').scrollIntoView({ behavior: \'smooth\', block: \'start\' });
  }

  $(\'btnAddUser\').addEventListener(\'click\', function () { $(\'userFormCard\').classList.remove(\'hide\'); });
  $(\'btnCancelUser\').addEventListener(\'click\', function () { $(\'userFormCard\').classList.add(\'hide\'); });
  $(\'btnSaveUser\').addEventListener(\'click\', function () {
    var username = $(\'u_username\').value.trim();
    var password = $(\'u_password\').value;
    if (!username || !password) { EM.toast(\'Username and password required\', \'err\'); return; }
    EM.post(\'index.php?api=users&action=add_user\', { username: username, role: $(\'u_role\').value, password: password }).then(function (r) {
      if (r.status !== \'success\') { EM.toast(r.message || \'Create failed\', \'err\'); return; }
      EM.toast(\'User created\', \'ok\');
      $(\'userFormCard\').classList.add(\'hide\');
      $(\'u_username\').value = \'\'; $(\'u_password\').value = \'\';
      refresh();
    });
  });
  $(\'btnSaveProfile\').addEventListener(\'click\', function () {
    var username = $(\'p_username\').value.trim();
    if (!username) { EM.toast(\'Username cannot be empty\', \'err\'); return; }
    EM.post(\'index.php?api=users&action=update_profile\', {
      username: username,
      avatar: $(\'p_avatar\').value.trim(),
      password: $(\'p_password\').value
    }).then(function (r) {
      if (r.status !== \'success\') { EM.toast(r.message || \'Save failed\', \'err\'); return; }
      EM.toast(\'Profile updated\', \'ok\');
      $(\'profileCard\').classList.add(\'hide\');
      if (r.new_user && r.new_user !== EM.user) { window.location.href = \'index.php\'; } else { refresh(); }
    });
  });

  refresh();
})();
',
    'modules/users/index.php' => '<?php
/**
 * Emerald Central Hub V11 — Users fragment.
 * Rendered inside the dashboard shell; logic in modules/users/app.js.
 */
if (!defined(\'EMERALD_SHELL\')) {
    header(\'Location: index.php?p=users\');
    exit;
}
?>
<div class="card">
  <div class="card-header">
    <h2>Identities</h2>
    <?php if ($__role === \'owner\'): ?><button class="btn btn-primary sm" id="btnAddUser">+ Add user</button><?php endif; ?>
  </div>
  <div class="table-wrap">
    <table class="tbl" id="userTable">
      <thead><tr>
        <th>User</th><th>Role</th><th>Last active</th><th class="right">Actions</th>
      </tr></thead>
      <tbody><tr><td colspan="4" class="empty">Loading…</td></tr></tbody>
    </table>
  </div>
</div>

<div class="card hide" id="userFormCard">
  <h2>Add user</h2>
  <div class="grid grid-2">
    <div class="field"><label>Username</label><input type="text" id="u_username" autocomplete="off"></div>
    <div class="field"><label>Role</label><select id="u_role">
      <option value="user">user</option><option value="admin">admin</option><option value="guest">guest</option><option value="owner">owner</option>
    </select></div>
  </div>
  <div class="field"><label>Password</label><input type="password" id="u_password" autocomplete="new-password"></div>
  <div class="row">
    <button class="btn btn-primary" id="btnSaveUser">Create user</button>
    <button class="btn ghost" id="btnCancelUser">Cancel</button>
  </div>
</div>

<div class="card hide" id="profileCard">
  <h2>My profile</h2>
  <div class="grid grid-2">
    <div class="field"><label>Username</label><input type="text" id="p_username"></div>
    <div class="field"><label>Avatar URL (optional)</label><input type="text" id="p_avatar" placeholder="https://…/avatar.png"></div>
  </div>
  <div class="field"><label>New password (leave blank to keep)</label><input type="password" id="p_password" autocomplete="new-password"></div>
  <div class="row">
    <button class="btn btn-primary" id="btnSaveProfile">Save profile</button>
  </div>
</div>
<script src="modules/users/app.js"></script>
',
    'notepad/index.php' => '<?php
/**
 * Emerald Central Hub V11 — Public Notepad (V10 UI preserved).
 * TinyMCE + Tailwind + SweetAlert2. Data dir: modules/notepad/data/
 * (migrated from public_notepad/ by update.php; originals kept).
 */
/* session must start via auth_start() so the V11 session name (SESSION_NAME)
   is used — starting it here would use PHPSESSID and break SSO */
require_once dirname(__DIR__) . \'/core/config.php\';
require_once CORE_DIR . \'/auth.php\';
auth_start();
$logged_sso_user = auth_user();
if ($logged_sso_user === null) { $logged_sso_user = \'\'; }

if (!defined(\'DIR_NOTEPAD\')) {
    $np = MODULES_DIR . \'/notepad/data\';
    if (!is_dir($np) && is_dir(APP_ROOT . \'/public_notepad\')) {
        $np = APP_ROOT . \'/public_notepad\';
    }
    if (!is_dir($np)) { @mkdir($np, 0755, true); }
    define(\'DIR_NOTEPAD\', $np);
}
if (!function_exists(\'sanitize\')) {
    function sanitize($string) { return sec_str($string); }
}

/* path safety: no traversal in user/file names */
function np_safe_name($s) {
    $s = str_replace(array(\'\\\\\', \'/\', "\\0"), \'\', (string)$s);
    $s = basename($s);
    return $s;
}

if (isset($_GET[\'api\'])) {
    header(\'Content-Type: application/json; charset=utf-8\');
    $users_data = db_load(\'users\');
    $api = (string)$_GET[\'api\'];

    if ($api === \'list_users\') {
        $output = array();
        foreach ($users_data as $uname => $udata) {
            $output[] = array(
                \'username\' => $uname,
                \'avatar\' => !empty($udata[\'avatar\']) ? $udata[\'avatar\'] : (\'https://ui-avatars.com/api/?name=\' . urlencode($uname) . \'&background=0ea5e9&color=fff&rounded=true&bold=true\'),
                \'last_active\' => isset($udata[\'last_active\']) ? $udata[\'last_active\'] : 0,
            );
        }
        echo json_encode($output);
        exit;
    }

    if ($api === \'verify_access\') {
        $user = sanitize(isset($_POST[\'user\']) ? $_POST[\'user\'] : \'\');
        $pass = isset($_POST[\'password\']) ? $_POST[\'password\'] : \'\';
        if ($user !== \'\' && verifyUserPassword($user, $pass)) {
            echo json_encode(array(\'status\' => \'success\'));
        } else {
            echo json_encode(array(\'status\' => \'error\', \'message\' => \'Invalid password.\'));
        }
        exit;
    }

    if ($api === \'list_notes\') {
        $user = np_safe_name(sanitize(isset($_POST[\'user\']) ? $_POST[\'user\'] : \'\'));
        if ($user === \'\' || $user === \'.\' || $user === \'..\') {
            echo json_encode(array(\'status\' => \'error\', \'message\' => \'Invalid user.\'));
            exit;
        }
        $user_dir = DIR_NOTEPAD . \'/\' . $user;
        if (!is_dir($user_dir)) { @mkdir($user_dir, 0755, true); }
        clearstatcache();
        $notes = array();
        foreach (scandir($user_dir) as $file) {
            if ($file !== \'.\' && $file !== \'..\' && is_file($user_dir . \'/\' . $file)) { $notes[] = $file; }
        }
        echo json_encode(array(\'status\' => \'success\', \'notes\' => array_values($notes)));
        exit;
    }

    if ($api === \'load_note\') {
        /* V10: load_note does not require password (workspace already unlocked client-side) */
        $user = np_safe_name(sanitize(isset($_POST[\'user\']) ? $_POST[\'user\'] : \'\'));
        $file = np_safe_name(sanitize(isset($_POST[\'file\']) ? $_POST[\'file\'] : \'\'));
        $content = \'\';
        if ($user !== \'\' && $file !== \'\' && $user !== \'.\' && $file !== \'.\') {
            $path = DIR_NOTEPAD . \'/\' . $user . \'/\' . $file;
            $base = realpath(DIR_NOTEPAD);
            $rp = (is_file($path) ? realpath($path) : false);
            if ($base && $rp && strpos($rp, $base) === 0 && is_file($rp)) {
                $content = (string)file_get_contents($rp);
            } else {
                /* fallback: original public_notepad path if migration copy missing */
                $alt = APP_ROOT . \'/public_notepad/\' . $user . \'/\' . $file;
                if (is_file($alt)) { $content = (string)file_get_contents($alt); }
            }
        }
        echo json_encode(array(\'status\' => \'success\', \'content\' => $content));
        exit;
    }

    if ($api === \'save_note\') {
        $user = np_safe_name(sanitize(isset($_POST[\'user\']) ? $_POST[\'user\'] : \'\'));
        $file = np_safe_name(sanitize(isset($_POST[\'file\']) ? $_POST[\'file\'] : \'\'));
        $content = isset($_POST[\'content\']) ? $_POST[\'content\'] : \'\';
        $pass = isset($_POST[\'password\']) ? $_POST[\'password\'] : \'\';
        $is_sso = ($logged_sso_user !== \'\' && $logged_sso_user === $user);
        if (!$is_sso && !verifyUserPassword($user, $pass)) {
            echo json_encode(array(\'status\' => \'error\'));
            exit;
        }
        $user_dir = DIR_NOTEPAD . \'/\' . $user;
        if (!is_dir($user_dir)) { @mkdir($user_dir, 0755, true); }
        @file_put_contents($user_dir . \'/\' . $file, $content);
        echo json_encode(array(\'status\' => \'success\'));
        exit;
    }

    if ($api === \'create_note\') {
        $user = np_safe_name(sanitize(isset($_POST[\'user\']) ? $_POST[\'user\'] : \'\'));
        $file = np_safe_name(sanitize(isset($_POST[\'file\']) ? $_POST[\'file\'] : \'\'));
        $pass = isset($_POST[\'password\']) ? $_POST[\'password\'] : \'\';
        $is_sso = ($logged_sso_user !== \'\' && $logged_sso_user === $user);
        if (!$is_sso && !verifyUserPassword($user, $pass)) {
            echo json_encode(array(\'status\' => \'error\'));
            exit;
        }
        if (strpos($file, \'.txt\') === false) { $file .= \'.txt\'; }
        $user_dir = DIR_NOTEPAD . \'/\' . $user;
        if (!is_dir($user_dir)) { @mkdir($user_dir, 0755, true); }
        if (!file_exists($user_dir . \'/\' . $file)) { @file_put_contents($user_dir . \'/\' . $file, \'\'); }
        echo json_encode(array(\'status\' => \'success\'));
        exit;
    }

    if ($api === \'delete_note\') {
        $user = np_safe_name(sanitize(isset($_POST[\'user\']) ? $_POST[\'user\'] : \'\'));
        $file = np_safe_name(sanitize(isset($_POST[\'file\']) ? $_POST[\'file\'] : \'\'));
        $pass = isset($_POST[\'password\']) ? $_POST[\'password\'] : \'\';
        $is_sso = ($logged_sso_user !== \'\' && $logged_sso_user === $user);
        if (!$is_sso && !verifyUserPassword($user, $pass)) {
            echo json_encode(array(\'status\' => \'error\'));
            exit;
        }
        $path = DIR_NOTEPAD . \'/\' . $user . \'/\' . $file;
        if (is_file($path)) { @unlink($path); }
        echo json_encode(array(\'status\' => \'success\'));
        exit;
    }

    echo json_encode(array(\'status\' => \'error\', \'message\' => \'Unknown action.\'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMERALD - Public Notepad</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js"></script>

    <style>
        body { background-color: #f8fafc; color: #0f172a; font-family: \'Plus Jakarta Sans\', sans-serif; overflow: hidden; transition: filter 0.5s ease; }
        .glass-panel { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(24px); border-bottom: 1px solid rgba(226, 232, 240, 0.8); }
        .user-card { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer; border: 1px solid rgba(226, 232, 240, 0.8); background: #ffffff; box-shadow: 0 4px 20px -2px rgba(0,0,0,0.03); }
        .user-card:hover { transform: translateY(-8px) scale(1.02); border-color: #10b981; box-shadow: 0 20px 40px -5px rgba(16,185,129,0.15); }
        .tab-btn { transition: all 0.3s ease; }
        .tab-btn.active { background: linear-gradient(90deg, rgba(16,185,129,0.1) 0%, rgba(255,255,255,0) 100%); color: #059669; border-left: 3px solid #10b981; }
        
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        .btn-animated { position: relative; overflow: hidden; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .btn-animated:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
        .btn-animated:active { transform: translateY(1px); box-shadow: 0 5px 10px -5px rgba(0,0,0,0.1); }
        
        #authModal { visibility: hidden; opacity: 0; pointer-events: none; transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); z-index: 99999; }
        #authModal.flex { visibility: visible; opacity: 1; pointer-events: auto; }
        #authModalContent { transform: scale(0.95) translateY(20px); transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        #authModal.flex #authModalContent { transform: scale(1) translateY(0); }
        
        .modal { opacity: 0; pointer-events: none; visibility: hidden; transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); z-index: 99999; backdrop-filter: blur(8px); }
        .modal.active { opacity: 1; pointer-events: auto; visibility: visible; }
        .modal-content { transform: scale(0.95) translateY(20px); transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        .modal.active .modal-content { transform: scale(1) translateY(0); }
        
        .tox-tinymce { border: none !important; border-radius: 0 !important; }
        .tox-statusbar__branding { display: none !important; }

        .bg-mesh { background-image: radial-gradient(at 40% 20%, hsla(160,100%,74%,0.15) 0px, transparent 50%), radial-gradient(at 80% 0%, hsla(189,100%,56%,0.15) 0px, transparent 50%), radial-gradient(at 0% 50%, hsla(340,100%,76%,0.15) 0px, transparent 50%); }
    </style>
</head>
<body class="flex flex-col h-screen relative selection:bg-emerald-500 selection:text-white bg-mesh">

    <header class="h-20 glass-panel flex items-center justify-between px-10 z-20 shadow-sm">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-[1rem] bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center shadow-[0_8px_16px_-4px_rgba(16,185,129,0.3)] border border-emerald-300/30">
                <i class="fa-solid fa-book-open text-white text-xl"></i>
            </div>
            <div>
                <h1 class="font-extrabold text-2xl tracking-tight text-slate-800 leading-tight">Public Notepad</h1>
                <p class="text-[10px] text-emerald-600 font-mono tracking-widest uppercase font-bold">Secure Global Workspace</p>
            </div>
        </div>
        <div class="flex gap-6 items-center">
            <div class="hidden md:flex items-center gap-3 text-slate-500 text-xs font-medium">
                <span>Shortcuts:</span>
                <kbd class="bg-slate-100 border border-slate-200 px-2 py-1 rounded-md font-mono text-slate-700 shadow-sm">Ctrl+F</kbd> Browser Find
                <kbd class="bg-slate-100 border border-slate-200 px-2 py-1 rounded-md font-mono text-slate-700 shadow-sm">Ctrl+H</kbd> Replace
                <kbd class="bg-slate-100 border border-slate-200 px-2 py-1 rounded-md font-mono text-slate-700 shadow-sm">Ctrl+S</kbd> Save File
            </div>
            <button onclick="window.location.href=\'../index.php\'" class="px-6 py-2.5 rounded-[1rem] bg-white border border-slate-200 text-slate-700 hover:text-emerald-600 transition-all font-bold text-sm shadow-sm hover:border-emerald-200 hover:bg-emerald-50 btn-animated flex items-center gap-2">
                <i class="fa-solid fa-arrow-right-to-bracket rotate-180"></i> Hub
            </button>
        </div>
    </header>

    <div class="flex-1 overflow-y-auto p-12 relative z-10" id="mainView">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-extrabold text-slate-800 mb-3">Select Identity Workspace</h2>
                <p class="text-slate-500 font-medium">Authentication required to view and manage physical txt files.</p>
            </div>
            <div class="flex flex-wrap justify-center gap-8" id="userContainerList"></div>
        </div>
    </div>

    <div id="authModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-md items-center justify-center">
        <div class="bg-white rounded-[2rem] w-full max-w-sm p-10 text-center shadow-2xl border border-slate-100 relative overflow-hidden" id="authModalContent">
            <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-emerald-400 to-teal-500"></div>
            <div class="w-20 h-20 bg-emerald-50 rounded-[1.2rem] border border-emerald-100 flex items-center justify-center mx-auto mb-6 shadow-inner">
                <i class="fa-solid fa-lock text-3xl text-emerald-500"></i>
            </div>
            <h3 class="font-extrabold text-2xl text-slate-800 mb-2">Workspace Auth</h3>
            <p class="text-sm text-slate-500 mb-8 font-medium">Unlocking workspace for <b class="text-emerald-600" id="authModalUser">User</b>.</p>
            
            <div class="relative group mb-8">
                <i class="fa-solid fa-key absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-emerald-500 transition-colors"></i>
                <input type="password" id="customAuthPass" class="w-full bg-slate-50 text-slate-800 font-bold text-lg rounded-[1.2rem] py-4 pr-4 pl-12 border border-slate-200 focus:outline-none focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 transition-all shadow-inner placeholder-slate-400 tracking-widest" placeholder="Passphrase">
            </div>
            
            <div class="flex justify-center gap-3">
                <button onclick="closeNotepadAuth()" class="flex-1 bg-white hover:bg-slate-50 text-slate-600 font-bold py-3.5 rounded-[1.2rem] border border-slate-200 transition-all btn-animated">Cancel</button>
                <button onclick="submitNotepadAuth()" class="flex-1 bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-3.5 rounded-[1.2rem] shadow-[0_8px_16px_-4px_rgba(16,185,129,0.3)] transition-all btn-animated flex items-center justify-center gap-2">
                    Unlock <i class="fa-solid fa-unlock-keyhole"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="flex-1 hidden overflow-hidden z-10" id="editorView">
        <div class="w-80 bg-slate-50 border-r border-slate-200 flex flex-col shadow-[4px_0_24px_rgba(0,0,0,0.02)] z-20">
            <div class="p-6 border-b border-slate-200 bg-white">
                <div class="flex items-center gap-4 mb-6 p-2 rounded-2xl border border-slate-100 bg-slate-50 shadow-inner">
                    <img id="activeUserAvatar" src="" class="w-12 h-12 rounded-xl border-2 border-white shadow-sm object-cover bg-white">
                    <div class="overflow-hidden">
                        <p class="text-[10px] text-emerald-500 font-mono tracking-widest uppercase font-bold mb-0.5">Active Node</p>
                        <h3 class="font-extrabold text-slate-800 text-lg truncate tracking-tight" id="currentUserDisplay">User</h3>
                    </div>
                </div>
                <div class="relative group mb-5">
                    <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-emerald-500 transition-colors"></i>
                    <input type="text" id="searchNoteFiles" placeholder="Filter txt files..." onkeyup="filterNotes()" class="w-full bg-white border border-slate-200 text-slate-700 rounded-xl py-3 pl-10 pr-4 text-sm font-medium outline-none focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 transition-all shadow-sm">
                </div>
                <button onclick="promptCreateNote()" class="w-full py-3 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl font-bold text-sm transition-all shadow-[0_8px_16px_-4px_rgba(16,185,129,0.3)] btn-animated flex items-center justify-center gap-2">
                    <i class="fa-solid fa-file-circle-plus"></i> New File (.txt)
                </button>
            </div>
            <div class="flex-1 overflow-y-auto p-4 space-y-1.5 custom-scrollbar bg-slate-50" id="notesList"></div>
            <div class="p-6 border-t border-slate-200 bg-white">
                <button onclick="backToList()" class="w-full py-3.5 border border-slate-200 rounded-xl text-slate-600 hover:text-red-500 hover:bg-red-50 hover:border-red-200 transition-all font-bold text-sm bg-white shadow-sm btn-animated flex items-center justify-center gap-2">
                    <i class="fa-solid fa-door-open"></i> Close Workspace
                </button>
            </div>
        </div>
        <div class="flex-1 flex flex-col relative bg-white">
            <div class="h-[72px] bg-white border-b border-slate-200 flex items-center px-8 justify-between z-20">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-emerald-50 border border-emerald-100 flex items-center justify-center">
                        <i class="fa-solid fa-file-lines text-emerald-500"></i>
                    </div>
                    <div class="font-mono text-slate-700 font-bold tracking-tight text-lg" id="currentFileDisplay">No file selected</div>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="deleteNotepadFile()" class="px-5 py-2.5 bg-white text-red-500 rounded-xl hover:bg-red-50 transition-all font-bold text-sm btn-animated border border-slate-200 hover:border-red-200 flex items-center gap-2 shadow-sm">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                    <button onclick="tinymce.activeEditor.execCommand(\'SearchReplace\');" class="px-5 py-2.5 bg-white text-blue-500 rounded-xl hover:bg-blue-50 transition-all font-bold text-sm btn-animated border border-slate-200 hover:border-blue-200 flex items-center gap-2 shadow-sm">
                        <i class="fa-solid fa-magnifying-glass-chart"></i> Find/Replace
                    </button>
                    <button onclick="saveNotepad()" class="px-6 py-2.5 bg-emerald-500 text-white rounded-xl hover:bg-emerald-600 transition-all font-bold text-sm shadow-[0_8px_16px_-4px_rgba(16,185,129,0.3)] btn-animated flex items-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i> Save Data
                    </button>
                </div>
            </div>
            <div class="flex-1 relative w-full h-full flex flex-col overflow-hidden bg-white">
                <textarea id="rawEditor" class="w-full h-full"></textarea>
            </div>
        </div>
    </div>

    <div class="modal fixed inset-0 flex items-center justify-center p-4" id="modalFindReplace">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-md" onclick="closeModal(\'modalFindReplace\')"></div>
        <div class="modal-content bg-white rounded-[2rem] shadow-2xl w-full max-w-sm relative z-10 overflow-hidden p-8 border border-slate-100 text-center">
            <div class="w-16 h-16 bg-blue-50 rounded-[1rem] border border-blue-100 flex items-center justify-center mx-auto mb-5 shadow-inner">
                <i class="fa-solid fa-magnifying-glass-chart text-2xl text-blue-500"></i>
            </div>
            <h3 class="font-extrabold text-2xl text-slate-800 mb-6">Find & Replace</h3>
            <div class="space-y-4">
                <input type="text" id="findText" placeholder="Search text..." class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl p-4 outline-none font-medium transition-all focus:border-blue-400 focus:ring-4 focus:ring-blue-500/10 shadow-inner">
                <input type="text" id="replaceText" placeholder="Replace with..." class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl p-4 outline-none font-medium transition-all focus:border-blue-400 focus:ring-4 focus:ring-blue-500/10 shadow-inner">
            </div>
            <div class="mt-8 flex gap-3">
                <button class="flex-1 py-3.5 bg-white text-slate-600 border border-slate-200 rounded-xl font-bold hover:bg-slate-50 transition-all btn-animated" onclick="executeFindReplace(false)">Replace</button>
                <button class="flex-1 py-3.5 bg-blue-500 text-white rounded-xl font-bold hover:bg-blue-600 transition-all btn-animated shadow-[0_8px_16px_-4px_rgba(59,130,246,0.3)]" onclick="executeFindReplace(true)">Replace All</button>
            </div>
        </div>
    </div>

<script>
    let activeUser = \'\'; let activeFile = \'\'; let activePass = \'\';
    let usersData = []; 
    const Toast = Swal.mixin({ toast: true, position: \'bottom-end\', showConfirmButton: false, timer: 3000, background: \'#ffffff\', color: \'#0f172a\', customClass: { popup: \'border border-slate-200 shadow-[0_10px_30px_-10px_rgba(0,0,0,0.1)] rounded-[1rem]\' }});

    document.addEventListener(\'DOMContentLoaded\', async () => {
        tinymce.init({
            selector: \'#rawEditor\',
            plugins: \'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table wordcount paste\',
            toolbar: \'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | forecolor backcolor | table image link | removeformat | fullscreen\',
            menubar: \'file edit view insert format tools table\',
            height: \'100%\',
            resize: false,
            skin: \'oxide\',
            forced_root_block: false,
            force_br_newlines: true,
            force_p_newlines: false,
            content_style: \'body { font-family: "Plus Jakarta Sans", sans-serif; font-size: 14px; line-height: 1.6; color: #0f172a; word-wrap: break-word; overflow-wrap: break-word; margin: 16px; }\',
            setup: function (editor) {
                editor.on(\'init\', function () {
                    editor.mode.set("readonly");
                    
                    // Injeksi presisi Gutter Angka Baris di dalam area teks, HANYA di Bawah Toolbar
                    const editArea = editor.getContainer().querySelector(\'.tox-edit-area\');
                    if(editArea) {
                        const gutter = document.createElement(\'div\');
                        gutter.id = \'npLineNumbers\';
                        gutter.className = \'w-12 bg-slate-50 border-r border-slate-200 text-right pr-2 py-[16px] font-mono text-[14px] leading-[1.6] text-slate-400 select-none overflow-hidden whitespace-pre shrink-0\';
                        gutter.textContent = \'1\';
                        
                        editArea.style.display = \'flex\';
                        editArea.insertBefore(gutter, editArea.firstChild);
                    }
                });

                editor.on(\'Change KeyUp SetContent paste\', function () {
                    const gutter = document.getElementById(\'npLineNumbers\');
                    if (gutter) {
                        const text = editor.getContent({format: \'text\'});
                        const lines = text.split(\'\\n\').length;
                        let numHtml = \'\';
                        for (let i = 1; i <= lines; i++) { numHtml += i + \'\\n\'; }
                        gutter.textContent = numHtml;
                    }
                });
                editor.on(\'Scroll\', function () {
                    const gutter = document.getElementById(\'npLineNumbers\');
                    const scrollTop = editor.getDoc().documentElement.scrollTop || editor.getDoc().body.scrollTop;
                    if (gutter) gutter.scrollTop = scrollTop;
                });
            }
        });

        const ssoUser = \'<?= $logged_sso_user ?>\';
        usersData = await fetch(\'?api=list_users\').then(r => r.json());
        const grid = document.getElementById(\'userContainerList\');
        const now = Math.floor(Date.now() / 1000);
        
        if (ssoUser) {
            activePass = \'sso_bypass_auth\';
            await openUserWorkspace(ssoUser);
            const savedFile = localStorage.getItem(\'emerald_np_file\');
            if(savedFile) await loadFile(savedFile);
        } else {
        
        usersData.forEach(u => {
            const isOnline = (now - (u.last_active || 0)) < 30; 
            const statusDot = isOnline ? \'<div class="w-5 h-5 rounded-full bg-emerald-500 shadow-[0_0_15px_rgba(16,185,129,0.8)] absolute bottom-0 right-0 border-4 border-white"></div>\' : \'<div class="w-5 h-5 rounded-full bg-slate-300 absolute bottom-0 right-0 border-4 border-white"></div>\';

            grid.innerHTML += `
                <div class="user-card w-64 h-64 rounded-[2rem] flex flex-col items-center justify-center p-6 relative group" onclick="openAuthModal(\'${u.username}\')">
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity rounded-[2rem]"></div>
                    <div class="relative mb-6 z-10">
                        <img src="${u.avatar}" class="w-24 h-24 rounded-[1.5rem] border-4 border-white object-cover shadow-lg group-hover:scale-105 transition-transform">
                        ${statusDot}
                    </div>
                    <span class="font-extrabold text-slate-800 text-xl tracking-tight z-10">${u.username}</span>
                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-2 z-10 group-hover:text-emerald-500 transition-colors">Access Terminal</span>
                </div>
            `;
        });
        
                }
        
        // Custom keybinds
        document.addEventListener(\'keydown\', e => { 
            if (!document.getElementById(\'editorView\').classList.contains(\'hidden\')) {
                // Ctrl+S untuk Save
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === \'s\') { 
                    e.preventDefault(); 
                    if(activeFile) saveNotepad(); 
                }
                // Ctrl+H untuk custom modal Replace
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === \'h\') { 
                    e.preventDefault(); 
                    if(activeFile) openFindReplaceModal(); 
                }
                // Ctrl+F TIDAK di-preventDefault agar native search Google Chrome terbuka
            }
        });

        const savedUser = localStorage.getItem(\'emerald_np_user\');
        const savedPass = localStorage.getItem(\'emerald_np_pass\');
        if(savedUser && savedPass) {
            const fd = new FormData(); fd.append(\'user\', savedUser); fd.append(\'password\', savedPass);
            const res = await fetch(\'?api=verify_access\', { method: \'POST\', body: fd }).then(r=>r.json());
            if(res.status === \'success\') {
                activePass = savedPass;
                await openUserWorkspace(savedUser);
                const savedFile = localStorage.getItem(\'emerald_np_file\');
                if(savedFile) await loadFile(savedFile);
            } else {
                localStorage.removeItem(\'emerald_np_user\'); localStorage.removeItem(\'emerald_np_pass\');
            }
        }
    });

    function openAuthModal(user) {
        document.getElementById(\'authModalUser\').innerText = user;
        document.getElementById(\'customAuthPass\').value = \'\';
        document.getElementById(\'authModal\').classList.add(\'flex\');
        setTimeout(() => { document.getElementById(\'customAuthPass\').focus(); }, 100);
    }

    function closeNotepadAuth() { document.getElementById(\'authModal\').classList.remove(\'flex\'); }

    async function submitNotepadAuth() {
        const user = document.getElementById(\'authModalUser\').innerText;
        const pass = document.getElementById(\'customAuthPass\').value;
        if(!pass) return Toast.fire({icon: \'error\', title: \'Passphrase required\'});

        const fd = new FormData(); fd.append(\'user\', user); fd.append(\'password\', pass);
        const res = await fetch(\'?api=verify_access\', { method: \'POST\', body: fd }).then(r=>r.json());
        if(res.status === \'success\') {
            closeNotepadAuth();
            activePass = pass;
            localStorage.setItem(\'emerald_np_user\', user); 
            localStorage.setItem(\'emerald_np_pass\', pass);
            openUserWorkspace(user);
        } else { Toast.fire({icon: \'error\', title: res.message}); }
    }

    // Fungsi Modal Find & Replace
    function openFindReplaceModal() { document.getElementById(\'modalFindReplace\').classList.add(\'active\'); }
    function closeModal(id) { document.getElementById(id).classList.remove(\'active\'); }

    // Eksekusi Find & Replace (aman untuk konten HTML editor)
    function executeFindReplace(replaceAll) {
        const findText = document.getElementById(\'findText\').value;
        const replaceText = document.getElementById(\'replaceText\').value;
        if(!findText) { Toast.fire({icon: \'error\', title: \'Search text required\'}); return; }
        if(!tinymce.activeEditor) return;
        const editor = tinymce.activeEditor;

        if(replaceAll) {
            // Ganti di semua text node (tidak merusak tag HTML)
            const walker = document.createTreeWalker(editor.getBody(), NodeFilter.SHOW_TEXT, null);
            const nodes = [];
            while(walker.nextNode()) nodes.push(walker.currentNode);
            let count = 0;
            nodes.forEach(n => {
                if(n.nodeValue.includes(findText)) {
                    const parts = n.nodeValue.split(findText);
                    n.nodeValue = parts.join(replaceText);
                    count += parts.length - 1;
                }
            });
            editor.fire(\'change\');
            Toast.fire({icon: \'success\', title: count ? count + \' occurrence(s) replaced\' : \'No matches\'});
        } else {
            // Ganti seleksi saat ini jika cocok, jika tidak cari kemunculan berikutnya
            const selected = editor.selection.getContent({format: \'text\'});
            if(selected === findText) {
                editor.selection.setContent(replaceText);
                editor.fire(\'change\');
                Toast.fire({icon: \'success\', title: \'Replaced\'});
            } else {
                try {
                    editor.execCommand(\'mceSearchReplace\', false, { text: findText, replace: replaceText });
                } catch(err) {
                    Toast.fire({icon: \'error\', title: \'No match\'});
                }
            }
        }
        closeModal(\'modalFindReplace\');
    }

    async function openUserWorkspace(user) {
        activeUser = user;
        document.getElementById(\'currentUserDisplay\').innerText = user;
        const udata = usersData.find(x => x.username === user);
        document.getElementById(\'activeUserAvatar\').src = udata ? udata.avatar : \'\';
        
        document.getElementById(\'mainView\').classList.add(\'hidden\');
        document.getElementById(\'editorView\').classList.remove(\'hidden\'); document.getElementById(\'editorView\').classList.add(\'flex\');
        
        await refreshNotesList();
    }

    async function refreshNotesList() {
        const fd = new FormData(); fd.append(\'user\', activeUser);
        const res = await fetch(\'?api=list_notes\', { method: \'POST\', body: fd }).then(r=>r.json());
        const list = document.getElementById(\'notesList\'); list.innerHTML = \'\';
        if(res.notes && res.notes.length === 0) {
            tinymce.activeEditor.setContent(\'\'); tinymce.activeEditor.mode.set("readonly"); document.getElementById(\'currentFileDisplay\').innerText = \'No physical file active\'; activeFile = \'\'; localStorage.removeItem(\'emerald_np_file\');
        } else if (res.notes) {
            res.notes.forEach(f => {
                const isActive = (f === activeFile) ? \'active bg-white border border-emerald-100 shadow-sm\' : \'text-slate-600 hover:bg-slate-100 hover:text-slate-900 border-l-transparent border border-transparent\';
                list.innerHTML += `<button onclick="loadFile(\'${f}\')" class="tab-btn w-full text-left px-5 py-3.5 rounded-xl font-medium text-sm truncate flex items-center group ${isActive}"><i class="fa-solid fa-file-lines mr-3 ${f === activeFile ? \'text-emerald-500\' : \'text-slate-400 group-hover:text-slate-500\'}"></i>${f}</button>`;
            });
            if(!activeFile || !res.notes.includes(activeFile)) loadFile(res.notes[0]);
        }
    }

    function filterNotes() {
        const q = document.getElementById(\'searchNoteFiles\').value.toLowerCase();
        document.querySelectorAll(\'.tab-btn\').forEach(btn => {
            if(btn.innerText.toLowerCase().includes(q)) btn.style.display = \'flex\';
            else btn.style.display = \'none\';
        });
    }

    async function loadFile(file) {
        activeFile = file; document.getElementById(\'currentFileDisplay\').innerText = file;
        localStorage.setItem(\'emerald_np_file\', file);
        const fd = new FormData(); fd.append(\'user\', activeUser); fd.append(\'file\', file);
        const res = await fetch(\'?api=load_note\', { method: \'POST\', body: fd }).then(r=>r.json());
        
        tinymce.activeEditor.mode.set("design");
        
        let plainContent = res.content;
        
        // Migrasi Data: Konversi teks lama menjadi struktur <div> untuk mencegah spasi ganda saat disalin
        if(!/<[a-z][\\s\\S]*>/i.test(plainContent) && plainContent.trim() !== \'\') {
            plainContent = plainContent.split(\'\\n\').map(line => `<div>${line || \'<br>\'}</div>`).join(\'\');
        } else {
            // Bersihkan tag <p> lama menjadi <div>
            plainContent = plainContent.replace(/<p\\b[^>]*>/gi, match => match.replace(\'<p\', \'<div\')).replace(/<\\/p>/gi, \'</div>\');
        }
        
        tinymce.activeEditor.setContent(plainContent);
        setTimeout(() => {
            const gutter = document.getElementById(\'npLineNumbers\');
            if (gutter && tinymce.activeEditor) {
                const text = tinymce.activeEditor.getContent({format: \'text\'});
                const lines = text.split(\'\\n\').length;
                let numHtml = \'\';
                for (let i = 1; i <= lines; i++) { numHtml += i + \'\\n\'; }
                gutter.textContent = numHtml;
                tinymce.activeEditor.refresh();
            }
        }, 150);
        
        document.querySelectorAll(\'.tab-btn\').forEach(btn => {
            btn.className = \'tab-btn w-full text-left px-5 py-3.5 rounded-xl font-medium text-sm truncate flex items-center group text-slate-600 hover:bg-slate-100 hover:text-slate-900 border-l-transparent border border-transparent\';
            btn.querySelector(\'i\').className = \'fa-solid fa-file-lines mr-3 text-slate-400 group-hover:text-slate-500\';
            if(btn.innerText.trim() === file) {
                btn.className = \'tab-btn w-full text-left px-5 py-3.5 rounded-xl font-medium text-sm truncate flex items-center group active bg-white border border-emerald-100 shadow-sm\';
                btn.querySelector(\'i\').className = \'fa-solid fa-file-lines mr-3 text-emerald-500\';
            }
        });
    }

    async function promptCreateNote() {
        const { value: fileName } = await Swal.fire({ 
            title: \'Create Physical File\', input: \'text\', background: \'#ffffff\', color: \'#0f172a\', 
            inputPlaceholder: \'filename.txt\',
            customClass: {popup: \'border border-slate-200 rounded-[2rem] shadow-2xl p-6\', title: \'font-extrabold text-2xl mb-4\', input:\'bg-slate-50 border border-slate-200 text-slate-800 rounded-xl outline-none p-4 w-[85%] mx-auto font-mono focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 shadow-inner\', confirmButton:\'bg-emerald-500 text-white rounded-xl px-8 py-3.5 ml-2 font-bold hover:bg-emerald-600 shadow-lg btn-animated\', cancelButton:\'bg-white border border-slate-200 text-slate-600 rounded-xl px-8 py-3.5 mr-2 hover:bg-slate-50 font-bold btn-animated\'},
            buttonsStyling: false, showCancelButton: true
        });
        if(fileName) {
            const fd = new FormData(); fd.append(\'user\', activeUser); fd.append(\'file\', fileName); fd.append(\'password\', activePass);
            await fetch(\'?api=create_note\', { method: \'POST\', body: fd });
            activeFile = fileName.includes(\'.txt\') ? fileName : fileName + \'.txt\';
            await refreshNotesList();
            await loadFile(activeFile);
            Toast.fire({ icon: \'success\', title: \'Physical file initialized\' });
        }
    }

    async function deleteNotepadFile() {
        if(!activeFile) return;
        Swal.fire({
            title: \'Purge File?\', html: `<p class="text-slate-500 font-medium mt-2">Permanently erase physical file <b>${activeFile}</b>?</p>`, showCancelButton: true, confirmButtonText: \'Purge File\',
            background: \'#ffffff\', color: \'#0f172a\',
            customClass: {popup: \'border border-slate-200 rounded-[2rem] shadow-2xl p-6\', title: \'font-extrabold text-2xl\', confirmButton:\'bg-red-500 text-white rounded-xl px-8 py-3.5 ml-2 font-bold hover:bg-red-600 shadow-lg btn-animated\', cancelButton:\'bg-white border border-slate-200 text-slate-600 rounded-xl px-8 py-3.5 mr-2 hover:bg-slate-50 font-bold btn-animated\'},
            buttonsStyling: false
        }).then(async (result) => {
            if(result.isConfirmed) {
                const fd = new FormData(); fd.append(\'user\', activeUser); fd.append(\'file\', activeFile); fd.append(\'password\', activePass);
                await fetch(\'?api=delete_note\', { method: \'POST\', body: fd });
                activeFile = \'\'; localStorage.removeItem(\'emerald_np_file\'); await refreshNotesList(); Toast.fire({ icon: \'success\', title: \'File erased\' });
            }
        });
    }

    function backToList() {
        document.getElementById(\'editorView\').classList.remove(\'flex\'); document.getElementById(\'editorView\').classList.add(\'hidden\');
        document.getElementById(\'mainView\').classList.remove(\'hidden\'); activeUser = \'\'; activeFile = \'\'; activePass = \'\';
        localStorage.removeItem(\'emerald_np_user\'); localStorage.removeItem(\'emerald_np_pass\'); localStorage.removeItem(\'emerald_np_file\');
    }

    async function saveNotepad() {
        if(!activeFile) return;
        const rawContent = tinymce.activeEditor.getContent();
        const fd = new FormData(); fd.append(\'user\', activeUser); fd.append(\'file\', activeFile); fd.append(\'password\', activePass); fd.append(\'content\', rawContent);
        await fetch(\'?api=save_note\', { method: \'POST\', body: fd }); Toast.fire({ icon: \'success\', title: \'Data physically saved\' });
    }
</script>
</body>
</html>',
    'view.php' => '<?php
define(\'ALLOW_PUBLIC_VIEW\', true);
require_once __DIR__ . \'/core/config.php\';

$requested_file = isset($_GET[\'f\']) ? $_GET[\'f\'] : null;

if (!$requested_file) {
    http_response_code(404);
    die("Asset not specified.");
}

$requested_file = str_replace(["\\0", \'../\', \'..\\\\\'], \'\', $requested_file);
$file_basename = basename($requested_file);
$file_dirname = dirname($requested_file);

function findAssetFile($base_dir, $dir_name, $base_name) {
    if ($dir_name && $dir_name !== \'.\') {
        $target_dir = rtrim($base_dir, \'/\\\\\') . \'/\' . ltrim($dir_name, \'/\\\\\');
        if (is_dir($target_dir)) {
            $iterator = new DirectoryIterator($target_dir);
            foreach ($iterator as $fileinfo) {
                if ($fileinfo->isFile() && pathinfo($fileinfo->getFilename(), PATHINFO_FILENAME) === $base_name) {
                    return $fileinfo->getPathname();
                }
            }
        }
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base_dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && pathinfo($file->getFilename(), PATHINFO_FILENAME) === $base_name) {
            return $file->getPathname();
        }
    }
    return false;
}

$found = findAssetFile(ASSETS_DIR, $file_dirname, $file_basename);

if ($found && file_exists($found)) {
    header(\'Content-Type: text/plain; charset=utf-8\');
    header(\'Content-Length: \' . filesize($found));
    readfile($found);
    exit;
} else {
    http_response_code(404);
    die("File not found or access denied.");
}
?>',
    'views/dashboard.php' => '<?php
$__emerald_page = isset($GLOBALS[\'emerald_page\']) ? $GLOBALS[\'emerald_page\'] : \'dashboard\';
if ($__emerald_page === \'notepad\') { header(\'Location: notepad/\'); exit; }
$__page_map = array(\'storage\' => \'files\');
$active_menu = isset($__page_map[$__emerald_page]) ? $__page_map[$__emerald_page] : $__emerald_page;
$__allowed_menus = array(\'dashboard\', \'files\', \'domains\', \'cloaking\', \'notes\', \'users\', \'firewall\', \'monitor\');
if (!in_array($active_menu, $__allowed_menus, true)) { $active_menu = \'dashboard\'; }
?>
<!DOCTYPE html>
<html lang="en" class="antialiased dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Central Hub</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Fira+Code:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: \'class\',
            theme: {
                extend: {
                    fontFamily: { 
                        sans: [\'Plus Jakarta Sans\', \'sans-serif\'], 
                        mono: [\'Fira Code\', \'monospace\'] 
                    },
                    colors: {
                        glass: {
                            50: \'rgba(255, 255, 255, 0.05)\',
                            100: \'rgba(255, 255, 255, 0.1)\',
                            200: \'rgba(255, 255, 255, 0.2)\',
                            dark: \'rgba(15, 23, 42, 0.6)\',
                            border: \'rgba(255, 255, 255, 0.08)\'
                        },
                        brand: { 400: \'#22d3ee\', 500: \'#06b6d4\', 600: \'#0891b2\' },
                        accent: { 400: \'#a78bfa\', 500: \'#8b5cf6\', 600: \'#7c3aed\' }
                    },
                    animation: {
                        \'blob\': \'blob 10s infinite\',
                        \'fade-in\': \'fadeIn 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards\',
                        \'slide-up\': \'slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards\'
                    },
                    keyframes: {
                        blob: {
                            \'0%\': { transform: \'translate(0px, 0px) scale(1)\' },
                            \'33%\': { transform: \'translate(30px, -50px) scale(1.1)\' },
                            \'66%\': { transform: \'translate(-20px, 20px) scale(0.9)\' },
                            \'100%\': { transform: \'translate(0px, 0px) scale(1)\' }
                        },
                        fadeIn: { \'0%\': { opacity: \'0\' }, \'100%\': { opacity: \'1\' } },
                        slideUp: { \'0%\': { transform: \'translateY(20px)\', opacity: \'0\' }, \'100%\': { transform: \'translateY(0)\', opacity: \'1\' } }
                    }
                }
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/theme/monokai.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/addon/dialog/dialog.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/addon/search/search.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/addon/search/searchcursor.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/addon/dialog/dialog.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/htmlmixed/htmlmixed.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/php/php.min.js"></script>

    <style>
        :root {
            --bg-base: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --glass-bg: rgba(255, 255, 255, 0.4);
            --glass-border: rgba(255, 255, 255, 0.5);
            --glass-card: rgba(255, 255, 255, 0.6);
            --glass-hover: rgba(255, 255, 255, 0.8);
            --glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1);
            --input-bg: rgba(255, 255, 255, 0.5);
            --border-color: rgba(148, 163, 184, 0.3);
        }

        html.dark {
            --bg-base: #020617;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --glass-bg: rgba(15, 23, 42, 0.4);
            --glass-border: rgba(255, 255, 255, 0.05);
            --glass-card: rgba(30, 41, 59, 0.5);
            --glass-hover: rgba(30, 41, 59, 0.8);
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.4);
            --input-bg: rgba(15, 23, 42, 0.6);
            --border-color: rgba(51, 65, 85, 0.5);
        }

        body {
            background-color: var(--bg-base);
            color: var(--text-main);
            transition: all 0.5s ease;
            overflow: hidden;
        }

        .glass-panel {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            box-shadow: var(--glass-shadow);
        }

        .card-premium {
            background: var(--glass-card);
            backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            border-radius: 1.5rem;
            box-shadow: var(--glass-shadow);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .card-premium:hover {
            transform: translateY(-4px);
            background: var(--glass-hover);
            border-color: rgba(6, 182, 212, 0.3);
            box-shadow: 0 20px 40px -10px rgba(6, 182, 212, 0.15);
        }

        .input-premium {
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            backdrop-filter: blur(8px);
            transition: all 0.3s ease;
            outline: none;
        }

        .input-premium:focus {
            border-color: #06b6d4;
            box-shadow: 0 0 0 4px rgba(6, 182, 212, 0.1);
            background: var(--glass-hover);
        }

        .btn-animated {
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1;
        }
        
        .btn-animated::before {
            content: \'\';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: all 0.5s ease;
            z-index: -1;
        }
        
        .btn-animated:hover::before {
            left: 100%;
        }

        .btn-animated:hover {
            transform: translateY(-2px);
        }

        .btn-animated:active {
            transform: translateY(1px);
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }

        .view-section { display: none; opacity: 0; transform: translateY(15px); transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1); }
        .view-section.active { display: block; opacity: 1; transform: translateY(0); }

        /* Modal Glassmorphism */
        .modal { opacity: 0; pointer-events: none; visibility: hidden; transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); z-index: 9999; backdrop-filter: blur(16px); }
        .modal.active { opacity: 1; pointer-events: auto; visibility: visible; }
        .modal-content { 
            transform: scale(0.95) translateY(20px); 
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            background: var(--glass-card);
            border: 1px solid var(--glass-border);
            box-shadow: 0 30px 60px -12px rgba(0,0,0,0.5);
            backdrop-filter: blur(24px);
        }
        .modal.active .modal-content { transform: scale(1) translateY(0); }

        table th {
            font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
            color: var(--text-muted); border-bottom: 1px solid var(--glass-border); background: var(--glass-bg);
        }
        table td { padding: 1rem; border-bottom: 1px solid var(--border-color); color: var(--text-main); font-size: 0.875rem; font-weight: 500; }
        tr:hover td { background: var(--glass-hover); }

        .auth-secret { color: transparent; text-shadow: 0 0 10px var(--text-muted); cursor: pointer; transition: all 0.2s; user-select: all; }
        .auth-secret:active, .auth-secret:focus, .auth-secret.revealed { color: var(--text-main); text-shadow: none; background: rgba(6, 182, 212, 0.1); border-radius: 6px; padding: 2px 6px; }

        .file-checkbox { width: 1.25rem; height: 1.25rem; border-radius: 0.375rem; border: 1px solid var(--border-color); appearance: none; cursor: pointer; background: var(--input-bg); transition: all 0.2s; position: relative; }
        .file-checkbox:checked { background: #06b6d4; border-color: #06b6d4; }
        .file-checkbox:checked::after { content: \'\'; position: absolute; left: 6px; top: 2px; width: 5px; height: 10px; border: solid white; border-width: 0 2px 2px 0; transform: rotate(45deg); }

        .role-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; box-shadow: inset 0 0 0 1px currentColor; backdrop-filter: blur(4px); }
        .role-owner { color: #f43f5e; background: rgba(244, 63, 94, 0.1); }
        .role-admin { color: #10b981; background: rgba(16, 185, 129, 0.1); }
        .role-guest { color: #8b5cf6; background: rgba(139, 92, 246, 0.1); }

        .CodeMirror { height: 100% !important; font-family: \'Fira Code\', monospace; font-size: 14px; background: transparent !important; color: var(--text-main) !important; border-radius: 0 0 1.5rem 1.5rem; }
    </style>
</head>
<body id="bodyTheme" class="selection:bg-brand-500/30 selection:text-brand-400">
    <script>window.EM = window.EM || {}; window.EM.csrf = <?= json_encode(sec_csrf_token()) ?>;</script>
    <script>if (localStorage.getItem(\'emerald_theme\') !== \'light\') document.documentElement.classList.add(\'dark\');</script>

    <div class="fixed inset-0 z-[-1] overflow-hidden bg-[var(--bg-base)] transition-colors duration-700 pointer-events-none">
        <div class="absolute top-[-10%] left-[-10%] w-[50vw] h-[50vw] rounded-full bg-brand-500/20 blur-[120px] animate-blob mix-blend-screen"></div>
        <div class="absolute top-[20%] right-[-10%] w-[40vw] h-[40vw] rounded-full bg-accent-500/20 blur-[120px] animate-blob mix-blend-screen" style="animation-delay: 2s;"></div>
        <div class="absolute bottom-[-20%] left-[20%] w-[60vw] h-[60vw] rounded-full bg-emerald-500/15 blur-[150px] animate-blob mix-blend-screen" style="animation-delay: 4s;"></div>
    </div>

    <div id="dropOverlay" class="fixed inset-0 bg-brand-900/40 backdrop-blur-md z-[1000] hidden items-center justify-center border-2 border-dashed border-brand-500 m-4 rounded-[2rem] pointer-events-none transition-all">
        <div class="text-center p-12 glass-panel rounded-3xl shadow-2xl">
            <div class="w-24 h-24 bg-brand-500/20 rounded-full flex items-center justify-center mx-auto mb-6 animate-bounce shadow-[0_0_30px_rgba(6,182,212,0.5)]">
                <i class="fa-solid fa-cloud-arrow-up text-5xl text-brand-400"></i>
            </div>
            <h2 class="text-3xl font-extrabold text-white">Deploy Payload</h2>
            <p class="text-brand-200 font-medium mt-2">Release to initiate secure transfer</p>
        </div>
    </div>

    <div class="h-screen w-screen p-4 md:p-6 flex gap-6 relative z-10 box-border">
        
        <aside class="w-[280px] glass-panel rounded-[2rem] flex flex-col shadow-2xl transition-all duration-300 z-30 overflow-hidden">
            <div class="h-24 flex items-center px-8 border-b border-[var(--glass-border)] relative">
                <div class="flex items-center gap-4 relative z-10">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-brand-400 to-accent-500 p-[1px] shadow-lg">
                        <div class="w-full h-full bg-[var(--glass-card)] rounded-2xl flex items-center justify-center backdrop-blur-md">
                            <i class="fa-solid fa-terminal text-brand-400 text-xl"></i>
                        </div>
                    </div>
                    <div>
                        <h1 class="font-extrabold text-xl tracking-tight text-[var(--text-main)] flex items-center gap-2">SUB CLOUD <div class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse shadow-[0_0_10px_#34d399]"></div></h1>
                        <p class="text-[10px] text-[var(--text-muted)] font-mono tracking-widest uppercase font-bold">POWERED BY PTSMC TEAM</p>
                    </div>
                </div>
            </div>

            <nav class="flex-1 px-5 py-8 space-y-2 overflow-y-auto custom-scrollbar" id="mainNav">
                <a href="index.php?p=dashboard" class="nav-item w-full flex items-center gap-4 px-4 py-3.5 <?= $active_menu === \'dashboard\' ? \'bg-brand-500/15 text-brand-400 border border-brand-500/30 font-bold shadow-inner\' : \'text-[var(--text-muted)] hover:bg-[var(--glass-hover)] hover:text-[var(--text-main)] border border-transparent font-medium\' ?> rounded-2xl transition-all">
                    <i class="fa-solid fa-chart-pie w-5 text-center <?= $active_menu === \'dashboard\' ? \'text-brand-400\' : \'\' ?>"></i> Overview
                </a>
                <a href="index.php?p=storage" class="nav-item w-full flex items-center gap-4 px-4 py-3.5 <?= $active_menu === \'files\' ? \'bg-brand-500/15 text-brand-400 border border-brand-500/30 font-bold shadow-inner\' : \'text-[var(--text-muted)] hover:bg-[var(--glass-hover)] hover:text-[var(--text-main)] border border-transparent font-medium\' ?> rounded-2xl transition-all">
                    <i class="fa-solid fa-folder-open w-5 text-center <?= $active_menu === \'files\' ? \'text-brand-400\' : \'\' ?>"></i> Storage
                </a>
                <a href="index.php?p=notes" class="nav-item w-full flex items-center gap-4 px-4 py-3.5 <?= $active_menu === \'notes\' ? \'bg-brand-500/15 text-brand-400 border border-brand-500/30 font-bold shadow-inner\' : \'text-[var(--text-muted)] hover:bg-[var(--glass-hover)] hover:text-[var(--text-main)] border border-transparent font-medium\' ?> rounded-2xl transition-all">
                    <i class="fa-solid fa-box w-5 text-center <?= $active_menu === \'notes\' ? \'text-brand-400\' : \'\' ?>"></i> Containers
                </a>
                <a href="index.php?p=cloaking" class="nav-item w-full flex items-center gap-4 px-4 py-3.5 <?= $active_menu === \'cloaking\' ? \'bg-brand-500/15 text-brand-400 border border-brand-500/30 font-bold shadow-inner\' : \'text-[var(--text-muted)] hover:bg-[var(--glass-hover)] hover:text-[var(--text-main)] border border-transparent font-medium\' ?> rounded-2xl transition-all">
                    <i class="fa-solid fa-masks-theater w-5 text-center <?= $active_menu === \'cloaking\' ? \'text-brand-400\' : \'\' ?>"></i> Data Cloaking
                </a>
                <a href="index.php?p=domains" class="nav-item w-full flex items-center gap-4 px-4 py-3.5 <?= $active_menu === \'domains\' ? \'bg-brand-500/15 text-brand-400 border border-brand-500/30 font-bold shadow-inner\' : \'text-[var(--text-muted)] hover:bg-[var(--glass-hover)] hover:text-[var(--text-main)] border border-transparent font-medium\' ?> rounded-2xl transition-all">
                    <i class="fa-solid fa-vault w-5 text-center <?= $active_menu === \'domains\' ? \'text-brand-400\' : \'\' ?>"></i> Domain Vault
                </a>
                <a href="index.php?p=users" class="nav-item w-full flex items-center gap-4 px-4 py-3.5 <?= $active_menu === \'users\' ? \'bg-brand-500/15 text-brand-400 border border-brand-500/30 font-bold shadow-inner\' : \'text-[var(--text-muted)] hover:bg-[var(--glass-hover)] hover:text-[var(--text-main)] border border-transparent font-medium\' ?> rounded-2xl transition-all">
                    <i class="fa-solid fa-fingerprint w-5 text-center <?= $active_menu === \'users\' ? \'text-brand-400\' : \'\' ?>"></i> Access
                </a>
                <a href="index.php?p=firewall" class="nav-item w-full flex items-center gap-4 px-4 py-3.5 <?= $active_menu === \'firewall\' ? \'bg-brand-500/15 text-brand-400 border border-brand-500/30 font-bold shadow-inner\' : \'text-[var(--text-muted)] hover:bg-[var(--glass-hover)] hover:text-[var(--text-main)] border border-transparent font-medium\' ?> rounded-2xl transition-all">
                    <i class="fa-solid fa-shield-halved w-5 text-center <?= $active_menu === \'firewall\' ? \'text-brand-400\' : \'\' ?>"></i> Firewall
                </a>
                
                <div class="pt-6 pb-1">
                    <p class="text-[9px] font-bold text-[var(--text-muted)] uppercase tracking-widest px-4">Public Apps</p>
                </div>
                <a href="/notepad/" class="w-full flex items-center gap-4 px-4 py-3 text-emerald-400 hover:bg-emerald-500/10 border border-transparent rounded-2xl font-bold transition-all btn-animated">
                    <i class="fa-solid fa-book-open w-5 text-center"></i> Public Notepad
                </a>
            </nav>

            <div class="p-5 border-t border-[var(--glass-border)] bg-[var(--glass-bg)]">
                <div class="flex items-center justify-between mb-4 cursor-pointer hover:bg-[var(--glass-hover)] p-3 rounded-2xl transition-all border border-transparent hover:border-[var(--glass-border)] shadow-sm" onclick="openProfileModal()">
                    <div class="flex items-center gap-3 overflow-hidden">
                        <div class="relative">
                            <img id="sidebarAvatar" src="" class="w-10 h-10 rounded-full border border-[var(--glass-border)] shadow-md object-cover">
                            <div id="sidebarDot" class="w-3 h-3 rounded-full absolute -bottom-0.5 -right-0.5 border-2 border-[var(--bg-base)] bg-slate-500"></div>
                        </div>
                        <div class="overflow-hidden">
                            <div class="font-bold text-[var(--text-main)] text-sm truncate" id="sidebarUsername"><?= htmlspecialchars($_SESSION[\'emerald_user\']) ?></div>
                            <div class="text-[9px] font-bold mt-0.5 role-display uppercase tracking-widest text-[var(--text-muted)]" id="sidebarRole">Fetching...</div>
                        </div>
                    </div>
                    <i class="fa-solid fa-sliders text-[var(--text-muted)] hover:text-brand-400 transition-colors"></i>
                </div>
                <a href="/index.php?action=logout" class="block w-full text-center px-4 py-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 hover:bg-rose-500 hover:text-white rounded-2xl transition-all text-xs font-bold shadow-sm btn-animated">
                    <i class="fa-solid fa-power-off mr-2"></i> Terminate Session
                </a>
            </div>
        </aside>

        <main class="flex-1 flex flex-col gap-6 relative z-20 min-w-0">
            <header class="glass-panel rounded-[2rem] h-24 flex items-center justify-between px-8 shadow-sm shrink-0">
                <div class="flex flex-col">
                    <h1 class="text-3xl font-extrabold text-[var(--text-main)] tracking-tight drop-shadow-md" id="pageTitle">System Overview</h1>
                    <div id="breadcrumb" class="text-xs font-mono text-[var(--text-muted)] mt-1.5 flex items-center gap-2 opacity-0 transition-opacity font-medium">
                        <i class="fa-solid fa-layer-group"></i> / root
                    </div>
                </div>
                <div class="flex items-center gap-6">
                    <div class="hidden lg:flex items-center gap-3 bg-[var(--input-bg)] px-5 py-2.5 rounded-2xl border border-[var(--glass-border)] shadow-inner backdrop-blur-md">
                        <span class="text-[10px] text-[var(--text-muted)] font-bold tracking-widest uppercase">Node Auth:</span>
                        <span class="font-extrabold text-[var(--text-main)] text-sm"><?= htmlspecialchars($_SESSION[\'emerald_user\']) ?></span>
                        <span id="headerRoleBadge" class="role-badge role-guest ml-2">User</span>
                    </div>
                    <div class="relative group">
                        <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--text-muted)] group-focus-within:text-brand-400 transition-colors"></i>
                        <input type="text" id="globalSearch" autocomplete="off" readonly onfocus="this.removeAttribute(\'readonly\');" oninput="performSearch()" placeholder="Ctrl+F to Find..." class="text-sm pl-11 pr-4 py-3 input-premium rounded-2xl w-80 shadow-sm font-medium">
                    </div>
                </div>
            </header>

            <div class="flex-1 glass-panel rounded-[2rem] p-8 overflow-y-auto custom-scrollbar relative shadow-lg" id="mainAreaWrapper">
                
                <div id="view_dashboard" class="view-section <?= $active_menu === \'dashboard\' ? \'active\' : \'\' ?>">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                        <div class="card-premium p-6 group">
                            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-brand-500/20 rounded-full blur-[40px] group-hover:bg-brand-500/40 transition-colors duration-500"></div>
                            <div class="flex items-center justify-between mb-6 relative z-10">
                                <h4 class="text-[var(--text-muted)] text-[10px] font-extrabold uppercase tracking-widest">Cipher Engine</h4>
                                <div class="w-10 h-10 rounded-2xl bg-[var(--glass-bg)] flex items-center justify-center border border-[var(--glass-border)] shadow-sm"><i class="fa-solid fa-shield-halved text-brand-400"></i></div>
                            </div>
                            <ul class="space-y-3 font-mono text-sm relative z-10 font-bold">
                                <li class="flex flex-col"><span class="text-[9px] text-[var(--text-muted)] tracking-widest mb-1">STANDARD</span><span class="text-[var(--text-main)] flex items-center gap-2"><div class="w-1.5 h-1.5 rounded-full bg-emerald-400"></div> AES-256</span></li>
                                <li class="flex flex-col"><span class="text-[9px] text-[var(--text-muted)] tracking-widest mb-1">INTEGRITY</span><span class="text-emerald-400 drop-shadow-[0_0_8px_rgba(52,211,153,0.5)]">OPTIMAL</span></li>
                            </ul>
                        </div>
                        <div class="card-premium p-6 group">
                            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-emerald-500/20 rounded-full blur-[40px] group-hover:bg-emerald-500/40 transition-colors duration-500"></div>
                            <div class="flex items-center justify-between mb-6 relative z-10">
                                <h4 class="text-[var(--text-muted)] text-[10px] font-extrabold uppercase tracking-widest">Network</h4>
                                <div class="w-10 h-10 rounded-2xl bg-[var(--glass-bg)] flex items-center justify-center border border-[var(--glass-border)] shadow-sm"><i class="fa-solid fa-network-wired text-emerald-400"></i></div>
                            </div>
                            <ul class="space-y-3 font-mono text-sm relative z-10 font-bold">
                                <li class="flex flex-col"><span class="text-[9px] text-[var(--text-muted)] tracking-widest mb-1">FIREWALL</span><span class="text-[var(--text-main)] flex items-center gap-2"><div class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></div> ACTIVE</span></li>
                                <li class="flex flex-col"><span class="text-[9px] text-[var(--text-muted)] tracking-widest mb-1">IP NODE</span><span class="text-[var(--text-main)]"><?= $_SERVER[\'REMOTE_ADDR\'] ?></span></li>
                            </ul>
                        </div>
                        <div class="card-premium p-6 group">
                            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-accent-500/20 rounded-full blur-[40px] group-hover:bg-accent-500/40 transition-colors duration-500"></div>
                            <div class="flex items-center justify-between mb-6 relative z-10">
                                <h4 class="text-[var(--text-muted)] text-[10px] font-extrabold uppercase tracking-widest">Environment</h4>
                                <div class="w-10 h-10 rounded-2xl bg-[var(--glass-bg)] flex items-center justify-center border border-[var(--glass-border)] shadow-sm"><i class="fa-solid fa-server text-accent-400"></i></div>
                            </div>
                            <ul class="space-y-3 font-mono text-sm relative z-10 font-bold" id="sysEnvList">
                                <li class="flex flex-col"><span class="text-[9px] text-[var(--text-muted)] tracking-widest mb-1">STORAGE</span><span class="text-accent-400" id="dashDisk">...</span></li>
                                <li class="flex flex-col"><span class="text-[9px] text-[var(--text-muted)] tracking-widest mb-1">INTERFACE</span><span class="text-[var(--text-main)] uppercase" id="dashSapi">...</span></li>
                            </ul>
                        </div>
                        <div class="card-premium p-6 group flex flex-col justify-between">
                            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-amber-500/20 rounded-full blur-[40px] group-hover:bg-amber-500/40 transition-colors duration-500"></div>
                            <div class="flex items-center justify-between mb-2 relative z-10">
                                <h4 class="text-[var(--text-muted)] text-[10px] font-extrabold uppercase tracking-widest">Interface</h4>
                                <div class="w-10 h-10 rounded-2xl bg-[var(--glass-bg)] flex items-center justify-center border border-[var(--glass-border)] shadow-sm"><i class="fa-solid fa-palette text-amber-400"></i></div>
                            </div>
                            <div class="relative z-10 mt-auto">
                                <p class="text-[9px] font-mono text-[var(--text-muted)] mb-3 tracking-widest font-bold">WORKSPACE THEME</p>
                                <div class="flex items-center justify-between bg-[var(--input-bg)] border border-[var(--border-color)] p-3 rounded-2xl cursor-pointer btn-animated shadow-inner w-full" onclick="toggleTheme()">
                                    <span class="text-sm font-extrabold text-[var(--text-main)]" id="themeText">Dark Mode</span>
                                    <div class="w-12 h-6 bg-slate-700/50 rounded-full flex items-center px-1 theme-toggle-btn border border-[var(--glass-border)]"><div class="w-4 h-4 bg-white rounded-full transition-all shadow-md" id="themeCircle"></div></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="card-premium flex flex-col h-[400px]">
                            <div class="px-6 py-5 border-b border-[var(--glass-border)] bg-[var(--glass-bg)] flex items-center gap-3 shrink-0 backdrop-blur-md">
                                <div class="w-8 h-8 rounded-xl bg-brand-500/20 flex items-center justify-center border border-brand-500/30"><i class="fa-solid fa-shield-halved text-brand-400 text-sm"></i></div>
                                <h3 class="font-extrabold text-[var(--text-main)] text-base">Access Logs</h3>
                            </div>
                            <div class="overflow-y-auto flex-1 custom-scrollbar bg-[var(--glass-bg)]">
                                <table class="w-full text-left border-collapse">
                                    <thead class="sticky top-0 z-10 shadow-sm backdrop-blur-md">
                                        <tr><th class="px-6 py-4">Time</th><th class="px-4 py-4">User</th><th class="px-4 py-4">IP</th><th class="px-4 py-4 text-right">Status</th></tr>
                                    </thead>
                                    <tbody id="logsList" class="divide-y divide-[var(--border-color)]"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-premium flex flex-col h-[400px]">
                            <div class="px-6 py-5 border-b border-[var(--glass-border)] bg-[var(--glass-bg)] flex items-center gap-3 shrink-0 backdrop-blur-md">
                                <div class="w-8 h-8 rounded-xl bg-accent-500/20 flex items-center justify-center border border-accent-500/30"><i class="fa-solid fa-clipboard-list text-accent-400 text-sm"></i></div>
                                <h3 class="font-extrabold text-[var(--text-main)] text-base">Activity Journal</h3>
                            </div>
                            <div class="overflow-y-auto flex-1 custom-scrollbar bg-[var(--glass-bg)]">
                                <table class="w-full text-left border-collapse">
                                    <thead class="sticky top-0 z-10 shadow-sm backdrop-blur-md">
                                        <tr><th class="px-6 py-4">Time</th><th class="px-4 py-4">User</th><th class="px-4 py-4">Action Details</th></tr>
                                    </thead>
                                    <tbody id="activityList" class="divide-y divide-[var(--border-color)]"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="view_files" class="view-section <?= $active_menu === \'files\' ? \'active h-full flex flex-col\' : \'\' ?>">
                    <div class="flex items-center gap-3 mb-6 bg-[var(--glass-card)] p-4 rounded-2xl border border-[var(--glass-border)] hidden shadow-lg backdrop-blur-xl animate-fade-in z-20" id="bulkToolbar">
                        <span class="text-[var(--text-main)] font-bold text-sm mr-2 border-r border-[var(--border-color)] pr-4 flex items-center gap-2"><span id="selCount" class="bg-brand-500 text-white px-2 py-0.5 rounded-md text-xs">0</span> selected</span>
                        <button class="bg-rose-500/10 text-rose-400 px-5 py-2.5 rounded-xl text-sm font-bold hover:bg-rose-500 hover:text-white border border-rose-500/20 transition-all btn-animated" onclick="bulkAction(\'delete\')"><i class="fa-solid fa-trash mr-1.5"></i> Purge</button>
                        <button class="bg-brand-500/10 text-brand-400 px-5 py-2.5 rounded-xl text-sm font-bold hover:bg-brand-500 hover:text-white border border-brand-500/20 transition-all btn-animated" onclick="bulkAction(\'copy\')"><i class="fa-solid fa-copy mr-1.5"></i> Copy</button>
                        <button class="bg-accent-500/10 text-accent-400 px-5 py-2.5 rounded-xl text-sm font-bold hover:bg-accent-500 hover:text-white border border-accent-500/20 transition-all btn-animated" onclick="bulkAction(\'cut\')"><i class="fa-solid fa-scissors mr-1.5"></i> Cut</button>
                    </div>
                    
                    <div class="flex items-center gap-3 mb-6 bg-brand-500/20 p-4 rounded-2xl border border-brand-400/40 hidden shadow-[0_0_30px_rgba(6,182,212,0.2)] backdrop-blur-xl animate-fade-in z-20" id="pasteToolbar">
                        <span class="text-brand-300 font-bold text-sm mr-2 border-r border-brand-400/40 pr-4" id="pasteInfo"></span>
                        <button class="bg-brand-500 text-white px-6 py-2.5 rounded-xl text-sm font-bold hover:bg-brand-400 transition-all btn-animated shadow-lg" onclick="executePaste()"><i class="fa-solid fa-paste mr-1.5"></i> Paste Here</button>
                        <button class="bg-transparent border border-brand-400/40 text-brand-300 px-5 py-2.5 rounded-xl text-sm font-bold hover:bg-brand-500/30 transition-all btn-animated" onclick="cancelPaste()">Cancel</button>
                    </div>

                    <div class="flex justify-between items-center mb-6 bg-[var(--glass-card)] border border-[var(--glass-border)] p-4 rounded-[2rem] shadow-sm backdrop-blur-lg">
                        <div class="flex gap-3">
                            <button class="bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-main)] px-5 py-3 text-sm font-bold rounded-2xl hover:border-brand-400/50 transition-all btn-animated flex items-center gap-2 shadow-sm" onclick="navigateUp()" id="btnNavUp" style="display:none;">
                                <i class="fa-solid fa-arrow-left"></i> Back
                            </button>
                            <div class="relative group">
                                <i class="fa-solid fa-filter absolute left-4 top-1/2 -translate-y-1/2 text-[var(--text-muted)] text-sm"></i>
                                <input type="text" id="assetSearchFilter" autocomplete="off" readonly onfocus="this.removeAttribute(\'readonly\');" placeholder="Filter current view..." onkeyup="filterAssets()" class="text-sm pl-11 pr-4 py-3 input-premium rounded-2xl w-64 font-medium shadow-sm">
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <button class="bg-[var(--input-bg)] border border-[var(--glass-border)] text-brand-400 px-5 py-3 text-sm font-bold rounded-2xl hover:border-brand-400/50 transition-all btn-animated flex items-center gap-2 shadow-sm" onclick="promptCreateFolder()">
                                <i class="fa-solid fa-folder-plus text-lg"></i>
                            </button>
                            <button class="bg-[var(--input-bg)] border border-[var(--glass-border)] text-emerald-400 px-5 py-3 text-sm font-bold rounded-2xl hover:border-emerald-400/50 transition-all btn-animated flex items-center gap-2 shadow-sm" onclick="promptCreateFile()">
                                <i class="fa-solid fa-file-circle-plus text-lg"></i>
                            </button>
                            <div class="w-px h-8 bg-[var(--border-color)] self-center mx-2"></div>
                            <button class="bg-brand-600 text-white px-6 py-3 text-sm font-bold rounded-2xl btn-animated flex items-center gap-2 shadow-lg hover:bg-brand-500 border border-brand-400/50" onclick="document.getElementById(\'fileInput\').click()">
                                <i class="fa-solid fa-cloud-arrow-up"></i> Upload
                            </button>
                            <button class="bg-glass-card border border-brand-500/40 text-brand-400 px-5 py-3 text-sm font-bold rounded-2xl btn-animated flex items-center gap-2 shadow-sm hover:bg-brand-500/20" onclick="document.getElementById(\'folderInput\').click()">
                                <i class="fa-solid fa-folder-tree"></i>
                            </button>
                            <input type="file" id="fileInput" class="hidden" multiple onchange="handleStandardUpload(this.files, false)">
                            <input type="file" id="folderInput" class="hidden" webkitdirectory directory multiple onchange="handleStandardUpload(this.files, true)">
                            <button class="bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-muted)] px-5 py-3 rounded-2xl hover:text-[var(--text-main)] transition-all btn-animated shadow-sm" onclick="loadFiles(currentPath)">
                                <i class="fa-solid fa-rotate-right"></i>
                            </button>
                        </div>
                    </div>

                    <div class="card-premium flex flex-col flex-1 min-h-[500px]">
                        <div class="overflow-y-auto flex-1 custom-scrollbar">
                            <table class="w-full text-left border-collapse relative">
                                <thead class="sticky top-0 z-10 shadow-sm backdrop-blur-xl bg-[var(--glass-bg)]/80">
                                    <tr>
                                        <th class="px-6 py-5 w-12 text-center border-b border-[var(--glass-border)]">
                                            <input type="checkbox" id="selectAllCheckbox" class="file-checkbox" onclick="event.stopPropagation(); toggleSelectAll(this)">
                                        </th>
                                        <th class="px-4 py-5 border-b border-[var(--glass-border)]">Asset Name</th>
                                        <th class="px-6 py-5 border-b border-[var(--glass-border)]">Format</th>
                                        <th class="px-6 py-5 border-b border-[var(--glass-border)]">Size</th>
                                        <th class="px-6 py-5 border-b border-[var(--glass-border)]">Last Mod</th>
                                        <th class="px-6 py-5 border-b border-[var(--glass-border)]">Owner</th>
                                        <th class="px-6 py-5 text-right border-b border-[var(--glass-border)]">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="filesList" class="divide-y divide-[var(--border-color)]"></tbody>
                            </table>
                        </div>
                        <div id="assetsPagination" class="bg-[var(--glass-bg)] border-t border-[var(--glass-border)] shrink-0 p-2 backdrop-blur-md"></div>
                    </div>
                </div>

                <div id="view_notes" class="view-section <?= $active_menu === \'notes\' ? \'active\' : \'\' ?>">
                    <div class="flex justify-between items-center mb-8 bg-[var(--glass-card)] p-6 border border-[var(--glass-border)] rounded-[2rem] shadow-lg backdrop-blur-xl">
                        <div>
                            <h2 class="text-2xl font-extrabold text-[var(--text-main)]">Containers</h2>
                            <p class="text-[var(--text-muted)] text-sm mt-1 font-medium">Manage system containers and their access credentials.</p>
                        </div>
                        <button class="bg-brand-600 border border-brand-400/50 text-white px-8 py-3.5 text-sm font-bold rounded-2xl btn-animated flex items-center gap-2 shadow-[0_0_20px_rgba(6,182,212,0.4)] hover:bg-brand-500" onclick="openContainerModal()">
                            <i class="fa-solid fa-layer-group"></i> Build Container
                        </button>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6" id="notesListArea"></div>
                </div>

                <div id="view_domains" class="view-section <?= $active_menu === \'domains\' ? \'active\' : \'\' ?>">
                    <div class="flex justify-between items-center mb-8 bg-[var(--glass-card)] p-6 border border-[var(--glass-border)] rounded-[2rem] shadow-lg backdrop-blur-xl">
                        <div>
                            <h2 class="text-2xl font-extrabold text-[var(--text-main)]">Domain Vault</h2>
                            <p class="text-[var(--text-muted)] text-sm mt-1 font-medium">Encrypted vault for hosting credentials, tokens and expiry tracking.</p>
                        </div>
                        <button class="bg-brand-600 border border-brand-400/50 text-white px-8 py-3.5 text-sm font-bold rounded-2xl btn-animated flex items-center gap-2 shadow-[0_0_20px_rgba(6,182,212,0.4)] hover:bg-brand-500" onclick="openDomainModal()">
                            <i class="fa-solid fa-vault"></i> Add Domain
                        </button>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6" id="domainsGrid"></div>
                </div>

                <div id="view_cloaking" class="view-section <?= $active_menu === \'cloaking\' ? \'active\' : \'\' ?>">
                    <div class="flex justify-between items-center mb-8 bg-[var(--glass-card)] p-6 border border-[var(--glass-border)] rounded-[2rem] shadow-lg backdrop-blur-xl">
                        <div>
                            <h2 class="text-2xl font-extrabold text-[var(--text-main)]">SEO Cloaking</h2>
                            <p class="text-[var(--text-muted)] text-sm mt-1 font-medium">Dynamic payload injections for targeted routing.</p>
                        </div>
                        <button class="bg-accent-600 border border-accent-400/50 text-white px-8 py-3.5 text-sm font-bold rounded-2xl hover:bg-accent-500 transition-all btn-animated shadow-[0_0_20px_rgba(139,92,246,0.4)] flex items-center gap-2" onclick="openCloakingModal()">
                            <i class="fa-solid fa-masks-theater"></i> Inject Cloaking
                        </button>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6" id="cloakingGrid"></div>
                </div>

                <div id="view_users" class="view-section <?= $active_menu === \'users\' ? \'active\' : \'\' ?>">
                    <div class="flex justify-between items-center mb-8 bg-[var(--glass-card)] p-6 border border-[var(--glass-border)] rounded-[2rem] shadow-lg backdrop-blur-xl">
                        <div>
                            <h2 class="text-2xl font-extrabold text-[var(--text-main)]">Identity Registry</h2>
                            <p class="text-[var(--text-muted)] text-sm mt-1 font-medium">Manage node privileges and secure access.</p>
                        </div>
                        <button class="bg-emerald-600 border border-emerald-400/50 text-white px-8 py-3.5 text-sm font-bold rounded-2xl hover:bg-emerald-500 transition-all btn-animated shadow-[0_0_20px_rgba(16,185,129,0.4)] flex items-center gap-2" onclick="openUserModal()">
                            <i class="fa-solid fa-fingerprint"></i> Register Node
                        </button>
                    </div>
                    <div class="card-premium max-w-5xl">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-[var(--glass-bg)] border-b border-[var(--glass-border)] backdrop-blur-md">
                                <tr><th class="px-8 py-5 text-sm">Identity</th><th class="px-6 py-5 text-sm">Privilege</th><th class="px-6 py-5 text-right text-sm">Actions</th></tr>
                            </thead>
                            <tbody id="usersList" class="divide-y divide-[var(--border-color)]"></tbody>
                        </table>
                    </div>
                </div>

                <div id="view_firewall" class="view-section <?= $active_menu === \'firewall\' ? \'active\' : \'\' ?>">
                    <div class="flex justify-between items-center mb-8 bg-[var(--glass-card)] p-6 border border-[var(--glass-border)] rounded-[2rem] shadow-lg backdrop-blur-xl">
                        <div>
                            <h2 class="text-2xl font-extrabold text-[var(--text-main)]">Network Firewall</h2>
                            <p class="text-[var(--text-muted)] text-sm mt-1 font-medium">Strict IP whitelist control for terminal access.</p>
                        </div>
                        <button class="bg-rose-600 border border-rose-400/50 text-white px-8 py-3.5 text-sm font-bold rounded-2xl hover:bg-rose-500 transition-all btn-animated shadow-[0_0_20px_rgba(244,63,94,0.4)] flex items-center gap-2" onclick="openFirewallModal()">
                            <i class="fa-solid fa-shield-virus"></i> Authorize IP
                        </button>
                    </div>
                    <div class="card-premium max-w-5xl">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-[var(--glass-bg)] border-b border-[var(--glass-border)] backdrop-blur-md">
                                <tr><th class="px-8 py-5 text-sm">IP Address</th><th class="px-6 py-5 text-sm">Notes</th><th class="px-6 py-5 text-sm">Added</th><th class="px-6 py-5 text-right text-sm">Actions</th></tr>
                            </thead>
                            <tbody id="firewallList" class="divide-y divide-[var(--border-color)] font-mono text-sm"></tbody>
                        </table>
                    </div>
                </div>

                <div id="view_monitor" class="view-section <?= $active_menu === \'monitor\' ? \'active\' : \'\' ?>">
                    <div class="flex justify-between items-center mb-8 bg-[var(--glass-card)] p-6 border border-[var(--glass-border)] rounded-[2rem] shadow-lg backdrop-blur-xl">
                        <div>
                            <h2 class="text-2xl font-extrabold text-[var(--text-main)]">Resource Monitor</h2>
                            <p class="text-[var(--text-muted)] text-sm mt-1 font-medium">Real-time Server Resource & Process execution.</p>
                        </div>
                        <button class="bg-brand-600 border border-brand-400/50 text-white px-8 py-3.5 text-sm font-bold rounded-2xl hover:bg-brand-500 transition-all btn-animated flex items-center gap-2 shadow-[0_0_20px_rgba(6,182,212,0.4)]" onclick="createSnapshot()">
                            <i class="fa-solid fa-camera"></i> System Snapshot
                        </button>
                    </div>
                    <div class="card-premium max-w-6xl flex flex-col h-[600px]">
                        <div class="overflow-y-auto flex-1 custom-scrollbar">
                            <table class="w-full text-left border-collapse">
                                <thead class="sticky top-0 z-10 bg-[var(--glass-bg)] backdrop-blur-md">
                                    <tr class="border-b border-[var(--glass-border)] text-xs font-bold text-[var(--text-muted)] uppercase tracking-widest">
                                        <th class="px-6 py-5">PID</th>
                                        <th class="px-6 py-5">User</th>
                                        <th class="px-6 py-5">CPU %</th>
                                        <th class="px-6 py-5">RAM %</th>
                                        <th class="px-6 py-5">Command Executed</th>
                                        <th class="px-6 py-5 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="processList" class="divide-y divide-[var(--border-color)] text-sm font-mono"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
            </div>
        </main>
    </div>

    <div class="modal fixed inset-0 flex items-center justify-center p-4 z-[9999]" id="modalAuthPrompt">
        <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal(\'modalAuthPrompt\')"></div>
        <div class="modal-content rounded-[2.5rem] w-full max-w-sm relative z-10 overflow-hidden p-10 text-center border-t-4 border-t-rose-500">
            <div class="w-24 h-24 bg-rose-500/20 rounded-[2rem] border border-rose-500/30 flex items-center justify-center mx-auto mb-8 shadow-[0_0_30px_rgba(244,63,94,0.3)]">
                <i class="fa-solid fa-lock text-5xl text-rose-400"></i>
            </div>
            <h3 class="font-extrabold text-2xl text-[var(--text-main)] mb-2">Auth Required</h3>
            <p class="text-[var(--text-muted)] text-sm mb-8 font-medium">Verify identity for destructive operation.</p>
            <input type="password" id="authPassword" placeholder="Passphrase" class="w-full text-center input-premium rounded-2xl p-4 font-bold text-lg mb-8 tracking-widest shadow-inner">
            <div class="flex gap-4">
                <button class="flex-1 py-3.5 bg-[var(--input-bg)] border border-[var(--glass-border)] rounded-2xl font-bold text-[var(--text-muted)] hover:text-[var(--text-main)] hover:bg-[var(--glass-hover)] transition-all btn-animated" onclick="closeModal(\'modalAuthPrompt\')">Cancel</button>
                <button class="flex-1 py-3.5 bg-rose-600 text-white rounded-2xl font-bold hover:bg-rose-500 transition-all btn-animated shadow-[0_0_20px_rgba(244,63,94,0.4)]" onclick="executeAuthorizedAction()">Confirm</button>
            </div>
        </div>
    </div>

    <div class="modal fixed inset-0 flex items-center justify-center p-4 z-[9999]" id="modalUploadProgress">
        <div class="fixed inset-0 bg-black/40 backdrop-blur-sm"></div>
        <div class="modal-content rounded-[2.5rem] w-full max-w-md relative z-10 overflow-hidden p-10 text-center border border-[var(--glass-border)]">
            <div class="w-24 h-24 bg-brand-500/20 rounded-[2rem] border border-brand-500/30 flex items-center justify-center mx-auto mb-8 animate-pulse shadow-[0_0_30px_rgba(6,182,212,0.3)]">
                <i class="fa-solid fa-cloud-arrow-up text-5xl text-brand-400"></i>
            </div>
            <h3 class="font-extrabold text-2xl text-[var(--text-main)] mb-3">Processing Payload</h3>
            <p class="text-brand-300 text-sm mb-8 font-mono tracking-wide" id="uploadProgressText">Initializing secure tunnel...</p>
            <div class="w-full bg-[var(--input-bg)] rounded-full h-3 mb-2 overflow-hidden border border-[var(--glass-border)] p-0.5">
                <div class="bg-gradient-to-r from-brand-500 to-accent-500 h-full rounded-full transition-all duration-300 shadow-[0_0_10px_rgba(6,182,212,0.8)]" id="uploadProgressBar" style="width: 0%"></div>
            </div>
        </div>
    </div>

    <div class="modal fixed inset-0 flex items-center justify-center p-4 z-[9999]" id="modalProfile">
        <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal(\'modalProfile\')"></div>
        <div class="modal-content rounded-[2.5rem] w-full max-w-lg relative z-10 overflow-hidden border border-[var(--glass-border)]">
            <div class="px-10 py-6 border-b border-[var(--glass-border)] flex justify-between items-center bg-[var(--glass-bg)] backdrop-blur-md">
                <h3 class="font-extrabold text-xl text-[var(--text-main)]">Profile Identity</h3>
                <button onclick="closeModal(\'modalProfile\')" class="w-8 h-8 rounded-full bg-[var(--input-bg)] text-[var(--text-muted)] hover:text-[var(--text-main)] flex items-center justify-center transition-colors"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-10">
                <div class="text-center mb-8 relative">
                    <img id="profileAvatarPreview" src="" class="w-32 h-32 rounded-full border-[3px] border-brand-500/50 shadow-[0_0_30px_rgba(6,182,212,0.3)] object-cover mx-auto cursor-pointer hover:opacity-80 transition-opacity" onclick="openAvatarZoom(this.src)">
                    <button onclick="document.getElementById(\'avatarUpload\').click()" class="absolute bottom-[-10px] right-1/2 translate-x-12 w-10 h-10 bg-brand-500 rounded-xl border border-[var(--glass-border)] text-white flex items-center justify-center hover:bg-brand-400 shadow-lg cursor-pointer transition-all"><i class="fa-solid fa-camera"></i></button>
                    <input type="file" id="avatarUpload" class="hidden" accept="image/*" onchange="handleAvatarUpload(event)">
                    <input type="hidden" id="profAvatarBase64">
                </div>
                <div class="grid grid-cols-2 gap-5 mb-5">
                    <div>
                        <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest mb-2">Username</label>
                        <input type="text" id="profUsername" class="w-full input-premium rounded-xl p-3.5 font-bold text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest mb-2">New Password</label>
                        <input type="password" id="profPassword" placeholder="Leave blank to keep" class="w-full input-premium rounded-xl p-3.5 font-bold text-sm">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-5">
                    <div>
                        <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest mb-2">Security Question</label>
                        <input type="text" id="profSecQ" class="w-full input-premium rounded-xl p-3.5 font-bold text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest mb-2">Security Answer</label>
                        <input type="text" id="profSecA" class="w-full input-premium rounded-xl p-3.5 font-bold text-sm">
                    </div>
                </div>
                <button class="w-full mt-8 py-4 bg-brand-600 border border-brand-400/50 text-white rounded-2xl font-bold btn-animated text-sm shadow-[0_0_20px_rgba(6,182,212,0.3)] hover:bg-brand-500" onclick="updateProfile()">Update Identity</button>
            </div>
        </div>
    </div>

    <div class="modal fixed inset-0 flex items-center justify-center p-4 md:p-8 z-[10000]" id="modalAvatarZoom">
        <div class="fixed inset-0 bg-black/60 backdrop-blur-xl" onclick="closeModal(\'modalAvatarZoom\')"></div>
        <img id="avatarZoomImg" src="" class="max-w-full max-h-full rounded-3xl relative z-10 shadow-[0_0_50px_rgba(0,0,0,0.8)] object-contain border border-[var(--glass-border)]">
    </div>

    <div class="modal fixed inset-0 flex items-center justify-center p-4 md:p-8 z-[9999]" id="modalEditor">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal(\'modalEditor\')"></div>
        <div class="modal-content rounded-[2.5rem] w-full max-w-[90vw] flex flex-col h-[90vh] relative z-10 overflow-hidden border border-[var(--glass-border)]">
            <div class="px-8 py-5 border-b border-[var(--glass-border)] flex justify-between items-center bg-[var(--glass-bg)] backdrop-blur-xl shrink-0 z-50">
                <div class="flex items-center gap-5">
                    <div class="w-12 h-12 rounded-[1rem] bg-brand-500/20 flex items-center justify-center border border-brand-500/30 shadow-inner">
                        <i id="editorIcon" class="fa-solid fa-code text-brand-400 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-xl text-[var(--text-main)] font-mono tracking-tight flex items-center gap-4">
                            <span id="editorTitle">filename.ext</span>
                            <span id="editorSize" class="text-[10px] bg-[var(--input-bg)] text-brand-300 border border-[var(--glass-border)] px-3 py-1 rounded-md font-sans uppercase font-extrabold shadow-sm">0 KB</span>
                        </h3>
                        <div class="text-[10px] text-[var(--text-muted)] mt-1 flex items-center gap-4 font-medium uppercase tracking-widest">
                            <span><i class="fa-regular fa-clock mr-1.5"></i> <span id="editorModified">...</span></span>
                            <span><kbd class="bg-[var(--input-bg)] border border-[var(--glass-border)] px-2 py-0.5 rounded font-mono shadow-sm">Ctrl+F</kbd> Find | <kbd class="bg-[var(--input-bg)] border border-[var(--glass-border)] px-2 py-0.5 rounded font-mono shadow-sm">Ctrl+S</kbd> Save</span>
                        </div>
                    </div>
                </div>
                <div class="flex gap-4">
                    <button class="bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-main)] px-6 py-3 text-sm font-bold rounded-2xl hover:bg-[var(--glass-hover)] transition-all btn-animated" onclick="closeModal(\'modalEditor\')">Close</button>
                    <button class="bg-brand-600 border border-brand-400/50 text-white px-8 py-3 text-sm font-bold rounded-2xl btn-animated flex items-center gap-2 hover:bg-brand-500 shadow-[0_0_20px_rgba(6,182,212,0.4)]" onclick="saveFileEditor()">
                        <i class="fa-solid fa-floppy-disk"></i> Commit
                    </button>
                </div>
            </div>
            <div class="flex-1 relative w-full flex flex-col bg-[#0b0f19]/90 backdrop-blur-md" id="editorContainer">
                <textarea id="codeEditor"></textarea>
            </div>
        </div>
    </div>

    <div class="modal fixed inset-0 flex items-center justify-center p-4 md:p-8 z-[9999]" id="modalPreviewAsset">
        <div class="fixed inset-0 bg-black/60 backdrop-blur-md" onclick="closeModal(\'modalPreviewAsset\')"></div>
        <div class="modal-content rounded-[2.5rem] w-full max-w-5xl flex flex-col h-[85vh] relative z-10 overflow-hidden border border-[var(--glass-border)]">
            <div class="px-8 py-5 border-b border-[var(--glass-border)] flex justify-between items-center bg-[var(--glass-bg)] backdrop-blur-xl shrink-0">
                <h3 class="font-bold text-xl text-[var(--text-main)] font-mono tracking-tight flex items-center gap-3">
                    <i class="fa-solid fa-eye text-brand-400"></i> <span id="previewTitle">Preview</span>
                </h3>
                <div class="flex gap-3">
                    <button class="bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-main)] px-6 py-2.5 text-sm font-bold rounded-xl hover:bg-[var(--glass-hover)] transition-all btn-animated" onclick="closeModal(\'modalPreviewAsset\')">Close</button>
                    <button id="btnEditPreview" class="bg-emerald-600 border border-emerald-400/50 text-white px-6 py-2.5 text-sm font-bold rounded-xl btn-animated hover:bg-emerald-500 shadow-[0_0_15px_rgba(16,185,129,0.3)] flex items-center gap-2"><i class="fa-solid fa-pen"></i> Edit Mode</button>
                </div>
            </div>
            <div class="flex-1 overflow-hidden relative flex bg-black/50" id="previewContentArea"></div>
        </div>
    </div>

    <div class="modal fixed inset-0 flex items-center justify-center p-4 z-[9999]" id="modalContainer">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal(\'modalContainer\')"></div>
        <div class="modal-content rounded-[2.5rem] w-full max-w-6xl relative z-10 max-h-[95vh] flex flex-col overflow-hidden border border-[var(--glass-border)]">
            <div class="px-10 py-6 border-b border-[var(--glass-border)] flex justify-between items-center bg-[var(--glass-bg)] backdrop-blur-xl">
                <h3 class="font-extrabold text-2xl text-[var(--text-main)] flex items-center gap-4">
                    <div class="w-12 h-12 bg-brand-500/20 rounded-[1rem] flex items-center justify-center border border-brand-500/30">
                        <i class="fa-solid fa-layer-group text-brand-400 text-xl"></i>
                    </div>
                    Configure Container
                </h3>
                <button onclick="closeModal(\'modalContainer\')" class="w-10 h-10 rounded-full bg-[var(--input-bg)] text-[var(--text-muted)] hover:text-rose-400 flex items-center justify-center transition-colors"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <div class="p-10 overflow-y-auto custom-scrollbar flex-1">
                <input type="hidden" id="containerId">
                <div class="space-y-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest mb-3 ml-2">Identifier</label>
                            <input type="text" id="containerTitle" placeholder="e.g., Project Alpha" class="w-full input-premium rounded-2xl p-4 font-bold text-base shadow-inner">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest mb-3 ml-2">Status</label>
                            <select id="containerStatus" class="w-full input-premium rounded-2xl p-4 font-bold text-base shadow-inner appearance-none">
                                <option value="active">Active (Green)</option>
                                <option value="new">New (Grey)</option>
                                <option value="deactive">Deactive (Red)</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
                        <div class="bg-[var(--glass-card)] border border-[var(--glass-border)] rounded-[2rem] p-6 flex flex-col shadow-sm">
                            <div class="flex items-center justify-between mb-5 border-b border-[var(--glass-border)] pb-4">
                                <h4 class="font-extrabold text-[var(--text-main)] text-sm flex items-center gap-3"><i class="fa-solid fa-list text-emerald-400"></i> Terminal & Resource List</h4>
                            </div>
                            <textarea id="containerTextList" class="w-full flex-1 input-premium rounded-xl p-5 font-mono text-xs h-72 custom-scrollbar whitespace-nowrap overflow-x-auto" placeholder="Enter payloads, commands..."></textarea>
                        </div>
                        <div class="bg-[var(--glass-card)] border border-[var(--glass-border)] rounded-[2rem] p-6 shadow-sm">
                            <div class="flex items-center gap-3 mb-5 border-b border-[var(--glass-border)] pb-4">
                                <h4 class="font-extrabold text-[var(--text-main)] text-sm"><i class="fa-solid fa-server text-accent-400"></i> Server Node Auth</h4>
                            </div>
                            <div class="space-y-4 mt-4">
                                <div class="flex items-center bg-[var(--input-bg)] border border-[var(--glass-border)] rounded-xl p-1 shadow-inner">
                                    <span class="text-[10px] font-extrabold text-[var(--text-muted)] w-20 text-center tracking-widest">HOST</span>
                                    <input type="text" id="containerHost" class="flex-1 bg-transparent text-[var(--text-main)] py-3.5 px-4 font-mono text-sm outline-none border-l border-[var(--glass-border)]">
                                </div>
                                <div class="flex items-center bg-[var(--input-bg)] border border-[var(--glass-border)] rounded-xl p-1 shadow-inner">
                                    <span class="text-[10px] font-extrabold text-[var(--text-muted)] w-20 text-center tracking-widest">USER</span>
                                    <input type="text" id="containerUser" class="flex-1 bg-transparent text-brand-400 py-3.5 px-4 font-mono text-sm outline-none border-l border-[var(--glass-border)]">
                                </div>
                                <div class="flex items-center bg-[var(--input-bg)] border border-[var(--glass-border)] rounded-xl p-1 shadow-inner">
                                    <span class="text-[10px] font-extrabold text-[var(--text-muted)] w-20 text-center tracking-widest">PASS</span>
                                    <input type="text" id="containerPass" class="flex-1 bg-transparent text-rose-400 py-3.5 px-4 font-mono text-sm outline-none border-l border-[var(--glass-border)]">
                                </div>
                                <div class="flex items-center bg-[var(--input-bg)] border border-[var(--glass-border)] rounded-xl p-1 shadow-inner">
                                    <span class="text-[10px] font-extrabold text-[var(--text-muted)] w-20 text-center tracking-widest">DIR</span>
                                    <input type="text" id="containerDir" class="flex-1 bg-transparent text-emerald-400 py-3.5 px-4 font-mono text-sm outline-none border-l border-[var(--glass-border)]">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-10 py-6 border-t border-[var(--glass-border)] bg-[var(--glass-bg)] flex justify-end gap-4 backdrop-blur-xl">
                <button class="px-8 py-3.5 bg-[var(--input-bg)] border border-[var(--glass-border)] rounded-2xl text-sm font-bold text-[var(--text-main)] hover:bg-[var(--glass-hover)] transition-all btn-animated" onclick="closeModal(\'modalContainer\')">Cancel</button>
                <button class="px-10 py-3.5 bg-brand-600 border border-brand-400/50 text-white rounded-2xl text-sm font-bold btn-animated flex items-center gap-2 hover:bg-brand-500 shadow-[0_0_20px_rgba(6,182,212,0.4)]" onclick="saveContainer()">
                    Store Container
                </button>
            </div>
        </div>
    </div>

    <div class="modal fixed inset-0 flex items-center justify-center p-4 md:p-8 z-[9999]" id="modalViewContainer">
        <div class="fixed inset-0 bg-black/60 backdrop-blur-xl" onclick="closeModal(\'modalViewContainer\')"></div>
        <div class="modal-content rounded-[2.5rem] w-full max-w-5xl relative z-10 overflow-hidden flex flex-col max-h-[90vh] border border-[var(--glass-border)]">
            <div class="px-10 py-8 border-b border-[var(--glass-border)] flex justify-between items-center bg-[var(--glass-bg)] backdrop-blur-xl">
                <div class="flex items-center gap-5">
                    <img id="viewContainerAvatar" src="" class="w-14 h-14 rounded-full border-2 border-[var(--glass-border)] shadow-[0_0_15px_rgba(255,255,255,0.1)] object-cover cursor-pointer hover:opacity-80">
                    <div>
                        <h3 class="font-extrabold text-2xl text-[var(--text-main)] tracking-tight drop-shadow-md" id="viewContainerTitle">Container</h3>
                        <p class="text-[10px] text-brand-400 font-mono uppercase tracking-widest font-bold mt-1" id="viewContainerOwner">Owner</p>
                    </div>
                </div>
                <div class="flex gap-3" id="viewContainerActions"></div>
            </div>
            <div class="p-10 overflow-y-auto custom-scrollbar flex-1 space-y-8" id="viewContainerContent"></div>
        </div>
    </div>

    <div class="modal fixed inset-0 flex items-center justify-center p-4 z-[9999]" id="modalDomainView">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal(\'modalDomainView\')"></div>
        <div class="modal-content rounded-[2.5rem] w-full max-w-2xl relative z-10 overflow-hidden flex flex-col border border-[var(--glass-border)]">
            <div class="px-10 py-6 border-b border-[var(--glass-border)] flex justify-between items-center bg-[var(--glass-bg)] backdrop-blur-xl">
                <h3 class="font-extrabold text-2xl text-[var(--text-main)] flex items-center gap-4">
                    <div class="w-12 h-12 bg-brand-500/20 rounded-[1rem] flex items-center justify-center border border-brand-500/30 shadow-inner">
                        <i class="fa-solid fa-vault text-brand-400"></i>
                    </div>
                    <span id="viewDomainTitle">Domain Vault</span>
                </h3>
                <button onclick="closeModal(\'modalDomainView\')" class="w-10 h-10 rounded-full bg-[var(--input-bg)] text-[var(--text-muted)] hover:text-rose-400 flex items-center justify-center transition-colors"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <div class="p-10 space-y-6 flex-1 overflow-y-auto custom-scrollbar" id="viewDomainContent"></div>
        </div>
    </div>

    <div class="modal fixed inset-0 flex items-center justify-center p-4 z-[9999]" id="modalDomain">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal(\'modalDomain\')"></div>
        <div class="modal-content rounded-[2.5rem] w-full max-w-3xl relative z-10 overflow-hidden flex flex-col border border-[var(--glass-border)]">
            <div class="px-10 py-6 border-b border-[var(--glass-border)] flex justify-between items-center bg-[var(--glass-bg)] backdrop-blur-xl">
                <h3 class="font-extrabold text-2xl text-[var(--text-main)] flex items-center gap-4">
                    <div class="w-12 h-12 bg-brand-500/20 rounded-[1rem] flex items-center justify-center border border-brand-500/30 shadow-inner">
                        <i class="fa-solid fa-vault text-brand-400"></i>
                    </div>
                    <span id="domainModalTitle">Add Domain</span>
                </h3>
                <button onclick="closeModal(\'modalDomain\')" class="w-10 h-10 rounded-full bg-[var(--input-bg)] text-[var(--text-muted)] hover:text-rose-400 flex items-center justify-center transition-colors"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <div class="p-10 space-y-8 flex-1 overflow-y-auto custom-scrollbar">
                <input type="hidden" id="domainId">
                <div class="grid grid-cols-2 gap-8">
                    <div>
                        <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest mb-3 ml-2">Domain Name</label>
                        <input type="text" id="domainField" placeholder="target.com" class="w-full input-premium rounded-2xl p-4 font-bold text-sm shadow-inner">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest mb-3 ml-2">Status</label>
                        <select id="domainStatus" class="w-full input-premium rounded-2xl p-4 font-bold text-sm shadow-inner">
                            <option value="active">Active</option>
                            <option value="new">New</option>
                            <option value="deactive">Deactive</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-8">
                    <div>
                        <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest mb-3 ml-2">Username</label>
                        <input type="text" id="domainUsername" placeholder="cpanel user" class="w-full input-premium rounded-2xl p-4 font-bold text-sm shadow-inner">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest mb-3 ml-2">Expiry</label>
                        <input type="text" id="domainExpiry" placeholder="YYYY-MM-DD" class="w-full input-premium rounded-2xl p-4 font-bold text-sm shadow-inner">
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest mb-3 ml-2">UAPI Token</label>
                    <input type="text" id="domainUapi" placeholder="uapi token" class="w-full input-premium rounded-2xl p-4 font-bold text-sm shadow-inner">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest mb-3 ml-2">cPanel Token</label>
                    <input type="text" id="domainCpanel" placeholder="cpanel api token" class="w-full input-premium rounded-2xl p-4 font-bold text-sm shadow-inner">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest mb-3 ml-2">Notes</label>
                    <textarea id="domainNotes" rows="3" placeholder="Vault notes" class="w-full input-premium rounded-2xl p-4 font-bold text-sm shadow-inner"></textarea>
                </div>
            </div>
            <div class="px-10 py-6 border-t border-[var(--glass-border)] flex justify-end gap-4 bg-[var(--glass-bg)]">
                <button class="px-8 py-3.5 bg-[var(--input-bg)] border border-[var(--glass-border)] rounded-2xl font-bold text-[var(--text-main)] text-sm hover:bg-[var(--glass-hover)] transition-all btn-animated" onclick="closeModal(\'modalDomain\')">Cancel</button>
                <button class="px-8 py-3.5 bg-brand-600 border border-brand-400/50 text-white rounded-2xl font-bold text-sm hover:bg-brand-500 transition-all btn-animated shadow-[0_0_20px_rgba(6,182,212,0.4)]" onclick="saveDomain()">Save Domain</button>
            </div>
        </div>
    </div>

    <div class="modal fixed inset-0 flex items-center justify-center p-4 z-[9999]" id="modalCloaking">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal(\'modalCloaking\')"></div>
        <div class="modal-content rounded-[2.5rem] w-full max-w-4xl relative z-10 overflow-hidden flex flex-col border border-[var(--glass-border)]">
            <div class="px-10 py-6 border-b border-[var(--glass-border)] flex justify-between items-center bg-[var(--glass-bg)] backdrop-blur-xl">
                <h3 class="font-extrabold text-2xl text-[var(--text-main)] flex items-center gap-4">
                    <div class="w-12 h-12 bg-accent-500/20 rounded-[1rem] flex items-center justify-center border border-accent-500/30 shadow-inner">
                        <i class="fa-solid fa-masks-theater text-accent-400"></i>
                    </div>
                    Cloaking
                </h3>
                <button onclick="closeModal(\'modalCloaking\')" class="w-10 h-10 rounded-full bg-[var(--input-bg)] text-[var(--text-muted)] hover:text-rose-400 flex items-center justify-center transition-colors"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <div class="p-10 space-y-8 flex-1 overflow-y-auto custom-scrollbar">
                <input type="hidden" id="cloakId">
                <div class="grid grid-cols-2 gap-8">
                    <div>
                        <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest mb-3 ml-2">Domain Target</label>
                        <input type="text" id="cloakDomain" placeholder="target.com" class="w-full input-premium rounded-2xl p-4 font-bold text-sm shadow-inner">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest mb-3 ml-2">Cloak Path</label>
                        <input type="text" id="cloakPath" placeholder="/seo-landing" class="w-full input-premium rounded-2xl p-4 font-bold text-sm shadow-inner">
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest mb-3 ml-2">Visibility Scope</label>
                    <select id="cloakType" class="w-full input-premium rounded-2xl p-4 font-bold text-sm shadow-inner appearance-none">
                        <option value="personal">Personal Scope</option>
                        <option value="global">Global Scope</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest mb-3 ml-2">Payload Schema (HTML/JS)</label>
                    <textarea id="cloakContent" class="w-full input-premium rounded-2xl p-5 font-mono text-xs h-56 shadow-inner text-accent-300 custom-scrollbar"></textarea>
                </div>
            </div>
            <div class="px-10 py-6 border-t border-[var(--glass-border)] bg-[var(--glass-bg)] flex justify-end gap-4 backdrop-blur-xl">
                <button class="px-8 py-3.5 bg-[var(--input-bg)] border border-[var(--glass-border)] rounded-2xl font-bold text-[var(--text-main)] text-sm hover:bg-[var(--glass-hover)] transition-all btn-animated" onclick="closeModal(\'modalCloaking\')">Cancel</button>
                <button class="px-8 py-3.5 bg-accent-600 border border-accent-400/50 text-white rounded-2xl font-bold text-sm hover:bg-accent-500 transition-all btn-animated shadow-[0_0_20px_rgba(139,92,246,0.4)]" onclick="saveCloaking()">Deploy Target</button>
            </div>
        </div>
    </div>

    <div class="modal fixed inset-0 flex items-center justify-center p-4 z-[9999]" id="modalUser">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal(\'modalUser\')"></div>
        <div class="modal-content rounded-[2.5rem] w-full max-w-sm relative z-10 overflow-hidden p-10 border-t-4 border-t-emerald-500 text-center">
            <div class="w-24 h-24 bg-emerald-500/20 rounded-[2rem] border border-emerald-500/30 flex items-center justify-center mx-auto mb-8 shadow-inner">
                <i class="fa-solid fa-user-shield text-5xl text-emerald-400"></i>
            </div>
            <h3 class="font-extrabold text-2xl text-[var(--text-main)] mb-6">Register Identity</h3>
            <div class="space-y-5">
                <input type="text" id="newUserName" autocomplete="off" placeholder="Node Username" class="w-full input-premium rounded-2xl p-4 font-bold text-sm text-center shadow-inner">
                <input type="password" id="newUserPass" autocomplete="new-password" placeholder="Passphrase" class="w-full input-premium rounded-2xl p-4 font-bold text-sm text-center shadow-inner">
                <select id="newUserRole" class="w-full input-premium rounded-2xl p-4 font-bold text-sm text-center shadow-inner appearance-none">
                    <option value="guest">Guest Level</option>
                    <option value="admin">Admin Level</option>
                    <option value="owner">Owner Level</option>
                </select>
            </div>
            <div class="mt-10 flex gap-4">
                <button class="flex-1 py-3.5 bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-main)] rounded-2xl text-sm font-bold hover:bg-[var(--glass-hover)] transition-all btn-animated" onclick="closeModal(\'modalUser\')">Cancel</button>
                <button class="flex-1 py-3.5 bg-emerald-600 border border-emerald-400/50 text-white rounded-2xl text-sm font-bold hover:bg-emerald-500 transition-all btn-animated shadow-[0_0_20px_rgba(16,185,129,0.4)]" onclick="saveUser()">Register</button>
            </div>
        </div>
    </div>

    <div class="modal fixed inset-0 flex items-center justify-center p-4 z-[9999]" id="modalDeleteUser">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal(\'modalDeleteUser\')"></div>
        <div class="modal-content rounded-[2.5rem] w-full max-w-sm relative z-10 overflow-hidden p-10 border-t-4 border-t-rose-500 text-center">
            <div class="w-24 h-24 bg-rose-500/20 rounded-[2rem] border border-rose-500/30 flex items-center justify-center mx-auto mb-8 shadow-inner">
                <i class="fa-solid fa-user-xmark text-5xl text-rose-400"></i>
            </div>
            <h3 class="font-extrabold text-2xl text-[var(--text-main)] mb-6">Purge Identity</h3>
            <div class="space-y-5">
                <input type="text" id="delTargetUser" class="w-full input-premium text-rose-400 bg-rose-500/5 rounded-2xl p-4 font-bold text-sm text-center shadow-inner border-rose-500/20" readonly>
                <select id="delMigrateTo" class="w-full input-premium rounded-2xl p-4 font-bold text-sm text-center shadow-inner appearance-none">
                    <option value="">-- Destroy All Data --</option>
                </select>
            </div>
            <div class="mt-10 flex gap-4">
                <button class="flex-1 py-3.5 bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-main)] rounded-2xl text-sm font-bold hover:bg-[var(--glass-hover)] transition-all btn-animated" onclick="closeModal(\'modalDeleteUser\')">Cancel</button>
                <button class="flex-1 py-3.5 bg-rose-600 border border-rose-400/50 text-white rounded-2xl text-sm font-bold hover:bg-rose-500 transition-all btn-animated shadow-[0_0_20px_rgba(244,63,94,0.4)]" onclick="executeDeleteUser()">Proceed</button>
            </div>
        </div>
    </div>

    <div class="modal fixed inset-0 flex items-center justify-center p-4 z-[9999]" id="modalFirewall">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal(\'modalFirewall\')"></div>
        <div class="modal-content rounded-[2.5rem] w-full max-w-sm relative z-10 overflow-hidden p-10 border-t-4 border-t-emerald-500 text-center">
            <div class="w-24 h-24 bg-emerald-500/20 rounded-[2rem] border border-emerald-500/30 flex items-center justify-center mx-auto mb-8 shadow-inner">
                <i class="fa-solid fa-shield-virus text-5xl text-emerald-400"></i>
            </div>
            <h3 class="font-extrabold text-2xl text-[var(--text-main)] mb-6">Whitelist IPv4/v6</h3>
            <div class="space-y-5">
                <input type="text" id="fwIP" autocomplete="off" placeholder="IP Address" class="w-full input-premium rounded-2xl p-4 font-bold text-sm text-center shadow-inner">
                <input type="text" id="fwNote" autocomplete="off" placeholder="Node Reference" class="w-full input-premium rounded-2xl p-4 font-bold text-sm text-center shadow-inner">
            </div>
            <div class="mt-10 flex gap-4">
                <button class="flex-1 py-3.5 bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-main)] rounded-2xl text-sm font-bold hover:bg-[var(--glass-hover)] transition-all btn-animated" onclick="closeModal(\'modalFirewall\')">Cancel</button>
                <button class="flex-1 py-3.5 bg-emerald-600 border border-emerald-400/50 text-white rounded-2xl text-sm font-bold hover:bg-emerald-500 transition-all btn-animated shadow-[0_0_20px_rgba(16,185,129,0.4)]" onclick="saveFirewallIP()">Authorize</button>
            </div>
        </div>
    </div>

<script>
    let currentEditorFile = \'\';
    let editorInstance = null;
    let globalFilesData = []; let globalNotesData = []; let globalCloakData = []; let globalUsers = []; let globalDomainsData = [];
    let currentPath = \'\'; 
    let currentAssetsPage = 1; const ASSETS_PER_PAGE = 100;
    const currentUser = \'<?= $_SESSION[\'emerald_user\'] ?>\';
    const API_MOD = { \'zip_file\': \'storage\', \'unzip_file\': \'storage\' };
    const __origFetch = window.fetch.bind(window);
    window.fetch = function(url, opts) {
        if (opts && opts.method === \'POST\' && opts.body && typeof opts.body.append === \'function\' && !opts.body.has(\'csrf\') && window.EM && window.EM.csrf) {
            opts.body.append(\'csrf\', window.EM.csrf);
        }
        return __origFetch(url, opts);
    };
    let clipboard = { action: \'\', files: [], sourcePath: \'\' };
    
    // Updated SweetAlert configurations for Glassmorphism
    const Toast = Swal.mixin({
        toast: true, position: \'bottom-end\', showConfirmButton: false, timer: 3000,
        background: \'var(--glass-card)\', color: \'var(--text-main)\', 
        customClass: { popup: \'border border-[var(--glass-border)] shadow-2xl rounded-2xl backdrop-blur-xl\' }
    });
    const swalDark = Swal.mixin({
        background: \'var(--glass-card)\', color: \'var(--text-main)\',
        customClass: {
            popup: \'border border-[var(--glass-border)] shadow-[0_0_50px_rgba(0,0,0,0.5)] rounded-[2.5rem] backdrop-blur-xl\',
            title: \'text-2xl font-extrabold text-[var(--text-main)] mt-4\',
            input: \'bg-[var(--input-bg)] border border-[var(--border-color)] text-[var(--text-main)] rounded-2xl p-4 text-center font-mono focus:border-brand-500 outline-none w-[85%] mx-auto\',
            confirmButton: \'bg-brand-600 text-white px-8 py-3 rounded-2xl text-sm font-bold hover:bg-brand-500 transition-all shadow-[0_0_20px_rgba(6,182,212,0.4)] mx-2\',
            cancelButton: \'bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-main)] px-8 py-3 rounded-2xl text-sm font-bold hover:bg-[var(--glass-hover)] transition-all mx-2\',
            actions: \'mt-8 mb-4\'
        },
        buttonsStyling: false
    });

    function getMyRole() {
        const roleEl = document.getElementById(\'headerRoleBadge\');
        return roleEl ? roleEl.innerText.trim().toLowerCase() : \'guest\';
    }

    document.addEventListener(\'DOMContentLoaded\', () => {
        const theme = localStorage.getItem(\'emerald_theme\');
        if (theme === \'light\') { document.documentElement.classList.remove(\'dark\'); document.getElementById(\'themeText\').innerText = \'Light Mode\'; document.getElementById(\'themeCircle\').style.transform = \'translateX(24px)\'; document.querySelector(\'.theme-toggle-btn\').classList.add(\'bg-emerald-400/50\'); document.querySelector(\'.theme-toggle-btn\').classList.remove(\'bg-slate-700/50\'); }

        const dropOverlay = document.getElementById(\'dropOverlay\');
        document.body.addEventListener(\'dragover\', function(e) {
            e.preventDefault();
            if(document.getElementById(\'view_files\').classList.contains(\'active\')){
                dropOverlay.classList.remove(\'hidden\'); dropOverlay.classList.add(\'flex\');
            }
        });
        document.body.addEventListener(\'dragleave\', function(e) {
            if (e.relatedTarget === null) {
                dropOverlay.classList.add(\'hidden\');
                dropOverlay.classList.remove(\'flex\');
            }
        });
        document.body.addEventListener(\'drop\', async (e) => { 
            e.preventDefault();
            dropOverlay.classList.add(\'hidden\'); dropOverlay.classList.remove(\'flex\');
            if(document.getElementById(\'view_files\').classList.contains(\'active\')){
                const items = e.dataTransfer.items;
                if(items && items.length > 0 && items[0].webkitGetAsEntry) {
                    processDropUpload(items);
                } else { handleStandardUpload(e.dataTransfer.files, false); }
            }
        });
        loadUsers(); 
        
        editorInstance = CodeMirror.fromTextArea(document.getElementById("codeEditor"), {
            lineNumbers: true, theme: "monokai", mode: "htmlmixed", 
            matchBrackets: true, autoCloseBrackets: true, lineWrapping: true,
            extraKeys: { "Ctrl-F": "findPersistent", "Ctrl-S": function(cm) { saveFileEditor(); } }
        });
        if(theme === \'light\') {
            editorInstance.setOption(\'theme\', \'default\');
        }

        document.addEventListener(\'keydown\', e => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === \'f\') {
                if (!document.getElementById(\'modalEditor\').classList.contains(\'active\') && !document.getElementById(\'modalPreviewAsset\').classList.contains(\'active\')) {
                    e.preventDefault(); document.getElementById(\'globalSearch\').focus();
                }
            }
        });

        const activeTab = \'<?= $active_menu ?>\';
        const titles = {\'dashboard\': \'System Overview\', \'files\': \'Storage Assets\', \'notes\': \'Containers\', \'domains\': \'Domain Vault\', \'cloaking\': \'SEO Cloaking\', \'users\': \'Identity Registry\', \'firewall\': \'Firewall & IPs\', \'monitor\': \'Resource Monitor\'};
        document.getElementById(\'pageTitle\').innerText = titles[activeTab];
        document.getElementById(\'breadcrumb\').style.opacity = (activeTab === \'files\') ? \'1\' : \'0\';

        if (activeTab === \'dashboard\') loadSysInfo();
        if (activeTab === \'files\') loadFiles(currentPath);
        if (activeTab === \'notes\') loadNotes();
        if (activeTab === \'cloaking\') loadCloaking();
        if (activeTab === \'users\') loadUsers();
        if (activeTab === \'firewall\') loadFirewall();
        if (activeTab === \'monitor\') loadMonitor();
        if (activeTab === \'domains\') loadDomains();
    });

    async function processDropUpload(items) {
        document.getElementById(\'modalUploadProgress\').classList.add(\'active\');
        document.getElementById(\'uploadProgressText\').innerText = \'Scanning directories...\';
        let currentUpload = 0;
        document.getElementById(\'uploadProgressText\').innerText = \'Uploading payloads...\';
        for (let i=0; i<items.length; i++) {
            const item = items[i].webkitGetAsEntry();
            if(item) await traverseFileTree(item, \'\', function(){
                currentUpload++;
                const pct = Math.min(100, Math.round((currentUpload / (currentUpload+2)) * 100));
                document.getElementById(\'uploadProgressBar\').style.width = pct + \'%\';
            });
        }
        document.getElementById(\'modalUploadProgress\').classList.remove(\'active\');
        document.getElementById(\'uploadProgressBar\').style.width = \'0%\';
        Toast.fire({ icon: \'success\', title: \'Upload Completed\' });
        loadFiles(currentPath);
    }

    async function traverseFileTree(item, path, callback = null) {
        if (item.isFile) {
            return new Promise((resolve) => {
                item.file(async (file) => {
                    const fd = new FormData(); fd.append(\'file\', file); fd.append(\'path\', currentPath); fd.append(\'relative_path\', path + file.name);
                    await fetch(\'index.php?api=storage&action=upload\', { method: \'POST\', body: fd });
                    if(callback) callback();
                    resolve();
                });
            });
        } else if (item.isDirectory) {
            let dirReader = item.createReader();
            return new Promise((resolve) => {
                dirReader.readEntries(async (entries) => {
                    for (let i=0; i<entries.length; i++) { await traverseFileTree(entries[i], path + item.name + "/", callback); }
                    resolve();
                });
             });
        }
    }

    async function handleStandardUpload(files, isFolderInput) {
        if (!files || files.length === 0) return;
        document.getElementById(\'modalUploadProgress\').classList.add(\'active\');
        for (let i = 0; i < files.length; i++) {
            document.getElementById(\'uploadProgressText\').innerText = `Uploading ${isFolderInput ? (files[i].webkitRelativePath || files[i].name) : files[i].name}...`;
            const fd = new FormData(); fd.append(\'file\', files[i]); fd.append(\'path\', currentPath);
            if(isFolderInput) fd.append(\'relative_path\', files[i].webkitRelativePath || files[i].name);
            await fetch(\'index.php?api=storage&action=upload\', { method: \'POST\', body: fd });
            document.getElementById(\'uploadProgressBar\').style.width = Math.round(((i+1)/files.length)*100) + \'%\';
        }
        document.getElementById(\'modalUploadProgress\').classList.remove(\'active\');
        document.getElementById(\'uploadProgressBar\').style.width = \'0%\';
        Toast.fire({ icon: \'success\', title: \'Upload completed\' }); loadFiles(currentPath);
    }

    function toggleTheme() {
        const body = document.documentElement;
        const text = document.getElementById(\'themeText\'); const circle = document.getElementById(\'themeCircle\'); const btn = document.querySelector(\'.theme-toggle-btn\');
        body.classList.toggle(\'dark\');
        if (!body.classList.contains(\'dark\')) {
            localStorage.setItem(\'emerald_theme\', \'light\'); text.innerText = \'Light Mode\';
            circle.style.transform = \'translateX(24px)\'; btn.classList.add(\'bg-emerald-400/50\'); btn.classList.remove(\'bg-slate-700/50\');
            if(editorInstance) editorInstance.setOption(\'theme\', \'default\');
        } else {
            localStorage.setItem(\'emerald_theme\', \'dark\');
            text.innerText = \'Dark Mode\'; circle.style.transform = \'translateX(0)\'; btn.classList.remove(\'bg-emerald-400/50\'); btn.classList.add(\'bg-slate-700/50\');
            if(editorInstance) editorInstance.setOption(\'theme\', \'monokai\');
        }
    }

    function stringToColor(str) { let hash = 0;
        for (let i = 0; i < str.length; i++) hash = str.charCodeAt(i) + ((hash << 5) - hash);
        return `hsl(${Math.abs(hash) % 360}, 70%, 65%)`; 
    }

    function closeModal(id) { document.getElementById(id).classList.remove(\'active\');
    }

    async function loadSysInfo() {
        const res = await fetch(\'index.php?api=dashboard&action=sys_info\').then(r=>r.json()).catch(e => { return {stats:{}, extended:{}, logs:[], activity:[]}; });
        if(!res.stats) return;
        
        if (res.extended) {
            document.getElementById(\'dashDisk\').innerText = `${res.extended.disk_free} / ${res.extended.disk_total}`;
            document.getElementById(\'dashSapi\').innerText = res.extended.php_sapi;
        }

        const logsList = document.getElementById(\'logsList\'); logsList.innerHTML = \'\';
        const logsData = Array.isArray(res.logs) ? res.logs : Object.values(res.logs || {});
        if(logsData.length > 0) {
            logsData.forEach(l => {
                if(!l || !l.time) return;
                const date = new Date(l.time * 1000).toLocaleString();
                const status = l.status === \'Success\' ? \'<span class="text-emerald-400 bg-emerald-500/20 px-2 py-1 rounded-md text-[9px] font-bold uppercase tracking-widest border border-emerald-500/30">SUCCESS</span>\' : \'<span class="text-rose-400 bg-rose-500/20 px-2 py-1 rounded-md text-[9px] font-bold uppercase tracking-widest border border-rose-500/30">FAILED</span>\';
                logsList.innerHTML += `<tr><td class="px-6 py-4 text-[var(--text-muted)] text-xs font-mono">${date}</td><td class="px-4 py-4 text-[var(--text-main)] font-bold text-sm"><i class="fa-solid fa-user-shield text-[var(--text-muted)] mr-2 text-xs"></i>${l.user}</td><td class="px-4 py-4 text-brand-400 font-mono text-xs">${l.ip}</td><td class="px-4 py-4 text-right">${status}</td></tr>`;
            });
        } else {
            logsList.innerHTML = `<tr><td colspan="4" class="px-6 py-8 text-center text-[var(--text-muted)] text-sm">No access logs recorded.</td></tr>`;
        }
        
        const activityList = document.getElementById(\'activityList\');
        if(activityList) {
            activityList.innerHTML = \'\';
            const activityData = Array.isArray(res.activity) ? res.activity : Object.values(res.activity || {});
            if (activityData.length > 0) {
                activityData.forEach(a => {
                    if(!a || !a.time) return;
                    const date = new Date(a.time * 1000).toLocaleString();
                    activityList.innerHTML += `<tr><td class="px-6 py-4 text-[var(--text-muted)] text-xs font-mono">${date}</td><td class="px-4 py-4 text-[var(--text-main)] font-bold text-sm"><i class="fa-solid fa-user-shield text-[var(--text-muted)] mr-2 text-xs"></i>${a.user}</td><td class="px-4 py-4 text-accent-400 font-mono text-xs">${a.detail}</td></tr>`;
                });
            } else {
                activityList.innerHTML = `<tr><td colspan="3" class="px-6 py-8 text-center text-[var(--text-muted)] text-sm">No activity recorded.</td></tr>`;
            }
        }
    }

    let authTarget = { action: \'\', id: \'\', path: \'\', extra: \'\' };
    function promptAuth(action, id, path = \'\', extra = \'\') {
        authTarget = { action, id, path, extra };
        swalDark.fire({
            title: \'Konfirmasi Eksekusi\',
            html: \'<p class="text-sm text-[var(--text-muted)] font-medium mb-4">Lanjutkan tindakan penghapusan/modifikasi pada data ini?</p>\',
            icon: \'warning\',
            showCancelButton: true,
            confirmButtonText: \'Ya, Eksekusi\',
            cancelButtonText: \'Batal\'
        }).then((result) => {
            if(result.isConfirmed) {
                const passField = document.getElementById(\'authPassword\');
                if(passField) passField.value = \'bypass\'; // Bypass visual legacy
                executeAuthorizedAction();
            }
        });
    }

    window.attemptAction = async function(action, filename, owner) {
        if(action === \'delete_file\') {
            promptAuth(\'delete_file\', filename, currentPath);
        } else if (action === \'zip_file\' || action === \'unzip_file\') {
            const fd = new FormData();
            fd.append(\'file\', filename); fd.append(\'path\', currentPath);
            const res = await fetch(\'index.php?api=\' + (API_MOD[action] || \'storage\') + \'&action=\' + action, { method: \'POST\', body: fd }).then(r=>r.json());
            if(res.status === \'success\') { Toast.fire({icon:\'success\', title: \'Action Executed\'}); loadFiles(currentPath); }
            else Toast.fire({icon:\'error\', title: res.message});
        }
    };

    window.downloadFolderAsZip = async function(folderName) {
        Toast.fire({icon:\'info\', title:\'Compressing directory...\'});
        const fd = new FormData();
        fd.append(\'file\', folderName); fd.append(\'path\', currentPath);
        const res = await fetch(\'index.php?api=storage&action=zip_file\', { method: \'POST\', body: fd }).then(r=>r.json());
        
        if(res.status === \'success\') {
            Toast.fire({icon:\'success\', title: \'Download starting...\'});
            loadFiles(currentPath);
            const zipName = folderName + \'.zip\';
            const fileRoute = currentPath ? `${currentPath}/${zipName}` : zipName;
            const link = document.createElement(\'a\');
            link.href = `/emerald_assets/${fileRoute}`;
            link.download = zipName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        } else {
            Toast.fire({icon:\'error\', title: res.message});
        }
    };

    async function executeAuthorizedAction() {
        const pass = document.getElementById(\'authPassword\').value;
        if(!pass) return Toast.fire({icon:\'error\', title:\'Passphrase empty\'});
        
        const fd = new FormData(); fd.append(\'auth_pass\', pass);
        if (authTarget.action === \'delete_file\') {
            fd.append(\'file\', authTarget.id); fd.append(\'path\', authTarget.path);
            const res = await fetch(\'index.php?api=storage&action=delete_file\', { method: \'POST\', body: fd }).then(r=>r.json());
            handleAuthResponse(res, () => loadFiles(currentPath));
        } else if (authTarget.action === \'multi_delete\') {
            fd.append(\'files\', JSON.stringify(authTarget.id));
            fd.append(\'path\', currentPath);
            const res = await fetch(\'index.php?api=storage&action=multi_delete\', { method: \'POST\', body: fd }).then(r=>r.json());
            handleAuthResponse(res, () => { loadFiles(currentPath); resetSelection(); });
        } else if (authTarget.action === \'delete_note\') {
            fd.append(\'id\', authTarget.id);
            const res = await fetch(\'index.php?api=notes&action=delete_note\', { method: \'POST\', body: fd }).then(r=>r.json());
            handleAuthResponse(res, () => loadNotes());
        } else if (authTarget.action === \'delete_cloaking\') {
            fd.append(\'id\', authTarget.id);
            const res = await fetch(\'index.php?api=cloaking&action=delete_cloaking\', { method: \'POST\', body: fd }).then(r=>r.json());
            handleAuthResponse(res, () => loadCloaking());
        } else if (authTarget.action === \'delete_user\') {
            fd.append(\'target_user\', authTarget.id);
            fd.append(\'migrate_to\', authTarget.extra);
            const res = await fetch(\'index.php?api=users&action=delete_user\', { method: \'POST\', body: fd }).then(r=>r.json());
            handleAuthResponse(res, () => { closeModal(\'modalDeleteUser\'); loadUsers(); });
        } else if (authTarget.action === \'delete_firewall\') {
            fd.append(\'id\', authTarget.id);
            const res = await fetch(\'index.php?api=firewall&action=delete_firewall\', { method: \'POST\', body: fd }).then(r=>r.json());
            handleAuthResponse(res, () => loadFirewall());
        } else if (authTarget.action === \'kill_process\') {
            fd.append(\'pid\', authTarget.id);
            const res = await fetch(\'index.php?api=monitor&action=kill_process\', { method: \'POST\', body: fd }).then(r=>r.json());
            handleAuthResponse(res, () => loadMonitor());
        } else if (authTarget.action === \'delete_domain\') {
            fd.append(\'id\', authTarget.id);
            const res = await fetch(\'index.php?api=domains&action=delete_domain\', { method: \'POST\', body: fd }).then(r=>r.json());
            handleAuthResponse(res, () => loadDomains());
        }
    }

    function handleAuthResponse(res, successCallback) {
        if(res.status === \'success\') { closeModal(\'modalAuthPrompt\');
            Toast.fire({icon:\'success\', title:\'Action Executed\'}); successCallback(); } 
        else { swalDark.fire({icon:\'error\', title:\'Denied\', html:`<p class="text-[var(--text-muted)] text-sm">${res.message}</p>`});
        }
    }

    function openProfileModal() {
        document.getElementById(\'profUsername\').value = currentUser;
        document.getElementById(\'profPassword\').value = \'\';
        document.getElementById(\'profSecQ\').value = \'\'; document.getElementById(\'profSecA\').value = \'\';
        document.getElementById(\'profileAvatarPreview\').src = document.getElementById(\'sidebarAvatar\').src;
        document.getElementById(\'modalProfile\').classList.add(\'active\');
    }

    function openAvatarZoom(src) {
        document.getElementById(\'avatarZoomImg\').src = src; document.getElementById(\'modalAvatarZoom\').classList.add(\'active\');
    }

    function handleAvatarUpload(event) {
        const file = event.target.files[0]; if(!file) return;
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = new Image();
            img.onload = function() {
                const canvas = document.createElement(\'canvas\');
                const MAX_WIDTH = 256; const MAX_HEIGHT = 256;
                let width = img.width; let height = img.height;
                if (width > height) { if (width > MAX_WIDTH) { height *= MAX_WIDTH / width; width = MAX_WIDTH;
                } } else { if (height > MAX_HEIGHT) { width *= MAX_HEIGHT / height; height = MAX_HEIGHT;
                } }
                canvas.width = width;
                canvas.height = height; const ctx = canvas.getContext(\'2d\'); ctx.drawImage(img, 0, 0, width, height);
                const dataUrl = canvas.toDataURL(\'image/jpeg\', 0.8);
                document.getElementById(\'profileAvatarPreview\').src = dataUrl;
                document.getElementById(\'profAvatarBase64\').value = dataUrl;
            }
            img.src = e.target.result;
        }
        reader.readAsDataURL(file);
    }
    
    async function updateProfile() {
        const fd = new FormData();
        fd.append(\'username\', document.getElementById(\'profUsername\').value); fd.append(\'password\', document.getElementById(\'profPassword\').value);
        fd.append(\'avatar\', document.getElementById(\'profAvatarBase64\').value); fd.append(\'sec_q\', document.getElementById(\'profSecQ\').value); fd.append(\'sec_a\', document.getElementById(\'profSecA\').value);
        const res = await fetch(\'index.php?api=users&action=update_profile\', { method: \'POST\', body: fd }).then(r=>r.json());
        if(res.status === \'success\') {
            Toast.fire({icon:\'success\', title:\'Identity Updated\'});
            if(res.new_user !== currentUser) window.location.reload(); 
            closeModal(\'modalProfile\'); loadUsers(); 
        } else { Toast.fire({icon:\'error\', title:res.message});
        }
    }

    // --- BULK FILE ACTIONS ---
    function toggleSelectAll(source) {
        const checkboxes = document.querySelectorAll(\'.file-sel\');
        checkboxes.forEach(cb => {
            if (cb.closest(\'tr\').style.display !== \'none\') { cb.checked = source.checked; }
        });
        updateBulkToolbar();
    }

    function updateBulkToolbar() {
        const checked = document.querySelectorAll(\'.file-sel:checked\');
        const toolbar = document.getElementById(\'bulkToolbar\');
        const pasteToolbar = document.getElementById(\'pasteToolbar\');
        
        if (clipboard.files.length > 0) {
            pasteToolbar.classList.remove(\'hidden\');
            pasteToolbar.classList.add(\'flex\');
            document.getElementById(\'pasteInfo\').innerText = `${clipboard.files.length} items to ${clipboard.action}`;
        } else {
            pasteToolbar.classList.add(\'hidden\');
            pasteToolbar.classList.remove(\'flex\');
        }

        if (checked.length > 0) {
            toolbar.classList.remove(\'hidden\');
            toolbar.classList.add(\'flex\');
            document.getElementById(\'selCount\').innerText = checked.length;
        } else {
            toolbar.classList.add(\'hidden\'); toolbar.classList.remove(\'flex\');
        }
    }

    function bulkAction(action) {
        const checked = Array.from(document.querySelectorAll(\'.file-sel:checked\')).map(cb => cb.value);
        if(checked.length === 0) return;
        
        if (action === \'delete\') {
            swalDark.fire({
                title: \'Purge Selected?\', html: `<p class="text-[var(--text-muted)] text-sm">Delete ${checked.length} selected items permanently?</p>`,
                showCancelButton: true, confirmButtonText: \'Yes, Purge\'
            }).then((result) => {
                if(result.isConfirmed) { promptAuth(\'multi_delete\', checked, currentPath); }
            });
        } else {
            clipboard = { action: action, files: checked, sourcePath: currentPath };
            resetSelection();
            Toast.fire({ icon: \'info\', title: `${checked.length} items ready to ${action}` });
        }
    }

    async function executePaste() {
        if(clipboard.files.length === 0) return;
        Toast.fire({ icon: \'info\', title: `Processing ${clipboard.action}...` });
        const fd = new FormData();
        fd.append(\'files\', JSON.stringify(clipboard.files)); fd.append(\'source_path\', clipboard.sourcePath); fd.append(\'target_path\', currentPath); fd.append(\'mode\', clipboard.action);
        const res = await fetch(\'index.php?api=storage&action=paste_files\', { method: \'POST\', body: fd }).then(r=>r.json());
        if(res.status === \'success\') {
            if(clipboard.action === \'cut\') clipboard = { action: \'\', files: [], sourcePath: \'\' };
            Toast.fire({ icon: \'success\', title: \'Action Successful\' });
            loadFiles(currentPath); resetSelection();
        } else { Toast.fire({ icon: \'error\', title: res.message });
        }
    }

    function cancelPaste() { clipboard = { action: \'\', files: [], sourcePath: \'\' };
        updateBulkToolbar(); }

    function resetSelection() {
        document.querySelectorAll(\'.file-sel\').forEach(cb => cb.checked = false);
        const sa = document.getElementById(\'selectAllCheckbox\'); if(sa) sa.checked = false;
        updateBulkToolbar();
    }

    async function loadFiles(path = \'\') {
        currentPath = path;
        document.getElementById(\'assetSearchFilter\').value = \'\';
        const res = await fetch(`index.php?api=storage&action=list_files&path=${path}`).then(r => r.json()).catch(e => { return {files:[]}; });
        globalFilesData = res.files;
        const breadcrumb = document.getElementById(\'breadcrumb\'); const btnUp = document.getElementById(\'btnNavUp\');
        if(path) { breadcrumb.innerHTML = `<i class="fa-solid fa-layer-group"></i> / root / <span class="text-brand-400 font-bold">${path}</span>`;
            btnUp.style.display = \'flex\'; } 
        else { breadcrumb.innerHTML = `<i class="fa-solid fa-layer-group"></i> / root`;
            btnUp.style.display = \'none\'; }
        renderFiles(res.files, 1); resetSelection();
    }

    function navigateUp() { if(!currentPath) return; let parts = currentPath.split(\'/\'); parts.pop(); loadFiles(parts.join(\'/\'));
    }
    function navigateDown(folder) { let newPath = currentPath ? currentPath + \'/\' + folder : folder; loadFiles(newPath);
    }

    function getExtIcon(ext) {
        ext = ext.toLowerCase();
        if(ext === \'zip\') return \'<div class="w-10 h-10 rounded-xl bg-amber-500/20 flex items-center justify-center border border-amber-500/30 shadow-inner"><i class="fa-solid fa-file-zipper text-amber-400 text-lg"></i></div>\';
        if([\'php\',\'html\',\'css\',\'js\',\'json\'].includes(ext)) return \'<div class="w-10 h-10 rounded-xl bg-brand-500/20 flex items-center justify-center border border-brand-500/30 shadow-inner"><i class="fa-solid fa-file-code text-brand-400 text-lg"></i></div>\';
        if([\'png\',\'jpg\',\'jpeg\',\'gif\'].includes(ext)) return \'<div class="w-10 h-10 rounded-xl bg-emerald-500/20 flex items-center justify-center border border-emerald-500/30 shadow-inner"><i class="fa-solid fa-image text-emerald-400 text-lg"></i></div>\';
        if(ext === \'txt\') return \'<div class="w-10 h-10 rounded-xl bg-[var(--input-bg)] flex items-center justify-center border border-[var(--glass-border)] shadow-inner"><i class="fa-solid fa-file-lines text-[var(--text-muted)] text-lg"></i></div>\';
        return \'<div class="w-10 h-10 rounded-xl bg-[var(--input-bg)] flex items-center justify-center border border-[var(--glass-border)] shadow-inner"><i class="fa-solid fa-file text-[var(--text-muted)] text-lg"></i></div>\';
    }

    function filterAssets() {
        const q = document.getElementById(\'assetSearchFilter\').value.toLowerCase();
        const trs = document.getElementById(\'filesList\').getElementsByTagName(\'tr\');
        Array.from(trs).forEach(tr => {
            const name = tr.getAttribute(\'data-filename\') || \'\';
            tr.style.display = name.toLowerCase().includes(q) ? \'\' : \'none\';
        });
    }

    async function promptRename(oldName) {
        const { value: newName } = await swalDark.fire({
            title: \'Rename Asset\',
            html: `<p class="text-sm text-[var(--text-muted)] mb-4">Enter new name for <b class="text-[var(--text-main)]">${oldName}</b>.</p>`,
            input: \'text\',
            inputValue: oldName,
            showCancelButton: true
        });
        
        if (newName && newName !== oldName) {
            const fd = new FormData();
            fd.append(\'old_name\', oldName); fd.append(\'new_name\', newName); fd.append(\'path\', currentPath);
            const res = await fetch(\'index.php?api=storage&action=rename_file\', { method: \'POST\', body: fd }).then(r => r.json());
            if (res.status === \'success\') { Toast.fire({icon: \'success\', title: \'Asset Renamed\'}); loadFiles(currentPath);
            } 
            else { Toast.fire({icon: \'error\', title: res.message});
            }
        }
    }

    function renderFiles(files, page = 1) {
        currentAssetsPage = page;
        const tbody = document.getElementById(\'filesList\'); tbody.innerHTML = \'\';
        
        const sortedFiles = files.sort((a,b) => b.is_dir - a.is_dir || a.name.localeCompare(b.name));
        const totalItems = sortedFiles.length;
        const totalPages = Math.ceil(totalItems / ASSETS_PER_PAGE);
        const startIdx = (page - 1) * ASSETS_PER_PAGE;
        const endIdx = startIdx + ASSETS_PER_PAGE;
        const paginatedFiles = sortedFiles.slice(startIdx, endIdx);
        paginatedFiles.forEach(f => {
            const icon = f.is_dir ? \'<div class="w-10 h-10 rounded-xl bg-brand-500/20 flex items-center justify-center border border-brand-500/30 shadow-inner"><i class="fa-solid fa-folder text-brand-400 text-lg"></i></div>\' : getExtIcon(f.ext);
            const baseUrl = window.location.origin; const fileRoute = currentPath ? `${currentPath}/${f.link_name}` : f.link_name;
            const cleanUrl = `${baseUrl}/view/${fileRoute}`;
            
            const color = stringToColor(f.owner);
 
            const trClick = `onclick="if(${f.is_dir}) { navigateDown(\'${f.name}\') } else { previewFile(\'${f.name}\') }"`;
            const wgetCmd = `wget ${cleanUrl} -O ${f.name}`;

            tbody.innerHTML += `
                <tr class="group cursor-pointer hover:bg-[var(--glass-hover)] transition-all" data-filename="${f.name}" ${trClick}>
                    <td class="px-6 py-4 text-center" onclick="event.stopPropagation()">
                        <input type="checkbox" class="file-sel file-checkbox" value="${f.name}" onchange="updateBulkToolbar()">
                    </td>
                    <td class="px-4 py-4">
                        <div class="flex items-center gap-4">
                            ${icon}
                            <div>
                                <div class="font-bold text-[var(--text-main)] tracking-tight text-sm group-hover:text-brand-400 transition-colors">${f.name}</div>
                                ${!f.is_dir ? `<div class="text-[10px] text-[var(--text-muted)] font-mono mt-1 truncate max-w-[250px]" title="${cleanUrl}">${cleanUrl}</div>` : `<div class="text-[10px] text-[var(--text-muted)] font-mono mt-1 uppercase tracking-widest">Directory</div>`}
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 font-mono text-[var(--text-muted)] font-bold uppercase tracking-widest text-[10px]">${f.ext}</td>
                    <td class="px-6 py-4 text-[var(--text-muted)] text-sm">${f.size}</td>
                    <td class="px-6 py-4 text-[var(--text-muted)] font-mono text-xs">${f.modified}</td>
                    <td class="px-6 py-4" onclick="event.stopPropagation()">
                        <span class="px-3 py-1.5 rounded-md text-[9px] font-extrabold uppercase tracking-widest border bg-[var(--input-bg)] shadow-sm" style="color: ${color}; border-color: ${color}40;">${f.owner}</span>
                    </td>
                    <td class="px-6 py-4 text-right space-x-2 whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity" onclick="event.stopPropagation()">
                        ${(!f.is_dir && f.ext === \'zip\') ? `<button onclick="attemptAction(\'unzip_file\', \'${f.name}\', \'${f.owner}\')" class="w-9 h-9 rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-amber-400 hover:text-white hover:bg-amber-500 hover:border-amber-400 transition-all shadow-sm" title="Extract"><i class="fa-solid fa-box-open"></i></button>` : \'\'}
                        ${(!f.is_dir && f.ext !== \'zip\') ? `<button onclick="attemptAction(\'zip_file\', \'${f.name}\', \'${f.owner}\')" class="w-9 h-9 rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-muted)] hover:text-amber-400 hover:border-amber-400/50 hover:bg-amber-500/10 transition-all shadow-sm" title="Compress"><i class="fa-solid fa-file-zipper"></i></button>` : \'\'}
                        ${f.is_dir ? `<button onclick="attemptAction(\'zip_file\', \'${f.name}\', \'${f.owner}\')" class="w-9 h-9 rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-muted)] hover:text-amber-400 hover:border-amber-400/50 hover:bg-amber-500/10 transition-all shadow-sm" title="Compress"><i class="fa-solid fa-file-zipper"></i></button>` : \'\'}

                        ${!f.is_dir ? `<button onclick="copyToClipboard(\'${wgetCmd.replace(/\'/g,"\\\\\'").replace(/"/g,"&quot;")}\')" class="w-9 h-9 rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-brand-400 hover:text-white hover:bg-brand-500 hover:border-brand-400 transition-all shadow-sm" title="Copy Wget"><i class="fa-solid fa-terminal"></i></button>` : \'\'}
                        ${!f.is_dir ? `<a href="${cleanUrl}" target="_blank" class="w-9 h-9 inline-flex items-center justify-center rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-emerald-400 hover:text-white hover:bg-emerald-500 hover:border-emerald-400 transition-all shadow-sm" title="Open Link"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>` : \'\'}
                        ${!f.is_dir ? `<a href="/emerald_assets/${currentPath ? currentPath+\'/\' : \'\'}${f.name}" download class="w-9 h-9 inline-flex items-center justify-center rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-muted)] hover:text-[var(--text-main)] hover:bg-[var(--glass-hover)] transition-all shadow-sm" title="Download File"><i class="fa-solid fa-download"></i></a>` : \'\'}
                        ${f.is_dir ? `<button onclick="downloadFolderAsZip(\'${f.name}\')" class="w-9 h-9 rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-emerald-400 hover:text-white hover:bg-emerald-500 hover:border-emerald-400 transition-all shadow-sm" title="Download Folder as ZIP"><i class="fa-solid fa-cloud-arrow-down"></i></button>` : \'\'}
                        <button onclick="promptRename(\'${f.name}\')" class="w-9 h-9 rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-muted)] hover:text-blue-400 hover:border-blue-400/50 hover:bg-blue-500/10 transition-all shadow-sm" title="Rename"><i class="fa-solid fa-pen"></i></button>
                        <button onclick="attemptAction(\'delete_file\', \'${f.name}\', \'${f.owner}\')" class="w-9 h-9 rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-muted)] hover:text-rose-400 hover:border-rose-400/50 hover:bg-rose-500/10 transition-all shadow-sm" title="Erase"><i class="fa-solid fa-trash"></i></button>
                        <button onclick="attemptAction(\'delete_file\', \'${f.name}\', \'${f.owner}\')" class="w-9 h-9 rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-muted)] hover:text-rose-400 hover:border-rose-400/50 hover:bg-rose-500/10 transition-all shadow-sm" title="Erase"><i class="fa-solid fa-trash"></i></button>
                    </td>
                </tr>
            `;
        });

        let pagHtml = `<div class="flex items-center justify-between"><span class="text-xs text-[var(--text-muted)] font-bold tracking-wide">Showing ${startIdx + 1} to ${Math.min(endIdx, totalItems)} of ${totalItems} Assets</span><div class="flex gap-2">`;
        if (page > 1) pagHtml += `<button class="px-5 py-2.5 bg-[var(--input-bg)] border border-[var(--glass-border)] rounded-xl text-xs font-bold hover:bg-[var(--glass-hover)] transition-all btn-animated shadow-sm" onclick="renderFiles(globalFilesData, ${page - 1})">Prev</button>`;
        if (page < totalPages) pagHtml += `<button class="px-5 py-2.5 bg-[var(--input-bg)] border border-[var(--glass-border)] rounded-xl text-xs font-bold hover:bg-[var(--glass-hover)] transition-all btn-animated shadow-sm" onclick="renderFiles(globalFilesData, ${page + 1})">Next</button>`;
        pagHtml += `</div></div>`;
        document.getElementById(\'assetsPagination\').innerHTML = pagHtml;
    }

    async function promptCreateFolder() {
        const { value: folderName } = await swalDark.fire({ 
            title: \'New Directory\', html: \'<p class="text-sm text-[var(--text-muted)] mb-4">Enter a name for the new directory.</p>\',
            input: \'text\', inputPlaceholder: \'e.g., project_assets\', showCancelButton: true 
        });
        if (folderName) {
            const fd = new FormData();
            fd.append(\'folder\', folderName); fd.append(\'path\', currentPath);
            const res = await fetch(\'index.php?api=storage&action=create_folder\', { method: \'POST\', body: fd }).then(r => r.json());
            if(res.status === \'success\') loadFiles(currentPath); else Toast.fire({icon:\'error\', title:res.message});
        }
    }

    async function promptCreateFile() {
        const { value: fileName } = await swalDark.fire({ 
            title: \'New File\', html: \'<p class="text-sm text-[var(--text-muted)] mb-4">Enter filename with extension.</p>\',
            input: \'text\', inputPlaceholder: \'script.php\', showCancelButton: true 
        });
        if (fileName) {
            const fd = new FormData();
            fd.append(\'file\', fileName); fd.append(\'path\', currentPath);
            const res = await fetch(\'index.php?api=storage&action=create_file\', { method: \'POST\', body: fd }).then(r => r.json());
            if(res.status === \'success\') { loadFiles(currentPath); editFile(fileName); } else Toast.fire({icon:\'error\', title:res.message});
        }
    }

    window.previewFile = async function(filename) {
        const ext = filename.split(\'.\').pop().toLowerCase();
        const fileRoute = currentPath ? `${currentPath}/${filename}` : filename;
        const cleanUrl = `${window.location.origin}/emerald_assets/${fileRoute}`;

        document.getElementById(\'previewTitle\').innerText = filename;
        const contentArea = document.getElementById(\'previewContentArea\');
        contentArea.innerHTML = \'<div class="absolute inset-0 flex items-center justify-center text-white"><i class="fa-solid fa-circle-notch fa-spin text-5xl opacity-50"></i></div>\';
        document.getElementById(\'modalPreviewAsset\').classList.add(\'active\');

        const btnEdit = document.getElementById(\'btnEditPreview\');
        btnEdit.onclick = function() { closeModal(\'modalPreviewAsset\'); editFile(filename); };

        if ([\'png\', \'jpg\', \'jpeg\', \'gif\', \'webp\', \'svg\', \'ico\'].includes(ext)) {
            contentArea.innerHTML = `<img src="${cleanUrl}" class="max-w-full max-h-full m-auto object-contain shadow-2xl rounded-lg border border-[var(--glass-border)]">`;
        } else if ([\'mp4\', \'webm\', \'ogg\'].includes(ext)) {
            contentArea.innerHTML = `<video controls src="${cleanUrl}" class="max-w-full max-h-full m-auto shadow-2xl rounded-lg border border-[var(--glass-border)]"></video>`;
        } else {
            const fd = new FormData();
            fd.append(\'file\', filename); fd.append(\'path\', currentPath);
            const res = await fetch(\'index.php?api=storage&action=read_file\', { method: \'POST\', body: fd }).then(r => r.json());
            if (res.status === \'success\') {
                const escaped = res.content.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
                contentArea.innerHTML = `<pre class="text-gray-300 font-mono text-sm w-full h-full text-left bg-transparent p-8 whitespace-pre-wrap overflow-auto custom-scrollbar select-all cursor-text">${escaped}</pre>`;
            } else {
                contentArea.innerHTML = `<div class="absolute inset-0 flex items-center justify-center text-rose-400 font-bold text-lg"><i class="fa-solid fa-triangle-exclamation mr-3 text-2xl"></i> Error loading preview</div>`;
            }
        }
    };
    
    async function editFile(filename) {
        const fd = new FormData(); fd.append(\'file\', filename); fd.append(\'path\', currentPath);
        const res = await fetch(\'index.php?api=storage&action=read_file\', { method: \'POST\', body: fd }).then(r => r.json());
        if (res.status === \'success\') {
            currentEditorFile = filename;
            const ext = filename.split(\'.\').pop().toLowerCase();
            document.getElementById(\'editorTitle\').innerText = filename;
            document.getElementById(\'editorModified\').innerText = res.modified;
            document.getElementById(\'editorSize\').innerText = res.size;
            
            let mode = "htmlmixed";
            if (ext === \'php\') mode = "php"; else if (ext === \'js\') mode = "javascript";
            else if (ext === \'css\') mode = "css";
            editorInstance.setOption("mode", mode);
            editorInstance.setValue(res.content);
            document.getElementById(\'modalEditor\').classList.add(\'active\');
            setTimeout(() => { editorInstance.refresh(); }, 150);
        }
    }

    async function saveFileEditor() {
        const fd = new FormData();
        fd.append(\'file\', currentEditorFile); fd.append(\'path\', currentPath); fd.append(\'content\', editorInstance.getValue());
        await fetch(\'index.php?api=storage&action=save_file\', { method: \'POST\', body: fd });
        Toast.fire({ icon: \'success\', title: \'Source synchronized\' }); closeModal(\'modalEditor\'); loadFiles(currentPath);
    }

    // --- CONTAINERS ---
    async function loadNotes() {
        const res = await fetch(\'index.php?api=notes&action=list_notes\').then(r => r.json()).catch(e => { return []; });
        globalNotesData = Array.isArray(res) ? res : ((res && res.notes) || []); renderNotes(globalNotesData);
    }

    function renderNotes(notes) {
        const listArea = document.getElementById(\'notesListArea\');
        listArea.innerHTML = \'\';
        notes.sort((a,b) => b.timestamp - a.timestamp).forEach(note => {
            const avatarUrl = note.avatar || `https://ui-avatars.com/api/?name=${note.owner}&background=0ea5e9&color=fff&rounded=true&bold=true`;
            const date = new Date(note.timestamp * 1000).toLocaleString();
            
            let data = {}; try { data = JSON.parse(note.data); } catch(e){}
            let statusColor = \'bg-emerald-400 shadow-[0_0_10px_rgba(52,211,153,0.5)]\'; let statusText = \'ACTIVE\';
   
            if(data.status === \'new\') { statusColor = \'bg-slate-400 shadow-[0_0_10px_rgba(148,163,184,0.5)]\'; statusText = \'NEW\'; }
            else if(data.status === \'deactive\') { statusColor = \'bg-rose-500 shadow-[0_0_10px_rgba(244,63,94,0.5)]\'; statusText = \'DEACTIVE\'; }

            listArea.innerHTML += `
                <div class="card-premium p-6 flex items-center cursor-pointer group" onclick="viewContainer(\'${note.id}\')">
                    <div class="absolute top-5 right-5 flex items-center gap-2 bg-[var(--input-bg)] px-3 py-1 rounded-lg border border-[var(--glass-border)] backdrop-blur-md">
                        <div class="w-2.5 h-2.5 rounded-full ${statusColor}"></div>
                        <span class="text-[9px] font-extrabold text-[var(--text-muted)] tracking-widest">${statusText}</span>
                    </div>
            
                    <img src="${avatarUrl}" class="w-14 h-14 rounded-full border-2 border-[var(--glass-border)] object-cover shadow-[0_0_15px_rgba(255,255,255,0.05)] mr-5">
                    <div class="flex-1 overflow-hidden pr-20">
                        <h3 class="font-extrabold text-[var(--text-main)] text-lg tracking-tight truncate group-hover:text-brand-400 transition-colors">${note.title}</h3>
                        <p class="text-[10px] text-[var(--text-muted)] font-mono mt-1 uppercase tracking-widest"><i class="fa-regular fa-clock mr-1.5"></i> ${date}</p>
                    </div>
                </div>`;
        });
    }

    window.viewContainer = function(id) {
        const note = globalNotesData.find(n => n.id === id);
        if(!note) return;
        
        let data = { auth: {host:\'\', user:\'\', pass:\'\', dir:\'\'}, list: \'\' };
        try { data = JSON.parse(note.data);
        } catch(e){}

        document.getElementById(\'viewContainerTitle\').innerText = note.title;
        document.getElementById(\'viewContainerOwner\').innerHTML = `<i class="fa-solid fa-user-shield mr-1.5"></i> ${note.owner}`;
        document.getElementById(\'viewContainerAvatar\').src = note.avatar || `https://ui-avatars.com/api/?name=${note.owner}&background=0ea5e9&color=fff&rounded=true&bold=true`;
        
        document.getElementById(\'viewContainerActions\').innerHTML = `
            <button onclick="closeModal(\'modalViewContainer\'); editContainer(\'${note.id}\')" class="bg-[var(--input-bg)] border border-brand-500/40 text-brand-400 px-5 py-2.5 rounded-2xl text-sm font-bold hover:bg-brand-500 hover:text-white transition-all btn-animated shadow-sm"><i class="fa-solid fa-pen mr-2"></i>Edit</button>
            <button onclick="closeModal(\'modalViewContainer\'); promptAuth(\'delete_note\', \'${note.id}\')" class="bg-[var(--input-bg)] border border-rose-500/40 text-rose-400 px-5 py-2.5 rounded-2xl text-sm font-bold hover:bg-rose-500 hover:text-white transition-all btn-animated shadow-sm"><i class="fa-solid fa-trash mr-2"></i>Delete</button>
        `;
        let authHTML = \'\';
        if(data.auth.host || data.auth.user || data.auth.dir) {
            authHTML = `
                <div class="bg-[var(--glass-card)] border border-[var(--glass-border)] rounded-[2rem] p-6 shadow-sm">
                    <div class="flex items-center gap-3 mb-4 border-b border-[var(--glass-border)] pb-3">
                        <i class="fa-solid fa-server text-accent-400 text-lg"></i><span class="text-sm font-bold text-[var(--text-main)] tracking-widest uppercase">Auth Config</span>
                    </div>
                    <ul class="text-sm font-mono text-[var(--text-muted)] space-y-3">
                        <li class="flex items-center bg-[var(--input-bg)] p-3 rounded-2xl border border-[var(--glass-border)] shadow-inner"><span class="text-[var(--text-muted)] font-bold w-20 text-xs">HOST</span><span class="cursor-pointer truncate text-accent-300 font-bold hover:underline hover:opacity-80 transition-all" onclick="copyToClipboard(this.innerText)" title="Click to copy">${data.auth.host || \'-\'}</span></li>
                        <li class="flex items-center bg-[var(--input-bg)] p-3 rounded-2xl border border-[var(--glass-border)] shadow-inner"><span class="text-[var(--text-muted)] font-bold w-20 text-xs">USER</span><span class="cursor-pointer truncate text-brand-300 font-bold hover:underline hover:opacity-80 transition-all" onclick="copyToClipboard(this.innerText)" title="Click to copy">${data.auth.user || \'-\'}</span></li>
                        <li class="flex items-center bg-[var(--input-bg)] p-3 rounded-2xl border border-[var(--glass-border)] shadow-inner"><span class="text-[var(--text-muted)] font-bold w-20 text-xs">PASS</span><span class="cursor-pointer truncate text-rose-400 font-bold hover:underline hover:opacity-80 transition-all" onclick="copyToClipboard(this.innerText)" title="Click to copy">${data.auth.pass || \'-\'}</span></li>
                        <li class="flex items-center bg-[var(--input-bg)] p-3 rounded-2xl border border-[var(--glass-border)] shadow-inner"><span class="text-[var(--text-muted)] font-bold w-20 text-xs">DIR</span><span class="cursor-pointer truncate text-emerald-400 font-bold hover:underline hover:opacity-80 transition-all" onclick="copyToClipboard(this.innerText)" title="Click to copy">${data.auth.dir || \'-\'}</span></li>
                    </ul>
                </div>
            `;
        }

        let listHTML = \'\'; let gsocketHTML = \'\';
        if(data.list && data.list.trim() !== \'\') {
            const lines = data.list.split(\'\\n\');
            lines.forEach(line => {
                let cl = line.trim(); if(!cl) return;
                if(cl.startsWith(\'http://\') || cl.startsWith(\'https://\')) {
                    listHTML += `<div class="flex items-center gap-4 py-3 border-b border-[var(--glass-border)] last:border-0 hover:bg-[var(--glass-hover)] transition-colors px-4 rounded-xl group"><a href="${cl}" target="_blank" class="text-sm truncate text-emerald-400 hover:text-emerald-300 font-mono flex-1 transition-colors hover:underline">${cl}</a><i class="fa-solid fa-arrow-up-right-from-square text-emerald-400 text-xs opacity-0 group-hover:opacity-100 transition-opacity"></i></div>`;
       
                } else if(cl.includes(\'gs-netcat\') || cl.startsWith(\'S=\')) {
                    gsocketHTML += `<div class="flex items-center gap-4 py-3 border-b border-[var(--glass-border)] last:border-0 hover:bg-[var(--glass-hover)] transition-colors px-4 rounded-xl"><i class="fa-solid fa-terminal text-brand-400 text-xs"></i><span class="text-sm truncate text-brand-300 font-mono flex-1 cursor-pointer" onclick="copyToClipboard(\'${cl.replace(/\'/g,"\\\\\'").replace(/"/g,"&quot;")}\')">${cl}</span></div>`;
                } else { 
                    listHTML += `<div class="flex items-center gap-4 py-3 border-b border-[var(--glass-border)] last:border-0 hover:bg-[var(--glass-hover)] transition-colors px-4 rounded-xl"><i class="fa-solid fa-align-left text-[var(--text-muted)] text-xs"></i><span class="text-sm truncate text-[var(--text-main)] font-mono flex-1 select-all">${cl}</span></div>`;
                }
            });
        }

        document.getElementById(\'viewContainerContent\').innerHTML = `
            ${authHTML}
            ${listHTML ? `<div class="bg-[var(--glass-card)] border border-[var(--glass-border)] rounded-[2rem] p-6 shadow-sm"><div class="text-sm font-bold text-[var(--text-main)] tracking-widest mb-4 flex items-center gap-3 border-b border-[var(--glass-border)] pb-3 uppercase"><i class="fa-solid fa-list text-emerald-400"></i> Assets & Links</div>${listHTML}</div>` : \'\'}
            ${gsocketHTML ? `<div class="bg-[var(--glass-card)] border border-brand-500/30 rounded-[2rem] p-6 shadow-[0_0_20px_rgba(6,182,212,0.1)]"><div class="text-sm font-bold text-[var(--text-main)] tracking-widest mb-4 flex items-center gap-3 border-b border-[var(--glass-border)] pb-3 uppercase"><i class="fa-solid fa-terminal text-brand-400"></i> Terminal Commands</div>${gsocketHTML}</div>` : \'\'}
        `;
        document.getElementById(\'modalViewContainer\').classList.add(\'active\');
    };

    window.editContainer = function(id) { 
        const note = globalNotesData.find(n => n.id === id);
        if(!note) return;
        document.getElementById(\'containerId\').value = note.id; document.getElementById(\'containerTitle\').value = note.title;
        let data = { auth: {host:\'\', user:\'\', pass:\'\', dir:\'\'}, list: \'\', status: \'active\' };
        try { data = JSON.parse(note.data); } catch(e){}
        document.getElementById(\'containerHost\').value = data.auth.host || \'\';
        document.getElementById(\'containerUser\').value = data.auth.user || \'\'; 
        document.getElementById(\'containerPass\').value = data.auth.pass || \'\'; document.getElementById(\'containerDir\').value = data.auth.dir || \'\';
        document.getElementById(\'containerTextList\').value = data.list || \'\';
        document.getElementById(\'containerStatus\').value = data.status || \'active\';
        document.getElementById(\'modalContainer\').classList.add(\'active\');
    };

    function openContainerModal() {
        document.getElementById(\'containerId\').value = \'\';
        document.getElementById(\'containerTitle\').value = \'\';
        document.getElementById(\'containerHost\').value = \'\'; document.getElementById(\'containerUser\').value = \'\'; 
        document.getElementById(\'containerPass\').value = \'\'; document.getElementById(\'containerDir\').value = \'\';
        document.getElementById(\'containerTextList\').value = \'\';
        document.getElementById(\'containerStatus\').value = \'active\';
        document.getElementById(\'modalContainer\').classList.add(\'active\');
    }

    async function saveContainer() {
        const fd = new FormData();
        fd.append(\'id\', document.getElementById(\'containerId\').value); fd.append(\'title\', document.getElementById(\'containerTitle\').value || \'Untitled\'); 
        fd.append(\'host\', document.getElementById(\'containerHost\').value); fd.append(\'user\', document.getElementById(\'containerUser\').value);
        fd.append(\'pass\', document.getElementById(\'containerPass\').value); fd.append(\'dir\', document.getElementById(\'containerDir\').value);
        fd.append(\'status\', document.getElementById(\'containerStatus\').value);
        fd.append(\'text_list\', document.getElementById(\'containerTextList\').value);
        await fetch(\'index.php?api=notes&action=save_note\', { method: \'POST\', body: fd }); 
        closeModal(\'modalContainer\'); loadNotes(); Toast.fire({icon:\'success\', title:\'Container Built\'});
    }

    // --- DOMAIN VAULT ---
    async function loadDomains() {
        const res = await fetch(\'index.php?api=domains&action=list_domains\').then(r => r.json()).catch(e => { return {domains: []}; });
        globalDomainsData = (res && res.domains) || [];
        renderDomains(globalDomainsData);
    }

    function renderDomains(domains) {
        const grid = document.getElementById(\'domainsGrid\');
        grid.innerHTML = \'\';
        if (!domains.length) {
            grid.innerHTML = `<div class="col-span-full text-center py-20 text-[var(--text-muted)] font-bold text-sm"><i class="fa-solid fa-vault text-4xl block mx-auto mb-4 opacity-30"></i>No vault entries yet. Click "Add Domain" to create one.</div>`;
            return;
        }
        domains.forEach(d => {
            const statusColor = d.status === \'active\' ? \'#34d399\' : (d.status === \'new\' ? \'#fbbf24\' : \'#f87171\');
            const statusLabel = (d.status || \'active\').toUpperCase();
            const expiry = d.expiry ? d.expiry : \'-\';
            const owner = d.owner ? d.owner : \'System\';
            grid.innerHTML += `
            <div class="glass-panel rounded-[2rem] p-6 border border-[var(--glass-border)] shadow-lg hover:shadow-2xl hover:border-brand-500/40 transition-all group cursor-pointer" onclick="viewDomain(\'${d.id}\')">
                <div class="flex items-start justify-between mb-4">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-brand-500/30 to-accent-500/30 border border-brand-500/30 flex items-center justify-center shadow-inner">
                        <i class="fa-solid fa-globe text-brand-400"></i>
                    </div>
                    <span class="px-3 py-1.5 rounded-md text-[9px] font-extrabold uppercase tracking-widest border bg-[var(--input-bg)] shadow-sm" style="color: ${statusColor}; border-color: ${statusColor}40;">${statusLabel}</span>
                </div>
                <div class="font-extrabold text-[var(--text-main)] text-lg tracking-tight truncate">${d.domain}</div>
                <div class="text-[10px] text-[var(--text-muted)] font-mono mt-1 uppercase tracking-widest">Owner: ${owner}</div>
                <div class="mt-4 pt-4 border-t border-[var(--glass-border)] flex items-center justify-between">
                    <span class="text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest">Expires <span class="text-amber-400 font-extrabold">${expiry}</span></span>
                    <div class="space-x-2 opacity-0 group-hover:opacity-100 transition-opacity" onclick="event.stopPropagation()">
                        <button onclick="editDomain(\'${d.id}\')" class="w-9 h-9 rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-muted)] hover:text-blue-400 hover:border-blue-400/50 hover:bg-blue-500/10 transition-all shadow-sm" title="Edit"><i class="fa-solid fa-pen text-sm"></i></button>
                        <button onclick="promptAuth(\'delete_domain\', \'${d.id}\')" class="w-9 h-9 rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-muted)] hover:text-rose-400 hover:border-rose-400/50 hover:bg-rose-500/10 transition-all shadow-sm" title="Delete"><i class="fa-solid fa-trash text-sm"></i></button>
                    </div>
                </div>
            </div>`;
        });
    }

    function copyRowValue(btn) { const v = btn.getAttribute(\'data-copy\'); if (v && v !== \'-\') copyToClipboard(v); }

    function viewDomain(id) {
        const d = globalDomainsData.find(x => x.id === id); if (!d) return;
        document.getElementById(\'viewDomainTitle\').innerText = d.domain;
        const rows = [
            [\'Domain\', d.domain], [\'Username\', d.username || \'-\'], [\'Expiry\', d.expiry || \'-\'],
            [\'Status\', (d.status || \'active\').toUpperCase()], [\'Owner\', d.owner || \'System\'],
            [\'cPanel Token\', d.cpanel_token || \'-\'], [\'UAPI Token\', d.uapi_token || \'-\'], [\'Notes\', d.notes || \'-\']
        ];
        document.getElementById(\'viewDomainContent\').innerHTML = rows.map(r => `
            <div class="flex items-center justify-between gap-6 bg-[var(--input-bg)] border border-[var(--glass-border)] rounded-2xl px-5 py-4">
                <div class="text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest shrink-0">${r[0]}</div>
                <div class="font-mono text-[var(--text-main)] text-sm text-right break-all">${r[1]}</div>
                <button data-copy="${String(r[1]).replace(/"/g, \'&quot;\')}" onclick="copyRowValue(this)" class="w-9 h-9 rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-brand-400 hover:text-white hover:bg-brand-500 transition-all shadow-sm shrink-0" title="Copy"><i class="fa-solid fa-copy text-sm"></i></button>
            </div>`).join(\'\');
        document.getElementById(\'modalDomainView\').classList.add(\'active\');
    }

    function editDomain(id) {
        const d = globalDomainsData.find(x => x.id === id); if (!d) return;
        openDomainModal(d);
    }

    function openDomainModal(d) {
        document.getElementById(\'domainModalTitle\').innerText = d ? \'Edit Domain\' : \'Add Domain\';
        document.getElementById(\'domainId\').value = d ? d.id : \'\';
        document.getElementById(\'domainField\').value = d ? (d.domain || \'\') : \'\';
        document.getElementById(\'domainStatus\').value = d ? (d.status || \'active\') : \'active\';
        document.getElementById(\'domainUsername\').value = d ? (d.username || \'\') : \'\';
        document.getElementById(\'domainUapi\').value = d ? (d.uapi_token || \'\') : \'\';
        document.getElementById(\'domainCpanel\').value = d ? (d.cpanel_token || \'\') : \'\';
        document.getElementById(\'domainExpiry\').value = d ? (d.expiry || \'\') : \'\';
        document.getElementById(\'domainNotes\').value = d ? (d.notes || \'\') : \'\';
        document.getElementById(\'modalDomain\').classList.add(\'active\');
    }

    async function saveDomain() {
        const fd = new FormData();
        fd.append(\'id\', document.getElementById(\'domainId\').value);
        fd.append(\'domain\', document.getElementById(\'domainField\').value.trim());
        fd.append(\'status\', document.getElementById(\'domainStatus\').value);
        fd.append(\'username\', document.getElementById(\'domainUsername\').value.trim());
        fd.append(\'uapi_token\', document.getElementById(\'domainUapi\').value.trim());
        fd.append(\'cpanel_token\', document.getElementById(\'domainCpanel\').value.trim());
        fd.append(\'expiry\', document.getElementById(\'domainExpiry\').value.trim());
        fd.append(\'notes\', document.getElementById(\'domainNotes\').value.trim());
        const res = await fetch(\'index.php?api=domains&action=save_domain\', { method: \'POST\', body: fd }).then(r => r.json());
        if (res.status === \'success\') { closeModal(\'modalDomain\'); loadDomains(); Toast.fire({ icon: \'success\', title: \'Domain Vault Updated\' }); }
        else { Toast.fire({ icon: \'error\', title: res.message }); }
    }

    // --- CLOAKING ---
    async function loadCloaking() {
        const res = await fetch(\'index.php?api=cloaking&action=list_cloaking\').then(r => r.json()).catch(e=>{return [];});
        globalCloakData = (res && res.cloaks) || []; renderCloaking(globalCloakData);
    }

    function renderCloaking(cloaks) {
        const grid = document.getElementById(\'cloakingGrid\');
        grid.innerHTML = \'\';
        cloaks.sort((a,b) => b.timestamp - a.timestamp).forEach(c => {
            const isGlobal = c.type === \'global\';
            const badgeClass = isGlobal ? \'bg-accent-500/20 text-accent-300 border-accent-500/40 shadow-[0_0_10px_rgba(139,92,246,0.3)]\' : \'bg-brand-500/20 text-brand-300 border-brand-500/40 shadow-[0_0_10px_rgba(6,182,212,0.3)]\';
            const avatarUrl = c.avatar || `https://ui-avatars.com/api/?name=${c.owner}&background=0ea5e9&color=fff&rounded=true&bold=true`;
            
            grid.innerHTML += `
                <div class="card-premium p-7 relative group cursor-pointer" onclick="viewCloaking(\'${c.id}\')">
                    <div class="absolute top-5 right-5 flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity" onclick="event.stopPropagation()">
                        <button onclick="editCloaking(\'${c.id}\')" class="w-10 h-10 rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-muted)] hover:text-brand-400 flex items-center justify-center shadow-sm transition-colors"><i class="fa-solid fa-pen"></i></button>
                        <button onclick="promptAuth(\'delete_cloaking\', \'${c.id}\')" class="w-10 h-10 rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-muted)] hover:text-rose-400 flex items-center justify-center shadow-sm transition-colors"><i class="fa-solid fa-trash"></i></button>
                    </div>
                    <div class="flex items-center gap-5 mb-5">
                        <img src="${avatarUrl}" class="w-12 h-12 rounded-full object-cover border-2 border-[var(--glass-border)] shadow-sm">
                        <div>
                            <h3 class="font-extrabold text-[var(--text-main)] text-lg tracking-tight truncate w-40">${c.domain}</h3>
                            <span class="px-3 py-1 rounded-md text-[9px] font-extrabold uppercase tracking-widest border mt-1 inline-block ${badgeClass}">${c.type}</span>
                        </div>
                    </div>
                    <div class="bg-[var(--input-bg)] border border-[var(--glass-border)] rounded-2xl p-4 font-mono text-xs text-brand-300 truncate mb-5 shadow-inner">
                        <span class="text-[var(--text-muted)]">PATH:</span> ${c.path}
                    </div>
                    <div class="text-[10px] text-[var(--text-muted)] uppercase tracking-widest font-bold">Node: <span class="text-[var(--text-main)]">${c.owner}</span></div>
                </div>
            `;
        });
    }

    window.viewCloaking = function(id) {
        const c = globalCloakData.find(x => x.id === id);
        if(!c) return;
        document.getElementById(\'viewContainerTitle\').innerText = c.domain;
        document.getElementById(\'viewContainerOwner\').innerHTML = `<i class="fa-solid fa-user-shield mr-1.5"></i> ${c.owner}`;
        document.getElementById(\'viewContainerAvatar\').src = c.avatar || `https://ui-avatars.com/api/?name=${c.owner}&background=0ea5e9&color=fff&rounded=true&bold=true`;
        document.getElementById(\'viewContainerActions\').innerHTML = `
            <button onclick="closeModal(\'modalViewContainer\'); editCloaking(\'${c.id}\')" class="bg-[var(--input-bg)] border border-brand-500/40 text-brand-400 px-5 py-2.5 rounded-2xl text-sm font-bold hover:bg-brand-500 hover:text-white transition-all btn-animated shadow-sm"><i class="fa-solid fa-pen mr-2"></i>Edit</button>
            <button onclick="closeModal(\'modalViewContainer\'); promptAuth(\'delete_cloaking\', \'${c.id}\')" class="bg-[var(--input-bg)] border border-rose-500/40 text-rose-400 px-5 py-2.5 rounded-2xl text-sm font-bold hover:bg-rose-500 hover:text-white transition-all btn-animated shadow-sm"><i class="fa-solid fa-trash mr-2"></i>Delete</button>
        `;
        document.getElementById(\'viewContainerContent\').innerHTML = `
            <div class="bg-[var(--glass-card)] border border-[var(--glass-border)] rounded-[2rem] p-6 shadow-sm mb-8">
                <div class="flex items-center gap-3 mb-4 border-b border-[var(--glass-border)] pb-3">
                    <i class="fa-solid fa-globe text-accent-400 text-lg"></i><span class="text-sm font-bold text-[var(--text-main)] tracking-widest uppercase">Target Details</span>
                </div>
                <ul class="text-sm font-mono text-[var(--text-muted)] space-y-3">
                    <li class="flex items-center bg-[var(--input-bg)] p-3 rounded-2xl border border-[var(--glass-border)] shadow-inner"><span class="text-[var(--text-muted)] font-bold w-24 text-xs">DOMAIN</span><span class="cursor-pointer truncate text-[var(--text-main)] font-bold" onclick="copyToClipboard(\'${c.domain}\')">${c.domain}</span></li>
                    <li class="flex items-center bg-[var(--input-bg)] p-3 rounded-2xl border border-[var(--glass-border)] shadow-inner"><span class="text-[var(--text-muted)] font-bold w-24 text-xs">PATH</span><span class="cursor-pointer truncate text-[var(--text-main)] font-bold" onclick="copyToClipboard(\'${c.path}\')">${c.path}</span></li>
                    <li class="flex items-center bg-[var(--input-bg)] p-3 rounded-2xl border border-[var(--glass-border)] shadow-inner"><span class="text-[var(--text-muted)] font-bold w-24 text-xs">SCOPE</span><span class="truncate text-[var(--text-main)] font-bold uppercase">${c.type}</span></li>
                </ul>
            </div>
            <div class="bg-[var(--glass-card)] border border-[var(--glass-border)] rounded-[2rem] p-6 shadow-sm">
                <div class="flex items-center gap-3 mb-4 border-b border-[var(--glass-border)] pb-3">
                    <i class="fa-solid fa-code text-brand-400 text-lg"></i><span class="text-sm font-bold text-[var(--text-main)] tracking-widest uppercase">Payload Body</span>
                </div>
                <pre class="bg-black/40 border border-[var(--glass-border)] text-brand-100 p-5 rounded-2xl font-mono text-sm overflow-x-auto whitespace-pre-wrap select-all custom-scrollbar">${c.content}</pre>
            </div>
        `;
        document.getElementById(\'modalViewContainer\').classList.add(\'active\');
    };

    function openCloakingModal(cloak = null) {
        document.getElementById(\'cloakId\').value = cloak ? cloak.id : \'\'; 
        document.getElementById(\'cloakDomain\').value = cloak ? cloak.domain : \'\';
        document.getElementById(\'cloakPath\').value = cloak ? cloak.path : \'\';
        document.getElementById(\'cloakType\').value = cloak ? cloak.type : \'personal\';
        document.getElementById(\'cloakContent\').value = cloak ? cloak.content : \'\'; 
        document.getElementById(\'modalCloaking\').classList.add(\'active\');
    }

    function editCloaking(id) { const c = globalCloakData.find(x => x.id === id); if(c) openCloakingModal(c);
    }

    async function saveCloaking() {
        const fd = new FormData();
        fd.append(\'id\', document.getElementById(\'cloakId\').value); fd.append(\'domain\', document.getElementById(\'cloakDomain\').value);
        fd.append(\'path\', document.getElementById(\'cloakPath\').value); fd.append(\'type\', document.getElementById(\'cloakType\').value); fd.append(\'content\', document.getElementById(\'cloakContent\').value);
        await fetch(\'index.php?api=cloaking&action=save_cloaking\', { method: \'POST\', body: fd }); closeModal(\'modalCloaking\'); loadCloaking();
        Toast.fire({icon:\'success\', title:\'Rule Deployed\'});
    }

    // --- USERS ---
    async function loadUsers() {
        const res = await fetch(\'index.php?api=users&action=list_users\').then(r => r.json()).catch(e=>{return [];});
        globalUsers = res;
        const tbody = document.getElementById(\'usersList\'); tbody.innerHTML = \'\';
        res.forEach(u => { 
            let roleClass = \'role-guest\';
            if(u.role === \'owner\') roleClass = \'role-owner\';
            if(u.role === \'admin\') roleClass = \'role-admin\';

            const avatarUrl = u.avatar ? u.avatar : `https://ui-avatars.com/api/?name=${u.username}&background=0ea5e9&color=fff&rounded=true&bold=true`;
            const isOnline = (Math.floor(Date.now()/1000) - (u.last_active || 0)) < 30; 
            
            if(u.username === currentUser) {
                document.getElementById(\'sidebarAvatar\').src = avatarUrl;
                document.getElementById(\'sidebarRole\').innerText = u.role;
                document.getElementById(\'headerRoleBadge\').innerText = u.role;
                document.getElementById(\'headerRoleBadge\').className = `role-badge ${roleClass} ml-2`;
                
                const sbDot = document.getElementById(\'sidebarDot\');
                if(sbDot) sbDot.className = `w-3 h-3 rounded-full absolute -bottom-0.5 -right-0.5 border-2 border-[var(--bg-base)] ${isOnline ? \'bg-emerald-400 shadow-[0_0_8px_#34d399]\' : \'bg-slate-500\'}`;
            }

            const statusDot = isOnline ? \'<div class="w-3.5 h-3.5 rounded-full bg-emerald-400 shadow-[0_0_8px_#34d399] absolute -bottom-0.5 -right-0.5 border-[3px] border-[var(--bg-base)]"></div>\' : \'<div class="w-3.5 h-3.5 rounded-full bg-slate-500 absolute -bottom-0.5 -right-0.5 border-[3px] border-[var(--bg-base)]"></div>\';
            
            tbody.innerHTML += `
            <tr class="group hover:bg-[var(--glass-hover)] transition-colors">
                <td class="px-8 py-5 font-bold text-[var(--text-main)] flex items-center gap-5">
                    <div class="relative cursor-pointer hover:opacity-80 transition-opacity" onclick="openAvatarZoom(\'${avatarUrl}\')">
                        <img src="${avatarUrl}" class="w-12 h-12 rounded-full border-2 border-[var(--glass-border)] shadow-sm object-cover">
                        <div id="user_dot_${u.username}">${statusDot}</div>
                    </div>
                    <div>
                        <span class="text-base block tracking-tight font-extrabold">${u.username}</span>
                        <span id="user_status_${u.username}" class="text-[10px] font-mono uppercase tracking-widest ${isOnline ? \'text-emerald-400 font-bold\' : \'text-[var(--text-muted)]\'}"><i class="fa-solid fa-circle text-[7px] mr-1.5"></i>${isOnline ? \'Online\' : \'Offline\'}</span>
                    </div>
                </td>
                <td class="px-6 py-5">
                    <span class="role-badge ${roleClass}"><i class="fa-solid fa-shield-halved mr-2"></i>${u.role}</span>
                </td>
                <td class="px-6 py-5 text-right opacity-0 group-hover:opacity-100 transition-opacity">
                    ${u.username === currentUser ? `<button onclick="promptDeleteUser(\'${u.username}\')" class="w-10 h-10 rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-muted)] hover:text-rose-400 hover:border-rose-400/50 hover:bg-rose-500/10 transition-all shadow-sm" title="Delete Identity"><i class="fa-solid fa-user-minus text-sm"></i></button>` : \'\'}
                </td>
            </tr>`;
        });
    }

    function promptDeleteUser(username) {
        document.getElementById(\'delTargetUser\').value = username;
        const select = document.getElementById(\'delMigrateTo\');
        select.innerHTML = \'<option value="">-- Destroy All Data --</option>\';
        globalUsers.forEach(u => {
            if(u.username !== username) select.innerHTML += `<option value="${u.username}">Migrate Data To: ${u.username}</option>`;
        });
        document.getElementById(\'modalDeleteUser\').classList.add(\'active\');
    }

    function executeDeleteUser() {
        const target = document.getElementById(\'delTargetUser\').value;
        const migrate = document.getElementById(\'delMigrateTo\').value;
        promptAuth(\'delete_user\', target, \'\', migrate);
    }

    function openUserModal() {
        if(getMyRole() !== \'owner\') return swalDark.fire({icon:\'error\', title:\'Denied\', html:\'<p class="text-[var(--text-muted)] text-sm">Only Owners can register users.</p>\'});
        document.getElementById(\'newUserName\').value = \'\'; document.getElementById(\'newUserPass\').value = \'\';
        document.getElementById(\'modalUser\').classList.add(\'active\');
    }

    async function saveUser() {
        const user = document.getElementById(\'newUserName\').value;
        const pass = document.getElementById(\'newUserPass\').value; const role = document.getElementById(\'newUserRole\').value;
        if(!user || !pass) return; const fd = new FormData(); fd.append(\'username\', user);
        fd.append(\'password\', pass); fd.append(\'role\', role);
        const res = await fetch(\'index.php?api=users&action=add_user\', { method: \'POST\', body: fd }).then(r => r.json());
        if(res.status === \'success\') { closeModal(\'modalUser\'); loadUsers(); Toast.fire({icon:\'success\',title:\'Identity Registered\'}); } else Toast.fire({icon:\'error\', title:res.message});
    }

    // --- FIREWALL ---
    async function loadFirewall() {
        const res = await fetch(\'index.php?api=firewall&action=list_firewall\').then(r => r.json()).catch(e=>{return [];});
        const tbody = document.getElementById(\'firewallList\'); tbody.innerHTML = \'\';
        (res.entries || []).forEach(fw => { 
            const date = new Date(fw.added * 1000).toLocaleString();
            tbody.innerHTML += `
            <tr class="group hover:bg-[var(--glass-hover)] transition-colors">
                <td class="px-8 py-5 text-emerald-400 font-extrabold text-base tracking-widest">${fw.ip}</td>
                <td class="px-6 py-5 text-[var(--text-main)] font-sans text-sm font-medium">${fw.note}</td>
                <td class="px-6 py-5 text-[var(--text-muted)] text-xs font-mono">${date}</td>
                <td class="px-6 py-5 text-right opacity-0 group-hover:opacity-100 transition-opacity">
                    <button onclick="promptAuth(\'delete_firewall\', \'${fw.id}\')" class="w-10 h-10 rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-muted)] hover:text-rose-400 hover:border-rose-400/50 hover:bg-rose-500/10 transition-all shadow-sm" title="Remove IP"><i class="fa-solid fa-trash text-sm"></i></button>
                </td>
            </tr>`; 
        });
    }

    function openFirewallModal() {
        if(getMyRole() !== \'owner\') return swalDark.fire({icon:\'error\', title:\'Denied\', html:\'<p class="text-[var(--text-muted)] text-sm">Only Owners can manage Firewall.</p>\'});
        document.getElementById(\'fwIP\').value = \'\'; document.getElementById(\'fwNote\').value = \'\';
        document.getElementById(\'modalFirewall\').classList.add(\'active\');
    }

    async function saveFirewallIP() {
        const ip = document.getElementById(\'fwIP\').value;
        const note = document.getElementById(\'fwNote\').value;
        if(!ip) return; const fd = new FormData(); fd.append(\'ip\', ip); fd.append(\'note\', note);
        const res = await fetch(\'index.php?api=firewall&action=add_firewall\', { method: \'POST\', body: fd }).then(r => r.json());
        if(res.status === \'success\') { closeModal(\'modalFirewall\'); loadFirewall();
            Toast.fire({icon:\'success\',title:\'IP Whitelisted\'}); } else Toast.fire({icon:\'error\', title:res.message});
    }

    function copyToClipboard(text) { if(!text || text === \'-\') return; navigator.clipboard.writeText(text);
        Toast.fire({ icon: \'success\', title: \'Copied\' }); }
    
    function performSearch() {
        const q = document.getElementById(\'globalSearch\').value.toLowerCase();
        if (document.getElementById(\'view_files\').classList.contains(\'active\')) renderFiles(globalFilesData.filter(f => f.name.toLowerCase().includes(q)));
        if (document.getElementById(\'view_notes\').classList.contains(\'active\')) renderNotes(globalNotesData.filter(n => n.title.toLowerCase().includes(q) || n.data.toLowerCase().includes(q)));
        if (document.getElementById(\'view_cloaking\').classList.contains(\'active\')) renderCloaking(globalCloakData.filter(c => c.domain.toLowerCase().includes(q) || c.path.toLowerCase().includes(q)));
        if (document.getElementById(\'view_domains\').classList.contains(\'active\')) renderDomains(globalDomainsData.filter(d => (d.domain||\'\').toLowerCase().includes(q) || (d.username||\'\').toLowerCase().includes(q) || (d.notes||\'\').toLowerCase().includes(q)));
    }

    async function loadMonitor() {
        const res = await fetch(\'index.php?api=monitor&action=list_processes\').then(r => r.json()).catch(e=>{return [];});
        const tbody = document.getElementById(\'processList\'); tbody.innerHTML = \'\';
        if(res.status === \'success\' && res.data) {
            if(res.data.length === 0) {
                tbody.innerHTML = \'<tr><td colspan="6" class="px-6 py-10 text-center text-[var(--text-muted)] font-medium">Shell exec disabled or inaccessible by OS.</td></tr>\';
            } else {
                res.data.forEach(p => {
                    const cpuColor = parseFloat(p.cpu) > 50 ? \'text-rose-400 font-extrabold\' : \'text-emerald-400 font-bold\';
                    tbody.innerHTML += `
                    <tr class="border-b border-[var(--glass-border)] hover:bg-[var(--glass-hover)] transition-colors group">
                        <td class="px-6 py-5 text-brand-400 font-bold tracking-widest">${p.pid}</td>
                        <td class="px-6 py-5 text-[var(--text-main)] font-semibold">${p.user}</td>
                        <td class="px-6 py-5 ${cpuColor}">${p.cpu}%</td>
                        <td class="px-6 py-5 text-[var(--text-muted)]">${p.mem}%</td>
                        <td class="px-6 py-5 text-[var(--text-muted)] truncate max-w-md font-mono text-xs" title="${p.cmd}">${p.cmd}</td>
                        <td class="px-6 py-5 text-right opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick="promptAuth(\'kill_process\', \'${p.pid}\')" class="w-10 h-10 rounded-[1rem] bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-muted)] hover:text-rose-400 hover:bg-rose-500/10 hover:border-rose-500/40 transition-all shadow-sm" title="Kill Process"><i class="fa-solid fa-skull"></i></button>
                        </td>
                    </tr>`;
                });
            }
        }
    }

    async function createSnapshot() {
        Toast.fire({ icon: \'info\', title: \'Building snapshot...\' });
        const res = await fetch(\'index.php?api=monitor&action=create_snapshot\').then(r=>r.json());
        if(res.status === \'success\') { Toast.fire({icon:\'success\', title:\'Snapshot saved to Assets\'});
        }
        else { Toast.fire({icon:\'error\', title:res.message}); }
    }

    // Interval Heartbeat Ringan
    setInterval(async () => { 
        const res = await fetch(\'/index.php?api=heartbeat\').then(r=>r.json());
        if(res.status === \'success\' && res.online_data) {
            const now = Math.floor(Date.now() / 1000);
            
            const myOnline = res.online_data[currentUser] ? (now - res.online_data[currentUser] < 30) : false;
            const sbDot = document.getElementById(\'sidebarDot\');
            if(sbDot) sbDot.className = `w-3 h-3 rounded-full absolute -bottom-0.5 -right-0.5 border-2 border-[var(--bg-base)] ${myOnline ? \'bg-emerald-400 shadow-[0_0_8px_#34d399]\' : \'bg-slate-500\'}`;
            
            if(document.getElementById(\'view_users\').classList.contains(\'active\')) {
                globalUsers.forEach(u => {
                    if(res.online_data[u.username]) u.last_active = res.online_data[u.username];
                    const isOnline = (now - (u.last_active || 0)) < 30; 

                    const uDot = document.getElementById(`user_dot_${u.username}`);
                    if(uDot) uDot.innerHTML = isOnline ?
                        \'<div class="w-3.5 h-3.5 rounded-full bg-emerald-400 shadow-[0_0_8px_#34d399] absolute -bottom-0.5 -right-0.5 border-[3px] border-[var(--bg-base)]"></div>\' : \'<div class="w-3.5 h-3.5 rounded-full bg-slate-500 absolute -bottom-0.5 -right-0.5 border-[3px] border-[var(--bg-base)]"></div>\';
                    
                    const uText = document.getElementById(`user_status_${u.username}`);
                    if(uText) {
                        uText.innerHTML = isOnline ?
                            \'<i class="fa-solid fa-circle text-[7px] mr-1.5"></i>Online\' : \'<i class="fa-solid fa-circle text-[7px] mr-1.5"></i>Offline\';
                        uText.className = `text-[10px] font-mono uppercase tracking-widest ${isOnline ? \'text-emerald-400 font-bold\' : \'text-[var(--text-muted)]\'}`;
                    }
                });
            }
        }
    }, 15000);
</script>
</body>
</html>',
    'views/login.php' => '<?php
/**
 * Emerald Central Hub V11 — login view.
 * Rendered by index.php when no session exists.
 * Talks to index.php?action=login|get_sec_q|reset_pass (V10-compatible).
 */
$__u = auth_user();
$__csrf = sec_csrf_token();
?><!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, \'UTF-8\'); ?> — Sign in</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="login-page">
  <div class="login-wrap">
    <div class="login-card">
      <div class="login-brand">
        <div class="logo-badge">E</div>
        <h1><?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, \'UTF-8\'); ?></h1>
        <p class="muted"><?php echo htmlspecialchars(APP_TAGLINE, ENT_QUOTES, \'UTF-8\'); ?></p>
      </div>

      <div class="vf" id="view-login">
        <div class="field">
          <label>Identity</label>
          <input id="login_user" type="text" placeholder="your.username" autocomplete="username" autofocus>
        </div>
        <div class="field">
          <label>Password</label>
          <input id="login_pass" type="password" placeholder="••••••••••" autocomplete="current-password">
        </div>
        <button class="btn-primary btn-block" id="loginBtn">Sign in</button>
        <button type="button" class="linkbtn btn-block" id="toResetBtn">Forgot password?</button>
      </div>

      <div class="vf hide" id="view-reset">
        <div class="field" id="resetStep1">
          <label>Identity</label>
          <input id="reset_user" type="text" placeholder="your.username" autocomplete="username">
          <button class="btn-primary btn-block" id="resetAskBtn" style="margin-top:14px">Get security question</button>
        </div>
        <div class="field hide" id="resetStep2">
          <p class="qtext" id="sec_q_display"></p>
          <label>Answer</label>
          <input id="reset_answer" type="text" placeholder="your answer" autocomplete="off">
          <label>New password</label>
          <input id="reset_new_pass" type="password" placeholder="••••••••••" autocomplete="new-password">
          <button class="btn-primary btn-block" id="resetBtn" style="margin-top:14px">Reset password</button>
        </div>
        <button type="button" class="linkbtn btn-block" id="backToLoginBtn">← Back to sign in</button>
      </div>

      <div class="login-foot">
        <button type="button" class="theme-toggle" id="themeToggle" title="Toggle theme">◐ Theme</button>
        <span class="muted" id="versionTag">v<?php echo htmlspecialchars(APP_VERSION, ENT_QUOTES, \'UTF-8\'); ?></span>
      </div>
    </div>
  </div>
<script>
window.EM = window.EM || {};
window.EM.csrf = <?php echo json_encode($__csrf); ?>;
</script>
<script src="assets/js/app.js"></script>
<script>
(function () {
  \'use strict\';
  var EM = window.EM;

  function show(v) {
    document.getElementById(\'view-login\').classList.toggle(\'hide\', v === \'reset\');
    document.getElementById(\'view-reset\').classList.toggle(\'hide\', v !== \'reset\');
  }

  document.getElementById(\'loginBtn\').addEventListener(\'click\', function () {
    var btn = this, user = document.getElementById(\'login_user\').value.trim(), pass = document.getElementById(\'login_pass\').value;
    if (!user || !pass) { EM.toast(\'Identity and password are required\', \'err\'); return; }
    btn.disabled = true;
    EM.post(\'index.php?action=login\', { username: user, password: pass }).then(function (res) {
      btn.disabled = false;
      if (res.status === \'ok\') { window.location.href = \'index.php\'; return; }
      EM.toast(res.message || \'Invalid credentials\', \'err\');
    }).catch(function () { btn.disabled = false; EM.toast(\'Network error\', \'err\'); });
  });

  document.getElementById(\'resetAskBtn\').addEventListener(\'click\', function () {
    var btn = this, user = document.getElementById(\'reset_user\').value.trim();
    if (!user) { EM.toast(\'Identity required\', \'err\'); return; }
    btn.disabled = true;
    EM.post(\'index.php?action=get_sec_q\', { username: user }).then(function (res) {
      btn.disabled = false;
      if (res.status !== \'success\') { EM.toast(res.message || \'Identity not found\', \'err\'); return; }
      document.getElementById(\'reset_user\').value = res.actual_user;
      document.getElementById(\'sec_q_display\').textContent = res.question;
      document.getElementById(\'resetStep1\').classList.add(\'hide\');
      document.getElementById(\'resetStep2\').classList.remove(\'hide\');
    }).catch(function () { btn.disabled = false; EM.toast(\'Network error\', \'err\'); });
  });

  document.getElementById(\'resetBtn\').addEventListener(\'click\', function () {
    var btn = this, user = document.getElementById(\'reset_user\').value.trim(),
        answer = document.getElementById(\'reset_answer\').value.trim(),
        pass = document.getElementById(\'reset_new_pass\').value;
    if (!user || !answer || !pass) { EM.toast(\'Fill in all fields\', \'err\'); return; }
    btn.disabled = true;
    EM.post(\'index.php?action=reset_pass\', { username: user, answer: answer, new_pass: pass }).then(function (res) {
      btn.disabled = false;
      if (res.status !== \'ok\') { EM.toast(res.message || \'Reset failed\', \'err\'); return; }
      EM.toast(\'Password updated — sign in now\', \'ok\');
      document.getElementById(\'reset_answer\').value = \'\';
      document.getElementById(\'reset_new_pass\').value = \'\';
      show(\'login\');
      document.getElementById(\'login_user\').value = user;
      document.getElementById(\'login_pass\').focus();
    }).catch(function () { btn.disabled = false; EM.toast(\'Network error\', \'err\'); });
  });

  document.getElementById(\'toResetBtn\').addEventListener(\'click\', function () { show(\'reset\'); document.getElementById(\'reset_user\').focus(); });
  document.getElementById(\'backToLoginBtn\').addEventListener(\'click\', function () { show(\'login\'); document.getElementById(\'login_user\').focus(); });

  [\'login_user\',\'login_pass\'].forEach(function (id) {
    document.getElementById(id).addEventListener(\'keydown\', function (e) { if (e.key === \'Enter\') { document.getElementById(\'loginBtn\').click(); } });
  });
  [\'reset_answer\',\'reset_new_pass\'].forEach(function (id) {
    document.getElementById(id).addEventListener(\'keydown\', function (e) { if (e.key === \'Enter\') { document.getElementById(\'resetBtn\').click(); } });
  });
})();
</script>
</body>
</html>
',
    'vieww.php' => '<?php
require_once __DIR__ . \'/core/config.php\';

$requested_file = isset($_GET[\'f\']) ? basename($_GET[\'f\']) : null;

if (!$requested_file) {
    http_response_code(404);
    die("Asset not specified.");
}

function searchFileRecursively($dir, $filename_without_ext) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && pathinfo($file->getFilename(), PATHINFO_FILENAME) === $filename_without_ext) {
            return $file->getPathname();
        }
    }
    return false;
}

$found = searchFileRecursively(ASSETS_DIR, $requested_file);

if ($found && file_exists($found)) {
    // Apapun ekstensinya, kita paksa menjadi teks sesuai permintaan agar tidak mendownload otomatis.
    header(\'Content-Type: text/plain; charset=utf-8\');
    header(\'Content-Length: \' . filesize($found));
    readfile($found);
    exit;
} else {
    http_response_code(404);
    die("File not found or access denied.");
}
?>',
);

/* ------------------------------------------------------------------ *
 *  Main entry
 * ------------------------------------------------------------------ */
$isCli = (PHP_SAPI === 'cli');
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$force = $isCli || isset($_REQUEST['force']);
$remove = isset($_REQUEST['remove']);

// Web intro (GET) — no changes performed
if (!$isCli && !$isPost) {
    v11_web_intro(V11_ROOT);
    exit;
}

// Marker guard: block accidental re-runs unless force is set
$__markerFile = V11_DATA_DIR . '/.v11_version';
if (is_file($__markerFile) && !$force) {
    $__m = @include($__markerFile);
    $__ver = is_array($__m) && isset($__m['version']) ? $__m['version'] : '?';
    $GLOBALS['__v11_log'] = array();
    v11_log('Upgrade blocked: V11 marker already present (version ' . $__ver . '). Use force=1 to re-run (safe: same files, data untouched).', 'warn');
    if ($isCli) { v11_cli_report(array('started' => date('c')), V11_ROOT); }
    else { v11_web_report(array('started' => date('c')), V11_ROOT); }
    exit(0);
}

// ---- Execute ----
$report = array('started' => date('c'), 'backed_up' => array(), 'written' => array(), 'data_dirs' => array());

v11_log('Upgrade started (V10 → V11)');
$keyHex = v11_key();
if ($keyHex === '') {
    v11_log('FATAL: could not obtain encryption key (.emerald_data/.sys_key).', 'err');
    $report['aborted'] = true;
    if ($isCli) { v11_cli_report($report, V11_ROOT); } else { v11_web_report($report, V11_ROOT); }
    exit(1);
}
v11_log('Encryption key ready ('.strlen($keyHex).' hex chars).');

// 1) Read-only probe of existing data
$resolved = v11_probe_db($report, $keyHex);

// 2) Backup everything first
if (!v11_backup($report, $resolved)) {
    if ($isCli) { v11_cli_report($report, V11_ROOT); } else { v11_web_report($report, V11_ROOT); }
    exit(1);
}

// 3) Registry of V11 dataset paths
if (v11_write_registry(V11_ROOT) === false) {
    if ($isCli) { v11_cli_report($report, V11_ROOT); } else { v11_web_report($report, V11_ROOT); }
    exit(1);
}

// 4) Migrate notepad data
v11_migrate_notepad(V11_ROOT, $report);

// 5) Module data dirs + .htaccess deny
v11_mkdirs(V11_ROOT, $report);

// 6) Write the embedded V11 application files
if (!v11_write_files(V11_ROOT, $V11_FILES, $report)) {
    if ($isCli) { v11_cli_report($report, V11_ROOT); } else { v11_web_report($report, V11_ROOT); }
    exit(1);
}

// 7) Lint
v11_lint(V11_ROOT, $V11_FILES, $report);

// 8) Marker (idempotency)
if (v11_marker(V11_ROOT, $report)) {
    v11_log('Marker written (.emerald_data/.v11_version) — re-runs blocked unless force=1.');
} else {
    v11_log('WARNING: could not write version marker.', 'warn');
}

v11_log('Upgrade finished. ' . count($report['written']) . ' files written, backup saved.');

if ($remove && !$isCli && $GLOBALS['__v11_ok']) {
    @unlink(__FILE__);
    v11_log('update.php removed per request.');
}

if ($isCli) {
    v11_cli_report($report, V11_ROOT);
} else {
    v11_web_report($report, V11_ROOT);
}
