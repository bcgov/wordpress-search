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

	test.beforeEach(async ({ admin, editor }) => {
		// Edit the first test post instead of creating a new one
		await admin.visitAdminPage( 'post.php', `post=${testData.postIds[0]}&action=edit` );
		// Wait for the editor to be ready
		await editor.canvas.locator('body').waitFor();
	});

	test('should display newly created categories taxonomy in the block editor', async ({ editor }) => {
		await editor.insertBlock({ name: BLOCK_NAME });

		// Wait for the block to be inserted and visible
		const block = editor.canvas.locator(`[data-type="${BLOCK_NAME}"]`);
		await expect(block).toBeVisible();

		// Wait for taxonomies to load in the inspector panel
		// The block shows taxonomies in the inspector panel with format "PostType: Taxonomy"
		// Look for a checkbox containing "Category" in its label
		const categoryCheckbox = editor.page.getByRole('checkbox', { name: /Category/i });
		
		// Wait for the category checkbox to appear (with timeout for API fetch)
		await expect(categoryCheckbox).toBeVisible({ timeout: 10000 });
		await expect(categoryCheckbox).toBeEnabled();

		// Select the Category taxonomy
		await categoryCheckbox.click();

		// Verify the checkbox is checked (this also waits for the state to update)
		await expect(categoryCheckbox).toBeChecked();

		// Note: Individual category terms (Test Category A, B, C) are not shown in the editor,
		// they only appear on the frontend. The editor just shows which taxonomies are selected.
	});
});
