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

	let postId: number | null;

	test.beforeEach(async ({ admin, editor, page }) => {
		// Create a new post with the Search Taxonomy Filter block for each test
		await admin.createNewPost();
		await editor.insertBlock({ name: BLOCK_NAME });

		const block = editor.canvas.locator(`[data-type="${BLOCK_NAME}"]`);
		await expect(block).toBeVisible();

		// Select the block and verify editor functionality
		await block.click();
		const categoryCheckbox = page.getByRole('checkbox', { name: /Post: Categories/i });
		await expect(categoryCheckbox).toBeVisible({ timeout: 15000 });
		await expect(categoryCheckbox).toBeEnabled();

		// Select the Category taxonomy if not already selected
		if (!(await categoryCheckbox.isChecked())) {
			await categoryCheckbox.click();
		}

		// Verify the checkbox is checked
		await expect(categoryCheckbox).toBeChecked();

		// A race condition can occur because the block attributes are not set until
		// the taxonomy API fetch finishes. Double clicking ensures state is set
		// before saving.
		await categoryCheckbox.click();
		await categoryCheckbox.click();

		// Publish the post
		postId = await editor.publishPost();
		expect(postId).not.toBeNull();
	});

	test('complete taxonomy filter workflow - full user journey', async ({ page }) => {
		expect(postId).not.toBeNull();

		// Navigate to frontend
		await page.goto(`/?p=${postId}`);

		// Wait for checkboxes to be visible
		const categoryCheckboxes = page.locator('input[name="taxonomy_category[]"]');
		await expect(categoryCheckboxes.first()).toBeVisible({ timeout: 10000 });

		// Get the first checkbox and its term ID
		const firstCheckbox = categoryCheckboxes.first();
		const termId = await firstCheckbox.getAttribute('value');
		expect(termId).toBeTruthy();

		// Check the checkbox and apply filter
		await firstCheckbox.check();
		const applyButton = page.locator('.taxonomy-filter-apply__button');
		await Promise.all([
			page.waitForURL((url) => {
				const urlObj = new URL(url);
				return urlObj.searchParams.has('taxonomy_category');
			}),
			applyButton.click(),
		]);

		// Verify URL contains the filter parameter
		const url = page.url();
		const urlParams = new URL(url).searchParams;
		expect(urlParams.get('taxonomy_category')).toBe(termId);

		// Uncheck the checkbox and apply filter
		const checkedCheckbox = page.locator(`input[name="taxonomy_category[]"][value="${termId}"]`);
		await expect(checkedCheckbox).toBeChecked();
		await checkedCheckbox.uncheck();

		await Promise.all([
			page.waitForURL((url) => {
				const urlObj = new URL(url);
				return !urlObj.searchParams.has('taxonomy_category');
			}),
			applyButton.click(),
		]);

		// Verify filter parameter is removed from URL
		const finalUrlParams = new URL(page.url()).searchParams;
		expect(finalUrlParams.has('taxonomy_category')).toBeFalsy();
	});
});
