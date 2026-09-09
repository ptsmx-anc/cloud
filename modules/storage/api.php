<?php
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
if (!defined('EMERALD_DISPATCH')) {
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
    $full = ($clean === '') ? $base : $base . '/' . $clean;
    $rp = realpath($full);
    if ($rp === false) { $rp = $full; }
    if (strpos($rp, $base) !== 0) { return null; }
    return $rp;
}

/* ------------------------------------------------------------------ *
 *  list_files
 * ------------------------------------------------------------------ */
if ($action === 'list_files') {
    $path_param = isset($_GET['path']) ? trim(sec_str($_GET['path']), '/') : '';
    $scan_dir = stg_resolve($path_param);
    if ($scan_dir === null) {
        sec_json_err('Invalid path.', 'error');
    }
    $files = array();
    $file_meta = db_load('file_meta');
    if (is_dir($scan_dir)) {
        $dir = new DirectoryIterator($scan_dir);
        foreach ($dir as $fileinfo) {
            if ($fileinfo->isDot()) { continue; }
            if ($fileinfo->getFilename() === 'notepad' || $fileinfo->getFilename() === '.htaccess') { continue; }
            $filename = $fileinfo->getFilename();
            $meta_key = $path_param ? $path_param . '/' . $filename : $filename;
            $owner = isset($file_meta[$meta_key]) ? $file_meta[$meta_key] : 'System';
            $is_dir = $fileinfo->isDir();
            $files[] = array(
                'name' => $filename,
                'ext' => $is_dir ? 'DIR' : pathinfo($filename, PATHINFO_EXTENSION),
                'size' => formatSize($fileinfo->getSize()),
                'modified' => date('Y-m-d H:i:s', $fileinfo->getMTime()),
                'is_dir' => $is_dir,
                'owner' => $owner,
                'link_name' => pathinfo($filename, PATHINFO_FILENAME),
            );
        }
    }
    sec_json_out(array('path' => $path_param, 'files' => array_values($files)));
}

/* ------------------------------------------------------------------ *
 *  upload
 * ------------------------------------------------------------------ */
if ($action === 'upload') {
    if (!empty($_FILES)) {
        $path_param = isset($_POST['path']) ? trim(sec_str($_POST['path']), '/') : '';
        $relative_path = isset($_POST['relative_path']) ? trim(sec_str($_POST['relative_path']), '/') : '';
        $base_dir = stg_resolve($path_param);
        if ($base_dir === null) { sec_json_err('Invalid path.', 'error'); }
        if (!is_dir($base_dir)) { @mkdir($base_dir, 0755, true); }

        if ($relative_path !== '' && strpos($relative_path, '/') !== false) {
            $sub_dir = dirname($relative_path);
            $target_dir = $base_dir . '/' . $sub_dir;
            if (!is_dir($target_dir)) { @mkdir($target_dir, 0755, true); }
            if (stg_resolve($path_param ? $path_param . '/' . $sub_dir : $sub_dir) === null) { sec_json_err('Invalid path.', 'error'); }
            $meta_name = $sub_dir . '/' . basename($relative_path);
            $name = basename($relative_path);
        } else {
            if (!is_dir($base_dir)) { @mkdir($base_dir, 0755, true); }
            $name = sec_str($_FILES['file']['name']);
            $name = str_replace(array('/', '\\', "\0"), '', $name);
            if ($name === '') { sec_json_err('Invalid filename.', 'error'); }
            $meta_name = $name;
        }

        if (move_uploaded_file($_FILES['file']['tmp_name'], $base_dir . '/' . $name)) {
            $final_meta_key = $path_param ? $path_param . '/' . $meta_name : $meta_name;
            $file_meta = db_load('file_meta');
            $file_meta[$final_meta_key] = isset($file_meta[$final_meta_key]) ? $file_meta[$final_meta_key] : $current_user;
            db_save('file_meta', $file_meta);
            logActivity($current_user, 'Uploaded asset: ' . $final_meta_key);
            sec_json_out(array('status' => 'success'));
        }
        sec_json_err('Upload failed.', 'error');
    }
    sec_json_err('No file received.', 'error');
}

/* ------------------------------------------------------------------ *
 *  create_folder / create_file
 * ------------------------------------------------------------------ */
if ($action === 'create_folder' || $action === 'create_file') {
    $target_name = isset($_POST['name']) ? $_POST['name'] : (isset($_POST['folder']) ? $_POST['folder'] : (isset($_POST['file']) ? $_POST['file'] : ''));
    $target_name = sec_str($target_name);
    $target_name = str_replace(array('/', '\\', "\0"), '', $target_name);
    $path_param = isset($_POST['path']) ? trim(sec_str($_POST['path']), '/') : '';
    $base_dir = stg_resolve($path_param);
    if ($base_dir === null) { sec_json_err('Invalid path.', 'error'); }
    if ($target_name === '') { sec_json_err('Name required.', 'error'); }
    $path = $base_dir . '/' . $target_name;

    if (!file_exists($path)) {
        if ($action === 'create_folder') {
            @mkdir($path, 0755);
            logActivity($current_user, 'Created directory: ' . $target_name);
        } else {
            @file_put_contents($path, '');
            logActivity($current_user, 'Created file: ' . $target_name);
        }
        $meta_key = $path_param ? $path_param . '/' . $target_name : $target_name;
        $file_meta = db_load('file_meta');
        $file_meta[$meta_key] = isset($file_meta[$meta_key]) ? $file_meta[$meta_key] : $current_user;
        db_save('file_meta', $file_meta);
        sec_json_out(array('status' => 'success'));
    }
    sec_json_err('Target already exists.', 'error');
}

/* ------------------------------------------------------------------ *
 *  rename_file
 * ------------------------------------------------------------------ */
if ($action === 'rename_file') {
    $old_name = sec_str(isset($_POST['old_name']) ? $_POST['old_name'] : '');
    $new_name = sec_str(isset($_POST['new_name']) ? $_POST['new_name'] : '');
    $old_name = str_replace(array('/', '\\', "\0"), '', $old_name);
    $new_name = str_replace(array('/', '\\', "\0"), '', $new_name);
    $path_param = isset($_POST['path']) ? trim(sec_str($_POST['path']), '/') : '';
    $base_dir = stg_resolve($path_param);
    if ($base_dir === null) { sec_json_err('Invalid path.', 'error'); }

    if ($old_name !== '' && $new_name !== '' && file_exists($base_dir . '/' . $old_name) && !file_exists($base_dir . '/' . $new_name)) {
        if (@rename($base_dir . '/' . $old_name, $base_dir . '/' . $new_name)) {
            $file_meta = db_load('file_meta');
            $old_k = $path_param ? $path_param . '/' . $old_name : $old_name;
            $new_k = $path_param ? $path_param . '/' . $new_name : $new_name;
            $file_meta[$new_k] = isset($file_meta[$old_k]) ? $file_meta[$old_k] : $current_user;
            if (isset($file_meta[$old_k])) { unset($file_meta[$old_k]); }
            db_save('file_meta', $file_meta);
            logActivity($current_user, 'Renamed asset from ' . $old_name . ' to ' . $new_name);
            sec_json_out(array('status' => 'success'));
        }
        sec_json_err('Permission denied.', 'error');
    }
    sec_json_err('Not found or target exists.', 'error');
}

/* ------------------------------------------------------------------ *
 *  zip_file / unzip_file
 * ------------------------------------------------------------------ */
if ($action === 'zip_file' || $action === 'unzip_file') {
    if (!class_exists('ZipArchive')) { sec_json_err('Zip support is not available on this host.', 'error'); }
    $file = sec_str(isset($_POST['file']) ? $_POST['file'] : '');
    $file = str_replace(array('/', '\\', "\0"), '', $file);
    $path_param = isset($_POST['path']) ? trim(sec_str($_POST['path']), '/') : '';
    $base_dir = stg_resolve($path_param);
    if ($base_dir === null) { sec_json_err('Invalid path.', 'error'); }
    $path = $base_dir . '/' . $file;

    if (file_exists($path)) {
        if ($action === 'zip_file') {
            $zipPath = $path . '.zip';
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
                $meta_key = $path_param ? $path_param . '/' . basename($zipPath) : basename($zipPath);
                $file_meta = db_load('file_meta');
                $file_meta[$meta_key] = isset($file_meta[$meta_key]) ? $file_meta[$meta_key] : $current_user;
                db_save('file_meta', $file_meta);
                logActivity($current_user, 'Compressed archive: ' . basename($zipPath));
                sec_json_out(array('status' => 'success'));
            }
            sec_json_err('Failed to create archive.', 'error');
        } else {
            $zip = new ZipArchive();
            if ($zip->open($path) === true) {
                $extract_name = pathinfo($path, PATHINFO_FILENAME);
                $extract_path = dirname($path) . '/' . $extract_name;
                if (!is_dir($extract_path)) { @mkdir($extract_path, 0755); }
                $zip->extractTo($extract_path);
                $zip->close();
                $meta_key = $path_param ? $path_param . '/' . $extract_name : $extract_name;
                $file_meta = db_load('file_meta');
                $file_meta[$meta_key] = isset($file_meta[$meta_key]) ? $file_meta[$meta_key] : $current_user;
                db_save('file_meta', $file_meta);
                logActivity($current_user, 'Extracted archive: ' . $file);
                sec_json_out(array('status' => 'success'));
            }
            sec_json_err('Failed to open archive.', 'error');
        }
    }
    sec_json_err('File not found.', 'error');
}

/* ------------------------------------------------------------------ *
 *  multi_delete
 * ------------------------------------------------------------------ */
if ($action === 'multi_delete') {
    $files = json_decode(isset($_POST['files']) ? $_POST['files'] : '[]', true);
    if (!is_array($files)) { $files = array(); }
    $path_param = isset($_POST['path']) ? trim(sec_str($_POST['path']), '/') : '';
    $base_dir = stg_resolve($path_param);
    if ($base_dir === null) { sec_json_err('Invalid path.', 'error'); }

    $file_meta = db_load('file_meta');
    $all_success = true;
    foreach ($files as $file) {
        $file = sec_str($file);
        $file = str_replace(array('/', '\\', "\0"), '', $file);
        $path = $base_dir . '/' . $file;
        $meta_key = $path_param ? $path_param . '/' . $file : $file;
        $owner = isset($file_meta[$meta_key]) ? $file_meta[$meta_key] : 'System';
        $is_authorized = ($owner === $current_user || $owner === 'System');
        if ($is_authorized && file_exists($path)) {
            if (is_dir($path)) { recursiveRemoveDir($path); } else { @unlink($path); }
            if (isset($file_meta[$meta_key])) { unset($file_meta[$meta_key]); }
            logActivity($current_user, 'Purged asset: ' . $file);
        } else {
            $all_success = false;
        }
    }
    db_save('file_meta', $file_meta);
    if ($all_success) { sec_json_out(array('status' => 'success')); }
    sec_json_err('Akses Ditolak: Sebagian file gagal dihapus karena Anda bukan pemilik aslinya.', 'error');
}

/* ------------------------------------------------------------------ *
 *  paste_files (cut / copy)
 * ------------------------------------------------------------------ */
if ($action === 'paste_files') {
    $files = json_decode(isset($_POST['files']) ? $_POST['files'] : '[]', true);
    if (!is_array($files)) { $files = array(); }
    $source_path = isset($_POST['source_path']) ? trim(sec_str($_POST['source_path']), '/') : '';
    $target_path = isset($_POST['target_path']) ? trim(sec_str($_POST['target_path']), '/') : '';
    $mode = isset($_POST['mode']) ? sec_str($_POST['mode']) : 'copy';

    $base_src = stg_resolve($source_path);
    $base_tgt = stg_resolve($target_path);
    if ($base_src === null || $base_tgt === null) { sec_json_err('Invalid path.', 'error'); }

    $file_meta = db_load('file_meta');
    foreach ($files as $file) {
        $file = sec_str($file);
        $file = str_replace(array('/', '\\', "\0"), '', $file);
        $src = $base_src . '/' . $file;
        $tgt = $base_tgt . '/' . $file;
        $src_meta_key = $source_path ? $source_path . '/' . $file : $file;
        $tgt_meta_key = $target_path ? $target_path . '/' . $file : $file;

        if (file_exists($src)) {
            if ($mode === 'cut') {
                @rename($src, $tgt);
                $file_meta[$tgt_meta_key] = isset($file_meta[$src_meta_key]) ? $file_meta[$src_meta_key] : $current_user;
                if (isset($file_meta[$src_meta_key])) { unset($file_meta[$src_meta_key]); }
            } else {
                if (is_dir($src)) { recursiveCopy($src, $tgt); } else { @copy($src, $tgt); }
                $file_meta[$tgt_meta_key] = $current_user;
            }
        }
    }
    db_save('file_meta', $file_meta);
    sec_json_out(array('status' => 'success'));
}

/* ------------------------------------------------------------------ *
 *  delete_file
 * ------------------------------------------------------------------ */
if ($action === 'delete_file') {
    $file = sec_str(isset($_POST['file']) ? $_POST['file'] : '');
    $file = str_replace(array('/', '\\', "\0"), '', $file);
    $path_param = isset($_POST['path']) ? trim(sec_str($_POST['path']), '/') : '';
    $base_dir = stg_resolve($path_param);
    if ($base_dir === null) { sec_json_err('Invalid path.', 'error'); }
    $path = $base_dir . '/' . $file;

    $file_meta = db_load('file_meta');
    $meta_key = $path_param ? $path_param . '/' . $file : $file;
    $owner = isset($file_meta[$meta_key]) ? $file_meta[$meta_key] : 'System';

    if ($owner !== 'System' && $owner !== $current_user) {
        sec_json_err('Akses Ditolak: Mutlak hanya pemilik asli (' . $owner . ') yang bisa menghapus data ini.', 'error');
    }

    if (file_exists($path)) {
        if (is_dir($path)) { recursiveRemoveDir($path); } else { @unlink($path); }
        if (isset($file_meta[$meta_key])) {
            unset($file_meta[$meta_key]);
            db_save('file_meta', $file_meta);
        }
        logActivity($current_user, 'Purged asset: ' . $file);
        sec_json_out(array('status' => 'success'));
    }
    sec_json_err('Not found.', 'error');
}

/* ------------------------------------------------------------------ *
 *  read_file
 * ------------------------------------------------------------------ */
if ($action === 'read_file') {
    $file = sec_str(isset($_POST['file']) ? $_POST['file'] : '');
    $file = str_replace(array('/', '\\', "\0"), '', $file);
    $path_param = isset($_POST['path']) ? trim(sec_str($_POST['path']), '/') : '';
    $base_dir = stg_resolve($path_param);
    if ($base_dir === null) { sec_json_err('Invalid path.', 'error'); }
    $path = $base_dir . '/' . $file;
    if (file_exists($path) && is_file($path)) {
        sec_json_out(array(
            'status' => 'success',
            'content' => (string)file_get_contents($path),
            'modified' => date('Y-m-d H:i:s', filemtime($path)),
            'size' => formatSize(filesize($path)),
        ));
    }
    sec_json_err('File not found.', 'error');
}

/* ------------------------------------------------------------------ *
 *  save_file
 * ------------------------------------------------------------------ */
if ($action === 'save_file') {
    $file = sec_str(isset($_POST['file']) ? $_POST['file'] : '');
    $file = str_replace(array('/', '\\', "\0"), '', $file);
    $content = isset($_POST['content']) ? $_POST['content'] : '';
    $path_param = isset($_POST['path']) ? trim(sec_str($_POST['path']), '/') : '';
    $base_dir = stg_resolve($path_param);
    if ($base_dir === null) { sec_json_err('Invalid path.', 'error'); }
    if ($file === '') { sec_json_err('Filename required.', 'error'); }
    $path = $base_dir . '/' . $file;
    if (@file_put_contents($path, $content) !== false) {
        logActivity($current_user, 'Modified source: ' . $file);
        sec_json_out(array('status' => 'success'));
    }
    sec_json_err('Could not save file.', 'error');
}

sec_json_err('Unknown action.', 'notfound');
