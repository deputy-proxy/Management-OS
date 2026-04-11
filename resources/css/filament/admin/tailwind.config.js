/** @type {import('tailwindcss').Config} */
const colors = require('tailwindcss/colors')

module.exports = {
    content: [
        './app/Filament/**/*.php',
        './resources/views/filament/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
    ],
    darkMode: 'class',
    theme: {
        extend: {
            // -----------------------------------------------
            // PRIMARY COLOR — Indigo (matches Stacked theme)
            // Swap these values to customise the accent color.
            // -----------------------------------------------
            colors: {
                primary: {
                    50:  '#eef2ff',
                    100: '#e0e7ff',
                    200: '#c7d2fe',
                    300: '#a5b4fc',
                    400: '#818cf8',
                    500: '#6366f1',
                    600: '#4f46e5',
                    700: '#4338ca',
                    800: '#3730a3',
                    900: '#312e81',
                    950: '#1e1b4b',
                },
                // Semantic aliases (unchanged from Filament defaults)
                danger:  colors.rose,
                success: colors.emerald,
                warning: colors.amber,
                info:    colors.blue,
            },

            // -----------------------------------------------
            // TYPOGRAPHY
            // -----------------------------------------------
            fontFamily: {
                sans: ['"DM Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },

            // -----------------------------------------------
            // BORDER RADIUS — slightly softer than default
            // -----------------------------------------------
            borderRadius: {
                DEFAULT: '0.5rem',   // 8px
                lg:      '0.75rem',  // 12px
                xl:      '1rem',     // 16px
                '2xl':   '1.25rem',  // 20px
            },

            // -----------------------------------------------
            // BOX SHADOWS — minimal / flat
            // -----------------------------------------------
            boxShadow: {
                sm:   '0 1px 2px 0 rgb(0 0 0 / 0.04)',
                DEFAULT: '0 1px 3px 0 rgb(0 0 0 / 0.06), 0 1px 2px -1px rgb(0 0 0 / 0.04)',
                md:   '0 4px 6px -1px rgb(0 0 0 / 0.06), 0 2px 4px -2px rgb(0 0 0 / 0.04)',
                lg:   '0 10px 15px -3px rgb(0 0 0 / 0.06), 0 4px 6px -4px rgb(0 0 0 / 0.04)',
                xl:   '0 20px 25px -5px rgb(0 0 0 / 0.07), 0 8px 10px -6px rgb(0 0 0 / 0.04)',
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
        require('@tailwindcss/typography'),
    ],
}