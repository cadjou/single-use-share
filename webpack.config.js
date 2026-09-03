const webpackConfig = require('@nextcloud/webpack-vue-config')

webpackConfig.entry = {
	main: { import: 'src/main.js', filename: 'singleuseshare-main.js' },
}

module.exports = webpackConfig
