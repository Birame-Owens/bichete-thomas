import { useEffect, useMemo, useState, type FormEvent } from 'react'
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
  createOptionCoiffure,
  deleteOptionCoiffure,
  getOptionsCoiffures,
  updateOptionCoiffure,
} from './catalogue.api'
import type { LaravelPaginated, OptionCoiffure, OptionForm } from './catalogue.types'

const emptyForm: OptionForm = {
  nom: '',
  prix: '',
  actif: true,
}

function OptionsCoiffuresPage() {
  const [items, setItems] = useState<LaravelPaginated<OptionCoiffure> | null>(null)
  const [form, setForm] = useState<OptionForm>(emptyForm)
  const [editing, setEditing] = useState<OptionCoiffure | null>(null)
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const options = useMemo(() => items?.data ?? [], [items])

  const loadPage = async (nextPage: number) => {
    setLoading(true)
    setError(null)
    try {
      setItems(await getOptionsCoiffures({ page: nextPage }))
      setPage(nextPage)
    } catch {
      setError('Impossible de charger les options.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    getOptionsCoiffures({ page: 1 })
      .then((response) => {
        setItems(response)
        setPage(1)
      })
      .catch(() => setError('Impossible de charger les options.'))
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
        await updateOptionCoiffure(editing.id, form)
      } else {
        await createOptionCoiffure(form)
      }
      resetForm()
      await loadPage(1)
    } catch {
      setError('Enregistrement impossible. Le nom existe peut-etre deja.')
    } finally {
      setSaving(false)
    }
  }

  const edit = (option: OptionCoiffure) => {
    setEditing(option)
    setForm({
      nom: option.nom,
      prix: String(option.prix),
      actif: option.actif,
    })
  }

  const remove = async (option: OptionCoiffure) => {
    if (!window.confirm(`Supprimer l option "${option.nom}" ?`)) {
      return
    }

    try {
      await deleteOptionCoiffure(option.id)
      await loadPage(page)
    } catch {
      setError('Suppression impossible. Cette option est peut-etre rattachee a une coiffure.')
    }
  }

  return (
    <CatalogueLayout
      title="Options coiffures"
      subtitle="Ajoutez les supplements et personnalisations qui peuvent etre associes aux coiffures."
    >
      <div className="grid gap-5 xl:grid-cols-[1fr_360px]">
        <Panel title="Liste des options" subtitle="Prix supplementaires disponibles au catalogue.">
          {error && <ErrorState label={error} />}
          {loading ? (
            <EmptyState label="Chargement des options..." />
          ) : options.length === 0 ? (
            <EmptyState label="Aucune option pour le moment." />
          ) : (
            <div className="overflow-hidden rounded-xl border border-gray-100">
              <table className="w-full min-w-[560px] text-left text-sm">
                <thead className="bg-[#fff7fb] text-xs font-black uppercase tracking-[0.12em] text-gray-500">
                  <tr>
                    <th className="px-4 py-3">Nom</th>
                    <th className="px-4 py-3">Prix</th>
                    <th className="px-4 py-3">Statut</th>
                    <th className="px-4 py-3 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {options.map((option) => (
                    <tr key={option.id}>
                      <td className="px-4 py-3 font-black text-gray-900">{option.nom}</td>
                      <td className="px-4 py-3 font-black text-[#c41468]">{money(option.prix)}</td>
                      <td className="px-4 py-3">
                        <StatusBadge active={option.actif} />
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex justify-end gap-2">
                          <button type="button" onClick={() => edit(option)} className={secondaryButtonClass}>
                            Modifier
                          </button>
                          <button type="button" onClick={() => void remove(option)} className={dangerButtonClass}>
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

        <Panel title={editing ? 'Modifier option' : 'Nouvelle option'}>
          <form onSubmit={submit} className="space-y-4">
            <FormField label="Nom">
              <input
                className={inputClass}
                value={form.nom}
                onChange={(event) => setForm((current) => ({ ...current, nom: event.target.value }))}
                required
              />
            </FormField>
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
            <label className="flex items-center gap-3 rounded-lg border border-gray-100 px-3 py-3 text-sm font-bold">
              <input
                type="checkbox"
                checked={form.actif}
                onChange={(event) => setForm((current) => ({ ...current, actif: event.target.checked }))}
              />
              Option active
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

export default OptionsCoiffuresPage
