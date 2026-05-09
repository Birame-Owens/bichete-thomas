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
})
