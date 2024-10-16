const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
    ...defaultConfig,
    entry: {
        editor: './client/editor/index.js',
        'settings-panel': './client/settings-panel/index.js',
        'drag-drop-reorder': './client/drag-drop-reorder/index.js',
    },
    output: {
        filename: '[name].js',
        path: __dirname + '/build',
    },
};
