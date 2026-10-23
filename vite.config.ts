import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { fontsource, local } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            // Self-hosted only (no CDN): FiraGO for UI text (Georgian + Latin),
            // JetBrains Mono for timers and scores. Load only the weights we use.
            fonts: [
                local('FiraGO', {
                    variants: [
                        {
                            src: 'resources/fonts/firago/FiraGO-Regular.woff2',
                            weight: 400,
                        },
                        {
                            src: 'resources/fonts/firago/FiraGO-Medium.woff2',
                            weight: 500,
                        },
                        {
                            src: 'resources/fonts/firago/FiraGO-SemiBold.woff2',
                            weight: 600,
                        },
                        {
                            src: 'resources/fonts/firago/FiraGO-Bold.woff2',
                            weight: 700,
                        },
                    ],
                    preload: [{ weight: 400 }, { weight: 500 }],
                    fallbacks: ['ui-sans-serif', 'system-ui', 'sans-serif'],
                }),
                fontsource('JetBrains Mono', {
                    weights: [400, 500],
                    subsets: ['latin'],
                    preload: false,
                    fallbacks: ['ui-monospace', 'monospace'],
                }),
            ],
        }),
        inertia(),
        react(),
        babel({
            presets: [reactCompilerPreset()],
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ]),
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
        // Oxlint's default plugins (typescript, unicorn, oxc) plus react for react/no-danger.
        plugins: ['typescript', 'unicorn', 'oxc', 'react'],
        rules: {
            'react/no-danger': 'error',
            'typescript/no-explicit-any': 'error',
            'no-eval': 'error',
            'no-implied-eval': 'error',
            'typescript/no-implied-eval': 'error',
            'no-console': ['warn', { allow: ['warn', 'error'] }],
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            'composer.json',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/app.css',
        },
    },
});
