<?php
/**
 * Emerald Central Hub V11 — Monitor module API.
 * Ported from V10: list_processes | kill_process | create_snapshot.
 * kill_process: owner/admin only (V10 rule).
 */
if (!defined('EMERALD_DISPATCH')) {
    http_response_code(403);
    exit;
}

if ($action === 'list_processes') {
    $process_data = array();
    $disabled = function_exists('ini_get') ? ini_get('disable_functions') : '';
    if (function_exists('shell_exec') && stripos($disabled, 'shell_exec') === false) {
        $out = @shell_exec('ps aux --sort=-%cpu | head -n 50');
        if ($out !== null && $out !== false) {
            $lines = explode("\n", trim($out));
            foreach ($lines as $i => $line) {
                if ($i === 0) { continue; }
                $cols = preg_split('/\s+/', $line, 11);
                if (count($cols) >= 11) {
                    $process_data[] = array(
                        'user' => $cols[0], 'pid' => $cols[1],
                        'cpu' => $cols[2], 'mem' => $cols[3],
                        'cmd' => htmlspecialchars($cols[10], ENT_QUOTES, 'UTF-8'),
                    );
                }
            }
        }
    }
    sec_json_out(array('status' => 'success', 'data' => $process_data));
}

if ($action === 'kill_process') {
    $pid = (int)(isset($_POST['pid']) ? $_POST['pid'] : 0);
    if ($current_role !== 'owner' && $current_role !== 'admin') {
        sec_json_err('Authorization failed.', 'forbidden');
    }
    if ($pid <= 0) { sec_json_err('Invalid PID.', 'error'); }
    $disabled = function_exists('ini_get') ? ini_get('disable_functions') : '';
    if (function_exists('shell_exec') && stripos($disabled, 'shell_exec') === false) {
        @shell_exec('kill -9 ' . (int)$pid);
        logActivity($current_user, 'Executed SIGKILL on PID: ' . $pid);
        sec_json_out(array('status' => 'success'));
    }
    sec_json_err('Exec function disabled by host.', 'error');
}

if ($action === 'create_snapshot') {
    if (!class_exists('ZipArchive')) { sec_json_err('Zip support is not available on this host.', 'error'); }
    $zipPath = ASSETS_DIR . '/System_Snapshot_' . date('Y-m-d_H-i-s') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        $path = DATA_DIR;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($it as $f) {
            if (!$f->isDir()) {
                $filePath = $f->getRealPath();
                $relativePath = '.emerald_data/' . substr($filePath, strlen($path) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
        $zip->close();
        $file_meta = db_load('file_meta');
        $file_meta[basename($zipPath)] = $current_user;
        db_save('file_meta', $file_meta);
        logActivity($current_user, 'Generated System Snapshot.');
        sec_json_out(array('status' => 'success', 'file' => basename($zipPath)));
    }
    sec_json_err('Failed to build zip architecture.', 'error');
}

sec_json_err('Unknown action.', 'notfound');
