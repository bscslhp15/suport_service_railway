<?php
// Standalone test version - NO AUTHENTICATION REQUIRED
// This test page has the EXACT same HTML structure and JavaScript as library_catalog.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Catalog - Test</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background: #f5f7fa; }
        
        .inventory-panel {
            display: none;
            padding: 20px;
            background: white;
            border-radius: 8px;
            margin-top: 20px;
        }
        .inventory-panel.active {
            display: block;
        }
        
        .inventory-tab-bar {
            display: flex;
            gap: 10px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .library-tab-button {
            padding: 10px 20px;
            border: none;
            background: #f0f0f0;
            cursor: pointer;
            border-radius: 4px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .library-tab-button.active {
            background: #2563eb;
            color: white;
        }
        
        .library-tab-button:hover {
            background: #e0e0e0;
        }
        
        .library-tab-button.active:hover {
            background: #1d4ed8;
        }

        .eresources-tab-button {
            padding: 10px 15px;
            border: none;
            background: transparent;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            color: #64748b;
            font-weight: 600;
        }

        .eresources-tab-button.active {
            border-bottom-color: #2563eb;
            color: #2563eb;
        }

        .eresources-tab-content {
            display: none;
            padding: 15px;
            background: white;
            border-radius: 8px;
        }

        .eresources-tab-content.active {
            display: block;
        }

        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .status-box { background: #fffbeb; border: 1px solid #fbbf24; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .error { color: #dc2626; }
        .success { color: #059669; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Library Catalog - Test Version</h1>
        <p>This is a standalone test WITHOUT authentication. If tabs work here, the JavaScript is correct.</p>
        
        <div class="status-box">
            <p><strong>Status:</strong> <span id="status">Loading...</span></p>
            <p><strong>Main Tabs Found:</strong> <span id="mainTabCount">0</span></p>
            <p><strong>E-Resources Tabs Found:</strong> <span id="eresTabCount">0</span></p>
            <p><strong>Panels Found:</strong> <span id="panelCount">0</span></p>
        </div>

        <!-- MAIN TABS -->
        <div class="inventory-tab-bar library-tab-bar" role="tablist">
            <button type="button" class="library-tab-button active" data-tab="management">Book Management</button>
            <button type="button" class="library-tab-button" data-tab="summary">Inventory Summary</button>
            <button type="button" class="library-tab-button" data-tab="categories">Categories</button>
            <button type="button" class="library-tab-button" data-tab="eresources">E-Resources</button>
            <button type="button" class="library-tab-button" data-tab="open_access">Open Access Library</button>
        </div>

        <!-- PANELS -->
        <div class="inventory-panel active" id="management">
            <h2>Book Management</h2>
            <p>Add, edit, and remove books from the library collection.</p>
        </div>

        <div class="inventory-panel" id="summary">
            <h2>Inventory Summary</h2>
            <p>View overall statistics and search all books in the system.</p>
        </div>

        <div class="inventory-panel" id="categories">
            <h2>Categories</h2>
            <p>Organize and manage book categories for better organization.</p>
        </div>

        <div class="inventory-panel" id="eresources">
            <h2>E-Resources</h2>
            
            <div style="border-bottom: 2px solid #e2e8f0; margin-bottom: 20px;">
                <div style="display: flex; gap: 12px;">
                    <button type="button" class="eresources-tab-button active" data-tab="pending">Pending Approvals</button>
                    <button type="button" class="eresources-tab-button" data-tab="add">Add Resource</button>
                    <button type="button" class="eresources-tab-button" data-tab="published">Published Resources</button>
                </div>
            </div>

            <div class="eresources-tab-content active" data-tab="pending">
                <h3>Pending Approvals</h3>
                <p>Resources waiting for approval...</p>
            </div>

            <div class="eresources-tab-content" data-tab="add">
                <h3>Add Resource</h3>
                <p>Add a new e-resource to the library...</p>
            </div>

            <div class="eresources-tab-content" data-tab="published">
                <h3>Published Resources</h3>
                <p>View all published e-resources...</p>
            </div>
        </div>

        <div class="inventory-panel" id="open_access">
            <h2>Open Access Library</h2>
            <p>Manage open access resources available to all users.</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            console.log('=== TAB SWITCHING TEST STARTED ===');
            
            // ============ TAB SWITCHING ============
            const mainTabButtons = document.querySelectorAll('.inventory-tab-bar .library-tab-button');
            const mainPanels = document.querySelectorAll('.inventory-panel');

            console.log('Main tab buttons found:', mainTabButtons.length);
            console.log('Panels found:', mainPanels.length);
            
            document.getElementById('mainTabCount').textContent = mainTabButtons.length;
            document.getElementById('panelCount').textContent = mainPanels.length;

            mainTabButtons.forEach((button, index) => {
                console.log(`Tab ${index}: `, button.getAttribute('data-tab'));
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tabName = this.getAttribute('data-tab');
                    console.log('CLICKED TAB:', tabName);
                    
                    mainTabButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    
                    mainPanels.forEach(panel => {
                        panel.style.display = 'none';
                        panel.classList.remove('active');
                    });
                    
                    const selectedPanel = document.getElementById(tabName);
                    if (selectedPanel) {
                        console.log('Showing panel:', tabName);
                        selectedPanel.style.display = 'block';
                        selectedPanel.classList.add('active');
                        document.getElementById('status').innerHTML = '<span class="success">✓ Switched to: ' + tabName + '</span>';
                    } else {
                        console.log('ERROR: Panel not found:', tabName);
                        document.getElementById('status').innerHTML = '<span class="error">✗ Panel not found: ' + tabName + '</span>';
                    }
                });
            });

            // E-Resources sub-tabs
            const eresourcesTabButtons = document.querySelectorAll('.eresources-tab-button');
            const eresourcesContents = document.querySelectorAll('.eresources-tab-content');

            console.log('E-Resources buttons found:', eresourcesTabButtons.length);
            console.log('E-Resources contents found:', eresourcesContents.length);
            
            document.getElementById('eresTabCount').textContent = eresourcesTabButtons.length;

            eresourcesTabButtons.forEach((button, index) => {
                console.log(`E-Resources tab ${index}: `, button.getAttribute('data-tab'));
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tabName = this.getAttribute('data-tab');
                    console.log('CLICKED E-RESOURCES TAB:', tabName);
                    
                    eresourcesTabButtons.forEach(btn => {
                        btn.classList.remove('active');
                        btn.style.borderBottomColor = 'transparent';
                        btn.style.color = '#64748b';
                    });
                    
                    this.classList.add('active');
                    this.style.borderBottomColor = '#2563eb';
                    this.style.color = '#2563eb';
                    
                    eresourcesContents.forEach(content => {
                        content.style.display = 'none';
                        content.classList.remove('active');
                    });
                    
                    const selectedContent = document.querySelector('.eresources-tab-content[data-tab="' + tabName + '"]');
                    if (selectedContent) {
                        console.log('Showing e-resource:', tabName);
                        selectedContent.style.display = 'block';
                        selectedContent.classList.add('active');
                    }
                });
            });

            document.getElementById('status').innerHTML = '<span class="success">✓ All event listeners registered</span>';
            console.log('=== ALL LISTENERS REGISTERED ===');
        });
    </script>
</body>
</html>
