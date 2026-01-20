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

                <!-- Text color picker (custom palette + native picker) -->
                <div class="color-picker-wrapper" id="textColorPicker">
                    <button type="button" class="format-btn color-picker-trigger" title="Text color">
                        <i class="fas fa-font"></i>
                    </button>
                    <div class="color-palette" id="textColorPalette"></div>
                </div>

                <!-- Highlight color picker (custom palette + native picker) -->
                <div class="color-picker-wrapper" id="highlightColorPicker">
                    <button type="button" class="format-btn color-picker-trigger" title="Highlight color">
                        <i class="fas fa-highlighter"></i>
                    </button>
                    <div class="color-palette" id="highlightColorPalette"></div>
                </div>
                
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
                <button type="button" onclick="deleteSelectedImage()" class="format-btn" title="Delete Selected Image">
                    <i class="fas fa-trash-can"></i> Img
                </button>
                <button type="button" onclick="deleteSelectedTable()" class="format-btn" title="Delete Selected Table">
                    <i class="fas fa-trash-can"></i> Tbl
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
        let lastEditorSelection = null;
        let activeColorPalette = null;
        let lastNativeRange = null;
        const currentFile = "<?php echo $filename; ?>";
        const isNew = <?php echo $isNew ? 'true' : 'false'; ?>;

        // Swatch colors used for the custom text / highlight pickers.
        // Roughly matches the Google Docs palettes.
        const TEXT_COLOR_SWATCHES = [
            '#000000', '#434343', '#666666', '#999999', '#b7b7b7', '#cccccc', '#d9d9d9', '#efefef',
            '#f3f3f3', '#ffffff', '#980000', '#ff0000', '#ff9900', '#ffff00', '#00ff00', '#00ffff',
            '#4a86e8', '#0000ff', '#9900ff', '#ff00ff', '#e6b8af', '#f4cccc', '#fce5cd', '#fff2cc',
            '#d9ead3', '#d0e0e3', '#c9daf8', '#cfe2f3', '#d9d2e9', '#ead1dc', '#dd7e6b', '#ea9999',
            '#f9cb9c', '#ffe599', '#b6d7a8', '#a2c4c9', '#a4c2f4', '#9fc5e8', '#b4a7d6', '#d5a6bd',
            '#cc4125', '#e06666', '#f6b26b', '#ffd966', '#93c47d', '#76a5af', '#6d9eeb', '#6fa8dc',
            '#8e7cc3', '#c27ba0', '#a61c00', '#cc0000', '#e69138', '#f1c232', '#6aa84f', '#45818e',
            '#3c78d8', '#3d85c6', '#674ea7', '#a64d79', '#85200c', '#990000', '#b45f06', '#bf9000',
            '#38761d', '#134f5c', '#1155cc', '#0b5394', '#351c75', '#741b47'
        ];

        const HIGHLIGHT_COLOR_SWATCHES = [
            '#ffffff', '#fff2cc', '#ffd966', '#ffe599', '#fff2cc', '#f4cccc', '#fce5cd', '#ead1dc',
            '#d9ead3', '#d0e0e3', '#cfe2f3', '#d9d2e9', '#e6b8af', '#f4cccc', '#fce5cd', '#fff2cc',
            '#d9ead3', '#d0e0e3', '#c9daf8', '#cfe2f3', '#d9d2e9', '#ead1dc', '#fce5cd', '#fff2cc',
            '#ffe599', '#ffd966', '#f6b26b', '#f9cb9c', '#f4cccc', '#ead1dc', '#d9d2e9', '#cfe2f3',
            '#d0e0e3', '#d9ead3'
        ];

        // Shared styles so exported/printed documents look like the on-screen page.
        const EXPORT_PAGE_STYLES = `
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                line-height: 1.8;
                color: #333;
                margin: 0;
                background: #ffffff;
            }
            .page {
                max-width: 800px;
                margin: 40px auto;
                padding: 30px;
                box-shadow: 0 0 0 1px #e0e0e0;
                background: #ffffff;
            }
            .page h1, .page h2, .page h3 {
                margin: 1.5rem 0 1rem 0;
                color: #2c3e50;
            }
            .page p {
                margin: 0 0 1rem 0;
            }
            .page ul,
            .page ol {
                margin: 0 0 0 2rem;
                padding-left: 1.5rem;
                list-style-position: outside;
            }
            .page ul {
                list-style-type: disc;
            }
            .page ol {
                list-style-type: decimal;
            }
            .page li {
                margin: 0.2rem 0;
            }
            .page li p {
                margin: 0;
            }
            .page table {
                border-collapse: collapse;
                width: 100%;
                margin: 1rem 0;
            }
            .page table, .page th, .page td {
                border: 1px solid #ddd;
                padding: 10px;
            }
            .page img {
                max-width: 100%;
                height: auto;
                border-radius: 6px;
            }
        `;

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
            clearTimeout(autosaveTimer);
            setSavingState('saving');
            autosaveTimer = setTimeout(function() {
                saveDocument(true);
            }, 1500);
        }

        function restoreEditorSelection() {
            if (!editorInstance || !lastEditorSelection || !lastEditorSelection.length) {
                return;
            }

            editorInstance.model.change(function(writer) {
                writer.setSelection(lastEditorSelection);
            });
        }

        function initializeEditor() {
            const el = document.getElementById('documentEditor');

            // If CKEditor 5 super-build is available, use it with only free plugins.
            if (el && window.CKEDITOR && CKEDITOR.ClassicEditor) {
                const Editor = CKEDITOR.ClassicEditor;

                // Strip premium / cloud plugins from builtinPlugins before create().
                if (Array.isArray(Editor.builtinPlugins)) {
                    const blocked = new Set([
                        'CloudServices',
                        'ExportPdf',
                        'ExportWord',
                        'CKBox',
                        'CKBoxImageEdit',
                        'CKBoxUtils',
                        'RealTimeCollaborativeComments',
                        'RealTimeCollaborativeTrackChanges',
                        'RealTimeCollaborativeRevisionHistory',
                        'PresenceList',
                        'Comments',
                        'TrackChanges',
                        'TrackChangesData',
                        'RevisionHistory',
                        'Pagination',
                        'WProofreader',
                        'MathType',
                        'SlashCommand',
                        'Template',
                        'DocumentOutline',
                        'FormatPainter',
                        'TableOfContents',
                        'PasteFromOfficeEnhanced',
                        'CaseChange',
                        'AIAdapter',
                        'AIAssistant',
                        'AICommands'
                    ]);

                    Editor.builtinPlugins = Editor.builtinPlugins.filter(function(Plugin) {
                        const name = Plugin.pluginName || Plugin.name || '';
                        return !blocked.has(name);
                    });
                }

                Editor.create(el, {
                    // Use only the custom header; hide the built-in main toolbar.
                    toolbar: [],
                    alignment: {
                        options: [ 'left', 'center', 'right', 'justify' ]
                    },
                    fontSize: {
                        options: [ '10px', '11px', '12px', '14px', '18px', '24px' ],
                        supportAllValues: true
                    },
                    fontFamily: {
                        supportAllValues: true
                    },
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
                            'imageStyle:inline',
                            'imageStyle:block',
                            'imageStyle:side',
                            '|',
                            'imageTextAlternative',
                            'toggleImageCaption',
                            'imageResize'
                        ]
                    },
                    simpleUpload: {
                        uploadUrl: 'upload_image.php'
                    },
                    removePlugins: [
                        'EasyImage',
                        'CloudServices',
                        'ExportPdf',
                        'ExportWord',
                        'CKBox',
                        'CKBoxImageEdit',
                        'CKBoxUtils',
                        'RealTimeCollaborativeComments',
                        'RealTimeCollaborativeTrackChanges',
                        'RealTimeCollaborativeRevisionHistory',
                        'PresenceList',
                        'Comments',
                        'TrackChanges',
                        'TrackChangesData',
                        'RevisionHistory',
                        'Pagination',
                        'WProofreader',
                        'MathType',
                        'SlashCommand',
                        'Template',
                        'DocumentOutline',
                        'FormatPainter',
                        'TableOfContents',
                        'PasteFromOfficeEnhanced',
                        'CaseChange',
                        'AIAdapter',
                        'AIAssistant',
                        'AICommands'
                    ]
                })
                .then(function(editor) {
                    editorInstance = editor;

                    // Track last selection in the CKEditor model for toolbar actions.
                    lastEditorSelection = [];
                    editor.model.document.selection.on('change:range', function() {
                        lastEditorSelection = [];
                        for (const range of editor.model.document.selection.getRanges()) {
                            lastEditorSelection.push(range.clone());
                        }
                    });

                    // Update status + autosave on data changes.
                    editor.model.document.on('change:data', function() {
                        updateStatusFromEditor();
                        scheduleAutosave();
                    });

                    updateStatusFromEditor();
                })
                .catch(function(error) {
                    console.error(error);
                    // Fallback to native editor if CKEditor fails.
                    if (el) {
                        setupNativeEditor(el);
                    }
                });
            } else if (el) {
                // No CKEditor – use native contentEditable fallback.
                setupNativeEditor(el);
            }
        }

        function setupNativeEditor(el) {
            el.addEventListener('input', function() {
                updateStatusFromEditor();
                scheduleAutosave();
            });

            // Track the last selection inside the editor so we can
            // insert images (and other objects) back at the right place
            // after dialogs/modals are used.
            document.addEventListener('selectionchange', function() {
                const sel = window.getSelection && window.getSelection();
                if (!sel || !sel.rangeCount) return;
                const range = sel.getRangeAt(0);
                let node = range.commonAncestorContainer;
                while (node && node !== el) {
                    node = node.parentNode;
                }
                if (node === el) {
                    lastNativeRange = range.cloneRange();
                }
            });

            updateStatusFromEditor();
        }

        function buildColorPalette(container, colors, type) {
            if (!container || container.dataset.initialized === '1') {
                return;
            }

            container.innerHTML = '';

            const header = document.createElement('div');
            header.className = 'color-palette-header';
            header.textContent = type === 'text' ? 'Text color' : 'Highlight color';
            container.appendChild(header);

            colors.forEach(function(color) {
                const swatch = document.createElement('div');
                swatch.className = 'color-option';
                swatch.style.backgroundColor = color;
                swatch.dataset.color = color;
                swatch.addEventListener('click', function(event) {
                    event.stopPropagation();
                    if (type === 'text') {
                        applyTextColor(color);
                    } else {
                        applyHighlightColor(color);
                    }
                    closeActiveColorPalette();
                });
                container.appendChild(swatch);
            });

            const footer = document.createElement('div');
            footer.className = 'color-palette-footer';
            const label = document.createElement('span');
            label.textContent = 'Custom';
            const input = document.createElement('input');
            input.type = 'color';
            input.addEventListener('input', function(event) {
                const value = event.target.value;
                if (type === 'text') {
                    applyTextColor(value);
                } else {
                    applyHighlightColor(value);
                }
            });
            footer.appendChild(label);
            footer.appendChild(input);
            container.appendChild(footer);

            container.dataset.initialized = '1';
        }

        function closeActiveColorPalette() {
            if (activeColorPalette) {
                activeColorPalette.classList.remove('active');
                activeColorPalette = null;
            }
        }

        function toggleColorPalette(type) {
            const paletteId = type === 'text' ? 'textColorPalette' : 'highlightColorPalette';
            const palette = document.getElementById(paletteId);
            if (!palette) return;

            if (palette.dataset.initialized !== '1') {
                const colors = type === 'text' ? TEXT_COLOR_SWATCHES : HIGHLIGHT_COLOR_SWATCHES;
                buildColorPalette(palette, colors, type);
            }

            if (activeColorPalette && activeColorPalette !== palette) {
                activeColorPalette.classList.remove('active');
            }

            const willShow = !palette.classList.contains('active');
            palette.classList.toggle('active', willShow);
            activeColorPalette = willShow ? palette : null;
        }

        function initializeColorPalettes() {
            const textPicker = document.getElementById('textColorPicker');
            const highlightPicker = document.getElementById('highlightColorPicker');

            if (textPicker) {
                const trigger = textPicker.querySelector('.color-picker-trigger');
                if (trigger) {
                    trigger.addEventListener('click', function(event) {
                        event.stopPropagation();
                        toggleColorPalette('text');
                    });
                }
            }

            if (highlightPicker) {
                const trigger = highlightPicker.querySelector('.color-picker-trigger');
                if (trigger) {
                    trigger.addEventListener('click', function(event) {
                        event.stopPropagation();
                        toggleColorPalette('highlight');
                    });
                }
            }

            document.addEventListener('click', function(event) {
                if (!event.target.closest('.color-picker-wrapper')) {
                    closeActiveColorPalette();
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            initializeEditor();
            setInterval(updateStatusFromEditor, 3000);
            initializeColorPalettes();

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

        function applyHeading(value) {
            if (!value) return;
            
            if (editorInstance) {
                restoreEditorSelection();
                editorInstance.execute('heading', { value: value });
                editorInstance.editing.view.focus();
            } else {
                const blockMap = {
                    paragraph: '<p>',
                    heading1: '<h1>',
                    heading2: '<h2>',
                    heading3: '<h3>'
                };
                const block = blockMap[value] || '<p>';
                document.execCommand('formatBlock', false, block);
            }
        }

        function applyFontFamily(value) {
            if (!value) return;
            
            if (editorInstance) {
                restoreEditorSelection();
                editorInstance.execute('fontFamily', { value: value });
                editorInstance.editing.view.focus();
            } else {
                document.execCommand('fontName', false, value);
            }
        }

        function applyFontSize(value) {
            if (!value) return;
            
            if (editorInstance) {
                restoreEditorSelection();
                editorInstance.execute('fontSize', { value: value });
                editorInstance.editing.view.focus();
            } else {
                const sizeMap = {
                    '10px': '2',
                    '11px': '2',
                    '12px': '3',
                    '14px': '4',
                    '18px': '5',
                    '24px': '6'
                };
                const legacySize = sizeMap[value] || '3';
                document.execCommand('fontSize', false, legacySize);
            }
        }

        function toggleBold() {
            if (editorInstance) {
                restoreEditorSelection();
                editorInstance.execute('bold');
                editorInstance.editing.view.focus();
            } else {
                document.execCommand('bold', false, null);
            }
        }

        function toggleItalic() {
            if (editorInstance) {
                restoreEditorSelection();
                editorInstance.execute('italic');
                editorInstance.editing.view.focus();
            } else {
                document.execCommand('italic', false, null);
            }
        }

        function toggleUnderline() {
            if (editorInstance) {
                restoreEditorSelection();
                editorInstance.execute('underline');
                editorInstance.editing.view.focus();
            } else {
                document.execCommand('underline', false, null);
            }
        }

        function toggleStrikethrough() {
            if (editorInstance) {
                restoreEditorSelection();
                editorInstance.execute('strikethrough');
                editorInstance.editing.view.focus();
            } else {
                document.execCommand('strikeThrough', false, null);
            }
        }

        function applyTextColor(color) {
            if (!color) return;
            
            if (editorInstance) {
                restoreEditorSelection();
                editorInstance.execute('fontColor', { value: color });
                editorInstance.editing.view.focus();
            } else {
                document.execCommand('foreColor', false, color);
            }
        }

        function applyHighlightColor(color) {
            if (!color) return;
            
            if (editorInstance) {
                restoreEditorSelection();
                editorInstance.execute('fontBackgroundColor', { value: color });
                editorInstance.editing.view.focus();
            } else {
                if (!document.execCommand('hiliteColor', false, color)) {
                    document.execCommand('backColor', false, color);
                }
            }
        }

        function applyAlignment(align) {
            const alignMap = {
                left: 'justifyLeft',
                center: 'justifyCenter',
                right: 'justifyRight',
                justify: 'justifyFull'
            };

            if (editorInstance) {
                restoreEditorSelection();
                editorInstance.execute('alignment', { value: align });
                editorInstance.editing.view.focus();
            } else if (alignMap[align]) {
                document.execCommand(alignMap[align], false, null);
            }
        }

        function toggleBulletedList() {
            if (editorInstance) {
                restoreEditorSelection();
                editorInstance.execute('bulletedList');
                editorInstance.editing.view.focus();
            } else {
                document.execCommand('insertUnorderedList', false, null);
            }
        }

        function toggleNumberedList() {
            if (editorInstance) {
                restoreEditorSelection();
                editorInstance.execute('numberedList');
                editorInstance.editing.view.focus();
            } else {
                document.execCommand('insertOrderedList', false, null);
            }
        }

        function indent() {
            if (editorInstance) {
                restoreEditorSelection();
                editorInstance.execute('indent');
                editorInstance.editing.view.focus();
            } else {
                document.execCommand('indent', false, null);
            }
        }

        function outdent() {
            if (editorInstance) {
                restoreEditorSelection();
                editorInstance.execute('outdent');
                editorInstance.editing.view.focus();
            } else {
                document.execCommand('outdent', false, null);
            }
        }

        function insertToolbarLink() {
            var url = prompt('Enter URL:', 'https://');
            if (!url) return;

            if (editorInstance) {
                restoreEditorSelection();
                editorInstance.execute('link', { href: url });
                editorInstance.editing.view.focus();
            } else {
                document.execCommand('createLink', false, url);
            }
        }

        function insertToolbarImage() {
            if (editorInstance) {
                // Selection is already tracked in lastEditorSelection.
                const modal = document.getElementById('imageUploadContainer');
                if (modal) {
                    modal.style.display = 'flex';
                }
            } else {
                // Remember the caret position inside the editor before opening the modal.
                const sel = window.getSelection && window.getSelection();
                if (sel && sel.rangeCount > 0) {
                    const range = sel.getRangeAt(0);
                    let node = range.commonAncestorContainer;
                    const editor = document.getElementById('documentEditor');
                    while (node && node !== editor) {
                        node = node.parentNode;
                    }
                    if (node === editor) {
                        lastNativeRange = range.cloneRange();
                    }
                }

                const modal = document.getElementById('imageUploadContainer');
                if (modal) {
                    modal.style.display = 'flex';
                }
            }
        }

        function insertToolbarTable() {
            if (editorInstance) {
                editorInstance.execute('insertTable');
                editorInstance.editing.view.focus();
            } else {
                const rows = 3;
                const cols = 3;
                let html = '<table border="1" style="border-collapse: collapse; width: 100%;">';
                for (let r = 0; r < rows; r++) {
                    html += '<tr>';
                    for (let c = 0; c < cols; c++) {
                        html += '<td style="padding: 8px;">&nbsp;</td>';
                    }
                    html += '</tr>';
                }
                html += '</table>';
                document.execCommand('insertHTML', false, html);
            }
        }

        // Image upload / insert helpers (native editor)

        let _pendingImageDataUrl = null;

        function showUrlInput() {
            const urlContainer = document.getElementById('urlInputContainer');
            if (urlContainer) {
                urlContainer.style.display = 'block';
            }
        }

        function closeImageUpload() {
            const modal = document.getElementById('imageUploadContainer');
            const preview = document.getElementById('imagePreview');
            const fileInput = document.getElementById('imageFile');
            const urlInput = document.getElementById('imageUrl');
            const insertBtn = document.getElementById('insertImageBtn');
            const urlContainer = document.getElementById('urlInputContainer');

            if (modal) modal.style.display = 'none';
            if (preview) {
                preview.src = '';
                preview.style.display = 'none';
            }
            if (fileInput) fileInput.value = '';
            if (urlInput) urlInput.value = '';
            if (insertBtn) insertBtn.disabled = true;
            if (urlContainer) urlContainer.style.display = 'none';
            _pendingImageDataUrl = null;
        }

        function previewImage(input) {
            const file = input && input.files && input.files[0];
            const preview = document.getElementById('imagePreview');
            const insertBtn = document.getElementById('insertImageBtn');

            if (!file || !preview) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                _pendingImageDataUrl = e.target.result;
                preview.src = _pendingImageDataUrl;
                preview.style.display = 'block';
                if (insertBtn) insertBtn.disabled = false;
            };
            reader.readAsDataURL(file);
        }

        function insertImageAtCursor(src) {
            if (!src) return;
            if (editorInstance) {
                // Let CKEditor handle image insertion so it renders correctly.
                try {
                    editorInstance.execute('insertImage', { source: [ src ] });
                } catch (e) {
                    // Fallback: append raw <img> HTML if insertImage is not available.
                    const currentHtml = editorInstance.getData();
                    editorInstance.setData(currentHtml + '<p><img src="' + src + '"></p>');
                }
                editorInstance.editing.view.focus();
                updateStatusFromEditor();
                scheduleAutosave();
            } else {
                const editor = document.getElementById('documentEditor');
                if (!editor) return;

                editor.focus();

                // Wrap the image in a resizable container so the user
                // can adjust width/height with the mouse (native fallback).
                const wrapper = document.createElement('div');
                wrapper.className = 'resizable-image';
                const img = document.createElement('img');
                img.src = src;
                wrapper.appendChild(img);

                // Add a visible resize handle in the bottom-right corner.
                enableImageResize(wrapper);

                const sel = window.getSelection && window.getSelection();
                let inserted = false;

                // Prefer the last saved range inside the editor (before opening the modal)
                if (lastNativeRange) {
                    const range = lastNativeRange.cloneRange();
                    let node = range.commonAncestorContainer;
                    while (node && node !== editor) {
                        node = node.parentNode;
                    }
                    if (node === editor) {
                        range.deleteContents();
                        range.insertNode(wrapper);
                        range.setStartAfter(wrapper);
                        range.setEndAfter(wrapper);
                        if (sel) {
                            sel.removeAllRanges();
                            sel.addRange(range);
                        }
                        inserted = true;
                    }
                }

                // Fallback: use current selection if it's inside the editor
                if (!inserted && sel && sel.rangeCount > 0) {
                    const range = sel.getRangeAt(0);
                    let container = range.commonAncestorContainer;
                    while (container && container !== editor) {
                        container = container.parentNode;
                    }
                    if (container === editor) {
                        range.deleteContents();
                        range.insertNode(wrapper);
                        range.setStartAfter(wrapper);
                        range.setEndAfter(wrapper);
                        sel.removeAllRanges();
                        sel.addRange(range);
                        inserted = true;
                    }
                }

                if (!inserted) {
                    editor.appendChild(wrapper);
                }

                updateStatusFromEditor();
                scheduleAutosave();
            }
        }

        function enableImageResize(wrapper) {
            if (!wrapper) return;
            const handle = document.createElement('div');
            handle.className = 'image-resize-handle resize-se';
            wrapper.appendChild(handle);

            let startX, startY, startWidth, startHeight, aspect;

            handle.addEventListener('mousedown', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const rect = wrapper.getBoundingClientRect();
                startX = e.clientX;
                startY = e.clientY;
                startWidth = rect.width;
                startHeight = rect.height || rect.width;
                aspect = startWidth / startHeight || 1;

                function onMove(ev) {
                    const dx = ev.clientX - startX;
                    let newWidth = Math.max(50, startWidth + dx);
                    let newHeight = newWidth / aspect;
                    wrapper.style.width = newWidth + 'px';
                    wrapper.style.height = newHeight + 'px';
                }

                function onUp() {
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                    scheduleAutosave();
                }

                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
            });
        }

        function deleteSelectedImage() {
            if (editorInstance) {
                const selection = editorInstance.model.document.selection;
                const element = selection.getSelectedElement();
                if (element && element.is('element', 'image')) {
                    editorInstance.model.change(function(writer) {
                        writer.remove(element);
                    });
                }
            } else {
                const sel = window.getSelection && window.getSelection();
                if (!sel || !sel.rangeCount) return;
                let node = sel.anchorNode;
                while (node && node.nodeType === 1 && node.tagName !== 'IMG') {
                    node = node.parentNode;
                }
                if (node && node.tagName === 'IMG') {
                    node.remove();
                }
            }
        }

        function deleteSelectedTable() {
            if (editorInstance) {
                const selection = editorInstance.model.document.selection;
                const element = selection.getSelectedElement();
                if (element && element.is('element', 'table')) {
                    editorInstance.model.change(function(writer) {
                        writer.remove(element);
                    });
                }
            } else {
                const sel = window.getSelection && window.getSelection();
                if (!sel || !sel.rangeCount) return;
                let node = sel.anchorNode;
                while (node && node.nodeType === 1 && node.tagName !== 'TABLE') {
                    node = node.parentNode;
                }
                if (node && node.tagName === 'TABLE') {
                    node.remove();
                }
            }
        }

        function insertImageFromFile() {
            if (!_pendingImageDataUrl) {
                return;
            }
            insertImageAtCursor(_pendingImageDataUrl);
            closeImageUpload();
        }

        function insertImageFromUrl() {
            const urlInput = document.getElementById('imageUrl');
            if (!urlInput) return;
            const url = urlInput.value.trim();
            if (!url) return;

            insertImageAtCursor(url);
            closeImageUpload();
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
        
        // Print the editor exactly as it appears on screen
        async function printDocument() {
            const editorEl = document.querySelector('.ck-editor__editable_inline') || document.getElementById('documentEditor');
            if (!editorEl || typeof html2canvas === 'undefined') {
                window.print();
                return;
            }

            const canvas = await html2canvas(editorEl, {
                scale: 2,
                useCORS: true,
                logging: false
            });

            const imgData = canvas.toDataURL('image/png');
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Print Document - ${document.getElementById('filename').value}</title>
                        <style>
                            body {
                                margin: 0;
                                padding: 0;
                            }
                            img {
                                width: 100%;
                                height: auto;
                                display: block;
                            }
                            @page {
                                margin: 0;
                            }
                        </style>
                    </head>
                    <body>
                        <img src="${imgData}" />
                    </body>
                </html>
            `);
            printWindow.document.close();

            const img = printWindow.document.querySelector('img');
            if (img) {
                img.onload = function() {
                    printWindow.focus();
                    printWindow.print();
                };
                img.onerror = function() {
                    printWindow.focus();
                    printWindow.print();
                };
            } else {
                printWindow.focus();
                printWindow.print();
            }
        }
        
        // Export to PDF using a screenshot of the editor, so it matches the on-screen layout
        async function exportToPDF() {
            try {
                if (typeof showNotification === 'function') {
                    showNotification('Generating PDF...', 'info');
                }
                
                const editorEl = document.querySelector('.ck-editor__editable_inline') || document.getElementById('documentEditor');
                if (!editorEl || typeof html2canvas === 'undefined' || !window.jspdf) {
                    if (typeof showNotification === 'function') {
                        showNotification('PDF export is not available in this browser', 'error');
                    }
                    return;
                }

                const { jsPDF } = window.jspdf;
                const doc = new jsPDF('p', 'pt', 'a4');

                const canvas = await html2canvas(editorEl, {
                    scale: 2,
                    useCORS: true,
                    logging: false
                });

                const imgData = canvas.toDataURL('image/png');
                const pageWidth = doc.internal.pageSize.getWidth();
                const pageHeight = doc.internal.pageSize.getHeight();
                const imgWidth = pageWidth;
                const imgHeight = canvas.height * imgWidth / canvas.width;
                let heightLeft = imgHeight;
                let position = 0;
                
                doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight, undefined, 'FAST');
                heightLeft -= pageHeight;
                
                // Add additional pages if needed
                while (heightLeft >= 0) {
                    position = heightLeft - imgHeight;
                    doc.addPage();
                    doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight, undefined, 'FAST');
                    heightLeft -= pageHeight;
                }
                
                const filename = (document.getElementById('filename').value || 'document').replace(/\.[^/.]+$/, "") + '.pdf';
                doc.save(filename);
                
                if (typeof showNotification === 'function') {
                    showNotification('PDF downloaded successfully!', 'success');
                }
                closeDownloadModal();
                
            } catch (error) {
                console.error('Error generating PDF:', error);
                if (typeof showNotification === 'function') {
                    showNotification('Error generating PDF. Using print method instead.', 'error');
                }
                
                // Fallback to print method
                const content = getEditorHtml();
                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <html>
                        <head>
                            <title>${document.getElementById('filename').value}</title>
                            <style>${EXPORT_PAGE_STYLES}</style>
                        </head>
                        <body>
                            <div class="page ck-content">
                                ${content}
                            </div>
                        </body>
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
                "<head><meta charset='utf-8'><title>Export HTML to Word Document</title><style>" +
                EXPORT_PAGE_STYLES +
                "</style></head><body><div class='page ck-content'>";
            const footer = "</div></body></html>";
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
    <style>${EXPORT_PAGE_STYLES}</style>
</head>
<body>
    <div class="page ck-content">
        ${content}
    </div>
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
