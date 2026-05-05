document.addEventListener('DOMContentLoaded', function () {
    var batchStartButton = document.getElementById('batch-start');
    var batchStartWithNoneButton = document.getElementById('batch-start-with-none');
    var batchStopButton = document.getElementById('batch-stop');
    var progressContainer = document.getElementById('batch-progress-container');
    var progressBar = document.getElementById('batch-progress-bar');
    var progressText = document.getElementById('batch-progress-text');
    var filesList = document.getElementById('batch-files-list');
    var creditsCount = document.getElementById('credits-count');
    var onlyNone = false;

    if (!batchStartButton || !batchStartWithNoneButton || !filesList) {
        return;
    }

    var filesQueue = [];
    var processedCount = 0;
    var totalFiles = 0;
    var totalCreditCost = 0;
    var isProcessing = false;
    var shouldStop = false;
    var currentCredits = parseInt(creditsCount.textContent, 10) || 0;

    function getCreditCost(el) {
        if (onlyNone) {
            var missing = el.dataset.missingLangs || '';
            return missing ? missing.split(',').filter(Boolean).length : 0;
        }
        return el.querySelectorAll('.lang-row').length || 1;
    }

    function collectFiles() {
        filesQueue = Array.prototype.slice.call(document.querySelectorAll('.file-item'))
            .map(function (el) {
                return { element: el, path: el.dataset.path, processed: false, creditCost: getCreditCost(el) };
            })
            .filter(function (file) { return !!file.path; })
            .filter(function (file) {
                if (!onlyNone) return true;
                return file.creditCost > 0;
            });

        totalFiles = filesQueue.length;
        totalCreditCost = filesQueue.reduce(function (sum, f) { return sum + f.creditCost; }, 0);
        return totalFiles > 0;
    }

    function updateProgress() {
        var percent = (processedCount / totalFiles) * 100;
        progressBar.style.width = percent + '%';
        progressText.textContent = processedCount + '/' + totalFiles + ' verarbeitet';
    }

    function updateCredits(used) {
        used = used || 1;
        currentCredits = Math.max(0, currentCredits - used);
        creditsCount.textContent = currentCredits;

        if (currentCredits <= 0) {
            shouldStop = true;
            showNotification('[Bilder Alt] Keine Credits mehr verfügbar. Verarbeitung wird gestoppt.', 'error');
            finishProcessing();
            return false;
        }
        return true;
    }

    function processApiRequest(fileItem) {
        var formData = new FormData();
        formData.append('path', fileItem.path);
        formData.append('contextUrl', window.location.hostname);

        if (onlyNone && fileItem.element.dataset.missingLangs) {
            formData.append('languages', fileItem.element.dataset.missingLangs);
        }

        return fetch('/contao/bilder-alt/api/v1/generate/path', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        }).then(function (res) { return res.json(); });
    }

    function updateStatusCell(fileItem, text, cssClass) {
        var cell = fileItem.element.querySelector('.status');
        if (!cell) return;
        cell.textContent = text;
        cell.className = 'status ' + cssClass;
    }

    function updateAltTextCell(fileItem, altData) {
        if (!Array.isArray(altData) || !altData.length) return;

        var cell = fileItem.element.querySelector('.alt-text');
        if (!cell) return;

        altData.forEach(function (entry) {
            if (!entry.isoCode || !entry.altTag) return;
            var row = cell.querySelector('.lang-row[data-lang="' + entry.isoCode + '"]');
            if (!row) return;
            var span = row.querySelector('.lang-alt');
            if (span) {
                span.textContent = entry.altTag;
                span.classList.remove('lang-alt--missing');
            }
        });

        fileItem.element.dataset.missingLangs = '';
        fileItem.element.dataset.has = '1';
    }

    function processFilePair(file1, file2) {
        if (shouldStop) return Promise.resolve();

        var files = [file1];
        if (file2) files.push(file2);

        files.forEach(function (f) { updateStatusCell(f, 'Wird verarbeitet...', 'processing'); });

        if (!updateCredits(file1.creditCost)) return Promise.resolve();

        return processApiRequest(file1).then(function (result1) {
            handleResult(file1, result1);
            processedCount++;
            updateProgress();

            if (!file2 || shouldStop) return;

            if (!updateCredits(file2.creditCost)) return;

            return processApiRequest(file2).then(function (result2) {
                handleResult(file2, result2);
                processedCount++;
                updateProgress();
            });
        }).catch(function (err) {
            console.error('[Bilder Alt] Fehler bei der Batch-Verarbeitung:', err);
            files.forEach(function (f) {
                updateStatusCell(f, 'Fehler: ' + (err.message || 'Unbekannter Fehler'), 'error');
            });
        }).then(function () {
            files.forEach(function (f) { f.processed = true; });
        });
    }

    function handleResult(file, data) {
        if (data && data.success && data.data && data.data[0] && data.data[0].altTag) {
            showNotification('[Bilder Alt] Alt-Text erfolgreich generiert "' + data.data[0].altTag + '"', 'success');
        }

        if (data && data.success) {
            updateStatusCell(file, 'Erfolgreich', 'success');
            updateAltTextCell(file, data.data);
        } else {
            var msg = (data && data.data && data.data[0] && data.data[0].message)
                || (data && data.message)
                || 'Fehler';
            updateStatusCell(file, msg, 'error');
        }
    }

    function processQueue() {
        if (shouldStop || processedCount >= totalFiles) {
            return finishProcessing();
        }

        var remaining = filesQueue.filter(function (f) { return !f.processed; });

        if (!remaining.length) {
            return finishProcessing();
        }

        var file1 = remaining[0];
        var file2 = remaining[1] || null;

        processFilePair(file1, file2).then(function () {
            setTimeout(processQueue, 600);
        });
    }

    function startProcessing() {
        if (!collectFiles()) {
            return showNotification('[Bilder Alt] Keine Dateien gefunden', 'error');
        }

        if (currentCredits < totalCreditCost) {
            return showNotification('[Bilder Alt] Nicht genug Credits verfügbar (' + totalCreditCost + ' benötigt, ' + currentCredits + ' vorhanden)', 'error');
        }

        shouldStop = false;
        isProcessing = true;

        batchStartButton.style.display = 'none';
        batchStartWithNoneButton.style.display = 'none';
        batchStopButton.style.display = 'inline-block';
        progressContainer.style.opacity = '1';

        processedCount = 0;
        updateProgress();
        processQueue();
    }

    function finishProcessing() {
        isProcessing = false;
        batchStartButton.style.display = 'inline-block';
        batchStartWithNoneButton.style.display = 'inline-block';
        batchStopButton.style.display = 'none';
        progressContainer.style.opacity = '0';
        var msg = shouldStop
            ? '[Bilder Alt] Verarbeitung wurde abgebrochen'
            : '[Bilder Alt] Alle Dateien wurden verarbeitet';
        showNotification(msg, shouldStop ? 'info' : 'success');
    }

    function showNotification(message, type) {
        type = type || 'info';
        var container = document.getElementById('bilder-alt-notifications');

        if (!container) {
            container = document.createElement('div');
            container.id = 'bilder-alt-notifications';
            Object.assign(container.style, {
                position: 'fixed', zIndex: '9999', top: '10px', right: '10px',
                width: '300px', background: 'var(--body-bg, #fff)',
                display: 'flex', flexDirection: 'column', gap: '10px'
            });
            document.body.appendChild(container);
        }

        var div = document.createElement('div');
        div.className = 'tl_' + (type === 'error' ? 'error' : (type === 'success' ? 'confirm' : 'info'));
        Object.assign(div.style, {
            padding: '10px 10px 10px 50px',
            borderRadius: '3px',
            boxShadow: '0 2px 5px rgba(0,0,0,0.2)'
        });
        div.innerHTML = message;
        container.appendChild(div);

        setTimeout(function () {
            div.style.transition = 'opacity 0.5s';
            div.style.opacity = '0';
            setTimeout(function () {
                div.remove();
                if (!container.children.length) container.remove();
            }, 500);
        }, 5000);
    }

    batchStartButton.addEventListener('click', function () {
        onlyNone = false;
        startProcessing();
    });

    batchStartWithNoneButton.addEventListener('click', function () {
        onlyNone = true;
        startProcessing();
    });

    if (batchStopButton) {
        batchStopButton.addEventListener('click', function () {
            shouldStop = true;
            showNotification('[Bilder Alt] Verarbeitung wird angehalten...', 'info');
        });
    }
});
