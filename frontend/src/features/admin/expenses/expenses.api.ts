import { apiClient } from '../../../lib/apiClient'
import type {
  ApiCollection,
  ApiItem,
  Expense,
  ExpenseCategory,
  ExpenseCategoryForm,
  ExpenseForm,
  ExpenseSummary,
  LaravelPaginated,
} from './expenses.types'

type ExpenseQueryParams = {
  page?: number
  per_page?: number
  search?: string
  categorie_depense_id?: number | string
  mode_paiement?: string
  date_debut?: string
  date_fin?: string
}

type CategoryQueryParams = {
  page?: number
  per_page?: number
  search?: string
  actif?: boolean
}

type CollectionResult<T, M = unknown> = {
  data: LaravelPaginated<T>
  meta?: M
}

function collection<T, M = unknown>(payload: ApiCollection<T, M>): CollectionResult<T, M> {
  return {
    data: payload.data,
    meta: payload.meta,
  }
}

function nullableNumber(value: string) {
  return value.trim() === '' ? null : Number(value)
}

function nullableText(value: string) {
  const text = value.trim()

  return text === '' ? null : text
}

function expensePayload(form: ExpenseForm) {
  return {
    categorie_depense_id: nullableNumber(form.categorie_depense_id),
    titre: form.titre.trim(),
    montant: Number(form.montant),
    date_depense: form.date_depense,
    mode_paiement: nullableText(form.mode_paiement),
    reference: nullableText(form.reference),
    description: nullableText(form.description),
  }
}

function categoryPayload(form: ExpenseCategoryForm) {
  return {
    nom: form.nom.trim(),
    description: nullableText(form.description),
    actif: form.actif,
  }
}

export async function getExpenses(params?: ExpenseQueryParams) {
  const response = await apiClient.get<ApiCollection<Expense, ExpenseSummary>>('/admin/depenses', {
    params,
  })

  return collection(response.data)
}

export async function createExpense(form: ExpenseForm) {
  const response = await apiClient.post<ApiItem<Expense>>('/admin/depenses', expensePayload(form))

  return response.data
}

export async function updateExpense(id: number, form: ExpenseForm) {
  const response = await apiClient.put<ApiItem<Expense>>(`/admin/depenses/${id}`, expensePayload(form))

  return response.data
}

export async function deleteExpense(id: number) {
  await apiClient.delete(`/admin/depenses/${id}`)
}

export async function getExpenseCategories(params?: CategoryQueryParams) {
  const response = await apiClient.get<ApiCollection<ExpenseCategory>>('/admin/categories-depenses', {
    params,
  })

  return response.data.data
}

export async function createExpenseCategory(form: ExpenseCategoryForm) {
  const response = await apiClient.post<ApiItem<ExpenseCategory>>('/admin/categories-depenses', categoryPayload(form))

  return response.data
}

export async function updateExpenseCategory(id: number, form: ExpenseCategoryForm) {
  const response = await apiClient.put<ApiItem<ExpenseCategory>>(`/admin/categories-depenses/${id}`, categoryPayload(form))

  return response.data
}

export async function deleteExpenseCategory(id: number) {
  await apiClient.delete(`/admin/categories-depenses/${id}`)
}
