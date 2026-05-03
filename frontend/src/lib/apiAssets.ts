import { API_BASE_URL } from './apiClient'

const getApiOrigin = () => {
  const fallbackOrigin =
    typeof window === 'undefined' ? 'http://localhost:8000' : window.location.origin

  try {
    return new URL(API_BASE_URL, fallbackOrigin).origin
  } catch {
    return fallbackOrigin
  }
}

export const apiAssetUrl = (url?: string | null) => {
  if (!url) {
    return null
  }

  if (/^(data:|blob:)/i.test(url)) {
    return url
  }

  if (/^https?:/i.test(url)) {
    const apiOrigin = getApiOrigin()

    try {
      const assetUrl = new URL(url)
      const apiUrl = new URL(apiOrigin)
      const isLocalAsset =
        ['localhost', '127.0.0.1'].includes(assetUrl.hostname)
        && assetUrl.pathname.startsWith('/storage/')
        && assetUrl.origin !== apiUrl.origin

      return isLocalAsset ? `${apiOrigin}${assetUrl.pathname}${assetUrl.search}` : url
    } catch {
      return url
    }
  }

  const path = url.startsWith('/') ? url : `/${url}`

  return `${getApiOrigin()}${path}`
}
