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
    <script src="https://cdn.ckeditor.com/ckeditor5/41.0.0/super-build/ckeditor.js"></script>
    <style>
        :root {
            --editor-zoom: 1;
        }

        .ck-editor__main {
            background: #f0f2f5;
            padding: 20px 0;
        }

        .ck-editor__editable_inline {
            max-width: 800px;
            margin: 20px auto;
            padding: 30px;
            min-height: 842px;
            box-shadow: 0 0 0 1px #e0e0e0, 0 4px 20px rgba(0,0,0,0.08);
            background: white;
            transform: scale(var(--editor-zoom));
            transform-origin: top center;
        }

        .zoom-controls {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
            font-size: 12px;
        }

        .zoom-controls input[type="range"] {
            flex: 1;
        }

        .saving-status {
            font-size: 13px;
            color: #6c757d;
        }

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
                <button onclick="showVersionHistory()" class="btn btn-secondary">
                    <i class="fas fa-history"></i> Version History
                </button>
                <button onclick="window.location.href='index.php'" class="btn btn-warning">
                    <i class="fas fa-home"></i> Home
                </button>
                <input type="text" id="filename" value="<?php echo htmlspecialchars($filename); ?>" placeholder="Enter filename">
            </div>
            
            <div class="format-toolbar">
                <select id="toolbarHeading" onchange="applyHeading(this.value)">
                    <option value="paragraph">Normal text</option>
                    <option value="heading1">Heading 1</option>
                    <option value="heading2">Heading 2</option>
                    <option value="heading3">Heading 3</option>
                </select>
                
                <select id="toolbarFontFamily" onchange="applyFontFamily(this.value)">
                    <option value="">Font</option>
                    <option value="Arial, Helvetica, sans-serif">Arial</option>
                    <option value="'Times New Roman', Times, serif">Times New Roman</option>
                    <option value="Georgia, 'Times New Roman', Times, serif">Georgia</option>
                    <option value="Verdana, Geneva, sans-serif">Verdana</option>
                    <option value="'Courier New', Courier, monospace">Courier New</option>
                </select>
                
                <select id="toolbarFontSize" onchange="applyFontSize(this.value)">
                    <option value="">Size</option>
                    <option value="10px">10</option>
                    <option value="11px">11</option>
                    <option value="12px">12</option>
                    <option value="14px">14</option>
                    <option value="18px">18</option>
                    <option value="24px">24</option>
                </select>
                
                <button type="button" onclick="toggleBold()" class="format-btn" title="Bold (Ctrl+B)">
                    <i class="fas fa-bold"></i>
                </button>
                <button type="button" onclick="toggleItalic()" class="format-btn" title="Italic (Ctrl+I)">
                    <i class="fas fa-italic"></i>
                </button>
                <button type="button" onclick="toggleUnderline()" class="format-btn" class="format-btn" title="Underline (Ctrl+U)">
                    <i class="fas fa-underline"></i>
                </button>
                <button type="button" onclick="toggleStrikethrough()" class="format-btn" title="Strikethrough">
                    <i class="fas fa-strikethrough"></i>
                </button>
                
                <input type="color" onchange="applyTextColor(this.value)" title="Text Color">
                <input type="color" onchange="applyHighlightColor(this.value)" title="Highlight Color">
                
                <button type="button" onclick="applyAlignment('left')" class="format-btn" title="Align Left">
                    <i class="fas fa-align-left"></i>
                </button>
                <button type="button" onclick="applyAlignment('center')" class="format-btn" title="Align Center">
                    <i class="fas fa-align-center"></i>
                </button>
                <button type="button" onclick="applyAlignment('right')" class="format-btn" title="Align Right">
                    <i class="fas fa-align-right"></i>
                </button>
                <button type="button" onclick="applyAlignment('justify')" class="format-btn" title="Justify">
                    <i class="fas fa-align-justify"></i>
                </button>
                
                <button type="button" onclick="toggleBulletedList()" class="format-btn" title="Bulleted List">
                    <i class="fas fa-list-ul"></i>
                </button>
                <button type="button" onclick="toggleNumberedList()" class="format-btn" title="Numbered List">
                    <i class="fas fa-list-ol"></i>
                </button>
                
                <button type="button" onclick="outdent()" class="format-btn" title="Decrease Indent">
                    <i class="fas fa-outdent"></i>
                </button>
                <button type="button" onclick="indent()" class="format-btn" title="Increase Indent">
                    <i class="fas fa-indent"></i>
                </button>
                
                <button type="button" onclick="insertToolbarLink()" class="format-btn" title="Insert Link">
                    <i class="fas fa-link"></i>
                </button>
                <button type="button" onclick="insertToolbarImage()" class="format-btn" title="Insert Image">
                    <i class="fas fa-image"></i>
                </button>
                <button type="button" onclick="insertToolbarTable()" class="format-btn" title="Insert Table">
                    <i class="fas fa-table"></i>
                </button>
            </div>
            
        </header>

        <div class="editor-container">
            <div 
                id="documentEditor" 
                class="document-editor"
                contenteditable="true"
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
            <div class="saving-status" id="savingStatus">Saved</div>
            <div class="zoom-controls">
                <span>Zoom</span>
                <input type="range" id="zoomSlider" min="50" max="150" value="100">
                <span id="zoomValue">100%</span>
            </div>
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

    <!-- Version History Modal -->
    <div id="historyModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeHistoryModal()">&times;</span>
            <h3>Version History</h3>
            <div id="historyList" class="history-list">
                <!-- Revisions will be loaded here -->
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        // Initialize jsPDF
        window.jsPDF = window.jspdf.jsPDF;

        let editorInstance = null;
        let autosaveTimer = null;
        const currentFile = "<?php echo $filename; ?>";
        const isNew = <?php echo $isNew ? 'true' : 'false'; ?>;

        function getEditorHtml() {
            if (editorInstance) {
                return editorInstance.getData();
            }
            const el = document.getElementById('documentEditor');
            return el ? el.innerHTML : '';
        }

        function getEditorText() {
            const temp = document.createElement('div');
            temp.innerHTML = getEditorHtml();
            return temp.textContent || temp.innerText || '';
        }

        function updateStatusFromEditor() {
            const text = getEditorText();
            const trimmed = text.trim();
            const words = trimmed ? trimmed.split(/\s+/).length : 0;
            const chars = text.length;
            const pages = Math.max(1, Math.ceil(chars / 2000));

            document.getElementById('wordCount').textContent = `Words: ${words}`;
            document.getElementById('charCount').textContent = `Characters: ${chars}`;
            document.getElementById('pageInfo').textContent = `Page: ${pages}`;
        }

        function setSavingState(state) {
            const el = document.getElementById('savingStatus');
            if (!el) return;
            if (state === 'saving') {
                el.textContent = 'Saving...';
            } else if (state === 'error') {
                el.textContent = 'Error';
            } else {
                el.textContent = 'Saved';
            }
        }

        function scheduleAutosave() {
            if (!editorInstance) return;
            clearTimeout(autosaveTimer);
            setSavingState('saving');
            autosaveTimer = setTimeout(function() {
                saveDocument(true);
            }, 1500);
        }

        function initializeEditor() {
            CKEDITOR.ClassicEditor
                .create(document.querySelector('#documentEditor'), {
                    toolbar: [
                        'heading',
                        '|',
                        'bold',
                        'italic',
                        'underline',
                        'strikethrough',
                        '|',
                        'fontColor',
                        'fontBackgroundColor',
                        '|',
                        'bulletedList',
                        'numberedList',
                        'outdent',
                        'indent',
                        '|',
                        'alignment',
                        '|',
                        'insertTable',
                        'link',
                        'imageUpload',
                        '|',
                        'undo',
                        'redo'
                    ],
                    table: {
                        contentToolbar: [
                            'tableColumn',
                            'tableRow',
                            'mergeTableCells',
                            'splitTableCellVertically',
                            'splitTableCellHorizontally',
                            'tableProperties',
                            'tableCellProperties'
                        ]
                    },
                    image: {
                        toolbar: [
                            'imageStyle:alignLeft',
                            'imageStyle:full',
                            'imageStyle:alignRight',
                            '|',
                            'imageTextAlternative',
                            'toggleImageCaption',
                            'imageResize'
                        ]
                    },
                    simpleUpload: {
                        uploadUrl: 'upload_image.php'
                    }
                })
                .then(function(editor) {
                    editorInstance = editor;

                    editor.model.document.on('change:data', function() {
                        updateStatusFromEditor();
                        scheduleAutosave();
                    });

                    updateStatusFromEditor();
                })
                .catch(function(error) {
                    console.error(error);
                });
        }

        document.addEventListener('DOMContentLoaded', function() {
            initializeEditor();
            setInterval(updateStatusFromEditor, 3000);

            const zoomSlider = document.getElementById('zoomSlider');
            if (zoomSlider) {
                zoomSlider.addEventListener('input', function() {
                    const value = parseInt(this.value, 10) || 100;
                    const factor = value / 100;
                    document.documentElement.style.setProperty('--editor-zoom', factor);
                    const label = document.getElementById('zoomValue');
                    if (label) {
                        label.textContent = value + '%';
                    }
                });
            }

            document.addEventListener('keydown', function(event) {
                if ((event.ctrlKey || event.metaKey) && event.key === 's') {
                    event.preventDefault();
                    saveDocument(false);
                }
            });
        });

        function focusEditor() {
            if (editorInstance) {
                editorInstance.editing.view.focus();
            }
        }

        function applyHeading(value) {
            if (!editorInstance || !value) return;
            editorInstance.execute('heading', { value: value });
            focusEditor();
        }

        function applyFontFamily(value) {
            if (!editorInstance || !value) return;
            editorInstance.execute('fontFamily', { value: value });
            focusEditor();
        }

        function applyFontSize(value) {
            if (!editorInstance || !value) return;
            editorInstance.execute('fontSize', { value: value });
            focusEditor();
        }

        function toggleBold() {
            if (!editorInstance) return;
            editorInstance.execute('bold');
            focusEditor();
        }

        function toggleItalic() {
            if (!editorInstance) return;
            editorInstance.execute('italic');
            focusEditor();
        }

        function toggleUnderline() {
            if (!editorInstance) return;
            editorInstance.execute('underline');
            focusEditor();
        }

        function toggleStrikethrough() {
            if (!editorInstance) return;
            editorInstance.execute('strikethrough');
            focusEditor();
        }

        function applyTextColor(color) {
            if (!editorInstance || !color) return;
            editorInstance.execute('fontColor', { value: color });
            focusEditor();
        }

        function applyHighlightColor(color) {
            if (!editorInstance || !color) return;
            editorInstance.execute('fontBackgroundColor', { value: color });
            focusEditor();
        }

        function applyAlignment(align) {
            if (!editorInstance) return;
            editorInstance.execute('alignment', { value: align });
            focusEditor();
        }

        function toggleBulletedList() {
            if (!editorInstance) return;
            editorInstance.execute('bulletedList');
            focusEditor();
        }

        function toggleNumberedList() {
            if (!editorInstance) return;
            editorInstance.execute('numberedList');
            focusEditor();
        }

        function indent() {
            if (!editorInstance) return;
            editorInstance.execute('indent');
            focusEditor();
        }

        function outdent() {
            if (!editorInstance) return;
            editorInstance.execute('outdent');
            focusEditor();
        }

        function insertToolbarLink() {
            if (!editorInstance) return;
            var url = prompt('Enter URL:', 'https://');
            if (!url) return;
            editorInstance.execute('link', { href: url });
            focusEditor();
        }

        function insertToolbarImage() {
            if (!editorInstance) return;
            editorInstance.execute('imageUpload');
            focusEditor();
        }

        function insertToolbarTable() {
            if (!editorInstance) return;
            editorInstance.execute('insertTable');
            focusEditor();
        }

        function showDownloadModal() {
            document.getElementById('downloadModal').style.display = 'block';
        }

        function closeDownloadModal() {
            document.getElementById('downloadModal').style.display = 'none';
        }

        function showVersionHistory() {
            const filename = document.getElementById('filename').value || currentFile;
            if (!filename) {
                showNotification('Please enter a filename first', 'error');
                return;
            }

            fetch('document_revisions.php?filename=' + encodeURIComponent(filename))
                .then(function(response) { return response.json(); })
                .then(function(revisions) {
                    const list = document.getElementById('historyList');
                    list.innerHTML = '';

                    if (!Array.isArray(revisions) || revisions.length === 0) {
                        list.innerHTML = '<div class="no-files">No revisions yet</div>';
                    } else {
                        revisions.forEach(function(rev) {
                            const item = document.createElement('div');
                            item.className = 'history-item';
                            const date = new Date(rev.created_at);
                            item.innerHTML = '<div class="file-name">' +
                                date.toLocaleString() +
                                '</div><div class="file-meta">Revision #' + rev.id +
                                '</div>';
                            item.onclick = function() {
                                restoreRevision(rev.id);
                            };
                            list.appendChild(item);
                        });
                    }

                    document.getElementById('historyModal').style.display = 'block';
                })
                .catch(function(error) {
                    console.error(error);
                    showNotification('Error loading version history', 'error');
                });
        }

        function closeHistoryModal() {
            document.getElementById('historyModal').style.display = 'none';
        }

        function restoreRevision(id) {
            if (!editorInstance) {
                return;
            }

            fetch('document_revisions.php?id=' + encodeURIComponent(id))
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (!data || !data.content) {
                        showNotification('Could not load revision', 'error');
                        return;
                    }
                    const html = data.content.html || '';
                    editorInstance.setData(html);
                    closeHistoryModal();
                    showNotification('Revision loaded into editor (remember to save)');
                })
                .catch(function(error) {
                    console.error(error);
                    showNotification('Error restoring revision', 'error');
                });
        }
        
        function printDocument() {
            const content = getEditorHtml();
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
                const temp = document.createElement('div');
                temp.style.position = 'absolute';
                temp.style.left = '-9999px';
                temp.innerHTML = getEditorHtml();
                document.body.appendChild(temp);

                const canvas = await html2canvas(temp, {
                    scale: 2,
                    useCORS: true,
                    logging: false
                });

                document.body.removeChild(temp);

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
                const content = getEditorHtml();
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
            const content = getEditorHtml();
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
            const content = getEditorText();
            const filename = (document.getElementById('filename').value || 'document').replace(/\.[^/.]+$/, "") + '.txt';
            downloadFile(content, filename, 'text/plain');
            showNotification('Document downloaded as text file');
            closeDownloadModal();
        }
        
        function exportToHTML() {
            const content = getEditorHtml();
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
        function saveDocument(isAutosave = false) {
            const filename = document.getElementById('filename').value || 'untitled_document';
            const html = getEditorHtml();
            const payload = { html: html };
            const contentJson = JSON.stringify(payload);
            
            const formData = new FormData();
            formData.append('type', 'document');
            formData.append('filename', filename);
            formData.append('content_html', html);
            formData.append('content_json', contentJson);
            formData.append('isNew', isNew);
            formData.append('oldFilename', currentFile);
            formData.append('autosave', isAutosave ? 'true' : 'false');
            
            fetch('save.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (!isAutosave) {
                        showNotification('Document saved successfully!');
                    }
                    setSavingState('saved');
                    if (isNew) {
                        window.history.replaceState({}, '', `document.php?file=${encodeURIComponent(filename)}`);
                    }
                } else {
                    showNotification('Error saving document: ' + data.message, 'error');
                    setSavingState('error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error saving document', 'error');
                setSavingState('error');
            });
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
