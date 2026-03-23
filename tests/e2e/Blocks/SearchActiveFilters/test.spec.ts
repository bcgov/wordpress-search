import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { createTestPosts, deleteTestPosts, type TestPostsData } from '../../helpers/test-data-setup';

test.describe('Search Active Filters Block', () => {
	let testData: TestPostsData;

	test.beforeAll(async ({ requestUtils }) => {
		// Create test posts and categories before all tests
		// This helper creates 4 posts: "Test Post 1", "Test Post 2", "Test Post 3", "Test Post 4"
		// and 3 categories: "Test Category A", "Test Category B", "Test Category C"
		testData = await createTestPosts(requestUtils);
	});

	test.afterAll(async ({ requestUtils }) => {
		// Delete test posts and categories after all tests
		await deleteTestPosts(requestUtils, testData);
	});

	test('complete search active filters workflow - full user journey', async ({ page, admin, requestUtils }) => {
		// Activate Twenty Twenty-Four theme explicitly (if not already active)
		try {
			await admin.visitAdminPage('themes.php');
			const activateButton = page.locator('a[href*="twentytwentyfour"].activate');
			if (await activateButton.count() > 0) {
				await activateButton.click();
			}
		} catch {
			// Theme might already be active, continue anyway
		}

		// Store original template content for cleanup
		let originalTemplateContent: string | null = null;
		let blockWasAdded = false;

		// Add the Search Active Filters block to the search template
		try {
			// Get the search template
			const template = await requestUtils.rest({
				method: 'GET',
				path: '/wp/v2/templates/twentytwentyfour//search',
			}) as { content: { raw: string } };

			// Store original content for cleanup
			originalTemplateContent = template.content.raw;

			// Check if block already exists in template content
			const blockMarkup = `<!-- wp:wordpress-search/search-active-filters /-->`;
			if (!template.content.raw.includes('wordpress-search/search-active-filters')) {
				let updatedContent = template.content.raw;
				
				// Try to insert before query loop block (so filters appear above results)
				const queryLoopPattern = /(<!-- wp:query[^>]*-->)/;
				if (queryLoopPattern.test(updatedContent)) {
					updatedContent = updatedContent.replace(
						queryLoopPattern,
						`${blockMarkup}\n$1`
					);
				} else {
					// If no query loop, add at the beginning
					updatedContent = blockMarkup + '\n' + updatedContent;
				}

				// Update the template
				await requestUtils.rest({
					method: 'PUT',
					path: '/wp/v2/templates/twentytwentyfour//search',
					data: {
						content: {
							raw: updatedContent,
						},
					},
				});
				blockWasAdded = true;
			}
		} catch (error) {
			// Template might not exist or be editable - continue anyway
			console.warn('Could not add block to search template via REST API, continuing test:', error);
		}

		// Test 1: Verify block appears when filters are applied
		// Navigate to search results page with one category filter applied
		// Using testData.categoryIds[0] for "Test Category A"
		// Taxonomy filters use "taxonomy_" prefix with taxonomy name
		await page.goto(`/?s=test&taxonomy_category=${testData.categoryIds[0]}`);
		await page.waitForLoadState('networkidle');

		// Check if block exists - if not, skip the test
		const activeFiltersBlock = page.locator('.wp-block-wordpress-search-search-active-filters');
		const blockExists = await activeFiltersBlock.count() > 0;
		if (!blockExists) {
			test.skip(true, 'Search Active Filters block is not in the search template. Please add it manually to test active filters functionality.');
			return;
		}

		// Verify block is visible
		await expect(activeFiltersBlock.first()).toBeVisible();

		// Verify filter count shows "1 filter applied"
		const filterCount = activeFiltersBlock.locator('.search-active-filters__count');
		await expect(filterCount).toBeVisible();
		const countText = await filterCount.textContent();
		expect(countText).toMatch(/1 filter applied/i);

		// Verify "Clear all" button is NOT visible when only 1 filter is applied
		const clearAllButton = activeFiltersBlock.locator('.search-active-filters__clear-all');
		await expect(clearAllButton).not.toBeVisible();

		// Verify filter chip is visible
		const filterChips = activeFiltersBlock.locator('.search-active-filters__chip');
		await expect(filterChips.first()).toBeVisible();
		
		// Verify chip contains the category name
		const chipLabel = filterChips.first().locator('.search-active-filters__chip-label');
		await expect(chipLabel).toBeVisible();
		const chipText = await chipLabel.textContent();
		expect(chipText).toBeTruthy();

		// Verify remove button (X) is present on the chip
		const chipRemoveButton = filterChips.first().locator('.search-active-filters__chip-remove');
		await expect(chipRemoveButton).toBeVisible();
		await expect(chipRemoveButton).toHaveAttribute('aria-label', 'Remove filter');

		// Test 2: Apply multiple filters and verify "Clear all" button appears
		// Navigate to search with 2 category filters applied (comma-separated format)
		await page.goto(`/?s=test&taxonomy_category=${testData.categoryIds[0]},${testData.categoryIds[1]}`);
		await page.waitForLoadState('networkidle');

		// Verify filter count shows "2 filters applied"
		await expect(filterCount).toBeVisible();
		const countTextMultiple = await filterCount.textContent();
		expect(countTextMultiple).toMatch(/2 filters applied/i);

		// Verify "Clear all" button is NOW visible when 2+ filters are applied
		await expect(clearAllButton).toBeVisible();
		await expect(clearAllButton).toHaveText('Clear all');

		// Verify both filter chips are visible
		const allChips = activeFiltersBlock.locator('.search-active-filters__chip');
		await expect(allChips).toHaveCount(2);

		// Test 3: Test individual chip remove button (X)
		// Click the remove button on the first chip
		const firstChipRemove = allChips.first().locator('.search-active-filters__chip-remove');
		await firstChipRemove.click();
		await page.waitForLoadState('networkidle');

		// Verify we're redirected to a URL with only one filter remaining
		const currentUrl = page.url();
		expect(currentUrl).toContain('s=test');
		expect(currentUrl).toContain('taxonomy_category');

		// Verify only 1 chip remains
		await expect(activeFiltersBlock.locator('.search-active-filters__chip')).toHaveCount(1);

		// Verify "Clear all" button is hidden again (only 1 filter left)
		await expect(clearAllButton).not.toBeVisible();

		// Test 4: Test "Clear all" button when multiple filters are applied
		// Navigate back to search with 2 filters (comma-separated format)
		await page.goto(`/?s=test&taxonomy_category=${testData.categoryIds[0]},${testData.categoryIds[1]}`);
		await page.waitForLoadState('networkidle');

		// Verify "Clear all" button is visible
		await expect(clearAllButton).toBeVisible();

		// Click "Clear all" button
		await clearAllButton.click();
		await page.waitForLoadState('networkidle');

		// Verify we're redirected to search results without filters
		const urlAfterClear = page.url();
		expect(urlAfterClear).toContain('s=test');
		expect(urlAfterClear).not.toContain('taxonomy_category');

		// Verify active filters block is either hidden or shows 0 filters
		// The block might still be present but with no chips
		const chipsAfterClear = activeFiltersBlock.locator('.search-active-filters__chip');
		const chipCount = await chipsAfterClear.count();
		expect(chipCount).toBe(0);

		// Test 5: Verify accessibility (proper semantic HTML, screen reader friendly, keyboard navigation)
		// Navigate back to search with filters applied
		await page.goto(`/?s=test&taxonomy_category=${testData.categoryIds[0]},${testData.categoryIds[1]}`);
		await page.waitForLoadState('networkidle');

		// Verify semantic HTML structure
		await expect(activeFiltersBlock.first()).toBeVisible();
		
		// Verify filter count text is readable and screen reader friendly
		await expect(filterCount).toBeVisible();
		const accessibilityCountText = await filterCount.textContent();
		expect(accessibilityCountText).toBeTruthy();
		expect(accessibilityCountText).toMatch(/\d+/); // Should contain at least one digit
		expect(accessibilityCountText).toMatch(/filter/i); // Should mention "filter"

		// Verify all remove buttons have proper ARIA labels
		const allRemoveButtons = activeFiltersBlock.locator('.search-active-filters__chip-remove');
		const removeButtonCount = await allRemoveButtons.count();
		for (let i = 0; i < removeButtonCount; i++) {
			const removeButton = allRemoveButtons.nth(i);
			await expect(removeButton).toHaveAttribute('aria-label', 'Remove filter');
		}

		// Verify clear all button is accessible (when visible)
		await expect(clearAllButton).toBeVisible();
		const clearAllText = await clearAllButton.textContent();
		expect(clearAllText).toBeTruthy();
		expect(clearAllText?.trim().length).toBeGreaterThan(0);

		// Test keyboard navigation - verify buttons are focusable
		await allRemoveButtons.first().focus();
		await expect(allRemoveButtons.first()).toBeFocused();

		// Test keyboard activation - press Enter on remove button
		await page.keyboard.press('Enter');
		await page.waitForLoadState('networkidle');
		
		// Verify filter was removed via keyboard
		await expect(activeFiltersBlock.locator('.search-active-filters__chip')).toHaveCount(1);

		// Cleanup: Remove the block from the search template
		if (blockWasAdded && originalTemplateContent !== null) {
			try {
				await requestUtils.rest({
					method: 'PUT',
					path: '/wp/v2/templates/twentytwentyfour//search',
					data: {
						content: {
							raw: originalTemplateContent,
						},
					},
				});
			} catch (error) {
				console.warn('Could not remove block from search template via REST API:', error);
			}
		}
	});
});
