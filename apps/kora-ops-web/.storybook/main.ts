import path from 'node:path';
import { fileURLToPath } from 'node:url';

import type { StorybookConfig } from '@storybook/react-vite';
import { mergeConfig } from 'vite';

const dirname = path.dirname(fileURLToPath(import.meta.url));
const projectRoot = path.resolve(dirname, '..');

const config: StorybookConfig = {
    stories: ['../resources/js/**/*.stories.@(js|jsx|mjs|ts|tsx)'],
    addons: [
        '@storybook/addon-docs',
        '@storybook/addon-a11y',
        '@storybook/addon-themes',
    ],
    framework: {
        name: '@storybook/react-vite',
        options: {},
    },
    viteFinal(config) {
        return mergeConfig(config, {
            resolve: {
                alias: {
                    '@': path.resolve(projectRoot, 'resources/js'),
                },
            },
        });
    },
};

export default config;
