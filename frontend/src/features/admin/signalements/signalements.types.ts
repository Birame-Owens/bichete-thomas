export type SignalementType = 'produit' | 'materiel' | 'autre'
export type SignalementUrgence = 'normale' | 'urgente'

export type Signalement = {
  id: number
  gerante_id: number
  type: SignalementType
  titre: string
  description: string | null
  urgence: SignalementUrgence
  lu_par_admin: boolean
  lu_at: string | null
  traite: boolean
  traite_at: string | null
  note_admin: string | null
  created_at: string
  updated_at: string
  gerante?: { id: number; name: string; email: string } | null
}

export type SignalementForm = {
  type: SignalementType
  titre: string
  description: string
  urgence: SignalementUrgence
}

export type NonLusCountResponse = { count: number }
