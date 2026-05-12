import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { Star } from 'lucide-react'
import { getAvisPrefill, submitVerifiedAvis } from './client.api'
import type { AvisPrefill } from './client.types'

type PageState = 'loading' | 'form' | 'success' | 'error'

export default function AvisPage() {
  const { token } = useParams<{ token: string }>()

  const [pageState, setPageState] = useState<PageState>('loading')
  const [prefill, setPrefill] = useState<AvisPrefill | null>(null)
  const [errorMessage, setErrorMessage] = useState('')

  const [note, setNote] = useState(0)
  const [hovered, setHovered] = useState(0)
  const [commentaire, setCommentaire] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})

  useEffect(() => {
    if (!token) {
      setErrorMessage('Lien invalide.')
      setPageState('error')
      return
    }

    getAvisPrefill(token)
      .then((data) => {
        setPrefill(data)
        setPageState('form')
      })
      .catch(() => {
        setErrorMessage('Ce lien est invalide ou a expiré. Merci de vérifier le lien WhatsApp reçu.')
        setPageState('error')
      })
  }, [token])

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!token) return

    setFieldErrors({})

    if (note === 0) {
      setFieldErrors({ note: ['Veuillez sélectionner une note.'] })
      return
    }

    setSubmitting(true)
    try {
      await submitVerifiedAvis(token, { note, commentaire })
      setPageState('success')
    } catch (err: unknown) {
      const axiosErr = err as { response?: { status?: number; data?: { errors?: Record<string, string[]>; message?: string } } }
      const status = axiosErr?.response?.status
      const data = axiosErr?.response?.data

      if (status === 422 && data?.errors) {
        setFieldErrors(data.errors)
      } else if (status === 422 && data?.message) {
        setErrorMessage(data.message)
        setPageState('error')
      } else {
        setErrorMessage('Une erreur est survenue. Veuillez réessayer.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  if (pageState === 'loading') {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[#faf9fa]">
        <p className="text-sm font-semibold text-gray-500">Chargement...</p>
      </div>
    )
  }

  if (pageState === 'error') {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[#faf9fa] px-4">
        <div className="w-full max-w-md rounded-2xl border border-gray-100 bg-white p-8 shadow-sm text-center">
          <p className="text-4xl mb-4">🙁</p>
          <h1 className="text-xl font-black text-gray-900 mb-2">Lien non valide</h1>
          <p className="text-sm text-gray-500">{errorMessage}</p>
        </div>
      </div>
    )
  }

  if (pageState === 'success') {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[#faf9fa] px-4">
        <div className="w-full max-w-md rounded-2xl border border-gray-100 bg-white p-8 shadow-sm text-center">
          <p className="text-5xl mb-4">🌸</p>
          <h1 className="text-xl font-black text-gray-900 mb-2">Merci pour votre avis !</h1>
          <p className="text-sm text-gray-500">
            Votre avis sera visible sur la page du salon après validation par notre équipe.
          </p>
        </div>
      </div>
    )
  }

  const displayNote = hovered > 0 ? hovered : note

  return (
    <div className="flex min-h-screen items-center justify-center bg-[#faf9fa] px-4 py-10">
      <div className="w-full max-w-md rounded-2xl border border-gray-100 bg-white p-8 shadow-sm">
        {/* En-tête */}
        <div className="mb-6 text-center">
          <p className="text-3xl mb-2">✂️</p>
          <h1 className="text-xl font-black text-gray-900">
            {prefill?.prenom ? `Merci ${prefill.prenom} !` : 'Votre avis'}
          </h1>
          {prefill?.coiffure_nom && (
            <p className="mt-1 text-sm text-gray-500">
              Prestation : <span className="font-semibold text-gray-700">{prefill.coiffure_nom}</span>
            </p>
          )}
        </div>

        <form onSubmit={handleSubmit} className="space-y-5">
          {/* Étoiles */}
          <div>
            <label className="block text-sm font-semibold text-gray-700 mb-2">
              Votre note <span className="text-[#f31976]">*</span>
            </label>
            <div
              className="flex gap-1"
              onMouseLeave={() => setHovered(0)}
            >
              {[1, 2, 3, 4, 5].map((star) => (
                <button
                  key={star}
                  type="button"
                  aria-label={`${star} étoile${star > 1 ? 's' : ''}`}
                  className="p-1 transition-transform hover:scale-110 focus:outline-none"
                  onMouseEnter={() => setHovered(star)}
                  onClick={() => setNote(star)}
                >
                  <Star
                    className="h-8 w-8 transition-colors"
                    fill={star <= displayNote ? '#f31976' : 'none'}
                    stroke={star <= displayNote ? '#f31976' : '#d1d5db'}
                    strokeWidth={1.5}
                  />
                </button>
              ))}
            </div>
            {fieldErrors.note && (
              <p className="mt-1 text-xs text-red-500">{fieldErrors.note[0]}</p>
            )}
          </div>

          {/* Commentaire */}
          <div>
            <label htmlFor="commentaire" className="block text-sm font-semibold text-gray-700 mb-1.5">
              Votre commentaire <span className="text-[#f31976]">*</span>
            </label>
            <textarea
              id="commentaire"
              rows={4}
              value={commentaire}
              onChange={(e) => setCommentaire(e.target.value)}
              placeholder="Décrivez votre expérience (minimum 10 caractères)..."
              className="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-800 placeholder:text-gray-400 focus:border-[#f31976] focus:outline-none focus:ring-2 focus:ring-[#f31976]/20 resize-none"
            />
            <div className="flex items-center justify-between mt-1">
              {fieldErrors.commentaire ? (
                <p className="text-xs text-red-500">{fieldErrors.commentaire[0]}</p>
              ) : (
                <span />
              )}
              <p className="text-xs text-gray-400 tabular-nums">{commentaire.length}/1000</p>
            </div>
          </div>

          {/* Bouton */}
          <button
            type="submit"
            disabled={submitting}
            className="w-full rounded-full bg-[#f31976] py-3 text-sm font-black text-white shadow-sm transition-opacity hover:opacity-90 disabled:opacity-50"
          >
            {submitting ? 'Envoi...' : 'Envoyer mon avis'}
          </button>
        </form>
      </div>
    </div>
  )
}
