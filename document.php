<?php
session_start();
require_once 'config.php';

$filename = isset($_GET['file']) ? $_GET['file'] : 'new_document_' . date('Y-m-d_H-i-s');
$isNew = !isset($_GET['file']);

// Load existing content from database
$content = '';
if (!$isNew) {
    $db = (new Database())->getConnection();
    $query = "SELECT content FROM documents WHERE filename = :filename";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":filename", $filename);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $content = $row['content'];
    }
}

// Save to history
saveToHistory('document', $filename);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($filename); ?> - Document Editor</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Resizable table styles */
        .resizable-table {
            position: relative;
            margin: 1rem 0;
        }
        
        .resizable-table table {
            border-collapse: collapse;
            width: 100%;
        }
        
        .resize-handle {
            position: absolute;
            background: #007bff;
            opacity: 0;
            transition: opacity 0.2s;
            z-index: 10;
        }
        
        .resize-handle:hover {
            opacity: 1;
        }
        
        .resize-handle.right {
            top: 0;
            right: -4px;
            width: 8px;
            height: 100%;
            cursor: col-resize;
        }
        
        .resize-handle.bottom {
            bottom: -4px;
            left: 0;
            width: 100%;
            height: 8px;
            cursor: row-resize;
        }
        
        .resize-handle.corner {
            bottom: -6px;
            right: -6px;
            width: 12px;
            height: 12px;
            cursor: nwse-resize;
            background: #0056b3;
            border-radius: 2px;
        }
        
        /* Image upload styles */
        .image-upload-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 10000;
        }
        
        .image-upload-modal {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        
        .upload-options {
            display: flex;
            gap: 10px;
            margin: 1rem 0;
        }
        
        .upload-option {
            flex: 1;
            padding: 1rem;
            border: 2px dashed #ddd;
            border-radius: 6px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .upload-option:hover {
            border-color: #007bff;
            background: #f8f9fa;
        }
        
        .upload-option i {
            font-size: 2rem;
            color: #007bff;
            margin-bottom: 0.5rem;
        }
        
        #imageFile {
            display: none;
        }
        
        .image-preview {
            max-width: 100%;
            max-height: 200px;
            margin: 1rem 0;
            display: none;
        }
    </style>
</head>
<body>
    <!-- Image Upload Modal -->
    <div class="image-upload-container" id="imageUploadContainer">
        <div class="image-upload-modal">
            <h3>Insert Image</h3>
            <div class="upload-options">
                <div class="upload-option" onclick="document.getElementById('imageFile').click()">
                    <i class="fas fa-upload"></i>
                    <div>Upload from Device</div>
                </div>
                <div class="upload-option" onclick="showUrlInput()">
                    <i class="fas fa-link"></i>
                    <div>From URL</div>
                </div>
            </div>
            
            <div id="urlInputContainer" style="display: none;">
                <input type="text" id="imageUrl" placeholder="Enter image URL" class="form-control" style="width: 100%; padding: 8px; margin: 10px 0;">
                <button onclick="insertImageFromUrl()" class="btn btn-primary">Insert Image</button>
            </div>
            
            <img id="imagePreview" class="image-preview" alt="Preview">
            
            <input type="file" id="imageFile" accept="image/*" onchange="previewImage(this)">
            
            <div style="margin-top: 1rem; display: flex; gap: 10px; justify-content: flex-end;">
                <button onclick="closeImageUpload()" class="btn btn-secondary">Cancel</button>
                <button onclick="insertImageFromFile()" class="btn btn-primary" id="insertImageBtn" disabled>Insert Image</button>
            </div>
        </div>
    </div>

    <div class="container">
        <header class="editor-header">
            <div class="editor-toolbar">
                <button onclick="saveDocument()" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save
                </button>
                <button onclick="showDownloadModal()" class="btn btn-success">
                    <i class="fas fa-download"></i> Download
                </button>
                <button onclick="printDocument()" class="btn btn-info">
                    <i class="fas fa-print"></i> Print
                </button>
                <button onclick="showHistory()" class="btn btn-secondary">
                    <i class="fas fa-history"></i> History
                </button>
                <button onclick="window.location.href='index.php'" class="btn btn-warning">
                    <i class="fas fa-home"></i> Home
                </button>
                <input type="text" id="filename" value="<?php echo htmlspecialchars($filename); ?>" placeholder="Enter filename">
            </div>
            
            <div class="format-toolbar">
                <select id="fontFamily" onchange="formatText('fontName', this.value)">
                    <option value="Arial">Arial</option>
                    <option value="Helvetica">Helvetica</option>
                    <option value="Times New Roman">Times New Roman</option>
                    <option value="Georgia">Georgia</option>
                    <option value="Verdana">Verdana</option>
                    <option value="Courier New">Courier New</option>
                    <option value="Trebuchet MS">Trebuchet MS</option>
                    <option value="Comic Sans MS">Comic Sans MS</option>
                </select>
                
                <select id="fontSize" onchange="formatText('fontSize', this.value)">
                    <option value="1">8pt</option>
                    <option value="2">10pt</option>
                    <option value="3" selected>12pt</option>
                    <option value="4">14pt</option>
                    <option value="5">18pt</option>
                    <option value="6">24pt</option>
                    <option value="7">36pt</option>
                </select>
                
                <button onclick="formatText('bold')" class="format-btn" title="Bold (Ctrl+B)" id="boldBtn">
                    <i class="fas fa-bold"></i>
                </button>
                <button onclick="formatText('italic')" class="format-btn" title="Italic (Ctrl+I)" id="italicBtn">
                    <i class="fas fa-italic"></i>
                </button>
                <button onclick="formatText('underline')" class="format-btn" title="Underline (Ctrl+U)" id="underlineBtn">
                    <i class="fas fa-underline"></i>
                </button>
                <button onclick="formatText('strikeThrough')" class="format-btn" title="Strikethrough">
                    <i class="fas fa-strikethrough"></i>
                </button>
                
                <input type="color" id="fontColor" onchange="formatText('foreColor', this.value)" title="Text Color">
                <input type="color" id="bgColor" onchange="formatText('hiliteColor', this.value)" title="Background Color">
                
                <button onclick="formatText('justifyLeft')" class="format-btn" title="Align Left">
                    <i class="fas fa-align-left"></i>
                </button>
                <button onclick="formatText('justifyCenter')" class="format-btn" title="Align Center">
                    <i class="fas fa-align-center"></i>
                </button>
                <button onclick="formatText('justifyRight')" class="format-btn" title="Align Right">
                    <i class="fas fa-align-right"></i>
                </button>
                <button onclick="formatText('justifyFull')" class="format-btn" title="Justify">
                    <i class="fas fa-align-justify"></i>
                </button>
                
                <button onclick="formatText('insertUnorderedList')" class="format-btn" title="Bullet List">
                    <i class="fas fa-list-ul"></i>
                </button>
                <button onclick="formatText('insertOrderedList')" class="format-btn" title="Numbered List">
                    <i class="fas fa-list-ol"></i>
                </button>
                
                <button onclick="formatText('outdent')" class="format-btn" title="Decrease Indent">
                    <i class="fas fa-outdent"></i>
                </button>
                <button onclick="formatText('indent')" class="format-btn" title="Increase Indent">
                    <i class="fas fa-indent"></i>
                </button>
                
                <button onclick="showImageUpload()" class="format-btn" title="Insert Image">
                    <i class="fas fa-image"></i>
                </button>
                <button onclick="createResizableTable()" class="format-btn" title="Insert Table">
                    <i class="fas fa-table"></i>
                </button>
                <button onclick="insertLink()" class="format-btn" title="Insert Link">
                    <i class="fas fa-link"></i>
                </button>
                
                <button onclick="formatText('formatBlock', '<h1>')" class="format-btn" title="Heading 1">
                    H1
                </button>
                <button onclick="formatText('formatBlock', '<h2>')" class="format-btn" title="Heading 2">
                    H2
                </button>
                <button onclick="formatText('formatBlock', '<h3>')" class="format-btn" title="Heading 3">
                    H3
                </button>
                <button onclick="formatText('formatBlock', '<p>')" class="format-btn" title="Paragraph">
                    P
                </button>
            </div>
        </header>

        <div class="editor-container">
            <div 
                id="documentEditor" 
                contenteditable="true" 
                class="document-editor"
                oninput="autoSave()"
                onkeydown="handleKeyDown(event)"
                onmouseup="updateFormatButtons()"
            >
                <?php 
                if (!empty($content)) {
                    echo htmlspecialchars_decode($content);
                } else {
                    echo '<h1>Document Title</h1><p>Start typing your document here...</p>';
                }
                ?>
            </div>
        </div>
        
        <div class="status-bar">
            <div class="word-count" id="wordCount">Words: 0</div>
            <div class="char-count" id="charCount">Characters: 0</div>
            <div class="page-info" id="pageInfo">Page: 1</div>
        </div>
    </div>

    <!-- Download Modal -->
    <div id="downloadModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeDownloadModal()">&times;</span>
            <h3>Download Document</h3>
            <div class="download-options">
                <button onclick="exportToPDF()" class="btn btn-success">
                    <i class="fas fa-file-pdf"></i> Download as PDF
                </button>
                <button onclick="exportToDOC()" class="btn btn-success">
                    <i class="fas fa-file-word"></i> Download as DOC
                </button>
                <button onclick="exportToTXT()" class="btn btn-success">
                    <i class="fas fa-file-alt"></i> Download as TXT
                </button>
                <button onclick="exportToHTML()" class="btn btn-success">
                    <i class="fas fa-file-code"></i> Download as HTML
                </button>
            </div>
        </div>
    </div>

    <!-- History Modal -->
    <div id="historyModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeHistoryModal()">&times;</span>
            <h3>Recent Documents</h3>
            <div id="historyList" class="history-list">
                <!-- History items will be loaded here -->
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        // Initialize jsPDF
        window.jsPDF = window.jspdf.jsPDF;

        let autoSaveTimer;
        const currentFile = "<?php echo $filename; ?>";
        const isNew = <?php echo $isNew ? 'true' : 'false'; ?>;
        let selectedImageFile = null;
        
        // Initialize editor
        document.addEventListener('DOMContentLoaded', function() {
            updateWordCount();
            updateFormatButtons();
            setInterval(updateWordCount, 2000);
            makeExistingTablesResizable();
        });
        
        function updateWordCount() {
            const text = document.getElementById('documentEditor').innerText || '';
            const words = text.trim() ? text.trim().split(/\s+/).length : 0;
            const chars = text.length;
            const pages = Math.ceil(chars / 2000); // Rough estimate
            
            document.getElementById('wordCount').textContent = `Words: ${words}`;
            document.getElementById('charCount').textContent = `Characters: ${chars}`;
            document.getElementById('pageInfo').textContent = `Page: ${pages}`;
        }
        
        function autoSave() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(saveDocument, 3000);
            updateWordCount();
        }
        
        function formatText(command, value = null) {
            document.execCommand(command, false, value);
            document.getElementById('documentEditor').focus();
            updateFormatButtons();
        }
        
        function updateFormatButtons() {
            const editor = document.getElementById('documentEditor');
            const selection = window.getSelection();
            
            if (selection.rangeCount > 0) {
                const parentElement = selection.getRangeAt(0).commonAncestorContainer.parentElement;
                
                // Update button states based on current formatting
                document.getElementById('boldBtn').classList.toggle('active', 
                    document.queryCommandState('bold'));
                document.getElementById('italicBtn').classList.toggle('active', 
                    document.queryCommandState('italic'));
                document.getElementById('underlineBtn').classList.toggle('active', 
                    document.queryCommandState('underline'));
                
                // Update font family and size if possible
                try {
                    const fontFamily = document.queryCommandValue('fontName');
                    const fontSize = document.queryCommandValue('fontSize');
                    
                    if (fontFamily && document.getElementById('fontFamily').querySelector(`option[value="${fontFamily}"]`)) {
                        document.getElementById('fontFamily').value = fontFamily;
                    }
                    
                    if (fontSize) {
                        document.getElementById('fontSize').value = fontSize;
                    }
                } catch (e) {
                    // Ignore errors for font queries
                }
            }
        }
        
        // Enhanced Image Upload Functions
        function showImageUpload() {
            document.getElementById('imageUploadContainer').style.display = 'flex';
            resetImageUpload();
        }
        
        function closeImageUpload() {
            document.getElementById('imageUploadContainer').style.display = 'none';
            resetImageUpload();
        }
        
        function resetImageUpload() {
            document.getElementById('imageFile').value = '';
            document.getElementById('imageUrl').value = '';
            document.getElementById('imagePreview').style.display = 'none';
            document.getElementById('urlInputContainer').style.display = 'none';
            document.getElementById('insertImageBtn').disabled = true;
            selectedImageFile = null;
        }
        
        function showUrlInput() {
            document.getElementById('urlInputContainer').style.display = 'block';
            document.getElementById('insertImageBtn').disabled = false;
        }
        
        function previewImage(input) {
            const file = input.files[0];
            if (file) {
                selectedImageFile = file;
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('imagePreview');
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    document.getElementById('insertImageBtn').disabled = false;
                }
                reader.readAsDataURL(file);
            }
        }
        
        function insertImageFromFile() {
            if (selectedImageFile) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    formatText('insertImage', e.target.result);
                    closeImageUpload();
                    showNotification('Image inserted successfully');
                }
                reader.readAsDataURL(selectedImageFile);
            }
        }
        
        function insertImageFromUrl() {
            const url = document.getElementById('imageUrl').value.trim();
            if (url) {
                // Validate URL
                if (!isValidUrl(url)) {
                    showNotification('Please enter a valid image URL', 'error');
                    return;
                }
                
                // Create image to check if it loads
                const img = new Image();
                img.onload = function() {
                    formatText('insertImage', url);
                    closeImageUpload();
                    showNotification('Image inserted successfully');
                };
                img.onerror = function() {
                    showNotification('Could not load image from URL', 'error');
                };
                img.src = url;
            } else {
                showNotification('Please enter an image URL', 'error');
            }
        }
        
        function isValidUrl(string) {
            try {
                new URL(string);
                return true;
            } catch (_) {
                return false;
            }
        }
        
        // Resizable Table Functions
        function createResizableTable() {
            const rows = parseInt(prompt('Enter number of rows:', '3')) || 3;
            const cols = parseInt(prompt('Enter number of columns:', '3')) || 3;
            
            const tableId = 'table_' + Date.now();
            
            let tableHTML = `
                <div class="resizable-table" id="${tableId}">
                    <table style="border-collapse: collapse; width: 100%; margin: 1rem 0;">
                        <tbody>`;
            
            for (let i = 0; i < rows; i++) {
                tableHTML += '<tr>';
                for (let j = 0; j < cols; j++) {
                    tableHTML += `<td style="border: 1px solid #ddd; padding: 8px; min-width: 50px;">&nbsp;</td>`;
                }
                tableHTML += '</tr>';
            }
            
            tableHTML += `
                        </tbody>
                    </table>
                    <div class="resize-handle right" onmousedown="startResize(event, '${tableId}', 'horizontal')"></div>
                    <div class="resize-handle bottom" onmousedown="startResize(event, '${tableId}', 'vertical')"></div>
                    <div class="resize-handle corner" onmousedown="startResize(event, '${tableId}', 'both')"></div>
                </div>`;
            
            formatText('insertHTML', tableHTML);
            makeTableResizable(tableId);
        }
        
        function makeExistingTablesResizable() {
            setTimeout(() => {
                const tables = document.querySelectorAll('#documentEditor table');
                tables.forEach((table, index) => {
                    if (!table.parentElement.classList.contains('resizable-table')) {
                        const tableId = 'table_' + Date.now() + '_' + index;
                        const wrapper = document.createElement('div');
                        wrapper.className = 'resizable-table';
                        wrapper.id = tableId;
                        
                        table.parentNode.insertBefore(wrapper, table);
                        wrapper.appendChild(table);
                        
                        // Add resize handles
                        const rightHandle = document.createElement('div');
                        rightHandle.className = 'resize-handle right';
                        rightHandle.setAttribute('onmousedown', `startResize(event, '${tableId}', 'horizontal')`);
                        
                        const bottomHandle = document.createElement('div');
                        bottomHandle.className = 'resize-handle bottom';
                        bottomHandle.setAttribute('onmousedown', `startResize(event, '${tableId}', 'vertical')`);
                        
                        const cornerHandle = document.createElement('div');
                        cornerHandle.className = 'resize-handle corner';
                        cornerHandle.setAttribute('onmousedown', `startResize(event, '${tableId}', 'both')`);
                        
                        wrapper.appendChild(rightHandle);
                        wrapper.appendChild(bottomHandle);
                        wrapper.appendChild(cornerHandle);
                        
                        makeTableResizable(tableId);
                    }
                });
            }, 100);
        }
        
        function makeTableResizable(tableId) {
            const tableWrapper = document.getElementById(tableId);
            if (!tableWrapper) return;
            
            tableWrapper.addEventListener('mouseenter', function() {
                this.querySelectorAll('.resize-handle').forEach(handle => {
                    handle.style.opacity = '0.7';
                });
            });
            
            tableWrapper.addEventListener('mouseleave', function() {
                this.querySelectorAll('.resize-handle').forEach(handle => {
                    handle.style.opacity = '0';
                });
            });
        }
        
        let isResizing = false;
        let resizeDirection = '';
        let currentTable = null;
        let startX, startY, startWidth, startHeight;
        
        function startResize(e, tableId, direction) {
            e.preventDefault();
            e.stopPropagation();
            
            isResizing = true;
            resizeDirection = direction;
            currentTable = document.getElementById(tableId);
            const table = currentTable.querySelector('table');
            
            startX = e.clientX;
            startY = e.clientY;
            startWidth = parseInt(document.defaultView.getComputedStyle(table).width, 10);
            startHeight = parseInt(document.defaultView.getComputedStyle(table).height, 10);
            
            document.addEventListener('mousemove', handleResize);
            document.addEventListener('mouseup', stopResize);
        }
        
        function handleResize(e) {
            if (!isResizing || !currentTable) return;
            
            const table = currentTable.querySelector('table');
            
            if (resizeDirection === 'horizontal' || resizeDirection === 'both') {
                const width = startWidth + (e.clientX - startX);
                table.style.width = Math.max(100, width) + 'px';
            }
            
            if (resizeDirection === 'vertical' || resizeDirection === 'both') {
                const height = startHeight + (e.clientY - startY);
                table.style.height = Math.max(50, height) + 'px';
            }
        }
        
        function stopResize() {
            isResizing = false;
            resizeDirection = '';
            currentTable = null;
            
            document.removeEventListener('mousemove', handleResize);
            document.removeEventListener('mouseup', stopResize);
        }
        
        function insertLink() {
            const url = prompt('Enter URL:');
            if (url) {
                formatText('createLink', url);
            }
        }
        
        function handleKeyDown(event) {
            // Ctrl+B for Bold
            if (event.ctrlKey && event.key === 'b') {
                event.preventDefault();
                formatText('bold');
            }
            // Ctrl+I for Italic
            else if (event.ctrlKey && event.key === 'i') {
                event.preventDefault();
                formatText('italic');
            }
            // Ctrl+U for Underline
            else if (event.ctrlKey && event.key === 'u') {
                event.preventDefault();
                formatText('underline');
            }
            // Ctrl+S for Save
            else if (event.ctrlKey && event.key === 's') {
                event.preventDefault();
                saveDocument();
            }
        }
        
        function showDownloadModal() {
            document.getElementById('downloadModal').style.display = 'block';
        }
        
        function closeDownloadModal() {
            document.getElementById('downloadModal').style.display = 'none';
        }
        
        function showHistory() {
            loadHistory('document');
            document.getElementById('historyModal').style.display = 'block';
        }
        
        function closeHistoryModal() {
            document.getElementById('historyModal').style.display = 'none';
        }
        
        function printDocument() {
            const content = document.getElementById('documentEditor').innerHTML;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Print Document - ${document.getElementById('filename').value}</title>
                        <style>
                            body { 
                                font-family: Arial, sans-serif; 
                                line-height: 1.6; 
                                margin: 40px;
                                color: #333;
                            }
                            h1, h2, h3 { color: #2c3e50; }
                            table { border-collapse: collapse; width: 100%; margin: 1rem 0; }
                            table, th, td { border: 1px solid #ddd; padding: 10px; }
                            img { max-width: 100%; height: auto; }
                            .resizable-table { position: relative; }
                            .resize-handle { display: none; }
                            @media print {
                                body { margin: 0; }
                            }
                        </style>
                    </head>
                    <body>${content}</body>
                </html>
            `);
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
        }
        
        // Enhanced Export functions with PDF generation
        async function exportToPDF() {
            try {
                showNotification('Generating PDF...', 'info');
                
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();
                const editor = document.getElementById('documentEditor');
                
                // Use html2canvas to capture the content
                const canvas = await html2canvas(editor, {
                    scale: 2,
                    useCORS: true,
                    logging: false
                });
                
                const imgData = canvas.toDataURL('image/png');
                const imgWidth = doc.internal.pageSize.getWidth();
                const pageHeight = doc.internal.pageSize.getHeight();
                const imgHeight = canvas.height * imgWidth / canvas.width;
                let heightLeft = imgHeight;
                let position = 0;
                
                doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;
                
                // Add additional pages if needed
                while (heightLeft >= 0) {
                    position = heightLeft - imgHeight;
                    doc.addPage();
                    doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                }
                
                const filename = (document.getElementById('filename').value || 'document').replace(/\.[^/.]+$/, "") + '.pdf';
                doc.save(filename);
                
                showNotification('PDF downloaded successfully!');
                closeDownloadModal();
                
            } catch (error) {
                console.error('Error generating PDF:', error);
                showNotification('Error generating PDF. Using print method instead.', 'error');
                
                // Fallback to print method
                const content = document.getElementById('documentEditor').innerHTML;
                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <html>
                        <head>
                            <title>${document.getElementById('filename').value}</title>
                            <style>
                                body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; }
                                table { border-collapse: collapse; width: 100%; }
                                table, th, td { border: 1px solid #ddd; padding: 8px; }
                                img { max-width: 100%; height: auto; }
                            </style>
                        </head>
                        <body>${content}</body>
                    </html>
                `);
                printWindow.document.close();
                printWindow.print();
                closeDownloadModal();
            }
        }
        
        function exportToDOC() {
            const content = document.getElementById('documentEditor').innerHTML;
            const filename = (document.getElementById('filename').value || 'document').replace(/\.[^/.]+$/, "") + '.doc';
            
            const header = "<html xmlns:o='urn:schemas-microsoft-com:office:office' " +
                "xmlns:w='urn:schemas-microsoft-com:office:word' " +
                "xmlns='http://www.w3.org/TR/REC-html40'>" +
                "<head><meta charset='utf-8'><title>Export HTML to Word Document</title></head><body>";
            const footer = "</body></html>";
            const sourceHTML = header + content + footer;
            
            const source = 'data:application/vnd.ms-word;charset=utf-8,' + encodeURIComponent(sourceHTML);
            const downloadLink = document.createElement('a');
            
            document.body.appendChild(downloadLink);
            
            downloadLink.href = source;
            downloadLink.download = filename;
            downloadLink.click();
            
            document.body.removeChild(downloadLink);
            showNotification('Document downloaded as Word file');
            closeDownloadModal();
        }
        
        function exportToTXT() {
            const content = document.getElementById('documentEditor').innerText;
            const filename = (document.getElementById('filename').value || 'document').replace(/\.[^/.]+$/, "") + '.txt';
            downloadFile(content, filename, 'text/plain');
            showNotification('Document downloaded as text file');
            closeDownloadModal();
        }
        
        function exportToHTML() {
            const content = document.getElementById('documentEditor').innerHTML;
            const filename = (document.getElementById('filename').value || 'document').replace(/\.[^/.]+$/, "") + '.html';
            
            const fullHTML = `<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>${document.getElementById('filename').value}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 40px; }
        table { border-collapse: collapse; width: 100%; }
        table, th, td { border: 1px solid #ddd; padding: 8px; }
        img { max-width: 100%; height: auto; }
    </style>
</head>
<body>
    ${content}
</body>
</html>`;
            
            downloadFile(fullHTML, filename, 'text/html');
            showNotification('Document downloaded as HTML file');
            closeDownloadModal();
        }
        
        function downloadFile(content, filename, mimeType) {
            const blob = new Blob([content], { type: mimeType });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
        
        // Save functionality
        function saveDocument() {
            const filename = document.getElementById('filename').value || 'untitled_document';
            const content = document.getElementById('documentEditor').innerHTML;
            
            const formData = new FormData();
            formData.append('type', 'document');
            formData.append('filename', filename);
            formData.append('content', content);
            formData.append('isNew', isNew);
            formData.append('oldFilename', currentFile);
            
            fetch('save.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Document saved successfully!');
                    if (isNew) {
                        window.history.replaceState({}, '', `document.php?file=${encodeURIComponent(filename)}`);
                    }
                } else {
                    showNotification('Error saving document: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error saving document', 'error');
            });
        }
        
        // History functionality
        function loadHistory(type) {
            fetch(`history.php?type=${type}`)
                .then(response => response.json())
                .then(files => {
                    const historyList = document.getElementById('historyList');
                    historyList.innerHTML = '';
                    
                    if (files.length === 0) {
                        historyList.innerHTML = '<div class="no-files">No recent files</div>';
                        return;
                    }
                    
                    files.forEach(file => {
                        const historyItem = document.createElement('div');
                        historyItem.className = 'history-item';
                        historyItem.onclick = () => {
                            window.location.href = `${type}.php?file=${encodeURIComponent(file.filename)}`;
                        };
                        historyItem.innerHTML = `
                            <div class="file-name">${file.filename}</div>
                            <div class="file-meta">${file.date} - ${file.type}</div>
                        `;
                        historyList.appendChild(historyItem);
                    });
                })
                .catch(error => {
                    console.error('Error loading history:', error);
                    document.getElementById('historyList').innerHTML = '<div class="no-files">Error loading history</div>';
                });
        }
        
        // Notification function
        function showNotification(message, type = 'success') {
            // Remove existing notifications
            document.querySelectorAll('.notification').forEach(notification => {
                notification.remove();
            });
            
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 4000);
        }
    </script>
</body>
</html>

<?php
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