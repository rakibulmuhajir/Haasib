import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: ['class','[data-theme="blue-whale-dark"]'],
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                surface: {
                    0: 'var(--p-surface-0)',
                    50: 'var(--p-surface-50)',
                    100: 'var(--p-surface-100)',
                    200: 'var(--p-surface-200)',
                    300: 'var(--p-surface-300)',
                    400: 'var(--p-surface-400)',
                    500: 'var(--p-surface-500)',
                    600: 'var(--p-surface-600)',
                    700: 'var(--p-surface-700)',
                    800: 'var(--p-surface-800)',
                    900: 'var(--p-surface-900)',
                    950: 'var(--p-surface-950)',
                },
                primary: {
                    DEFAULT: 'var(--p-primary-color)',
                    50: 'var(--p-primary-50)',
                    100: 'var(--p-primary-100)',
                    200: 'var(--p-primary-200)',
                    300: 'var(--p-primary-300)',
                    400: 'var(--p-primary-400)',
                    500: 'var(--p-primary-500)',
                    600: 'var(--p-primary-600)',
                    700: 'var(--p-primary-700)',
                    800: 'var(--p-primary-800)',
                    900: 'var(--p-primary-900)',
                    950: 'var(--p-primary-950)'
                },
                // Remap Tailwind gray scale to theme surfaces so
                // existing Breeze classes pick up the theme tokens.
                gray: {
                    50: 'var(--p-surface-50)',
                    100: 'var(--p-surface-100)',
                    200: 'var(--p-surface-200)',
                    300: 'var(--p-surface-300)',
                    400: 'var(--p-surface-400)',
                    500: 'var(--p-surface-500)',
                    600: 'var(--p-surface-600)',
                    700: 'var(--p-surface-700)',
                    800: 'var(--p-surface-800)',
                    900: 'var(--p-surface-900)'
                }
            },
            borderRadius: {
                md: 'var(--p-border-radius-md)',
                lg: 'var(--p-border-radius-lg)'
            }
        },
    },

    plugins: [forms],
};
