const webpackConfig = require('@nextcloud/webpack-vue-config')
const ESLintPlugin = require('eslint-webpack-plugin')
const StyleLintPlugin = require('stylelint-webpack-plugin')
const path = require('path')

webpackConfig.entry = {
	main: { import: path.join(__dirname, 'src', 'main.js'), filename: 'main.js' },
	settings: path.join(__dirname, 'src', 'settings.js'),
	areas: path.join(__dirname, 'src', 'areas.js'),
	puestos: path.join(__dirname, 'src', 'puestos.js'),
}

webpackConfig.plugins.push(
	new ESLintPlugin({
		extensions: ['js', 'vue'],
		files: 'src',
	}),
)
webpackConfig.plugins.push(
	new StyleLintPlugin({
		files: 'src/**/*.{css,scss,vue}',
	}),
)

webpackConfig.module.rules.push({
	test: /\.svg$/i,
	type: 'asset/source',
})

module.exports = webpackConfig
