<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    
    if ($type === 'document') {
        $filename = $_POST['filename'] ?? '';
        $content = $_POST['content'] ?? '';
        $isNew = $_POST['isNew'] ?? false;
        $oldFilename = $_POST['oldFilename'] ?? '';
        
        if (empty($filename)) {
            echo json_encode(['success' => false, 'message' => 'Filename is required']);
            exit;
        }
        
        // Ensure .txt extension for documents
        if (!preg_match('/\.txt$/i', $filename)) {
            $filename .= '.txt';
        }
        
        $db = (new Database())->getConnection();
        
        // Delete old file if renaming
        if (!$isNew && $oldFilename && $oldFilename !== $filename) {
            $query = "DELETE FROM documents WHERE filename = :oldFilename";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":oldFilename", $oldFilename);
            $stmt->execute();
        }
        
        // Check if file exists
        $query = "SELECT id FROM documents WHERE filename = :filename";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":filename", $filename);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Update existing
            $query = "UPDATE documents SET content = :content, updated_at = NOW() WHERE filename = :filename";
        } else {
            // Insert new
            $query = "INSERT INTO documents (filename, content) VALUES (:filename, :content)";
        }
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":filename", $filename);
        $stmt->bindParam(":content", $content);
        
        if ($stmt->execute()) {
            // Save to history
            saveToHistory('document', $filename);
            echo json_encode(['success' => true, 'filename' => $filename]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not save file to database']);
        }
        
    } elseif ($type === 'spreadsheet') {
        $filename = $_POST['filename'] ?? '';
        $data = $_POST['data'] ?? '{}';
        $isNew = $_POST['isNew'] ?? false;
        $oldFilename = $_POST['oldFilename'] ?? '';
        
        if (empty($filename)) {
            echo json_encode(['success' => false, 'message' => 'Filename is required']);
            exit;
        }
        
        // Ensure .json extension for spreadsheets
        if (!preg_match('/\.json$/i', $filename)) {
            $filename .= '.json';
        }
        
        $db = (new Database())->getConnection();
        
        // Delete old file if renaming
        if (!$isNew && $oldFilename && $oldFilename !== $filename) {
            $query = "DELETE FROM spreadsheets WHERE filename = :oldFilename";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":oldFilename", $oldFilename);
            $stmt->execute();
        }
        
        // Check if file exists
        $query = "SELECT id FROM spreadsheets WHERE filename = :filename";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":filename", $filename);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Update existing
            $query = "UPDATE spreadsheets SET data = :data, updated_at = NOW() WHERE filename = :filename";
        } else {
            // Insert new
            $query = "INSERT INTO spreadsheets (filename, data) VALUES (:filename, :data)";
        }
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":filename", $filename);
        $stmt->bindParam(":data", $data);
        
        if ($stmt->execute()) {
            // Save to history
            saveToHistory('spreadsheet', $filename);
            echo json_encode(['success' => true, 'filename' => $filename]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not save spreadsheet to database']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid file type']);
    }
}

function saveToHistory($type, $filename) {
    $db = (new Database())->getConnection();
    $query = "INSERT INTO history (type, filename, timestamp) VALUES (:type, :filename, :timestamp)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":type", $type);
    $stmt->bindParam(":filename", $filename);
    $timestamp = time();
    $stmt->bindParam(":timestamp", $timestamp);
    $stmt->execute();
}
?>