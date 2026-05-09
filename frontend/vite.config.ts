import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],

  // Proxy dev : redirige /api/* vers le backend Laravel.
  // Sans ca, le frontend (localhost:5173) et le backend (localhost:8000) sont
  // deux origines differentes : axios ne peut pas lire le cookie XSRF-TOKEN
  // pose par le backend (cross-origin cookie isolation), donc il ne peut pas
  // l echoer dans le header X-XSRF-TOKEN -> toutes les mutations admin
  // partent en 419 CSRF mismatch.
  // Avec le proxy, le navigateur voit toutes les requetes comme same-origin :
  // les cookies sont stockes pour localhost:5173, document.cookie les voit,
  // axios echoe le token, plus de 419.
  // En production, ce proxy est ignore (Vite dev only) ; c est VITE_API_BASE_URL
  // au build qui pilote l URL du backend.
  server: {
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: false, // garde l Origin du navigateur pour les checks CORS Laravel
      },
    },
  },

  // ---------------------------------------------------------------------
  // Build production (I10)
  // ---------------------------------------------------------------------
  build: {
    // Pas de source maps en prod : evite de leak le code source frontend
    // (logique de routing admin, structure des composants, etc.) sur le
    // serveur public. Les source maps restent utiles en dev (defaut Vite).
    sourcemap: false,

    // Augmente le seuil d alerte de taille de chunk (defaut 500 kB).
    // Avec le lazy loading des routes admin (I9), les chunks vendor sont
    // les plus gros mais restent acceptables.
    chunkSizeWarningLimit: 800,

    rollupOptions: {
      output: {
        // Decoupage manuel des chunks vendor : isole React et React Router
        // dans leur propre chunk pour qu ils soient caches par le navigateur
        // entre les deploiements (l upgrade d une lib metier ne casse pas le
        // cache de React, et inversement).
        // Le reste de node_modules part dans un chunk "vendor" generique.
        manualChunks: (id: string) => {
          if (!id.includes('node_modules')) {
            return undefined
          }

          if (id.includes('react-router')) {
            return 'vendor-router'
          }

          if (id.includes('/react/') || id.includes('/react-dom/') || id.includes('/scheduler/')) {
            return 'vendor-react'
          }

          if (id.includes('axios')) {
            return 'vendor-axios'
          }

          if (id.includes('lucide-react')) {
            return 'vendor-icons'
          }

          return 'vendor'
        },
      },
    },
  },
})
