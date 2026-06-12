import * as Sentry from '@sentry/react'

/**
 * Initialise Sentry pour capter les erreurs JS du front (erreurs non gerees +
 * rejets de promesse). N'envoie RIEN tant qu'aucun DSN n'est fourni : en dev
 * local (pas de VITE_SENTRY_DSN), Sentry reste totalement inactif.
 *
 * Le DSN est injecte au BUILD (Vite inline les variables VITE_*), donc il doit
 * etre passe comme build-arg a l'image nginx (cf docker-compose.prod.yml).
 *
 * Par defaut : erreurs uniquement (tracesSampleRate 0 = pas de monitoring de
 * performance), pour rester leger et gratuit. Activable via VITE_SENTRY_TRACES.
 */
export function initSentry(): void {
  const dsn = import.meta.env.VITE_SENTRY_DSN as string | undefined

  if (!dsn) {
    return
  }

  Sentry.init({
    dsn,
    environment: import.meta.env.MODE,
    tracesSampleRate: Number(import.meta.env.VITE_SENTRY_TRACES ?? 0),
    // On ne collecte pas d'infos personnelles par defaut (RGPD-friendly).
    sendDefaultPii: false,
  })
}
