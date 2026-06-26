// Types pour l'ecommerce

export interface LaravelPaginated<T> {
  current_page: number
  data: T[]
  from: number
  last_page: number
  links: Array<{ url: string | null; label: string; active: boolean }>
  next_page_url: string | null
  path: string
  per_page: number
  prev_page_url: string | null
  to: number
  total: number
}

export interface ApiCollection<T> {
  data: LaravelPaginated<T>
}

export interface ApiItem<T> {
  message?: string
  data: T
}

export interface Categorie {
  id: number
  nom: string
  slug: string
  description: string | null
  image: string | null
  parent_id: number | null
  ordre_affichage: number
  est_active: boolean
  est_populaire: boolean
  couleur_theme: string | null
  meta_donnees: Record<string, any> | null
  created_at?: string
  updated_at?: string
  _count?: { produits: number }
}

export interface Produit {
  id: number
  nom: string
  slug: string
  description: string
  description_courte: string | null
  image_principale: string
  prix: number
  prix_promo: number | null
  debut_promo: string | null
  fin_promo: string | null
  categorie_id: number
  stock_disponible: number
  seuil_alerte: number
  gestion_stock: boolean
  fait_sur_mesure: boolean
  delai_production_jours: number | null
  cout_production: number | null
  tailles_disponibles: string[] | null
  couleurs_disponibles: string[] | null
  materiaux_necessaires: Record<string, any> | null
  est_visible: boolean
  est_populaire: boolean
  est_nouveaute: boolean
  ordre_affichage: number
  nombre_vues: number
  nombre_ventes: number
  note_moyenne: number
  nombre_avis: number
  meta_titre: string | null
  meta_description: string | null
  tags: string[] | null
  created_at?: string
  updated_at?: string
  category?: Categorie
  images_produits?: ImageProduit[]
}

export interface ImageProduit {
  id: number
  produit_id: number
  nom_fichier: string
  chemin_original: string
  chemin_miniature: string
  chemin_moyen: string
  alt_text: string | null
  titre: string | null
  description: string | null
  ordre_affichage: number
  est_principale: boolean
  est_visible: boolean
  format: string
  taille_octets: number
  largeur: number
  hauteur: number
  couleur_dominante: string | null
  couleur_associee: string | null
  created_at?: string
  updated_at?: string
}

export interface Commande {
  id: number
  numero_commande: string
  client_id: number
  sous_total: number
  frais_livraison: number
  remise: number
  montant_tva: number
  montant_total: number
  statut: CommandeStatut
  date_confirmation: string | null
  date_debut_production: string | null
  date_fin_production: string | null
  date_livraison_prevue: string | null
  date_livraison_reelle: string | null
  adresse_livraison: string
  telephone_livraison: string
  nom_destinataire: string
  instructions_livraison: string | null
  mode_livraison: 'domicile' | 'boutique' | 'point_relais'
  notes_client: string | null
  notes_admin: string | null
  notes_production: string | null
  source: 'site_web' | 'whatsapp' | 'telephone' | 'boutique' | 'facebook' | 'admin'
  code_promo: string | null
  priorite: 'normale' | 'urgente' | 'tres_urgente'
  est_cadeau: boolean
  message_cadeau: string | null
  note_satisfaction: number | null
  commentaire_satisfaction: string | null
  created_at?: string
  updated_at?: string
  client?: any
  articles_commandes?: ArticleCommande[]
  paiements?: any[]
}

export type CommandeStatut = 'en_attente' | 'confirmee' | 'en_preparation' | 'en_production' | 'prete' | 'en_livraison' | 'livree' | 'annulee' | 'echoue' | 'retournee'

export interface ArticleCommande {
  id: number
  commande_id: number
  produit_id: number
  nom_produit: string
  description_produit: string | null
  prix_unitaire: number
  quantite: number
  prix_total_article: number
  taille_choisie: string | null
  couleur_choisie: string | null
  options_supplementaires: Record<string, any> | null
  demandes_personnalisation: string | null
  mesures_client: Record<string, any> | null
  instructions_tailleur: string | null
  tailleur_id: number | null
  created_at?: string
  updated_at?: string
}

export interface DeliveryZone {
  id: number
  nom: string
  prix: number
  est_active: boolean
  ordre_affichage: number
  created_at?: string
  updated_at?: string
}

export interface ShopSetting {
  id?: number
  key: string
  value: string | null
  group: string
  created_at?: string
  updated_at?: string
}

export interface ShippingSetting {
  id?: number
  default_cost: number
  free_threshold: number
  is_enabled: boolean
  created_at?: string
  updated_at?: string
}

export interface KPIStats {
  chiffre_affaires: number
  nombre_commandes: number
  nombre_produits: number
  commandes_en_attente: number
}

// Form types (pour les inputs contrôlés)
export interface CategorieForm {
  nom: string
  slug: string
  description: string
  image: File | null
  ordre_affichage: string
  est_active: boolean
  est_populaire: boolean
}

export interface ProduitForm {
  nom: string
  slug: string
  description: string
  description_courte: string
  prix: string
  prix_promo: string
  categorie_id: string
  stock_disponible: string
  seuil_alerte: string
  est_visible: boolean
  est_populaire: boolean
  est_nouveaute: boolean
  image_principale: File | null
  order_affichage: string
}

export interface CommandeStatusUpdate {
  statut: CommandeStatut
}
