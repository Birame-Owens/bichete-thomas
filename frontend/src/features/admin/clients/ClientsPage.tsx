import { useCallback, useEffect, useMemo, useRef, useState, type FormEvent } from 'react'
import {
  Ban,
  Bell,
  BellOff,
  Edit,
  Eye,
  Gift,
  Mail,
  MessageCircle,
  Phone,
  Plus,
  RefreshCw,
  Search,
  ShieldCheck,
  ShieldOff,
  Star,
  Trash2,
  UserRound,
} from 'lucide-react'
import AdminLayout from '../../../layouts/AdminLayout'
import {
  blacklistClient,
  createClient,
  deleteClient,
  getClient,
  getClients,
  preferencesToForm,
  unblacklistClient,
  updateClient,
  updateClientPreferences,
} from './clients.api'
import {
  EmptyState,
  ErrorState,
  FormField,
  Modal,
  Pagination,
  StatusBadge,
  SuccessState,
  dangerButtonClass,
  inputClass,
  primaryButtonClass,
  secondaryButtonClass,
} from './ClientsUi'
import type {
  Client,
  ClientForm,
  ClientPreferencesForm,
  ClientSource,
  LaravelPaginated,
  ListeNoireClient,
} from './clients.types'

const emptyForm: ClientForm = {
  nom: '',
  prenom: '',
  telephone: '',
  email: '',
  source: 'physique',
  nombre_reservations_terminees: '0',
  fidelite_disponible: false,
}

const emptyPreferences: ClientPreferencesForm = {
  coiffures_preferees: '',
  options_preferees: '',
  notes: '',
  notifications_whatsapp: true,
  notifications_promos: true,
}

function clientName(client: Client) {
  return `${client.prenom} ${client.nom}`.trim()
}

function sourceLabel(source: ClientSource) {
  return source === 'en_ligne' ? 'En ligne' : 'Physique'
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

function tags(value?: string[] | null) {
  return value && value.length > 0 ? value : []
}

function clientToForm(client: Client): ClientForm {
  return {
    nom: client.nom,
    prenom: client.prenom,
    telephone: client.telephone,
    email: client.email ?? '',
    source: client.source,
    nombre_reservations_terminees: String(client.nombre_reservations_terminees ?? 0),
    fidelite_disponible: client.fidelite_disponible,
  }
}

function validateClientForm(form: ClientForm) {
  const reservations = Number(form.nombre_reservations_terminees)

  if (form.nom.trim() === '' || form.prenom.trim() === '') {
    return 'Le nom et le prenom sont obligatoires.'
  }

  if (!/^\+?[0-9\s().-]{7,30}$/.test(form.telephone.trim())) {
    return 'Le telephone doit contenir entre 7 et 30 caracteres numeriques.'
  }

  if (form.email.trim() !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) {
    return 'L email client doit etre valide.'
  }

  if (!Number.isInteger(reservations) || reservations < 0) {
    return 'Le nombre de reservations terminees doit etre un entier positif.'
  }

  return null
}

function validatePreferencesForm(form: ClientPreferencesForm) {
  if (form.notes.length > 5000) {
    return 'Les notes internes ne doivent pas depasser 5000 caracteres.'
  }

  return null
}

function ClientsPage() {
  const [items, setItems] = useState<LaravelPaginated<Client> | null>(null)
  const [form, setForm] = useState<ClientForm>(emptyForm)
  const [preferencesForm, setPreferencesForm] = useState<ClientPreferencesForm>(emptyPreferences)
  const [editingClient, setEditingClient] = useState<Client | null>(null)
  const [preferencesClient, setPreferencesClient] = useState<Client | null>(null)
  const [detailClient, setDetailClient] = useState<Client | null>(null)
  const [blacklistTarget, setBlacklistTarget] = useState<Client | null>(null)
  const [blacklistReason, setBlacklistReason] = useState('')
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [sourceFilter, setSourceFilter] = useState('all')
  const [statusFilter, setStatusFilter] = useState('all')
  const [loyaltyFilter, setLoyaltyFilter] = useState('all')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [detailLoading, setDetailLoading] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)
  const [preferencesModalOpen, setPreferencesModalOpen] = useState(false)
  const [detailModalOpen, setDetailModalOpen] = useState(false)
  const [blacklistModalOpen, setBlacklistModalOpen] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const filtersReady = useRef(false)

  const clients = useMemo(() => items?.data ?? [], [items])
  const blacklistedOnPage = useMemo(() => clients.filter((client) => client.est_blackliste).length, [clients])
  const loyaltyReadyOnPage = useMemo(() => clients.filter((client) => client.fidelite_disponible).length, [clients])
  const whatsappOnPage = useMemo(
    () => clients.filter((client) => client.preferences?.notifications_whatsapp ?? true).length,
    [clients],
  )

  const loadPage = useCallback(async (nextPage: number, nextSearch: string, nextSource: string, nextStatus: string, nextLoyalty: string) => {
    setLoading(true)
    setError(null)
    try {
      setItems(
        await getClients({
          page: nextPage,
          per_page: 12,
          search: nextSearch || undefined,
          source: nextSource === 'all' ? undefined : nextSource,
          blackliste: nextStatus === 'all' ? undefined : nextStatus === 'blocked',
          fidelite_disponible: nextLoyalty === 'all' ? undefined : nextLoyalty === 'ready',
        }),
      )
      setPage(nextPage)
    } catch {
      setError('Impossible de charger les clients.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    let cancelled = false

    getClients({ page: 1, per_page: 12 })
      .then((response) => {
        if (!cancelled) {
          setItems(response)
          setPage(1)
        }
      })
      .catch(() => {
        if (!cancelled) {
          setError('Impossible de charger les clients.')
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false)
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
      void loadPage(1, search, sourceFilter, statusFilter, loyaltyFilter)
    }, 300)

    return () => window.clearTimeout(timeoutId)
  }, [loadPage, loyaltyFilter, search, sourceFilter, statusFilter])

  const resetForm = () => {
    setForm(emptyForm)
    setEditingClient(null)
    setModalOpen(false)
  }

  const openClientModal = (client?: Client) => {
    setError(null)
    setSuccess(null)
    if (client) {
      setEditingClient(client)
      setForm(clientToForm(client))
    } else {
      setEditingClient(null)
      setForm(emptyForm)
    }
    setModalOpen(true)
  }

  const openPreferencesModal = (client: Client) => {
    setError(null)
    setSuccess(null)
    setPreferencesClient(client)
    setPreferencesForm(preferencesToForm(client))
    setPreferencesModalOpen(true)
  }

  const closePreferencesModal = () => {
    setPreferencesClient(null)
    setPreferencesForm(emptyPreferences)
    setPreferencesModalOpen(false)
  }

  const openBlacklistModal = (client: Client) => {
    setError(null)
    setSuccess(null)
    setBlacklistTarget(client)
    setBlacklistReason(client.blacklist_active?.raison ?? '')
    setBlacklistModalOpen(true)
  }

  const closeBlacklistModal = () => {
    setBlacklistTarget(null)
    setBlacklistReason('')
    setBlacklistModalOpen(false)
  }

  const openDetailModal = async (client: Client) => {
    setError(null)
    setSuccess(null)
    setDetailClient(client)
    setDetailModalOpen(true)
    setDetailLoading(true)
    try {
      setDetailClient(await getClient(client.id))
    } catch {
      setError('Impossible de charger la fiche client.')
    } finally {
      setDetailLoading(false)
    }
  }

  const refreshDetail = async (clientId: number) => {
    if (!detailModalOpen) {
      return
    }

    try {
      setDetailClient(await getClient(clientId))
    } catch {
      setDetailClient(null)
    }
  }

  const submitClient = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setError(null)
    setSuccess(null)

    const validationError = validateClientForm(form)
    if (validationError) {
      setError(validationError)
      return
    }

    const isEditing = editingClient !== null
    setSaving(true)
    try {
      if (editingClient) {
        await updateClient(editingClient.id, form)
      } else {
        await createClient(form)
      }
      resetForm()
      setSuccess(isEditing ? 'Client mis a jour.' : 'Client cree.')
      await loadPage(1, search, sourceFilter, statusFilter, loyaltyFilter)
    } catch {
      setError('Enregistrement impossible. Verifiez le telephone et les champs.')
    } finally {
      setSaving(false)
    }
  }

  const submitPreferences = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (!preferencesClient) {
      return
    }

    setError(null)
    setSuccess(null)

    const validationError = validatePreferencesForm(preferencesForm)
    if (validationError) {
      setError(validationError)
      return
    }

    setSaving(true)
    try {
      await updateClientPreferences(preferencesClient.id, preferencesForm)
      const clientId = preferencesClient.id
      closePreferencesModal()
      setSuccess('Preferences client mises a jour.')
      await loadPage(page, search, sourceFilter, statusFilter, loyaltyFilter)
      await refreshDetail(clientId)
    } catch {
      setError('Sauvegarde des preferences impossible.')
    } finally {
      setSaving(false)
    }
  }

  const submitBlacklist = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (!blacklistTarget) {
      return
    }

    setError(null)
    setSuccess(null)
    setSaving(true)
    try {
      await blacklistClient(blacklistTarget.id, blacklistReason)
      const clientId = blacklistTarget.id
      closeBlacklistModal()
      setSuccess('Client ajoute a la liste noire.')
      await loadPage(page, search, sourceFilter, statusFilter, loyaltyFilter)
      await refreshDetail(clientId)
    } catch {
      setError('Impossible de bloquer ce client.')
    } finally {
      setSaving(false)
    }
  }

  const unblock = async (client: Client) => {
    try {
      await unblacklistClient(client.id)
      setSuccess('Client retire de la liste noire.')
      await loadPage(page, search, sourceFilter, statusFilter, loyaltyFilter)
      await refreshDetail(client.id)
    } catch {
      setError('Impossible de debloquer ce client.')
    }
  }

  const remove = async (client: Client) => {
    if (!window.confirm(`Supprimer le client "${clientName(client)}" ?`)) {
      return
    }

    try {
      await deleteClient(client.id)
      setSuccess('Client supprime.')
      await loadPage(page, search, sourceFilter, statusFilter, loyaltyFilter)
    } catch {
      setError('Suppression impossible pour ce client.')
    }
  }

  const blacklistHistory = detailClient?.liste_noire ?? []

  return (
    <AdminLayout>
      <div className="mb-5 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.18em] text-[#e91e63]">
            Module clients
          </p>
          <h1 className="mt-2 text-2xl font-black text-[#111018] sm:text-3xl">Clients</h1>
          <p className="mt-2 max-w-3xl text-sm font-medium text-gray-500">
            Fiches clientes, preferences, fidelite, notifications et liste noire.
          </p>
        </div>
        <div className="flex flex-col gap-2 sm:flex-row">
          <button
            type="button"
            onClick={() => void loadPage(page, search, sourceFilter, statusFilter, loyaltyFilter)}
            className={`${secondaryButtonClass} inline-flex items-center justify-center gap-2`}
          >
            <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
            Actualiser
          </button>
          <button
            type="button"
            onClick={() => openClientModal()}
            className={`${primaryButtonClass} inline-flex items-center justify-center gap-2`}
          >
            <Plus className="h-4 w-4" />
            Nouveau client
          </button>
        </div>
      </div>

      <section className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div className="rounded-xl border border-[#f1e7ee] bg-white px-4 py-3 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.5)]">
          <p className="text-xs font-black uppercase tracking-[0.08em] text-gray-400">Clients</p>
          <p className="mt-1 text-2xl font-black text-[#111018]">{items?.total ?? 0}</p>
          <p className="mt-1 text-xs font-bold text-gray-500">Base client admin</p>
        </div>
        <div className="rounded-xl border border-[#f1e7ee] bg-white px-4 py-3 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.5)]">
          <p className="text-xs font-black uppercase tracking-[0.08em] text-gray-400">Bloques</p>
          <p className="mt-1 text-2xl font-black text-[#111018]">{blacklistedOnPage}</p>
          <p className="mt-1 text-xs font-bold text-gray-500">Sur cette page</p>
        </div>
        <div className="rounded-xl border border-[#f1e7ee] bg-white px-4 py-3 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.5)]">
          <p className="text-xs font-black uppercase tracking-[0.08em] text-gray-400">Fidelite</p>
          <p className="mt-1 text-2xl font-black text-[#111018]">{loyaltyReadyOnPage}</p>
          <p className="mt-1 text-xs font-bold text-gray-500">Recompense disponible</p>
        </div>
        <div className="rounded-xl border border-[#f1e7ee] bg-white px-4 py-3 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.5)]">
          <p className="text-xs font-black uppercase tracking-[0.08em] text-gray-400">WhatsApp</p>
          <p className="mt-1 text-2xl font-black text-[#111018]">{whatsappOnPage}</p>
          <p className="mt-1 text-xs font-bold text-gray-500">Notifications activees</p>
        </div>
      </section>

      <section className="mb-5 rounded-xl border border-gray-100 bg-white p-4 shadow-[0_18px_36px_-32px_rgba(20,20,43,0.5)]">
        <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
          <div className="relative w-full xl:max-w-md">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              className="w-full rounded-lg border border-gray-200 py-2.5 pl-10 pr-4 text-sm font-semibold outline-none focus:border-[#e91e63] focus:ring-4 focus:ring-[#e91e63]/10"
              placeholder="Rechercher nom, telephone, email..."
            />
          </div>
          <div className="grid gap-2 sm:grid-cols-3 xl:flex xl:items-center">
            <select
              value={sourceFilter}
              onChange={(event) => setSourceFilter(event.target.value)}
              className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-bold text-gray-700 xl:w-auto"
            >
              <option value="all">Toutes sources</option>
              <option value="physique">Physique</option>
              <option value="en_ligne">En ligne</option>
            </select>
            <select
              value={statusFilter}
              onChange={(event) => setStatusFilter(event.target.value)}
              className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-bold text-gray-700 xl:w-auto"
            >
              <option value="all">Tous statuts</option>
              <option value="clear">Actifs</option>
              <option value="blocked">Bloques</option>
            </select>
            <select
              value={loyaltyFilter}
              onChange={(event) => setLoyaltyFilter(event.target.value)}
              className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-bold text-gray-700 xl:w-auto"
            >
              <option value="all">Toute fidelite</option>
              <option value="ready">Recompense dispo</option>
              <option value="none">Sans recompense</option>
            </select>
          </div>
        </div>
      </section>

      {error && <div className="mb-5"><ErrorState label={error} /></div>}
      {success && <div className="mb-5"><SuccessState label={success} /></div>}

      <section className="grid gap-3 lg:hidden">
        {loading ? (
          Array.from({ length: 4 }).map((_, index) => (
            <article key={index} className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
              <div className="h-5 w-2/3 animate-pulse rounded bg-gray-100" />
              <div className="mt-3 h-4 w-full animate-pulse rounded bg-gray-100" />
              <div className="mt-3 h-4 w-1/2 animate-pulse rounded bg-gray-100" />
            </article>
          ))
        ) : clients.length === 0 ? (
          <EmptyState label="Aucun client trouve." />
        ) : (
          clients.map((client) => (
            <article key={client.id} className="rounded-xl border border-gray-100 bg-white p-4 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.55)]">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <h2 className="truncate text-base font-black text-gray-950">{clientName(client)}</h2>
                  <p className="mt-1 flex items-center gap-2 truncate text-sm font-semibold text-gray-500">
                    <Phone className="h-4 w-4 shrink-0 text-[#e91e63]" />
                    {client.telephone}
                  </p>
                  {client.email && (
                    <p className="mt-1 flex items-center gap-2 truncate text-sm font-semibold text-gray-500">
                      <Mail className="h-4 w-4 shrink-0 text-gray-400" />
                      {client.email}
                    </p>
                  )}
                </div>
                <StatusBadge blacklisted={client.est_blackliste} />
              </div>
              <div className="mt-4 grid gap-2 text-sm font-bold text-gray-500">
                <span>{sourceLabel(client.source)}</span>
                <span className="inline-flex items-center gap-2 text-[#c41468]">
                  <Gift className="h-4 w-4" />
                  {client.nombre_reservations_terminees} reservation(s) terminee(s)
                </span>
                <span>{client.fidelite_disponible ? 'Recompense fidelite disponible' : 'Fidelite en progression'}</span>
              </div>
              <div className="mt-4 flex flex-wrap justify-end gap-1">
                <button type="button" onClick={() => void openDetailModal(client)} className="flex h-9 w-9 items-center justify-center rounded-lg text-gray-600 transition hover:bg-gray-100" title="Fiche">
                  <Eye className="h-4 w-4" />
                </button>
                <button type="button" onClick={() => openPreferencesModal(client)} className="flex h-9 w-9 items-center justify-center rounded-lg text-[#c41468] transition hover:bg-[#fff2f7]" title="Preferences">
                  <Star className="h-4 w-4" />
                </button>
                {client.est_blackliste ? (
                  <button type="button" onClick={() => void unblock(client)} className="flex h-9 w-9 items-center justify-center rounded-lg text-emerald-700 transition hover:bg-emerald-50" title="Debloquer">
                    <ShieldCheck className="h-4 w-4" />
                  </button>
                ) : (
                  <button type="button" onClick={() => openBlacklistModal(client)} className="flex h-9 w-9 items-center justify-center rounded-lg text-red-600 transition hover:bg-red-50" title="Bloquer">
                    <Ban className="h-4 w-4" />
                  </button>
                )}
                <button type="button" onClick={() => openClientModal(client)} className="flex h-9 w-9 items-center justify-center rounded-lg text-indigo-600 transition hover:bg-indigo-50" title="Modifier">
                  <Edit className="h-4 w-4" />
                </button>
                <button type="button" onClick={() => void remove(client)} className="flex h-9 w-9 items-center justify-center rounded-lg text-red-600 transition hover:bg-red-50" title="Supprimer">
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            </article>
          ))
        )}
      </section>

      <section className="hidden overflow-hidden rounded-xl border border-gray-100 bg-white shadow-[0_18px_36px_-32px_rgba(20,20,43,0.5)] lg:block">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[1040px] text-left text-sm">
            <thead className="bg-gray-50 text-xs font-black uppercase tracking-[0.12em] text-gray-500">
              <tr>
                <th className="px-5 py-3">Client</th>
                <th className="px-5 py-3">Contact</th>
                <th className="px-5 py-3">Source</th>
                <th className="px-5 py-3">Fidelite</th>
                <th className="px-5 py-3">Notifications</th>
                <th className="px-5 py-3">Statut</th>
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
              ) : clients.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-5 py-8">
                    <EmptyState label="Aucun client trouve." />
                  </td>
                </tr>
              ) : (
                clients.map((client) => (
                  <tr key={client.id} className="transition hover:bg-[#fff8fb]">
                    <td className="px-5 py-4">
                      <div className="font-black text-gray-950">{clientName(client)}</div>
                      <div className="text-xs font-bold text-gray-400">#{client.id}</div>
                    </td>
                    <td className="px-5 py-4">
                      <div className="font-semibold text-gray-700">{client.telephone}</div>
                      <div className="max-w-[220px] truncate text-xs font-bold text-gray-400">
                        {client.email || 'Email non renseigne'}
                      </div>
                    </td>
                    <td className="px-5 py-4 font-semibold text-gray-500">{sourceLabel(client.source)}</td>
                    <td className="px-5 py-4">
                      <div className="font-black text-[#c41468]">{client.nombre_reservations_terminees}</div>
                      <div className="text-xs font-bold text-gray-400">
                        {client.fidelite_disponible ? 'Recompense disponible' : 'En progression'}
                      </div>
                    </td>
                    <td className="px-5 py-4">
                      <div className="flex gap-2">
                        <span className={`flex h-8 w-8 items-center justify-center rounded-lg ${client.preferences?.notifications_whatsapp ?? true ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-400'}`} title="WhatsApp">
                          <MessageCircle className="h-4 w-4" />
                        </span>
                        <span className={`flex h-8 w-8 items-center justify-center rounded-lg ${client.preferences?.notifications_promos ?? true ? 'bg-[#fff2f7] text-[#c41468]' : 'bg-gray-100 text-gray-400'}`} title="Promos">
                          <Bell className="h-4 w-4" />
                        </span>
                      </div>
                    </td>
                    <td className="px-5 py-4"><StatusBadge blacklisted={client.est_blackliste} /></td>
                    <td className="px-5 py-4">
                      <div className="flex justify-end gap-1">
                        <button type="button" onClick={() => void openDetailModal(client)} className="flex h-9 w-9 items-center justify-center rounded-lg text-gray-600 transition hover:bg-gray-100" title="Fiche">
                          <Eye className="h-4 w-4" />
                        </button>
                        <button type="button" onClick={() => openPreferencesModal(client)} className="flex h-9 w-9 items-center justify-center rounded-lg text-[#c41468] transition hover:bg-[#fff2f7]" title="Preferences">
                          <Star className="h-4 w-4" />
                        </button>
                        {client.est_blackliste ? (
                          <button type="button" onClick={() => void unblock(client)} className="flex h-9 w-9 items-center justify-center rounded-lg text-emerald-700 transition hover:bg-emerald-50" title="Debloquer">
                            <ShieldCheck className="h-4 w-4" />
                          </button>
                        ) : (
                          <button type="button" onClick={() => openBlacklistModal(client)} className="flex h-9 w-9 items-center justify-center rounded-lg text-red-600 transition hover:bg-red-50" title="Bloquer">
                            <Ban className="h-4 w-4" />
                          </button>
                        )}
                        <button type="button" onClick={() => openClientModal(client)} className="flex h-9 w-9 items-center justify-center rounded-lg text-indigo-600 transition hover:bg-indigo-50" title="Modifier">
                          <Edit className="h-4 w-4" />
                        </button>
                        <button type="button" onClick={() => void remove(client)} className="flex h-9 w-9 items-center justify-center rounded-lg text-red-600 transition hover:bg-red-50" title="Supprimer">
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

      {items && (
        <Pagination
          page={page}
          lastPage={items.last_page}
          total={items.total}
          onPrevious={() => void loadPage(page - 1, search, sourceFilter, statusFilter, loyaltyFilter)}
          onNext={() => void loadPage(page + 1, search, sourceFilter, statusFilter, loyaltyFilter)}
        />
      )}

      {modalOpen && (
        <Modal title={editingClient ? 'Modifier client' : 'Nouveau client'} onClose={resetForm}>
          <form onSubmit={submitClient} className="space-y-5">
            <div className="grid gap-4 sm:grid-cols-2">
              <FormField label="Prenom">
                <input className={inputClass} value={form.prenom} onChange={(event) => setForm((current) => ({ ...current, prenom: event.target.value }))} required placeholder="Awa" />
              </FormField>
              <FormField label="Nom">
                <input className={inputClass} value={form.nom} onChange={(event) => setForm((current) => ({ ...current, nom: event.target.value }))} required placeholder="Ndiaye" />
              </FormField>
              <FormField label="Telephone">
                <input className={inputClass} value={form.telephone} onChange={(event) => setForm((current) => ({ ...current, telephone: event.target.value }))} required placeholder="+221 77 000 00 00" />
              </FormField>
              <FormField label="Email">
                <input className={inputClass} type="email" value={form.email} onChange={(event) => setForm((current) => ({ ...current, email: event.target.value }))} placeholder="cliente@example.com" />
              </FormField>
              <FormField label="Source">
                <select className={inputClass} value={form.source} onChange={(event) => setForm((current) => ({ ...current, source: event.target.value as ClientSource }))}>
                  <option value="physique">Physique</option>
                  <option value="en_ligne">En ligne</option>
                </select>
              </FormField>
              <FormField label="Reservations terminees">
                <input className={inputClass} type="number" min="0" step="1" value={form.nombre_reservations_terminees} onChange={(event) => setForm((current) => ({ ...current, nombre_reservations_terminees: event.target.value }))} />
              </FormField>
              <label className="flex items-center gap-3 rounded-lg border border-gray-100 px-3 py-3 text-sm font-bold sm:col-span-2">
                <input type="checkbox" checked={form.fidelite_disponible} onChange={(event) => setForm((current) => ({ ...current, fidelite_disponible: event.target.checked }))} />
                Recompense fidelite disponible
              </label>
            </div>
            <div className="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
              <button type="button" onClick={resetForm} className={secondaryButtonClass}>Annuler</button>
              <button type="submit" disabled={saving} className={`${primaryButtonClass} inline-flex items-center justify-center gap-2`}>
                {saving && <RefreshCw className="h-4 w-4 animate-spin" />}
                {saving ? 'Enregistrement...' : editingClient ? 'Modifier' : 'Creer'}
              </button>
            </div>
          </form>
        </Modal>
      )}

      {preferencesModalOpen && preferencesClient && (
        <Modal title={`Preferences - ${clientName(preferencesClient)}`} onClose={closePreferencesModal}>
          <form onSubmit={submitPreferences} className="space-y-5">
            <div className="grid gap-4 sm:grid-cols-2">
              <FormField label="Coiffures preferees" hint="Separez par des virgules">
                <input className={inputClass} value={preferencesForm.coiffures_preferees} onChange={(event) => setPreferencesForm((current) => ({ ...current, coiffures_preferees: event.target.value }))} placeholder="Knotless, tresses, brushing" />
              </FormField>
              <FormField label="Options preferees" hint="Separez par des virgules">
                <input className={inputClass} value={preferencesForm.options_preferees} onChange={(event) => setPreferencesForm((current) => ({ ...current, options_preferees: event.target.value }))} placeholder="Soin, shampoing, finition" />
              </FormField>
              <FormField label="Notes internes">
                <textarea className={inputClass} rows={6} value={preferencesForm.notes} onChange={(event) => setPreferencesForm((current) => ({ ...current, notes: event.target.value }))} placeholder="Allergies, habitudes, remarques..." />
              </FormField>
              <div className="space-y-3 sm:self-start">
                <label className="flex items-center gap-3 rounded-lg border border-gray-100 px-3 py-3 text-sm font-bold">
                  <input type="checkbox" checked={preferencesForm.notifications_whatsapp} onChange={(event) => setPreferencesForm((current) => ({ ...current, notifications_whatsapp: event.target.checked }))} />
                  Notifications WhatsApp
                </label>
                <label className="flex items-center gap-3 rounded-lg border border-gray-100 px-3 py-3 text-sm font-bold">
                  <input type="checkbox" checked={preferencesForm.notifications_promos} onChange={(event) => setPreferencesForm((current) => ({ ...current, notifications_promos: event.target.checked }))} />
                  Notifications promos
                </label>
              </div>
            </div>
            <div className="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
              <button type="button" onClick={closePreferencesModal} className={secondaryButtonClass}>Annuler</button>
              <button type="submit" disabled={saving} className={`${primaryButtonClass} inline-flex items-center justify-center gap-2`}>
                {saving && <RefreshCw className="h-4 w-4 animate-spin" />}
                {saving ? 'Sauvegarde...' : 'Sauvegarder'}
              </button>
            </div>
          </form>
        </Modal>
      )}

      {blacklistModalOpen && blacklistTarget && (
        <Modal title={`Bloquer - ${clientName(blacklistTarget)}`} onClose={closeBlacklistModal}>
          <form onSubmit={submitBlacklist} className="space-y-5">
            <FormField label="Raison">
              <textarea className={inputClass} rows={5} value={blacklistReason} onChange={(event) => setBlacklistReason(event.target.value)} placeholder="Rendez-vous non honores, litige, comportement..." />
            </FormField>
            <div className="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
              <button type="button" onClick={closeBlacklistModal} className={secondaryButtonClass}>Annuler</button>
              <button type="submit" disabled={saving} className={`${dangerButtonClass} inline-flex items-center justify-center gap-2`}>
                {saving && <RefreshCw className="h-4 w-4 animate-spin" />}
                {saving ? 'Blocage...' : 'Bloquer'}
              </button>
            </div>
          </form>
        </Modal>
      )}

      {detailModalOpen && detailClient && (
        <Modal title={`Fiche client - ${clientName(detailClient)}`} onClose={() => setDetailModalOpen(false)} wide>
          {detailLoading ? (
            <div className="rounded-xl bg-gray-50 p-6 text-sm font-bold text-gray-500">Chargement de la fiche...</div>
          ) : (
            <div className="grid gap-5 xl:grid-cols-[1fr_1fr]">
              <section className="rounded-xl border border-gray-100 p-4">
                <div className="mb-4 flex items-start justify-between gap-3">
                  <div>
                    <p className="flex items-center gap-2 text-lg font-black text-gray-950">
                      <UserRound className="h-5 w-5 text-[#e91e63]" />
                      {clientName(detailClient)}
                    </p>
                    <p className="mt-1 text-sm font-semibold text-gray-500">Client #{detailClient.id}</p>
                  </div>
                  <StatusBadge blacklisted={detailClient.est_blackliste} />
                </div>
                <div className="grid gap-3 text-sm font-semibold text-gray-600">
                  <span className="flex items-center gap-2"><Phone className="h-4 w-4 text-[#e91e63]" />{detailClient.telephone}</span>
                  <span className="flex items-center gap-2"><Mail className="h-4 w-4 text-gray-400" />{detailClient.email || 'Email non renseigne'}</span>
                  <span>Source : {sourceLabel(detailClient.source)}</span>
                  <span>Creation : {formatDate(detailClient.created_at)}</span>
                </div>
                <div className="mt-5 grid gap-3 sm:grid-cols-2">
                  <div className="rounded-lg bg-[#fff8fb] px-3 py-3">
                    <p className="text-xs font-black uppercase tracking-[0.08em] text-[#c41468]">Reservations</p>
                    <p className="mt-1 text-2xl font-black text-gray-950">{detailClient.nombre_reservations_terminees}</p>
                  </div>
                  <div className="rounded-lg bg-[#fff8fb] px-3 py-3">
                    <p className="text-xs font-black uppercase tracking-[0.08em] text-[#c41468]">Fidelite</p>
                    <p className="mt-1 text-sm font-black text-gray-950">{detailClient.fidelite_disponible ? 'Disponible' : 'En progression'}</p>
                  </div>
                </div>
                <div className="mt-5 flex flex-wrap gap-2">
                  <button type="button" onClick={() => openClientModal(detailClient)} className={`${secondaryButtonClass} inline-flex items-center gap-2`}>
                    <Edit className="h-4 w-4" />
                    Modifier
                  </button>
                  <button type="button" onClick={() => openPreferencesModal(detailClient)} className={`${secondaryButtonClass} inline-flex items-center gap-2`}>
                    <Star className="h-4 w-4" />
                    Preferences
                  </button>
                  {detailClient.est_blackliste ? (
                    <button type="button" onClick={() => void unblock(detailClient)} className={`${secondaryButtonClass} inline-flex items-center gap-2`}>
                      <ShieldCheck className="h-4 w-4" />
                      Debloquer
                    </button>
                  ) : (
                    <button type="button" onClick={() => openBlacklistModal(detailClient)} className={`${dangerButtonClass} inline-flex items-center gap-2`}>
                      <ShieldOff className="h-4 w-4" />
                      Bloquer
                    </button>
                  )}
                </div>
              </section>

              <section className="rounded-xl border border-gray-100 p-4">
                <h3 className="text-base font-black text-gray-950">Preferences</h3>
                <div className="mt-4 grid gap-4">
                  <div>
                    <p className="text-xs font-black uppercase tracking-[0.08em] text-gray-400">Coiffures</p>
                    <div className="mt-2 flex flex-wrap gap-2">
                      {tags(detailClient.preferences?.coiffures_preferees).length > 0 ? (
                        tags(detailClient.preferences?.coiffures_preferees).map((item) => (
                          <span key={item} className="rounded-full bg-[#fff2f7] px-3 py-1 text-xs font-black text-[#c41468]">{item}</span>
                        ))
                      ) : (
                        <span className="text-sm font-semibold text-gray-400">Aucune coiffure preferee</span>
                      )}
                    </div>
                  </div>
                  <div>
                    <p className="text-xs font-black uppercase tracking-[0.08em] text-gray-400">Options</p>
                    <div className="mt-2 flex flex-wrap gap-2">
                      {tags(detailClient.preferences?.options_preferees).length > 0 ? (
                        tags(detailClient.preferences?.options_preferees).map((item) => (
                          <span key={item} className="rounded-full bg-gray-100 px-3 py-1 text-xs font-black text-gray-600">{item}</span>
                        ))
                      ) : (
                        <span className="text-sm font-semibold text-gray-400">Aucune option preferee</span>
                      )}
                    </div>
                  </div>
                  <div>
                    <p className="text-xs font-black uppercase tracking-[0.08em] text-gray-400">Notes</p>
                    <p className="mt-2 whitespace-pre-wrap rounded-lg bg-gray-50 p-3 text-sm font-semibold text-gray-600">
                      {detailClient.preferences?.notes || 'Aucune note interne'}
                    </p>
                  </div>
                  <div className="grid gap-2 sm:grid-cols-2">
                    <span className="inline-flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm font-bold text-gray-600">
                      {detailClient.preferences?.notifications_whatsapp ?? true ? <Bell className="h-4 w-4 text-emerald-600" /> : <BellOff className="h-4 w-4 text-gray-400" />}
                      WhatsApp
                    </span>
                    <span className="inline-flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm font-bold text-gray-600">
                      {detailClient.preferences?.notifications_promos ?? true ? <Bell className="h-4 w-4 text-[#c41468]" /> : <BellOff className="h-4 w-4 text-gray-400" />}
                      Promos
                    </span>
                  </div>
                </div>
              </section>

              <section className="rounded-xl border border-gray-100 p-4 xl:col-span-2">
                <h3 className="text-base font-black text-gray-950">Historique reservations</h3>
                <div className="mt-3 rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-5 text-sm font-bold text-gray-500">
                  {detailClient.nombre_reservations_terminees > 0
                    ? `${detailClient.nombre_reservations_terminees} reservation(s) terminee(s) comptabilisee(s).`
                    : 'Aucune reservation terminee comptabilisee.'}
                </div>
              </section>

              <section className="rounded-xl border border-gray-100 p-4 xl:col-span-2">
                <h3 className="text-base font-black text-gray-950">Liste noire</h3>
                <div className="mt-3 grid gap-3">
                  {blacklistHistory.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-5 text-sm font-bold text-gray-500">
                      Aucun historique de blocage.
                    </div>
                  ) : (
                    blacklistHistory.map((entry: ListeNoireClient) => (
                      <div key={entry.id} className="rounded-xl border border-gray-100 bg-white px-4 py-3">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                          <span className={`w-max rounded-full px-3 py-1 text-xs font-black ${entry.actif ? 'bg-red-50 text-red-700' : 'bg-gray-100 text-gray-500'}`}>
                            {entry.actif ? 'Actif' : 'Retire'}
                          </span>
                          <span className="text-xs font-bold text-gray-400">
                            {formatDate(entry.blackliste_at)} - {entry.retire_at ? formatDate(entry.retire_at) : 'en cours'}
                          </span>
                        </div>
                        <p className="mt-2 text-sm font-semibold text-gray-600">{entry.raison || 'Raison non renseignee'}</p>
                      </div>
                    ))
                  )}
                </div>
              </section>
            </div>
          )}
        </Modal>
      )}
    </AdminLayout>
  )
}

export default ClientsPage
