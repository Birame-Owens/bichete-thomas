import { useEffect, useState } from 'react'
import { CheckCircle, Loader2, XCircle } from 'lucide-react'
import { confirmNaboopayReturn } from '../client.api'
import { BoutiqueHeader } from './BoutiqueHeader'
import { clearPanier } from './panier'

type Etat = 'verification' | 'succes' | 'echec' | 'annule'

// Page /boutique/commande-confirmee :
// - retour NabooPay (paiement=naboopay_success + paiement_id + signature)
//   => on confirme aupres du backend (double verification serveur), puis
//   on vide le panier.
// - paiement a la livraison (commande=CMD-...) => confirmation directe.
export function CommandeConfirmeePage() {
  const params = new URLSearchParams(window.location.search)
  const paiementFlow = params.get('paiement')
  const numeroCommande = params.get('commande')

  const [etat, setEtat] = useState<Etat>(() => {
    if (paiementFlow === 'naboopay_success') return 'verification'
    if (paiementFlow === 'naboopay_cancel') return 'annule'
    return 'succes'
  })

  useEffect(() => {
    if (paiementFlow !== 'naboopay_success') {
      if (etat === 'succes') clearPanier()
      return
    }

    const paiementId = params.get('paiement_id')
    const signature = params.get('signature')

    if (!paiementId || !signature) {
      setEtat('echec')
      return
    }

    confirmNaboopayReturn(paiementId, signature)
      .then(() => {
        clearPanier()
        setEtat('succes')
      })
      .catch(() => setEtat('echec'))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  return (
    <div className="min-h-screen bg-[#faf5f8]">
      <BoutiqueHeader backHref="/boutique" backLabel="Boutique" />

      <main className="grid min-h-[70vh] place-items-center px-4">
        <div className="w-full max-w-md bg-white p-10 text-center">
          {etat === 'verification' ? (
            <>
              <Loader2 className="mx-auto h-12 w-12 animate-spin text-[#f31976]" />
              <h1 className="mt-5 text-xl font-black uppercase tracking-[0.12em] text-slate-950">
                Vérification du paiement…
              </h1>
              <p className="mt-2 text-sm font-semibold text-slate-500">
                Un instant, nous confirmons votre transaction.
              </p>
            </>
          ) : etat === 'succes' ? (
            <>
              <CheckCircle className="mx-auto h-14 w-14 text-emerald-500" />
              <h1 className="mt-5 text-xl font-black uppercase tracking-[0.12em] text-slate-950">
                Commande confirmée !
              </h1>
              {numeroCommande ? (
                <p className="mt-3 bg-[#fff0f6] px-4 py-2 text-sm font-black text-[#f31976]">
                  {numeroCommande}
                </p>
              ) : null}
              <p className="mt-3 text-sm font-semibold text-slate-500">
                Merci pour votre commande ! Le salon vous contactera très vite sur WhatsApp
                pour organiser la {'livraison ou le retrait au salon'}.
              </p>
              <a
                href="/boutique"
                className="mt-6 inline-flex h-12 items-center bg-[#f31976] px-8 text-xs font-black uppercase tracking-[0.16em] text-white transition hover:brightness-110"
              >
                Retour à la boutique
              </a>
            </>
          ) : etat === 'annule' ? (
            <>
              <XCircle className="mx-auto h-14 w-14 text-amber-500" />
              <h1 className="mt-5 text-xl font-black uppercase tracking-[0.12em] text-slate-950">
                Paiement annulé
              </h1>
              <p className="mt-3 text-sm font-semibold text-slate-500">
                Votre paiement n'a pas abouti. Votre panier est conservé — vous pouvez réessayer
                ou choisir le paiement à la livraison.
              </p>
              <a
                href="/boutique/commander"
                className="mt-6 inline-flex h-12 items-center bg-[#f31976] px-8 text-xs font-black uppercase tracking-[0.16em] text-white transition hover:brightness-110"
              >
                Réessayer
              </a>
            </>
          ) : (
            <>
              <XCircle className="mx-auto h-14 w-14 text-rose-500" />
              <h1 className="mt-5 text-xl font-black uppercase tracking-[0.12em] text-slate-950">
                Vérification impossible
              </h1>
              <p className="mt-3 text-sm font-semibold text-slate-500">
                Nous n'avons pas pu confirmer le paiement immédiatement. Si vous avez été débitée,
                pas d'inquiétude : la confirmation arrivera automatiquement et le salon vous contactera.
              </p>
              <a
                href="/boutique"
                className="mt-6 inline-flex h-12 items-center bg-[#f31976] px-8 text-xs font-black uppercase tracking-[0.16em] text-white transition hover:brightness-110"
              >
                Retour à la boutique
              </a>
            </>
          )}
        </div>
      </main>
    </div>
  )
}
