const defaultTheme = require('tailwindcss/defaultTheme');

/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './node_modules/flowbite/**/*.js'
    ],

    theme: {
        extend: {
            fontFamily: {
                CoconPro: ['SpaceGrotesk-Semibold', 'sans-serif'],
                DINPro: ['SpaceGrotesk-Regular', 'sans-serif'],
                'DINPro-bold': ['SpaceGrotesk-Bold', 'sans-serif'],
                'DINPro-light': ['SpaceGrotesk-Light', 'sans-serif'],
                'DINPro-medium': ['SpaceGrotesk-Medium', 'sans-serif'],
            },

            colors: {
                // Namen aus historischen Gründen beibehalten (in vielen Views referenziert),
                // Werte auf ein von Stadel abweichendes Royal-/Navy-Blau umgestellt.
                'blau-900': '#1b4176',
                'blau-500': '#1f73d6',
                'blau-300': '#59b0ff',
                'blau-100': '#bce0ff',
                'sdarkblue': '#122748',
                'ssystemblue': '#3391f0',

                'cerulean': {
                    '50': '#eef7ff',
                    '100': '#d9edff',
                    '200': '#bce0ff',
                    '300': '#8ecdff',
                    '400': '#59b0ff',
                    '500': '#3391f0', // primary accent (Royal-Blau)
                    '600': '#1f73d6',
                    '700': '#1a5cad',
                    '800': '#1b4d8e',
                    '900': '#1b4176',
                    '950': '#122748',
                },

                'chathams-blue': {
                    '50': '#f1f6fc',
                    '100': '#e0ecf8',
                    '200': '#c7dbf1',
                    '300': '#9ec2e7',
                    '400': '#6fa1da',
                    '500': '#4d82cd',
                    '600': '#3967b3',
                    '700': '#305392',
                    '800': '#1f3d6e', // dark heading (Navy)
                    '900': '#1c3358',
                    '950': '#131f38',
                },

                'hawkes-blue': {
                    '50': '#f0f8ff',
                    '100': '#dceffd', // Table Card (Hellblau)
                    '200': '#bce0fb',
                    '300': '#82cbf8',
                    '400': '#40b1f2',
                    '500': '#1795e3',
                    '600': '#0877c1',
                    '700': '#085f9c',
                    '800': '#0b5081',
                    '900': '#0f426b',
                    '950': '#0a2b47',
                },

            },
            width: {
                '128': '32rem',
            },
        },

    },

    darkMode: 'class',

    plugins: [
        require('@tailwindcss/forms'),
        require('flowbite/plugin'),
    ],
};
