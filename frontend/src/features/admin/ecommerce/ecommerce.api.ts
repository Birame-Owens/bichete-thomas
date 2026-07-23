import { apiClient } from '../../../lib/apiClient'
import type {
  ApiEnvelope,
  BackendPagination,
  Categorie,
  CategoryOption,
  CategoryStats,
  Produit,
  Commande,
  DeliveryZone,
  LaravelPaginated,
  KPIStats,
} from './ecommerce.types'

const BASE = '/admin/ecommerce'

// ----------------------------------------------------------------------
// Le backend (module admin v2) renvoie ses listes sous la forme :
//   { success, data: { produits|categories|commandes: [...], pagination: {...} } }
// alors que les pages consomment le format LaravelPaginated {data, current_page...}.
// Les helpers ci-dessous font la conversion a un seul endroit.
// ----------------------------------------------------------------------

function toPaginated<T>(items: T[] | undefined, pagination?: BackendPagination): LaravelPaginated<T> {
  const data = items ?? []
  return {
    data,
    current_page: pagination?.current_page ?? 1,
    last_page: pagination?.last_page ?? 1,
    per_page: pagination?.per_page ?? data.length,
    total: pagination?.total ?? data.length,
  }
}

/** Le backend renvoie l objet categorie {id, nom, slug} mais pas categorie_id : on l aplatit. */
function normalizeProduit(p: any): Produit {
  return {
    ...p,
    categorie_id: p.categorie_id ?? p.categorie?.id ?? 0,
  }
}

/** Convertit un objet en FormData (les FormData passent tels quels). */
function asFormData(payload: any): FormData {
  if (payload instanceof FormData) return payload
  const formData = new FormData()
  Object.keys(payload).forEach(key => {
    if (payload[key] !== null && payload[key] !== undefined) {
      formData.append(key, payload[key])
    }
  })
  return formData
}

// ============= CATEGORIES =============

export async function getCategories(page = 1, perPage = 15, search = '') {
  const { data } = await apiClient.get<ApiEnvelope<{ categories: Categorie[]; pagination: BackendPagination }>>(
    `${BASE}/categories`,
    { params: { page, per_page: perPage, search } },
  )
  return toPaginated(data.data.categories, data.data.pagination)
}

/** Categories parentes avec leurs sous-categories embarquees. */
export async function getCategoriesParents() {
  const { data } = await apiClient.get<ApiEnvelope<{ categories: Categorie[] }>>(
    `${BASE}/categories`,
    { params: { type: 'parents' } },
  )
  return data.data.categories ?? []
}

export async function getCategoryStats() {
  const { data } = await apiClient.get<ApiEnvelope<CategoryStats>>(`${BASE}/categories/stats`)
  return data.data
}

/** Liste plate {id, nom, parent_id} pour les selects. */
export async function getCategoryOptions() {
  const { data } = await apiClient.get<ApiEnvelope<CategoryOption[]>>(`${BASE}/categories/options`)
  return data.data ?? []
}

export async function toggleCategoryStatus(id: number) {
  const { data } = await apiClient.post<ApiEnvelope<{ category: Categorie }>>(
    `${BASE}/categories/${id}/toggle-status`,
    {},
  )
  return data.data.category
}

export async function getCategoryById(id: number) {
  const { data } = await apiClient.get<ApiEnvelope<{ category: Categorie }>>(`${BASE}/categories/${id}`)
  return data.data.category ?? (data.data as unknown as Categorie)
}

export async function createCategory(payload: any) {
  const { data } = await apiClient.post<ApiEnvelope<any>>(`${BASE}/categories`, asFormData(payload))
  return data.data
}

export async function updateCategory(id: number, payload: any) {
  const formData = asFormData(payload)
  formData.append('_method', 'PUT')
  const { data } = await apiClient.post<ApiEnvelope<any>>(`${BASE}/categories/${id}`, formData)
  return data.data
}

export async function deleteCategory(id: number) {
  await apiClient.delete(`${BASE}/categories/${id}`)
}

// ============= PRODUITS =============

export async function getProduits(page = 1, perPage = 15, search = '', categoryId = '', status = '') {
  const params: any = { page, per_page: perPage }
  if (search) params.search = search
  if (categoryId) params.category_id = categoryId
  if (status && status !== 'all') params.status = status

  const { data } = await apiClient.get<ApiEnvelope<{ produits: any[]; pagination: BackendPagination }>>(
    `${BASE}/produits`,
    { params },
  )
  const paginated = toPaginated(data.data.produits, data.data.pagination)
  return { ...paginated, data: paginated.data.map(normalizeProduit) }
}

/** Top produits par nombre de ventes (dashboard). */
export async function getTopProduits(limit = 5) {
  const { data } = await apiClient.get<ApiEnvelope<{ produits: any[]; pagination: BackendPagination }>>(
    `${BASE}/produits`,
    { params: { per_page: limit, sort: 'nombre_ventes', direction: 'desc' } },
  )
  return (data.data.produits ?? []).map(normalizeProduit)
}

export async function getProduitById(id: number) {
  const { data } = await apiClient.get<ApiEnvelope<{ produit: any }>>(`${BASE}/produits/${id}`)
  return normalizeProduit(data.data.produit)
}

export async function createProduit(payload: any) {
  const { data } = await apiClient.post<ApiEnvelope<{ produit: any }>>(`${BASE}/produits`, asFormData(payload))
  return normalizeProduit(data.data.produit)
}

export async function updateProduit(id: number, payload: any) {
  const formData = asFormData(payload)
  formData.append('_method', 'PUT')
  const { data } = await apiClient.post<ApiEnvelope<{ produit: any }>>(`${BASE}/produits/${id}`, formData)
  return normalizeProduit(data.data.produit)
}

export async function deleteProduit(id: number) {
  await apiClient.delete(`${BASE}/produits/${id}`)
}

export async function toggleProduitStatus(id: number) {
  const { data } = await apiClient.post<ApiEnvelope<{ produit: any }>>(`${BASE}/produits/${id}/toggle-status`, {})
  return data.data.produit
}

export async function duplicateProduit(id: number) {
  const { data } = await apiClient.post<ApiEnvelope<{ produit: any }>>(`${BASE}/produits/${id}/duplicate`, {})
  return normalizeProduit(data.data.produit)
}

export async function deleteProduitImage(produitId: number, imageId: number) {
  await apiClient.delete(`${BASE}/produits/${produitId}/images/${imageId}`)
}

export async function updateProduitImagesOrder(produitId: number, order: Array<{ id: number; ordre: number }>) {
  const { data } = await apiClient.put<ApiEnvelope<any>>(`${BASE}/produits/${produitId}/images/order`, { images: order })
  return data.data
}

// ============= COMMANDES =============

export async function getCommandes(
  page = 1,
  perPage = 15,
  search = '',
  statut = '',
  dateDebut = '',
  dateFin = '',
  priorite = '',
) {
  const params: any = { page, per_page: perPage }
  if (search) params.numero_commande = search
  if (statut) params.statut = statut
  if (dateDebut) params.date_debut = dateDebut
  if (dateFin) params.date_fin = dateFin
  if (priorite) params.priorite = priorite

  const { data } = await apiClient.get<ApiEnvelope<{ commandes: Commande[]; pagination: BackendPagination }>>(
    `${BASE}/commandes`,
    { params },
  )
  return toPaginated(data.data.commandes, data.data.pagination)
}

export async function getCommandeById(id: number): Promise<Commande> {
  const { data } = await apiClient.get<ApiEnvelope<{ commande: any }>>(`${BASE}/commandes/${id}`)
  const commande = data.data.commande
  // Le format detail expose les articles sous "articles" avec produit imbrique :
  // on aplatit vers articles_commandes {nom_produit, prix_total_article} attendu par la page.
  return {
    ...commande,
    articles_commandes: (commande.articles ?? []).map((a: any) => ({
      id: a.id,
      nom_produit: a.produit?.nom ?? '',
      quantite: a.quantite,
      prix_unitaire: a.prix_unitaire,
      prix_total_article: a.prix_total,
    })),
  }
}

export async function createCommande(payload: any) {
  const { data } = await apiClient.post<ApiEnvelope<any>>(`${BASE}/commandes`, payload)
  return data.data
}

export async function updateCommande(id: number, payload: any) {
  const { data } = await apiClient.put<ApiEnvelope<any>>(`${BASE}/commandes/${id}`, payload)
  return data.data
}

export async function deleteCommande(id: number) {
  await apiClient.delete(`${BASE}/commandes/${id}`)
}

export async function updateCommandeStatus(id: number, statut: string) {
  const { data } = await apiClient.patch<ApiEnvelope<any>>(`${BASE}/commandes/${id}/statut`, { statut })
  return data.data
}

export async function markCommandeAsPaid(id: number, payload: any) {
  const { data } = await apiClient.post<ApiEnvelope<any>>(`${BASE}/commandes/${id}/payer`, payload)
  return data.data
}

export async function getCommandesStats() {
  const { data } = await apiClient.get<ApiEnvelope<KPIStats>>(`${BASE}/stats/commandes`)
  return data.data
}

// ============= DELIVERY ZONES =============

export async function getDeliveryZones() {
  const { data } = await apiClient.get<ApiEnvelope<DeliveryZone[]>>(`${BASE}/delivery-zones`)
  return data.data
}

export async function createDeliveryZone(payload: any) {
  const { data } = await apiClient.post<ApiEnvelope<DeliveryZone>>(`${BASE}/delivery-zones`, payload)
  return data.data
}

export async function updateDeliveryZone(id: number, payload: any) {
  const { data } = await apiClient.put<ApiEnvelope<DeliveryZone>>(`${BASE}/delivery-zones/${id}`, payload)
  return data.data
}

export async function deleteDeliveryZone(id: number) {
  await apiClient.delete(`${BASE}/delivery-zones/${id}`)
}

// ============= SETTINGS =============

export async function getShopSettings() {
  const { data } = await apiClient.get<ApiEnvelope<any>>(`${BASE}/shop-settings`)
  return data.data
}

export async function updateShopSettings(payload: any) {
  const { data } = await apiClient.put<ApiEnvelope<any>>(`${BASE}/shop-settings`, payload)
  return data.data
}

export async function getShippingSettings() {
  const { data } = await apiClient.get<ApiEnvelope<any>>(`${BASE}/shipping`)
  return data.data
}

export async function updateShippingSettings(payload: any) {
  const { data } = await apiClient.put<ApiEnvelope<any>>(`${BASE}/shipping`, payload)
  return data.data
}
