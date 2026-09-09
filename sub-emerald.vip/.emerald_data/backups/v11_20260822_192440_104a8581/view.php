<?php
define('ALLOW_PUBLIC_VIEW', true);
require_once __DIR__ . '/core/config.php';

$requested_file = isset($_GET['f']) ? $_GET['f'] : null;

if (!$requested_file) {
    http_response_code(404);
    die("Asset not specified.");
}

$requested_file = str_replace(["\0", '../', '..\\'], '', $requested_file);
$file_basename = basename($requested_file);
$file_dirname = dirname($requested_file);

function findAssetFile($base_dir, $dir_name, $base_name) {
    if ($dir_name && $dir_name !== '.') {
        $target_dir = rtrim($base_dir, '/\\') . '/' . ltrim($dir_name, '/\\');
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
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Length: ' . filesize($found));
    readfile($found);
    exit;
} else {
    http_response_code(404);
    die("File not found or access denied.");
}
?>