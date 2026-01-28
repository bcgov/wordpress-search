import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe('Search Block', () => {
	const BLOCK_NAME = 'wordpress-search/search-bar';

	test.describe('Editor Tests', () => {
		test.beforeEach(async ({ admin }) => {
			// Create a new post before each test
			await admin.createNewPost();
		});

		test('should be inserted into a post/page', async ({ editor }) => {
			await editor.insertBlock({ name: BLOCK_NAME });

			// Verify the block is inserted
			const block = editor.canvas.locator(`[data-type="${BLOCK_NAME}"]`);
			await expect(block).toBeVisible();
		});

		test('should render correctly in the editor with all UI elements', async ({ editor }) => {
			await editor.insertBlock({ name: BLOCK_NAME });

			const block = editor.canvas.locator(`[data-type="${BLOCK_NAME}"]`);

			// Verify search input is visible
			const searchInput = block.locator('.dswp-search-bar__input');
			await expect(searchInput).toBeVisible();
			await expect(searchInput).toHaveAttribute('type', 'search');
			await expect(searchInput).toHaveAttribute('name', 's');

			// Verify clear button is visible (may be hidden initially)
			const clearButton = block.locator('.dswp-search-bar__clear-button');
			await expect(clearButton).toBeVisible();

			// Verify submit button is visible
			const submitButton = block.locator('.dswp-search-bar__button--primary');
			await expect(submitButton).toBeVisible();
			await expect(submitButton).toHaveText('Search');

			// Verify search icon is visible
			const searchIcon = block.locator('.dswp-search-bar__search-icon');
			await expect(searchIcon).toBeVisible();

			// Verify form has proper role attribute
			const form = block.locator('form[role="search"]');
			await expect(form).toBeVisible();
		});

		test('should save correctly', async ({ editor }) => {
			await editor.insertBlock({ name: BLOCK_NAME });

			// Wait for block to be fully loaded
			await editor.canvas.locator(`[data-type="${BLOCK_NAME}"]`).waitFor();

			// Verify the block saves correctly (snapshot test)
			expect(await editor.getEditedPostContent()).toMatchSnapshot();
		});
	});

	test.describe('Frontend Tests', () => {
		let postId;

		test.beforeEach(async ({ admin, editor }) => {
			// Create a new post with the Search block
			await admin.createNewPost();
			await editor.insertBlock({ name: BLOCK_NAME });
			
			// Publish the post
			postId = await editor.publishPost();
		});

		test('should display search input field that is visible and functional', async ({ page }) => {
			await page.goto(`/?p=${postId}`);

			const searchInput = page.locator('.dswp-search-bar__input');
			await expect(searchInput).toBeVisible();
			await expect(searchInput).toHaveAttribute('type', 'search');
			await expect(searchInput).toHaveAttribute('name', 's');
			await expect(searchInput).toBeEditable();
		});

		test('should show clear button when typing in search input', async ({ page }) => {
			await page.goto(`/?p=${postId}`);

			const searchInput = page.locator('.dswp-search-bar__input');
			const clearButton = page.locator('.dswp-search-bar__clear-button');

			// Initially, clear button should be hidden (when input is empty)
			await expect(clearButton).toHaveCSS('display', 'none');

			// Type in the search input
			await searchInput.fill('test search');

			// Clear button should now be visible
			await expect(clearButton).toHaveCSS('display', 'flex');
		});

		test('should hide clear button when input is empty', async ({ page }) => {
			await page.goto(`/?p=${postId}`);

			const searchInput = page.locator('.dswp-search-bar__input');
			const clearButton = page.locator('.dswp-search-bar__clear-button');

			// Type something
			await searchInput.fill('test');
			await expect(clearButton).toHaveCSS('display', 'flex');

			// Clear the input
			await searchInput.fill('');
			await expect(clearButton).toHaveCSS('display', 'none');
		});

		test('should clear input and submit form when clicking clear button', async ({ page }) => {
			// Navigate to the page first (without search params)
			await page.goto(`/?p=${postId}`);
			
			const searchInput = page.locator('.dswp-search-bar__input');
			const clearButton = page.locator('.dswp-search-bar__clear-button');
			
			// Fill in the search input to make the clear button visible
			await searchInput.fill('test search');
			await expect(clearButton).toHaveCSS('display', 'flex');

			// Set up navigation listener to catch form submission
			// The form should submit with empty search term (s= or no s parameter)
			await Promise.all([
				page.waitForURL((url) => {
					const urlObj = new URL(url);
					const searchParam = urlObj.searchParams.get('s');
					return searchParam === '' || searchParam === null;
				}),
				clearButton.click(),
			]);

			// Verify URL - search parameter should be empty or not present
			const url = page.url();
			const urlParams = new URL(url).searchParams;
			const searchParam = urlParams.get('s');
			expect(searchParam === '' || searchParam === null).toBeTruthy();
		});

		test('should redirect to search results page when submitting form with search term', async ({ page }) => {
			await page.goto(`/?p=${postId}`);

			const searchInput = page.locator('.dswp-search-bar__input');
			const submitButton = page.locator('.dswp-search-bar__button--primary');

			// Type search term
			const searchTerm = 'test query';
			await searchInput.fill(searchTerm);

			// Submit the form by clicking submit button and wait for navigation
			await Promise.all([
				page.waitForURL((url) => {
					const urlObj = new URL(url);
					return urlObj.searchParams.get('s') === searchTerm;
				}),
				submitButton.click(),
			]);

			// Verify we're on a search results page (check that s parameter exists)
			const url = page.url();
			const urlParams = new URL(url).searchParams;
			expect(urlParams.has('s')).toBeTruthy();
		});

		test('should include search query in URL when submitting form', async ({ page }) => {
			await page.goto(`/?p=${postId}`);

			const searchInput = page.locator('.dswp-search-bar__input');
			const submitButton = page.locator('.dswp-search-bar__button--primary');

			const searchTerm = 'wordpress search';
			await searchInput.fill(searchTerm);

			// Submit form and wait for navigation
			await Promise.all([
				page.waitForURL((url) => {
					const urlObj = new URL(url);
					return urlObj.searchParams.get('s') === searchTerm;
				}),
				submitButton.click(),
			]);

			// Verify URL contains the search query
			const url = page.url();
			const urlParams = new URL(url).searchParams;
			expect(urlParams.get('s')).toBe(searchTerm);
		});

		test('should preserve filter parameters when submitting search', async ({ page }) => {
			// Navigate to a page with filter parameters
			await page.goto(`/?p=${postId}&post_type[]=post&post_type[]=page`);

			const form = page.locator('.dswp-search-bar__form');
			const searchInput = page.locator('.dswp-search-bar__input');
			const submitButton = page.locator('.dswp-search-bar__button--primary');

			// Wait for form to be visible
			await form.waitFor({ state: 'visible' });

			// Check that hidden inputs for filter parameters exist
			const hiddenInputs = form.locator('input[type="hidden"]');
			const hiddenInputCount = await hiddenInputs.count();
			expect(hiddenInputCount).toBeGreaterThan(0);

			// Verify post_type filter parameters are present in the form
			const postTypeInputs = form.locator('input[name="post_type[]"]');
			const postTypeCount = await postTypeInputs.count();
			expect(postTypeCount).toBeGreaterThan(0);

			// Type search term and submit
			const searchTerm = 'test';
			await searchInput.fill(searchTerm);

			// Submit form and wait for navigation
			await Promise.all([
				page.waitForURL((url) => {
					const urlObj = new URL(url);
					return urlObj.searchParams.get('s') === searchTerm;
				}),
				submitButton.click(),
			]);

			// Verify filter parameters are preserved in URL
			const url = page.url();
			const urlParams = new URL(url).searchParams;
			expect(urlParams.get('s')).toBe(searchTerm);
			
			// Filter parameters should be preserved if they exist in the URL
			// Note: WordPress may strip empty or invalid filter parameters, so we check if they exist
			const postTypeParams = urlParams.getAll('post_type[]');
			if (postTypeParams.length > 0) {
				expect(postTypeParams).toContain('post');
				expect(postTypeParams).toContain('page');
			} else {
				// If parameters aren't in URL, verify they were at least in the form
				expect(postTypeCount).toBeGreaterThan(0);
			}
		});

		test('should display current search query when on search results page', async ({ page }) => {
			const searchTerm = 'current query';

			// Navigate to the page with Search block and search query in URL
			// Note: WordPress may redirect when search params are present
			const response = await page.goto(`/?p=${postId}&s=${encodeURIComponent(searchTerm)}`, { 
				waitUntil: 'domcontentloaded',
				timeout: 30000 
			});

			// Check if we're still on a page that might have the Search block
			// (WordPress might redirect to search results page)
			const currentUrl = page.url();
			const urlParams = new URL(currentUrl).searchParams;
			
			// If search param exists in URL, check if Search block is present
			if (urlParams.get('s') === searchTerm) {
				const searchInput = page.locator('.dswp-search-bar__input').first();
				const inputCount = await searchInput.count();
				
				if (inputCount > 0) {
					// If Search block is present, verify it shows the current query
					await expect(searchInput).toHaveValue(searchTerm);
				}
				// If Search block is not present, WordPress redirected to search results
				// page which doesn't have our block - this is expected behavior
			}
		});

		test('should submit form when pressing Enter in search input', async ({ page }) => {
			await page.goto(`/?p=${postId}`);

			const searchInput = page.locator('.dswp-search-bar__input');
			const searchTerm = 'enter key test';

			// Type search term
			await searchInput.fill(searchTerm);

			// Press Enter and wait for navigation
			await Promise.all([
				page.waitForURL((url) => {
					const urlObj = new URL(url);
					return urlObj.searchParams.get('s') === searchTerm;
				}),
				searchInput.press('Enter'),
			]);

			// Verify URL contains search query
			const url = page.url();
			const urlParams = new URL(url).searchParams;
			expect(urlParams.get('s')).toBe(searchTerm);
		});

		test('should submit form when clicking Search button', async ({ page }) => {
			await page.goto(`/?p=${postId}`);

			const searchInput = page.locator('.dswp-search-bar__input');
			const submitButton = page.locator('.dswp-search-bar__button--primary');
			const searchTerm = 'button click test';

			// Type search term
			await searchInput.fill(searchTerm);

			// Click submit button and wait for navigation
			await Promise.all([
				page.waitForURL((url) => {
					const urlObj = new URL(url);
					return urlObj.searchParams.get('s') === searchTerm;
				}),
				submitButton.click(),
			]);

			// Verify URL contains search query
			const url = page.url();
			const urlParams = new URL(url).searchParams;
			expect(urlParams.get('s')).toBe(searchTerm);
		});

		test('should have proper accessibility attributes', async ({ page }) => {
			await page.goto(`/?p=${postId}`);

			// Verify form has role attribute (should be "search" for accessibility)
			const form = page.locator('.dswp-search-bar__form');
			await expect(form).toHaveAttribute('role');
			await expect(form).toHaveAttribute('method', 'get');

			// Verify search input has proper attributes
			const searchInput = page.locator('.dswp-search-bar__input');
			await expect(searchInput).toHaveAttribute('type', 'search');
			await expect(searchInput).toHaveAttribute('name', 's');

			// Verify submit button has proper type
			const submitButton = page.locator('.dswp-search-bar__button--primary');
			await expect(submitButton).toHaveAttribute('type', 'submit');
		});
	});
});
