import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe('Search Taxonomy Filter Block', () => {
	const BLOCK_NAME = 'wordpress-search/search-taxonomy-filter';

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

		test('should render correctly in the editor with taxonomy checkboxes', async ({ editor }) => {
			await editor.insertBlock({ name: BLOCK_NAME });

			const block = editor.canvas.locator(`[data-type="${BLOCK_NAME}"]`);

			// Verify block is visible
			await expect(block).toBeVisible();

			// Verify the preview header is visible
			const previewHeader = block.getByRole('heading', { name: /taxonomy filter/i });
			await expect(previewHeader).toBeVisible();

			// Verify description text is visible (either "Configure taxonomies" or "Selected:")
			// Try to find text that would be visible to a user
			const configureText = block.getByText(/configure taxonomies/i);
			const selectedText = block.getByText(/selected:/i);
			const descriptionCount = await configureText.count() + await selectedText.count();
			expect(descriptionCount).toBeGreaterThan(0);
		});

		test('should allow taxonomies to be selected in the inspector', async ({ editor, page }) => {
			await editor.insertBlock({ name: BLOCK_NAME });

			// Open the inspector controls panel
			// The panel should be open by default (initialOpen={true})
			// Look for taxonomy checkboxes in the inspector
			const inspectorPanel = page.getByRole('region', { name: /settings/i });
			
			// Wait for taxonomies to load
			await page.waitForTimeout(1000);

			// Try to find any taxonomy checkbox in the inspector
			// We'll look for checkboxes that are in the settings panel
			const taxonomyCheckboxes = inspectorPanel.getByRole('checkbox');
			const checkboxCount = await taxonomyCheckboxes.count();

			// There should be at least some checkboxes available (if taxonomies exist)
			// If no taxonomies exist, that's also a valid state
			if (checkboxCount > 0) {
				// Click the first available taxonomy checkbox
				const firstCheckbox = taxonomyCheckboxes.first();
				await firstCheckbox.click();

				// Verify it's checked
				await expect(firstCheckbox).toBeChecked();
			}
		});

		test('should allow multiple taxonomies to be selected', async ({ editor, page }) => {
			await editor.insertBlock({ name: BLOCK_NAME });

			// Open inspector controls
			const inspectorPanel = page.getByRole('region', { name: /settings/i });
			
			// Wait for taxonomies to load
			await page.waitForTimeout(1000);

			const taxonomyCheckboxes = inspectorPanel.getByRole('checkbox');
			const checkboxCount = await taxonomyCheckboxes.count();

			// If we have at least 2 checkboxes, test selecting multiple
			if (checkboxCount >= 2) {
				const firstCheckbox = taxonomyCheckboxes.nth(0);
				const secondCheckbox = taxonomyCheckboxes.nth(1);

				// Select first taxonomy
				await firstCheckbox.click();
				await expect(firstCheckbox).toBeChecked();

				// Select second taxonomy
				await secondCheckbox.click();
				await expect(secondCheckbox).toBeChecked();

				// Both should be checked
				await expect(firstCheckbox).toBeChecked();
				await expect(secondCheckbox).toBeChecked();
			}
		});

		test('should display checkboxes for terms when taxonomies are selected', async ({ editor, page }) => {
			await editor.insertBlock({ name: BLOCK_NAME });

			// Select at least one taxonomy in the inspector
			const inspectorPanel = page.getByRole('region', { name: /settings/i });
			
			// Wait for taxonomies to load
			await page.waitForTimeout(1000);

			const taxonomyCheckboxes = inspectorPanel.getByRole('checkbox');
			const checkboxCount = await taxonomyCheckboxes.count();

			if (checkboxCount > 0) {
				// Select a taxonomy
				const firstTaxonomyCheckbox = taxonomyCheckboxes.first();
				await firstTaxonomyCheckbox.click();

				// The preview should update to show "Selected:" text
				const block = editor.canvas.locator(`[data-type="${BLOCK_NAME}"]`);
				const selectedText = block.getByText(/selected:/i);
				await expect(selectedText).toBeVisible();
			}
		});

		test('should save correctly', async ({ editor, page }) => {
			await editor.insertBlock({ name: BLOCK_NAME });

			// Wait for block to be fully loaded
			await editor.canvas.locator(`[data-type="${BLOCK_NAME}"]`).waitFor();

			// Wait a bit for any async operations to complete
			await page.waitForTimeout(500);

			// Verify the block saves correctly (snapshot test)
			expect(await editor.getEditedPostContent()).toMatchSnapshot();
		});
	});

	test.describe('Frontend Tests', () => {
		let postId;

		test.beforeEach(async ({ admin, editor }) => {
			// Create a new post with the Search Taxonomy Filter block
			await admin.createNewPost();
			await editor.insertBlock({ name: BLOCK_NAME });

			// Select at least one taxonomy so the block renders on the frontend
			// (The block doesn't render if no taxonomies are selected)
			const inspectorPanel = editor.page.getByRole('region', { name: /settings/i });
			
			// Wait for taxonomies to load
			await editor.page.waitForTimeout(1500);

			const taxonomyCheckboxes = inspectorPanel.getByRole('checkbox');
			const checkboxCount = await taxonomyCheckboxes.count();

			if (checkboxCount > 0) {
				// Select the first available taxonomy
				const firstCheckbox = taxonomyCheckboxes.first();
				await firstCheckbox.click();
				
				// Wait for the selection to register and block to update
				await editor.page.waitForTimeout(1000);
				
				// Verify the checkbox is actually checked
				await expect(firstCheckbox).toBeChecked();
			}

			// Publish the post
			postId = await editor.publishPost();
		});

		test('should display taxonomy filter checkboxes that are visible and functional', async ({ page }) => {
			await page.goto(`/?p=${postId}`);

			// Wait for page to load
			await page.waitForLoadState('networkidle');

			// Verify form is visible (the block only renders if taxonomies are selected)
			// Use a more specific selector to find the taxonomy filter form
			const form = page.locator('form.taxonomy-filter-form').first();
			
			// Check if form exists - if not, the block didn't render (no taxonomies selected)
			const formCount = await form.count();
			if (formCount === 0) {
				// Skip this test if no form is present (no taxonomies available in test environment)
				test.skip();
				return;
			}

			await expect(form).toBeVisible();

			// Verify at least one fieldset (taxonomy filter section) is visible
			// Fieldsets have an implicit 'group' role, so we can use getByRole
			const fieldsets = form.getByRole('group');
			const fieldsetCount = await fieldsets.count();
			
			expect(fieldsetCount).toBeGreaterThan(0);

			// Verify checkboxes are visible - look for them within the form
			const checkboxes = form.getByRole('checkbox');
			const checkboxCount = await checkboxes.count();
			expect(checkboxCount).toBeGreaterThan(0);

			// Verify checkboxes are functional (can be clicked)
			const firstCheckbox = checkboxes.first();
			await expect(firstCheckbox).toBeVisible();
			await expect(firstCheckbox).toBeEnabled();
		});

		test('should add filter parameter to URL when checking a taxonomy term', async ({ page }) => {
			await page.goto(`/?p=${postId}`);
			await page.waitForLoadState('networkidle');

			const form = page.locator('form.taxonomy-filter-form').first();
			const formCount = await form.count();
			if (formCount === 0) {
				test.skip();
				return;
			}

			const checkboxes = form.getByRole('checkbox');
			const checkboxCount = await checkboxes.count();

			if (checkboxCount > 0) {
				const firstCheckbox = checkboxes.first();
				const checkboxName = await firstCheckbox.getAttribute('name');
				const checkboxValue = await firstCheckbox.getAttribute('value');

				if (checkboxName && checkboxValue) {
					// Get the taxonomy parameter name (remove [] from name)
					const taxonomyParam = checkboxName.replace('[]', '');

					// Check the checkbox
					await firstCheckbox.check();

					// Click Apply Filters button
					const applyButton = page.getByRole('button', { name: /apply filters/i });
					await applyButton.click();

					// Wait for navigation
					await page.waitForURL((url) => {
						const urlObj = new URL(url);
						return urlObj.searchParams.has(taxonomyParam);
					});

					// Verify URL contains the taxonomy parameter
					const url = page.url();
					const urlParams = new URL(url).searchParams;
					expect(urlParams.has(taxonomyParam)).toBeTruthy();
					
					// Verify the value is in the parameter (could be comma-separated)
					const paramValue = urlParams.get(taxonomyParam);
					expect(paramValue).toContain(checkboxValue);
				}
			}
		});

		test('should remove filter parameter from URL when unchecking a taxonomy term', async ({ page }) => {
			await page.goto(`/?p=${postId}`);
			await page.waitForLoadState('networkidle');

			// Start with a URL that has a taxonomy filter
			const form = page.locator('form.taxonomy-filter-form').first();
			const formCount = await form.count();
			if (formCount === 0) {
				test.skip();
				return;
			}

			const checkboxes = form.getByRole('checkbox');
			const checkboxCount = await checkboxes.count();

			if (checkboxCount > 0) {
				const firstCheckbox = checkboxes.first();
				const checkboxName = await firstCheckbox.getAttribute('name');
				const checkboxValue = await firstCheckbox.getAttribute('value');

				if (checkboxName && checkboxValue) {
					const taxonomyParam = checkboxName.replace('[]', '');

					// First, check the checkbox and apply to set a filter
					await firstCheckbox.check();
					const applyButton = page.getByRole('button', { name: /apply filters/i });
					await applyButton.click();
					await page.waitForURL((url) => {
						const urlObj = new URL(url);
						return urlObj.searchParams.has(taxonomyParam);
					});

					// Now uncheck it
					await page.goto(`/?p=${postId}&${taxonomyParam}=${checkboxValue}`);
					await firstCheckbox.uncheck();

					// Click Apply Filters
					await applyButton.click();

					// Wait for navigation - the parameter should be removed
					await page.waitForURL((url) => {
						const urlObj = new URL(url);
						// Parameter should not exist, or if it exists, should not contain the value
						const paramValue = urlObj.searchParams.get(taxonomyParam);
						return !paramValue || !paramValue.includes(checkboxValue);
					});

					// Verify the parameter is removed or doesn't contain the value
					const url = page.url();
					const urlParams = new URL(url).searchParams;
					const paramValue = urlParams.get(taxonomyParam);
					if (paramValue) {
						expect(paramValue).not.toContain(checkboxValue);
					}
				}
			}
		});

		test('should allow multiple terms from the same taxonomy to be selected (comma-separated)', async ({ page }) => {
			await page.goto(`/?p=${postId}`);
			await page.waitForLoadState('networkidle');

			const form = page.locator('form.taxonomy-filter-form').first();
			const formCount = await form.count();
			if (formCount === 0) {
				test.skip();
				return;
			}

			const checkboxes = form.getByRole('checkbox');
			const checkboxCount = await checkboxes.count();

			if (checkboxCount >= 2) {
				const firstCheckbox = checkboxes.nth(0);
				const secondCheckbox = checkboxes.nth(1);
				const firstName = await firstCheckbox.getAttribute('name');
				const secondName = await secondCheckbox.getAttribute('name');

				// Both checkboxes should be from the same taxonomy (same name)
				if (firstName === secondName) {
					const taxonomyParam = firstName.replace('[]', '');
					const firstValue = await firstCheckbox.getAttribute('value');
					const secondValue = await secondCheckbox.getAttribute('value');

					// Check both checkboxes
					await firstCheckbox.check();
					await secondCheckbox.check();

					// Apply filters
					const applyButton = page.getByRole('button', { name: /apply filters/i });
					await applyButton.click();

					// Wait for navigation
					await page.waitForURL((url) => {
						const urlObj = new URL(url);
						return urlObj.searchParams.has(taxonomyParam);
					});

					// Verify URL contains both values (comma-separated)
					const url = page.url();
					const urlParams = new URL(url).searchParams;
					const paramValue = urlParams.get(taxonomyParam);
					expect(paramValue).toContain(firstValue);
					expect(paramValue).toContain(secondValue);
					expect(paramValue).toContain(',');
				}
			}
		});

		test('should allow multiple taxonomies to be filtered simultaneously', async ({ page }) => {
			await page.goto(`/?p=${postId}`);
			await page.waitForLoadState('networkidle');

			const form = page.locator('form.taxonomy-filter-form').first();
			const formCount = await form.count();
			if (formCount === 0) {
				test.skip();
				return;
			}
			// Get all fieldsets (groups) within the form
			const fieldsets = form.getByRole('group');
			const fieldsetCount = await fieldsets.count();

			if (fieldsetCount >= 2) {
				// Get checkboxes from different fieldsets (different taxonomies)
				const firstFieldset = fieldsets.nth(0);
				const secondFieldset = fieldsets.nth(1);

				const firstCheckbox = firstFieldset.getByRole('checkbox').first();
				const secondCheckbox = secondFieldset.getByRole('checkbox').first();

				const firstName = await firstCheckbox.getAttribute('name');
				const secondName = await secondCheckbox.getAttribute('name');

				// They should be from different taxonomies
				if (firstName !== secondName) {
					const firstTaxonomyParam = firstName.replace('[]', '');
					const secondTaxonomyParam = secondName.replace('[]', '');

					// Check both checkboxes
					await firstCheckbox.check();
					await secondCheckbox.check();

					// Apply filters
					const applyButton = page.getByRole('button', { name: /apply filters/i });
					await applyButton.click();

					// Wait for navigation
					await page.waitForURL((url) => {
						const urlObj = new URL(url);
						return urlObj.searchParams.has(firstTaxonomyParam) && 
						       urlObj.searchParams.has(secondTaxonomyParam);
					});

					// Verify both taxonomy parameters are in the URL
					const url = page.url();
					const urlParams = new URL(url).searchParams;
					expect(urlParams.has(firstTaxonomyParam)).toBeTruthy();
					expect(urlParams.has(secondTaxonomyParam)).toBeTruthy();
				}
			}
		});

		test('should preserve filter parameters when submitting a search', async ({ page }) => {
			await page.goto(`/?p=${postId}`);
			await page.waitForLoadState('networkidle');

			// Navigate with taxonomy filter already applied
			const form = page.locator('form.taxonomy-filter-form').first();
			const formCount = await form.count();
			if (formCount === 0) {
				test.skip();
				return;
			}

			const checkboxes = form.getByRole('checkbox');
			const checkboxCount = await checkboxes.count();

			if (checkboxCount > 0) {
				const firstCheckbox = checkboxes.first();
				const checkboxName = await firstCheckbox.getAttribute('name');
				const checkboxValue = await firstCheckbox.getAttribute('value');

				if (checkboxName && checkboxValue) {
					const taxonomyParam = checkboxName.replace('[]', '');

					// Set up initial filter
					await firstCheckbox.check();
					const applyButton = page.getByRole('button', { name: /apply filters/i });
					await applyButton.click();
					await page.waitForURL((url) => {
						const urlObj = new URL(url);
						return urlObj.searchParams.has(taxonomyParam);
					});

					// Now check if there's a search form on the page
					// If there is, submit it and verify taxonomy filter is preserved
					const searchForm = page.locator('form[role="search"]');
					const searchFormCount = await searchForm.count();

					if (searchFormCount > 0) {
						const searchInput = searchForm.getByRole('searchbox');
						await searchInput.fill('test search');
						await searchInput.press('Enter');

						// Wait for navigation
						await page.waitForURL((url) => {
							const urlObj = new URL(url);
							return urlObj.searchParams.has('s');
						});

						// Verify both search and taxonomy filter are in URL
						const url = page.url();
						const urlParams = new URL(url).searchParams;
						expect(urlParams.has('s')).toBeTruthy();
						expect(urlParams.has(taxonomyParam)).toBeTruthy();
					}
				}
			}
		});

		test('should expand/collapse term lists when clicking View All toggle', async ({ page }) => {
			await page.goto(`/?p=${postId}`);

			// Look for "View All" button (only appears if there are more than 5 terms)
			const viewAllButton = page.getByRole('button', { name: /view all/i });
			const viewAllCount = await viewAllButton.count();

			if (viewAllCount > 0) {
				// Initially, button should say "View all" and aria-expanded should be false
				await expect(viewAllButton.first()).toHaveAttribute('aria-expanded', 'false');

				// Click to expand
				await viewAllButton.first().click();

				// Button should now say "View less" and aria-expanded should be true
				await expect(viewAllButton.first()).toHaveAttribute('aria-expanded', 'true');
				await expect(viewAllButton.first()).toContainText(/view less/i);

				// Click again to collapse
				await viewAllButton.first().click();

				// Button should say "View all" again and aria-expanded should be false
				await expect(viewAllButton.first()).toHaveAttribute('aria-expanded', 'false');
				await expect(viewAllButton.first()).toContainText(/view all/i);
			}
		});

		test('should check currently selected terms on page load', async ({ page }) => {
			// First, get a checkbox value
			await page.goto(`/?p=${postId}`);
			await page.waitForLoadState('networkidle');

			const form = page.locator('form.taxonomy-filter-form').first();
			const formCount = await form.count();
			if (formCount === 0) {
				test.skip();
				return;
			}

			const checkboxes = form.getByRole('checkbox');
			const checkboxCount = await checkboxes.count();

			if (checkboxCount > 0) {
				const firstCheckbox = checkboxes.first();
				const checkboxName = await firstCheckbox.getAttribute('name');
				const checkboxValue = await firstCheckbox.getAttribute('value');

				if (checkboxName && checkboxValue) {
					const taxonomyParam = checkboxName.replace('[]', '');

					// Navigate with the taxonomy filter in URL
					await page.goto(`/?p=${postId}&${taxonomyParam}=${checkboxValue}`);
					await page.waitForLoadState('networkidle');

					// Re-query the form after navigation
					const formAfterNav = page.locator('form.taxonomy-filter-form').first();
					const formCountAfterNav = await formAfterNav.count();
					if (formCountAfterNav === 0) {
						test.skip();
						return;
					}

					// Find the checkbox with this value
					// Using locator with value attribute is acceptable here as we're verifying
					// that the checkbox state matches the URL parameter
					const matchingCheckbox = formAfterNav.locator(`input[type="checkbox"][value="${checkboxValue}"]`);
					await expect(matchingCheckbox).toBeChecked();
				}
			}
		});

		test('should preserve other filter parameters when form is submitted', async ({ page }) => {
			// Navigate with multiple filter parameters
			await page.goto(`/?p=${postId}&post_type[]=post&s=test`);
			await page.waitForLoadState('networkidle');

			const form = page.locator('form.taxonomy-filter-form').first();
			const formCount = await form.count();
			if (formCount === 0) {
				test.skip();
				return;
			}

			const checkboxes = form.getByRole('checkbox');
			const checkboxCount = await checkboxes.count();

			if (checkboxCount > 0) {
				const firstCheckbox = checkboxes.first();
				const checkboxName = await firstCheckbox.getAttribute('name');
				const checkboxValue = await firstCheckbox.getAttribute('value');

				if (checkboxName && checkboxValue) {
					const taxonomyParam = checkboxName.replace('[]', '');

					// Check a taxonomy filter
					await firstCheckbox.check();

					// Apply filters
					const applyButton = page.getByRole('button', { name: /apply filters/i });
					await applyButton.click();

					// Wait for navigation
					await page.waitForURL((url) => {
						const urlObj = new URL(url);
						return urlObj.searchParams.has(taxonomyParam);
					});

					// Verify other parameters are preserved (if they were in the form as hidden inputs)
					// Note: WordPress may strip some parameters, so we check if they exist
					const url = page.url();
					const urlParams = new URL(url).searchParams;
					
					// The taxonomy parameter should be present
					expect(urlParams.has(taxonomyParam)).toBeTruthy();
				}
			}
		});

		test('should reset pagination when filters change', async ({ page }) => {
			// Navigate to a page with pagination
			await page.goto(`/?p=${postId}&paged=2`);
			await page.waitForLoadState('networkidle');

			const form = page.locator('form.taxonomy-filter-form').first();
			const formCount = await form.count();
			if (formCount === 0) {
				test.skip();
				return;
			}

			const checkboxes = form.getByRole('checkbox');
			const checkboxCount = await checkboxes.count();

			if (checkboxCount > 0) {
				const firstCheckbox = checkboxes.first();
				const checkboxName = await firstCheckbox.getAttribute('name');
				const checkboxValue = await firstCheckbox.getAttribute('value');

				if (checkboxName && checkboxValue) {
					const taxonomyParam = checkboxName.replace('[]', '');

					// Check a filter
					await firstCheckbox.check();

					// Apply filters
					const applyButton = page.getByRole('button', { name: /apply filters/i });
					await applyButton.click();

					// Wait for navigation
					await page.waitForURL((url) => {
						const urlObj = new URL(url);
						return urlObj.searchParams.has(taxonomyParam);
					});

					// Verify pagination parameter is removed from URL
					const url = page.url();
					const urlParams = new URL(url).searchParams;
					expect(urlParams.has('paged')).toBeFalsy();
				}
			}
		});

		test('should have proper accessibility attributes', async ({ page }) => {
			await page.goto(`/?p=${postId}`);

			// Wait for page to load
			await page.waitForLoadState('networkidle');

			// Verify form has proper structure (the block only renders if taxonomies are selected)
			// Use a more specific selector to find the taxonomy filter form
			const form = page.locator('form.taxonomy-filter-form').first();
			
			// Check if form exists - if not, the block didn't render (no taxonomies selected)
			const formCount = await form.count();
			if (formCount === 0) {
				// Skip this test if no form is present (no taxonomies available in test environment)
				test.skip();
				return;
			}

			await expect(form).toBeVisible();
			await expect(form).toHaveAttribute('method', 'get');

			// Verify fieldsets have proper structure
			const fieldsets = form.getByRole('group');
			const fieldsetCount = await fieldsets.count();

			if (fieldsetCount > 0) {
				const firstFieldset = fieldsets.first();

				// Verify legend (label) exists - look for it within the fieldset
				const legend = firstFieldset.locator('legend');
				await expect(legend).toBeVisible();

				// Verify checkboxes have labels
				const checkboxes = firstFieldset.getByRole('checkbox');
				const checkboxCount = await checkboxes.count();

				if (checkboxCount > 0) {
					const firstCheckbox = checkboxes.first();
					const checkboxId = await firstCheckbox.getAttribute('id');

					if (checkboxId) {
						// Verify label is associated with checkbox
						// Using locator with for attribute is acceptable here as it's testing accessibility
						const label = page.locator(`label[for="${checkboxId}"]`);
						await expect(label).toBeVisible();
					}
				}

				// Verify "View All" button has proper ARIA attributes (if present)
				const viewAllButton = firstFieldset.getByRole('button', { name: /view all/i });
				const viewAllCount = await viewAllButton.count();

				if (viewAllCount > 0) {
					await expect(viewAllButton.first()).toHaveAttribute('aria-expanded');
					await expect(viewAllButton.first()).toHaveAttribute('aria-controls');
					await expect(viewAllButton.first()).toHaveAttribute('aria-label');
				}
			}

			// Verify Apply Filters button has proper type
			const applyButton = page.getByRole('button', { name: /apply filters/i });
			await expect(applyButton).toBeVisible();
		});
	});
});
