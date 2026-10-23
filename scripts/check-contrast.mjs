// Verifies WCAG AA contrast of the Synapse design tokens in resources/css/app.css.
// Text pairs need 4.5:1, UI boundaries (input borders, focus ring) need 3:1.
// Run: node scripts/check-contrast.mjs (part of `npm run check`).

import { readFileSync } from 'node:fs';

const css = readFileSync(
    new URL('../resources/css/app.css', import.meta.url),
    'utf8',
);

function tokens(selector) {
    const start = css.indexOf(`${selector} {`);

    if (start === -1) {
        throw new Error(`Block "${selector}" not found in app.css`);
    }

    const block = css.slice(start, css.indexOf('}', start));
    const result = {};

    for (const [, name, l, c, h] of block.matchAll(
        /--([\w-]+):\s*oklch\(([\d.]+)\s+([\d.]+)\s+([\d.]+)\)/g,
    )) {
        result[name] = [Number(l), Number(c), Number(h)];
    }

    return result;
}

function luminance([L, C, H]) {
    const a = C * Math.cos((H * Math.PI) / 180);
    const b = C * Math.sin((H * Math.PI) / 180);
    const l = (L + 0.3963377774 * a + 0.2158037573 * b) ** 3;
    const m = (L - 0.1055613458 * a - 0.0638541728 * b) ** 3;
    const s = (L - 0.0894841775 * a - 1.291485548 * b) ** 3;
    const clamp = (v) => Math.min(1, Math.max(0, v));
    const r = clamp(4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s);
    const g = clamp(-1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s);
    const bl = clamp(-0.0041960863 * l - 0.7034186147 * m + 1.707614701 * s);

    return 0.2126 * r + 0.7152 * g + 0.0722 * bl;
}

function contrast(x, y) {
    const [hi, lo] = [luminance(x), luminance(y)].sort((p, q) => q - p);

    return (hi + 0.05) / (lo + 0.05);
}

const TEXT = 4.5;
const UI = 3;

const pairs = [
    ['foreground', 'background', TEXT],
    ['card-foreground', 'card', TEXT],
    ['popover-foreground', 'popover', TEXT],
    ['muted-foreground', 'background', TEXT],
    ['muted-foreground', 'card', TEXT],
    ['muted-foreground', 'muted', TEXT],
    ['primary-foreground', 'primary', TEXT],
    ['primary', 'background', TEXT],
    ['primary', 'card', TEXT],
    ['secondary-foreground', 'secondary', TEXT],
    ['accent-foreground', 'accent', TEXT],
    ['destructive-foreground', 'destructive', TEXT],
    ['destructive', 'background', TEXT],
    ['destructive', 'card', TEXT],
    ['success-foreground', 'success', TEXT],
    ['success', 'background', TEXT],
    ['success', 'card', TEXT],
    ['warning-foreground', 'warning', TEXT],
    ['warning', 'background', TEXT],
    ['warning', 'card', TEXT],
    ['sidebar-foreground', 'sidebar', TEXT],
    ['sidebar-accent-foreground', 'sidebar-accent', TEXT],
    ['sidebar-primary-foreground', 'sidebar-primary', TEXT],
    ['muted-foreground', 'sidebar', TEXT],
    ['input', 'background', UI],
    ['input', 'card', UI],
    ['ring', 'background', UI],
    ['ring', 'card', UI],
];

const light = tokens(':root');
const themes = { light, dark: { ...light, ...tokens('.dark') } };
let failures = 0;

for (const [theme, values] of Object.entries(themes)) {
    for (const [fg, bg, min] of pairs) {
        if (!values[fg] || !values[bg]) {
            console.error(`[${theme}] missing token: ${values[fg] ? bg : fg}`);
            failures++;
            continue;
        }

        const ratio = contrast(values[fg], values[bg]);

        if (ratio < min) {
            console.error(
                `[${theme}] ${fg} on ${bg}: ${ratio.toFixed(2)} < ${min}`,
            );
            failures++;
        }
    }
}

if (failures > 0) {
    console.error(
        `\nContrast check failed: ${failures} pair(s) below WCAG AA.`,
    );
    process.exit(1);
}

process.stdout.write(
    `Contrast check passed: ${pairs.length * 2} pairs meet WCAG AA in light and dark.\n`,
);
