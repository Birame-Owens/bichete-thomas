import { useEffect, useState } from 'react'

// ---------------------------------------------------------------------
// Panier boutique (phase 2 ecommerce) — persiste en localStorage, partage
// entre pages via un CustomEvent (pas besoin de context provider global :
// les pages boutique sont des routes independantes).
// ---------------------------------------------------------------------

export type PanierItem = {
  produitId: number
  slug: string
  nom: string
  image: string | null
  prix: number
  couleur: string | null
  taille: string | null
  quantite: number
}

const STORAGE_KEY = 'bt_boutique_panier'
const EVENT = 'bt-panier-updated'

function read(): PanierItem[] {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    const parsed = raw ? JSON.parse(raw) : []
    return Array.isArray(parsed) ? parsed : []
  } catch {
    return []
  }
}

function write(items: PanierItem[]) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(items))
  window.dispatchEvent(new CustomEvent(EVENT))
}

/** Cle d identite d une ligne : meme produit + meme variante = meme ligne. */
function sameLine(a: PanierItem, produitId: number, couleur: string | null, taille: string | null) {
  return a.produitId === produitId && a.couleur === couleur && a.taille === taille
}

export function addToPanier(item: Omit<PanierItem, 'quantite'>, quantite = 1) {
  const items = read()
  const existing = items.find((i) => sameLine(i, item.produitId, item.couleur, item.taille))
  if (existing) {
    existing.quantite = Math.min(existing.quantite + quantite, 20)
  } else {
    items.push({ ...item, quantite })
  }
  write(items)
}

export function updateQuantite(produitId: number, couleur: string | null, taille: string | null, quantite: number) {
  const items = read()
  const item = items.find((i) => sameLine(i, produitId, couleur, taille))
  if (!item) return
  item.quantite = Math.max(1, Math.min(quantite, 20))
  write(items)
}

export function removeFromPanier(produitId: number, couleur: string | null, taille: string | null) {
  write(read().filter((i) => !sameLine(i, produitId, couleur, taille)))
}

export function clearPanier() {
  write([])
}

export function panierTotal(items: PanierItem[]): number {
  return items.reduce((sum, i) => sum + i.prix * i.quantite, 0)
}

export function panierCount(items: PanierItem[]): number {
  return items.reduce((sum, i) => sum + i.quantite, 0)
}

/** Hook reactif : re-render a chaque modification du panier (toutes pages). */
export function usePanier(): PanierItem[] {
  const [items, setItems] = useState<PanierItem[]>(read)

  useEffect(() => {
    const refresh = () => setItems(read())
    window.addEventListener(EVENT, refresh)
    window.addEventListener('storage', refresh)
    return () => {
      window.removeEventListener(EVENT, refresh)
      window.removeEventListener('storage', refresh)
    }
  }, [])

  return items
}
