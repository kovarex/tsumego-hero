import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
	plugins: [react()],
	build: {
		outDir: 'webroot/js/dist',
		emptyOutDir: true,
		rollupOptions: {
			input: './app/index.tsx',  // Single entry point
			output: {
				format: 'es',
				entryFileNames: 'app.js',  // Single bundle
				chunkFileNames: 'app-[hash].js',
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
