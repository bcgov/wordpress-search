import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe('Block', () => {
    const BLOCK_NAME = 'wordpress-search/search-post-type-filter';
    const BLOCK_CLASS = '.wp-block-wordpress-search-search-post-type-filter';

    // TODO: Run e2e tests in Playwright Docker container for consistency.
    const SCREENSHOT_OPTIONS = {maxDiffPixelRatio: 0.02};

    test.beforeEach(async ({ admin }) => {
        // Create a new post before each test
        await admin.createNewPost();
    });

    test('should be created', async ({ editor }) => {
        await editor.insertBlock({ name: BLOCK_NAME });

        // Should probably make this into a reusable function as it will appear often.
        const preview = (await editor.openPreviewPage()).locator(BLOCK_CLASS);
        await expect(preview).toHaveScreenshot(SCREENSHOT_OPTIONS);
    });

    test('should remove post type', async ({ editor }) => {
        await editor.insertBlock({ name: BLOCK_NAME });
        await editor.page.getByRole('checkbox', { name: 'Posts' }).click();

        const preview = (await editor.openPreviewPage()).locator(BLOCK_CLASS);
        await expect(preview).toHaveScreenshot(SCREENSHOT_OPTIONS);
    });
});