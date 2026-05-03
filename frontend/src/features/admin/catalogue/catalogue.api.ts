import { apiClient } from '../../../lib/apiClient'
import { apiAssetUrl } from '../../../lib/apiAssets'
import type {
  ApiCollection,
  ApiItem,
  CategorieCoiffure,
  CategorieForm,
  Coiffure,
  CoiffureForm,
  LaravelPaginated,
  OptionCoiffure,
  OptionForm,
  VarianteCoiffure,
  VarianteForm,
} from './catalogue.types'

type QueryParams = {
  page?: number
  categorie_coiffure_id?: number | string
  search?: string
  actif?: boolean | string
}

function cleanText(value: string) {
  return value.trim() === '' ? null : value.trim()
}

function collection<T>(payload: ApiCollection<T>, normalize: (item: T) => T): LaravelPaginated<T> {
  return {
    ...payload.data,
    data: payload.data.data.map(normalize),
  }
}

function normalizeCategorie(category: CategorieCoiffure): CategorieCoiffure {
  return {
    ...category,
    image: apiAssetUrl(category.image),
  }
}

function normalizeCoiffure(coiffure: Coiffure): Coiffure {
  return {
    ...coiffure,
    image: apiAssetUrl(coiffure.image),
    categorie: coiffure.categorie ? normalizeCategorie(coiffure.categorie) : coiffure.categorie,
    images: coiffure.images?.map((image) => ({
      ...image,
      url: apiAssetUrl(image.url) ?? image.url,
    })),
  }
}

function normalizeVariante(variante: VarianteCoiffure): VarianteCoiffure {
  return {
    ...variante,
    coiffure: variante.coiffure ? normalizeCoiffure(variante.coiffure) : variante.coiffure,
  }
}

function appendBoolean(formData: FormData, key: string, value: boolean) {
  formData.append(key, value ? '1' : '0')
}

function categoryFormData(form: CategorieForm) {
  const formData = new FormData()
  formData.append('nom', form.nom.trim())
  formData.append('description', cleanText(form.description) ?? '')
  appendBoolean(formData, 'actif', form.actif)
  if (form.image) {
    formData.append('image', form.image)
  }

  return formData
}

function coiffureFormData(form: CoiffureForm) {
  const formData = new FormData()
  formData.append('categorie_coiffure_id', form.categorie_coiffure_id)
  formData.append('nom', form.nom.trim())
  formData.append('description', cleanText(form.description) ?? '')
  appendBoolean(formData, 'actif', form.actif)

  form.option_ids.forEach((id, index) => {
    formData.append(`option_ids[${index}]`, String(id))
  })

  form.variantes
    .filter((variante) => variante.nom.trim() !== '' && variante.prix !== '' && variante.duree_minutes !== '')
    .forEach((variante, index) => {
      formData.append(`variantes[${index}][nom]`, variante.nom.trim())
      formData.append(`variantes[${index}][prix]`, variante.prix)
      formData.append(`variantes[${index}][duree_minutes]`, variante.duree_minutes)
      appendBoolean(formData, `variantes[${index}][actif]`, variante.actif)
    })

  form.images.forEach((image, index) => {
    formData.append(`images[${index}]`, image)
    if (index === 0) {
      formData.append('image', image)
    }
  })

  return formData
}

export async function getCategoriesCoiffures(params?: QueryParams) {
  const response = await apiClient.get<ApiCollection<CategorieCoiffure>>('/admin/categories-coiffures', {
    params,
  })

  return collection(response.data, normalizeCategorie)
}

export async function createCategorieCoiffure(form: CategorieForm) {
  const response = await apiClient.post<ApiItem<CategorieCoiffure>>(
    '/admin/categories-coiffures',
    categoryFormData(form),
  )

  return normalizeCategorie(response.data.data)
}

export async function updateCategorieCoiffure(id: number, form: CategorieForm) {
  const formData = categoryFormData(form)
  formData.append('_method', 'PUT')
  const response = await apiClient.post<ApiItem<CategorieCoiffure>>(`/admin/categories-coiffures/${id}`, formData)

  return normalizeCategorie(response.data.data)
}

export async function deleteCategorieCoiffure(id: number) {
  await apiClient.delete(`/admin/categories-coiffures/${id}`)
}

export async function getOptionsCoiffures(params?: QueryParams) {
  const response = await apiClient.get<ApiCollection<OptionCoiffure>>('/admin/options-coiffures', {
    params,
  })

  return collection(response.data, (item) => item)
}

export async function createOptionCoiffure(form: OptionForm) {
  const response = await apiClient.post<ApiItem<OptionCoiffure>>('/admin/options-coiffures', {
    nom: form.nom.trim(),
    prix: Number(form.prix),
    actif: form.actif,
  })

  return response.data.data
}

export async function updateOptionCoiffure(id: number, form: OptionForm) {
  const response = await apiClient.put<ApiItem<OptionCoiffure>>(`/admin/options-coiffures/${id}`, {
    nom: form.nom.trim(),
    prix: Number(form.prix),
    actif: form.actif,
  })

  return response.data.data
}

export async function deleteOptionCoiffure(id: number) {
  await apiClient.delete(`/admin/options-coiffures/${id}`)
}

export async function getCoiffures(params?: QueryParams) {
  const response = await apiClient.get<ApiCollection<Coiffure>>('/admin/coiffures', {
    params,
  })

  return collection(response.data, normalizeCoiffure)
}

export async function createCoiffure(form: CoiffureForm) {
  const response = await apiClient.post<ApiItem<Coiffure>>('/admin/coiffures', coiffureFormData(form))

  return normalizeCoiffure(response.data.data)
}

export async function updateCoiffure(id: number, form: CoiffureForm) {
  const formData = coiffureFormData(form)
  formData.append('_method', 'PUT')
  const response = await apiClient.post<ApiItem<Coiffure>>(`/admin/coiffures/${id}`, formData)

  return normalizeCoiffure(response.data.data)
}

export async function deleteCoiffure(id: number) {
  await apiClient.delete(`/admin/coiffures/${id}`)
}

export async function getVariantesCoiffures(params?: QueryParams) {
  const response = await apiClient.get<ApiCollection<VarianteCoiffure>>('/admin/variantes-coiffures', {
    params,
  })

  return collection(response.data, normalizeVariante)
}

export async function createVarianteCoiffure(form: VarianteForm) {
  const response = await apiClient.post<ApiItem<VarianteCoiffure>>('/admin/variantes-coiffures', {
    coiffure_id: Number(form.coiffure_id),
    nom: form.nom.trim(),
    prix: Number(form.prix),
    duree_minutes: Number(form.duree_minutes),
    actif: form.actif,
  })

  return normalizeVariante(response.data.data)
}

export async function updateVarianteCoiffure(id: number, form: VarianteForm) {
  const response = await apiClient.put<ApiItem<VarianteCoiffure>>(`/admin/variantes-coiffures/${id}`, {
    coiffure_id: Number(form.coiffure_id),
    nom: form.nom.trim(),
    prix: Number(form.prix),
    duree_minutes: Number(form.duree_minutes),
    actif: form.actif,
  })

  return normalizeVariante(response.data.data)
}

export async function deleteVarianteCoiffure(id: number) {
  await apiClient.delete(`/admin/variantes-coiffures/${id}`)
}
