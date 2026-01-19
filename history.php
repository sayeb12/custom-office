<?php
require_once 'config.php';
header('Content-Type: application/json');

$type = $_GET['type'] ?? '';

$db = (new Database())->getConnection();
$query = "SELECT type, filename, timestamp, created_at FROM history";

if ($type) {
    $query .= " WHERE type = :type";
}

$query .= " ORDER BY timestamp DESC LIMIT 20";

$stmt = $db->prepare($query);

if ($type) {
    $stmt->bindParam(":type", $type);
}

$stmt->execute();
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format the response
$formattedHistory = array_map(function($item) {
    return [
        'type' => $item['type'],
        'filename' => $item['filename'],
        'timestamp' => $item['timestamp'],
        'date' => date('Y-m-d H:i:s', strtotime($item['created_at']))
    ];
}, $history);

echo json_encode($formattedHistory);
?>