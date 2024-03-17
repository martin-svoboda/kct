/**
 * Configuration of the build process
 * - entry: what do you want to compile
 * - output: where the compiled files endup
 * - copy: what to copy from build folder where
 * - sprites: define from where generate svg sprites
 */

const config = {
	entry: {
		'theme': ['./assets/scripts/theme.js', './assets/styles/theme.scss'],
		'plugin': ['./assets/scripts/plugin.js', './assets/styles/plugin.scss'],
		'events': ['./assets/apps/events/app.jsx'],
		'departments': ['./assets/apps/departments/app.jsx'],
		'block-editor': './assets/scripts/block-editor.js',
		'editor-style': './assets/styles/editor-style.scss',
	},
	output: {
		path: 'build',
	},
	copy: [
		{source: 'editor-style.css', destination: 'themes/kct/editor-style.css'},
		{source: 'theme.css', destination: 'themes/kct/theme.css'},
	],
	sprite: {
		input: 'assets/svgs/**/*.svg',
		output: {
			filename: 'sprites.svg',
			styles: 'assets/styles/sprites.scss',
		},
	},
	browsersync: {
		files: [
			'build/**/*.css',
			'build/**/*.js',
			'build/**/*.svg',
			'src/**/*.php',
			'templates/**/*.php',
			'themes/main/**/*.php',
			'themes/main/*.php',
		],
	},
};

/**
 * Import dependencies for build process
 */

const path = require('path');
const fs = require('fs');
const BrowserSyncPlugin = require('browser-sync-webpack-plugin');
const SVGSpritemapPlugin = require('svg-spritemap-webpack-plugin');
const globImporter = require('node-sass-glob-importer');
const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const packageJson = require('./package.json');

/**
 * Resolves absolute paths for entry from configuration
 *
 * @param paths
 * @returns {string|{}|*}
 */

const resolvePathsRecursively = (paths) => {
	if (typeof paths === 'string') {
		return path.resolve(__dirname, paths);
	} else if (Array.isArray(paths)) {
		return paths.map(resolvePathsRecursively);
	} else if (Object(paths) === paths) {
		const resolvedPaths = {};

		Object.keys(paths).forEach(key => {
			resolvedPaths[key] = resolvePathsRecursively(paths[key]);
		});

		return resolvedPaths;
	}
};

/**
 * Custom plugin for copying files.
 */
class CopyAfterCompilationWebpackPlugin {
	constructor(files = []) {
		this.files = files;
	}

	apply(compiler) {
		compiler.hooks.done.tap('CopyAfterCompilationWebpackPlugin', () => {
			this.files
				.filter(file => (
					(fs.existsSync(file.source) && fs.existsSync(file.destination) && !fs.readFileSync(file.source).equals(fs.readFileSync(file.destination)))
					||
					(fs.existsSync(file.source) && !fs.existsSync(file.destination))
				))
				.forEach((file) => {
					fs.copyFileSync(file.source, file.destination);
					console.log(`Copied: '${file.source}' > '${file.destination}'`);
				});
		});
	}
}

/**
 * Exports the configuration for the build process
 */

module.exports = {
	...defaultConfig,
	entry: resolvePathsRecursively(config.entry),
	output: {
		...defaultConfig.output,
		path: resolvePathsRecursively(config.output.path)
	},
	resolve: {
		...defaultConfig.resolve,
		extensions: ['.js', '.json', '.jsx'],
	},
	module: {
		...defaultConfig.module,
		rules: [
			...defaultConfig.module.rules.map((rule) => {
				if (rule.test.test('.scss')) {
					rule.use.forEach(use => {
						if (use.loader === require.resolve('sass-loader')) {
							use.options.sassOptions = {
								...(use.options.sassOptions || null),
								importer: globImporter(),
							};
						}
					});
				} else if (rule.test.test('.css') && packageJson.devDependencies.tailwindcss && fs.existsSync(resolvePathsRecursively('tailwind.config.js'))) {
					rule.test = /\.p?css$/;
					rule.use.forEach(use => {
						if (use.loader === require.resolve('postcss-loader')) {
							use.options.postcssOptions = {
								...(use.options.postcssOptions || null),
								plugins: [
									require('postcss-import'),
									require('tailwindcss/nesting'),
									require('tailwindcss'),
									...(use.options.postcssOptions.plugins || null),
								],
							};
						}
					});
				}

				return rule;
			}),
		],
	},
	plugins: [
		...defaultConfig.plugins.filter(plugin => plugin.constructor.name !== 'LiveReloadPlugin' || !config.browsersync),
		config.browsersync && new BrowserSyncPlugin({
			...config.browsersync,
			...(
				fs.existsSync(resolvePathsRecursively('.ssl/certs/master.key')) && fs.existsSync(resolvePathsRecursively('.ssl/certs/master.crt'))
					? {
						https: resolvePathsRecursively({
							key: '.ssl/certs/master.key',
							cert: '.ssl/certs/master.crt',
						})
					}
					: {}
			),
		}, {
			injectCss: true,
			reload: true,
		}),
		Array.isArray(config.copy) && config.copy.length > 0 && new CopyAfterCompilationWebpackPlugin(
			config.copy.map(paths => ({
				source: path.resolve(config.output.path, paths.source),
				destination: resolvePathsRecursively(paths.destination),
			})),
		),
		config.sprite && config.sprite.input && new SVGSpritemapPlugin(resolvePathsRecursively(config.sprite.input), {
			output: {
				filename: config.sprite.output.filename || 'sprites.svg',
				svg4everybody: true,
				svgo: true,
			},
			sprite: {
				generate: {
					title: true,
					symbol: true,
					use: true,
					view: '-fragment',
				},
			},
			styles: {
				filename: resolvePathsRecursively(config.sprite.output.styles),
				format: 'fragment',
				callback: (content) => `${content}
				@each $name, $size in $sizes {
					.sprite--#{$name} {
						width: map-get($size, width);
						height: map-get($size, height);
					}
				}`,
			},
		}),
	].filter(Boolean),
};
