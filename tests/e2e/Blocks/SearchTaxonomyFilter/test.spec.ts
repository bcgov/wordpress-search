import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { createTestPosts, deleteTestPosts, type TestPostsData } from '../../helpers/test-data-setup';

test.describe('Search Taxonomy Filter Block', () => {
	const BLOCK_NAME = 'wordpress-search/search-taxonomy-filter';
	let testData: TestPostsData;

	test.beforeAll(async ({ requestUtils }) => {
		// Create test posts and categories before all tests
		testData = await createTestPosts(requestUtils);
	});

	test.afterAll(async ({ requestUtils }) => {
		// Delete test posts and categories after all tests
		await deleteTestPosts(requestUtils, testData);
	});

	test.beforeEach(async ({ admin }) => {
		// Create a new post before each test
		await admin.createNewPost();
	});

	test('should display newly created categories taxonomy in the block editor', async ({ editor, page }) => {
		await editor.insertBlock({ name: BLOCK_NAME });

		// Wait for the block to be inserted and visible
		const block = editor.canvas.locator(`[data-type="${BLOCK_NAME}"]`);
		await expect(block).toBeVisible();

		// Select the block to ensure inspector panel shows block settings
		await block.click();

		// The block shows taxonomies in the inspector panel with format "PostType: Taxonomy"
		// The label is "Post: Categories" (plural, with post type prefix)
		const categoryCheckbox = page.getByRole('checkbox', { name: /Post: Categories/i });
		
		// Wait for the category checkbox to appear (with timeout for API fetch)
		// This will wait for the taxonomy API call to complete and render the checkboxes
		await expect(categoryCheckbox).toBeVisible({ timeout: 15000 });
		await expect(categoryCheckbox).toBeEnabled();

		// Select the Category taxonomy (if not already selected)
		if ( !(await categoryCheckbox.isChecked()) ) {
			await categoryCheckbox.click();
		}

		// Verify the checkbox is checked (this also waits for the state to update)
		await expect(categoryCheckbox).toBeChecked();

		// Note: Individual category terms (Test Category A, B, C) are not shown in the editor,
		// they only appear on the frontend. The editor just shows which taxonomies are selected.
	});
});
