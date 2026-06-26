import { apiClient } from '../../../lib/apiClient'
import { apiAssetUrl } from '../../../lib/apiAssets'
import type {
  ApiCollection,
  ApiItem,
  Categorie,
  Produit,
  Commande,
  DeliveryZone,
  LaravelPaginated,
  CommandeStatusUpdate,
  KPIStats,
} from './ecommerce.types'

const BASE = '/admin/ecommerce'

// ============= CATEGORIES =============

export async function getCategories(page = 1, perPage = 15, search = '') {
  const { data } = await apiClient.get<ApiCollection<Categorie>>(`${BASE}/categories`, {
    params: { page, per_page: perPage, search },
  })
  return data.data
}

export async function getCategoryById(id: number) {
  const { data } = await apiClient.get<ApiItem<Categorie>>(`${BASE}/categories/${id}`)
  return data.data
}

export async function createCategory(payload: any) {
  const { data } = await apiClient.post<ApiItem<Categorie>>(`${BASE}/categories`, payload)
  return data.data
}

export async function updateCategory(id: number, payload: any) {
  const formData = new FormData()
  Object.keys(payload).forEach(key => {
    if (payload[key] !== null && payload[key] !== undefined) {
      formData.append(key, payload[key])
    }
  })
  if (payload.image instanceof File) {
    formData.delete('image')
    formData.append('image', payload.image)
  }
  formData.append('_method', 'PUT')

  const { data } = await apiClient.post<ApiItem<Categorie>>(`${BASE}/categories/${id}`, formData)
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

  const { data } = await apiClient.get<ApiCollection<Produit>>(`${BASE}/produits`, { params })
  return data.data
}

export async function getProduitById(id: number) {
  const { data } = await apiClient.get<ApiItem<Produit>>(`${BASE}/produits/${id}`)
  return data.data
}

export async function createProduit(payload: any) {
  const formData = new FormData()
  Object.keys(payload).forEach(key => {
    if (payload[key] !== null && payload[key] !== undefined && !(payload[key] instanceof File)) {
      formData.append(key, payload[key])
    }
  })
  if (payload.image_principale instanceof File) {
    formData.append('image_principale', payload.image_principale)
  }

  const { data } = await apiClient.post<ApiItem<Produit>>(`${BASE}/produits`, formData)
  return data.data
}

export async function updateProduit(id: number, payload: any) {
  const formData = new FormData()
  Object.keys(payload).forEach(key => {
    if (payload[key] !== null && payload[key] !== undefined && !(payload[key] instanceof File)) {
      formData.append(key, payload[key])
    }
  })
  if (payload.image_principale instanceof File) {
    formData.append('image_principale', payload.image_principale)
  }
  formData.append('_method', 'PUT')

  const { data } = await apiClient.post<ApiItem<Produit>>(`${BASE}/produits/${id}`, formData)
  return data.data
}

export async function deleteProduit(id: number) {
  await apiClient.delete(`${BASE}/produits/${id}`)
}

export async function toggleProduitStatus(id: number) {
  const { data } = await apiClient.post<ApiItem<Produit>>(`${BASE}/produits/${id}/toggle-status`, {})
  return data.data
}

export async function duplicateProduit(id: number) {
  const { data } = await apiClient.post<ApiItem<Produit>>(`${BASE}/produits/${id}/duplicate`, {})
  return data.data
}

export async function deleteProduitImage(produitId: number, imageId: number) {
  await apiClient.delete(`${BASE}/produits/${produitId}/images/${imageId}`)
}

export async function updateProduitImagesOrder(produitId: number, order: Array<{ id: number; ordre: number }>) {
  const { data } = await apiClient.put<ApiItem<Produit>>(`${BASE}/produits/${produitId}/images/order`, { images: order })
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

  const { data } = await apiClient.get<ApiCollection<Commande>>(`${BASE}/commandes`, { params })
  return data.data
}

export async function getCommandeById(id: number) {
  const { data } = await apiClient.get<ApiItem<Commande>>(`${BASE}/commandes/${id}`)
  return data.data
}

export async function createCommande(payload: any) {
  const { data } = await apiClient.post<ApiItem<Commande>>(`${BASE}/commandes`, payload)
  return data.data
}

export async function updateCommande(id: number, payload: any) {
  const { data } = await apiClient.put<ApiItem<Commande>>(`${BASE}/commandes/${id}`, payload)
  return data.data
}

export async function deleteCommande(id: number) {
  await apiClient.delete(`${BASE}/commandes/${id}`)
}

export async function updateCommandeStatus(id: number, statut: string) {
  const { data } = await apiClient.patch<ApiItem<Commande>>(`${BASE}/commandes/${id}/statut`, { statut })
  return data.data
}

export async function markCommandeAsPaid(id: number, payload: any) {
  const { data } = await apiClient.post<ApiItem<Commande>>(`${BASE}/commandes/${id}/payer`, payload)
  return data.data
}

export async function getCommandesStats() {
  const { data } = await apiClient.get<{ data: KPIStats }>(`${BASE}/stats/commandes`)
  return data.data
}

// ============= DELIVERY ZONES =============

export async function getDeliveryZones() {
  const { data } = await apiClient.get<ApiCollection<DeliveryZone>>(`${BASE}/delivery-zones`)
  return data.data.data
}

export async function createDeliveryZone(payload: any) {
  const { data } = await apiClient.post<ApiItem<DeliveryZone>>(`${BASE}/delivery-zones`, payload)
  return data.data
}

export async function updateDeliveryZone(id: number, payload: any) {
  const { data } = await apiClient.put<ApiItem<DeliveryZone>>(`${BASE}/delivery-zones/${id}`, payload)
  return data.data
}

export async function deleteDeliveryZone(id: number) {
  await apiClient.delete(`${BASE}/delivery-zones/${id}`)
}

// ============= SETTINGS =============

export async function getShopSettings() {
  const { data } = await apiClient.get<{ data: any[] }>(`${BASE}/shop-settings`)
  return data.data
}

export async function updateShopSettings(payload: any) {
  const { data } = await apiClient.put<ApiItem<any>>(`${BASE}/shop-settings`, payload)
  return data.data
}

export async function getShippingSettings() {
  const { data } = await apiClient.get<ApiItem<any>>(`${BASE}/shipping`)
  return data.data
}

export async function updateShippingSettings(payload: any) {
  const { data } = await apiClient.put<ApiItem<any>>(`${BASE}/shipping`, payload)
  return data.data
}
