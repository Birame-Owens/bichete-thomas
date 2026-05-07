import { apiAssetUrl } from '../../../lib/apiAssets'
import { apiClient } from '../../../lib/apiClient'
import type {
  ApiCollection,
  ApiItem,
  CoiffureReview,
  LaravelPaginated,
  ReviewStatus,
  ReviewSummary,
} from './reviews.types'

type ReviewQueryParams = {
  page?: number
  per_page?: number
  search?: string
  statut?: ReviewStatus | 'all'
}

type CollectionResult<T, M = unknown> = {
  data: LaravelPaginated<T>
  meta?: M
}

function normalizeReview(review: CoiffureReview): CoiffureReview {
  return {
    ...review,
    photo_url: apiAssetUrl(review.photo_url),
    coiffure: review.coiffure
      ? {
          ...review.coiffure,
          image: apiAssetUrl(review.coiffure.image),
        }
      : review.coiffure,
  }
}

function collection<T extends CoiffureReview, M = unknown>(payload: ApiCollection<T, M>): CollectionResult<T, M> {
  return {
    data: {
      ...payload.data,
      data: payload.data.data.map((item) => normalizeReview(item) as T),
    },
    meta: payload.meta,
  }
}

export async function getCoiffureReviews(params?: ReviewQueryParams) {
  const response = await apiClient.get<ApiCollection<CoiffureReview, ReviewSummary>>('/admin/avis-coiffures', {
    params: {
      ...params,
      statut: params?.statut === 'all' ? undefined : params?.statut,
    },
  })

  return collection(response.data)
}

export async function approveCoiffureReview(id: number) {
  const response = await apiClient.patch<ApiItem<CoiffureReview>>(`/admin/avis-coiffures/${id}/approuver`, {})

  return normalizeReview(response.data.data)
}

export async function rejectCoiffureReview(id: number) {
  const response = await apiClient.patch<ApiItem<CoiffureReview>>(`/admin/avis-coiffures/${id}/rejeter`, {})

  return normalizeReview(response.data.data)
}

export async function deleteCoiffureReview(id: number) {
  await apiClient.delete(`/admin/avis-coiffures/${id}`)
}
