<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

$db = (new Database())->getConnection();

// GET /document_revisions.php?filename=...   -> list revisions
// GET /document_revisions.php?id=...        -> single revision

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id'])) {
        $id = (int) $_GET['id'];
        $stmt = $db->prepare("SELECT id, filename, content_json, created_at FROM document_revisions WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Revision not found']);
            exit;
        }

        $contentJson = json_decode($row['content_json'], true) ?: [];

        echo json_encode([
            'id' => (int) $row['id'],
            'filename' => $row['filename'],
            'created_at' => $row['created_at'],
            'content' => $contentJson,
        ]);
        exit;
    }

    $filename = $_GET['filename'] ?? '';
    if ($filename === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Filename is required']);
        exit;
    }

    $stmt = $db->prepare("SELECT id, created_at FROM document_revisions WHERE filename = :filename ORDER BY created_at DESC, id DESC");
    $stmt->bindParam(':filename', $filename);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $revisions = [];
    foreach ($rows as $row) {
        $revisions[] = [
            'id' => (int) $row['id'],
            'created_at' => $row['created_at'],
        ];
    }

    echo json_encode($revisions);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>

