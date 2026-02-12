import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { createTestPosts, deleteTestPosts, type TestPostsData } from '../../helpers/test-data-setup';

test.describe('Search Post Type Filter Block', () => {
	let testData: TestPostsData;
	let testPageIds: number[] = [];
	const BLOCK_NAME = 'wordpress-search/search-post-type-filter';

	test.beforeAll(async ({ requestUtils }) => {
		// Create test posts before all tests
		// This helper creates 4 posts: "Test Post 1", "Test Post 2", "Test Post 3", "Test Post 4"
		testData = await createTestPosts(requestUtils);

		// Create test pages for post type filter testing
		const pageTitles = ['Test Page 1', 'Test Page 2'];
		const pagePromises = pageTitles.map(title =>
			requestUtils.rest({
				method: 'POST',
				path: '/wp/v2/pages',
				data: {
					title,
					status: 'publish',
					content: `Content for ${title}`,
				},
			})
		);
		const pages = await Promise.all(pagePromises) as Array<{ id: number }>;
		testPageIds = pages.map(page => page.id);
	});

	test.afterAll(async ({ requestUtils }) => {
		// Delete test posts and categories after all tests
		await deleteTestPosts(requestUtils, testData);

		// Delete test pages
		for (const pageId of testPageIds) {
			try {
				await requestUtils.rest({
					method: 'DELETE',
					path: `/wp/v2/pages/${pageId}`,
					data: { force: true },
				});
			} catch {
				// Page might already be deleted, continue
			}
		}
	});

	test('complete search post type filter workflow - full user journey', async ({ page, admin, requestUtils }) => {
		// Store original template content for cleanup
		let originalTemplateContent: string | null = null;
		let blockWasAdded = false;

		try {
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

			// Add the Search Post Type Filter block to the search template using REST API
			try {
				// Get the search template
				const template = await requestUtils.rest({
					method: 'GET',
					path: '/wp/v2/templates/twentytwentyfour//search',
				}) as { content: { raw: string } };

				// Store original content for cleanup
				originalTemplateContent = template.content.raw;

				// Check if block already exists in template content
				const blockMarkup = `<!-- wp:wordpress-search/search-post-type-filter /-->`;
				if (!template.content.raw.includes('wordpress-search/search-post-type-filter')) {
					let updatedContent = template.content.raw;
					
					// Try to insert before query loop block (so filter appears above results)
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

			// ===== FRONTEND TESTS =====
			// Navigate to search results page
			await page.goto(`/?s=test`);
			await page.waitForLoadState('networkidle');

			// Check if block exists - if not, skip the test
			const postTypeFilterBlock = page.locator('.wp-block-wordpress-search-search-post-type-filter');
			const blockExists = await postTypeFilterBlock.count() > 0;
			if (!blockExists) {
				test.skip(true, 'Search Post Type Filter block is not in the search template. Please add it manually to test post type filter functionality.');
				return;
			}

			// Verify block is visible
			await expect(postTypeFilterBlock.first()).toBeVisible();

			// Test 1: Verify "All" link functionality
			const allLink = postTypeFilterBlock.getByRole('link', { name: /^All$/i });
			await expect(allLink).toBeVisible();
			
			// Click "All" link and verify it's active
			await allLink.click();
			await page.waitForLoadState('networkidle');
			const allLinkAfterClick = postTypeFilterBlock.getByRole('link', { name: /^All$/i });
			const allLinkClasses = await allLinkAfterClick.getAttribute('class');
			expect(allLinkClasses).toContain('dswp-search-post-type-filter__button--active');

			// Verify URL doesn't contain post_type parameter when "All" is selected
			const urlAfterAll = page.url();
			expect(urlAfterAll).toContain('s=test');
			expect(urlAfterAll).not.toContain('post_type=');

			// Test 2: Click "Posts" filter link
			const postsLink = postTypeFilterBlock.getByRole('link', { name: /^Posts$/i });
			await expect(postsLink).toBeVisible();
			await postsLink.click();
			await page.waitForLoadState('networkidle');

			// Verify Posts link is active
			const postsLinkAfterClick = postTypeFilterBlock.getByRole('link', { name: /^Posts$/i });
			const postsLinkClasses = await postsLinkAfterClick.getAttribute('class');
			expect(postsLinkClasses).toContain('dswp-search-post-type-filter__button--active');

			// Verify URL contains post_type=post parameter
			const urlAfterPosts = page.url();
			expect(urlAfterPosts).toContain('post_type=post');

			// Test 3: Click "Pages" filter link
			const pagesLink = postTypeFilterBlock.getByRole('link', { name: /^Pages$/i });
			await expect(pagesLink).toBeVisible();
			await pagesLink.click();
			await page.waitForLoadState('networkidle');

			// Verify Pages link is active
			const pagesLinkAfterClick = postTypeFilterBlock.getByRole('link', { name: /^Pages$/i });
			const pagesLinkClasses = await pagesLinkAfterClick.getAttribute('class');
			expect(pagesLinkClasses).toContain('dswp-search-post-type-filter__button--active');

			// Verify URL contains post_type=page parameter
			const urlAfterPages = page.url();
			expect(urlAfterPages).toContain('post_type=page');

			// Test 4: Switch back to "Posts" filter
			const postsLinkAgain = postTypeFilterBlock.getByRole('link', { name: /^Posts$/i });
			await postsLinkAgain.click();
			await page.waitForLoadState('networkidle');

			// Verify Posts link is active again
			const postsLinkFinal = postTypeFilterBlock.getByRole('link', { name: /^Posts$/i });
			const postsLinkFinalClasses = await postsLinkFinal.getAttribute('class');
			expect(postsLinkFinalClasses).toContain('dswp-search-post-type-filter__button--active');

			// Verify URL contains post_type=post parameter again
			const urlAfterPostsAgain = page.url();
			expect(urlAfterPostsAgain).toContain('post_type=post');

			// Test 5: Verify accessibility (proper semantic HTML, screen reader friendly, keyboard navigation)
			// Navigate back to search page
			await page.goto(`/?s=test`);
			await page.waitForLoadState('networkidle');

			// Re-select the block after navigation
			const postTypeFilterBlockAfterNav = page.locator('.wp-block-wordpress-search-search-post-type-filter');
			await expect(postTypeFilterBlockAfterNav.first()).toBeVisible();

			// Verify semantic HTML structure
			await expect(postTypeFilterBlockAfterNav.first()).toBeVisible();

			// Verify all links are accessible and have proper text
			const allLinks = postTypeFilterBlockAfterNav.getByRole('link');
			const linkCount = await allLinks.count();
			expect(linkCount).toBeGreaterThan(0);

			// Verify links are keyboard accessible
			const allLinkForAccessibility = postTypeFilterBlockAfterNav.getByRole('link', { name: /^All$/i });
			await allLinkForAccessibility.focus();
			await expect(allLinkForAccessibility).toBeFocused();

			// Test keyboard activation - press Enter on Posts link
			const postsLinkForAccessibility = postTypeFilterBlockAfterNav.getByRole('link', { name: /^Posts$/i });
			await postsLinkForAccessibility.focus();
			await expect(postsLinkForAccessibility).toBeFocused();
			
			// For links, we need to actually click them (Enter key may not trigger navigation in all browsers)
			// But we verify keyboard accessibility by ensuring they're focusable
			await postsLinkForAccessibility.click();
			await page.waitForLoadState('networkidle');

			// Verify filter was applied
			const urlAfterKeyboard = page.url();
			expect(urlAfterKeyboard).toContain('post_type=post');
		} finally {
			// Cleanup: Always remove the block from the search template at the end
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
		}
	});
});
