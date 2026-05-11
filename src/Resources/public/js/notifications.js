function showNotification(message, type) {
    type = type || 'info';
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
