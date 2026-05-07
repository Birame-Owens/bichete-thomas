import { useCallback, useEffect, useMemo, useRef, useState, type FormEvent } from 'react'
import {
  Banknote,
  CalendarDays,
  Edit,
  FolderPlus,
  Plus,
  RefreshCw,
  Search,
  Tags,
  Trash2,
  WalletCards,
} from 'lucide-react'
import AdminLayout from '../../../layouts/AdminLayout'
import {
  createExpense,
  createExpenseCategory,
  deleteExpense,
  deleteExpenseCategory,
  getExpenseCategories,
  getExpenses,
  updateExpense,
  updateExpenseCategory,
} from './expenses.api'
import type {
  Expense,
  ExpenseCategory,
  ExpenseCategoryForm,
  ExpenseForm,
  ExpenseSummary,
  LaravelPaginated,
} from './expenses.types'
import {
  EmptyState,
  ErrorState,
  FormField,
  Modal,
  Pagination,
  SuccessState,
  dangerButtonClass,
  inputClass,
  primaryButtonClass,
  secondaryButtonClass,
} from '../payments/PaymentsUi'

const paymentModes = [
  { value: 'especes', label: 'Especes' },
  { value: 'wave', label: 'Wave' },
  { value: 'orange_money', label: 'Orange Money' },
  { value: 'carte_bancaire', label: 'Carte bancaire' },
  { value: 'virement', label: 'Virement' },
  { value: 'cheque', label: 'Cheque' },
  { value: 'autre', label: 'Autre' },
]

const emptySummary: ExpenseSummary = {
  total_montant: 0,
  nombre_depenses: 0,
  total_mois_courant: 0,
  total_aujourdhui: 0,
}

const emptyExpenseForm = (): ExpenseForm => ({
  categorie_depense_id: '',
  titre: '',
  montant: '',
  date_depense: todayInput(),
  mode_paiement: 'especes',
  reference: '',
  description: '',
})

const emptyCategoryForm: ExpenseCategoryForm = {
  nom: '',
  description: '',
  actif: true,
}

function todayInput() {
  const now = new Date()
  const offset = now.getTimezoneOffset() * 60000

  return new Date(now.getTime() - offset).toISOString().slice(0, 10)
}

function numberValue(value: number | string | null | undefined) {
  return Number(value ?? 0)
}

function money(value: number | string | null | undefined) {
  return `${numberValue(value).toLocaleString('fr-FR')} FCFA`
}

function formatDate(value?: string | null) {
  if (!value) {
    return '-'
  }

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(date)
}

function modeLabel(value?: string | null) {
  if (!value) {
    return 'Non renseigne'
  }

  return paymentModes.find((mode) => mode.value === value)?.label ?? value
}

function expenseToForm(expense: Expense): ExpenseForm {
  return {
    categorie_depense_id: expense.categorie_depense_id ? String(expense.categorie_depense_id) : '',
    titre: expense.titre,
    montant: String(expense.montant ?? ''),
    date_depense: String(expense.date_depense ?? '').slice(0, 10),
    mode_paiement: expense.mode_paiement ?? 'especes',
    reference: expense.reference ?? '',
    description: expense.description ?? '',
  }
}

function categoryToForm(category: ExpenseCategory): ExpenseCategoryForm {
  return {
    nom: category.nom,
    description: category.description ?? '',
    actif: category.actif,
  }
}

function validateExpense(form: ExpenseForm) {
  const amount = Number(form.montant)

  if (form.titre.trim() === '') {
    return 'Le titre de la depense est obligatoire.'
  }

  if (Number.isNaN(amount) || amount <= 0) {
    return 'Le montant doit etre superieur a zero.'
  }

  if (!form.date_depense) {
    return 'La date de depense est obligatoire.'
  }

  return null
}

function validateCategory(form: ExpenseCategoryForm) {
  if (form.nom.trim() === '') {
    return 'Le nom de la categorie est obligatoire.'
  }

  return null
}

function KpiCard({
  label,
  value,
  helper,
  icon: Icon,
  tone = 'pink',
}: {
  label: string
  value: string
  helper: string
  icon: typeof Banknote
  tone?: 'pink' | 'amber' | 'emerald' | 'violet'
}) {
  const tones = {
    pink: 'bg-[#fff2f7] text-[#e91e63]',
    amber: 'bg-amber-50 text-amber-700',
    emerald: 'bg-emerald-50 text-emerald-700',
    violet: 'bg-violet-50 text-violet-700',
  }

  return (
    <div className="rounded-xl border border-[#f1e7ee] bg-white px-4 py-3 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.5)]">
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-xs font-black uppercase text-gray-400">{label}</p>
          <p className="mt-1 truncate text-2xl font-black text-[#111018]">{value}</p>
          <p className="mt-1 text-xs font-bold text-gray-500">{helper}</p>
        </div>
        <span className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-full ${tones[tone]}`}>
          <Icon className="h-5 w-5" />
        </span>
      </div>
    </div>
  )
}

function ExpensesPage() {
  const [items, setItems] = useState<LaravelPaginated<Expense> | null>(null)
  const [summary, setSummary] = useState<ExpenseSummary>(emptySummary)
  const [categories, setCategories] = useState<ExpenseCategory[]>([])
  const [expenseForm, setExpenseForm] = useState<ExpenseForm>(emptyExpenseForm)
  const [categoryForm, setCategoryForm] = useState<ExpenseCategoryForm>(emptyCategoryForm)
  const [editingExpense, setEditingExpense] = useState<Expense | null>(null)
  const [editingCategory, setEditingCategory] = useState<ExpenseCategory | null>(null)
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [categoryFilter, setCategoryFilter] = useState('all')
  const [modeFilter, setModeFilter] = useState('all')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [loading, setLoading] = useState(true)
  const [categoriesLoading, setCategoriesLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [expenseModalOpen, setExpenseModalOpen] = useState(false)
  const [categoryModalOpen, setCategoryModalOpen] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const filtersReady = useRef(false)

  const expenses = useMemo(() => items?.data ?? [], [items])
  const activeCategories = useMemo(() => categories.filter((category) => category.actif), [categories])
  const averageExpense = summary.nombre_depenses > 0 ? summary.total_montant / summary.nombre_depenses : 0

  const loadPage = useCallback(
    async (
      nextPage: number,
      nextSearch: string,
      nextCategory: string,
      nextMode: string,
      nextDateFrom: string,
      nextDateTo: string,
    ) => {
      setLoading(true)
      setError(null)

      try {
        const response = await getExpenses({
          page: nextPage,
          per_page: 12,
          search: nextSearch || undefined,
          categorie_depense_id: nextCategory === 'all' ? undefined : nextCategory,
          mode_paiement: nextMode === 'all' ? undefined : nextMode,
          date_debut: nextDateFrom || undefined,
          date_fin: nextDateTo || undefined,
        })

        setItems(response.data)
        setSummary(response.meta ?? emptySummary)
        setPage(nextPage)
      } catch {
        setError('Impossible de charger les depenses.')
      } finally {
        setLoading(false)
      }
    },
    [],
  )

  const loadCategories = useCallback(async () => {
    setCategoriesLoading(true)

    try {
      const response = await getExpenseCategories({ page: 1, per_page: 100 })
      setCategories(response.data)
    } catch {
      setError('Impossible de charger les categories de depenses.')
    } finally {
      setCategoriesLoading(false)
    }
  }, [])

  useEffect(() => {
    let cancelled = false

    Promise.all([
      getExpenses({ page: 1, per_page: 12 }),
      getExpenseCategories({ page: 1, per_page: 100 }),
    ])
      .then(([expensesResponse, categoriesResponse]) => {
        if (!cancelled) {
          setItems(expensesResponse.data)
          setSummary(expensesResponse.meta ?? emptySummary)
          setCategories(categoriesResponse.data)
          setPage(1)
        }
      })
      .catch(() => {
        if (!cancelled) {
          setError('Impossible de charger le module depenses.')
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false)
          setCategoriesLoading(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    if (!filtersReady.current) {
      filtersReady.current = true
      return
    }

    const timeoutId = window.setTimeout(() => {
      void loadPage(1, search, categoryFilter, modeFilter, dateFrom, dateTo)
    }, 300)

    return () => window.clearTimeout(timeoutId)
  }, [categoryFilter, dateFrom, dateTo, loadPage, modeFilter, search])

  const resetExpenseModal = () => {
    setExpenseForm(emptyExpenseForm())
    setEditingExpense(null)
    setExpenseModalOpen(false)
  }

  const resetCategoryModal = () => {
    setCategoryForm(emptyCategoryForm)
    setEditingCategory(null)
    setCategoryModalOpen(false)
  }

  const openExpenseModal = (expense?: Expense) => {
    setError(null)
    setSuccess(null)
    if (expense) {
      setEditingExpense(expense)
      setExpenseForm(expenseToForm(expense))
    } else {
      setEditingExpense(null)
      setExpenseForm(emptyExpenseForm())
    }
    setExpenseModalOpen(true)
  }

  const openCategoryModal = (category?: ExpenseCategory) => {
    setError(null)
    setSuccess(null)
    if (category) {
      setEditingCategory(category)
      setCategoryForm(categoryToForm(category))
    } else {
      setEditingCategory(null)
      setCategoryForm(emptyCategoryForm)
    }
    setCategoryModalOpen(true)
  }

  const refresh = () => {
    void loadPage(page, search, categoryFilter, modeFilter, dateFrom, dateTo)
    void loadCategories()
  }

  const submitExpense = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setError(null)
    setSuccess(null)

    const validationError = validateExpense(expenseForm)
    if (validationError) {
      setError(validationError)
      return
    }

    setSaving(true)
    try {
      if (editingExpense) {
        await updateExpense(editingExpense.id, expenseForm)
      } else {
        await createExpense(expenseForm)
      }
      resetExpenseModal()
      setSuccess(editingExpense ? 'Depense mise a jour.' : 'Depense enregistree.')
      await loadPage(1, search, categoryFilter, modeFilter, dateFrom, dateTo)
      await loadCategories()
    } catch {
      setError('Enregistrement impossible. Verifiez le montant et la categorie.')
    } finally {
      setSaving(false)
    }
  }

  const submitCategory = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setError(null)
    setSuccess(null)

    const validationError = validateCategory(categoryForm)
    if (validationError) {
      setError(validationError)
      return
    }

    setSaving(true)
    try {
      if (editingCategory) {
        await updateExpenseCategory(editingCategory.id, categoryForm)
      } else {
        await createExpenseCategory(categoryForm)
      }
      resetCategoryModal()
      setSuccess(editingCategory ? 'Categorie mise a jour.' : 'Categorie creee.')
      await loadCategories()
    } catch {
      setError('Enregistrement impossible. Le nom existe peut-etre deja.')
    } finally {
      setSaving(false)
    }
  }

  const removeExpense = async (expense: Expense) => {
    if (!window.confirm(`Supprimer la depense "${expense.titre}" ?`)) {
      return
    }

    try {
      await deleteExpense(expense.id)
      setSuccess('Depense supprimee.')
      await loadPage(page, search, categoryFilter, modeFilter, dateFrom, dateTo)
      await loadCategories()
    } catch {
      setError('Suppression impossible pour cette depense.')
    }
  }

  const removeCategory = async (category: ExpenseCategory) => {
    if (!window.confirm(`Supprimer la categorie "${category.nom}" ? Les depenses seront conservees sans categorie.`)) {
      return
    }

    try {
      await deleteExpenseCategory(category.id)
      setSuccess('Categorie supprimee.')
      await loadCategories()
      await loadPage(page, search, categoryFilter, modeFilter, dateFrom, dateTo)
    } catch {
      setError('Suppression impossible pour cette categorie.')
    }
  }

  return (
    <AdminLayout>
      <div className="mb-5 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <p className="text-xs font-bold uppercase text-[#e91e63]">Gestion depenses</p>
          <h1 className="mt-2 text-2xl font-black text-[#111018] sm:text-3xl">Depenses</h1>
          <p className="mt-2 max-w-3xl text-sm font-medium text-gray-500">
            Suivez les achats, charges, fournitures, loyers, salaires et autres sorties du salon.
          </p>
        </div>
        <div className="flex flex-col gap-2 sm:flex-row">
          <button type="button" onClick={refresh} className={`${secondaryButtonClass} inline-flex items-center justify-center gap-2`}>
            <RefreshCw className={`h-4 w-4 ${loading || categoriesLoading ? 'animate-spin' : ''}`} />
            Actualiser
          </button>
          <button type="button" onClick={() => openCategoryModal()} className={`${secondaryButtonClass} inline-flex items-center justify-center gap-2`}>
            <FolderPlus className="h-4 w-4" />
            Categorie
          </button>
          <button type="button" onClick={() => openExpenseModal()} className={`${primaryButtonClass} inline-flex items-center justify-center gap-2`}>
            <Plus className="h-4 w-4" />
            Nouvelle depense
          </button>
        </div>
      </div>

      <section className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <KpiCard label="Depenses filtrees" value={money(summary.total_montant)} helper={`${summary.nombre_depenses} ligne(s)`} icon={Banknote} />
        <KpiCard label="Ce mois" value={money(summary.total_mois_courant)} helper="Toutes categories" icon={CalendarDays} tone="violet" />
        <KpiCard label="Aujourd hui" value={money(summary.total_aujourdhui)} helper="Sorties du jour" icon={WalletCards} tone="amber" />
        <KpiCard label="Moyenne" value={money(averageExpense)} helper="Par depense filtree" icon={Tags} tone="emerald" />
      </section>

      {error && <div className="mb-4"><ErrorState label={error} /></div>}
      {success && <div className="mb-4"><SuccessState label={success} /></div>}

      <section className="mb-5 rounded-xl border border-gray-100 bg-white p-4 shadow-[0_18px_36px_-32px_rgba(20,20,43,0.5)]">
        <div className="grid gap-3 xl:grid-cols-[minmax(220px,1fr)_repeat(5,auto)] xl:items-center">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              className="w-full rounded-lg border border-gray-200 py-2.5 pl-10 pr-4 text-sm font-semibold outline-none focus:border-[#e91e63] focus:ring-4 focus:ring-[#e91e63]/10"
              placeholder="Titre, reference, description..."
            />
          </div>
          <select value={categoryFilter} onChange={(event) => setCategoryFilter(event.target.value)} className={inputClass}>
            <option value="all">Toutes categories</option>
            {categories.map((category) => (
              <option key={category.id} value={category.id}>{category.nom}</option>
            ))}
          </select>
          <select value={modeFilter} onChange={(event) => setModeFilter(event.target.value)} className={inputClass}>
            <option value="all">Tous paiements</option>
            {paymentModes.map((mode) => (
              <option key={mode.value} value={mode.value}>{mode.label}</option>
            ))}
          </select>
          <input type="date" value={dateFrom} onChange={(event) => setDateFrom(event.target.value)} className={inputClass} />
          <input type="date" value={dateTo} onChange={(event) => setDateTo(event.target.value)} className={inputClass} />
          <button
            type="button"
            onClick={() => {
              setSearch('')
              setCategoryFilter('all')
              setModeFilter('all')
              setDateFrom('')
              setDateTo('')
            }}
            className={secondaryButtonClass}
          >
            Reinitialiser
          </button>
        </div>
      </section>

      <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_310px]">
        <section>
          <div className="grid gap-3 lg:hidden">
            {loading ? (
              Array.from({ length: 4 }).map((_, index) => (
                <article key={index} className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                  <div className="h-5 w-2/3 animate-pulse rounded bg-gray-100" />
                  <div className="mt-3 h-4 w-full animate-pulse rounded bg-gray-100" />
                  <div className="mt-3 h-4 w-1/2 animate-pulse rounded bg-gray-100" />
                </article>
              ))
            ) : expenses.length === 0 ? (
              <EmptyState label="Aucune depense trouvee." />
            ) : (
              expenses.map((expense) => (
                <article key={expense.id} className="rounded-xl border border-gray-100 bg-white p-4 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.55)]">
                  <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                      <h2 className="truncate text-base font-black text-gray-950">{expense.titre}</h2>
                      <p className="mt-1 text-xs font-bold text-gray-400">
                        {formatDate(expense.date_depense)} - {expense.categorie?.nom ?? 'Sans categorie'}
                      </p>
                    </div>
                    <p className="shrink-0 text-lg font-black text-[#c41468]">{money(expense.montant)}</p>
                  </div>
                  <div className="mt-4 flex items-center justify-between gap-3">
                    <span className="rounded-full bg-[#fff2f7] px-3 py-1 text-xs font-black text-[#c41468]">
                      {modeLabel(expense.mode_paiement)}
                    </span>
                    <div className="flex justify-end gap-1">
                      <button type="button" onClick={() => openExpenseModal(expense)} className="flex h-9 w-9 items-center justify-center rounded-lg text-indigo-600 transition hover:bg-indigo-50" title="Modifier">
                        <Edit className="h-4 w-4" />
                      </button>
                      <button type="button" onClick={() => void removeExpense(expense)} className="flex h-9 w-9 items-center justify-center rounded-lg text-red-600 transition hover:bg-red-50" title="Supprimer">
                        <Trash2 className="h-4 w-4" />
                      </button>
                    </div>
                  </div>
                </article>
              ))
            )}
          </div>

          <div className="hidden overflow-hidden rounded-xl border border-gray-100 bg-white shadow-[0_18px_36px_-32px_rgba(20,20,43,0.5)] lg:block">
            <div className="overflow-x-auto">
              <table className="w-full min-w-[940px] text-left text-sm">
                <thead className="bg-gray-50 text-xs font-black uppercase text-gray-500">
                  <tr>
                    <th className="px-5 py-3">Depense</th>
                    <th className="px-5 py-3">Categorie</th>
                    <th className="px-5 py-3">Date</th>
                    <th className="px-5 py-3">Paiement</th>
                    <th className="px-5 py-3">Reference</th>
                    <th className="px-5 py-3 text-right">Montant</th>
                    <th className="px-5 py-3 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {loading ? (
                    Array.from({ length: 5 }).map((_, row) => (
                      <tr key={row}>
                        {Array.from({ length: 7 }).map((__, cell) => (
                          <td key={cell} className="px-5 py-4">
                            <div className="h-5 animate-pulse rounded bg-gray-100" />
                          </td>
                        ))}
                      </tr>
                    ))
                  ) : expenses.length === 0 ? (
                    <tr>
                      <td colSpan={7} className="px-5 py-8">
                        <EmptyState label="Aucune depense trouvee." />
                      </td>
                    </tr>
                  ) : (
                    expenses.map((expense) => (
                      <tr key={expense.id} className="align-top transition hover:bg-[#fff8fb]">
                        <td className="px-5 py-4">
                          <div className="font-black text-gray-950">{expense.titre}</div>
                          <div className="mt-1 line-clamp-1 text-xs font-semibold text-gray-400">
                            {expense.description ?? 'Aucune description'}
                          </div>
                        </td>
                        <td className="px-5 py-4 font-bold text-gray-600">{expense.categorie?.nom ?? 'Sans categorie'}</td>
                        <td className="px-5 py-4 font-semibold text-gray-500">{formatDate(expense.date_depense)}</td>
                        <td className="px-5 py-4 font-semibold text-gray-500">{modeLabel(expense.mode_paiement)}</td>
                        <td className="px-5 py-4 font-semibold text-gray-500">{expense.reference ?? '-'}</td>
                        <td className="px-5 py-4 text-right font-black text-[#c41468]">{money(expense.montant)}</td>
                        <td className="px-5 py-4">
                          <div className="flex justify-end gap-1">
                            <button type="button" onClick={() => openExpenseModal(expense)} className="flex h-9 w-9 items-center justify-center rounded-lg text-indigo-600 transition hover:bg-indigo-50" title="Modifier">
                              <Edit className="h-4 w-4" />
                            </button>
                            <button type="button" onClick={() => void removeExpense(expense)} className="flex h-9 w-9 items-center justify-center rounded-lg text-red-600 transition hover:bg-red-50" title="Supprimer">
                              <Trash2 className="h-4 w-4" />
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>

          {items && (
            <Pagination
              page={page}
              lastPage={items.last_page}
              total={items.total}
              onPrevious={() => void loadPage(page - 1, search, categoryFilter, modeFilter, dateFrom, dateTo)}
              onNext={() => void loadPage(page + 1, search, categoryFilter, modeFilter, dateFrom, dateTo)}
            />
          )}
        </section>

        <aside className="rounded-xl border border-gray-100 bg-white p-4 shadow-[0_18px_36px_-32px_rgba(20,20,43,0.5)]">
          <div className="mb-4 flex items-center justify-between gap-3">
            <div>
              <h2 className="text-lg font-black text-gray-950">Categories</h2>
              <p className="text-xs font-bold text-gray-400">{activeCategories.length} active(s)</p>
            </div>
            <button type="button" onClick={() => openCategoryModal()} className="flex h-9 w-9 items-center justify-center rounded-lg bg-[#fff2f7] text-[#c41468]" title="Ajouter categorie">
              <Plus className="h-4 w-4" />
            </button>
          </div>
          <div className="space-y-2">
            {categoriesLoading ? (
              Array.from({ length: 4 }).map((_, index) => (
                <div key={index} className="h-14 animate-pulse rounded-lg bg-gray-100" />
              ))
            ) : categories.length === 0 ? (
              <EmptyState label="Aucune categorie." />
            ) : (
              categories.map((category) => (
                <div key={category.id} className="rounded-lg border border-gray-100 px-3 py-2">
                  <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                      <p className="truncate text-sm font-black text-gray-950">{category.nom}</p>
                      <p className="mt-0.5 text-xs font-semibold text-gray-400">
                        {category.depenses_count ?? 0} depense(s)
                      </p>
                    </div>
                    <span className={`rounded-full px-2 py-1 text-[11px] font-black ${category.actif ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'}`}>
                      {category.actif ? 'Actif' : 'Inactif'}
                    </span>
                  </div>
                  <div className="mt-2 flex justify-end gap-1">
                    <button type="button" onClick={() => openCategoryModal(category)} className="flex h-8 w-8 items-center justify-center rounded-lg text-indigo-600 transition hover:bg-indigo-50" title="Modifier categorie">
                      <Edit className="h-4 w-4" />
                    </button>
                    <button type="button" onClick={() => void removeCategory(category)} className="flex h-8 w-8 items-center justify-center rounded-lg text-red-600 transition hover:bg-red-50" title="Supprimer categorie">
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </div>
                </div>
              ))
            )}
          </div>
        </aside>
      </div>

      {expenseModalOpen && (
        <Modal title={editingExpense ? 'Modifier depense' : 'Nouvelle depense'} onClose={resetExpenseModal} wide>
          <form onSubmit={submitExpense} className="space-y-5">
            <div className="grid gap-4 lg:grid-cols-3">
              <FormField label="Titre">
                <input className={inputClass} value={expenseForm.titre} onChange={(event) => setExpenseForm((current) => ({ ...current, titre: event.target.value }))} placeholder="Achat meches, loyer, transport..." required />
              </FormField>
              <FormField label="Categorie">
                <select className={inputClass} value={expenseForm.categorie_depense_id} onChange={(event) => setExpenseForm((current) => ({ ...current, categorie_depense_id: event.target.value }))}>
                  <option value="">Sans categorie</option>
                  {activeCategories.map((category) => (
                    <option key={category.id} value={category.id}>{category.nom}</option>
                  ))}
                </select>
              </FormField>
              <FormField label="Montant">
                <input className={inputClass} type="number" min="1" step="1" value={expenseForm.montant} onChange={(event) => setExpenseForm((current) => ({ ...current, montant: event.target.value }))} placeholder="25000" required />
              </FormField>
              <FormField label="Date depense">
                <input className={inputClass} type="date" value={expenseForm.date_depense} onChange={(event) => setExpenseForm((current) => ({ ...current, date_depense: event.target.value }))} required />
              </FormField>
              <FormField label="Mode paiement">
                <select className={inputClass} value={expenseForm.mode_paiement} onChange={(event) => setExpenseForm((current) => ({ ...current, mode_paiement: event.target.value }))}>
                  {paymentModes.map((mode) => (
                    <option key={mode.value} value={mode.value}>{mode.label}</option>
                  ))}
                </select>
              </FormField>
              <FormField label="Reference" hint="Ticket, facture, transaction ou note interne">
                <input className={inputClass} value={expenseForm.reference} onChange={(event) => setExpenseForm((current) => ({ ...current, reference: event.target.value }))} placeholder="FAC-2026-001" />
              </FormField>
              <FormField label="Description">
                <textarea className={inputClass} rows={4} value={expenseForm.description} onChange={(event) => setExpenseForm((current) => ({ ...current, description: event.target.value }))} placeholder="Details utiles pour retrouver la depense..." />
              </FormField>
              <div className="rounded-xl border border-[#f1e7ee] bg-[#fff8fb] p-4 lg:col-span-2">
                <p className="text-sm font-black text-[#c41468]">Resume</p>
                <div className="mt-3 grid gap-2 text-sm font-bold text-gray-600 sm:grid-cols-3">
                  <span>Montant : {money(expenseForm.montant || 0)}</span>
                  <span>Date : {expenseForm.date_depense ? formatDate(expenseForm.date_depense) : '-'}</span>
                  <span>Paiement : {modeLabel(expenseForm.mode_paiement)}</span>
                </div>
              </div>
            </div>
            <div className="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
              <button type="button" onClick={resetExpenseModal} className={secondaryButtonClass}>Annuler</button>
              <button type="submit" disabled={saving} className={`${primaryButtonClass} inline-flex items-center justify-center gap-2`}>
                {saving && <RefreshCw className="h-4 w-4 animate-spin" />}
                {saving ? 'Enregistrement...' : editingExpense ? 'Modifier' : 'Enregistrer'}
              </button>
            </div>
          </form>
        </Modal>
      )}

      {categoryModalOpen && (
        <Modal title={editingCategory ? 'Modifier categorie' : 'Nouvelle categorie'} onClose={resetCategoryModal}>
          <form onSubmit={submitCategory} className="space-y-5">
            <div className="grid gap-4 sm:grid-cols-2">
              <FormField label="Nom">
                <input className={inputClass} value={categoryForm.nom} onChange={(event) => setCategoryForm((current) => ({ ...current, nom: event.target.value }))} placeholder="Fournitures" required />
              </FormField>
              <label className="flex items-center gap-3 rounded-lg border border-gray-100 px-3 py-3 text-sm font-bold sm:self-end">
                <input type="checkbox" checked={categoryForm.actif} onChange={(event) => setCategoryForm((current) => ({ ...current, actif: event.target.checked }))} />
                Categorie active
              </label>
              <FormField label="Description">
                <textarea className={inputClass} rows={4} value={categoryForm.description} onChange={(event) => setCategoryForm((current) => ({ ...current, description: event.target.value }))} placeholder="A quoi sert cette categorie ?" />
              </FormField>
            </div>
            <div className="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-between">
              {editingCategory ? (
                <button type="button" onClick={() => void removeCategory(editingCategory)} className={dangerButtonClass}>
                  Supprimer
                </button>
              ) : (
                <span />
              )}
              <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button type="button" onClick={resetCategoryModal} className={secondaryButtonClass}>Annuler</button>
                <button type="submit" disabled={saving} className={`${primaryButtonClass} inline-flex items-center justify-center gap-2`}>
                  {saving && <RefreshCw className="h-4 w-4 animate-spin" />}
                  {saving ? 'Enregistrement...' : editingCategory ? 'Modifier' : 'Creer'}
                </button>
              </div>
            </div>
          </form>
        </Modal>
      )}
    </AdminLayout>
  )
}

export default ExpensesPage
