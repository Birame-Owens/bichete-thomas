import axios, { AxiosError, type AxiosRequestConfig } from 'axios'

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
  // Timeout de 15s : evite qu un appel reseau bloque indefiniment l UI
  // (frequent en pic Tabaski avec backend sature). Au-dela, on entre dans
  // le retry I12 qui peut redeclencher la requete.
  timeout: 15000,
})

apiClient.interceptors.request.use((config) => {
  // FormData a son propre Content-Type (multipart avec boundary genere
  // par le navigateur) ; on retire le notre pour ne pas le casser.
  if (config.data instanceof FormData) {
    delete config.headers['Content-Type']
  }

  return config
})

// ----------------------------------------------------------------------
// Retry interceptor (I12)
// ----------------------------------------------------------------------
// Pendant le pic Tabaski, le backend peut renvoyer des 503 transitoires
// (PHP-FPM saturating, base de donnees brieve indispo) ou la connexion peut
// timeout. Sans retry, l utilisateur voit une erreur definitive alors qu un
// simple re-essai 1-2s plus tard aurait reussi.
//
// Politique :
// - Retry uniquement sur erreurs reseau / 5xx / 429 / timeouts.
// - PAS sur 4xx (validation, auth, droits) : ce sont des erreurs business,
//   re-essayer ne changera rien et masquerait des bugs.
// - Backoff exponentiel + jitter : 0.5s + jitter, 1s + jitter, 2s + jitter
//   pour eviter le "thundering herd" si tout le monde retry au meme moment.
// - PAS sur les requetes NON-idempotentes par defaut (POST / PUT / PATCH /
//   DELETE) sauf si la requete a explicitement opt-in via config.retry.
//   Une POST de paiement re-jouee creerait un doublon.
//
// Pour opter-in cote appel : apiClient.get('/foo', { retry: { attempts: 5 } })
// ----------------------------------------------------------------------

type RetryConfig = {
  attempts?: number
  /** Liste des methodes idempotentes pour lesquelles le retry est applique. */
  idempotentMethods?: readonly string[]
}

type RetriableConfig = AxiosRequestConfig & {
  retry?: RetryConfig
  /** Compteur interne incremente a chaque tentative. */
  __retryCount?: number
}

const DEFAULT_RETRY: Required<RetryConfig> = {
  attempts: 3,
  idempotentMethods: ['get', 'head', 'options'],
}

const RETRIABLE_STATUSES = new Set([429, 500, 502, 503, 504])

const RETRIABLE_NETWORK_CODES = new Set([
  'ECONNABORTED', // axios timeout
  'ETIMEDOUT',
  'ECONNRESET',
  'ENETUNREACH',
])

function shouldRetry(error: AxiosError, config: RetriableConfig): boolean {
  const retry = { ...DEFAULT_RETRY, ...(config.retry ?? {}) }
  const attempt = config.__retryCount ?? 0

  if (attempt >= retry.attempts) {
    return false
  }

  // Methode idempotente OU explicitement opt-in via config.retry ?
  const method = (config.method ?? 'get').toLowerCase()
  if (!retry.idempotentMethods.includes(method) && !config.retry) {
    return false
  }

  // Erreur reseau (pas de reponse)
  if (!error.response) {
    return RETRIABLE_NETWORK_CODES.has(error.code ?? '')
  }

  return RETRIABLE_STATUSES.has(error.response.status)
}

/** Backoff exponentiel : 500ms, 1s, 2s + un jitter aleatoire de 0-300ms. */
function backoffDelay(attempt: number): number {
  const base = 500 * 2 ** attempt
  const jitter = Math.floor(Math.random() * 300)
  return base + jitter
}

apiClient.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const config = error.config as RetriableConfig | undefined

    if (!config || !shouldRetry(error, config)) {
      return Promise.reject(error)
    }

    config.__retryCount = (config.__retryCount ?? 0) + 1
    const delay = backoffDelay(config.__retryCount - 1)

    await new Promise((resolve) => setTimeout(resolve, delay))

    return apiClient(config)
  },
)
