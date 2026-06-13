import { defineConfig } from 'vitest/config'
import react from '@vitejs/plugin-react'

// Config dediee aux tests (Vitest la prend en priorite sur vite.config.ts).
// Separee pour ne pas polluer vite.config.ts avec l'option `test` (qui n'est
// pas dans le type UserConfig de Vite) ni declencher de conflit de types au
// build. Ce fichier n'est pas type-checke par `tsc -b` (hors include node).
export default defineConfig({
  plugins: [react()],
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    restoreMocks: true,
  },
})
