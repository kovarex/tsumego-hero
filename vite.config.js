import { readFileSync, writeFileSync } from 'node:fs';
import { resolve as pathResolve, join } from 'node:path';
import { createHash } from 'node:crypto';
import { defineConfig, transformWithEsbuild } from 'vite';
import react from '@vitejs/plugin-react';

/**
 * Legacy JS files (global-scope scripts, must remain non-module to stay in global scope).
 * Order matches the original AssetCompress app.js bundle order.
 */
const LEGACY_JS_FILES = [
	'webroot/js/util.js',
	'webroot/js/Rating.js',
	'webroot/js/TimeModeTimer.js',
	'webroot/js/XPStatus.js',
	'webroot/js/BoardSelector.js',
	'webroot/js/RatingModeDifficultySelector.js',
	'webroot/js/AccountWidget.js',
	'webroot/js/dark.js',
	'webroot/js/previewBoard.js',
	'webroot/js/TagConnectionsEdit.js',
	'webroot/FileSaver.min.js',
	'webroot/js/multipleChoice.js',
	'webroot/js/multipleChoiceCustom.js',
	'webroot/js/scoreEstimatingCustom.js',
	'webroot/js/set-view.js',
];

/**
 * Concatenates legacy global-scope JS files and emits them as a plain (non-module) script.
 * Bypasses Rollup's module system entirely so functions remain globally accessible.
 * Appends a 'virtual:legacy-app' entry to the Vite manifest.json after the build.
 */
function legacyJsBundlePlugin() {
	let outDir;
	let rootDir;

	return {
		name: 'legacy-js-bundle',

		configResolved(config) {
			outDir = config.build.outDir;
			rootDir = config.root;
		},

		buildStart() {
			// Register legacy files with Vite's watcher so changes trigger a rebuild.
			// Without this, Rollup doesn't know about them (they're read via readFileSync,
			// not imported as modules) and watch mode would ignore edits to these files.
			LEGACY_JS_FILES.forEach(f => {
				this.addWatchFile(pathResolve(rootDir, f));
			});
		},

		async closeBundle() {
			// Concatenate all legacy files in order
			const parts = LEGACY_JS_FILES.map(f => {
				const abs = pathResolve(rootDir, f);
				return readFileSync(abs, 'utf-8');
			});
			const code = parts.join('\n\n');

			// Minify with esbuild (same tool Vite uses internally)
			const result = await transformWithEsbuild(code, 'legacy-app.js', {
				minify: true,
				target: 'es2020',
				loader: 'js',
			});

			// Content hash for cache busting
			const hash = createHash('md5').update(result.code).digest('hex').slice(0, 8);
			const filename = `legacy-app-${hash}.js`;

			// Write the plain (non-module) script to the dist directory
			writeFileSync(join(outDir, filename), result.code);

			// Append the legacy-app entry to the Vite manifest
			const manifestPath = join(outDir, '.vite/manifest.json');
			const manifest = JSON.parse(readFileSync(manifestPath, 'utf-8'));
			manifest['virtual:legacy-app'] = {
				file: filename,
				src: 'virtual:legacy-app',
				isEntry: true,
			};
			writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));
		},
	};
}

// In watch mode (pnpm dev = vite build --watch), disable emptyOutDir to prevent a race condition
// where rapid back-to-back rebuilds clear the dist directory before legacyJsBundlePlugin can read
// manifest.json in its closeBundle hook. Old files accumulate in dist but the manifest always
// points to the latest hashes, so this is harmless for local development.
const isWatchMode = process.argv.includes('--watch');

export default defineConfig({
	plugins: [react(), legacyJsBundlePlugin()],
	build: {
		// All built assets go to webroot/dist/ (CSS bundles, React app, source maps, manifest)
		outDir: 'webroot/dist',
		emptyOutDir: !isWatchMode,
		// Generate manifest.json so ViteManifest.php can resolve hashed filenames
		manifest: true,
		rollupOptions: {
			input: {
				// React app (comments, issues — mounts into PHP-rendered pages)
				app: './app/index.tsx',
				// CSS bundles — Vite extracts CSS from these JS proxy files into
				// separate hashed .css assets recorded in manifest.json
				'app-theme': './webroot/css/app-theme.js',
				'dark-theme': './webroot/css/dark-theme.js',
				'light-theme': './webroot/css/light-theme.js',
			},
			output: {
				entryFileNames: '[name]-[hash].js',
				chunkFileNames: '[name]-[hash].js',
				assetFileNames: '[name]-[hash][extname]',
			}
		},
		sourcemap: true,
	},
	// For development server (if you want to test components standalone)
	server: {
		port: 5173,
	}
});
