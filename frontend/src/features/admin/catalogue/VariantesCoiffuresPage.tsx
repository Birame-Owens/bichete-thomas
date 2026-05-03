import { useCallback, useEffect, useMemo, useState, type FormEvent } from 'react'
import CatalogueLayout from './components/CatalogueLayout'
import {
  EmptyState,
  ErrorState,
  FormField,
  Pagination,
  Panel,
  StatusBadge,
} from './components/CatalogueUi'
import {
  dangerButtonClass,
  inputClass,
  money,
  primaryButtonClass,
  secondaryButtonClass,
} from './components/catalogueUiTokens'
import {
  createVarianteCoiffure,
  deleteVarianteCoiffure,
  getCoiffures,
  getVariantesCoiffures,
  updateVarianteCoiffure,
} from './catalogue.api'
import type { Coiffure, LaravelPaginated, VarianteCoiffure, VarianteForm } from './catalogue.types'

const emptyForm: VarianteForm = {
  coiffure_id: '',
  nom: '',
  prix: '',
  duree_minutes: '',
  actif: true,
}

function VariantesCoiffuresPage() {
  const [items, setItems] = useState<LaravelPaginated<VarianteCoiffure> | null>(null)
  const [coiffuresList, setCoiffuresList] = useState<Coiffure[]>([])
  const [form, setForm] = useState<VarianteForm>(emptyForm)
  const [editing, setEditing] = useState<VarianteCoiffure | null>(null)
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const variantes = useMemo(() => items?.data ?? [], [items])

  const loadPage = useCallback(async (nextPage: number) => {
    setLoading(true)
    setError(null)
    try {
      const variantesResponse = await getVariantesCoiffures({ page: nextPage })
      setItems(variantesResponse)
      setPage(nextPage)
    } catch {
      setError('Impossible de charger les variantes.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    Promise.all([getVariantesCoiffures({ page: 1 }), getCoiffures()])
      .then(([variantesResponse, coiffuresResponse]) => {
        setItems(variantesResponse)
        setCoiffuresList(coiffuresResponse.data)
        setPage(1)
      })
      .catch(() => setError('Impossible de charger les variantes.'))
      .finally(() => setLoading(false))
  }, [])

  const resetForm = () => {
    setForm(emptyForm)
    setEditing(null)
  }

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setSaving(true)
    setError(null)
    try {
      if (editing) {
        await updateVarianteCoiffure(editing.id, form)
      } else {
        await createVarianteCoiffure(form)
      }
      resetForm()
      await loadPage(1)
    } catch {
      setError('Enregistrement impossible. Verifiez la coiffure, le prix et la duree.')
    } finally {
      setSaving(false)
    }
  }

  const edit = (variante: VarianteCoiffure) => {
    setEditing(variante)
    setForm({
      coiffure_id: String(variante.coiffure_id),
      nom: variante.nom,
      prix: String(variante.prix),
      duree_minutes: String(variante.duree_minutes),
      actif: variante.actif,
    })
  }

  const remove = async (variante: VarianteCoiffure) => {
    if (!window.confirm(`Supprimer la variante "${variante.nom}" ?`)) {
      return
    }

    try {
      await deleteVarianteCoiffure(variante.id)
      await loadPage(page)
    } catch {
      setError('Suppression impossible pour cette variante.')
    }
  }

  return (
    <CatalogueLayout
      title="Variantes coiffures"
      subtitle="Declinez chaque coiffure par taille, duree ou finition avec son prix exact."
    >
      <div className="grid gap-5 xl:grid-cols-[1fr_360px]">
        <Panel title="Liste des variantes" subtitle="Prix et durees rattaches aux coiffures.">
          {error && <ErrorState label={error} />}
          {loading ? (
            <EmptyState label="Chargement des variantes..." />
          ) : variantes.length === 0 ? (
            <EmptyState label="Aucune variante pour le moment." />
          ) : (
            <div className="overflow-hidden rounded-xl border border-gray-100">
              <table className="w-full min-w-[700px] text-left text-sm">
                <thead className="bg-[#fff7fb] text-xs font-black uppercase tracking-[0.12em] text-gray-500">
                  <tr>
                    <th className="px-4 py-3">Variante</th>
                    <th className="px-4 py-3">Coiffure</th>
                    <th className="px-4 py-3">Prix</th>
                    <th className="px-4 py-3">Duree</th>
                    <th className="px-4 py-3">Statut</th>
                    <th className="px-4 py-3 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {variantes.map((variante) => (
                    <tr key={variante.id}>
                      <td className="px-4 py-3 font-black text-gray-900">{variante.nom}</td>
                      <td className="px-4 py-3 font-semibold text-gray-500">
                        {variante.coiffure?.nom ?? 'Non renseignee'}
                      </td>
                      <td className="px-4 py-3 font-black text-[#c41468]">{money(variante.prix)}</td>
                      <td className="px-4 py-3 font-bold text-gray-600">{variante.duree_minutes} min</td>
                      <td className="px-4 py-3">
                        <StatusBadge active={variante.actif} />
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex justify-end gap-2">
                          <button type="button" onClick={() => edit(variante)} className={secondaryButtonClass}>
                            Modifier
                          </button>
                          <button type="button" onClick={() => void remove(variante)} className={dangerButtonClass}>
                            Supprimer
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
          {items && (
            <Pagination
              page={page}
              lastPage={items.last_page}
              total={items.total}
              onPrevious={() => void loadPage(page - 1)}
              onNext={() => void loadPage(page + 1)}
            />
          )}
        </Panel>

        <Panel title={editing ? 'Modifier variante' : 'Nouvelle variante'}>
          <form onSubmit={submit} className="space-y-4">
            <FormField label="Coiffure">
              <select
                className={inputClass}
                value={form.coiffure_id}
                onChange={(event) => setForm((current) => ({ ...current, coiffure_id: event.target.value }))}
                required
              >
                <option value="">Choisir une coiffure</option>
                {coiffuresList.map((coiffure) => (
                  <option key={coiffure.id} value={coiffure.id}>
                    {coiffure.nom}
                  </option>
                ))}
              </select>
            </FormField>
            <FormField label="Nom">
              <input
                className={inputClass}
                value={form.nom}
                onChange={(event) => setForm((current) => ({ ...current, nom: event.target.value }))}
                required
              />
            </FormField>
            <div className="grid grid-cols-2 gap-3">
              <FormField label="Prix">
                <input
                  className={inputClass}
                  type="number"
                  min="0"
                  value={form.prix}
                  onChange={(event) => setForm((current) => ({ ...current, prix: event.target.value }))}
                  required
                />
              </FormField>
              <FormField label="Duree min">
                <input
                  className={inputClass}
                  type="number"
                  min="1"
                  value={form.duree_minutes}
                  onChange={(event) => setForm((current) => ({ ...current, duree_minutes: event.target.value }))}
                  required
                />
              </FormField>
            </div>
            <label className="flex items-center gap-3 rounded-lg border border-gray-100 px-3 py-3 text-sm font-bold">
              <input
                type="checkbox"
                checked={form.actif}
                onChange={(event) => setForm((current) => ({ ...current, actif: event.target.checked }))}
              />
              Variante active
            </label>
            <div className="flex gap-2">
              <button type="submit" disabled={saving} className={primaryButtonClass}>
                {saving ? 'Enregistrement...' : editing ? 'Mettre a jour' : 'Creer'}
              </button>
              {editing && (
                <button type="button" onClick={resetForm} className={secondaryButtonClass}>
                  Annuler
                </button>
              )}
            </div>
          </form>
        </Panel>
      </div>
    </CatalogueLayout>
  )
}

export default VariantesCoiffuresPage
