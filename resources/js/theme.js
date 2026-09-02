function prefersDarkSystem() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

export function applyTheme(theme) {
    const estSombre = theme === 'dark' || (theme === 'system' && prefersDarkSystem());
    document.documentElement.classList.toggle('dark', estSombre);
}

export function watchSystemTheme(getCurrentTheme) {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (getCurrentTheme() === 'system') {
            applyTheme('system');
        }
    });
}
