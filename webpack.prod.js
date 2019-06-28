const common = require('./webpack.common.js');
const merge = require('webpack-merge');

module.exports = merge(common, {
    mode: 'production',
    devtool: 'source-map',
    module: {
        rules: [
            {
                test: /\.js$/,
                exclude: /node_modules/,
                use: 'babel-loader'
            }
        ]
    },
    output: {
        filename: 'flatratepay.js',
    }
});