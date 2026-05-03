import { useCallback, useEffect, useMemo, useRef, useState, type FormEvent } from 'react'
import {
  BadgePercent,
  CalendarClock,
  Edit,
  Eye,
  EyeOff,
  Gift,
  Plus,
  RefreshCw,
  Search,
  Sparkles,
  TicketPercent,
  Trash2,
} from 'lucide-react'
import AdminLayout from '../../../layouts/AdminLayout'
import {
  createCodePromo,
  createRegleFidelite,
  deleteCodePromo,
  deleteRegleFidelite,
  getCodesPromo,
  getReglesFidelite,
  updateCodePromo,
  updateRegleFidelite,
} from './promotions.api'
import {
  EmptyState,
  ErrorState,
  FormField,
  Modal,
  Pagination,
  StatusBadge,
  SuccessState,
  inputClass,
  primaryButtonClass,
  secondaryButtonClass,
} from './PromotionsUi'
import type {
  CodePromo,
  CodePromoForm,
  DiscountType,
  LaravelPaginated,
  RegleFidelite,
  RegleFideliteForm,
} from './promotions.types'

type TabId = 'codes' | 'loyalty'

const emptyCodeForm: CodePromoForm = {
  code: '',
  nom: '',
  type_reduction: 'pourcentage',
  valeur: '',
  date_debut: '',
  date_fin: '',
  limite_utilisation: '',
  actif: true,
}

const emptyRuleForm: RegleFideliteForm = {
  nom: '',
  nombre_reservations_requis: '9',
  type_recompense: 'pourcentage',
  valeur_recompense: '10',
  actif: true,
}

const tabs: Array<{ id: TabId; label: string; icon: typeof TicketPercent }> = [
  { id: 'codes', label: 'Codes promo', icon: TicketPercent },
  { id: 'loyalty', label: 'Regles fidelite', icon: Gift },
]

function numberValue(value: number | string) {
  return Number(value || 0)
}

function money(value: number | string) {
  return `${numberValue(value).toLocaleString('fr-FR')} FCFA`
}

function discountLabel(type: DiscountType, value: number | string) {
  return type === 'pourcentage' ? `${numberValue(value).toLocaleString('fr-FR')}%` : money(value)
}

function formatDateTime(value: string | null) {
  if (!value) {
    return 'Sans date'
  }

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
}

function toDateTimeInput(value: string | null) {
  if (!value) {
    return ''
  }

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value.slice(0, 16).replace(' ', 'T')
  }

  const offset = date.getTimezoneOffset() * 60000

  return new Date(date.getTime() - offset).toISOString().slice(0, 16)
}

function codeToForm(code: CodePromo): CodePromoForm {
  return {
    code: code.code,
    nom: code.nom ?? '',
    type_reduction: code.type_reduction,
    valeur: String(code.valeur ?? ''),
    date_debut: toDateTimeInput(code.date_debut),
    date_fin: toDateTimeInput(code.date_fin),
    limite_utilisation: code.limite_utilisation === null ? '' : String(code.limite_utilisation),
    actif: code.actif,
  }
}

function ruleToForm(rule: RegleFidelite): RegleFideliteForm {
  return {
    nom: rule.nom,
    nombre_reservations_requis: String(rule.nombre_reservations_requis),
    type_recompense: rule.type_recompense,
    valeur_recompense: String(rule.valeur_recompense ?? ''),
    actif: rule.actif,
  }
}

function promoState(code: CodePromo) {
  if (!code.actif) {
    return { label: 'Inactif', className: 'bg-gray-100 text-gray-500' }
  }

  const now = Date.now()
  const startsAt = code.date_debut ? new Date(code.date_debut).getTime() : null
  const endsAt = code.date_fin ? new Date(code.date_fin).getTime() : null

  if (startsAt && startsAt > now) {
    return { label: 'Programme', className: 'bg-sky-50 text-sky-700' }
  }

  if (endsAt && endsAt < now) {
    return { label: 'Expire', className: 'bg-amber-50 text-amber-700' }
  }

  if (code.limite_utilisation !== null && code.nombre_utilisations >= code.limite_utilisation) {
    return { label: 'Epuise', className: 'bg-orange-50 text-orange-700' }
  }

  return { label: 'Actif', className: 'bg-emerald-50 text-emerald-700' }
}

function promoPeriod(code: CodePromo) {
  if (!code.date_debut && !code.date_fin) {
    return 'Toujours disponible'
  }

  return `${code.date_debut ? formatDateTime(code.date_debut) : 'Maintenant'} -> ${
    code.date_fin ? formatDateTime(code.date_fin) : 'sans fin'
  }`
}

function validateCodeForm(form: CodePromoForm) {
  const value = Number(form.valeur)
  const limit = form.limite_utilisation.trim() === '' ? null : Number(form.limite_utilisation)

  if (form.code.trim() === '') {
    return 'Le code promo est obligatoire.'
  }

  if (!/^[A-Za-z0-9_\-\s]+$/.test(form.code)) {
    return 'Le code promo accepte seulement lettres, chiffres, tirets et underscores.'
  }

  if (Number.isNaN(value) || value < 0) {
    return 'La valeur de reduction doit etre valide.'
  }

  if (form.type_reduction === 'pourcentage' && value > 100) {
    return 'Une reduction en pourcentage ne peut pas depasser 100%.'
  }

  if (form.date_debut && form.date_fin && new Date(form.date_fin).getTime() < new Date(form.date_debut).getTime()) {
    return 'La date de fin doit etre apres la date de debut.'
  }

  if (limit !== null && (!Number.isInteger(limit) || limit < 1)) {
    return 'La limite d utilisation doit etre un entier positif.'
  }

  return null
}

function validateRuleForm(form: RegleFideliteForm) {
  const reservations = Number(form.nombre_reservations_requis)
  const value = Number(form.valeur_recompense)

  if (form.nom.trim() === '') {
    return 'Le nom de la regle est obligatoire.'
  }

  if (!Number.isInteger(reservations) || reservations < 1 || reservations > 1000) {
    return 'Le nombre de reservations doit etre un entier positif.'
  }

  if (Number.isNaN(value) || value < 0) {
    return 'La valeur de recompense doit etre valide.'
  }

  if (form.type_recompense === 'pourcentage' && value > 100) {
    return 'Une recompense en pourcentage ne peut pas depasser 100%.'
  }

  return null
}

function PromotionsPage() {
  const [activeTab, setActiveTab] = useState<TabId>('codes')
  const [codes, setCodes] = useState<LaravelPaginated<CodePromo> | null>(null)
  const [rules, setRules] = useState<LaravelPaginated<RegleFidelite> | null>(null)
  const [codeForm, setCodeForm] = useState<CodePromoForm>(emptyCodeForm)
  const [ruleForm, setRuleForm] = useState<RegleFideliteForm>(emptyRuleForm)
  const [editingCode, setEditingCode] = useState<CodePromo | null>(null)
  const [editingRule, setEditingRule] = useState<RegleFidelite | null>(null)
  const [codePage, setCodePage] = useState(1)
  const [rulePage, setRulePage] = useState(1)
  const [codeSearch, setCodeSearch] = useState('')
  const [ruleSearch, setRuleSearch] = useState('')
  const [codeStatus, setCodeStatus] = useState('all')
  const [ruleStatus, setRuleStatus] = useState('all')
  const [loadingCodes, setLoadingCodes] = useState(true)
  const [loadingRules, setLoadingRules] = useState(true)
  const [saving, setSaving] = useState(false)
  const [codeModalOpen, setCodeModalOpen] = useState(false)
  const [ruleModalOpen, setRuleModalOpen] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const codeFiltersReady = useRef(false)
  const ruleFiltersReady = useRef(false)

  const codeItems = useMemo(() => codes?.data ?? [], [codes])
  const ruleItems = useMemo(() => rules?.data ?? [], [rules])
  const activeCodesOnPage = useMemo(() => codeItems.filter((code) => code.actif).length, [codeItems])
  const activeRulesOnPage = useMemo(() => ruleItems.filter((rule) => rule.actif).length, [ruleItems])

  const loadCodes = useCallback(async (nextPage: number, nextSearch: string, nextStatus: string) => {
    setLoadingCodes(true)
    setError(null)
    try {
      setCodes(
        await getCodesPromo({
          page: nextPage,
          per_page: 12,
          search: nextSearch || undefined,
          actif: nextStatus === 'all' ? undefined : nextStatus === 'active',
        }),
      )
      setCodePage(nextPage)
    } catch {
      setError('Impossible de charger les codes promo.')
    } finally {
      setLoadingCodes(false)
    }
  }, [])

  const loadRules = useCallback(async (nextPage: number, nextSearch: string, nextStatus: string) => {
    setLoadingRules(true)
    setError(null)
    try {
      setRules(
        await getReglesFidelite({
          page: nextPage,
          per_page: 12,
          search: nextSearch || undefined,
          actif: nextStatus === 'all' ? undefined : nextStatus === 'active',
        }),
      )
      setRulePage(nextPage)
    } catch {
      setError('Impossible de charger les regles de fidelite.')
    } finally {
      setLoadingRules(false)
    }
  }, [])

  useEffect(() => {
    let cancelled = false

    getCodesPromo({ page: 1, per_page: 12 })
      .then((response) => {
        if (!cancelled) {
          setCodes(response)
          setCodePage(1)
        }
      })
      .catch(() => {
        if (!cancelled) {
          setError('Impossible de charger les codes promo.')
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoadingCodes(false)
        }
      })

    getReglesFidelite({ page: 1, per_page: 12 })
      .then((response) => {
        if (!cancelled) {
          setRules(response)
          setRulePage(1)
        }
      })
      .catch(() => {
        if (!cancelled) {
          setError('Impossible de charger les regles de fidelite.')
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoadingRules(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    if (!codeFiltersReady.current) {
      codeFiltersReady.current = true
      return
    }

    const timeoutId = window.setTimeout(() => {
      void loadCodes(1, codeSearch, codeStatus)
    }, 300)

    return () => window.clearTimeout(timeoutId)
  }, [codeSearch, codeStatus, loadCodes])

  useEffect(() => {
    if (!ruleFiltersReady.current) {
      ruleFiltersReady.current = true
      return
    }

    const timeoutId = window.setTimeout(() => {
      void loadRules(1, ruleSearch, ruleStatus)
    }, 300)

    return () => window.clearTimeout(timeoutId)
  }, [ruleSearch, ruleStatus, loadRules])

  const resetCodeModal = () => {
    setCodeForm(emptyCodeForm)
    setEditingCode(null)
    setCodeModalOpen(false)
  }

  const resetRuleModal = () => {
    setRuleForm(emptyRuleForm)
    setEditingRule(null)
    setRuleModalOpen(false)
  }

  const openCodeModal = (code?: CodePromo) => {
    setError(null)
    setSuccess(null)
    if (code) {
      setEditingCode(code)
      setCodeForm(codeToForm(code))
    } else {
      setEditingCode(null)
      setCodeForm(emptyCodeForm)
    }
    setCodeModalOpen(true)
  }

  const openRuleModal = (rule?: RegleFidelite) => {
    setError(null)
    setSuccess(null)
    if (rule) {
      setEditingRule(rule)
      setRuleForm(ruleToForm(rule))
    } else {
      setEditingRule(null)
      setRuleForm(emptyRuleForm)
    }
    setRuleModalOpen(true)
  }

  const openLoyaltyExample = () => {
    setError(null)
    setSuccess(null)
    setEditingRule(null)
    setRuleForm({
      nom: 'Reduction 10e reservation',
      nombre_reservations_requis: '9',
      type_recompense: 'pourcentage',
      valeur_recompense: '10',
      actif: true,
    })
    setRuleModalOpen(true)
  }

  const submitCode = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setError(null)
    setSuccess(null)

    const validationError = validateCodeForm(codeForm)
    if (validationError) {
      setError(validationError)
      return
    }

    setSaving(true)
    try {
      if (editingCode) {
        await updateCodePromo(editingCode.id, codeForm)
      } else {
        await createCodePromo(codeForm)
      }
      resetCodeModal()
      setSuccess(editingCode ? 'Code promo mis a jour.' : 'Code promo cree.')
      await loadCodes(1, codeSearch, codeStatus)
    } catch {
      setError('Enregistrement impossible. Verifiez le code, les dates et la reduction.')
    } finally {
      setSaving(false)
    }
  }

  const submitRule = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setError(null)
    setSuccess(null)

    const validationError = validateRuleForm(ruleForm)
    if (validationError) {
      setError(validationError)
      return
    }

    setSaving(true)
    try {
      if (editingRule) {
        await updateRegleFidelite(editingRule.id, ruleForm)
      } else {
        await createRegleFidelite(ruleForm)
      }
      resetRuleModal()
      setSuccess(editingRule ? 'Regle de fidelite mise a jour.' : 'Regle de fidelite creee.')
      await loadRules(1, ruleSearch, ruleStatus)
    } catch {
      setError('Enregistrement impossible. Verifiez la regle et la recompense.')
    } finally {
      setSaving(false)
    }
  }

  const toggleCode = async (code: CodePromo) => {
    try {
      await updateCodePromo(code.id, { ...codeToForm(code), actif: !code.actif })
      await loadCodes(codePage, codeSearch, codeStatus)
    } catch {
      setError('Changement de statut impossible pour ce code.')
    }
  }

  const toggleRule = async (rule: RegleFidelite) => {
    try {
      await updateRegleFidelite(rule.id, { ...ruleToForm(rule), actif: !rule.actif })
      await loadRules(rulePage, ruleSearch, ruleStatus)
    } catch {
      setError('Changement de statut impossible pour cette regle.')
    }
  }

  const removeCode = async (code: CodePromo) => {
    if (!window.confirm(`Supprimer le code "${code.code}" ?`)) {
      return
    }

    try {
      await deleteCodePromo(code.id)
      await loadCodes(codePage, codeSearch, codeStatus)
      setSuccess('Code promo supprime.')
    } catch {
      setError('Suppression impossible pour ce code promo.')
    }
  }

  const removeRule = async (rule: RegleFidelite) => {
    if (!window.confirm(`Supprimer la regle "${rule.nom}" ?`)) {
      return
    }

    try {
      await deleteRegleFidelite(rule.id)
      await loadRules(rulePage, ruleSearch, ruleStatus)
      setSuccess('Regle de fidelite supprimee.')
    } catch {
      setError('Suppression impossible pour cette regle.')
    }
  }

  const refreshCurrentTab = () => {
    if (activeTab === 'codes') {
      void loadCodes(codePage, codeSearch, codeStatus)
      return
    }

    void loadRules(rulePage, ruleSearch, ruleStatus)
  }

  const activeSearch = activeTab === 'codes' ? codeSearch : ruleSearch
  const activeStatus = activeTab === 'codes' ? codeStatus : ruleStatus
  const currentLoading = activeTab === 'codes' ? loadingCodes : loadingRules

  return (
    <AdminLayout>
      <div className="mb-5 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.18em] text-[#e91e63]">
            Promotions & Fidelite
          </p>
          <h1 className="mt-2 text-2xl font-black text-[#111018] sm:text-3xl">
            Codes promo et fidelite
          </h1>
          <p className="mt-2 max-w-3xl text-sm font-medium text-gray-500">
            Gere les remises applicables aux clientes et les recompenses apres reservations.
          </p>
        </div>
        <div className="flex flex-col gap-2 sm:flex-row">
          <button
            type="button"
            onClick={refreshCurrentTab}
            className={`${secondaryButtonClass} inline-flex items-center justify-center gap-2`}
          >
            <RefreshCw className={`h-4 w-4 ${currentLoading ? 'animate-spin' : ''}`} />
            Actualiser
          </button>
          <button
            type="button"
            onClick={() => (activeTab === 'codes' ? openCodeModal() : openRuleModal())}
            className={`${primaryButtonClass} inline-flex items-center justify-center gap-2`}
          >
            <Plus className="h-4 w-4" />
            {activeTab === 'codes' ? 'Nouveau code' : 'Nouvelle regle'}
          </button>
        </div>
      </div>

      <section className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div className="rounded-xl border border-[#f1e7ee] bg-white px-4 py-3 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.5)]">
          <p className="text-xs font-black uppercase tracking-[0.08em] text-gray-400">Codes promo</p>
          <p className="mt-1 text-2xl font-black text-[#111018]">{codes?.total ?? 0}</p>
          <p className="mt-1 text-xs font-bold text-gray-500">{activeCodesOnPage} actif(s) sur cette page</p>
        </div>
        <div className="rounded-xl border border-[#f1e7ee] bg-white px-4 py-3 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.5)]">
          <p className="text-xs font-black uppercase tracking-[0.08em] text-gray-400">Regles fidelite</p>
          <p className="mt-1 text-2xl font-black text-[#111018]">{rules?.total ?? 0}</p>
          <p className="mt-1 text-xs font-bold text-gray-500">{activeRulesOnPage} active(s) sur cette page</p>
        </div>
        <div className="rounded-xl border border-[#f1e7ee] bg-white px-4 py-3 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.5)]">
          <p className="text-xs font-black uppercase tracking-[0.08em] text-gray-400">Exemple fidelite</p>
          <p className="mt-1 text-lg font-black text-[#111018]">{'9 -> 10e'}</p>
          <p className="mt-1 text-xs font-bold text-gray-500">Reduction a la reservation suivante</p>
        </div>
        <div className="rounded-xl border border-[#f1e7ee] bg-white px-4 py-3 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.5)]">
          <p className="text-xs font-black uppercase tracking-[0.08em] text-gray-400">Devise</p>
          <p className="mt-1 text-2xl font-black text-[#111018]">FCFA</p>
          <p className="mt-1 text-xs font-bold text-gray-500">Montants fixes et recompenses</p>
        </div>
      </section>

      <div className="mb-5 flex gap-2 overflow-x-auto rounded-xl border border-[#f1e7ee] bg-white p-1 shadow-[0_14px_32px_-28px_rgba(20,20,43,0.55)]">
        {tabs.map((tab) => {
          const Icon = tab.icon
          const isActive = activeTab === tab.id

          return (
            <button
              key={tab.id}
              type="button"
              onClick={() => setActiveTab(tab.id)}
              className={[
                'inline-flex min-w-max items-center gap-2 rounded-lg px-4 py-2 text-sm font-bold transition',
                isActive ? 'bg-[#e91e63] text-white' : 'text-gray-500 hover:bg-[#fff2f7] hover:text-[#c41468]',
              ].join(' ')}
            >
              <Icon className="h-4 w-4" />
              {tab.label}
            </button>
          )
        })}
      </div>

      <section className="mb-5 rounded-xl border border-gray-100 bg-white p-4 shadow-[0_18px_36px_-32px_rgba(20,20,43,0.5)]">
        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div className="relative w-full lg:max-w-md">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input
              value={activeSearch}
              onChange={(event) =>
                activeTab === 'codes' ? setCodeSearch(event.target.value) : setRuleSearch(event.target.value)
              }
              className="w-full rounded-lg border border-gray-200 py-2.5 pl-10 pr-4 text-sm font-semibold outline-none focus:border-[#e91e63] focus:ring-4 focus:ring-[#e91e63]/10"
              placeholder={activeTab === 'codes' ? 'Rechercher un code...' : 'Rechercher une regle...'}
            />
          </div>
          <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
            <select
              value={activeStatus}
              onChange={(event) =>
                activeTab === 'codes' ? setCodeStatus(event.target.value) : setRuleStatus(event.target.value)
              }
              className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-bold text-gray-700 sm:w-auto"
            >
              <option value="all">Tous les statuts</option>
              <option value="active">Actifs</option>
              <option value="inactive">Inactifs</option>
            </select>
            {activeTab === 'loyalty' && (
              <button
                type="button"
                onClick={openLoyaltyExample}
                className={`${secondaryButtonClass} inline-flex justify-center gap-2`}
              >
                <Sparkles className="h-4 w-4" />
                {'Exemple 9 -> 10e'}
              </button>
            )}
          </div>
        </div>
      </section>

      {error && <div className="mb-5"><ErrorState label={error} /></div>}
      {success && <div className="mb-5"><SuccessState label={success} /></div>}

      {activeTab === 'codes' ? (
        <>
          <section className="grid gap-3 lg:hidden">
            {loadingCodes ? (
              Array.from({ length: 4 }).map((_, index) => (
                <article key={index} className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                  <div className="h-5 w-2/3 animate-pulse rounded bg-gray-100" />
                  <div className="mt-3 h-4 w-full animate-pulse rounded bg-gray-100" />
                  <div className="mt-3 h-4 w-1/2 animate-pulse rounded bg-gray-100" />
                </article>
              ))
            ) : codeItems.length === 0 ? (
              <EmptyState label="Aucun code promo trouve." />
            ) : (
              codeItems.map((code) => {
                const state = promoState(code)

                return (
                  <article key={code.id} className="rounded-xl border border-gray-100 bg-white p-4 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.55)]">
                    <div className="flex items-start justify-between gap-3">
                      <div className="min-w-0">
                        <h2 className="truncate text-base font-black text-gray-950">{code.code}</h2>
                        <p className="mt-1 line-clamp-2 text-sm font-semibold text-gray-500">
                          {code.nom || 'Code sans libelle'}
                        </p>
                      </div>
                      <span className={`shrink-0 rounded-full px-3 py-1 text-xs font-black ${state.className}`}>
                        {state.label}
                      </span>
                    </div>
                    <div className="mt-4 grid gap-2 text-sm font-bold text-gray-500">
                      <span className="inline-flex items-center gap-2 text-[#c41468]">
                        <BadgePercent className="h-4 w-4" />
                        {discountLabel(code.type_reduction, code.valeur)}
                      </span>
                      <span className="inline-flex items-center gap-2">
                        <CalendarClock className="h-4 w-4 text-gray-400" />
                        {promoPeriod(code)}
                      </span>
                      <span>
                        {code.nombre_utilisations} / {code.limite_utilisation ?? 'illimite'} utilisation(s)
                      </span>
                    </div>
                    <div className="mt-4 flex justify-end gap-1">
                      <button
                        type="button"
                        onClick={() => void toggleCode(code)}
                        className="flex h-9 w-9 items-center justify-center rounded-lg text-gray-600 transition hover:bg-gray-100"
                        title={code.actif ? 'Desactiver' : 'Activer'}
                      >
                        {code.actif ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                      </button>
                      <button
                        type="button"
                        onClick={() => openCodeModal(code)}
                        className="flex h-9 w-9 items-center justify-center rounded-lg text-indigo-600 transition hover:bg-indigo-50"
                        title="Modifier"
                      >
                        <Edit className="h-4 w-4" />
                      </button>
                      <button
                        type="button"
                        onClick={() => void removeCode(code)}
                        className="flex h-9 w-9 items-center justify-center rounded-lg text-red-600 transition hover:bg-red-50"
                        title="Supprimer"
                      >
                        <Trash2 className="h-4 w-4" />
                      </button>
                    </div>
                  </article>
                )
              })
            )}
          </section>

          <section className="hidden overflow-hidden rounded-xl border border-gray-100 bg-white shadow-[0_18px_36px_-32px_rgba(20,20,43,0.5)] lg:block">
            <div className="overflow-x-auto">
              <table className="w-full min-w-[980px] text-left text-sm">
                <thead className="bg-gray-50 text-xs font-black uppercase tracking-[0.12em] text-gray-500">
                  <tr>
                    <th className="px-5 py-3">Code</th>
                    <th className="px-5 py-3">Reduction</th>
                    <th className="px-5 py-3">Periode</th>
                    <th className="px-5 py-3">Utilisations</th>
                    <th className="px-5 py-3">Statut</th>
                    <th className="px-5 py-3 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {loadingCodes ? (
                    Array.from({ length: 5 }).map((_, row) => (
                      <tr key={row}>
                        {Array.from({ length: 6 }).map((__, cell) => (
                          <td key={cell} className="px-5 py-4">
                            <div className="h-5 animate-pulse rounded bg-gray-100" />
                          </td>
                        ))}
                      </tr>
                    ))
                  ) : codeItems.length === 0 ? (
                    <tr>
                      <td colSpan={6} className="px-5 py-8">
                        <EmptyState label="Aucun code promo trouve." />
                      </td>
                    </tr>
                  ) : (
                    codeItems.map((code) => {
                      const state = promoState(code)

                      return (
                        <tr key={code.id} className="transition hover:bg-[#fff8fb]">
                          <td className="px-5 py-4">
                            <div className="font-black text-gray-950">{code.code}</div>
                            <div className="max-w-[240px] truncate text-xs font-bold text-gray-400">
                              {code.nom || 'Code sans libelle'}
                            </div>
                          </td>
                          <td className="px-5 py-4 font-black text-[#c41468]">
                            {discountLabel(code.type_reduction, code.valeur)}
                          </td>
                          <td className="max-w-xs px-5 py-4 font-semibold text-gray-500">
                            <span className="line-clamp-2">{promoPeriod(code)}</span>
                          </td>
                          <td className="px-5 py-4 font-semibold text-gray-500">
                            {code.nombre_utilisations} / {code.limite_utilisation ?? 'illimite'}
                          </td>
                          <td className="px-5 py-4">
                            <span className={`rounded-full px-3 py-1 text-xs font-black ${state.className}`}>
                              {state.label}
                            </span>
                          </td>
                          <td className="px-5 py-4">
                            <div className="flex justify-end gap-1">
                              <button
                                type="button"
                                onClick={() => void toggleCode(code)}
                                className="flex h-9 w-9 items-center justify-center rounded-lg text-gray-600 transition hover:bg-gray-100"
                                title={code.actif ? 'Desactiver' : 'Activer'}
                              >
                                {code.actif ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                              </button>
                              <button
                                type="button"
                                onClick={() => openCodeModal(code)}
                                className="flex h-9 w-9 items-center justify-center rounded-lg text-indigo-600 transition hover:bg-indigo-50"
                                title="Modifier"
                              >
                                <Edit className="h-4 w-4" />
                              </button>
                              <button
                                type="button"
                                onClick={() => void removeCode(code)}
                                className="flex h-9 w-9 items-center justify-center rounded-lg text-red-600 transition hover:bg-red-50"
                                title="Supprimer"
                              >
                                <Trash2 className="h-4 w-4" />
                              </button>
                            </div>
                          </td>
                        </tr>
                      )
                    })
                  )}
                </tbody>
              </table>
            </div>
          </section>

          {codes && (
            <Pagination
              page={codePage}
              lastPage={codes.last_page}
              total={codes.total}
              onPrevious={() => void loadCodes(codePage - 1, codeSearch, codeStatus)}
              onNext={() => void loadCodes(codePage + 1, codeSearch, codeStatus)}
            />
          )}
        </>
      ) : (
        <>
          <section className="grid gap-3 lg:hidden">
            {loadingRules ? (
              Array.from({ length: 4 }).map((_, index) => (
                <article key={index} className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                  <div className="h-5 w-2/3 animate-pulse rounded bg-gray-100" />
                  <div className="mt-3 h-4 w-full animate-pulse rounded bg-gray-100" />
                  <div className="mt-3 h-4 w-1/2 animate-pulse rounded bg-gray-100" />
                </article>
              ))
            ) : ruleItems.length === 0 ? (
              <EmptyState label="Aucune regle de fidelite trouvee." />
            ) : (
              ruleItems.map((rule) => (
                <article key={rule.id} className="rounded-xl border border-gray-100 bg-white p-4 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.55)]">
                  <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                      <h2 className="truncate text-base font-black text-gray-950">{rule.nom}</h2>
                      <p className="mt-1 text-sm font-semibold text-gray-500">
                        Apres {rule.nombre_reservations_requis} reservation(s)
                      </p>
                    </div>
                    <StatusBadge active={rule.actif} />
                  </div>
                  <div className="mt-4 flex items-center justify-between gap-3">
                    <span className="inline-flex items-center gap-2 text-sm font-black text-[#c41468]">
                      <Gift className="h-4 w-4" />
                      {discountLabel(rule.type_recompense, rule.valeur_recompense)}
                    </span>
                    <div className="flex justify-end gap-1">
                      <button
                        type="button"
                        onClick={() => void toggleRule(rule)}
                        className="flex h-9 w-9 items-center justify-center rounded-lg text-gray-600 transition hover:bg-gray-100"
                        title={rule.actif ? 'Desactiver' : 'Activer'}
                      >
                        {rule.actif ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                      </button>
                      <button
                        type="button"
                        onClick={() => openRuleModal(rule)}
                        className="flex h-9 w-9 items-center justify-center rounded-lg text-indigo-600 transition hover:bg-indigo-50"
                        title="Modifier"
                      >
                        <Edit className="h-4 w-4" />
                      </button>
                      <button
                        type="button"
                        onClick={() => void removeRule(rule)}
                        className="flex h-9 w-9 items-center justify-center rounded-lg text-red-600 transition hover:bg-red-50"
                        title="Supprimer"
                      >
                        <Trash2 className="h-4 w-4" />
                      </button>
                    </div>
                  </div>
                </article>
              ))
            )}
          </section>

          <section className="hidden overflow-hidden rounded-xl border border-gray-100 bg-white shadow-[0_18px_36px_-32px_rgba(20,20,43,0.5)] lg:block">
            <div className="overflow-x-auto">
              <table className="w-full min-w-[860px] text-left text-sm">
                <thead className="bg-gray-50 text-xs font-black uppercase tracking-[0.12em] text-gray-500">
                  <tr>
                    <th className="px-5 py-3">Regle</th>
                    <th className="px-5 py-3">Declenchement</th>
                    <th className="px-5 py-3">Recompense</th>
                    <th className="px-5 py-3">Statut</th>
                    <th className="px-5 py-3 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {loadingRules ? (
                    Array.from({ length: 5 }).map((_, row) => (
                      <tr key={row}>
                        {Array.from({ length: 5 }).map((__, cell) => (
                          <td key={cell} className="px-5 py-4">
                            <div className="h-5 animate-pulse rounded bg-gray-100" />
                          </td>
                        ))}
                      </tr>
                    ))
                  ) : ruleItems.length === 0 ? (
                    <tr>
                      <td colSpan={5} className="px-5 py-8">
                        <EmptyState label="Aucune regle de fidelite trouvee." />
                      </td>
                    </tr>
                  ) : (
                    ruleItems.map((rule) => (
                      <tr key={rule.id} className="transition hover:bg-[#fff8fb]">
                        <td className="px-5 py-4">
                          <div className="font-black text-gray-950">{rule.nom}</div>
                          <div className="text-xs font-bold text-gray-400">#{rule.id}</div>
                        </td>
                        <td className="px-5 py-4 font-semibold text-gray-500">
                          Apres {rule.nombre_reservations_requis} reservation(s)
                        </td>
                        <td className="px-5 py-4 font-black text-[#c41468]">
                          {discountLabel(rule.type_recompense, rule.valeur_recompense)}
                        </td>
                        <td className="px-5 py-4">
                          <StatusBadge active={rule.actif} />
                        </td>
                        <td className="px-5 py-4">
                          <div className="flex justify-end gap-1">
                            <button
                              type="button"
                              onClick={() => void toggleRule(rule)}
                              className="flex h-9 w-9 items-center justify-center rounded-lg text-gray-600 transition hover:bg-gray-100"
                              title={rule.actif ? 'Desactiver' : 'Activer'}
                            >
                              {rule.actif ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                            </button>
                            <button
                              type="button"
                              onClick={() => openRuleModal(rule)}
                              className="flex h-9 w-9 items-center justify-center rounded-lg text-indigo-600 transition hover:bg-indigo-50"
                              title="Modifier"
                            >
                              <Edit className="h-4 w-4" />
                            </button>
                            <button
                              type="button"
                              onClick={() => void removeRule(rule)}
                              className="flex h-9 w-9 items-center justify-center rounded-lg text-red-600 transition hover:bg-red-50"
                              title="Supprimer"
                            >
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
          </section>

          {rules && (
            <Pagination
              page={rulePage}
              lastPage={rules.last_page}
              total={rules.total}
              onPrevious={() => void loadRules(rulePage - 1, ruleSearch, ruleStatus)}
              onNext={() => void loadRules(rulePage + 1, ruleSearch, ruleStatus)}
            />
          )}
        </>
      )}

      {codeModalOpen && (
        <Modal title={editingCode ? 'Modifier code promo' : 'Nouveau code promo'} onClose={resetCodeModal}>
          <form onSubmit={submitCode} className="space-y-5">
            <div className="grid gap-4 sm:grid-cols-2">
              <FormField label="Code promo">
                <input
                  className={inputClass}
                  value={codeForm.code}
                  onChange={(event) => setCodeForm((current) => ({ ...current, code: event.target.value }))}
                  required
                  placeholder="WELCOME10"
                />
              </FormField>
              <FormField label="Nom interne">
                <input
                  className={inputClass}
                  value={codeForm.nom}
                  onChange={(event) => setCodeForm((current) => ({ ...current, nom: event.target.value }))}
                  placeholder="Offre lancement"
                />
              </FormField>
              <FormField label="Type reduction">
                <select
                  className={inputClass}
                  value={codeForm.type_reduction}
                  onChange={(event) =>
                    setCodeForm((current) => ({
                      ...current,
                      type_reduction: event.target.value as DiscountType,
                    }))
                  }
                >
                  <option value="pourcentage">Pourcentage</option>
                  <option value="montant">Montant fixe</option>
                </select>
              </FormField>
              <FormField label={codeForm.type_reduction === 'pourcentage' ? 'Pourcentage' : 'Montant'}>
                <div className="relative">
                  <input
                    className={`${inputClass} pr-10`}
                    type="number"
                    min="0"
                    max={codeForm.type_reduction === 'pourcentage' ? 100 : undefined}
                    step={codeForm.type_reduction === 'pourcentage' ? '0.01' : '100'}
                    value={codeForm.valeur}
                    onChange={(event) => setCodeForm((current) => ({ ...current, valeur: event.target.value }))}
                    required
                  />
                  <BadgePercent className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                </div>
              </FormField>
              <FormField label="Date debut">
                <input
                  className={inputClass}
                  type="datetime-local"
                  value={codeForm.date_debut}
                  onChange={(event) => setCodeForm((current) => ({ ...current, date_debut: event.target.value }))}
                />
              </FormField>
              <FormField label="Date fin">
                <input
                  className={inputClass}
                  type="datetime-local"
                  value={codeForm.date_fin}
                  onChange={(event) => setCodeForm((current) => ({ ...current, date_fin: event.target.value }))}
                />
              </FormField>
              <FormField label="Limite utilisation" hint="Vide = illimite">
                <input
                  className={inputClass}
                  type="number"
                  min="1"
                  step="1"
                  value={codeForm.limite_utilisation}
                  onChange={(event) =>
                    setCodeForm((current) => ({ ...current, limite_utilisation: event.target.value }))
                  }
                />
              </FormField>
              <label className="flex items-center gap-3 rounded-lg border border-gray-100 px-3 py-3 text-sm font-bold sm:self-end">
                <input
                  type="checkbox"
                  checked={codeForm.actif}
                  onChange={(event) => setCodeForm((current) => ({ ...current, actif: event.target.checked }))}
                />
                Code actif
              </label>
            </div>
            <div className="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
              <button type="button" onClick={resetCodeModal} className={secondaryButtonClass}>
                Annuler
              </button>
              <button type="submit" disabled={saving} className={`${primaryButtonClass} inline-flex items-center justify-center gap-2`}>
                {saving && <RefreshCw className="h-4 w-4 animate-spin" />}
                {saving ? 'Enregistrement...' : editingCode ? 'Modifier' : 'Creer'}
              </button>
            </div>
          </form>
        </Modal>
      )}

      {ruleModalOpen && (
        <Modal title={editingRule ? 'Modifier regle fidelite' : 'Nouvelle regle fidelite'} onClose={resetRuleModal}>
          <form onSubmit={submitRule} className="space-y-5">
            <div className="grid gap-4 sm:grid-cols-2">
              <FormField label="Nom de la regle">
                <input
                  className={inputClass}
                  value={ruleForm.nom}
                  onChange={(event) => setRuleForm((current) => ({ ...current, nom: event.target.value }))}
                  required
                  placeholder="Reduction 10e reservation"
                />
              </FormField>
              <FormField label="Reservations requises">
                <input
                  className={inputClass}
                  type="number"
                  min="1"
                  max="1000"
                  step="1"
                  value={ruleForm.nombre_reservations_requis}
                  onChange={(event) =>
                    setRuleForm((current) => ({
                      ...current,
                      nombre_reservations_requis: event.target.value,
                    }))
                  }
                  required
                />
              </FormField>
              <FormField label="Type recompense">
                <select
                  className={inputClass}
                  value={ruleForm.type_recompense}
                  onChange={(event) =>
                    setRuleForm((current) => ({
                      ...current,
                      type_recompense: event.target.value as DiscountType,
                    }))
                  }
                >
                  <option value="pourcentage">Pourcentage</option>
                  <option value="montant">Montant fixe</option>
                </select>
              </FormField>
              <FormField label={ruleForm.type_recompense === 'pourcentage' ? 'Pourcentage' : 'Montant'}>
                <div className="relative">
                  <input
                    className={`${inputClass} pr-10`}
                    type="number"
                    min="0"
                    max={ruleForm.type_recompense === 'pourcentage' ? 100 : undefined}
                    step={ruleForm.type_recompense === 'pourcentage' ? '0.01' : '100'}
                    value={ruleForm.valeur_recompense}
                    onChange={(event) =>
                      setRuleForm((current) => ({ ...current, valeur_recompense: event.target.value }))
                    }
                    required
                  />
                  <BadgePercent className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                </div>
              </FormField>
              <label className="flex items-center gap-3 rounded-lg border border-gray-100 px-3 py-3 text-sm font-bold sm:self-end">
                <input
                  type="checkbox"
                  checked={ruleForm.actif}
                  onChange={(event) => setRuleForm((current) => ({ ...current, actif: event.target.checked }))}
                />
                Regle active
              </label>
            </div>
            <div className="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
              <button type="button" onClick={resetRuleModal} className={secondaryButtonClass}>
                Annuler
              </button>
              <button type="submit" disabled={saving} className={`${primaryButtonClass} inline-flex items-center justify-center gap-2`}>
                {saving && <RefreshCw className="h-4 w-4 animate-spin" />}
                {saving ? 'Enregistrement...' : editingRule ? 'Modifier' : 'Creer'}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </AdminLayout>
  )
}

export default PromotionsPage
