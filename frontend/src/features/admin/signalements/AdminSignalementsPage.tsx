import { useCallback, useEffect, useState } from 'react'
import { AlertTriangle, CheckCircle2, Clock, Filter, PackageSearch, RefreshCw } from 'lucide-react'
import AdminLayout from '../../../layouts/AdminLayout'
import { getAdminSignalements, marquerLu, marquerTraite } from './signalements.api'
import type { Signalement } from './signalements.types'

const typeLabel = (t: string) => ({ produit: 'Produit / Fourniture', materiel: 'Equipement / Materiel', autre: 'Autre' }[t] ?? t)

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function AdminSignalementsPage() {
  const [signalements, setSignalements] = useState<Signalement[]>([])
  const [loading, setLoading]           = useState(true)
  const [filtreTraite, setFiltreTraite] = useState<'tous' | 'non_traite' | 'traite'>('non_traite')
  const [noteMap, setNoteMap]           = useState<Record<number, string>>({})

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const params = filtreTraite === 'tous' ? {} : { traite: filtreTraite === 'traite' }
      setSignalements(await getAdminSignalements(params))
    } catch {
      // silencieux
    } finally {
      setLoading(false)
    }
  }, [filtreTraite])

  useEffect(() => { void load() }, [load])

  const handleMarquerLu = async (s: Signalement) => {
    if (s.lu_par_admin) return
    const updated = await marquerLu(s.id)
    setSignalements((prev) => prev.map((x) => (x.id === s.id ? updated : x)))
  }

  const handleTraite = async (s: Signalement) => {
    const note = noteMap[s.id] ?? ''
    const updated = await marquerTraite(s.id, note || undefined)
    setSignalements((prev) => prev.map((x) => (x.id === s.id ? updated : x)))
  }

  return (
    <AdminLayout>
      <div className="mb-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="text-xs font-bold uppercase text-[#e91e63]">Gestion</p>
          <h1 className="mt-1 text-2xl font-black text-[#111018] sm:text-3xl">Signalements</h1>
          <p className="mt-1 text-sm font-medium text-gray-500">Besoins signales par les gerantes du salon.</p>
        </div>
        <div className="flex items-center gap-2">
          <select
            value={filtreTraite}
            onChange={(e) => setFiltreTraite(e.target.value as typeof filtreTraite)}
            className="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium shadow-sm outline-none focus:border-[#e91e63]"
          >
            <option value="non_traite">Non traites</option>
            <option value="traite">Traites</option>
            <option value="tous">Tous</option>
          </select>
          <button type="button" onClick={() => void load()} className="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-bold shadow-sm hover:bg-gray-50">
            <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
          </button>
        </div>
      </div>

      {loading ? (
        <div className="rounded-xl border border-gray-100 bg-white p-8 text-sm font-bold text-gray-400 shadow-sm">Chargement...</div>
      ) : signalements.length === 0 ? (
        <div className="flex flex-col items-center gap-3 rounded-xl border border-gray-100 bg-white py-16 shadow-sm">
          <PackageSearch className="h-10 w-10 text-gray-300" />
          <p className="text-sm font-bold text-gray-400">Aucun signalement dans cette categorie.</p>
        </div>
      ) : (
        <div className="space-y-4">
          {signalements.map((s) => (
            <div
              key={s.id}
              className={[
                'rounded-xl border bg-white p-5 shadow-sm transition',
                s.urgence === 'urgente' ? 'border-red-200' : 'border-gray-100',
                !s.lu_par_admin ? 'ring-2 ring-[#e91e63]/20' : '',
              ].join(' ')}
              onClick={() => void handleMarquerLu(s)}
            >
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                  {/* Header */}
                  <div className="flex flex-wrap items-center gap-2">
                    {s.urgence === 'urgente' && <AlertTriangle className="h-4 w-4 shrink-0 text-red-500" />}
                    {!s.lu_par_admin && <span className="h-2 w-2 shrink-0 rounded-full bg-[#e91e63]" />}
                    <p className="font-black text-gray-950">{s.titre}</p>
                  </div>

                  <div className="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-xs font-medium text-gray-400">
                    <span>{typeLabel(s.type)}</span>
                    <span>·</span>
                    <span>{s.gerante?.name ?? 'Gerante'}</span>
                    <span>·</span>
                    <span>{formatDate(s.created_at)}</span>
                  </div>

                  {s.description && <p className="mt-2 text-sm text-gray-700">{s.description}</p>}

                  {/* Note admin */}
                  {!s.traite && (
                    <div className="mt-3 flex gap-2">
                      <input
                        type="text"
                        placeholder="Note de reponse (optionnel)..."
                        value={noteMap[s.id] ?? ''}
                        onChange={(e) => setNoteMap((m) => ({ ...m, [s.id]: e.target.value }))}
                        onClick={(e) => e.stopPropagation()}
                        className="min-w-0 flex-1 rounded-lg border border-gray-200 px-3 py-1.5 text-sm outline-none focus:border-[#e91e63]"
                      />
                      <button
                        type="button"
                        onClick={(e) => { e.stopPropagation(); void handleTraite(s) }}
                        className="shrink-0 rounded-lg bg-emerald-500 px-3 py-1.5 text-xs font-black text-white hover:bg-emerald-600"
                      >
                        Marquer traite
                      </button>
                    </div>
                  )}

                  {s.note_admin && (
                    <p className="mt-2 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-800">
                      <span className="font-black">Votre note :</span> {s.note_admin}
                    </p>
                  )}
                </div>

                {/* Statut badge */}
                <div className="shrink-0">
                  {s.traite ? (
                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-black text-emerald-700">
                      <CheckCircle2 className="h-3 w-3" />Traite
                    </span>
                  ) : s.lu_par_admin ? (
                    <span className="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-black text-blue-700">
                      <CheckCircle2 className="h-3 w-3" />Lu
                    </span>
                  ) : (
                    <span className="inline-flex items-center gap-1 rounded-full bg-[#fff2f7] px-2.5 py-1 text-xs font-black text-[#e91e63]">
                      <Clock className="h-3 w-3" />Non lu
                    </span>
                  )}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </AdminLayout>
  )
}

export default AdminSignalementsPage
