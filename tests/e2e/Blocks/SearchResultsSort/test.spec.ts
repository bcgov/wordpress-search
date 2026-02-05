import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { createTestPosts, deleteTestPosts, createSortTestPosts, deleteSortTestPosts, type TestPostsData } from '../../helpers/test-data-setup';

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
				// Filter to only our test posts (Apple, Banana, Cherry)
				const testTitles = titlesAsc.filter(t => 
					t.includes('Apple') || t.includes('Banana') || t.includes('Cherry')
				);
				
				if (testTitles.length >= 2) {
					// Verify alphabetical order (ascending)
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
					t.includes('Apple') || t.includes('Banana') || t.includes('Cherry')
				);
				
				if (testTitles.length >= 2) {
					// Verify reverse alphabetical order (descending)
					const sorted = [...testTitles].sort((a, b) => b.localeCompare(a));
					expect(testTitles).toEqual(sorted);
				}
			}
		} finally {
			// Clean up test posts using helper function
			await deleteSortTestPosts(requestUtils, sortTestPostIds);
		}
	});
});
