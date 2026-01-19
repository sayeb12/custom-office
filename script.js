function createNew(type) {
    if (type === 'document') {
        window.location.href = 'document.php';
    } else if (type === 'spreadsheet') {
        window.location.href = 'spreadsheet.php';
    }
}

function loadRecentFiles() {
    // Load documents
    fetch('load.php?type=documents')
        .then(response => response.json())
        .then(files => {
            const container = document.getElementById('document-list');
            container.innerHTML = '';
            
            files.forEach(file => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                fileItem.onclick = () => openFile('document', file.name);
                fileItem.innerHTML = `
                    <h3>${file.name}</h3>
                    <div class="file-meta">Modified: ${file.modified}</div>
                `;
                container.appendChild(fileItem);
            });
        });

    // Load spreadsheets
    fetch('load.php?type=spreadsheets')
        .then(response => response.json())
        .then(files => {
            const container = document.getElementById('spreadsheet-list');
            container.innerHTML = '';
            
            files.forEach(file => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                fileItem.onclick = () => openFile('spreadsheet', file.name);
                fileItem.innerHTML = `
                    <h3>${file.name}</h3>
                    <div class="file-meta">Modified: ${file.modified}</div>
                `;
                container.appendChild(fileItem);
            });
        });
}

function openFile(type, filename) {
    if (type === 'document') {
        window.location.href = `document.php?file=${encodeURIComponent(filename)}`;
    } else if (type === 'spreadsheet') {
        window.location.href = `spreadsheet.php?file=${encodeURIComponent(filename)}`;
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
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        if (window.saveDocument) {
            saveDocument();
        } else if (window.saveSpreadsheet) {
            saveSpreadsheet();
        }
    }
});