<?php
session_start();

if (isset($_GET['type']) && isset($_GET['file'])) {
    $type = $_GET['type'];
    $filename = $_GET['file'];
    $format = $_GET['format'] ?? 'pdf';
    
    if ($type === 'document') {
        $filepath = 'data/documents/' . $filename;
        if (file_exists($filepath)) {
            $content = file_get_contents($filepath);
            
            switch ($format) {
                case 'pdf':
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment; filename="' . pathinfo($filename, PATHINFO_FILENAME) . '.pdf"');
                    // Convert HTML to PDF - you'd need a library like TCPDF or Dompdf
                    echo "PDF conversion would be implemented with a PDF library";
                    break;
                    
                case 'doc':
                    header('Content-Type: application/msword');
                    header('Content-Disposition: attachment; filename="' . pathinfo($filename, PATHINFO_FILENAME) . '.doc"');
                    echo "<html><body>" . $content . "</body></html>";
                    break;
                    
                case 'txt':
                    header('Content-Type: text/plain');
                    header('Content-Disposition: attachment; filename="' . pathinfo($filename, PATHINFO_FILENAME) . '.txt"');
                    echo strip_tags($content);
                    break;
                    
                case 'html':
                    header('Content-Type: text/html');
                    header('Content-Disposition: attachment; filename="' . pathinfo($filename, PATHINFO_FILENAME) . '.html"');
                    echo $content;
                    break;
            }
        }
    } elseif ($type === 'spreadsheet') {
        $filepath = 'data/spreadsheets/' . $filename;
        if (file_exists($filepath)) {
            $data = json_decode(file_get_contents($filepath), true);
            
            switch ($format) {
                case 'csv':
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="' . pathinfo($filename, PATHINFO_FILENAME) . '.csv"');
                    
                    $output = fopen('php://output', 'w');
                    foreach ($data['data'] as $row) {
                        fputcsv($output, $row);
                    }
                    fclose($output);
                    break;
                    
                case 'json':
                    header('Content-Type: application/json');
                    header('Content-Disposition: attachment; filename="' . pathinfo($filename, PATHINFO_FILENAME) . '.json"');
                    echo json_encode($data, JSON_PRETTY_PRINT);
                    break;
            }
        }
    }
}
?>