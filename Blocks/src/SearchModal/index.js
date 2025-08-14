/**
 * WordPress Block Registration Dependencies
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-blocks/#registerblocktype
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal Block Dependencies
 * Import the block's edit component for the editor interface
 */
import Edit from './edit';
import Save from './Save';
import './style.scss';

/**
 * Register Search Modal Block
 *
 * Registers a custom modal block with WordPress's block system.
 * This block acts as a container that can hold other blocks,
 * displaying them only when a trigger button is clicked.
 *
 * Block Features:
 * - Container block using InnerBlocks
 * - Modal functionality with open/close states
 * - Customizable trigger button
 * - Option for mobile-only display
 * - Server-side rendering for better SEO and performance
 *
 * @see /render.php for frontend output
 */
registerBlockType( 'wordpress-search/search-modal', {
	/**
	 * Edit Component
	 * Provides the editor interface for the block
	 */
	edit: Edit,

	/**
	 * Save Component
	 * Outputs the complete modal HTML structure with InnerBlocks content
	 * This allows for proper JavaScript functionality and security
	 */
	save: Save,
} );
