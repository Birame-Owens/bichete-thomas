import axios from 'axios'

// URL de l API. En dev, par defaut "/api" passe par le proxy Vite vers
// le backend Laravel (cf vite.config.ts) -> tout est same-origin pour le
// navigateur, ce qui evite le pb cross-origin cookie sur le token CSRF.
// En prod, definir VITE_API_BASE_URL=https://api.example.com/api au build.
export const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? '/api'

// Axios partage par toute l app. withCredentials envoie automatiquement
// le cookie httpOnly d auth (B4) sur chaque requete. xsrf{Cookie,Header}Name
// configurent l echo automatique du token CSRF lu depuis document.cookie.
export const apiClient = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
  },
  withCredentials: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
})

apiClient.interceptors.request.use((config) => {
  // FormData a son propre Content-Type (multipart avec boundary genere
  // par le navigateur) ; on retire le notre pour ne pas le casser.
  if (config.data instanceof FormData) {
    delete config.headers['Content-Type']
  }

  return config
})
