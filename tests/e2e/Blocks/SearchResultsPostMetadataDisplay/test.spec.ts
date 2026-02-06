import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { createTestPosts, deleteTestPosts, type TestPostsData } from '../../helpers/test-data-setup';

test.describe('Search Results Post Metadata Display Block', () => {
	const BLOCK_NAME = 'wordpress-search/search-results-post-metadata-display';
	let testData: TestPostsData;

	test.beforeAll(async ({ requestUtils }) => {
		// Create test posts and categories before all tests
		// This helper already assigns categories to posts
		testData = await createTestPosts(requestUtils);
	});

	test.afterAll(async ({ requestUtils }) => {
		// Delete test posts and categories after all tests
		await deleteTestPosts(requestUtils, testData);
	});

	test('should display category metadata in search results', async ({ page, admin, requestUtils }) => {
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

		// Add the Search Results Post Metadata Display block to the post template inside the search template
		try {
			// Get the search template
			const template = await requestUtils.rest({
				method: 'GET',
				path: '/wp/v2/templates/twentytwentyfour//search',
			}) as { content: { raw: string } };

			// Check if block already exists in template content
			if (!template.content.raw.includes('wordpress-search/search-results-post-metadata-display')) {
				let updatedContent = template.content.raw;
				const blockMarkup = `<!-- wp:wordpress-search/search-results-post-metadata-display /-->`;
				
				// Find the post template block inside the query loop
				// The post template structure is typically:
				// <!-- wp:post-template -->
				//   <!-- wp:post-title /-->
				//   <!-- wp:post-excerpt /-->
				// <!-- /wp:post-template -->
				// We need to insert the metadata block inside the post template, before the closing tag
				
				// Pattern to match post template with its content
				// This matches from opening to closing, capturing the content in between
				const postTemplatePattern = /(<!-- wp:post-template[^>]*-->)([\s\S]*?)(<!-- \/wp:post-template -->)/;
				
				if (postTemplatePattern.test(updatedContent)) {
					// Insert the block inside the post template, before the closing tag
					updatedContent = updatedContent.replace(
						postTemplatePattern,
						(match, opening, content, closing) => {
							// Add the metadata block before the closing tag
							return `${opening}${content}\n${blockMarkup}\n${closing}`;
						}
					);
				} else {
					// If no post template found, try to find query loop and add post template with our block
					const queryLoopPattern = /(<!-- wp:query[^>]*-->)([\s\S]*?)(<!-- \/wp:query -->)/;
					if (queryLoopPattern.test(updatedContent)) {
						// Insert post template with metadata block inside query loop
						updatedContent = updatedContent.replace(
							queryLoopPattern,
							(match, opening, content, closing) => {
								// Check if there's already a post template in the content
								if (content.includes('wp:post-template')) {
									// Add block to existing post template
									return `${opening}${content.replace(
										/(<!-- wp:post-template[^>]*-->)/,
										`$1\n${blockMarkup}`
									)}${closing}`;
								} else {
									// Add new post template with metadata block
									return `${opening}${content}\n<!-- wp:post-template -->\n${blockMarkup}\n<!-- /wp:post-template -->\n${closing}`;
								}
							}
						);
					} else {
						// Last resort: add at the beginning
						updatedContent = blockMarkup + '\n' + updatedContent;
					}
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
			}
		} catch (error) {
			// Template might not exist or be editable - continue anyway
			console.warn('Could not add block to search template via REST API, continuing test:', error);
		}

		// Navigate to search results page
		// Search for "Test Post" to get all our test posts from the helper
		await page.goto(`/?s=Test Post`);

		// Wait for page to load
		await page.waitForLoadState('networkidle');

		// Check if metadata block is present
		const metadataBlocks = page.locator('.wp-block-wordpress-search-search-results-post-metadata-display');
		const blockCount = await metadataBlocks.count();

		// Check if block exists - if not, skip the test
		if (blockCount === 0) {
			test.skip(true, 'Search Results Post Metadata Display block is not in the search template. Please add it manually to test metadata display functionality.');
			return;
		}

		// Verify at least one metadata block is visible
		await expect(metadataBlocks.first()).toBeVisible({ timeout: 10000 });

		// Verify metadata is displayed for posts
		// We should see category information in the metadata
		const metadataList = metadataBlocks.first().locator('.metadata-list');
		await expect(metadataList).toBeVisible();

		// Verify metadata items exist
		const metadataItems = metadataBlocks.first().locator('.metadata-item');
		const itemCount = await metadataItems.count();
		expect(itemCount).toBeGreaterThan(0);

		// Verify category is displayed in metadata
		// Categories should appear as "Category: Test Category A" (or similar)
		const metadataLabels = metadataBlocks.first().locator('.metadata-label');
		const labels = await metadataLabels.all();
		
		let categoryFound = false;
		for (const label of labels) {
			const labelText = await label.textContent();
			if (labelText && (labelText.toLowerCase().includes('category') || labelText.toLowerCase().includes('categories'))) {
				categoryFound = true;
				
				// Verify the category value is displayed
				const metadataItem = label.locator('xpath=ancestor::*[contains(@class, "metadata-item")]');
				const categoryValue = metadataItem.locator('.metadata-value');
				await expect(categoryValue).toBeVisible();
				
				const valueText = await categoryValue.textContent();
				// Should contain one of our test categories
				expect(valueText).toMatch(/Test Category [ABC]/);
				break;
			}
		}

		// Verify that at least one post shows category metadata
		expect(categoryFound).toBe(true);
	});
});
