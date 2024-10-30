module.exports = {
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	rules: {
		// Add any custom rules or overrides here
		'no-console': [ 'error', { allow: [ 'error' ] } ],
		'jsdoc/require-param-type': 'off',
	},
	globals: {
		wpQueuePluginData: 'readonly',
		wpQueuePluginPostListData: 'readonly',
	},
};
