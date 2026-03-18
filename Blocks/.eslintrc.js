/**
 * Local ESLint overrides for this package.
 *
 * `wp-scripts lint-js` enables `eslint-plugin-import` with a TypeScript-based
 * resolver, which is producing noisy resolver failures in this JS-only
 * project. We force the import resolver back to the Node resolver.
 */
module.exports = {
	env: {
		browser: true,
		es6: true,
		node: true,
		jest: true,
	},
	parserOptions: {
		ecmaVersion: 2020,
		sourceType: 'module',
		ecmaFeatures: {
			jsx: true,
		},
	},
	settings: {
		'import/resolver': {
			node: {
				extensions: [ '.js', '.jsx', '.json', '.scss', '.css' ],
			},
		},
	},
	rules: {
		// These are producing lots of failures due to how `wp-scripts lint-js`
		// config is being combined with this local override.
		'jsdoc/require-returns': 'off',
		'jsdoc/require-param': 'off',
		'jsdoc/require-param-type': 'off',
		'jsdoc/require-param-description': 'off',
		'prefer-arrow/prefer-arrow-functions': 'off',
	},
};

