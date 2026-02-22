async function seitenAiGenerateTitle(pageId, btn) {
    btn.disabled = true;
    btn.classList.add('loading');
    const originalHtml = btn.innerHTML;
    btn.innerHTML += '<span class="bilder-alt-spinner"></span>';

    const formData = new FormData();
    formData.append('pageId', pageId);

    try {
        const response = await fetch('/contao/bilder-alt/api/v1/page/generate-title', {
            method: 'POST',
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            body: formData,
        });

        const data = await response.json();

        if (data.success && data.title) {
            const field = document.getElementById('ctrl_pageTitle');
            if (field) {
                field.value = data.title;
                field.dispatchEvent(new Event('change'));
            }
            seitenAiShowNotification('SEO-Titel generiert: "' + data.title + '"', 'success');
        } else {
            seitenAiShowNotification(data.message || 'Fehler bei der Generierung des Titels.', 'error');
        }
    } catch (error) {
        console.error('[Bilder Alt] Fehler:', error);
        seitenAiShowNotification('Fehler bei der Kommunikation mit dem Server.', 'error');
    } finally {
        btn.disabled = false;
        btn.classList.remove('loading');
        btn.innerHTML = originalHtml;
    }
}

async function seitenAiGenerateDescription(pageId, btn) {
    btn.disabled = true;
    btn.classList.add('loading');
    const originalHtml = btn.innerHTML;
    btn.innerHTML += '<span class="bilder-alt-spinner"></span>';

    const formData = new FormData();
    formData.append('pageId', pageId);

    try {
        const response = await fetch('/contao/bilder-alt/api/v1/page/generate-description', {
            method: 'POST',
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            body: formData,
        });

        const data = await response.json();

        if (data.success && data.description) {
            const field = document.getElementById('ctrl_description');
            if (field) {
                field.value = data.description;
                field.dispatchEvent(new Event('change'));
            }
            seitenAiShowNotification('Beschreibung generiert: "' + data.description + '"', 'success');
        } else {
            seitenAiShowNotification(data.message || 'Fehler bei der Generierung der Beschreibung.', 'error');
        }
    } catch (error) {
        console.error('[Bilder Alt] Fehler:', error);
        seitenAiShowNotification('Fehler bei der Kommunikation mit dem Server.', 'error');
    } finally {
        btn.disabled = false;
        btn.classList.remove('loading');
        btn.innerHTML = originalHtml;
    }
}

function seitenAiShowNotification(message, type) {
    let container = document.getElementById('bilder-alt-notifications');
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

    const div = document.createElement('div');
    div.className = 'tl_' + (type === 'error' ? 'error' : type === 'success' ? 'confirm' : 'info');
    Object.assign(div.style, {
        padding: '10px 10px 10px 50px',
        borderRadius: '3px',
        boxShadow: '0 2px 5px rgba(0,0,0,0.2)',
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
