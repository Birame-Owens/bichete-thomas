export interface Category {
  id: number;
  nom: string;
  slug: string;
  description?: string;
  image?: string;
  est_active: boolean;
  created_at: string;
}

export interface Produit {
  id: number;
  nom: string;
  prix: number;
  stock_disponible: number;
  categorie_id: number;
  description_courte?: string;
  description?: string;
  est_visible: boolean;
  image_principale?: string;
  created_at: string;
}

export interface Commande {
  id: number;
  numero_commande: string;
  client_nom: string;
  client_tel: string;
  montant_total: number;
  statut: 'en_attente' | 'confirmee' | 'en_livraison' | 'livree' | 'annulea';
  date_commande: string;
  adresse_livraison?: string;
  articles?: Array<{ nom: string; quantite: number; prix: number }>;
}

export interface LaravelPaginated<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}
