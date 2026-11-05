import js from '@eslint/js';
import globals from 'globals';
import reactHooks from 'eslint-plugin-react-hooks';
import reactRefresh from 'eslint-plugin-react-refresh';
import tseslint from 'typescript-eslint';
import stylistic from '@stylistic/eslint-plugin';

export default tseslint.config(
	//Of webroot, only the legacy scripts are linted
	{ ignores: ['webroot/*', '!webroot/js', 'webroot/besogo/*', '!webroot/besogo', '!webroot/besogo/js', 'vendor', 'tmp', 'node_modules', 'build'] },
	//The legacy global scripts of webroot (vite.config.js bundles them): the site doesn't load jQuery,
	//so they can't use it.
	{
		files: ['webroot/js/**/*.js', 'webroot/besogo/js/**/*.js'],
		languageOptions: {
			ecmaVersion: 'latest',
			sourceType: 'script',
			globals: globals.browser,
		},
		linterOptions: {
			reportUnusedDisableDirectives: 'error',
		},
		rules: {
			'no-restricted-globals': ['error',
				{ name: '$', message: 'The site doesn\'t load jQuery; use the DOM, fetch, or Display (webroot/js/Display.js).' },
				{ name: 'jQuery', message: 'The site doesn\'t load jQuery; use the DOM, fetch, or Display (webroot/js/Display.js).' }],
		},
	},
	{
		extends: [js.configs.recommended, ...tseslint.configs.recommended],
		files: ['app/**/*.{ts,tsx}'],
		languageOptions: {
			ecmaVersion: 2020,
			globals: globals.browser,
		},
		plugins: {
			'react-hooks': reactHooks,
			'react-refresh': reactRefresh,
			'@stylistic': stylistic,
		},
		rules: {
			...reactHooks.configs.recommended.rules,
			'react-refresh/only-export-components': [
				'warn',
				{ allowConstantExport: true },
			],
			'@typescript-eslint/no-unused-vars': [
				'warn',
				{ 
					argsIgnorePattern: '^_',
					varsIgnorePattern: '^_',
				}
			],
			// === Code Style Rules (matching PHP project conventions) ===
			// NO braces for single-statement if/else/for/while (like PHP's RemoveBracersAroundBlocksWithOneCommandFixer)
			// Use 'multi' to require braces only when there are multiple statements (not just multi-line)
			'curly': ['error', 'multi'],
			// Single-statement bodies must be on the NEXT line (like PHP Allman style)
			'@stylistic/nonblock-statement-body-position': ['error', 'below'],
			// Allman brace style - opening braces on next line
			'@stylistic/brace-style': ['error', 'allman', { allowSingleLine: true }],
			// Tabs for indentation (matching PHP)
			'@stylistic/indent': ['error', 'tab', { SwitchCase: 1 }],
			// No trailing commas (matching PHP trailing_comma_in_multiline: false)
			'@stylistic/comma-dangle': ['error', 'never'],
		},
	}
);
