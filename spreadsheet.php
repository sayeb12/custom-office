<?php
session_start();
require_once 'config.php';

$filename = isset($_GET['file']) ? $_GET['file'] : 'new_spreadsheet_' . date('Y-m-d_H-i-s');
$isNew = !isset($_GET['file']);

// Load existing data from database
$spreadsheetData = [];
$metadata = [
    'styles' => [], 
    'merged' => [], 
    'cols' => 15, 
    'rows' => 50
];

if (!$isNew) {
    $db = (new Database())->getConnection();
    $query = "SELECT data FROM spreadsheets WHERE filename = :filename";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":filename", $filename);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $fileData = json_decode($row['data'], true);
        $spreadsheetData = $fileData['data'] ?? [];
        $metadata = array_merge($metadata, $fileData['metadata'] ?? []);
    }
}

// Save to history
saveToHistory('spreadsheet', $filename);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($filename); ?> - Spreadsheet Editor</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .formula-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 5px 15px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        .formula-input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: monospace;
        }
        
        .cell-reference {
            min-width: 80px;
            padding: 8px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
        }
        
        /* Enhanced Color Picker */
        .color-picker-wrapper {
            position: relative;
            display: inline-block;
        }
        
        .color-picker-trigger {
            width: 40px;
            height: 30px;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        
        .color-palette {
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 10px;
            z-index: 1000;
            display: none;
            grid-template-columns: repeat(8, 1fr);
            gap: 4px;
            width: 180px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            margin-top: 5px;
        }
        
        .color-palette.active {
            display: grid;
        }
        
        .color-option {
            width: 20px;
            height: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 3px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .color-option:hover {
            transform: scale(1.2);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .color-palette-header {
            grid-column: 1 / -1;
            font-size: 11px;
            font-weight: bold;
            color: #666;
            margin-bottom: 5px;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }
        
        /* Improved merged cell styling */
        .spreadsheet-cell.merged {
            display: none !important;
        }
        
        .spreadsheet-cell.main-merge-cell {
            background-color: #f0f8ff !important;
            border: 2px solid #007bff !important;
            position: relative;
            z-index: 2;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="editor-header">
            <div class="editor-toolbar">
                <button onclick="saveSpreadsheet()" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save
                </button>
                <button onclick="downloadSpreadsheet()" class="btn btn-success">
                    <i class="fas fa-download"></i> Download
                </button>
                <button onclick="printSpreadsheet()" class="btn btn-info">
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
            
            <div class="spreadsheet-toolbar">
                <div class="toolbar-group">
                    <button onclick="addRowAbove()" class="btn btn-sm" title="Insert Row Above">
                        <i class="fas fa-plus"></i> Row Above
                    </button>
                    <button onclick="addRowBelow()" class="btn btn-sm" title="Insert Row Below">
                        <i class="fas fa-plus"></i> Row Below
                    </button>
                    <button onclick="addColumnLeft()" class="btn btn-sm" title="Insert Column Left">
                        <i class="fas fa-plus"></i> Col Left
                    </button>
                    <button onclick="addColumnRight()" class="btn btn-sm" title="Insert Column Right">
                        <i class="fas fa-plus"></i> Col Right
                    </button>
                </div>
                
                <div class="toolbar-group">
                    <button onclick="deleteSelectedRow()" class="btn btn-sm btn-danger" title="Delete Row">
                        <i class="fas fa-trash"></i> Delete Row
                    </button>
                    <button onclick="deleteSelectedColumn()" class="btn btn-sm btn-danger" title="Delete Column">
                        <i class="fas fa-trash"></i> Delete Column
                    </button>
                </div>
                
                <div class="toolbar-group">
                    <!-- Enhanced Text Color Picker -->
                    <div class="color-picker-wrapper">
                        <div class="color-picker-trigger" onclick="toggleColorPicker('text')" title="Text Color">
                            <i class="fas fa-font" style="color: #333;"></i>
                        </div>
                        <div class="color-palette" id="textColorPalette">
                            <div class="color-palette-header">Text Color</div>
                            <div class="color-option" style="background-color: #000000;" data-color="#000000"></div>
                            <div class="color-option" style="background-color: #FF0000;" data-color="#FF0000"></div>
                            <div class="color-option" style="background-color: #00FF00;" data-color="#00FF00"></div>
                            <div class="color-option" style="background-color: #0000FF;" data-color="#0000FF"></div>
                            <div class="color-option" style="background-color: #FFFF00;" data-color="#FFFF00"></div>
                            <div class="color-option" style="background-color: #FF00FF;" data-color="#FF00FF"></div>
                            <div class="color-option" style="background-color: #00FFFF;" data-color="#00FFFF"></div>
                            <div class="color-option" style="background-color: #FFFFFF; border: 2px solid #ccc;" data-color="#FFFFFF"></div>
                            <div class="color-option" style="background-color: #808080;" data-color="#808080"></div>
                            <div class="color-option" style="background-color: #C0C0C0;" data-color="#C0C0C0"></div>
                            <div class="color-option" style="background-color: #800000;" data-color="#800000"></div>
                            <div class="color-option" style="background-color: #008000;" data-color="#008000"></div>
                            <div class="color-option" style="background-color: #000080;" data-color="#000080"></div>
                            <div class="color-option" style="background-color: #808000;" data-color="#808000"></div>
                            <div class="color-option" style="background-color: #800080;" data-color="#800080"></div>
                            <div class="color-option" style="background-color: #008080;" data-color="#008080"></div>
                        </div>
                    </div>
                    
                    <!-- Enhanced Background Color Picker -->
                    <div class="color-picker-wrapper">
                        <div class="color-picker-trigger" onclick="toggleColorPicker('bg')" title="Fill Color">
                            <i class="fas fa-fill-drip" style="color: #333;"></i>
                        </div>
                        <div class="color-palette" id="bgColorPalette">
                            <div class="color-palette-header">Fill Color</div>
                            <div class="color-option" style="background-color: #FFFFFF;" data-color="#FFFFFF"></div>
                            <div class="color-option" style="background-color: #FFCCCC;" data-color="#FFCCCC"></div>
                            <div class="color-option" style="background-color: #CCFFCC;" data-color="#CCFFCC"></div>
                            <div class="color-option" style="background-color: #CCCCFF;" data-color="#CCCCFF"></div>
                            <div class="color-option" style="background-color: #FFFFCC;" data-color="#FFFFCC"></div>
                            <div class="color-option" style="background-color: #FFCCFF;" data-color="#FFCCFF"></div>
                            <div class="color-option" style="background-color: #CCFFFF;" data-color="#CCFFFF"></div>
                            <div class="color-option" style="background-color: #E6E6E6;" data-color="#E6E6E6"></div>
                            <div class="color-option" style="background-color: #F0F0F0;" data-color="#F0F0F0"></div>
                            <div class="color-option" style="background-color: #FFE6CC;" data-color="#FFE6CC"></div>
                            <div class="color-option" style="background-color: #E6FFCC;" data-color="#E6FFCC"></div>
                            <div class="color-option" style="background-color: #CCE6FF;" data-color="#CCE6FF"></div>
                            <div class="color-option" style="background-color: #FFE6FF;" data-color="#FFE6FF"></div>
                            <div class="color-option" style="background-color: #E6FFFF;" data-color="#E6FFFF"></div>
                            <div class="color-option" style="background-color: #FFFFE6;" data-color="#FFFFE6"></div>
                            <div class="color-option" style="background-color: #000000;" data-color="#000000"></div>
                        </div>
                    </div>
                    
                    <select id="fontFamily" onchange="applyFontFamily(this.value)">
                        <option value="">Font</option>
                        <option value="Arial">Arial</option>
                        <option value="Helvetica">Helvetica</option>
                        <option value="Times New Roman">Times New Roman</option>
                        <option value="Courier New">Courier New</option>
                        <option value="Verdana">Verdana</option>
                        <option value="Georgia">Georgia</option>
                    </select>
                    <select id="fontSize" onchange="applyFontSize(this.value)">
                        <option value="">Size</option>
                        <option value="8">8</option>
                        <option value="9">9</option>
                        <option value="10">10</option>
                        <option value="11">11</option>
                        <option value="12" selected>12</option>
                        <option value="14">14</option>
                        <option value="16">16</option>
                        <option value="18">18</option>
                        <option value="20">20</option>
                        <option value="24">24</option>
                    </select>
                </div>
                
                <div class="toolbar-group">
                    <button onclick="toggleBold()" class="btn btn-sm format-btn" id="boldBtn" title="Bold (Ctrl+B)">
                        <i class="fas fa-bold"></i>
                    </button>
                    <button onclick="toggleItalic()" class="btn btn-sm format-btn" id="italicBtn" title="Italic (Ctrl+I)">
                        <i class="fas fa-italic"></i>
                    </button>
                    <button onclick="toggleUnderline()" class="btn btn-sm format-btn" id="underlineBtn" title="Underline (Ctrl+U)">
                        <i class="fas fa-underline"></i>
                    </button>
                </div>
                
                <div class="toolbar-group">
                    <button onclick="applyAlignment('left')" class="btn btn-sm" title="Align Left">
                        <i class="fas fa-align-left"></i>
                    </button>
                    <button onclick="applyAlignment('center')" class="btn btn-sm" title="Align Center">
                        <i class="fas fa-align-center"></i>
                    </button>
                    <button onclick="applyAlignment('right')" class="btn btn-sm" title="Align Right">
                        <i class="fas fa-align-right"></i>
                    </button>
                    <button onclick="applyAlignment('justify')" class="btn btn-sm" title="Justify">
                        <i class="fas fa-align-justify"></i>
                    </button>
                </div>

                <div class="toolbar-group">
                    <button onclick="mergeCells()" class="btn btn-sm" title="Merge Cells">
                        <i class="fas fa-object-group"></i> Merge
                    </button>
                    <button onclick="unmergeCells()" class="btn btn-sm" title="Unmerge Cells">
                        <i class="fas fa-object-ungroup"></i> Unmerge
                    </button>
                </div>
                
                <div class="toolbar-group">
                    <button onclick="applyBorder('all')" class="btn btn-sm" title="All Borders">
                        <i class="fas fa-border-all"></i>
                    </button>
                    <button onclick="applyBorder('outside')" class="btn btn-sm" title="Outside Border">
                        <i class="fas fa-square"></i>
                    </button>
                    <button onclick="applyBorder('none')" class="btn btn-sm" title="No Border">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </header>

        <!-- Formula Bar -->
        <div class="formula-bar">
            <div class="cell-reference" id="activeCell">A1</div>
            <input type="text" class="formula-input" id="formulaInput" placeholder="Enter value or formula" onkeydown="handleFormulaInput(event)">
            <button onclick="applyFormula()" class="btn btn-sm btn-primary">
                <i class="fas fa-check"></i>
            </button>
        </div>

        <div class="spreadsheet-wrapper">
            <div class="spreadsheet-container" id="spreadsheetContainer">
                <div class="spreadsheet-header">
                    <div class="corner-cell" onclick="selectAll()">
                        <i class="fas fa-arrows-alt"></i>
                    </div>
                    <?php for ($col = 0; $col < $metadata['cols']; $col++): ?>
                        <div class="header-cell" data-col="<?php echo $col; ?>">
                            <span><?php echo getColumnName($col); ?></span>
                        </div>
                    <?php endfor; ?>
                </div>
                
                <div class="spreadsheet-body" id="spreadsheetBody">
                    <?php for ($row = 0; $row < $metadata['rows']; $row++): ?>
                    <div class="spreadsheet-row" data-row="<?php echo $row; ?>">
                        <div class="row-header" data-row="<?php echo $row; ?>">
                            <span><?php echo $row + 1; ?></span>
                        </div>
                        <?php for ($col = 0; $col < $metadata['cols']; $col++): 
                            $cellId = $row . '_' . $col;
                            $cellStyle = $metadata['styles'][$cellId] ?? [];
                            $cellValue = isset($spreadsheetData[$row][$col]) ? htmlspecialchars($spreadsheetData[$row][$col]) : '';
                            $isMerged = isCellMerged($row, $col, $metadata['merged'] ?? []);
                            $isMainMergeCell = isMainMergeCell($row, $col, $metadata['merged'] ?? []);
                        ?>
                            <div class="spreadsheet-cell <?php echo $isMerged ? 'merged' : ''; ?> <?php echo $isMainMergeCell ? 'main-merge-cell' : ''; ?>" 
                                 contenteditable="true"
                                 data-row="<?php echo $row; ?>" 
                                 data-col="<?php echo $col; ?>"
                                 id="cell_<?php echo $row; ?>_<?php echo $col; ?>"
                                 oninput="handleCellInput(<?php echo $row; ?>, <?php echo $col; ?>, this.innerHTML)"
                                 onmousedown="startSelection(<?php echo $row; ?>, <?php echo $col; ?>)"
                                 onmouseover="extendSelection(<?php echo $row; ?>, <?php echo $col; ?>)"
                                 onfocus="focusCell(<?php echo $row; ?>, <?php echo $col; ?>)"
                                 onkeydown="handleCellKeyDown(event, <?php echo $row; ?>, <?php echo $col; ?>)"
                                 style="<?php echo getCellStyle($cellStyle); ?>">
                                <?php echo $cellValue; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        
        <div class="status-bar">
            <div class="cell-info" id="cellInfo">Ready</div>
            <div class="selection-info" id="selectionInfo"></div>
            <div class="zoom-info" id="zoomInfo">100%</div>
        </div>
    </div>

    <!-- Download Modal -->
    <div id="downloadModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeDownloadModal()">&times;</span>
            <h3>Download Spreadsheet</h3>
            <div class="download-options">
                <button onclick="exportToCSV()" class="btn btn-success">
                    <i class="fas fa-file-csv"></i> Download as CSV
                </button>
                <button onclick="exportToExcel()" class="btn btn-success">
                    <i class="fas fa-file-excel"></i> Download as Excel
                </button>
                <button onclick="exportToJSON()" class="btn btn-success">
                    <i class="fas fa-file-code"></i> Download as JSON
                </button>
            </div>
        </div>
    </div>

    <!-- History Modal -->
    <div id="historyModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeHistoryModal()">&times;</span>
            <h3>Recent Files</h3>
            <div id="historyList" class="history-list">
                <!-- History items will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // Global variables
        const spreadsheetData = <?php echo json_encode($spreadsheetData); ?>;
        const metadata = <?php echo json_encode($metadata); ?>;
        const currentFile = "<?php echo $filename; ?>";
        const isNew = <?php echo $isNew ? 'true' : 'false'; ?>;
        
        let selectedCells = [];
        let isSelecting = false;
        let selectionStart = null;
        let currentCell = null;
        let activeCellElement = null;
        let activeColorPicker = null;

        // Initialize spreadsheet
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Initializing spreadsheet...');
            initializeData();
            applyExistingMergedCells();
            document.addEventListener('mouseup', stopSelection);
            document.addEventListener('keydown', handleKeyDown);
            document.addEventListener('click', closeAllColorPickers);
            updateFormatButtons();
            
            // Set initial active cell
            if (selectedCells.length === 0) {
                focusCell(0, 0);
            }
        });

        function initializeData() {
            for (let row = 0; row < metadata.rows; row++) {
                if (!spreadsheetData[row]) {
                    spreadsheetData[row] = [];
                }
                for (let col = 0; col < metadata.cols; col++) {
                    if (spreadsheetData[row][col] === undefined) {
                        spreadsheetData[row][col] = '';
                    }
                }
            }
        }

        // Enhanced Color Picker Functions
        function toggleColorPicker(type) {
            const textPalette = document.getElementById('textColorPalette');
            const bgPalette = document.getElementById('bgColorPalette');
            
            // Close all first
            closeAllColorPickers();
            
            // Open the requested palette
            if (type === 'text') {
                textPalette.classList.add('active');
                activeColorPicker = 'text';
            } else if (type === 'bg') {
                bgPalette.classList.add('active');
                activeColorPicker = 'bg';
            }
            
            // Add event listeners to color options
            setTimeout(() => {
                document.querySelectorAll('.color-option').forEach(option => {
                    option.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const color = this.getAttribute('data-color');
                        if (activeColorPicker === 'text') {
                            applyTextColor(color);
                        } else if (activeColorPicker === 'bg') {
                            applyBackgroundColor(color);
                        }
                        closeAllColorPickers();
                    });
                });
            }, 10);
        }

        function closeAllColorPickers(e) {
            // Don't close if clicking on color picker elements
            if (e && (e.target.closest('.color-picker-wrapper') || e.target.classList.contains('color-option'))) {
                return;
            }
            
            document.getElementById('textColorPalette').classList.remove('active');
            document.getElementById('bgColorPalette').classList.remove('active');
            activeColorPicker = null;
        }

        // Add hover effects for color pickers
        document.addEventListener('DOMContentLoaded', function() {
            const colorTriggers = document.querySelectorAll('.color-picker-trigger');
            
            colorTriggers.forEach(trigger => {
                trigger.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8f9fa';
                });
                
                trigger.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = 'white';
                });
            });
        });

        // SELECTION FUNCTIONS
        function startSelection(row, col) {
            isSelecting = true;
            selectionStart = { row, col };
            clearSelection();
            addToSelection(row, col);
            currentCell = { row, col };
            updateActiveCell(row, col);
            updateFormatButtons();
        }

        function extendSelection(row, col) {
            if (!isSelecting || !selectionStart) return;
            
            clearSelection();
            
            const startRow = Math.min(selectionStart.row, row);
            const endRow = Math.max(selectionStart.row, row);
            const startCol = Math.min(selectionStart.col, col);
            const endCol = Math.max(selectionStart.col, col);
            
            for (let r = startRow; r <= endRow; r++) {
                for (let c = startCol; c <= endCol; c++) {
                    addToSelection(r, c);
                }
            }
            updateSelectionInfo();
        }

        function stopSelection() {
            isSelecting = false;
            selectionStart = null;
        }

        function clearSelection() {
            document.querySelectorAll('.spreadsheet-cell.selected').forEach(cell => {
                cell.classList.remove('selected');
            });
            selectedCells = [];
            updateSelectionInfo();
        }

        function addToSelection(row, col) {
            const cell = document.getElementById(`cell_${row}_${col}`);
            if (cell && !cell.classList.contains('merged')) {
                cell.classList.add('selected');
                if (!selectedCells.some(c => c.row === row && c.col === col)) {
                    selectedCells.push({ row, col });
                }
            }
        }

        function focusCell(row, col) {
            if (!isSelecting) {
                clearSelection();
                addToSelection(row, col);
            }
            currentCell = { row, col };
            updateActiveCell(row, col);
            updateCellInfo(row, col);
            updateFormatButtons();
            
            // Update formula input with cell value
            const cellValue = spreadsheetData[row] && spreadsheetData[row][col] ? spreadsheetData[row][col] : '';
            document.getElementById('formulaInput').value = cellValue;
        }

        function updateActiveCell(row, col) {
            const colName = getColumnName(col);
            document.getElementById('activeCell').textContent = `${colName}${row + 1}`;
            
            // Store reference to active cell element
            activeCellElement = document.getElementById(`cell_${row}_${col}`);
        }

        function selectAll() {
            clearSelection();
            for (let row = 0; row < metadata.rows; row++) {
                for (let col = 0; col < metadata.cols; col++) {
                    addToSelection(row, col);
                }
            }
            updateSelectionInfo();
        }

        function updateCellInfo(row, col) {
            const colName = getColumnName(col);
            document.getElementById('cellInfo').textContent = `${colName}${row + 1}`;
        }

        function updateSelectionInfo() {
            if (selectedCells.length === 0) {
                document.getElementById('selectionInfo').textContent = '';
                return;
            }
            
            const rows = selectedCells.map(c => c.row);
            const cols = selectedCells.map(c => c.col);
            const startRow = Math.min(...rows);
            const endRow = Math.max(...rows);
            const startCol = Math.min(...cols);
            const endCol = Math.max(...cols);
            
            const startColName = getColumnName(startCol);
            const endColName = getColumnName(endCol);
            
            if (startRow === endRow && startCol === endCol) {
                document.getElementById('selectionInfo').textContent = `${startColName}${startRow + 1}`;
            } else {
                document.getElementById('selectionInfo').textContent = 
                    `${startColName}${startRow + 1}:${endColName}${endRow + 1}`;
            }
        }

        function getColumnName(col) {
            let name = '';
            while (col >= 0) {
                name = String.fromCharCode(65 + (col % 26)) + name;
                col = Math.floor(col / 26) - 1;
            }
            return name || 'A';
        }

        // CELL OPERATIONS
        function handleCellInput(row, col, value) {
            if (!spreadsheetData[row]) spreadsheetData[row] = [];
            spreadsheetData[row][col] = value;
            
            // Update formula input
            document.getElementById('formulaInput').value = value;
        }

        function handleCellKeyDown(event, row, col) {
            if (event.key === 'Enter') {
                event.preventDefault();
                // Move to next row
                if (row < metadata.rows - 1) {
                    const nextCell = document.getElementById(`cell_${row + 1}_${col}`);
                    if (nextCell) {
                        nextCell.focus();
                    }
                }
            } else if (event.key === 'Tab') {
                event.preventDefault();
                // Move to next column
                if (col < metadata.cols - 1) {
                    const nextCell = document.getElementById(`cell_${row}_${col + 1}`);
                    if (nextCell) {
                        nextCell.focus();
                    }
                }
            }
        }

        function handleFormulaInput(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                applyFormula();
            }
        }

        function applyFormula() {
            const formulaValue = document.getElementById('formulaInput').value;
            
            if (selectedCells.length === 0 && currentCell) {
                // Apply to current cell only
                const { row, col } = currentCell;
                if (!spreadsheetData[row]) spreadsheetData[row] = [];
                spreadsheetData[row][col] = formulaValue;
                
                const cellElement = document.getElementById(`cell_${row}_${col}`);
                if (cellElement) {
                    cellElement.innerHTML = formulaValue;
                }
            } else if (selectedCells.length > 0) {
                // Apply to all selected cells
                selectedCells.forEach(({ row, col }) => {
                    if (!spreadsheetData[row]) spreadsheetData[row] = [];
                    spreadsheetData[row][col] = formulaValue;
                    
                    const cellElement = document.getElementById(`cell_${row}_${col}`);
                    if (cellElement) {
                        cellElement.innerHTML = formulaValue;
                    }
                });
            }
            
            showNotification('Value applied to selected cells');
        }

        // FORMATTING FUNCTIONS
        function applyBackgroundColor(color) {
            applyToSelectedCells('background-color', color);
        }

        function applyTextColor(color) {
            applyToSelectedCells('color', color);
        }

        function applyFontSize(size) {
            applyToSelectedCells('font-size', size + 'px');
        }

        function applyFontFamily(fontFamily) {
            applyToSelectedCells('font-family', fontFamily);
        }

        function toggleBold() {
            toggleFormat('font-weight', 'bold', 'normal');
        }

        function toggleItalic() {
            toggleFormat('font-style', 'italic', 'normal');
        }

        function toggleUnderline() {
            toggleFormat('text-decoration', 'underline', 'none');
        }

        function toggleFormat(property, valueOn, valueOff) {
            if (selectedCells.length === 0) {
                showNotification('Please select cells first', 'error');
                return;
            }
            
            const firstCell = selectedCells[0];
            const cellId = `${firstCell.row}_${firstCell.col}`;
            const currentStyle = metadata.styles[cellId] || {};
            const currentValue = currentStyle[property];
            
            const newValue = currentValue === valueOn ? valueOff : valueOn;
            applyToSelectedCells(property, newValue);
        }

        function applyAlignment(align) {
            applyToSelectedCells('text-align', align);
        }

        function applyBorder(type) {
            if (selectedCells.length === 0) {
                showNotification('Please select cells first', 'error');
                return;
            }
            
            selectedCells.forEach(cell => {
                const { row, col } = cell;
                const cellElement = document.getElementById(`cell_${row}_${col}`);
                
                if (!cellElement || cellElement.classList.contains('merged')) return;
                
                switch(type) {
                    case 'all':
                        cellElement.style.border = '1px solid #000';
                        break;
                    case 'outside':
                        cellElement.style.border = '1px solid #000';
                        break;
                    case 'none':
                        cellElement.style.border = '1px solid #e0e0e0';
                        break;
                }
            });
            
            showNotification('Border applied');
        }

        function applyToSelectedCells(property, value) {
            if (selectedCells.length === 0) {
                showNotification('Please select cells first', 'error');
                return;
            }
            
            selectedCells.forEach(cell => {
                const { row, col } = cell;
                const cellId = `${row}_${col}`;
                const cellElement = document.getElementById(`cell_${row}_${col}`);
                
                if (!cellElement || cellElement.classList.contains('merged')) return;
                
                if (!metadata.styles[cellId]) {
                    metadata.styles[cellId] = {};
                }
                
                // Update the style in metadata
                metadata.styles[cellId][property] = value;
                
                // Apply the style directly to the element
                cellElement.style[property] = value;
            });
            
            updateFormatButtons();
            showNotification('Format applied to selected cells');
        }

        function updateFormatButtons() {
            if (selectedCells.length === 0) return;
            
            const firstCell = selectedCells[0];
            const cellId = `${firstCell.row}_${firstCell.col}`;
            const style = metadata.styles[cellId] || {};
            const cellElement = document.getElementById(`cell_${firstCell.row}_${firstCell.col}`);
            
            if (!cellElement) return;
            
            // Get computed style for more accurate reading
            const computedStyle = window.getComputedStyle(cellElement);
            
            // Update button active states
            document.getElementById('boldBtn').classList.toggle('active', 
                computedStyle.fontWeight === 'bold' || computedStyle.fontWeight === '700');
            document.getElementById('italicBtn').classList.toggle('active', 
                computedStyle.fontStyle === 'italic');
            document.getElementById('underlineBtn').classList.toggle('active', 
                computedStyle.textDecoration.includes('underline'));
        }

        // MERGE FUNCTIONALITY
        function mergeCells() {
            if (selectedCells.length < 2) {
                showNotification('Please select at least 2 cells to merge', 'error');
                return;
            }
            
            const rows = selectedCells.map(c => c.row);
            const cols = selectedCells.map(c => c.col);
            
            const startRow = Math.min(...rows);
            const endRow = Math.max(...rows);
            const startCol = Math.min(...cols);
            const endCol = Math.max(...cols);
            
            const expectedCellCount = (endRow - startRow + 1) * (endCol - startCol + 1);
            if (selectedCells.length !== expectedCellCount) {
                showNotification('Please select a rectangular area to merge', 'error');
                return;
            }
            
            for (let row = startRow; row <= endRow; row++) {
                for (let col = startCol; col <= endCol; col++) {
                    const cell = document.getElementById(`cell_${row}_${col}`);
                    if (cell && cell.classList.contains('merged')) {
                        showNotification('Cannot merge already merged cells', 'error');
                        return;
                    }
                }
            }
            
            if (!metadata.merged) metadata.merged = [];
            const mergeRange = [startRow, startCol, endRow, endCol];
            metadata.merged.push(mergeRange);
            
            const mainCell = document.getElementById(`cell_${startRow}_${startCol}`);
            const mainContent = mainCell.innerHTML;
            const mainCellStyle = mainCell.style.cssText;
            
            // Calculate merged cell dimensions
            const cellWidth = 100; // Base cell width
            const cellHeight = 25; // Base cell height
            
            for (let row = startRow; row <= endRow; row++) {
                for (let col = startCol; col <= endCol; col++) {
                    const cell = document.getElementById(`cell_${row}_${col}`);
                    if (cell) {
                        if (row === startRow && col === startCol) {
                            // Main cell - make it span
                            cell.classList.add('main-merge-cell');
                            cell.style.width = `${(endCol - startCol + 1) * cellWidth}px`;
                            cell.style.height = `${(endRow - startRow + 1) * cellHeight}px`;
                            cell.innerHTML = mainContent;
                            cell.style.cssText += mainCellStyle;
                        } else {
                            // Other cells - hide and make non-editable
                            cell.classList.add('merged');
                            cell.style.display = 'none';
                            cell.contentEditable = 'false';
                            if (spreadsheetData[row]) {
                                spreadsheetData[row][col] = '';
                            }
                        }
                    }
                }
            }
            
            clearSelection();
            showNotification('Cells merged successfully');
        }

        function unmergeCells() {
            if (selectedCells.length === 0) {
                showNotification('Please select a merged cell to unmerge', 'error');
                return;
            }
            
            const cell = selectedCells[0];
            const cellElement = document.getElementById(`cell_${cell.row}_${cell.col}`);
            
            if (!cellElement.classList.contains('main-merge-cell')) {
                showNotification('Please select the main merged cell (top-left cell)', 'error');
                return;
            }
            
            let mergeIndex = -1;
            let mergeRange = null;
            
            for (let i = 0; i < metadata.merged.length; i++) {
                const range = metadata.merged[i];
                if (range[0] === cell.row && range[1] === cell.col) {
                    mergeRange = range;
                    mergeIndex = i;
                    break;
                }
            }
            
            if (!mergeRange) {
                showNotification('Could not find merge data', 'error');
                return;
            }
            
            const [startRow, startCol, endRow, endCol] = mergeRange;
            
            for (let row = startRow; row <= endRow; row++) {
                for (let col = startCol; col <= endCol; col++) {
                    const cell = document.getElementById(`cell_${row}_${col}`);
                    if (cell) {
                        cell.classList.remove('merged', 'main-merge-cell');
                        cell.style.width = '';
                        cell.style.height = '';
                        cell.style.display = '';
                        cell.contentEditable = 'true';
                    }
                }
            }
            
            metadata.merged.splice(mergeIndex, 1);
            
            clearSelection();
            showNotification('Cells unmerged successfully');
        }

        function applyExistingMergedCells() {
            if (!metadata.merged || metadata.merged.length === 0) return;
            
            metadata.merged.forEach(mergeRange => {
                const [startRow, startCol, endRow, endCol] = mergeRange;
                const cellWidth = 100;
                const cellHeight = 25;
                
                for (let row = startRow; row <= endRow; row++) {
                    for (let col = startCol; col <= endCol; col++) {
                        const cell = document.getElementById(`cell_${row}_${col}`);
                        if (cell) {
                            if (row === startRow && col === startCol) {
                                cell.classList.add('main-merge-cell');
                                cell.style.width = `${(endCol - startCol + 1) * cellWidth}px`;
                                cell.style.height = `${(endRow - startRow + 1) * cellHeight}px`;
                            } else {
                                cell.classList.add('merged');
                                cell.style.display = 'none';
                                cell.contentEditable = 'false';
                            }
                        }
                    }
                }
            });
        }

        // EXPORT FUNCTIONS - FIXED EXCEL EXPORT
        function downloadSpreadsheet() {
            document.getElementById('downloadModal').style.display = 'block';
        }

        function closeDownloadModal() {
            document.getElementById('downloadModal').style.display = 'none';
        }

        function exportToCSV() {
            try {
                let csvContent = '';
                const delimiter = ',';
                const lineBreak = '\r\n';
                
                for (let row = 0; row < metadata.rows; row++) {
                    const rowData = [];
                    for (let col = 0; col < metadata.cols; col++) {
                        if (isCellMergedInData(row, col) && !isMainMergeCellInData(row, col)) {
                            rowData.push('');
                            continue;
                        }
                        
                        let cellValue = spreadsheetData[row] && spreadsheetData[row][col] ? 
                            spreadsheetData[row][col] : '';
                        
                        // Remove HTML tags
                        cellValue = cellValue.replace(/<[^>]*>/g, '');
                        
                        if (cellValue.includes(delimiter) || cellValue.includes('"') || cellValue.includes('\n') || cellValue.includes('\r')) {
                            cellValue = '"' + cellValue.replace(/"/g, '""') + '"';
                        }
                        
                        rowData.push(cellValue);
                    }
                    csvContent += rowData.join(delimiter) + lineBreak;
                }
                
                const filename = (document.getElementById('filename').value || 'spreadsheet').replace(/\.[^/.]+$/, "") + '.csv';
                downloadFile(csvContent, filename, 'text/csv; charset=utf-8');
                showNotification('CSV file downloaded successfully!');
                
            } catch (error) {
                console.error('Error exporting to CSV:', error);
                showNotification('Error exporting to CSV: ' + error.message, 'error');
            }
        }

        function exportToExcel() {
            try {
                // Create proper Excel XML format
                let xmlContent = [
                    <?php echo "'<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>'"; ?>,
                    <?php echo "'<?mso-application progid=\"Excel.Sheet\"?>'"; ?>,
                    '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"',
                    '  xmlns:o="urn:schemas-microsoft-com:office:office"',
                    '  xmlns:x="urn:schemas-microsoft-com:office:excel"',
                    '  xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"',
                    '  xmlns:html="http://www.w3.org/TR/REC-html40">',
                    '  <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">',
                    '    <Author>Custom Office Suite</Author>',
                    '    <Created>' + new Date().toISOString() + '</Created>',
                    '  </DocumentProperties>',
                    '  <Styles>',
                    '    <Style ss:ID="Default" ss:Name="Normal">',
                    '      <Alignment ss:Vertical="Bottom"/>',
                    '      <Borders/>',
                    '      <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#000000"/>',
                    '      <Interior/>',
                    '      <NumberFormat/>',
                    '      <Protection/>',
                    '    </Style>',
                    '  </Styles>',
                    '  <Worksheet ss:Name="Sheet1">',
                    '    <Table>'
                ].join('\n');
                
                for (let row = 0; row < metadata.rows; row++) {
                    xmlContent += '\n      <Row>';
                    for (let col = 0; col < metadata.cols; col++) {
                        if (isCellMergedInData(row, col) && !isMainMergeCellInData(row, col)) {
                            xmlContent += '<Cell><Data ss:Type="String"></Data></Cell>';
                            continue;
                        }
                        
                        const cellValue = spreadsheetData[row] && spreadsheetData[row][col] ? 
                            spreadsheetData[row][col] : '';
                        
                        // Remove HTML tags and trim
                        const cleanValue = cellValue.replace(/<[^>]*>/g, '').trim();
                        
                        let dataType = 'String';
                        let processedValue = escapeXml(cleanValue);
                        
                        // Better number detection
                        const numericValue = cleanValue.replace(/[$,%]/g, '');
                        if (cleanValue !== '' && !isNaN(numericValue) && numericValue.trim() !== '' && cleanValue !== ' ') {
                            dataType = 'Number';
                            processedValue = numericValue;
                        }
                        
                        xmlContent += `<Cell><Data ss:Type="${dataType}">${processedValue}</Data></Cell>`;
                    }
                    xmlContent += '</Row>';
                }
                
                xmlContent += '\n    </Table>\n  </Worksheet>\n</Workbook>';
                
                const filename = (document.getElementById('filename').value || 'spreadsheet').replace(/\.[^/.]+$/, "") + '.xls';
                
                // Use proper MIME type for Excel
                const excelBlob = new Blob([xmlContent], { 
                    type: 'application/vnd.ms-excel' 
                });
                
                downloadBlob(excelBlob, filename);
                showNotification('Excel file downloaded successfully!');
                
            } catch (error) {
                console.error('Error exporting to Excel:', error);
                showNotification('Error exporting to Excel: ' + error.message, 'error');
            }
        }

        function exportToJSON() {
            try {
                const exportData = {
                    data: spreadsheetData,
                    metadata: metadata,
                    exportDate: new Date().toISOString(),
                    version: '1.0'
                };
                
                const filename = (document.getElementById('filename').value || 'spreadsheet') + '.json';
                downloadFile(JSON.stringify(exportData, null, 2), filename, 'application/json');
                showNotification('JSON file downloaded successfully!');
            } catch (error) {
                console.error('Error exporting to JSON:', error);
                showNotification('Error exporting to JSON: ' + error.message, 'error');
            }
        }

        function downloadFile(content, filename, mimeType) {
            try {
                const blob = new Blob([content], { type: mimeType });
                downloadBlob(blob, filename);
            } catch (error) {
                console.error('Error downloading file:', error);
                showNotification('Error downloading file', 'error');
            }
        }

        function downloadBlob(blob, filename) {
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.style.display = 'none';
            
            document.body.appendChild(a);
            a.click();
            
            // Clean up
            setTimeout(() => {
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            }, 100);
        }

        function escapeXml(unsafe) {
            if (unsafe === null || unsafe === undefined) return '';
            return unsafe.toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&apos;');
        }

        // Helper functions
        function isCellMergedInData(row, col) {
            if (!metadata.merged) return false;
            for (const mergeRange of metadata.merged) {
                const [startRow, startCol, endRow, endCol] = mergeRange;
                if (row >= startRow && row <= endRow && col >= startCol && col <= endCol) {
                    return true;
                }
            }
            return false;
        }

        function isMainMergeCellInData(row, col) {
            if (!metadata.merged) return false;
            for (const mergeRange of metadata.merged) {
                const [startRow, startCol] = mergeRange;
                if (row === startRow && col === startCol) {
                    return true;
                }
            }
            return false;
        }

        // SAVE FUNCTIONALITY
        function saveSpreadsheet() {
            const filename = document.getElementById('filename').value || 'untitled_spreadsheet';
            const saveData = {
                data: spreadsheetData,
                metadata: metadata
            };
            
            const formData = new FormData();
            formData.append('type', 'spreadsheet');
            formData.append('filename', filename);
            formData.append('data', JSON.stringify(saveData));
            formData.append('isNew', isNew);
            formData.append('oldFilename', currentFile);
            
            fetch('save.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Spreadsheet saved successfully!');
                    if (isNew) {
                        window.history.replaceState({}, '', `spreadsheet.php?file=${encodeURIComponent(filename)}`);
                    }
                } else {
                    showNotification('Error saving spreadsheet: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error saving spreadsheet', 'error');
            });
        }

        // HISTORY FUNCTIONALITY
        function showHistory() {
            loadHistory('spreadsheet');
            document.getElementById('historyModal').style.display = 'block';
        }

        function closeHistoryModal() {
            document.getElementById('historyModal').style.display = 'none';
        }

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

        // KEYBOARD SHORTCUTS
        function handleKeyDown(event) {
            if (event.ctrlKey) {
                switch(event.key) {
                    case 'b':
                        event.preventDefault();
                        toggleBold();
                        break;
                    case 'i':
                        event.preventDefault();
                        toggleItalic();
                        break;
                    case 'u':
                        event.preventDefault();
                        toggleUnderline();
                        break;
                    case 's':
                        event.preventDefault();
                        saveSpreadsheet();
                        break;
                }
            }
        }

        // NOTIFICATION FUNCTION
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
function getColumnName($col) {
    $name = '';
    while ($col >= 0) {
        $name = chr(65 + ($col % 26)) . $name;
        $col = floor($col / 26) - 1;
    }
    return $name;
}

function getCellStyle($style) {
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
    foreach ($mergedRanges as $range) {
        list($startRow, $startCol, $endRow, $endCol) = $range;
        if ($row >= $startRow && $row <= $endRow && $col >= $startCol && $col <= $endCol) {
            return true;
        }
    }
    return false;
}

function isMainMergeCell($row, $col, $mergedRanges) {
    foreach ($mergedRanges as $range) {
        list($startRow, $startCol, $endRow, $endCol) = $range;
        if ($row === $startRow && $col === $startCol) {
            return true;
        }
    }
    return false;
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
