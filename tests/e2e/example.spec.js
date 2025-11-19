import { test, expect, RequestUtils } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Example', () => {

    test('Plugin is active', async ({admin, page}) => {
        await page.goto('/wp-admin');
        await page.getByLabel('Username or Email Address').fill('admin');
        await page.getByLabel('Password', { exact: true }).fill('password');
        await page.getByRole('button', { name: 'Log In' }).click();

        await admin.visitAdminPage( 'plugins.php' );
        await expect(page.getByLabel('Deactivate WordPress Search')).toContainText('Deactivate');
    })
});