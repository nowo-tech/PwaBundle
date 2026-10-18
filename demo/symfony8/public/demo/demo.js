// Demo only: show the bundle's install prompt without a beforeinstallprompt event (headless browsers, Safari, Firefox).
document.addEventListener('click', (event) => {
    const trigger = event.target instanceof Element ? event.target.closest('[data-demo-preview-prompt]') : null;
    const prompt = document.getElementById('nowo-pwa-install');
    if (!trigger || !prompt) {
        return;
    }
    prompt.hidden = false;
    prompt.querySelectorAll('[data-pwa-install-action^="dismiss"]').forEach((button) => {
        button.addEventListener('click', () => { prompt.hidden = true; }, { once: true });
    });
});
