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

        // A race condition can occur because the block attributes are not set until
        // the post type API fetch finishes. This double click lets the block finish
        // loading completely before comparing to the snapshot.
        await editor.page.getByRole('checkbox', { name: 'Pages' }).click();
        await editor.page.getByRole('checkbox', { name: 'Pages' }).click();
        expect(await editor.getEditedPostContent()).toMatchSnapshot();

        // Should probably make this into a reusable function as it will appear often.
        const preview = (await editor.openPreviewPage()).locator(BLOCK_CLASS);
        await expect(preview).toHaveScreenshot(SCREENSHOT_OPTIONS);
    });

    test('should remove post type', async ({ editor }) => {
        await editor.insertBlock({ name: BLOCK_NAME });
        await editor.page.getByRole('checkbox', { name: 'Posts' }).click();
        expect(await editor.getEditedPostContent()).toMatchSnapshot();

        const preview = (await editor.openPreviewPage()).locator(BLOCK_CLASS);
        await expect(preview).toHaveScreenshot(SCREENSHOT_OPTIONS);
    });

    test('should add back all post types when unselecting all', async ({ editor }) => {
        await editor.insertBlock({ name: BLOCK_NAME });
        const postsCheckbox = editor.page.getByRole('checkbox', { name: 'Posts' });
        const pagesCheckbox = editor.page.getByRole('checkbox', { name: 'Pages' });
        
        await postsCheckbox.click();
        await pagesCheckbox.click();

        expect(postsCheckbox).toBeChecked();
        expect(pagesCheckbox).toBeChecked();
        expect(await editor.getEditedPostContent()).toMatchSnapshot();
    });

    test('should set underline color', async ({ editor }) => {
        await editor.insertBlock({ name: BLOCK_NAME });
        await editor.page.getByRole('button', { name: 'Active Filter Underline Color' }).click();
        await editor.page.getByRole('option', { name: 'Contrast' }).click();
        expect(await editor.getEditedPostContent()).toMatchSnapshot();

        // TODO: Update block to display the underline inside the block element.
        // Because the underline displays outside the bounds of the block element,
        // we can't effectively screenshot it.

        // const preview = (await editor.openPreviewPage()).getByRole('main');
        // await expect(preview).toHaveScreenshot(SCREENSHOT_OPTIONS);
    });
});