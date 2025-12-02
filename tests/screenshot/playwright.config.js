import {defineConfig} from '@playwright/test';
import baseConfig from '../../playwright.config';

const config = defineConfig({
    ...baseConfig,
    testDir: './',
});

export default config;