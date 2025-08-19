# WordPress Search Plugin

WordPress wordpress-search plugin is a plugin that adds custom functionality to your WordPress site dev1.0.1.

## Features

### Search Functionality
- Custom search bar block with enhanced functionality
- Advanced taxonomy filtering
- Post type filtering
- Search results sorting
- Metadata and taxonomy search
- Search highlighting

### Filter Persistence (New Feature)
The plugin now includes comprehensive filter persistence across search queries:

- **Search Term Changes**: When users enter a new search term, all currently applied filters (taxonomy, post type, sort order, etc.) are automatically preserved
- **Clear Search**: The clear button (X) removes the search term but maintains all active filters
- **Filter Application**: When applying new filters, existing search terms and other filters are preserved
- **URL Preservation**: All filter parameters are maintained in the URL for bookmarking and sharing

#### How It Works
1. **Search Form Enhancement**: The search form automatically includes hidden inputs for all current filter parameters
2. **JavaScript Enhancement**: Client-side JavaScript ensures filter parameters are preserved during form submission
3. **Clear Button Behavior**: Modified to preserve filters when clearing search terms
4. **Component Integration**: All filter components (taxonomy, post type, sort) preserve other parameters when making changes

#### Supported Filter Types
- Taxonomy filters (e.g., categories, tags, custom taxonomies)
- Post type filters
- Sort parameters
- Search queries
- Any custom filter parameters

## Requirements

- WordPress 6.4.4 or higher
- PHP 7.4 or higher

## Installation

1. Upload the plugin files to the `/wp-content/plugins/wordpress-search` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Use the search blocks in your posts/pages

## Usage

### Search Bar Block
Add the search bar block to any page or post. It will automatically preserve any existing filters when performing new searches.

### Filter Blocks
- **Taxonomy Filter**: Filter results by taxonomy terms
- **Post Type Filter**: Filter by post types
- **Search Results Sort**: Sort results by various criteria
- **Active Filters**: Display and manage currently applied filters

### Filter Persistence Examples

#### Scenario 1: Search with Filters
1. User applies taxonomy filters (e.g., Category: News, Tag: Technology)
2. User enters search term "WordPress"
3. Results show posts matching "WordPress" with the applied filters
4. User changes search term to "Plugin"
5. **Result**: All taxonomy filters remain active, only search term changes

#### Scenario 2: Clear Search
1. User has search term "WordPress" with active filters
2. User clicks clear button (X)
3. **Result**: Search term is cleared, but all filters remain active

#### Scenario 3: Apply New Filters
1. User has search term "WordPress" with taxonomy filters
2. User changes post type filter to "Document"
3. **Result**: Search term and taxonomy filters are preserved, only post type changes

## Technical Details

### URL Parameter Handling
The plugin automatically handles URL parameter preservation:
- Search queries (`s` parameter)
- Taxonomy filters (`taxonomy_*` parameters)
- Post type filters (`post_type` parameter)
- Sort parameters (`sort`, `meta_sort`, `meta_field`)
- Custom filter parameters

### Form Enhancement
- Hidden inputs are automatically added for all current filter parameters
- JavaScript ensures parameter preservation during form submission
- Fallback handling for edge cases

### Component Integration
All filter components are designed to work together:
- Taxonomy filters preserve search terms and other filters
- Post type filters preserve search terms and taxonomy filters
- Sort options preserve all other parameters
- Active filters display current state and allow individual removal

## Support

For support and questions, please contact govwordpress@gov.bc.ca or visit the project page at https://github.com/bcgov/wordpress-search.

## License

Apache License Version 2.0 - see LICENSE file for details.
