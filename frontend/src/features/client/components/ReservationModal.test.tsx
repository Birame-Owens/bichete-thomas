import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'

// --- Mocks ------------------------------------------------------------------
// On isole le composant des appels reseau : les fonctions API sont remplacees
// par des mocks, et usePhoneLookup renvoie un etat inerte (pas de fetch tel).
vi.mock('../client.api', () => ({
  getClientCoiffureDetails: vi.fn(),
  getClientAvailability: vi.fn(),
  createClientReservation: vi.fn(),
}))
vi.mock('../hooks/usePhoneLookup', () => ({
  usePhoneLookup: () => ({ state: 'idle', data: null }),
}))

import ReservationModal from './ReservationModal'
import { createClientReservation, getClientAvailability, getClientCoiffureDetails } from '../client.api'
import type {
  ClientAvailability,
  ClientCoiffure,
  ClientReservationResponse,
  ClientSettings,
} from '../client.types'

// --- Fausses donnees --------------------------------------------------------
function makeCoiffure(): ClientCoiffure {
  return {
    id: 1,
    nom: 'Knotless Braids',
    description: 'Tresses sans noeud.',
    image: '/img.jpg',
    est_populaire: true,
    est_nouveaute: false,
    categorie: { id: 2, nom: 'Tresses' },
    prix_min: 15000,
    duree_min_minutes: 120,
    images: [{ id: 10, url: '/img.jpg', alt: 'Knotless', principale: true }],
    avis_resume: { moyenne: 0, total: 0 },
    avis: [],
    prestations_recentes: [],
    coiffures_liees: [],
    variantes: [
      { id: 100, nom: 'Court', prix: 15000, duree_minutes: 120 },
      { id: 101, nom: 'Long', prix: 25000, duree_minutes: 180 },
    ],
    options: [{ id: 200, nom: 'Perles', prix: 2000 }],
  }
}

function makeSettings(): ClientSettings {
  return {
    devise: 'FCFA',
    telephone_whatsapp: '+221770000000',
    heure_ouverture: '09:00',
    heure_fermeture: '19:00',
    jours_fermeture: [],
    montant_acompte_defaut: 5000,
    pourcentage_acompte: 30,
    limite_reservations_par_jour: 20,
    limite_reservations_par_creneau: 3,
    paiements_en_ligne: { wave: true, orange_money: true, carte_bancaire: true },
  }
}

function makeAvailability(): ClientAvailability {
  return {
    date: '2026-12-01',
    heure_ouverture: '09:00',
    heure_fermeture: '19:00',
    limite_reservations_par_jour: 20,
    reservations_jour: 0,
    jour_complet: false,
    jour_ferme: false,
    limite_reservations_par_creneau: 3,
    creneaux: [
      { heure: '09:00', reservations: 0, limite: 3, disponible: true, raison: null },
      { heure: '10:00', reservations: 0, limite: 3, disponible: true, raison: null },
    ],
  }
}

function makeReservationResponse(requiresRedirect = false): ClientReservationResponse {
  return {
    message: 'Reservation enregistree.',
    data: {
      id: 42,
      date_reservation: '2026-12-01',
      heure_debut: '09:00',
      heure_fin: '11:00',
      statut: 'en_attente',
      montant_total: 15000,
      montant_reduction: 0,
      montant_acompte: 4500,
      montant_restant: 10500,
      devise: 'FCFA',
    },
    payment: {
      id: 7,
      reservation_id: 42,
      client_id: null,
      numero_recu: 'R-001',
      type: 'acompte',
      mode_paiement: 'wave',
      montant: 4500,
      devise: 'FCFA',
      statut: 'en_attente',
      reference: null,
    },
    checkout_url: requiresRedirect ? 'https://checkout.naboopay.com/abc' : null,
    requires_redirect: requiresRedirect,
  }
}

function renderModal(onClose = vi.fn()) {
  return {
    onClose,
    ...render(
      <ReservationModal
        coiffure={makeCoiffure()}
        settings={makeSettings()}
        devise="FCFA"
        promotions={[]}
        onClose={onClose}
      />,
    ),
  }
}

beforeEach(() => {
  vi.mocked(getClientCoiffureDetails).mockResolvedValue(makeCoiffure())
  vi.mocked(getClientAvailability).mockResolvedValue(makeAvailability())
  vi.mocked(createClientReservation).mockResolvedValue(makeReservationResponse())
})

describe('ReservationModal', () => {
  it('charge les details et affiche les variantes a l etape Prestation', async () => {
    renderModal()

    expect(await screen.findByRole('heading', { name: 'Knotless Braids' })).toBeInTheDocument()
    expect(screen.getByText('Court')).toBeInTheDocument()
    expect(screen.getByText('Long')).toBeInTheDocument()
    expect(screen.getByText(/Etape 1/)).toBeInTheDocument()
    expect(getClientCoiffureDetails).toHaveBeenCalledWith(1)
  })

  it('navigue dans l assistant : Prestation -> Date & heure -> Paiement', async () => {
    const user = userEvent.setup()
    renderModal()
    await screen.findByText('Court')

    await user.click(screen.getByRole('button', { name: /Continuer/ }))
    expect(await screen.findByText(/Etape 2/)).toBeInTheDocument()

    // Les creneaux de disponibilite sont charges.
    await screen.findByRole('button', { name: '09:00' })

    await user.click(screen.getByRole('button', { name: /Continuer/ }))
    expect(await screen.findByText(/Etape 3/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Confirmer et payer/ })).toBeInTheDocument()
  })

  it('soumet la reservation avec les bonnes infos', async () => {
    const user = userEvent.setup()
    renderModal()
    await screen.findByText('Court')

    // Etape 1 -> 2
    await user.click(screen.getByRole('button', { name: /Continuer/ }))
    await user.click(await screen.findByRole('button', { name: '09:00' }))

    // Etape 2 -> 3
    await user.click(screen.getByRole('button', { name: /Continuer/ }))
    await screen.findByText(/Etape 3/)

    // Coordonnees (champs requis)
    await user.type(screen.getByPlaceholderText('77 123 45 67'), '770000000')
    await user.type(screen.getByLabelText('Prenom'), 'Awa')
    await user.type(screen.getByLabelText('Nom'), 'Diop')

    await user.click(screen.getByRole('button', { name: /Confirmer et payer/ }))

    await waitFor(() => expect(createClientReservation).toHaveBeenCalledTimes(1))
    const payload = vi.mocked(createClientReservation).mock.calls[0][0]
    expect(payload.coiffure_id).toBe(1)
    expect(payload.variante_coiffure_id).toBe(100)
    expect(payload.client.prenom).toBe('Awa')
    expect(payload.client.nom).toBe('Diop')
    expect(payload.heure_debut).toBe('09:00')
  })

  it('appelle onClose au clic sur Fermer', async () => {
    const user = userEvent.setup()
    const { onClose } = renderModal()
    await screen.findByText('Court')

    await user.click(screen.getByRole('button', { name: 'Fermer' }))
    expect(onClose).toHaveBeenCalledTimes(1)
  })
})
