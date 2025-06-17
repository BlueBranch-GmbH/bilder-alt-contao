document.addEventListener('DOMContentLoaded', function() {
    const batchStartButton = document.getElementById('batch-start');
    const batchStopButton = document.getElementById('batch-stop');
    const progressContainer = document.getElementById('batch-progress-container');
    const progressBar = document.getElementById('batch-progress-bar');
    const progressText = document.getElementById('batch-progress-text');
    const filesList = document.getElementById('batch-files-list');

    if (!batchStartButton || !filesList) return;

    let filesQueue = [];
    let processedCount = 0;
    let totalFiles = 0;
    let isProcessing = false;
    let shouldStop = false;

    // Alle Dateien sammeln
    function collectFiles() {
        filesQueue = [];
        const fileItems = document.querySelectorAll('.file-item');

        fileItems.forEach(item => {
            const filePath = item.getAttribute('data-path');
            if (filePath) {
                filesQueue.push({
                    element: item,
                    path: filePath,
                    processed: false
                });
            }
        });

        totalFiles = filesQueue.length;
        return totalFiles > 0;
    }

    // Fortschritt aktualisieren
    function updateProgress() {
        const percentage = (processedCount / totalFiles) * 100;
        progressBar.style.width = percentage + '%';
        progressText.textContent = processedCount + '/' + totalFiles + ' verarbeitet';
    }

    // Datei verarbeiten
    async function processFile(fileItem) {
        if (shouldStop) return;

        const statusCell = fileItem.element.querySelector('.status');
        statusCell.textContent = 'Wird verarbeitet...';
        statusCell.className = 'status processing';

        const formData = new FormData();
        formData.append('path', fileItem.path);
        formData.append('contextUrl', window.location.hostname);

        try {
            const response = await fetch('/contao/bilder-alt/api/v1/generate/path', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                statusCell.textContent = 'Erfolgreich';
                statusCell.className = 'status success';
            } else {
                let errorMessage = 'Fehler';
                if (data?.data?.length) {
                    errorMessage = data.data[0].message || 'Fehler';
                } else if (data.message) {
                    errorMessage = data.message;
                }

                statusCell.textContent = errorMessage;
                statusCell.className = 'status error';
                console.error('Fehler bei der Verarbeitung:', data);
            }
        } catch (error) {
            statusCell.textContent = 'Fehler: ' + error.message;
            statusCell.className = 'status error';
            console.error('Fehler beim API-Aufruf:', error);
        }

        fileItem.processed = true;
        processedCount++;
        updateProgress();
    }

    // Dateien sequentiell verarbeiten
    async function processQueue() {
        if (shouldStop || processedCount >= totalFiles) {
            finishProcessing();
            return;
        }

        const nextFile = filesQueue.find(file => !file.processed);

        if (nextFile) {
            await processFile(nextFile);
            setTimeout(processQueue, 500); // Kurze Pause zwischen den Anfragen
        } else {
            finishProcessing();
        }
    }

    // Verarbeitung starten
    function startProcessing() {
        if (!collectFiles()) {
            showNotification('Keine Dateien zum Verarbeiten gefunden', 'error');
            return;
        }

        shouldStop = false;
        isProcessing = true;
        processedCount = 0;

        batchStartButton.style.display = 'none';
        batchStopButton.style.display = 'inline-block';
        progressContainer.style.display = 'block';

        updateProgress();
        processQueue();
    }

    // Verarbeitung beenden
    function finishProcessing() {
        isProcessing = false;
        batchStartButton.style.display = 'inline-block';
        batchStopButton.style.display = 'none';

        if (shouldStop) {
            showNotification('Verarbeitung wurde abgebrochen', 'info');
        } else if (processedCount === totalFiles) {
            showNotification('Alle Dateien wurden verarbeitet', 'success');
        }
    }

    // Event-Listener für Buttons
    batchStartButton.addEventListener('click', startProcessing);

    if (batchStopButton) {
        batchStopButton.addEventListener('click', function() {
            shouldStop = true;
            showNotification('Verarbeitung wird angehalten...', 'info');
        });
    }

    // Benachrichtigung anzeigen
    function showNotification(message, type = 'info') {
        let container = document.getElementById('bilder-alt-notifications');
        if (!container) {
            container = document.createElement('div');
            container.id = 'bilder-alt-notifications';
            container.style.position = 'fixed';
            container.style.zIndex = '9999';
            container.style.top = '10px';
            container.style.right = '10px';
            container.style.width = '300px';
            document.body.appendChild(container);
        }

        const notification = document.createElement('div');
        notification.className = `tl_${type === 'error' ? 'error' : (type === 'success' ? 'confirm' : 'info')}`;
        notification.style.padding = '10px 10px 10px 50px';
        notification.style.borderRadius = '3px';
        notification.style.boxShadow = '0 2px 5px rgba(0,0,0,0.2)';
        notification.style.marginBottom = '10px';
        notification.innerHTML = message;

        container.appendChild(notification);

        setTimeout(() => {
            notification.style.transition = 'opacity 0.5s';
            notification.style.opacity = '0';

            setTimeout(() => {
                container.removeChild(notification);

                if (container.children.length === 0) {
                    document.body.removeChild(container);
                }
            }, 500);
        }, 5000);
    }
});
