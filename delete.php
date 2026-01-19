<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['type']) || !isset($_POST['filename'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$type = $_POST['type'];
$filename = $_POST['filename'];

try {
    $db = (new Database())->getConnection();
    
    if ($type === 'document') {
        // Delete from documents table
        $query = "DELETE FROM documents WHERE filename = :filename";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":filename", $filename);
        $stmt->execute();
        
        // Also delete from history
        $query = "DELETE FROM history WHERE type = 'document' AND filename = :filename";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":filename", $filename);
        $stmt->execute();
        
    } elseif ($type === 'spreadsheet') {
        // Delete from spreadsheets table
        $query = "DELETE FROM spreadsheets WHERE filename = :filename";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":filename", $filename);
        $stmt->execute();
        
        // Also delete from history
        $query = "DELETE FROM history WHERE type = 'spreadsheet' AND filename = :filename";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":filename", $filename);
        $stmt->execute();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid file type']);
        exit;
    }
    
    echo json_encode(['success' => true, 'message' => 'File deleted successfully']);
    
} catch (PDOException $e) {
    error_log("Delete error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Delete error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>