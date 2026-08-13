import { withThemeByClassName } from '@storybook/addon-themes';
import type { Preview } from '@storybook/react-vite';

import '../resources/css/app.css';

const preview: Preview = {
    decorators: [
        withThemeByClassName({
            themes: {
                light: '',
                dark: 'dark',
            },
            defaultTheme: 'light',
        }),
    ],
    parameters: {
        actions: { argTypesRegex: '^on[A-Z].*' },
        backgrounds: {
            default: 'KORA Mist',
            values: [
                { name: 'KORA Mist', value: '#F4F6F8' },
                { name: 'KORA White', value: '#FFFFFF' },
                { name: 'KORA Night', value: '#0B1224' },
            ],
        },
        controls: {
            matchers: {
                color: /(background|color)$/i,
                date: /Date$/i,
            },
        },
        docs: {
            toc: true,
        },
        layout: 'centered',
    },
    tags: ['autodocs'],
};

export default preview;
