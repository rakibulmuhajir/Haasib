import { ref, watch } from 'vue';

const theme = ref(localStorage.getItem('theme') || 'system');
const font = ref(localStorage.getItem('font') || 'sans');

const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');

const fonts = {
    sans: 'Figtree, sans-serif',
    serif: 'Georgia, serif',
    mono: 'ui-monospace, SFMono-Regular, Menlo, monospace',
};

function applyTheme() {
    const isDark = theme.value === 'dark' || (theme.value === 'system' && prefersDark.matches);
    document.documentElement.classList.toggle('dark', isDark);
}

function applyFont() {
    document.documentElement.style.setProperty('--app-font', fonts[font.value]);
}

watch(theme, (val) => {
    if (val === 'system') {
        localStorage.removeItem('theme');
    } else {
        localStorage.setItem('theme', val);
    }
    applyTheme();
});

watch(font, (val) => {
    localStorage.setItem('font', val);
    applyFont();
});

prefersDark.addEventListener('change', applyTheme);

applyTheme();
applyFont();

export function useTheme() {
    const cycleTheme = () => {
        const themes = ['light', 'dark', 'system'];
        const next = themes[(themes.indexOf(theme.value) + 1) % themes.length];
        theme.value = next;
    };

    const cycleFont = () => {
        const options = Object.keys(fonts);
        const next = options[(options.indexOf(font.value) + 1) % options.length];
        font.value = next;
    };

    return { theme, font, cycleTheme, cycleFont };
}
