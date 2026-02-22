document.addEventListener('DOMContentLoaded', function () {
    const btnTitle = document.getElementById('batch-generate-title');
    const btnDescription = document.getElementById('batch-generate-description');
    const btnBoth = document.getElementById('batch-generate-both');
    const btnTitleEmpty = document.getElementById('batch-generate-title-empty');
    const btnDescriptionEmpty = document.getElementById('batch-generate-description-empty');
    const btnBothEmpty = document.getElementById('batch-generate-both-empty');
    const btnStop = document.getElementById('batch-stop');
    const progressContainer = document.getElementById('batch-progress-container');
    const progressBar = document.getElementById('batch-progress-bar');
    const progressText = document.getElementById('batch-progress-text');
    const creditsCount = document.getElementById('credits-count');
    const selectAll = document.getElementById('batch-select-all');

    if (!btnTitle || !btnDescription) return;

    const allBtns = [btnTitle, btnDescription, btnBoth, btnTitleEmpty, btnDescriptionEmpty, btnBothEmpty];

    let pagesQueue = [];
    let processedCount = 0;
    let totalPages = 0;
    let shouldStop = false;
    let currentCredits = parseInt(creditsCount ? creditsCount.textContent : '0', 10) || 0;
    let mode = 'title';

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.page-checkbox').forEach(function (cb) {
                cb.checked = selectAll.checked;
            });
        });
    }

    function collectPages(filterMode) {
        pagesQueue = [];
        document.querySelectorAll('.page-item').forEach(function (el) {
            const cb = el.querySelector('.page-checkbox');
            if (cb && !cb.checked) return;
            const id = el.dataset.id;
            if (!id) return;

            const hasTitle = el.dataset.hasTitle === '1';
            const hasDescription = el.dataset.hasDescription === '1';

            let needsTitle = false;
            let needsDescription = false;

            if (filterMode === 'title') {
                needsTitle = true;
            } else if (filterMode === 'description') {
                needsDescription = true;
            } else if (filterMode === 'both') {
                needsTitle = true;
                needsDescription = true;
            } else if (filterMode === 'title-empty') {
                if (hasTitle) return;
                needsTitle = true;
            } else if (filterMode === 'description-empty') {
                if (hasDescription) return;
                needsDescription = true;
            } else if (filterMode === 'both-empty') {
                needsTitle = !hasTitle;
                needsDescription = !hasDescription;
                if (!needsTitle && !needsDescription) return;
            }

            pagesQueue.push({element: el, id: id, processed: false, needsTitle: needsTitle, needsDescription: needsDescription});
        });
        totalPages = pagesQueue.length;
        return totalPages > 0;
    }

    function updateProgress() {
        var percent = totalPages > 0 ? (processedCount / totalPages) * 100 : 0;
        progressBar.style.width = percent + '%';
        progressText.textContent = processedCount + '/' + totalPages + ' verarbeitet';
    }

    function updateCredits(used) {
        currentCredits = Math.max(0, currentCredits - used);
        if (creditsCount) creditsCount.textContent = currentCredits;
        if (currentCredits <= 0) {
            shouldStop = true;
            showNotification('[KI Seiten] Keine Credits mehr verfügbar. Verarbeitung wird gestoppt.', 'error');
            finishProcessing();
            return false;
        }
        return true;
    }

    function updateStatus(pageItem, text, cssClass) {
        var cell = pageItem.element.querySelector('.status');
        if (cell) {
            cell.textContent = text;
            cell.className = 'status ' + cssClass;
        }
    }

    function updateCell(pageItem, field, value) {
        var cell = pageItem.element.querySelector('[data-field="' + field + '"]');
        if (cell) cell.textContent = value;
    }

    async function processPage(pageItem) {
        if (shouldStop) return;
        updateStatus(pageItem, 'Wird verarbeitet...', 'processing');

        try {
            if (pageItem.needsTitle) {
                if (!updateCredits(1)) return;
                var fd1 = new FormData();
                fd1.append('pageId', pageItem.id);
                fd1.append('save', '1');
                var res1 = await fetch('/contao/bilder-alt/api/v1/page/generate-title', {
                    method: 'POST',
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    body: fd1,
                });
                var data1 = await res1.json();
                if (data1.success) {
                    updateCell(pageItem, 'pageTitle', data1.title);
                } else {
                    updateStatus(pageItem, data1.message || 'Fehler', 'error');
                    processedCount++;
                    updateProgress();
                    return;
                }
            }

            if (pageItem.needsDescription) {
                if (!updateCredits(1)) return;
                var fd2 = new FormData();
                fd2.append('pageId', pageItem.id);
                fd2.append('save', '1');
                var res2 = await fetch('/contao/bilder-alt/api/v1/page/generate-description', {
                    method: 'POST',
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    body: fd2,
                });
                var data2 = await res2.json();
                if (data2.success) {
                    updateCell(pageItem, 'description', data2.description);
                } else {
                    updateStatus(pageItem, data2.message || 'Fehler', 'error');
                    processedCount++;
                    updateProgress();
                    return;
                }
            }

            updateStatus(pageItem, 'Erfolgreich', 'success');
        } catch (err) {
            updateStatus(pageItem, 'Fehler: ' + (err.message || 'Unbekannter Fehler'), 'error');
        }

        processedCount++;
        updateProgress();
        pageItem.processed = true;
    }

    async function processQueue() {
        if (shouldStop || processedCount >= totalPages) {
            return finishProcessing();
        }
        var remaining = pagesQueue.filter(function (p) { return !p.processed; });
        if (!remaining.length) return finishProcessing();

        await processPage(remaining[0]);
        setTimeout(processQueue, 600);
    }

    function startProcessing(selectedMode) {
        mode = selectedMode;
        if (!collectPages(mode)) {
            return showNotification('[KI Seiten] Keine Seiten ausgewählt oder alle bereits befüllt.', 'error');
        }

        var creditsNeeded = pagesQueue.reduce(function (sum, p) {
            return sum + (p.needsTitle ? 2 : 0) + (p.needsDescription ? 2 : 0);
        }, 0);

        if (currentCredits < creditsNeeded) {
            return showNotification(
                '[KI Seiten] Nicht genug Credits. Benötigt: ' + creditsNeeded + ', Verfügbar: ' + currentCredits,
                'error'
            );
        }

        shouldStop = false;
        processedCount = 0;
        allBtns.forEach(function (b) { if (b) b.style.display = 'none'; });
        btnStop.style.display = 'inline-block';
        progressContainer.style.opacity = '1';

        updateProgress();
        processQueue();
    }

    function finishProcessing() {
        allBtns.forEach(function (b) { if (b) b.style.display = 'inline-block'; });
        btnStop.style.display = 'none';
        progressContainer.style.opacity = '0';
        var msg = shouldStop
            ? '[KI Seiten] Verarbeitung wurde abgebrochen.'
            : '[KI Seiten] Alle Seiten wurden verarbeitet.';
        showNotification(msg, shouldStop ? 'info' : 'success');
    }

    function showNotification(message, type) {
        var container = document.getElementById('bilder-alt-notifications');
        if (!container) {
            container = document.createElement('div');
            container.id = 'bilder-alt-notifications';
            Object.assign(container.style, {
                position: 'fixed',
                zIndex: '9999',
                top: '10px',
                right: '10px',
                width: '320px',
                display: 'flex',
                flexDirection: 'column',
                gap: '10px',
            });
            document.body.appendChild(container);
        }
        var div = document.createElement('div');
        div.className = 'tl_' + (type === 'error' ? 'error' : type === 'success' ? 'confirm' : 'info');
        Object.assign(div.style, {padding: '10px 10px 10px 50px', borderRadius: '3px', boxShadow: '0 2px 5px rgba(0,0,0,0.2)'});
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

    btnTitle.addEventListener('click', function () { startProcessing('title'); });
    btnDescription.addEventListener('click', function () { startProcessing('description'); });
    btnBoth.addEventListener('click', function () { startProcessing('both'); });
    if (btnTitleEmpty) btnTitleEmpty.addEventListener('click', function () { startProcessing('title-empty'); });
    if (btnDescriptionEmpty) btnDescriptionEmpty.addEventListener('click', function () { startProcessing('description-empty'); });
    if (btnBothEmpty) btnBothEmpty.addEventListener('click', function () { startProcessing('both-empty'); });
    btnStop.addEventListener('click', function () {
        shouldStop = true;
        showNotification('[KI Seiten] Verarbeitung wird angehalten...', 'info');
    });
});
