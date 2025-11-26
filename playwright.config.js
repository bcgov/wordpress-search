import {defineConfig} from '@playwright/test';
import baseConfig from '@wordpress/scripts/config/playwright.config.js';

const config = defineConfig({
    ...baseConfig,
    testDir: 'tests/e2e',
    projects: [
        {
          name: 'chromium',
          use: {
            viewport: {width: 1280, height: 720},
          },
        },
    ],
});

export default config;