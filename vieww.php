<?php
require_once __DIR__ . '/core/config.php';

$requested_file = isset($_GET['f']) ? basename($_GET['f']) : null;

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
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Length: ' . filesize($found));
    readfile($found);
    exit;
} else {
    http_response_code(404);
    die("File not found or access denied.");
}
?>