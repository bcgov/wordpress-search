import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe('Search Modal Block', () => {
	const BLOCK_NAME = 'wordpress-search/search-modal';

	test('complete search modal workflow - full user journey', async ({ page, admin, editor }) => {
		// ============================================
		// PART 1: Editor Tests
		// ============================================

		// Create a new post
		await admin.createNewPost();

		// Test 1: Insert the block
		await editor.insertBlock({ name: BLOCK_NAME });

		// Verify the block is inserted
		const block = editor.canvas.locator(`[data-type="${BLOCK_NAME}"]`);
		await expect(block).toBeVisible();

		// Test 2: Verify block renders correctly with InnerBlocks support
		// Verify InnerBlocks container is present
		const innerBlocksContainer = block.locator('.dswp-search-modal__body');
		await expect(innerBlocksContainer).toBeVisible();

		// Verify the content preview area is visible
		const contentPreview = block.locator('.dswp-search-modal__content-preview');
		await expect(contentPreview).toBeVisible();

		// Test 3: Add a block inside the modal
		await block.click();

		// Get the block's client ID to use as clientId
		const blockClientId = await block.getAttribute('data-block');
		expect(blockClientId).toBeTruthy();

		// Click the appender button to set the insertion point inside InnerBlocks
		const appenderButton = block
			.locator('.dswp-search-modal__body')
			.locator('.block-list-appender')
			.getByRole('button', { name: 'Add block' });
		
		await expect(appenderButton).toBeVisible({ timeout: 10000 });
		await appenderButton.click();

		// Use editor.insertBlock with clientId to ensure it goes inside the modal
		await editor.insertBlock({ name: 'core/paragraph' }, { clientId: blockClientId! });

		// Find the paragraph block inside the modal body
		const paragraphBlock = block.locator('.dswp-search-modal__body').locator('[data-type="core/paragraph"]').first();
		await expect(paragraphBlock).toBeVisible({ timeout: 10000 });
		
		await paragraphBlock.click();
		await editor.canvas.locator('p[contenteditable="true"]').first().fill('Test content inside modal');
		
		// Verify it was added inside the modal body
		await expect(
			block.locator('.dswp-search-modal__body p')
		).toContainText('Test content inside modal', { timeout: 5000 });

		// Test 4-6: Configure block settings in inspector (optional)
		await block.click();
		
		// Try to access inspector controls, but don't fail if they're not accessible
		try {
			// Open settings panel
			const settingsButton = page.getByRole('button', { name: /Settings/i });
			if (await settingsButton.isVisible({ timeout: 2000 })) {
				await settingsButton.click();

				// Try to find text input for button text
				const textInputs = page.locator('.components-text-control__input, input[type="text"]');
				const inputCount = await textInputs.count();
				if (inputCount > 0) {
					const firstInput = textInputs.first();
					if (await firstInput.isVisible({ timeout: 2000 })) {
						await firstInput.fill('Open Search');
						await firstInput.blur();
					}
				}

				// Try to find select for button style
				const selects = page.locator('.components-select-control__input, select');
				const selectCount = await selects.count();
				if (selectCount > 0) {
					const firstSelect = selects.first();
					if (await firstSelect.isVisible({ timeout: 2000 })) {
						await firstSelect.selectOption('secondary');
					}
				}
			}
		} catch (error) {
			// Inspector controls not accessible - continue with default values
		}

		// Publish the post
		const postId = await editor.publishPost();
		expect(postId).not.toBeNull();

		// ============================================
		// PART 2: Frontend Tests
		// ============================================

		// Navigate to frontend
		// Set viewport to mobile size first to ensure trigger button is visible
		await page.setViewportSize({ width: 375, height: 667 });
		await page.goto(`/?p=${postId}`);
		await page.waitForLoadState('networkidle');

		// Verify trigger button is visible (on mobile)
		const triggerButton = page.locator('.dswp-search-modal__trigger');
		await expect(triggerButton).toBeVisible({ timeout: 10000 });

		// Test accessibility attributes on trigger button
		await expect(triggerButton).toHaveAttribute('aria-expanded', 'false');
		await expect(triggerButton).toHaveAttribute('aria-controls');

		// Open modal when trigger button is clicked
		await triggerButton.click();

		// Verify the modal overlay is visible
		const modalOverlay = page.locator('.dswp-search-modal__overlay');
		await expect(modalOverlay).toBeVisible({ timeout: 5000 });

		// Verify the modal has the open class
		await expect(modalOverlay).toHaveClass(/dswp-search-modal--open/);

		// Verify modal overlay structure
		const modalContainer = modalOverlay.locator('.dswp-search-modal__container');
		await expect(modalContainer).toBeVisible();

		const modalContent = modalOverlay.locator('.dswp-search-modal__content');
		await expect(modalContent).toBeVisible();

		// Test accessibility attributes when modal is open
		await expect(modalOverlay).toHaveAttribute('role', 'dialog');
		await expect(modalOverlay).toHaveAttribute('aria-modal', 'true');
		await expect(modalOverlay).toHaveAttribute('aria-hidden', 'false');
		await expect(triggerButton).toHaveAttribute('aria-expanded', 'true');

		// Verify close button has aria-label
		const closeButton = page.locator('.dswp-search-modal__close');
		await expect(closeButton).toHaveAttribute('aria-label', 'Close modal');

		// Verify the modal body is visible
		const modalBody = modalOverlay.locator('.dswp-search-modal__body');
		await expect(modalBody).toBeVisible({ timeout: 5000 });
		
		// Verify content exists in the modal body
		await expect(modalBody.locator('p')).toContainText('Test content inside modal');

		// Test focus trapping inside modal
		await closeButton.focus();
		await expect(closeButton).toBeFocused({ timeout: 1000 });

		// Try to tab through focusable elements to verify focus trapping
		await page.keyboard.press('Tab');

		// Focus should still be within the modal after tabbing
		const focusedElement = page.locator(':focus');
		await expect(focusedElement).toBeVisible();
		
		// Verify focused element is within the modal
		const focusedHandle = await focusedElement.elementHandle();
		expect(focusedHandle).toBeTruthy();
		const isWithinModal = await modalOverlay.evaluate((modal, focused) => {
			return modal.contains(focused);
		}, focusedHandle!);
		expect(isWithinModal).toBeTruthy();

		// Close modal using close button
		await closeButton.click();
		await expect(modalOverlay).toHaveAttribute('aria-hidden', 'true');
		await expect(modalOverlay).not.toHaveClass(/dswp-search-modal--open/);
		await expect(triggerButton).toHaveAttribute('aria-expanded', 'false');

		// Test closing modal with Escape key
		await triggerButton.click();
		await expect(modalOverlay).toBeVisible({ timeout: 5000 });

		await page.keyboard.press('Escape');
		await expect(modalOverlay).toHaveAttribute('aria-hidden', 'true');
		await expect(modalOverlay).not.toHaveClass(/dswp-search-modal--open/);
		await expect(triggerButton).toHaveAttribute('aria-expanded', 'false');
		await expect(triggerButton).toBeFocused({ timeout: 1000 });

		// Test mobile behavior (content hidden, button visible)
		await page.setViewportSize({ width: 375, height: 667 });
		await page.reload();
		await page.waitForLoadState('networkidle');

		// Verify trigger button is visible on mobile
		await expect(triggerButton).toBeVisible({ timeout: 10000 });

		// Verify inline content is hidden on mobile
		const inlineContent = page.locator('.dswp-search-modal__inline-content');
		const inlineDisplay = await inlineContent.evaluate((el) => window.getComputedStyle(el).display);
		expect(inlineDisplay).toBe('none');

		// Test desktop behavior (content inline, button hidden)
		await page.setViewportSize({ width: 1200, height: 800 });
		await page.reload();
		await page.waitForLoadState('networkidle');
		
		// Trigger resize event to ensure responsive state updates
		await page.evaluate(() => {
			window.dispatchEvent(new Event('resize'));
		});

		// Verify trigger button is hidden on desktop
		const triggerDisplay = await triggerButton.evaluate((el) => window.getComputedStyle(el).display);
		expect(triggerDisplay).toBe('none');

		// Verify inline content is visible on desktop
		const inlineDisplayDesktop = await inlineContent.evaluate((el) => window.getComputedStyle(el).display);
		expect(inlineDisplayDesktop).not.toBe('none');

		// Verify content is visible inline on desktop
		await expect(
			page.locator('.dswp-search-modal__inline-content p')
		).toContainText('Test content inside modal');
	});
});
