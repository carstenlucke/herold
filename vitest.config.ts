import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'jsdom',
        // Vuetify components import their own CSS; let Vite transform them
        server: { deps: { inline: ['vuetify'] } },
    },
})
