// Types du module ecommerce admin.
// Le backend renvoie ses reponses sous la forme {success, data: {...}} ;
// la couche ecommerce.api.ts se charge de les aplatir vers LaravelPaginated
// pour que les pages consomment un format unique.

// ============= CATEGORIES =============

export interface Categorie {
  id: number
  nom: string
  slug: string
  description?: string | null
  image?: string | null
  parent_id?: number | null
  ordre_affichage: number
  est_active: boolean
  est_populaire: boolean
  couleur_theme?: string | null
  produits_count?: number
  sous_categories_count?: number
  sous_categories?: SousCategorie[]
  created_at?: string
  updated_at?: string
}

/** Alias historique (le backend nomme le modele Category). */
export type Category = Categorie

/** Sous-categorie renvoyee par categories?type=parents et /sous-categories. */
export interface SousCategorie {
  id: number
  nom: string
  slug: string
  description?: string | null
  image?: string | null
  parent_id: number
  est_active: boolean
  produits_count: number
  created_at?: string
}

/** Cartes du header de la page categories (GET categories/stats). */
export interface CategoryStats {
  total_categories: number
  total_sous_categories: number
  total_produits: number
  categories_actives: number
}

/** Option plate pour les selects (GET categories/options). */
export interface CategoryOption {
  id: number
  nom: string
  parent_id: number | null
}

export interface CategorieForm {
  nom: string
  slug: string
  description: string
  image: File | null
  ordre_affichage: string
  est_active: boolean
  est_populaire: boolean
}

// ============= PRODUITS =============

export interface StockStatus {
  status: 'unlimited' | 'out_of_stock' | 'low_stock' | 'in_stock'
  label: string
  color: string
}

export interface ProduitImage {
  id: number
  nom_fichier: string
  url_originale: string
  url_miniature?: string | null
  url_moyenne?: string | null
  alt_text?: string | null
  ordre_affichage: number
  est_principale: boolean
  couleur_associee?: string | null
}

export type TypeVariante = 'vetement' | 'chaussure' | 'parfum' | 'aucun'

export interface Produit {
  id: number
  nom: string
  slug: string
  description: string
  description_courte?: string | null
  prix: number
  prix_promo?: number | null
  prix_actuel?: number
  en_promo?: boolean
  debut_promo?: string | null
  fin_promo?: string | null
  image_principale?: string | null
  images?: ProduitImage[]
  categorie?: { id: number; nom: string; slug: string } | null
  /** Aplati par ecommerce.api.ts depuis categorie.id (le backend ne renvoie que l objet categorie). */
  categorie_id: number
  stock_disponible: number
  seuil_alerte: number
  stock_status?: StockStatus
  gestion_stock?: boolean
  fait_sur_mesure?: boolean
  delai_production_jours?: number | null
  cout_production?: number | null
  type_variante?: TypeVariante
  tailles_disponibles?: string[]
  couleurs_disponibles?: string[]
  couleur_tailles?: Record<string, string[]> | null
  couleur_tailles_stock?: Record<string, Record<string, number>> | null
  couleur_tailles_seuil?: Record<string, Record<string, number>> | null
  materiaux_necessaires?: string[]
  est_visible: boolean
  est_populaire: boolean
  est_nouveaute: boolean
  ordre_affichage: number
  nombre_vues?: number
  nombre_ventes?: number
  note_moyenne?: number | null
  nombre_avis?: number
  meta_titre?: string | null
  meta_description?: string | null
  tags?: string
  created_at?: string
  updated_at?: string
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

// ============= COMMANDES =============

export type CommandeStatut =
  | 'en_attente'
  | 'confirmee'
  | 'en_preparation'
  | 'en_production'
  | 'prete'
  | 'en_livraison'
  | 'livree'
  | 'annulee'
  | 'echoue'
  | 'retournee'

export interface ArticleCommande {
  id: number
  nom_produit: string
  quantite: number
  prix_unitaire?: number
  prix_total_article: number
}

export interface Commande {
  id: number
  numero_commande: string
  client?: { id: number; nom_complet: string; telephone: string } | null
  nom_destinataire: string
  telephone_livraison: string
  adresse_livraison?: string | null
  montant_total: number
  statut: CommandeStatut
  statut_label?: string
  priorite?: string | null
  source?: string | null
  created_at?: string
  date_commande?: string
  date_livraison_prevue?: string | null
  nb_articles?: number
  est_payee?: boolean
  est_en_retard?: boolean
  peut_supprimer?: boolean
  mode_livraison?: string | null
  zone_livraison_nom?: string | null
  sous_total?: number
  frais_livraison?: number
  instructions_livraison?: string | null
  /** Aplati par ecommerce.api.ts depuis data.commande.articles (format detail). */
  articles_commandes?: ArticleCommande[]
}

export interface CommandeStatusUpdate {
  statut: CommandeStatut
}

// ============= STATS / DIVERS =============

export interface TopClientMois {
  id: number
  nom: string
  prenom: string
  telephone: string
  paiements_count: number
  total_paye: number
}

export interface EvolutionMensuelle {
  mois: string
  commandes: number
  chiffre_affaires: number
}

export interface KPIStats {
  total_commandes: number
  commandes_aujourd_hui: number
  commandes_ce_mois: number
  commandes_cette_annee: number
  ca_total: number
  ca_ce_mois: number
  ca_cette_annee: number
  /** {statut: total} — tableau vide [] quand aucune commande (pluck PHP). */
  commandes_par_statut: Record<string, number> | []
  commandes_en_attente: number
  commandes_en_preparation: number
  commandes_pretes: number
  commandes_payees: number
  commandes_urgentes: number
  commandes_en_retard: number
  panier_moyen: number
  top_clients_mois: TopClientMois[]
  evolution_mensuelle: EvolutionMensuelle[]
  [key: string]: unknown
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

// ============= ENVELOPPES API =============

/** Enveloppe generique du backend : {success, message?, data}. */
export interface ApiEnvelope<T> {
  success: boolean
  message?: string
  data: T
}

export interface BackendPagination {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

/** Format pagine unique consomme par les pages. */
export interface LaravelPaginated<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}
