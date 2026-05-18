import { useEffect } from 'react'
import { apiClient } from '../lib/apiClient'

interface PageSeo {
  slug: string
  meta_title: string | null
  meta_description: string | null
  canonical_url: string | null
  image_og: string | null
  schema_json: Record<string, unknown> | null
}

function setMeta(name: string, content: string) {
  let el = document.querySelector<HTMLMetaElement>(`meta[name="${name}"]`)
  if (!el) {
    el = document.createElement('meta')
    el.setAttribute('name', name)
    document.head.appendChild(el)
  }
  el.content = content
}

function setOg(property: string, content: string) {
  let el = document.querySelector<HTMLMetaElement>(`meta[property="${property}"]`)
  if (!el) {
    el = document.createElement('meta')
    el.setAttribute('property', property)
    document.head.appendChild(el)
  }
  el.content = content
}

function setCanonical(url: string) {
  let el = document.querySelector<HTMLLinkElement>('link[rel="canonical"]')
  if (!el) {
    el = document.createElement('link')
    el.rel = 'canonical'
    document.head.appendChild(el)
  }
  el.href = url
}

function injectJsonLd(id: string, schema: Record<string, unknown>) {
  removeJsonLd(id)
  const script = document.createElement('script')
  script.type = 'application/ld+json'
  script.id = id
  script.textContent = JSON.stringify(schema)
  document.head.appendChild(script)
}

function removeJsonLd(id: string) {
  document.getElementById(id)?.remove()
}

/**
 * Injecte les balises SEO dynamiques depuis l'API pour la page courante.
 * Si la page n'existe pas en base, ne fait rien (dégradation silencieuse).
 */
export function useSeoPage(slug: string) {
  useEffect(() => {
    const originalTitle = document.title
    const controller = new AbortController()

    apiClient
      .get<{ data: PageSeo }>(`/seo/${slug}`, { signal: controller.signal })
      .then(({ data: { data: seo } }) => {
        if (seo.meta_title) {
          document.title = seo.meta_title
          setOg('og:title', seo.meta_title)
        }
        if (seo.meta_description) {
          setMeta('description', seo.meta_description)
          setOg('og:description', seo.meta_description)
        }
        if (seo.canonical_url) {
          setCanonical(seo.canonical_url)
          setOg('og:url', seo.canonical_url)
        }
        if (seo.image_og) {
          setOg('og:image', seo.image_og)
        }
        if (seo.schema_json) {
          injectJsonLd(`ld-json-${slug}`, seo.schema_json)
        }
      })
      .catch(() => {
        // Page SEO absente en base : on garde les balises statiques de index.html
      })

    return () => {
      controller.abort()
      document.title = originalTitle
      removeJsonLd(`ld-json-${slug}`)
    }
  }, [slug])
}
