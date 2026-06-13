import { apiClient } from '../../../lib/apiClient'
import { apiAssetUrl } from '../../../lib/apiAssets'
import type { GaleriePhoto } from './galerie.types'

type IndexResponse = { data: GaleriePhoto[]; max: number }
type ItemResponse = { message?: string; data: GaleriePhoto }

function normalize(photo: GaleriePhoto): GaleriePhoto {
  return { ...photo, url: apiAssetUrl(photo.url) ?? photo.url }
}

export async function getGaleriePhotos() {
  const response = await apiClient.get<IndexResponse>('/admin/galerie-photos')
  return { max: response.data.max, data: response.data.data.map(normalize) }
}

export async function createGaleriePhoto(input: { image: File; titre?: string; sous_titre?: string }) {
  const formData = new FormData()
  formData.append('image', input.image)
  if (input.titre) {
    formData.append('titre', input.titre)
  }
  if (input.sous_titre) {
    formData.append('sous_titre', input.sous_titre)
  }
  const response = await apiClient.post<ItemResponse>('/admin/galerie-photos', formData)
  return normalize(response.data.data)
}

export async function updateGaleriePhoto(
  id: number,
  input: { titre: string; sous_titre: string; actif: boolean },
) {
  const response = await apiClient.put<ItemResponse>(`/admin/galerie-photos/${id}`, {
    titre: input.titre.trim() === '' ? null : input.titre.trim(),
    sous_titre: input.sous_titre.trim() === '' ? null : input.sous_titre.trim(),
    actif: input.actif,
  })
  return normalize(response.data.data)
}

export async function deleteGaleriePhoto(id: number) {
  await apiClient.delete(`/admin/galerie-photos/${id}`)
}
