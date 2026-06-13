import { useCallback, useEffect, useState, type ChangeEvent } from 'react'
import { Eye, EyeOff, Loader2, RefreshCw, Trash2, Upload } from 'lucide-react'
import CatalogueLayout from '../catalogue/components/CatalogueLayout'
import { EmptyState, ErrorState } from '../catalogue/components/CatalogueUi'
import { inputClass, primaryButtonClass } from '../catalogue/components/catalogueUiTokens'
import { createGaleriePhoto, deleteGaleriePhoto, getGaleriePhotos, updateGaleriePhoto } from './galerie.api'
import type { GaleriePhoto } from './galerie.types'

type Draft = { titre: string; sous_titre: string }

function GaleriePage() {
  const [photos, setPhotos] = useState<GaleriePhoto[]>([])
  const [max, setMax] = useState(10)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [uploading, setUploading] = useState(false)
  const [savingId, setSavingId] = useState<number | null>(null)
  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [drafts, setDrafts] = useState<Record<number, Draft>>({})

  const load = useCallback(() => {
    setLoading(true)
    getGaleriePhotos()
      .then((response) => {
        setPhotos(response.data)
        setMax(response.max)
        setError(null)
      })
      .catch(() => setError('Impossible de charger la galerie.'))
      .finally(() => setLoading(false))
  }, [])

  useEffect(() => {
    load()
  }, [load])

  const remaining = Math.max(0, max - photos.length)

  const draftOf = (photo: GaleriePhoto): Draft =>
    drafts[photo.id] ?? { titre: photo.titre ?? '', sous_titre: photo.sous_titre ?? '' }

  const setDraft = (id: number, patch: Partial<Draft>, base: Draft) => {
    setDrafts((current) => ({ ...current, [id]: { ...base, ...current[id], ...patch } }))
  }

  async function handleUpload(event: ChangeEvent<HTMLInputElement>) {
    const files = Array.from(event.target.files ?? []).slice(0, remaining)
    event.target.value = ''
    if (files.length === 0) {
      return
    }
    setUploading(true)
    setError(null)
    try {
      for (const file of files) {
        await createGaleriePhoto({ image: file })
      }
      load()
    } catch {
      setError("Echec de l'upload. Formats image uniquement, 4 Mo max, 10 photos au total.")
    } finally {
      setUploading(false)
    }
  }

  async function handleSave(photo: GaleriePhoto) {
    const draft = draftOf(photo)
    setSavingId(photo.id)
    setError(null)
    try {
      const updated = await updateGaleriePhoto(photo.id, { ...draft, actif: photo.actif })
      setPhotos((current) => current.map((item) => (item.id === photo.id ? updated : item)))
      setDrafts((current) => {
        const next = { ...current }
        delete next[photo.id]
        return next
      })
    } catch {
      setError('Enregistrement impossible.')
    } finally {
      setSavingId(null)
    }
  }

  async function handleToggleActif(photo: GaleriePhoto) {
    const draft = draftOf(photo)
    try {
      const updated = await updateGaleriePhoto(photo.id, { ...draft, actif: !photo.actif })
      setPhotos((current) => current.map((item) => (item.id === photo.id ? updated : item)))
    } catch {
      setError('Changement de visibilite impossible.')
    }
  }

  async function handleDelete(photo: GaleriePhoto) {
    if (!window.confirm('Supprimer cette photo de la galerie ?')) {
      return
    }
    setDeletingId(photo.id)
    try {
      await deleteGaleriePhoto(photo.id)
      setPhotos((current) => current.filter((item) => item.id !== photo.id))
    } catch {
      setError('Suppression impossible.')
    } finally {
      setDeletingId(null)
    }
  }

  return (
    <CatalogueLayout
      title="Galerie d'accueil"
      subtitle="Les photos affichees dans la section « Le salon en images » de la page d'accueil (10 maximum)."
      action={
        <label
          className={`${primaryButtonClass} inline-flex w-full cursor-pointer items-center justify-center gap-2 sm:w-auto ${
            remaining === 0 || uploading ? 'cursor-not-allowed opacity-60' : ''
          }`}
        >
          {uploading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Upload className="h-4 w-4" />}
          Ajouter des photos
          <input
            type="file"
            accept="image/*"
            multiple
            disabled={remaining === 0 || uploading}
            onChange={handleUpload}
            className="sr-only"
          />
        </label>
      }
    >
      <section className="mb-5 flex items-center justify-between gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-[0_18px_36px_-32px_rgba(20,20,43,0.5)]">
        <p className="text-sm font-bold text-gray-500">
          Les photos sont optimisees (WebP) et chargees a la demande : aucun impact notable sur les performances.
        </p>
        <span className="shrink-0 rounded-full bg-[#fff2f7] px-3 py-1 text-sm font-black text-[#c41468]">
          {photos.length} / {max}
        </span>
      </section>

      {error && <ErrorState label={error} />}

      {loading ? (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {Array.from({ length: 3 }).map((_, index) => (
            <div key={index} className="h-72 animate-pulse rounded-xl bg-gray-100" />
          ))}
        </div>
      ) : photos.length === 0 ? (
        <EmptyState label="Aucune photo pour le moment. Ajoutez-en jusqu'a 10 avec le bouton ci-dessus." />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {photos.map((photo) => {
            const draft = draftOf(photo)
            const dirty = draft.titre !== (photo.titre ?? '') || draft.sous_titre !== (photo.sous_titre ?? '')

            return (
              <article
                key={photo.id}
                className={`overflow-hidden rounded-xl border bg-white shadow-sm transition ${
                  photo.actif ? 'border-gray-100' : 'border-amber-200'
                }`}
              >
                <div className="relative aspect-[4/5] overflow-hidden bg-[#fff2f7]">
                  <img src={photo.url} alt={photo.titre ?? ''} className="h-full w-full object-cover" loading="lazy" />
                  {!photo.actif && (
                    <span className="absolute left-3 top-3 rounded-full bg-amber-500 px-2.5 py-1 text-[10px] font-black uppercase text-white">
                      Masquee
                    </span>
                  )}
                </div>
                <div className="space-y-3 p-4">
                  <input
                    className={inputClass}
                    placeholder="Titre (ex: Notre univers)"
                    value={draft.titre}
                    onChange={(event) => setDraft(photo.id, { titre: event.target.value }, { titre: photo.titre ?? '', sous_titre: photo.sous_titre ?? '' })}
                  />
                  <input
                    className={inputClass}
                    placeholder="Sous-titre (ex: Une ambiance chaleureuse)"
                    value={draft.sous_titre}
                    onChange={(event) => setDraft(photo.id, { sous_titre: event.target.value }, { titre: photo.titre ?? '', sous_titre: photo.sous_titre ?? '' })}
                  />
                  <div className="flex items-center justify-between gap-2 border-t border-gray-100 pt-3">
                    <button
                      type="button"
                      onClick={() => void handleToggleActif(photo)}
                      className={`inline-flex items-center gap-1.5 rounded-lg px-2.5 py-2 text-xs font-black transition ${
                        photo.actif ? 'text-emerald-700 hover:bg-emerald-50' : 'text-amber-700 hover:bg-amber-50'
                      }`}
                      title={photo.actif ? 'Visible sur le site' : 'Masquee'}
                    >
                      {photo.actif ? <Eye className="h-4 w-4" /> : <EyeOff className="h-4 w-4" />}
                      {photo.actif ? 'Visible' : 'Masquee'}
                    </button>
                    <div className="flex items-center gap-1">
                      <button
                        type="button"
                        onClick={() => void handleSave(photo)}
                        disabled={!dirty || savingId === photo.id}
                        className="inline-flex items-center gap-1.5 rounded-lg bg-[#e91e63] px-3 py-2 text-xs font-black text-white transition hover:bg-[#c41468] disabled:cursor-not-allowed disabled:opacity-40"
                      >
                        {savingId === photo.id ? <RefreshCw className="h-4 w-4 animate-spin" /> : null}
                        Enregistrer
                      </button>
                      <button
                        type="button"
                        onClick={() => void handleDelete(photo)}
                        disabled={deletingId === photo.id}
                        className="rounded-lg p-2 text-red-600 transition hover:bg-red-50 disabled:opacity-40"
                        title="Supprimer"
                      >
                        {deletingId === photo.id ? <RefreshCw className="h-4 w-4 animate-spin" /> : <Trash2 className="h-4 w-4" />}
                      </button>
                    </div>
                  </div>
                </div>
              </article>
            )
          })}
        </div>
      )}
    </CatalogueLayout>
  )
}

export default GaleriePage
