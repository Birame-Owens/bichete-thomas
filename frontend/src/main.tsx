import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import './index.css'
import App from './App.tsx'
import { initSentry } from './lib/sentry'

// Capture des erreurs front (no-op si aucun VITE_SENTRY_DSN n'est defini).
initSentry()

// Recuperation auto apres un deploiement : si un chunk lazy ne peut pas se
// charger (souvent parce que la page etait ouverte pendant un deploiement et
// reference d'anciens fichiers hashes), Vite emet "vite:preloadError". On
// recharge alors la page pour recuperer la derniere version. Garde-fou
// temporel : au plus un rechargement / 10 s, pour eviter toute boucle si
// l'echec est reel (reseau, chunk reellement manquant).
window.addEventListener('vite:preloadError', () => {
  const lastReload = Number(sessionStorage.getItem('bt-preload-reload-at') ?? 0)
  if (Date.now() - lastReload > 10_000) {
    sessionStorage.setItem('bt-preload-reload-at', String(Date.now()))
    window.location.reload()
  }
})

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <BrowserRouter>
      <App />
    </BrowserRouter>
  </StrictMode>,
)
