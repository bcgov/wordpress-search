import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe('Block', () => {
    const BLOCK_NAME = 'wordpress-search/search-post-type-filter';

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
    });

    test('should remove post type', async ({ editor }) => {
        await editor.insertBlock({ name: BLOCK_NAME });
        await editor.page.getByRole('checkbox', { name: 'Posts' }).click();
        expect(await editor.getEditedPostContent()).toMatchSnapshot();
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
        // Use first() to get the exact "Contrast" option (not "Contrast / Two" or "Contrast / Three")
        await editor.page.getByRole('option', { name: 'Contrast' }).first().click();
        expect(await editor.getEditedPostContent()).toMatchSnapshot();
    });
});