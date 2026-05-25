import { readFileSync, writeFileSync } from 'node:fs';
import { resolve as pathResolve, join } from 'node:path';
import { createHash } from 'node:crypto';
import { defineConfig, transformWithEsbuild } from 'vite';
import react from '@vitejs/plugin-react';

/**
 * Legacy JS files (global-scope scripts, must remain non-module to stay in global scope).
 */
const LEGACY_APP_FILES = [
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
 * Besogo Go board viewer JS files (global-scope, loaded on puzzle pages).
 */
const LEGACY_BESOGO_FILES = [
	'webroot/besogo/js/besogo.js',
	'webroot/besogo/js/transformation.js',
	'webroot/besogo/js/treeProblemUpdater.js',
	'webroot/besogo/js/nodeHashTable.js',
	'webroot/besogo/js/editor.js',
	'webroot/besogo/js/gameRoot.js',
	'webroot/besogo/js/status.js',
	'webroot/besogo/js/svgUtil.js',
	'webroot/besogo/js/cookieUtil.js',
	'webroot/besogo/js/parseSgf.js',
	'webroot/besogo/js/loadSgf.js',
	'webroot/besogo/js/saveSgf.js',
	'webroot/besogo/js/boardDisplay.js',
	'webroot/besogo/js/coord.js',
	'webroot/besogo/js/toolPanel.js',
	'webroot/besogo/js/filePanel.js',
	'webroot/besogo/js/controlPanel.js',
	'webroot/besogo/js/commentPanel.js',
	'webroot/besogo/js/treePanel.js',
	'webroot/besogo/js/diffInfo.js',
	'webroot/besogo/js/scaleParameters.js',
];

/**
 * Creates a plugin that concatenates legacy global-scope JS files and emits them as a plain
 * (non-module) script. Bypasses Rollup's module system entirely so functions remain globally
 * accessible. Appends a 'virtual:legacy-{name}' entry to the Vite manifest.json after the build.
 *
 * closeBundle hooks run sequentially per plugin, so two instances writing to the manifest is safe.
 */
function legacyBundlePlugin(name, files, { minify = true } = {}) {
	let outDir;
	let rootDir;

	return {
		name: `legacy-bundle-${name}`,

		configResolved(config) {
			outDir = config.build.outDir;
			rootDir = config.root;
		},

		buildStart() {
			// Register files with Vite's watcher so changes trigger a rebuild.
			// Without this, Rollup doesn't know about them (they're read via readFileSync,
			// not imported as modules) and watch mode would ignore edits to these files.
			files.forEach(f => {
				this.addWatchFile(pathResolve(rootDir, f));
			});
		},

		async closeBundle() {
			// Concatenate all files in order
			const parts = files.map(f => {
				const abs = pathResolve(rootDir, f);
				return readFileSync(abs, 'utf-8');
			});
			const code = parts.join(';\n\n');

			// Minify with esbuild (same tool Vite uses internally), or use raw code if minify=false
			let outputCode;
			if (minify) {
				const result = await transformWithEsbuild(code, `legacy-${name}.js`, {
					minify: true,
					target: 'es2020',
					loader: 'js',
				});
				outputCode = result.code;
			} else {
				outputCode = code;
			}

			// Content hash for cache busting
			const hash = createHash('md5').update(outputCode).digest('hex').slice(0, 8);
			const filename = `legacy-${name}-${hash}.js`;

			// Write the plain (non-module) script to the dist directory
			writeFileSync(join(outDir, filename), outputCode);

			// Append the entry to the Vite manifest
			const manifestPath = join(outDir, '.vite/manifest.json');
			const manifest = JSON.parse(readFileSync(manifestPath, 'utf-8'));
			manifest[`virtual:legacy-${name}`] = {
				file: filename,
				src: `virtual:legacy-${name}`,
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
	plugins: [react(), legacyBundlePlugin('app', LEGACY_APP_FILES), legacyBundlePlugin('besogo', LEGACY_BESOGO_FILES, { minify: false })],
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
				'besogo-css': './webroot/besogo/css/besogo-bundle.js',
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
