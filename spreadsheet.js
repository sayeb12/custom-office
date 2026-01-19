// Global variables
let selectedCells = [];
let currentSelection = { startRow: -1, startCol: -1, endRow: -1, endCol: -1 };
let isSelecting = false;
let dragStart = null;

// Initialize spreadsheet
document.addEventListener('DOMContentLoaded', function() {
    console.log('Spreadsheet initialized');
    initializeEventListeners();
    applyExistingMergedCells();
});

function initializeEventListeners() {
    // Add mouseup event to document to end selection
    document.addEventListener('mouseup', endSelection);
    
    // Add keyboard shortcuts
    document.addEventListener('keydown', handleKeyDown);
}

// Cell selection and manipulation
function startSelection(row, col) {
    isSelecting = true;
    dragStart = { row, col };
    clearSelection();
    selectSingleCell(row, col);
}

function dragSelection(row, col) {
    if (!isSelecting || !dragStart) return;
    
    clearSelection();
    
    const startRow = Math.min(dragStart.row, row);
    const endRow = Math.max(dragStart.row, row);
    const startCol = Math.min(dragStart.col, col);
    const endCol = Math.max(dragStart.col, col);
    
    // Select rectangular area
    for (let r = startRow; r <= endRow; r++) {
        for (let c = startCol; c <= endCol; c++) {
            const cell = document.getElementById(`cell_${r}_${c}`);
            if (cell && !cell.classList.contains('merged')) {
                cell.classList.add('selected');
                selectedCells.push({ row: r, col: c });
            }
        }
    }
    
    currentSelection = { startRow, startCol, endRow, endCol };
    updateSelectionInfo();
}

function endSelection() {
    isSelecting = false;
    dragStart = null;
}

function clearSelection() {
    document.querySelectorAll('.spreadsheet-cell.selected').forEach(cell => {
        cell.classList.remove('selected');
    });
    selectedCells = [];
    currentSelection = { startRow: -1, startCol: -1, endRow: -1, endCol: -1 };
    updateSelectionInfo();
}

function selectSingleCell(row, col) {
    const cell = document.getElementById(`cell_${row}_${col}`);
    if (cell && !cell.classList.contains('merged')) {
        cell.classList.add('selected');
        selectedCells.push({ row, col });
        currentSelection = { startRow: row, startCol: col, endRow: row, endCol: col };
        updateCellInfo(row, col);
        updateSelectionInfo();
    }
}

function selectAll() {
    clearSelection();
    document.querySelectorAll('.spreadsheet-cell').forEach(cell => {
        if (!cell.classList.contains('merged')) {
            cell.classList.add('selected');
            const row = parseInt(cell.dataset.row);
            const col = parseInt(cell.dataset.col);
            selectedCells.push({ row, col });
        }
    });
    
    if (selectedCells.length > 0) {
        const firstCell = selectedCells[0];
        const lastCell = selectedCells[selectedCells.length - 1];
        currentSelection = {
            startRow: firstCell.row,
            startCol: firstCell.col,
            endRow: lastCell.row,
            endCol: lastCell.col
        };
        updateSelectionInfo();
    }
}

function updateCellInfo(row, col) {
    const colName = getColumnName(col);
    document.getElementById('cellInfo').textContent = `${colName}${row + 1}`;
}

function updateSelectionInfo() {
    const { startRow, startCol, endRow, endCol } = currentSelection;
    if (startRow === -1) {
        document.getElementById('selectionInfo').textContent = '';
        return;
    }
    
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

// Cell input handling
function updateCell(row, col, value) {
    if (!spreadsheetData[row]) spreadsheetData[row] = [];
    spreadsheetData[row][col] = value;
    console.log('Cell updated:', row, col, value);
}

// Cell formatting functions
function applyBackgroundColor(color) {
    applyToSelectedCells('backgroundColor', color);
}

function applyTextColor(color) {
    applyToSelectedCells('color', color);
}

function applyFontSize(size) {
    applyToSelectedCells('fontSize', size + 'px');
}

function applyFontFamily(fontFamily) {
    applyToSelectedCells('fontFamily', fontFamily);
}

function toggleFormat(format) {
    if (selectedCells.length === 0) {
        showNotification('Please select cells first', 'error');
        return;
    }
    
    const firstCell = selectedCells[0];
    const cellId = `${firstCell.row}_${firstCell.col}`;
    const currentStyle = metadata.styles[cellId] || {};
    
    const toggleValues = {
        'bold': { fontWeight: currentStyle.fontWeight === 'bold' ? 'normal' : 'bold' },
        'italic': { fontStyle: currentStyle.fontStyle === 'italic' ? 'normal' : 'italic' },
        'underline': { textDecoration: currentStyle.textDecoration === 'underline' ? 'none' : 'underline' }
    };
    
    if (toggleValues[format]) {
        applyToSelectedCells(toggleValues[format]);
        updateFormatButtons();
    }
}

function applyAlignment(align) {
    applyToSelectedCells({ textAlign: align });
}

function applyToSelectedCells(style, value = null) {
    if (selectedCells.length === 0) {
        showNotification('Please select cells first', 'error');
        return;
    }
    
    selectedCells.forEach(cell => {
        const row = cell.row;
        const col = cell.col;
        const cellId = `${row}_${col}`;
        const cellElement = document.getElementById(`cell_${row}_${col}`);
        
        if (!cellElement || cellElement.classList.contains('merged')) return;
        
        if (!metadata.styles) metadata.styles = {};
        if (!metadata.styles[cellId]) metadata.styles[cellId] = {};
        
        if (typeof style === 'object') {
            Object.assign(metadata.styles[cellId], style);
        } else {
            metadata.styles[cellId][style] = value;
        }
        
        updateCellStyle(cellElement, metadata.styles[cellId]);
    });
    
    showNotification('Format applied to selected cells');
}

function updateCellStyle(cell, style) {
    let styleString = '';
    Object.keys(style).forEach(key => {
        if (style[key] && style[key] !== 'normal' && style[key] !== 'none') {
            styleString += `${key}: ${style[key]};`;
        }
    });
    cell.style.cssText = styleString;
}

function updateFormatButtons() {
    if (selectedCells.length === 0) return;
    
    const firstCell = selectedCells[0];
    const cellId = `${firstCell.row}_${firstCell.col}`;
    const style = metadata.styles[cellId] || {};
    
    // Update button states
    document.getElementById('boldBtn').classList.toggle('active', style.fontWeight === 'bold');
    document.getElementById('italicBtn').classList.toggle('active', style.fontStyle === 'italic');
    document.getElementById('underlineBtn').classList.toggle('active', style.textDecoration === 'underline');
}

// Row and Column operations
function addRowAbove() {
    const targetRow = selectedCells.length > 0 ? selectedCells[0].row : 0;
    insertRowAt(targetRow);
}

function addRowBelow() {
    const targetRow = selectedCells.length > 0 ? selectedCells[0].row + 1 : metadata.rows;
    insertRowAt(targetRow);
}

function insertRowAt(targetRow) {
    // Shift data down
    for (let row = metadata.rows - 1; row >= targetRow; row--) {
        spreadsheetData[row + 1] = [...(spreadsheetData[row] || [])];
    }
    spreadsheetData[targetRow] = new Array(metadata.cols).fill('');
    
    // Update merged cells
    updateMergedCellsForRowInsert(targetRow);
    
    metadata.rows++;
    rebuildSpreadsheet();
    showNotification('Row added successfully');
}

function addColumnLeft() {
    const targetCol = selectedCells.length > 0 ? selectedCells[0].col : 0;
    insertColumnAt(targetCol);
}

function addColumnRight() {
    const targetCol = selectedCells.length > 0 ? selectedCells[0].col + 1 : metadata.cols;
    insertColumnAt(targetCol);
}

function insertColumnAt(targetCol) {
    // Shift data right
    for (let row = 0; row < metadata.rows; row++) {
        if (!spreadsheetData[row]) spreadsheetData[row] = [];
        for (let col = metadata.cols - 1; col >= targetCol; col--) {
            spreadsheetData[row][col + 1] = spreadsheetData[row][col] || '';
        }
        spreadsheetData[row][targetCol] = '';
    }
    
    // Update merged cells
    updateMergedCellsForColumnInsert(targetCol);
    
    metadata.cols++;
    rebuildSpreadsheet();
    showNotification('Column added successfully');
}

function deleteSelectedRow() {
    if (selectedCells.length === 0) {
        showNotification('Please select a cell in the row to delete', 'error');
        return;
    }
    
    const rowToDelete = selectedCells[0].row;
    
    // Shift data up
    for (let row = rowToDelete; row < metadata.rows - 1; row++) {
        spreadsheetData[row] = [...(spreadsheetData[row + 1] || [])];
    }
    spreadsheetData[metadata.rows - 1] = [];
    
    // Update merged cells
    updateMergedCellsForRowDelete(rowToDelete);
    
    metadata.rows--;
    rebuildSpreadsheet();
    showNotification('Row deleted successfully');
}

function deleteSelectedColumn() {
    if (selectedCells.length === 0) {
        showNotification('Please select a cell in the column to delete', 'error');
        return;
    }
    
    const colToDelete = selectedCells[0].col;
    
    // Shift data left
    for (let row = 0; row < metadata.rows; row++) {
        if (spreadsheetData[row]) {
            for (let col = colToDelete; col < metadata.cols - 1; col++) {
                spreadsheetData[row][col] = spreadsheetData[row][col + 1] || '';
            }
            spreadsheetData[row].pop();
        }
    }
    
    // Update merged cells
    updateMergedCellsForColumnDelete(colToDelete);
    
    metadata.cols--;
    rebuildSpreadsheet();
    showNotification('Column deleted successfully');
}

// Merge cells functionality
function mergeSelectedCells() {
    if (selectedCells.length < 2) {
        showNotification('Please select at least 2 cells to merge', 'error');
        return;
    }
    
    const rows = selectedCells.map(cell => cell.row);
    const cols = selectedCells.map(cell => cell.col);
    
    const startRow = Math.min(...rows);
    const endRow = Math.max(...rows);
    const startCol = Math.min(...cols);
    const endCol = Math.max(...cols);
    
    // Verify rectangular selection
    const selectedAreaSize = (endRow - startRow + 1) * (endCol - startCol + 1);
    if (selectedCells.length !== selectedAreaSize) {
        showNotification('Please select a rectangular area to merge', 'error');
        return;
    }
    
    // Store merge range
    if (!metadata.merged) metadata.merged = [];
    const mergeRange = [startRow, startCol, endRow, endCol];
    metadata.merged.push(mergeRange);
    
    // Get content from first cell
    const mainCell = document.getElementById(`cell_${startRow}_${startCol}`);
    const mainContent = mainCell.innerHTML;
    
    // Apply merge
    for (let row = startRow; row <= endRow; row++) {
        for (let col = startCol; col <= endCol; col++) {
            const cell = document.getElementById(`cell_${row}_${col}`);
            if (cell) {
                if (row === startRow && col === startCol) {
                    // Main merge cell - make it span
                    cell.classList.add('merged', 'main-merge-cell');
                    cell.style.gridColumn = `span ${endCol - startCol + 1}`;
                    cell.style.gridRow = `span ${endRow - startRow + 1}`;
                    cell.contentEditable = 'true';
                    cell.innerHTML = mainContent;
                } else {
                    // Other cells in merge range - hide and make non-editable
                    cell.classList.add('merged');
                    cell.style.display = 'none';
                    cell.contentEditable = 'false';
                    // Clear data from hidden cells
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

function unmergeSelectedCells() {
    if (selectedCells.length === 0) {
        showNotification('Please select a merged cell to unmerge', 'error');
        return;
    }
    
    const cell = selectedCells[0];
    const cellElement = document.getElementById(`cell_${cell.row}_${cell.col}`);
    
    if (!cellElement.classList.contains('main-merge-cell')) {
        showNotification('Please select the main merged cell (top-left cell of the merge)', 'error');
        return;
    }
    
    // Find the merge range
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
        showNotification('Could not find merge data for selected cell', 'error');
        return;
    }
    
    const [startRow, startCol, endRow, endCol] = mergeRange;
    
    // Unmerge all cells in the range
    for (let row = startRow; row <= endRow; row++) {
        for (let col = startCol; col <= endCol; col++) {
            const cell = document.getElementById(`cell_${row}_${col}`);
            if (cell) {
                cell.classList.remove('merged', 'main-merge-cell');
                cell.style.gridColumn = '';
                cell.style.gridRow = '';
                cell.style.display = '';
                cell.contentEditable = 'true';
            }
        }
    }
    
    // Remove from metadata
    metadata.merged.splice(mergeIndex, 1);
    
    clearSelection();
    showNotification('Cells unmerged successfully');
}

function applyExistingMergedCells() {
    if (!metadata.merged || metadata.merged.length === 0) return;
    
    metadata.merged.forEach(mergeRange => {
        const [startRow, startCol, endRow, endCol] = mergeRange;
        const mainCell = document.getElementById(`cell_${startRow}_${startCol}`);
        
        if (mainCell) {
            mainCell.classList.add('merged', 'main-merge-cell');
            mainCell.style.gridColumn = `span ${endCol - startCol + 1}`;
            mainCell.style.gridRow = `span ${endRow - startRow + 1}`;
            mainCell.contentEditable = 'true';
            
            // Hide other cells in merge range
            for (let row = startRow; row <= endRow; row++) {
                for (let col = startCol; col <= endCol; col++) {
                    if (row !== startRow || col !== startCol) {
                        const cell = document.getElementById(`cell_${row}_${col}`);
                        if (cell) {
                            cell.classList.add('merged');
                            cell.style.display = 'none';
                            cell.contentEditable = 'false';
                        }
                    }
                }
            }
        }
    });
}

// Rebuild spreadsheet
function rebuildSpreadsheet() {
    const spreadsheetBody = document.getElementById('spreadsheetBody');
    spreadsheetBody.innerHTML = '';
    
    for (let row = 0; row < metadata.rows; row++) {
        const rowElement = document.createElement('div');
        rowElement.className = 'spreadsheet-row';
        rowElement.dataset.row = row;
        rowElement.innerHTML = `<div class="row-header" data-row="${row}">${row + 1}</div>`;
        
        for (let col = 0; col < metadata.cols; col++) {
            rowElement.innerHTML += createCellHTML(row, col);
        }
        
        spreadsheetBody.appendChild(rowElement);
    }
    
    updateHeader();
    applyExistingMergedCells();
    clearSelection();
}

function createCellHTML(row, col) {
    const cellId = `${row}_${col}`;
    const cellStyle = metadata.styles[cellId] || {};
    const cellValue = spreadsheetData[row] && spreadsheetData[row][col] ? spreadsheetData[row][col] : '';
    const isMerged = isCellMergedInData(row, col);
    const isMainMergeCell = isMainMergeCellInData(row, col);
    
    return `
        <div class="spreadsheet-cell ${isMerged ? 'merged' : ''} ${isMainMergeCell ? 'main-merge-cell' : ''}" 
             contenteditable="true"
             data-row="${row}" 
             data-col="${col}"
             id="cell_${row}_${col}"
             oninput="updateCell(${row}, ${col}, this.innerHTML)"
             onmousedown="startSelection(${row}, ${col})"
             onmouseover="dragSelection(${row}, ${col})"
             onfocus="selectSingleCell(${row}, ${col})"
             style="${getCellStyle(cellStyle)}">${cellValue}</div>
    `;
}

function updateHeader() {
    const header = document.querySelector('.spreadsheet-header');
    header.innerHTML = '<div class="corner-cell" onclick="selectAll()"><i class="fas fa-arrows-alt"></i></div>';
    
    for (let col = 0; col < metadata.cols; col++) {
        header.innerHTML += `<div class="header-cell" data-col="${col}">${getColumnName(col)}</div>`;
    }
}

function updateRowNumbers() {
    document.querySelectorAll('.row-header').forEach((header, index) => {
        header.textContent = index + 1;
        header.dataset.row = index;
    });
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

function updateMergedCellsForRowInsert(insertRow) {
    if (!metadata.merged) return;
    metadata.merged.forEach(mergeRange => {
        if (mergeRange[0] >= insertRow) {
            mergeRange[0]++;
            mergeRange[2]++;
        } else if (mergeRange[2] >= insertRow) {
            mergeRange[2]++;
        }
    });
}

function updateMergedCellsForRowDelete(deleteRow) {
    if (!metadata.merged) return;
    for (let i = metadata.merged.length - 1; i >= 0; i--) {
        const mergeRange = metadata.merged[i];
        if (deleteRow >= mergeRange[0] && deleteRow <= mergeRange[2]) {
            metadata.merged.splice(i, 1);
        } else if (mergeRange[0] > deleteRow) {
            mergeRange[0]--;
            mergeRange[2]--;
        } else if (mergeRange[2] > deleteRow) {
            mergeRange[2]--;
        }
    }
}

function updateMergedCellsForColumnInsert(insertCol) {
    if (!metadata.merged) return;
    metadata.merged.forEach(mergeRange => {
        if (mergeRange[1] >= insertCol) {
            mergeRange[1]++;
            mergeRange[3]++;
        } else if (mergeRange[3] >= insertCol) {
            mergeRange[3]++;
        }
    });
}

function updateMergedCellsForColumnDelete(deleteCol) {
    if (!metadata.merged) return;
    for (let i = metadata.merged.length - 1; i >= 0; i--) {
        const mergeRange = metadata.merged[i];
        if (deleteCol >= mergeRange[1] && deleteCol <= mergeRange[3]) {
            metadata.merged.splice(i, 1);
        } else if (mergeRange[1] > deleteCol) {
            mergeRange[1]--;
            mergeRange[3]--;
        } else if (mergeRange[3] > deleteCol) {
            mergeRange[3]--;
        }
    }
}

// Keyboard shortcuts
function handleKeyDown(event) {
    // Ctrl+B for Bold
    if (event.ctrlKey && event.key === 'b') {
        event.preventDefault();
        toggleFormat('bold');
    }
    // Ctrl+I for Italic
    else if (event.ctrlKey && event.key === 'i') {
        event.preventDefault();
        toggleFormat('italic');
    }
    // Ctrl+U for Underline
    else if (event.ctrlKey && event.key === 'u') {
        event.preventDefault();
        toggleFormat('underline');
    }
    // Ctrl+S for Save
    else if (event.ctrlKey && event.key === 's') {
        event.preventDefault();
        saveSpreadsheet();
    }
}

// Download functionality
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
        const lineBreak = '\n';
        
        for (let row = 0; row < metadata.rows; row++) {
            const rowData = [];
            for (let col = 0; col < metadata.cols; col++) {
                // Skip merged cells that are not main
                if (isCellMergedInData(row, col) && !isMainMergeCellInData(row, col)) {
                    rowData.push('');
                    continue;
                }
                
                let cellValue = spreadsheetData[row] && spreadsheetData[row][col] ? 
                    spreadsheetData[row][col] : '';
                
                // Escape CSV special characters
                if (cellValue.includes(delimiter) || cellValue.includes('"') || cellValue.includes('\n') || cellValue.includes('\r')) {
                    cellValue = '"' + cellValue.replace(/"/g, '""') + '"';
                }
                
                rowData.push(cellValue);
            }
            csvContent += rowData.join(delimiter) + lineBreak;
        }
        
        // Add UTF-8 BOM for Excel compatibility
        const BOM = '\uFEFF';
        const csvWithBOM = BOM + csvContent;
        
        const filename = (document.getElementById('filename').value || 'spreadsheet').replace(/\.[^/.]+$/, "") + '.csv';
        downloadFile(csvWithBOM, filename, 'text/csv;charset=utf-8;');
        showNotification('CSV file downloaded successfully!');
        
    } catch (error) {
        console.error('Error exporting to CSV:', error);
        showNotification('Error exporting to CSV: ' + error.message, 'error');
    }
}

function exportToExcel() {
    try {
        // Simple XML Spreadsheet format
        let xmlContent = '<?xml version="1.0"?>' +
            '<?mso-application progid="Excel.Sheet"?>' +
            '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' +
            ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' +
            '<Worksheet ss:Name="Sheet1">' +
            '<Table>';
        
        for (let row = 0; row < metadata.rows; row++) {
            xmlContent += '<Row>';
            for (let col = 0; col < metadata.cols; col++) {
                // Skip merged cells that are not main
                if (isCellMergedInData(row, col) && !isMainMergeCellInData(row, col)) {
                    xmlContent += '<Cell><Data ss:Type="String"></Data></Cell>';
                    continue;
                }
                
                const cellValue = spreadsheetData[row] && spreadsheetData[row][col] ? 
                    spreadsheetData[row][col] : '';
                
                // Detect data type
                let dataType = 'String';
                let processedValue = escapeXml(cellValue);
                
                // Check if it's a number
                if (!isNaN(cellValue) && cellValue.trim() !== '') {
                    dataType = 'Number';
                    processedValue = cellValue;
                }
                
                xmlContent += '<Cell><Data ss:Type="' + dataType + '">' + processedValue + '</Data></Cell>';
            }
            xmlContent += '</Row>';
        }
        
        xmlContent += '</Table></Worksheet></Workbook>';
        
        const filename = (document.getElementById('filename').value || 'spreadsheet').replace(/\.[^/.]+$/, "") + '.xls';
        downloadFile(xmlContent, filename, 'application/vnd.ms-excel');
        showNotification('Excel file downloaded successfully!');
        
    } catch (error) {
        console.error('Error exporting to Excel:', error);
        showNotification('Error exporting to Excel: ' + error.message, 'error');
    }
}

function exportToJSON() {
    const exportData = {
        data: spreadsheetData,
        metadata: metadata
    };
    const filename = (document.getElementById('filename').value || 'spreadsheet') + '.json';
    downloadFile(JSON.stringify(exportData, null, 2), filename, 'application/json');
    showNotification('JSON file downloaded successfully!');
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

function escapeXml(unsafe) {
    if (unsafe === null || unsafe === undefined) return '';
    return unsafe.toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&apos;');
}

// Save functionality
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

// History functionality
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
        });
}

// Print functionality
function printSpreadsheet() {
    const printContent = document.querySelector('.spreadsheet-container').cloneNode(true);
    const printWindow = window.open('', '_blank');
    
    printWindow.document.write(`
        <html>
            <head>
                <title>Print Spreadsheet</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    .spreadsheet-container { border: 1px solid #ddd; }
                    .spreadsheet-header, .spreadsheet-row { display: flex; }
                    .corner-cell, .header-cell, .row-header { 
                        padding: 8px; border: 1px solid #ddd; 
                        min-width: 50px; text-align: center; font-weight: bold;
                        background: #f8f9fa;
                    }
                    .spreadsheet-cell { 
                        padding: 8px; border: 1px solid #ddd; 
                        min-width: 100px; min-height: 30px;
                    }
                    .spreadsheet-cell.merged { display: none; }
                    .spreadsheet-cell.main-merge-cell { 
                        background-color: #f0f0f0;
                    }
                </style>
            </head>
            <body>
                <h2>${document.getElementById('filename').value}</h2>
                ${printContent.innerHTML}
            </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.print();
}