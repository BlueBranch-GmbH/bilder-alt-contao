async function generateImageTag($event, el) {
    $event?.preventDefault();

    const path = el.getAttribute('--data-file-path');

    el.innerHTML = el.innerHTML + '<span class="loading"></span>';
    el.classList.add('loading');

    const formData = new FormData();
    formData.append('path', path);

    const domain = window.location.hostname;
    formData.append('contextUrl', domain);
    
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
            showNotification('Alt-Text erfolgreich generiert!', 'success');
        } else if (data?.data?.length) {
            data?.data.forEach(item => {
                showNotification(item.message || 'Fehler bei der Generierung des Alt-Texts.', 'error');
            })
        } else {
            showNotification('Fehler bei der Generierung des Alt-Texts.', 'error');
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
