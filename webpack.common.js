const path = require('path');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');

module.exports = {
    entry: './FlatRatePay/assets/js/src/tokenization-form.js',
    plugins: [
        new CleanWebpackPlugin()
    ],
    output: {
        filename: 'flatratepay.js',
        path: path.resolve(__dirname, './FlatRatePay/assets/js/dist')
    }
};