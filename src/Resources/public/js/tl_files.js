const IMAGE_AI = 'bundles/bilderalt/icons/ai.svg';
const IMAGE_AI_NO_ALT = 'bundles/bilderalt/icons/ai-no-alt.svg';

async function generateImageTag($event, el) {
    $event?.preventDefault();

    const path = el.getAttribute('data-file-path');

    el.innerHTML = el.innerHTML + '<span class="loading"></span>';
    el.classList.add('loading');

    const formData = new FormData();
    formData.append('path', path);
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
            const succeeded = (data?.data || []).filter(item => item.success !== false);
            let message;
            if (succeeded.length === 1) {
                const altText = succeeded[0]?.altTag || '';
                message = `Alt-Text erfolgreich generiert: &ldquo;${altText}&rdquo;`;
            } else {
                const lines = succeeded.map(item => {
                    const lang = (item.isoCode || '').toUpperCase();
                    const altText = item?.altTag || '';
                    return `<b>${lang}:</b> &ldquo;${altText}&rdquo;`;
                });
                message = `Alt-Texte für folgende Sprachen generiert:<br>${lines.join('<br>')}`;
            }
            showNotification(message, 'success');
            updateElLink(el);
        } else if (data?.data?.length) {
            data?.data.forEach(item => {
                showNotification(item.message || 'Fehler bei der Generierung des Alt-Texts.', 'error');
            })
        } else {
            const message = data?.message || 'Fehler bei der Generierung des Alt-Texts.';
            showNotification(message, 'error');
        }
    } catch (error) {
        console.error('Fehler beim API-Aufruf:', error);
        showNotification('Fehler bei der Kommunikation mit dem Server.', 'error');
    } finally {
        el.classList.remove('loading');
        const loadingSpan = el.querySelector('.loading');
        if (loadingSpan) {
            loadingSpan.remove();
        }
    }
}

function updateElLink(el) {
    if (!el) {
        return
    }
    el.classList.remove('no-alt');

    const img = el.querySelector('img');
    if (!img) {
        return;
    }
    img.src = IMAGE_AI;
}

function showNotification(message, type = 'info') {
    let container = document.getElementById('bilder-alt-notifications');
    if (!container) {
        container = document.createElement('div');
        container.id = 'bilder-alt-notifications';
        container.style.position = 'fixed';
        container.style.zIndex = '9999';
        container.style.top = '10px';
        container.style.right = '10px';
        container.style.background = '#fff';
        document.body.appendChild(container);
    }

    const notification = document.createElement('div');
    notification.className = `tl_${type === 'error' ? 'error' : (type === 'success' ? 'confirm' : 'info')}`;
    notification.style.padding = '10px 10px 10px 50px';
    notification.style.borderRadius = '3px';
    notification.style.boxShadow = '0 2px 5px rgba(0,0,0,0.2)';
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
