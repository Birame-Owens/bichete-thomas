import { useCallback, useEffect, useState } from 'react'
import { AlertTriangle, CheckCircle2, Clock, PackageSearch, Send } from 'lucide-react'
import GeranteLayout from '../../layouts/GeranteLayout'
import { createSignalement, getGeranteSignalements } from './signalements.api'
import type { Signalement, SignalementForm } from '../admin/signalements/signalements.types'

const typeOptions = [
  { value: 'produit',  label: 'Produit / Fourniture' },
  { value: 'materiel', label: 'Equipement / Materiel' },
  { value: 'autre',    label: 'Autre' },
] as const

const inputClass = 'w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-900 shadow-sm outline-none focus:border-[#e91e63] focus:ring-2 focus:ring-[#e91e63]/20'
const labelClass = 'block text-xs font-black uppercase text-gray-500 mb-1'

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function StatutBadge({ s }: { s: Signalement }) {
  if (s.traite) {
    return <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-black text-emerald-700"><CheckCircle2 className="h-3 w-3" />Traite</span>
  }
  if (s.lu_par_admin) {
    return <span className="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-black text-blue-700"><CheckCircle2 className="h-3 w-3" />Lu</span>
  }
  return <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-black text-amber-700"><Clock className="h-3 w-3" />En attente</span>
}

function GeranteSignalementsPage() {
  const [signalements, setSignalements] = useState<Signalement[]>([])
  const [loading, setLoading]           = useState(true)
  const [sending, setSending]           = useState(false)
  const [success, setSuccess]           = useState(false)
  const [error, setError]               = useState<string | null>(null)

  const [form, setForm] = useState<SignalementForm>({
    type: 'produit',
    titre: '',
    description: '',
    urgence: 'normale',
  })

  const load = useCallback(async () => {
    try {
      setSignalements(await getGeranteSignalements())
    } catch {
      // silencieux
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => { void load() }, [load])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.titre.trim()) return
    setSending(true)
    setError(null)
    setSuccess(false)
    try {
      const created = await createSignalement(form)
      setSignalements((prev) => [created, ...prev])
      setForm({ type: 'produit', titre: '', description: '', urgence: 'normale' })
      setSuccess(true)
      window.setTimeout(() => setSuccess(false), 4000)
    } catch {
      setError('Impossible d envoyer le signalement. Reessayez.')
    } finally {
      setSending(false)
    }
  }

  return (
    <GeranteLayout>
      <div className="mb-5">
        <p className="text-xs font-bold uppercase text-[#e91e63]">Signalement</p>
        <h1 className="mt-1 text-2xl font-black text-[#111018]">Signaler un besoin</h1>
        <p className="mt-1 text-sm font-medium text-gray-500">
          Notifiez l administratrice d un besoin urgent : produit manquant, materiel en panne, autre.
        </p>
      </div>

      {/* Formulaire */}
      <section className="mb-6 rounded-xl border border-[#f1e7ee] bg-white p-5 shadow-sm">
        <h2 className="mb-4 text-base font-black text-[#111018]">Nouveau signalement</h2>
        <form onSubmit={(e) => void handleSubmit(e)} className="space-y-4">
          {/* Type + Urgence */}
          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <label className={labelClass}>Categorie</label>
              <select value={form.type} onChange={(e) => setForm((f) => ({ ...f, type: e.target.value as SignalementForm['type'] }))} className={inputClass}>
                {typeOptions.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
              </select>
            </div>
            <div>
              <label className={labelClass}>Urgence</label>
              <div className="flex gap-2 pt-1">
                {(['normale', 'urgente'] as const).map((u) => (
                  <button
                    key={u}
                    type="button"
                    onClick={() => setForm((f) => ({ ...f, urgence: u }))}
                    className={[
                      'flex-1 rounded-xl border py-2 text-sm font-black transition',
                      form.urgence === u
                        ? u === 'urgente'
                          ? 'border-red-500 bg-red-500 text-white'
                          : 'border-[#e91e63] bg-[#e91e63] text-white'
                        : 'border-gray-200 bg-white text-gray-500 hover:border-gray-300',
                    ].join(' ')}
                  >
                    {u === 'urgente' ? '🔴 Urgent' : '🟡 Normal'}
                  </button>
                ))}
              </div>
            </div>
          </div>

          {/* Titre */}
          <div>
            <label className={labelClass}>Objet <span className="text-red-500">*</span></label>
            <input
              type="text"
              value={form.titre}
              onChange={(e) => setForm((f) => ({ ...f, titre: e.target.value }))}
              placeholder="Ex: Rupture de meches bresiliennes n°2"
              className={inputClass}
              required
              maxLength={255}
            />
          </div>

          {/* Description */}
          <div>
            <label className={labelClass}>Details <span className="text-gray-400 normal-case font-medium">(optionnel)</span></label>
            <textarea
              value={form.description}
              onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
              placeholder="Quantite approximative, depuis quand, contexte..."
              className={`${inputClass} min-h-[80px] resize-y`}
              maxLength={2000}
            />
          </div>

          {error && <p className="text-sm font-bold text-red-600">{error}</p>}
          {success && (
            <div className="flex items-center gap-2 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">
              <CheckCircle2 className="h-4 w-4" />
              Signalement envoye. L administratrice a ete notifiee.
            </div>
          )}

          <button
            type="submit"
            disabled={sending || !form.titre.trim()}
            className="inline-flex items-center gap-2 rounded-xl bg-[#e91e63] px-5 py-2.5 text-sm font-black text-white shadow-sm transition hover:bg-[#c41468] disabled:opacity-60"
          >
            <Send className="h-4 w-4" />
            {sending ? 'Envoi...' : 'Envoyer le signalement'}
          </button>
        </form>
      </section>

      {/* Historique */}
      <section className="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
        <h2 className="mb-4 text-base font-black text-[#111018]">Mes signalements</h2>
        {loading ? (
          <p className="text-sm text-gray-400">Chargement...</p>
        ) : signalements.length === 0 ? (
          <div className="flex flex-col items-center gap-2 py-8 text-gray-400">
            <PackageSearch className="h-8 w-8" />
            <p className="text-sm font-medium">Aucun signalement envoye pour l instant.</p>
          </div>
        ) : (
          <div className="space-y-3">
            {signalements.map((s) => (
              <div key={s.id} className={['rounded-xl border p-4', s.urgence === 'urgente' ? 'border-red-100 bg-red-50/40' : 'border-gray-100'].join(' ')}>
                <div className="flex flex-wrap items-start justify-between gap-2">
                  <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                      {s.urgence === 'urgente' && <AlertTriangle className="h-4 w-4 shrink-0 text-red-500" />}
                      <p className="font-black text-gray-950">{s.titre}</p>
                    </div>
                    <p className="mt-0.5 text-xs font-medium text-gray-400">
                      {typeOptions.find((o) => o.value === s.type)?.label} · {formatDate(s.created_at)}
                    </p>
                    {s.description && <p className="mt-1 text-sm text-gray-600">{s.description}</p>}
                    {s.note_admin && (
                      <p className="mt-2 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-800">
                        <span className="font-black">Reponse admin :</span> {s.note_admin}
                      </p>
                    )}
                  </div>
                  <StatutBadge s={s} />
                </div>
              </div>
            ))}
          </div>
        )}
      </section>
    </GeranteLayout>
  )
}

export default GeranteSignalementsPage
