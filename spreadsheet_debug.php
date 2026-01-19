<?php
session_start();
require_once 'config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Debug: Starting spreadsheet.php<br>";

// Function definitions
function getColumnName($col) {
    $name = '';
    while ($col >= 0) {
        $name = chr(65 + ($col % 26)) . $name;
        $col = floor($col / 26) - 1;
    }
    return $name;
}

function getCellStyle($style) {
    if (!is_array($style)) return '';
    
    $styleString = '';
    if (isset($style['background-color'])) $styleString .= 'background-color: ' . $style['background-color'] . ';';
    if (isset($style['color'])) $styleString .= 'color: ' . $style['color'] . ';';
    if (isset($style['font-size'])) $styleString .= 'font-size: ' . $style['font-size'] . ';';
    if (isset($style['font-family'])) $styleString .= 'font-family: ' . $style['font-family'] . ';';
    if (isset($style['font-weight']) && $style['font-weight'] === 'bold') $styleString .= 'font-weight: bold;';
    if (isset($style['font-style']) && $style['font-style'] === 'italic') $styleString .= 'font-style: italic;';
    if (isset($style['text-decoration']) && $style['text-decoration'] === 'underline') $styleString .= 'text-decoration: underline;';
    if (isset($style['text-align'])) $styleString .= 'text-align: ' . $style['text-align'] . ';';
    return $styleString;
}

function isCellMerged($row, $col, $mergedRanges) {
    if (empty($mergedRanges) return false;
    foreach ($mergedRanges as $range) {
        if (is_array($range) && count($range) === 4) {
            list($startRow, $startCol, $endRow, $endCol) = $range;
            if ($row >= $startRow && $row <= $endRow && $col >= $startCol && $col <= $endCol) {
                return true;
            }
        }
    }
    return false;
}

function isMainMergeCell($row, $col, $mergedRanges) {
    if (empty($mergedRanges)) return false;
    foreach ($mergedRanges as $range) {
        if (is_array($range) && count($range) === 4) {
            list($startRow, $startCol, $endRow, $endCol) = $range;
            if ($row === $startRow && $col === $startCol) {
                return true;
            }
        }
    }
    return false;
}

function saveToHistory($type, $filename) {
    try {
        $db = (new Database())->getConnection();
        $query = "INSERT INTO history (type, filename, timestamp) VALUES (:type, :filename, :timestamp)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":type", $type);
        $stmt->bindParam(":filename", $filename);
        $timestamp = time();
        $stmt->bindParam(":timestamp", $timestamp);
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Error saving history: " . $e->getMessage());
    }
}

echo "Debug: Functions defined<br>";

// Main code
$filename = isset($_GET['file']) ? $_GET['file'] : 'new_spreadsheet_' . date('Y-m-d_H-i-s');
$isNew = !isset($_GET['file']);

echo "Debug: Filename: $filename, IsNew: " . ($isNew ? 'true' : 'false') . "<br>";

// Load existing data from database
$spreadsheetData = [];
$metadata = [
    'styles' => [], 
    'merged' => [], 
    'cols' => 15, 
    'rows' => 50
];

if (!$isNew) {
    try {
        echo "Debug: Loading existing file from database<br>";
        $db = (new Database())->getConnection();
        $query = "SELECT data FROM spreadsheets WHERE filename = :filename";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":filename", $filename);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "Debug: Found file in database<br>";
            $fileData = json_decode($row['data'], true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo "Debug: JSON decode error: " . json_last_error_msg() . "<br>";
                $fileData = [];
            }
            
            $spreadsheetData = $fileData['data'] ?? [];
            $metadata = array_merge($metadata, $fileData['metadata'] ?? []);
            echo "Debug: Data loaded successfully<br>";
        } else {
            echo "Debug: File not found in database<br>";
        }
    } catch (Exception $e) {
        echo "Debug: Error loading spreadsheet: " . $e->getMessage() . "<br>";
        error_log("Error loading spreadsheet: " . $e->getMessage());
    }
}

// Save to history
saveToHistory('spreadsheet', $filename);
echo "Debug: History saved<br>";

echo "Debug: Page loaded successfully. If you see this, the PHP is working.<br>";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($filename); ?> - Spreadsheet Editor</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <h1>Spreadsheet Debug Page</h1>
        <p>If you can see this, the PHP is working correctly.</p>
        <p>Filename: <?php echo htmlspecialchars($filename); ?></p>
        <p>Is New: <?php echo $isNew ? 'Yes' : 'No'; ?></p>
        <button onclick="window.location.href='index.php'">Back to Home</button>
    </div>
</body>
</html>