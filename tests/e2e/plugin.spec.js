import { test, expect, RequestUtils } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Plugin', () => {

    test('Plugin is active', async ({admin, page}) => {
        await admin.visitAdminPage( 'plugins.php' );
        await expect(page.getByLabel('Deactivate WordPress Search')).toContainText('Deactivate');
    })
});