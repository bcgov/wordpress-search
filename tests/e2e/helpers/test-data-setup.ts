import type { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 * Interface for test data returned by createTestPosts
 */
export interface TestPostsData {
	postIds: number[];
	categoryIds: number[];
}

/**
 * Creates test posts and categories using native WordPress REST API
 * 
 * Creates:
 * - 4 posts using default WordPress post type
 * - 3 categories: "Test Category A", "Test Category B", "Test Category C"
 * - Assigns categories to posts
 * 
 * @param requestUtils - RequestUtils instance for making API calls
 * @returns Promise resolving to object with postIds and categoryIds arrays
 */
export async function createTestPosts(
	requestUtils: RequestUtils
): Promise<TestPostsData> {
	const categoryIds: number[] = [];
	const postIds: number[] = [];

	// Create categories using native WordPress REST API
	const categoryNames = [ 'Test Category A', 'Test Category B', 'Test Category C' ];
	for ( const name of categoryNames ) {
		try {
			const category = await requestUtils.rest( {
				method: 'POST',
				path: '/wp/v2/categories',
				data: {
					name: name,
				},
			} ) as { id: number };
			categoryIds.push( category.id );
		} catch ( error ) {
			// Category might already exist, try to get it
			const existing = await requestUtils.rest( {
				method: 'GET',
				path: `/wp/v2/categories?search=${encodeURIComponent( name )}`,
			} ) as Array<{ id: number; name: string }>;
			if ( existing && existing.length > 0 ) {
				categoryIds.push( existing[0].id );
			}
		}
	}

	// Create posts using native WordPress REST API
	const postTitles = [ 'Test Post 1', 'Test Post 2', 'Test Post 3', 'Test Post 4' ];
	for ( let i = 0; i < postTitles.length; i++ ) {
		const post = await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/posts',
			data: {
				title: postTitles[i],
				content: `Content for ${postTitles[i]}`,
				status: 'publish',
				categories: categoryIds.length > 0 ? [ categoryIds[i % categoryIds.length] ] : [],
			},
		} ) as { id: number };
		postIds.push( post.id );
	}

	return {
		postIds,
		categoryIds,
	};
}

/**
 * Deletes test posts and categories using native WordPress REST API
 * 
 * @param requestUtils - RequestUtils instance for making API calls
 * @param testData - Object containing postIds and categoryIds to delete
 * @returns Promise that resolves when posts and categories are deleted
 */
export async function deleteTestPosts(
	requestUtils: RequestUtils,
	testData: TestPostsData
): Promise<void> {
	// Delete posts using native WordPress REST API
	for ( const postId of testData.postIds ) {
		await requestUtils.rest( {
			method: 'DELETE',
			path: `/wp/v2/posts/${postId}`,
			data: {
				force: true, // Permanently delete, don't move to trash
			},
		} );
	}

	// Delete categories using native WordPress REST API
	for ( const categoryId of testData.categoryIds ) {
		await requestUtils.rest( {
			method: 'DELETE',
			path: `/wp/v2/categories/${categoryId}`,
			data: {
				force: true, // Permanently delete
			},
		} );
	}
}
