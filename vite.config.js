import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'
import { readFileSync, writeFileSync } from 'fs'

function syncViteDev() {
  return {
    name: 'sync-vite-dev',
    configResolved(config) {
      const envPath = resolve(__dirname, '.env')
      let content = readFileSync(envPath, 'utf8')
      const isDev = config.command === 'serve'
      content = content.replace(/^VITE_DEV=.*/m, `VITE_DEV=${isDev ? 'true' : 'false'}`)
      writeFileSync(envPath, content)
    },
  }
}

export default defineConfig({
  plugins: [vue(), syncViteDev()],

  publicDir: false,

  build: {
    outDir: 'public',
    emptyOutDir: false,
    manifest: true,
    rollupOptions: {
      input: {
        site:  resolve(__dirname, 'src/site/main.js'),
        admin: resolve(__dirname, 'src/admin/main.js'),
      },
      output: {
        entryFileNames: (chunk) => `${chunk.name}/js/[name].[hash].js`,
        chunkFileNames: (chunk) => {
          const isAdmin = chunk.moduleIds?.some(id => id.includes('/admin/'))
          return `${isAdmin ? 'admin' : 'site'}/js/[name].[hash].js`
        },
        assetFileNames: (asset) => {
          if (/\.(css)$/.test(asset.name ?? '')) {
            const name = asset.name?.replace('.css', '') ?? 'asset'
            const dir  = name === 'admin' ? 'admin' : 'site'
            return `${dir}/css/main.[hash].css`
          }
          return 'site/img/[name].[hash][extname]'
        },
      },
    },
  },

  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
    },
  },

  server: {
    port: 5173,
    proxy: {
      '^(?!/site|/admin|/@vite|/@id|/node_modules)': {
        target: 'http://localhost:8085',
        changeOrigin: true,
      },
    },
  },
})
