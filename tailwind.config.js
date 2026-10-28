import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

const brand = {
    50: '#E8F6F3', 100: '#CFEDE7', 200: '#A4DBD1', 300: '#6FC2B4', 400: '#34A394',
    500: '#14897B', 600: '#0F766E', 700: '#0C5F59', 800: '#0B4A46', 900: '#0B3B38',
};

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                brand,
                // Sisa warna biru bawaan (badge, cincin fokus kolom isian) ikut jadi teal
                blue: brand,
                gold: {
                    50: '#FEF8E1', 100: '#FCEFB8', 300: '#F6D977',
                    400: '#F2C94C', 500: '#D9A82B', 600: '#B98A1B',
                },
                sidebar: '#0F3A37',
                page: '#E6F4F1',
                // Abu-abu bernuansa teal supaya garis dan teks senada dengan palet
                gray: {
                    50: '#F4F9F8', 100: '#E7F0EE', 200: '#D5E3E0', 300: '#B7CBC7', 400: '#8CA39F',
                    500: '#5B706D', 600: '#475C5A', 700: '#2F4442', 800: '#1C302E', 900: '#0B1F1D',
                },
                success: { 50: '#E8F5E9', 500: '#43A047', 600: '#2E7D32', 700: '#1B5E20' },
                warning: { 50: '#FFF4E0', 500: '#E39A1B', 600: '#C77700', 700: '#9A5B00' },
                danger: { 50: '#FDECEA', 100: '#FAD4D0', 200: '#F5B5AE', 500: '#D92D20', 600: '#B42318', 700: '#912018' },
            },
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            borderRadius: { xl: '0.625rem', '2xl': '1rem' },
        },
    },

    plugins: [forms],
};