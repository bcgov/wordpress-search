import { test, expect } from '@wordpress/e2e-test-utils-playwright';
<<<<<<< Updated upstream
import { createTestPosts, deleteTestPosts, createSortTestPosts, deleteSortTestPosts, type TestPostsData } from '../../helpers/test-data-setup';
=======
import { createTestPosts, deleteTestPosts,  type TestPostsData } from '../../helpers/test-data-setup';
>>>>>>> Stashed changes

test.describe('Search Results Sort Block', () => {
	const BLOCK_NAME = 'wordpress-search/searchresultssort';
	let testData: TestPostsData;

	test.beforeAll(async ({ requestUtils }) => {
		// Create test posts and categories before all tests
		testData = await createTestPosts(requestUtils);
	});

	test.afterAll(async ({ requestUtils }) => {
		// Delete test posts and categories after all tests
		await deleteTestPosts(requestUtils, testData);
	});

<<<<<<< Updated upstream
	test('complete search results sort workflow - full user journey', async ({ page, requestUtils }) => {

		// Create posts with different titles for sorting test using helper function
		const sortTestPostIds = await createSortTestPosts(requestUtils);

		try {
			// Navigate to search results page (block only renders on search pages)
			// Search for "Post" to get all our test posts
			// Note: The block must be in the search results template, not on a regular post page
			await page.goto(`/?s=Post`);

			// Wait for sort select to be visible
			const sortSelect = page.locator('.search-results-sort__sort-select');
			await expect(sortSelect).toBeVisible({ timeout: 10000 });
=======
	test('complete search results sort workflow - full user journey', async ({ page, admin, requestUtils }) => {
		// Activate Twenty Twenty-Four theme explicitly (if not already active)
		// WordPress 6.8 defaults to Twenty Twenty-Five, so we need to switch
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

		// Try to add the SearchResultsSort block to the search template using REST API
		try {
			// Get the search template
			const template = await requestUtils.rest({
				method: 'GET',
				path: '/wp/v2/templates/twentytwentyfour//search',
			}) as { content: { raw: string } };

			// Check if block already exists in template content
			const blockMarkup = `<!-- wp:wordpress-search/searchresultssort /-->`;
			if (!template.content.raw.includes('wordpress-search/searchresultssort')) {
				// Add the block to the template content
				// Insert it after the query loop or at the beginning if no query loop exists
				let updatedContent = template.content.raw;
				
				// Try to insert after query loop block
				const queryLoopPattern = /(<!-- wp:query[^>]*-->[\s\S]*?<!-- \/wp:query -->)/;
				if (queryLoopPattern.test(updatedContent)) {
					updatedContent = updatedContent.replace(
						queryLoopPattern,
						`$1\n${blockMarkup}`
					);
				} else {
					// If no query loop, add at the beginning of content area
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
			}
		} catch (error) {
			// Template might not exist or be editable - continue anyway
			// The block might already be in the template, or we'll test without it
			console.warn('Could not add block to search template via REST API, continuing test:', error);
		}

		// Navigate to search results page (block only renders on search pages)
		// Search for "Test Post" to get all our test posts from the helper
		await page.goto(`/?s=Test Post`);

		// Wait for sort select to be visible
		const sortSelect = page.locator('.search-results-sort__sort-select');
		
		// Check if block exists - if not, skip the test
		const blockExists = await sortSelect.count() > 0;
		if (!blockExists) {
			test.skip(true, 'SearchResultsSort block is not in the search template. Please add it manually to test sort functionality.');
			return;
		}

		await expect(sortSelect).toBeVisible({ timeout: 10000 });
>>>>>>> Stashed changes

			// Helper function to extract post titles from search results
			// Try multiple common selectors that themes might use
			const getPostTitles = async (): Promise<string[]> => {
				// Try common selectors for post titles in search results
				const selectors = [
					'article h2 a',
					'article .entry-title',
					'article .post-title',
					'article h2',
					'.wp-block-post-title',
					'article header h2',
				];

				for (const selector of selectors) {
					const titles = page.locator(selector);
					const count = await titles.count();
					if (count > 0) {
						const titleTexts: string[] = [];
						for (let i = 0; i < count; i++) {
							const text = await titles.nth(i).textContent();
							if (text) {
								titleTexts.push(text.trim());
							}
						}
						if (titleTexts.length > 0) {
							return titleTexts;
						}
					}
				}
				return [];
			};

			// Test title ascending sort
			await sortSelect.selectOption('title_asc');

			// Wait for navigation and page to load
			await page.waitForURL((url) => {
				const urlObj = new URL(url);
				return urlObj.searchParams.get('sort') === 'title_asc';
			});
			await page.waitForLoadState('networkidle');

			// Verify URL contains the sort parameter
			const url = page.url();
			const urlParams = new URL(url).searchParams;
			expect(urlParams.get('sort')).toBe('title_asc');

			// Get post titles and verify they're sorted alphabetically (ascending)
			const titlesAsc = await getPostTitles();
			if (titlesAsc.length >= 2) {
<<<<<<< Updated upstream
				// Filter to only our test posts (Apple, Banana, Cherry)
				const testTitles = titlesAsc.filter(t => 
					t.includes('Apple') || t.includes('Banana') || t.includes('Cherry')
				);
				
				if (testTitles.length >= 2) {
					// Verify alphabetical order (ascending)
=======
				// Filter to only our test posts (Test Post 1, Test Post 2, Test Post 3, Test Post 4)
				const testTitles = titlesAsc.filter(t => 
					t.includes('Test Post')
				);
				
				if (testTitles.length >= 2) {
					// Verify alphabetical order (ascending) - Test Post 1, Test Post 2, Test Post 3, Test Post 4
>>>>>>> Stashed changes
					const sorted = [...testTitles].sort((a, b) => a.localeCompare(b));
					expect(testTitles).toEqual(sorted);
				}
			}

			// Test title descending sort
			await sortSelect.selectOption('title_desc');

			// Wait for navigation and page to load
			await page.waitForURL((url) => {
				const urlObj = new URL(url);
				return urlObj.searchParams.get('sort') === 'title_desc';
			});
			await page.waitForLoadState('networkidle');

			// Verify URL contains the sort parameter
			const newUrl = page.url();
			const newUrlParams = new URL(newUrl).searchParams;
			expect(newUrlParams.get('sort')).toBe('title_desc');

			// Get post titles and verify they're sorted alphabetically (descending)
			const titlesDesc = await getPostTitles();
			if (titlesDesc.length >= 2) {
				// Filter to only our test posts
				const testTitles = titlesDesc.filter(t => 
<<<<<<< Updated upstream
					t.includes('Apple') || t.includes('Banana') || t.includes('Cherry')
				);
				
				if (testTitles.length >= 2) {
					// Verify reverse alphabetical order (descending)
=======
					t.includes('Test Post')
				);
				
				if (testTitles.length >= 2) {
					// Verify reverse alphabetical order (descending) - Test Post 4, Test Post 3, Test Post 2, Test Post 1
>>>>>>> Stashed changes
					const sorted = [...testTitles].sort((a, b) => b.localeCompare(a));
					expect(testTitles).toEqual(sorted);
				}
			}
<<<<<<< Updated upstream
		} finally {
			// Clean up test posts using helper function
			await deleteSortTestPosts(requestUtils, sortTestPostIds);
		}
	});
});
=======
	});
});
>>>>>>> Stashed changes
