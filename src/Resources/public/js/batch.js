document.addEventListener('DOMContentLoaded', () => {
    const $ = id => document.getElementById(id);

    const batchStartButton = $('batch-start');
    const batchStopButton = $('batch-stop');
    const progressContainer = $('batch-progress-container');
    const progressBar = $('batch-progress-bar');
    const progressText = $('batch-progress-text');
    const filesList = $('batch-files-list');
    const creditsCount = $('credits-count');

    if (!batchStartButton || !filesList) return;

    let filesQueue = [];
    let processedCount = 0;
    let totalFiles = 0;
    let isProcessing = false;
    let shouldStop = false;
    let currentCredits = parseInt(creditsCount.textContent, 10) || 0;

    function collectFiles() {
        filesQueue = [...document.querySelectorAll('.file-item')]
            .map(el => ({element: el, path: el.dataset.path, processed: false}))
            .filter(file => !!file.path);

        totalFiles = filesQueue.length;
        return totalFiles > 0;
    }

    function updateProgress() {
        const percent = (processedCount / totalFiles) * 100;
        progressBar.style.width = `${percent}%`;
        progressText.textContent = `${processedCount}/${totalFiles} verarbeitet`;
    }

    function updateCredits(used = 1) {
        currentCredits = Math.max(0, currentCredits - used);
        creditsCount.textContent = currentCredits;

        if (currentCredits <= 0) {
            shouldStop = true;
            showNotification('Keine Credits mehr verfügbar. Verarbeitung wird gestoppt.', 'error');
            finishProcessing();
            return false;
        }
        return true;
    }

    async function processApiRequest(fileItem) {
        const formData = new FormData();
        formData.append('path', fileItem.path);
        formData.append('contextUrl', window.location.hostname);

        const res = await fetch('/contao/bilder-alt/api/v1/generate/path', {
            method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: formData
        });

        return await res.json();
    }

    function setStatus(fileItem, text, cssClass) {
        const cell = fileItem.element.querySelector('.status');
        if (!cell) return;
        cell.textContent = text;
        cell.className = `status ${cssClass}`;
    }

    async function processFilePair(file1, file2 = null) {
        if (shouldStop) return;

        [file1, file2].filter(Boolean).forEach(file => setStatus(file, 'Wird verarbeitet...', 'processing'));

        if (!updateCredits(file2 ? 2 : 1)) return;

        try {
            const result1 = await processApiRequest(file1);
            handleResult(file1, result1);

            if (file2 && !shouldStop) {
                const result2 = await processApiRequest(file2);
                handleResult(file2, result2);
            }
        } catch (err) {
            console.error('Fehler bei der Batch-Verarbeitung:', err);
            [file1, file2].filter(Boolean).forEach(file => {
                setStatus(file, `Fehler: ${err.message || 'Unbekannter Fehler'}`, 'error');
            });
        }

        [file1, file2].filter(Boolean).forEach(file => {
            file.processed = true;
            processedCount++;
        });

        updateProgress();
    }

    function handleResult(file, data) {
        if (data?.success) {
            setStatus(file, 'Erfolgreich', 'success');
        } else {
            const msg = data?.data?.[0]?.message || data?.message || 'Fehler';
            setStatus(file, msg, 'error');
            console.error('Fehler bei der Verarbeitung:', data);
        }
    }

    async function processQueue() {
        if (shouldStop || processedCount >= totalFiles) return finishProcessing();

        const remaining = filesQueue.filter(f => !f.processed);
        if (!remaining.length) {
            return finishProcessing();
        }

        const [file1, file2] = remaining;
        await processFilePair(file1, file2);
        setTimeout(processQueue, 800);
    }

    function startProcessing() {
        if (!collectFiles()) return showNotification('Keine Dateien gefunden', 'error');
        if (currentCredits <= 0) return showNotification('Keine Credits verfügbar', 'error');

        shouldStop = false;
        isProcessing = true;
        processedCount = 0;

        batchStartButton.style.display = 'none';
        batchStopButton.style.display = 'inline-block';
        progressContainer.style.display = 'block';

        updateProgress();
        processQueue();
    }

    function finishProcessing() {
        isProcessing = false;
        batchStartButton.style.display = 'inline-block';
        batchStopButton.style.display = 'none';

        const msg = shouldStop ? 'Verarbeitung wurde abgebrochen' : 'Alle Dateien wurden verarbeitet';

        showNotification(msg, shouldStop ? 'info' : 'success');
    }

    batchStartButton.addEventListener('click', startProcessing);
    batchStopButton?.addEventListener('click', () => {
        shouldStop = true;
        showNotification('Verarbeitung wird angehalten...', 'info');
    });

    // Notification-System
    function showNotification(message, type = 'info') {
        let container = $('bilder-alt-notifications');

        if (!container) {
            container = document.createElement('div');
            container.id = 'bilder-alt-notifications';
            Object.assign(container.style, {
                position: 'fixed', zIndex: '9999', top: '10px', right: '10px', width: '300px'
            });
            document.body.appendChild(container);
        }

        const div = document.createElement('div');
        div.className = `tl_${type === 'error' ? 'error' : (type === 'success' ? 'confirm' : 'info')}`;
        Object.assign(div.style, {
            padding: '10px 10px 10px 50px',
            borderRadius: '3px',
            boxShadow: '0 2px 5px rgba(0,0,0,0.2)',
            marginBottom: '10px'
        });
        div.innerHTML = message;
        container.appendChild(div);

        setTimeout(() => {
            div.style.transition = 'opacity 0.5s';
            div.style.opacity = '0';
            setTimeout(() => {
                div.remove();
                if (!container.children.length) container.remove();
            }, 500);
        }, 5000);
    }
});
