<?php
header('Content-Type: application/json');

function getFiles($type) {
    $files = [];
    $directory = 'data/' . $type . '/';
    
    if (is_dir($directory)) {
        $fileList = scandir($directory);
        foreach ($fileList as $file) {
            if ($file !== '.' && $file !== '..') {
                $filePath = $directory . $file;
                $files[] = [
                    'name' => $file,
                    'modified' => date('Y-m-d H:i:s', filemtime($filePath)),
                    'size' => filesize($filePath)
                ];
            }
        }
    }
    
    // Sort by modification time (newest first)
    usort($files, function($a, $b) {
        return strtotime($b['modified']) - strtotime($a['modified']);
    });
    
    return array_slice($files, 0, 10); // Return only 10 most recent files
}

$type = $_GET['type'] ?? '';
if (in_array($type, ['documents', 'spreadsheets'])) {
    echo json_encode(getFiles($type));
} else {
    echo json_encode([]);
}
?>