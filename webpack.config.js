const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		editor: './client/editor/index.js',
		'settings-panel': './client/settings-panel/index.js',
		'post-list': './client/post-list/index.js',
		'classic-editor': './client/classic-editor/index.js',
	},
	output: {
		filename: '[name].js',
		path: __dirname + '/build',
	},
};
