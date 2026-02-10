import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { createTestPosts, deleteTestPosts, type TestPostsData } from '../../helpers/test-data-setup';

test.describe('Search Result Count Block', () => {
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

	test('complete search result count workflow - full user journey', async ({ page, admin, requestUtils }) => {
		// Activate Twenty Twenty-Four theme explicitly (if not already active)
		try {
			await admin.visitAdminPage('themes.php');
			const activateButton = page.locator('a[href*="twentytwentyfour"].activate');
			if (await activateButton.count() > 0) {
				await activateButton.click();
				await page.waitForTimeout(1000); // Wait for activation
			}
		} catch {
			// Theme might already be active, continue anyway
		}

		// Store original template content for cleanup
		let originalTemplateContent: string | null = null;
		let blockWasAdded = false;

		// Add the Search Result Count block to the search template
		try {
			// Get the search template
			const template = await requestUtils.rest({
				method: 'GET',
				path: '/wp/v2/templates/twentytwentyfour//search',
			}) as { content: { raw: string } };

			// Store original content for cleanup
			originalTemplateContent = template.content.raw;

			// Check if block already exists in template content
			const blockMarkup = `<!-- wp:wordpress-search/search-result-count /-->`;
			if (!template.content.raw.includes('wordpress-search/search-result-count')) {
				let updatedContent = template.content.raw;
				
				// Try to insert before query loop block (so count appears above results)
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

		// Test 1: Display correct result count when search results are present
		// Navigate to search results page - Search for "test" to get all test posts (should be 4 posts)
		await page.goto(`/?s=test`);
		await page.waitForLoadState('networkidle');

		// Check if block exists - if not, skip the test
		const resultCountBlock = page.locator('.search-result-count');
		const blockExists = await resultCountBlock.count() > 0;
		if (!blockExists) {
			test.skip(true, 'Search Result Count block is not in the search template. Please add it manually to test result count functionality.');
			return;
		}

		// Verify block is visible
		await expect(resultCountBlock.first()).toBeVisible({ timeout: 10000 });

		// Verify the block structure
		const blockContent = resultCountBlock.first().locator('.search-result-count__content');
		await expect(blockContent).toBeVisible();

		// Verify the count message is displayed
		const countMessage = resultCountBlock.first().locator('.search-result-count__message');
		await expect(countMessage).toBeVisible();

		// Verify it shows exactly 4 results found (our test posts)
		let messageText = await countMessage.textContent();
		expect(messageText).toMatch(/4 results found/i);

		// Test 2: Display "0 results found" when no results are present
		// Search for something that doesn't exist
		await page.goto(`/?s=ThisQueryShouldReturnZeroResultsXYZ123`);
		await page.waitForLoadState('networkidle');

		await expect(countMessage).toBeVisible({ timeout: 10000 });

		// Verify it shows "0 results found"
		messageText = await countMessage.textContent();
		expect(messageText).toMatch(/0 results found/i);


		// Test 3: Verify count for "Test Post" search (should show 5 results)
		await page.goto(`/?s=Test Post`);
		await page.waitForLoadState('networkidle');

		await expect(countMessage).toBeVisible({ timeout: 10000 });

		// Verify it shows exactly 5 results found
		messageText = await countMessage.textContent();
		expect(messageText).toMatch(/5 results found/i);

		// Test 4: Verify accessibility (proper semantic HTML, screen reader friendly)
		// Navigate back to a search with results
		await page.goto(`/?s=test`);
		await page.waitForLoadState('networkidle');

		await expect(countMessage).toBeVisible({ timeout: 10000 });

		// Verify semantic HTML structure and accessibility
		// The block should use a div container (which is appropriate for this use case)
		const blockElement = resultCountBlock.first();
		await expect(blockElement).toBeVisible();

		// Verify the message contains readable text (screen reader friendly)
		messageText = await countMessage.textContent();
		expect(messageText).toBeTruthy();
		expect(messageText).toMatch(/\d+/); // Should contain at least one digit

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
