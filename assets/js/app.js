document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-confirm]').forEach(button => {
        button.addEventListener('click', event => {
            if (!window.confirm(button.dataset.confirm)) event.preventDefault();
        });
    });

    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const body = document.body;
    
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', () => {
            body.classList.toggle('sidebar-open');
        });
    }
    
    const backdrop = document.createElement('div');
    backdrop.className = 'sidebar-backdrop';
    document.body.appendChild(backdrop);
    
    backdrop.addEventListener('click', () => {
        body.classList.remove('sidebar-open');
    });
});
