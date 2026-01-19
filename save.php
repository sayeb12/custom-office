<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    
    if ($type === 'document') {
        $filename = $_POST['filename'] ?? '';
        $contentHtml = $_POST['content_html'] ?? ($_POST['content'] ?? '');
        $contentJson = $_POST['content_json'] ?? '';
        $isNew = $_POST['isNew'] ?? false;
        $oldFilename = $_POST['oldFilename'] ?? '';
        $isAutosave = isset($_POST['autosave']) && $_POST['autosave'] === 'true';
        
        if (empty($filename)) {
            echo json_encode(['success' => false, 'message' => 'Filename is required']);
            exit;
        }
        
        // Ensure .txt extension for documents
        if (!preg_match('/\.txt$/i', $filename)) {
            $filename .= '.txt';
        }
        
        $db = (new Database())->getConnection();

        // Sanitize HTML content to prevent XSS
        $cleanHtml = sanitize_html($contentHtml);
        
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
        $stmt->bindParam(":content", $cleanHtml);
        
        if ($stmt->execute()) {
            // On manual save, store a revision snapshot
            if (!$isAutosave && $contentJson !== '') {
                saveDocumentRevision($db, $filename, $contentJson);
            }

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

function saveDocumentRevision(PDO $db, $filename, $contentJson) {
    $query = "INSERT INTO document_revisions (filename, content_json) VALUES (:filename, :content_json)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':filename', $filename);
    $stmt->bindParam(':content_json', $contentJson);
    $stmt->execute();
}

function sanitize_html($html) {
    if ($html === '') {
        return '';
    }

    // Basic removal of script/style tags
    $html = preg_replace('#<\s*(script|style)[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html);

    if (!class_exists('DOMDocument')) {
        // Fallback: strip event handlers and javascript: URLs via regex
        $html = preg_replace('/on\w+="[^"]*"/i', '', $html);
        $html = preg_replace("/on\w+='[^']*'/i", '', $html);
        $html = preg_replace('#(href|src)\s*=\s*"(javascript:[^"]*)"#i', '$1="#"', $html);
        $html = preg_replace("#(href|src)\s*=\s*'(javascript:[^']*)'#i", '$1=\"#\"', $html);
        return $html;
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    // Remove script and event handler attributes
    $xpath = new DOMXPath($dom);

    foreach ($xpath->query('//script') as $node) {
        $node->parentNode->removeChild($node);
    }

    foreach ($xpath->query('//@*') as $attr) {
        if (stripos($attr->nodeName, 'on') === 0) {
            $attr->ownerElement->removeAttributeNode($attr);
            continue;
        }

        if (in_array(strtolower($attr->nodeName), ['href', 'src'], true)) {
            if (preg_match('/^\s*javascript:/i', $attr->nodeValue)) {
                $attr->ownerElement->setAttribute($attr->nodeName, '#');
            }
        }
    }

    $clean = $dom->saveHTML();
    libxml_clear_errors();

    return $clean;
}
?>
