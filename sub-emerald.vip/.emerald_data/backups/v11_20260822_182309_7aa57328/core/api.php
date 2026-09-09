<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['emerald_user'])) {
    http_response_code(403);
    exit(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

header('Content-Type: application/json');
$action = $_GET['api'] ?? '';
$current_user = $_SESSION['emerald_user'];
$users = getDB($users_db);
$current_role = $users[$current_user]['role'] ?? 'guest';

if ($action === 'heartbeat') {
    $users[$current_user]['last_active'] = time();
    saveDB($users_db, $users);
    $statuses = [];
    foreach($users as $uname => $udata) { $statuses[$uname] = $udata['last_active'] ?? 0; }
    echo json_encode(['status' => 'success', 'online_data' => $statuses]); exit;
}

$users[$current_user]['last_active'] = time();
saveDB($users_db, $users);

$modifying_actions = ['upload', 'create_folder', 'create_file', 'delete_file', 'multi_delete', 'paste_files', 'save_file', 'save_note', 'delete_note', 'add_user', 'delete_user', 'save_cloaking', 'delete_cloaking', 'update_profile', 'zip_file', 'unzip_file', 'add_firewall', 'delete_firewall', 'rename_file', 'kill_process'];
if ($current_role === 'guest' && in_array($action, $modifying_actions)) {
    exit(json_encode(['status' => 'error', 'message' => 'Guest privileges do not allow modifications.']));
}

if ($action === 'sys_info') {
    $logs = getDB($logs_db);
    $activity = getDB($activity_db);
    $disk_free = @disk_free_space('/');
    $disk_total = @disk_total_space('/');
    echo json_encode([
        'stats' => getSystemStats(),
        'extended' => ['disk_free' => formatSize($disk_free ?: 0), 'disk_total' => formatSize($disk_total ?: 0), 'php_sapi' => php_sapi_name()],
        'logs' => (is_array($logs) || is_object($logs)) ? array_values((array)$logs) : [],
        'activity' => (is_array($activity) || is_object($activity)) ? array_values((array)$activity) : [],
        'firewall_count' => count(getDB($firewall_db))
    ]); exit;
}

if ($action === 'list_processes') {
    $process_data = [];
    if (function_exists('shell_exec') && !in_array('shell_exec', array_map('trim', explode(', ', ini_get('disable_functions'))))) {
        $out = @shell_exec("ps aux --sort=-%cpu | head -n 50");
        if ($out) {
            $lines = explode("\n", trim($out));
            foreach($lines as $i => $line) {
                if($i===0) continue;
                $cols = preg_split('/\s+/', $line, 11);
                if(count($cols) >= 11) $process_data[] = ['user'=>$cols[0], 'pid'=>$cols[1], 'cpu'=>$cols[2], 'mem'=>$cols[3], 'cmd'=>htmlspecialchars($cols[10])];
            }
        }
    }
    echo json_encode(['status'=>'success', 'data'=>$process_data]); exit;
}

if ($action === 'kill_process') {
    $pid = (int)$_POST['pid'];
    if ($current_role !== 'owner' && $current_role !== 'admin') exit(json_encode(['status' => 'error', 'message' => 'Authorization failed.']));
    if (function_exists('shell_exec')) { @shell_exec("kill -9 $pid"); logActivity($current_user, "Executed SIGKILL on PID: $pid"); echo json_encode(['status'=>'success']); } 
    else { echo json_encode(['status'=>'error', 'message'=>'Exec function disabled by host.']); }
    exit;
}

if ($action === 'create_snapshot') {
    $zipPath = ASSETS_DIR . '/System_Snapshot_' . date('Y-m-d_H-i-s') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        $path = DATA_DIR;
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($files as $name => $f) {
            if (!$f->isDir()) { $filePath = $f->getRealPath(); $relativePath = '.emerald_data/' . substr($filePath, strlen($path) + 1); $zip->addFile($filePath, $relativePath); }
        }
        $zip->close();
        $file_meta = getDB($file_meta_db); $file_meta[basename($zipPath)] = $current_user; saveDB($file_meta_db, $file_meta);
        logActivity($current_user, "Generated System Snapshot."); echo json_encode(['status' => 'success']);
    } else { echo json_encode(['status' => 'error', 'message' => 'Failed to build zip architecture.']); }
    exit;
}

if ($action === 'list_firewall') { echo json_encode(array_values(getDB($firewall_db))); exit; }

if ($action === 'add_firewall') {
    $fw = getDB($firewall_db); $ip = sanitize($_POST['ip']); $note = sanitize($_POST['note']);
    $fw[] = ['id' => generateId(), 'ip' => $ip, 'note' => $note, 'added' => time(), 'owner' => $current_user];
    saveDB($firewall_db, $fw); logActivity($current_user, "Whitelisted IP: $ip");
    echo json_encode(['status' => 'success']); exit;
}

if ($action === 'delete_firewall') {
    $fw = getDB($firewall_db); $id = sanitize($_POST['id']);
    $target_key = null; $target_fw = null;
    foreach($fw as $key => $val) { if($val['id'] === $id) { $target_fw = $val; $target_key = $key; break; } }
    if (!$target_fw) exit(json_encode(['status' => 'error', 'message' => 'Not found']));
    
    $owner = $target_fw['owner'] ?? 'System';
    if ($owner !== $current_user && $current_role !== 'owner') exit(json_encode(['status' => 'error', 'message' => 'Mutlak hanya pemilik asli ('.$owner.') yang bisa menghapus.']));
    
    logActivity($current_user, "Removed IP from Whitelist: " . $target_fw['ip']);
    unset($fw[$target_key]); saveDB($firewall_db, array_values($fw));
    echo json_encode(['status' => 'success']); exit;
}

if ($action === 'list_files') {
    $files = []; $file_meta = getDB($file_meta_db);
    $path_param = isset($_GET['path']) ? trim(sanitize($_GET['path']), '/') : '';
    $scan_dir = ASSETS_DIR . ($path_param ? '/' . $path_param : '');
    if(!is_dir($scan_dir) || strpos(realpath($scan_dir), realpath(ASSETS_DIR)) !== 0) $scan_dir = ASSETS_DIR;

    if (file_exists($scan_dir)) {
        $dir = new DirectoryIterator($scan_dir);
        foreach ($dir as $fileinfo) {
            if (!$fileinfo->isDot() && $fileinfo->getFilename() !== 'notepad' && $fileinfo->getFilename() !== '.htaccess') {
                $filename = $fileinfo->getFilename();
                $meta_key = $path_param ? $path_param . '/' . $filename : $filename;
                $owner = isset($file_meta[$meta_key]) ? $file_meta[$meta_key] : 'System';
                $is_dir = $fileinfo->isDir();
                
                $files[] = [
                    'name' => $filename, 'ext' => $is_dir ? 'DIR' : pathinfo($filename, PATHINFO_EXTENSION), 'size' => formatSize($fileinfo->getSize()),
                    'modified' => date("Y-m-d H:i:s", $fileinfo->getMTime()), 'is_dir' => $is_dir,
                    'owner' => $owner, 'link_name' => pathinfo($filename, PATHINFO_FILENAME)
                ];
            }
        }
    }
    echo json_encode(['path' => $path_param, 'files' => $files]); exit;
}

if ($action === 'upload') {
    if (!empty($_FILES)) {
        $path_param = isset($_POST['path']) ? trim(sanitize($_POST['path']), '/') : '';
        $relative_path = isset($_POST['relative_path']) ? trim(sanitize($_POST['relative_path']), '/') : '';
        $target_dir = ASSETS_DIR . ($path_param ? '/' . $path_param : '');
        
        if ($relative_path && strpos($relative_path, '/') !== false) {
            $sub_dir = dirname($relative_path); $target_dir .= '/' . $sub_dir;
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
            $meta_name = $sub_dir . '/' . basename($relative_path); $name = basename($relative_path);
        } else {
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
            $name = sanitize($_FILES['file']['name']); $meta_name = $name;
        }
        
        if (move_uploaded_file($_FILES['file']['tmp_name'], $target_dir . '/' . $name)) {
            $final_meta_key = $path_param ? $path_param . '/' . $meta_name : $meta_name;
            $file_meta = getDB($file_meta_db);
            $file_meta[$final_meta_key] = isset($file_meta[$final_meta_key]) ? $file_meta[$final_meta_key] : $current_user; 
            saveDB($file_meta_db, $file_meta);
            logActivity($current_user, "Uploaded asset: " . $final_meta_key);
            echo json_encode(['status' => 'success']);
        } else { echo json_encode(['status' => 'error', 'message' => 'Upload failed']); }
    } exit;
}

if ($action === 'create_folder' || $action === 'create_file') {
    $target_name = sanitize($_POST['name'] ?? $_POST['folder'] ?? $_POST['file']);
    $path_param = isset($_POST['path']) ? trim(sanitize($_POST['path']), '/') : '';
    $path = ASSETS_DIR . ($path_param ? '/' . $path_param : '') . '/' . $target_name;
    if (!file_exists($path)) {
        if($action === 'create_folder') { mkdir($path, 0755); logActivity($current_user, "Created directory: $target_name"); }
        else { file_put_contents($path, ''); logActivity($current_user, "Created file: $target_name"); }
        $meta_key = $path_param ? $path_param . '/' . $target_name : $target_name;
        $file_meta = getDB($file_meta_db); 
        $file_meta[$meta_key] = isset($file_meta[$meta_key]) ? $file_meta[$meta_key] : $current_user; 
        saveDB($file_meta_db, $file_meta);
        echo json_encode(['status' => 'success']);
    } else { echo json_encode(['status' => 'error', 'message' => 'Target already exists']); }
    exit;
}

if ($action === 'rename_file') {
    $old_name = sanitize($_POST['old_name']); $new_name = sanitize($_POST['new_name']);
    $path_param = isset($_POST['path']) ? trim(sanitize($_POST['path']), '/') : '';
    $base_dir = ASSETS_DIR . ($path_param ? '/' . $path_param : '');
    if (file_exists($base_dir . '/' . $old_name) && !file_exists($base_dir . '/' . $new_name)) {
        if (rename($base_dir . '/' . $old_name, $base_dir . '/' . $new_name)) {
            $file_meta = getDB($file_meta_db);
            $old_k = $path_param ? $path_param . '/' . $old_name : $old_name; $new_k = $path_param ? $path_param . '/' . $new_name : $new_name;
            $file_meta[$new_k] = isset($file_meta[$old_k]) ? $file_meta[$old_k] : $current_user;
            if(isset($file_meta[$old_k])) unset($file_meta[$old_k]);
            saveDB($file_meta_db, $file_meta);
            logActivity($current_user, "Renamed asset from $old_name to $new_name");
            echo json_encode(['status' => 'success']);
        } else { echo json_encode(['status' => 'error', 'message' => 'Permission denied.']); }
    } else { echo json_encode(['status' => 'error', 'message' => 'Not found or exists.']); }
    exit;
}

if ($action === 'zip_file' || $action === 'unzip_file') {
    $file = sanitize($_POST['file']); $path_param = isset($_POST['path']) ? trim(sanitize($_POST['path']), '/') : '';
    $path = ASSETS_DIR . ($path_param ? '/' . $path_param : '') . '/' . $file;
    if(file_exists($path)) {
        if($action === 'zip_file') {
            $zipPath = $path . '.zip'; $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                if(is_dir($path)) {
                    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::LEAVES_ONLY);
                    foreach ($files as $name => $f) { if (!$f->isDir()) { $zip->addFile($f->getRealPath(), substr($f->getRealPath(), strlen($path) + 1)); } }
                } else { $zip->addFile($path, basename($path)); }
                $zip->close();
                $meta_key = $path_param ? $path_param . '/' . basename($zipPath) : basename($zipPath);
                $file_meta = getDB($file_meta_db); $file_meta[$meta_key] = isset($file_meta[$meta_key]) ? $file_meta[$meta_key] : $current_user; saveDB($file_meta_db, $file_meta);
                logActivity($current_user, "Compressed archive: " . basename($zipPath)); echo json_encode(['status' => 'success']);
            }
        } else {
            $zip = new ZipArchive;
            if ($zip->open($path) === TRUE) {
                $extract_name = pathinfo($path, PATHINFO_FILENAME); $extract_path = dirname($path) . '/' . $extract_name;
                if(!is_dir($extract_path)) mkdir($extract_path, 0755);
                $zip->extractTo($extract_path); $zip->close();
                $meta_key = $path_param ? $path_param . '/' . $extract_name : $extract_name;
                $file_meta = getDB($file_meta_db); $file_meta[$meta_key] = isset($file_meta[$meta_key]) ? $file_meta[$meta_key] : $current_user; saveDB($file_meta_db, $file_meta);
                logActivity($current_user, "Extracted archive: $file"); echo json_encode(['status' => 'success']);
            }
        }
    }
    exit;
}

function recursiveRemoveDir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . "/" . $object)) recursiveRemoveDir($dir . DIRECTORY_SEPARATOR . $object);
                else unlink($dir . DIRECTORY_SEPARATOR . $object);
            }
        } rmdir($dir);
    }
}

if ($action === 'multi_delete') {
    $files = json_decode($_POST['files'], true);
    $path_param = isset($_POST['path']) ? trim(sanitize($_POST['path']), '/') : '';
    $base_dir = ASSETS_DIR . ($path_param ? '/' . $path_param : '');
    
    $file_meta = getDB($file_meta_db); $all_success = true;

    foreach($files as $file) {
        $file = sanitize($file); $path = $base_dir . '/' . $file;
        $meta_key = $path_param ? $path_param . '/' . $file : $file;
        $owner = $file_meta[$meta_key] ?? 'System';
        
        $is_authorized = ($owner === $current_user || $owner === 'System');

        if ($is_authorized && file_exists($path)) {
            if (is_dir($path)) recursiveRemoveDir($path); else unlink($path);
            if (isset($file_meta[$meta_key])) unset($file_meta[$meta_key]);
            logActivity($current_user, "Purged asset: $file");
        } else { $all_success = false; }
    }
    saveDB($file_meta_db, $file_meta);
    if($all_success) echo json_encode(['status' => 'success']);
    else echo json_encode(['status' => 'error', 'message' => 'Akses Ditolak: Sebagian file gagal dihapus karena Anda bukan pemilik aslinya.']);
    exit;
}

if ($action === 'paste_files') {
    $files = json_decode($_POST['files'], true);
    $source_path = isset($_POST['source_path']) ? trim(sanitize($_POST['source_path']), '/') : '';
    $target_path = isset($_POST['target_path']) ? trim(sanitize($_POST['target_path']), '/') : '';
    $mode = $_POST['mode'];

    $base_src = ASSETS_DIR . ($source_path ? '/' . $source_path : ''); $base_tgt = ASSETS_DIR . ($target_path ? '/' . $target_path : '');
    $file_meta = getDB($file_meta_db);

    foreach($files as $file) {
        $file = sanitize($file); $src = $base_src . '/' . $file; $tgt = $base_tgt . '/' . $file;
        $src_meta_key = $source_path ? $source_path . '/' . $file : $file; $tgt_meta_key = $target_path ? $target_path . '/' . $file : $file;

        if(file_exists($src)) {
            if($mode === 'cut') {
                rename($src, $tgt);
                $file_meta[$tgt_meta_key] = $file_meta[$src_meta_key] ?? $current_user; unset($file_meta[$src_meta_key]);
            } else {
                if(is_dir($src)) recursiveCopy($src, $tgt); else copy($src, $tgt);
                $file_meta[$tgt_meta_key] = $current_user;
            }
        }
    }
    saveDB($file_meta_db, $file_meta); echo json_encode(['status' => 'success']); exit;
}

if ($action === 'delete_file') {
    $file = sanitize($_POST['file']); $path_param = isset($_POST['path']) ? trim(sanitize($_POST['path']), '/') : '';
    $path = ASSETS_DIR . ($path_param ? '/' . $path_param : '') . '/' . $file;

    $file_meta = getDB($file_meta_db); $meta_key = $path_param ? $path_param . '/' . $file : $file;
    $owner = $file_meta[$meta_key] ?? 'System';

    if ($owner !== 'System' && $owner !== $current_user) {
        exit(json_encode(['status' => 'error', 'message' => 'Akses Ditolak: Mutlak hanya pemilik asli ('.$owner.') yang bisa menghapus data ini.']));
    }

    if (file_exists($path)) {
        if (is_dir($path)) { recursiveRemoveDir($path); } else { unlink($path); }
        if (isset($file_meta[$meta_key])) { unset($file_meta[$meta_key]); saveDB($file_meta_db, $file_meta); }
        logActivity($current_user, "Purged asset: $file"); echo json_encode(['status' => 'success']);
    } else { echo json_encode(['status' => 'error', 'message' => 'Not found']); }
    exit;
}

if ($action === 'read_file') {
    $file = sanitize($_POST['file']); $path_param = isset($_POST['path']) ? trim(sanitize($_POST['path']), '/') : '';
    $path = ASSETS_DIR . ($path_param ? '/' . $path_param : '') . '/' . $file;
    if (file_exists($path) && is_file($path)) echo json_encode(['status' => 'success', 'content' => file_get_contents($path), 'modified' => date("Y-m-d H:i:s", filemtime($path)), 'size' => formatSize(filesize($path))]);
    exit;
}

if ($action === 'save_file') {
    $file = sanitize($_POST['file']); $content = $_POST['content']; $path_param = isset($_POST['path']) ? trim(sanitize($_POST['path']), '/') : '';
    if (file_put_contents(ASSETS_DIR . ($path_param ? '/' . $path_param : '') . '/' . $file, $content) !== false) { logActivity($current_user, "Modified source: $file"); echo json_encode(['status' => 'success']); }
    exit;
}

if ($action === 'list_notes') { 
    $notes = array_values(getDB($notes_db));
    foreach($notes as &$note) { $owner = $note['owner'] ?? 'System'; $note['avatar'] = !empty($users[$owner]['avatar']) ? $users[$owner]['avatar'] : "https://ui-avatars.com/api/?name=".urlencode($owner)."&background=0ea5e9&color=fff&rounded=true&bold=true"; }
    echo json_encode($notes); exit; 
}

if ($action === 'save_note') {
    $notes = getDB($notes_db); $id = $_POST['id'] ?: generateId();
    $raw_list = explode("\n", $_POST['text_list']); $parsed_list = [];
    foreach($raw_list as $line) { $cl = trim($line); if(empty($cl)) continue; $parsed_list[] = preg_replace('/^-->\s*/', '', $cl); }
    $title = sanitize($_POST['title']);
    $data = ['auth' => ['host' => sanitize($_POST['host']), 'user' => sanitize($_POST['user']), 'pass' => sanitize($_POST['pass']), 'dir' => sanitize($_POST['dir'])], 'list' => implode("\n", $parsed_list), 'status' => sanitize($_POST['status'] ?? 'active')];
    
    $existing_owner = isset($notes[$id]) ? $notes[$id]['owner'] : $current_user;
    $notes[$id] = ['id' => $id, 'title' => $title, 'owner' => $existing_owner, 'timestamp' => time(), 'data' => json_encode($data)];
    saveDB($notes_db, $notes); logActivity($current_user, "Configured Container: $title"); echo json_encode(['status' => 'success']); exit;
}

if ($action === 'delete_note') {
    $notes = getDB($notes_db); $id = sanitize($_POST['id']); 
    if (isset($notes[$id])) {
        $owner = $notes[$id]['owner'] ?? 'System';
        if ($owner !== 'System' && $owner !== $current_user) { exit(json_encode(['status' => 'error', 'message' => 'Mutlak hanya pemilik asli yang bisa menghapus.'])); }
        $title = $notes[$id]['title']; unset($notes[$id]); saveDB($notes_db, $notes); logActivity($current_user, "Purged Container: $title");
        echo json_encode(['status' => 'success']);
    }
    exit;
}

if ($action === 'list_cloaking') {
    $cloaks = getDB($cloaking_db);
    $filtered = array_filter($cloaks, function($c) use ($current_user, $current_role) { return $c['type'] === 'global' || $c['owner'] === $current_user || $current_role === 'owner' || $current_role === 'admin'; });
    $result = array_values($filtered);
    foreach($result as &$c) { $owner = $c['owner'] ?? 'System'; $c['avatar'] = !empty($users[$owner]['avatar']) ? $users[$owner]['avatar'] : "https://ui-avatars.com/api/?name=".urlencode($owner)."&background=0ea5e9&color=fff&rounded=true&bold=true"; }
    echo json_encode($result); exit;
}

if ($action === 'save_cloaking') {
    $cloaks = getDB($cloaking_db); $id = $_POST['id'] ?: generateId(); $domain = sanitize($_POST['domain']);
    $existing_owner = isset($cloaks[$id]) ? $cloaks[$id]['owner'] : $current_user;
    $cloaks[$id] = ['id' => $id, 'domain' => $domain, 'path' => sanitize($_POST['path']), 'content' => $_POST['content'], 'type' => sanitize($_POST['type']), 'owner' => $existing_owner, 'timestamp' => time()];
    saveDB($cloaking_db, $cloaks); logActivity($current_user, "Deployed Cloak for: $domain"); echo json_encode(['status' => 'success']); exit;
}

if ($action === 'delete_cloaking') {
    $cloaks = getDB($cloaking_db); $id = sanitize($_POST['id']); 
    if (isset($cloaks[$id])) {
        $owner = $cloaks[$id]['owner'] ?? 'System';
        if ($owner !== 'System' && $owner !== $current_user) { exit(json_encode(['status' => 'error', 'message' => 'Mutlak hanya pemilik asli yang bisa menghapus.'])); }
        $domain = $cloaks[$id]['domain']; unset($cloaks[$id]); saveDB($cloaking_db, $cloaks); logActivity($current_user, "Purged Cloak: $domain");
    }
    echo json_encode(['status' => 'success']); exit;
}

if ($action === 'list_users') {
    $output = []; foreach ($users as $uname => $data) $output[] = ['username' => $uname, 'role' => $data['role'], 'avatar' => $data['avatar'] ?? '', 'last_active' => $data['last_active'] ?? 0];
    echo json_encode($output); exit;
}

if ($action === 'add_user') {
    if ($current_role !== 'owner') exit(json_encode(['status' => 'error', 'message' => 'Only owners can add users.']));
    $new_user = sanitize($_POST['username']);
    if (!isset($users[$new_user])) {
        $users[$new_user] = ['password' => password_hash($_POST['password'], PASSWORD_DEFAULT), 'role' => sanitize($_POST['role']), 'avatar' => '', 'last_active' => time(), 'sec_q' => '', 'sec_a' => ''];
        saveDB($users_db, $users); logActivity($current_user, "Registered Identity: $new_user"); echo json_encode(['status' => 'success']);
    } else echo json_encode(['status' => 'error', 'message' => 'User exists']);
    exit;
}

if ($action === 'delete_user') {
    
    $target_user = sanitize($_POST['target_user']); $migrate_to = sanitize($_POST['migrate_to']); $auth_pass = $_POST['auth_pass'] ?? '';
    
    if ($target_user !== $current_user) exit(json_encode(['status' => 'error', 'message' => 'Akses Ditolak: Anda mutlak hanya bisa menghapus identitas akun Anda sendiri.']));
    if (!isset($users[$target_user])) exit(json_encode(['status' => 'error', 'message' => 'Target user not found.']));

    if ($migrate_to) {
        $file_meta = getDB($file_meta_db); foreach($file_meta as $fname => $owner) { if($owner === $target_user) $file_meta[$fname] = $migrate_to; } saveDB($file_meta_db, $file_meta);
        $notes = getDB($notes_db); foreach($notes as $nid => $ndata) { if($ndata['owner'] === $target_user) $notes[$nid]['owner'] = $migrate_to; } saveDB($notes_db, $notes);
        $cloaks = getDB($cloaking_db); foreach($cloaks as $cid => $cdata) { if($cdata['owner'] === $target_user) $cloaks[$cid]['owner'] = $migrate_to; } saveDB($cloaking_db, $cloaks);
        
        $old_dir = DIR_NOTEPAD . '/' . $target_user; $new_dir = DIR_NOTEPAD . '/' . $migrate_to;
        if (is_dir($old_dir)) {
            if (!is_dir($new_dir)) mkdir($new_dir, 0755, true);
            foreach(scandir($old_dir) as $f) { if ($f !== '.' && $f !== '..') rename($old_dir . '/' . $f, $new_dir . '/' . $f); }
            recursiveRemoveDir($old_dir);
        }
        logActivity($current_user, "Erased User: $target_user (Data migrated to $migrate_to)");
    } else {
        $old_dir = DIR_NOTEPAD . '/' . $target_user; if (is_dir($old_dir)) { recursiveRemoveDir($old_dir); }
        logActivity($current_user, "Erased User: $target_user (All data purged)");
    }
    unset($users[$target_user]); saveDB($users_db, $users); echo json_encode(['status' => 'success']); exit;
}

if ($action === 'update_profile') {
    $old_user = $current_user; $new_user = sanitize($_POST['username']); $pass = $_POST['password']; $avatar = $_POST['avatar']; 
    if (empty($new_user)) exit(json_encode(['status' => 'error', 'message' => 'Username cannot be empty']));
    if ($new_user !== $old_user && isset($users[$new_user])) exit(json_encode(['status' => 'error', 'message' => 'Username already taken']));

    $userData = $users[$old_user];
    if (!empty($pass)) $userData['password'] = password_hash($pass, PASSWORD_DEFAULT);
    if (!empty($avatar)) $userData['avatar'] = $avatar;

    if ($new_user !== $old_user) { unset($users[$old_user]); $users[$new_user] = $userData; $_SESSION['emerald_user'] = $new_user; } else { $users[$old_user] = $userData; }
    saveDB($users_db, $users); logActivity($new_user, "Updated Profile Identity"); echo json_encode(['status' => 'success', 'new_user' => $new_user]); exit;
}
?>