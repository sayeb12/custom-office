<?php
session_start();
require_once 'config.php';

// Load recent files from database
$db = (new Database())->getConnection();

// Load recent documents
$query = "SELECT filename, created_at, updated_at FROM documents ORDER BY updated_at DESC LIMIT 8";
$stmt = $db->prepare($query);
$stmt->execute();
$recentDocuments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Load recent spreadsheets
$query = "SELECT filename, created_at, updated_at FROM spreadsheets ORDER BY updated_at DESC LIMIT 8";
$stmt = $db->prepare($query);
$stmt->execute();
$recentSpreadsheets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custom Office Suite - Google Docs & Sheets Alternative</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .file-item {
            position: relative;
        }
        
        .delete-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(220, 53, 69, 0.9);
            color: white;
            border: none;
            border-radius: 4px;
            width: 30px;
            height: 30px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: all 0.3s ease;
            font-size: 12px;
        }
        
        .file-item:hover .delete-btn {
            opacity: 1;
        }
        
        .delete-btn:hover {
            background: #dc3545;
            transform: scale(1.1);
        }
        
        .confirm-delete {
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
        
        .confirm-delete-modal {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        
        .confirm-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <!-- Delete Confirmation Modal -->
    <div class="confirm-delete" id="confirmDelete">
        <div class="confirm-delete-modal">
            <h3><i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i> Delete File</h3>
            <p id="deleteMessage">Are you sure you want to delete this file? This action cannot be undone.</p>
            <div class="confirm-buttons">
                <button onclick="cancelDelete()" class="btn btn-secondary">Cancel</button>
                <button onclick="confirmDelete()" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>

    <div class="container">
        <header>
            <h1><i class="fas fa-office-building"></i> Custom Office Suite</h1>
            <p style="text-align: center; margin-top: 10px; opacity: 0.9;">A complete document and spreadsheet editor</p>
        </header>
        
        <div class="dashboard">
            <div class="toolbar">
                <button onclick="createNew('document')" class="btn btn-primary">
                    <i class="fas fa-file-alt"></i> New Document
                </button>
                <button onclick="createNew('spreadsheet')" class="btn btn-success">
                    <i class="fas fa-table"></i> New Spreadsheet
                </button>
            </div>

            <div class="file-list">
                <h2><i class="fas fa-file-alt"></i> Recent Documents</h2>
                <div id="document-list" class="file-grid">
                    <?php foreach ($recentDocuments as $doc): ?>
                    <div class="file-item" data-type="document" data-filename="<?php echo $doc['filename']; ?>">
                        <button class="delete-btn" onclick="showDeleteConfirm('document', '<?php echo $doc['filename']; ?>')" title="Delete file">
                            <i class="fas fa-trash"></i>
                        </button>
                        <div onclick="openFile('document', '<?php echo $doc['filename']; ?>')">
                            <h3><i class="fas fa-file-word"></i> <?php echo htmlspecialchars($doc['filename']); ?></h3>
                            <div class="file-meta">
                                Created: <?php echo date('M j, Y g:i A', strtotime($doc['created_at'])); ?><br>
                                Modified: <?php echo date('M j, Y g:i A', strtotime($doc['updated_at'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($recentDocuments)): ?>
                    <div class="no-files">
                        <i class="fas fa-file-alt" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>No documents yet. Create your first document!</p>
                    </div>
                    <?php endif; ?>
                </div>

                <h2><i class="fas fa-table"></i> Recent Spreadsheets</h2>
                <div id="spreadsheet-list" class="file-grid">
                    <?php foreach ($recentSpreadsheets as $sheet): ?>
                    <div class="file-item" data-type="spreadsheet" data-filename="<?php echo $sheet['filename']; ?>">
                        <button class="delete-btn" onclick="showDeleteConfirm('spreadsheet', '<?php echo $sheet['filename']; ?>')" title="Delete file">
                            <i class="fas fa-trash"></i>
                        </button>
                        <div onclick="openFile('spreadsheet', '<?php echo $sheet['filename']; ?>')">
                            <h3><i class="fas fa-file-excel"></i> <?php echo htmlspecialchars($sheet['filename']); ?></h3>
                            <div class="file-meta">
                                Created: <?php echo date('M j, Y g:i A', strtotime($sheet['created_at'])); ?><br>
                                Modified: <?php echo date('M j, Y g:i A', strtotime($sheet['updated_at'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($recentSpreadsheets)): ?>
                    <div class="no-files">
                        <i class="fas fa-table" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>No spreadsheets yet. Create your first spreadsheet!</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        let deleteType = null;
        let deleteFilename = null;

        function createNew(type) {
            if (type === 'document') {
                window.location.href = 'document.php';
            } else if (type === 'spreadsheet') {
                window.location.href = 'spreadsheet.php';
            }
        }

        function openFile(type, filename) {
            if (type === 'document') {
                window.location.href = `document.php?file=${encodeURIComponent(filename)}`;
            } else if (type === 'spreadsheet') {
                window.location.href = `spreadsheet.php?file=${encodeURIComponent(filename)}`;
            }
        }

        function showDeleteConfirm(type, filename) {
            event.stopPropagation(); // Prevent opening the file
            deleteType = type;
            deleteFilename = filename;
            
            const message = `Are you sure you want to delete "${filename}"? This action cannot be undone.`;
            document.getElementById('deleteMessage').textContent = message;
            document.getElementById('confirmDelete').style.display = 'flex';
        }

        function cancelDelete() {
            document.getElementById('confirmDelete').style.display = 'none';
            deleteType = null;
            deleteFilename = null;
        }

        function confirmDelete() {
            if (!deleteType || !deleteFilename) {
                return;
            }

            const formData = new FormData();
            formData.append('type', deleteType);
            formData.append('filename', deleteFilename);

            fetch('delete.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the file item from DOM
                    const fileItem = document.querySelector(`.file-item[data-type="${deleteType}"][data-filename="${deleteFilename}"]`);
                    if (fileItem) {
                        fileItem.remove();
                    }
                    
                    // Show notification
                    showNotification('File deleted successfully');
                    
                    // Check if no files left and show empty state
                    checkEmptyState();
                } else {
                    showNotification('Error deleting file: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error deleting file', 'error');
            })
            .finally(() => {
                cancelDelete();
            });
        }

        function checkEmptyState() {
            // Check documents
            const documentList = document.getElementById('document-list');
            const documentItems = documentList.querySelectorAll('.file-item');
            if (documentItems.length === 0) {
                documentList.innerHTML = `
                    <div class="no-files">
                        <i class="fas fa-file-alt" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>No documents yet. Create your first document!</p>
                    </div>
                `;
            }

            // Check spreadsheets
            const spreadsheetList = document.getElementById('spreadsheet-list');
            const spreadsheetItems = spreadsheetList.querySelectorAll('.file-item');
            if (spreadsheetItems.length === 0) {
                spreadsheetList.innerHTML = `
                    <div class="no-files">
                        <i class="fas fa-table" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>No spreadsheets yet. Create your first spreadsheet!</p>
                    </div>
                `;
            }
        }

        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey)) {
                switch(e.key) {
                    case 'n':
                        e.preventDefault();
                        createNew('document');
                        break;
                    case 't':
                        e.preventDefault();
                        createNew('spreadsheet');
                        break;
                }
            }
            
            // Escape key to close delete confirmation
            if (e.key === 'Escape') {
                cancelDelete();
            }
        });

        // Close modal when clicking outside
        document.getElementById('confirmDelete').addEventListener('click', function(e) {
            if (e.target === this) {
                cancelDelete();
            }
        });
    </script>
</body>
</html>