import axios from 'axios'
import { useEffect, useRef, useState } from 'react'
import { isValidPhoneNumber } from 'react-phone-number-input'
import { lookupClientByPhone } from '../client.api'
import type { ClientLookupResponse } from '../client.types'

/**
 * Etats du hook usePhoneLookup (Phase 5 etape 1).
 *
 * idle = tel vide ou invalide format (rien a chercher).
 * loading = E.164 valide, fetch en cours (apres debounce).
 * found = backend a renvoye un client.
 * not_found = backend a renvoye found:false (tel inconnu ou non parsable).
 * throttled = backend a renvoye 429 (5/min epuises) - rare, on attend la frappe suivante.
 * error = autre erreur reseau, peu probable vu retry I12 sur GET.
 */
export type LookupState = 'idle' | 'loading' | 'found' | 'not_found' | 'throttled' | 'error'

export type PhoneLookupOutcome = {
  state: LookupState
  data: ClientLookupResponse | null
}

const INITIAL: PhoneLookupOutcome = { state: 'idle', data: null }

/**
 * Lance un lookup backend `GET /api/client/lookup` debounce des qu un tel
 * formellement E.164 (validation libphonenumber locale) est saisi.
 *
 * - Skip la requete si le tel n est pas valide -> evite de cramer la quota
 *   throttle:5,1 sur des inputs intermediaires.
 * - AbortController annule les fetch obsoletes quand l utilisateur retape vite.
 * - 429 backend traite en silence : la quota se reinitialise vite (1 min)
 *   et l utilisateur ne perd rien (il a juste a retaper apres une pause).
 *
 * @param tel  Tel courant du form. Peut etre undefined ou string E.164 partielle.
 * @param debounceMs  Pause apres la derniere frappe avant fetch.
 */
export function usePhoneLookup(tel: string | undefined, debounceMs = 300): PhoneLookupOutcome {
  const [outcome, setOutcome] = useState<PhoneLookupOutcome>(INITIAL)
  // Garde l AbortController courant pour annuler la requete precedente si
  // l utilisateur tape vite (sinon course condition : ancien lookup arrive
  // apres le nouveau et ecrase le bon resultat).
  const controllerRef = useRef<AbortController | null>(null)

  useEffect(() => {
    // Reset propre quand le tel sort du format valide (efface, retape, etc.).
    if (!tel || !isValidPhoneNumber(tel)) {
      controllerRef.current?.abort()
      setOutcome(INITIAL)

      return
    }

    const timer = setTimeout(() => {
      // Annule le precedent fetch en vol avant d en lancer un nouveau.
      controllerRef.current?.abort()
      const controller = new AbortController()
      controllerRef.current = controller

      setOutcome((prev) => ({ state: 'loading', data: prev.data }))

      lookupClientByPhone(tel, controller.signal)
        .then((data) => {
          setOutcome({
            state: data.found ? 'found' : 'not_found',
            data,
          })
        })
        .catch((error: unknown) => {
          // axios.isCancel = abort volontaire, on ignore (un fetch plus recent prend le relai).
          if (axios.isCancel(error)) {
            return
          }
          // 429 = throttle epuise. On reste sur "not_found" sans bruit pour l UX.
          // L utilisateur ne sait pas qu il y a un throttle, il retapera plus tard
          // si besoin.
          const status = axios.isAxiosError(error) ? error.response?.status : null
          if (status === 429) {
            setOutcome({ state: 'throttled', data: null })

            return
          }
          setOutcome({ state: 'error', data: null })
        })
    }, debounceMs)

    return () => {
      clearTimeout(timer)
    }
  }, [tel, debounceMs])

  // Cleanup global au unmount : abort tout fetch en cours pour eviter les
  // setState sur composant demonte.
  useEffect(() => {
    return () => {
      controllerRef.current?.abort()
    }
  }, [])

  return outcome
}
