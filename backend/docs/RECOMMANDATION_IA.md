# Système de Recommandation IA — NDEYA SHOP

> Feuille de route pour implémenter un moteur de recommandation progressif.
> Architecture actuelle : Laravel 11 + PostgreSQL + Redis sur Coolify.

---

## Ce qui existe déjà (données utilisables)

| Données | Table | Signal |
|---|---|---|
| Historique achats par client | `commandes` + `articles_commande` | Fort |
| Produits achetés ensemble | `articles_commande` (même `commande_id`) | Fort |
| Wishlist | `wishlists` | Fort (intention) |
| Vues produit (global) | `produits.nombre_vues` | Faible (pas individuel) |
| Notes et avis | `avis_clients` | Moyen |
| Couleurs et tailles achetées | `articles_commande.couleur_choisie` / `taille_choisie` | Moyen |
| Catégories, prix, tags | `produits` | Contenu |

**Problème principal :** `nombre_vues` est un compteur global.
On sait qu'un produit a 200 vues mais pas **qui** l'a vu.
Sans tracking individuel, impossible de faire "les gens qui ont vu X ont aussi vu Y".

---

## Phase 1 — Préparer le terrain (à faire maintenant)

### 1.1 Migration : table de tracking des vues

Créer le fichier `database/migrations/XXXX_create_product_views_table.php` :

```php
Schema::create('product_views', function (Blueprint $table) {
    $table->id();
    $table->foreignId('produit_id')->constrained('produits')->onDelete('cascade');
    $table->string('session_id', 100)->nullable(); // visiteur anonyme
    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('created_at')->useCurrent();

    $table->index(['produit_id', 'created_at']);
    $table->index('session_id');
    $table->index(['user_id', 'created_at']);
});
```

### 1.2 Modifier ProductService.php

Dans `app/Services/Client/ProductService.php`, méthode `getProductBySlug()`,
remplacer le dispatch existant par :

```php
// Incrémenter les vues en arrière-plan (non bloquant)
dispatch(function () use ($product) {
    Produit::where('id', $product->id)->increment('nombre_vues');

    // Tracking individuel pour les recommandations futures
    \DB::table('product_views')->insert([
        'produit_id' => $product->id,
        'session_id' => session()->getId(),
        'user_id'    => auth('sanctum')->id(), // null si non connecté
        'created_at' => now(),
    ]);
})->afterResponse();
```

### 1.3 Nettoyage automatique (optionnel)

Les vues de plus de 90 jours ne sont plus utiles pour les recommandations.
Ajouter une commande artisan ou un job planifié :

```php
// Dans app/Console/Kernel.php ou un Job
\DB::table('product_views')
    ->where('created_at', '<', now()->subDays(90))
    ->delete();
```

Planifier dans `routes/console.php` :
```php
Schedule::command('db:prune-views')->weekly();
```

---

## Phase 2 — Recommandations SQL (dès 100 commandes)

Aucune dépendance externe. Tout se fait avec PostgreSQL.

### 2.1 "Souvent achetés ensemble"

```sql
SELECT
    p.id,
    p.nom,
    p.slug,
    p.prix,
    COUNT(*) AS frequence
FROM articles_commande ac1
JOIN articles_commande ac2
    ON ac1.commande_id = ac2.commande_id
    AND ac2.produit_id != ac1.produit_id
JOIN produits p
    ON p.id = ac2.produit_id
    AND p.est_visible = true
    AND p.deleted_at IS NULL
JOIN commandes c
    ON c.id = ac1.commande_id
    AND c.statut IN ('confirmee', 'en_preparation', 'prete', 'livree')
WHERE ac1.produit_id = :produit_id
GROUP BY p.id, p.nom, p.slug, p.prix
ORDER BY frequence DESC
LIMIT 6;
```

### 2.2 "Populaires dans la même catégorie"

```sql
SELECT id, nom, slug, prix, nombre_ventes
FROM produits
WHERE categorie_id = :categorie_id
  AND id != :produit_id
  AND est_visible = true
  AND deleted_at IS NULL
ORDER BY nombre_ventes DESC
LIMIT 6;
```

### 2.3 "Basé sur vos achats" (pour client connecté)

```sql
-- Trouver les catégories que ce client achète
SELECT DISTINCT p.categorie_id
FROM commandes c
JOIN articles_commande ac ON ac.commande_id = c.id
JOIN produits p ON p.id = ac.produit_id
WHERE c.client_id = :client_id
  AND c.statut IN ('confirmee', 'livree');

-- Puis recommander les best-sellers de ces catégories non encore achetés
SELECT p.id, p.nom, p.slug, p.prix
FROM produits p
WHERE p.categorie_id IN (:categories_client)
  AND p.id NOT IN (:produits_deja_achetes)
  AND p.est_visible = true
  AND p.deleted_at IS NULL
ORDER BY p.nombre_ventes DESC
LIMIT 6;
```

### 2.4 Implémenter dans Laravel

Créer `app/Services/Client/RecommandationService.php` :

```php
<?php

namespace App\Services\Client;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class RecommandationService
{
    public function achetiEnsemble(int $produitId, int $limit = 6): array
    {
        return Cache::remember("reco:ensemble:{$produitId}", 3600, function () use ($produitId, $limit) {
            return DB::table('articles_commande as ac1')
                ->join('articles_commande as ac2', function ($join) use ($produitId) {
                    $join->on('ac1.commande_id', '=', 'ac2.commande_id')
                         ->where('ac2.produit_id', '!=', $produitId);
                })
                ->join('produits as p', 'p.id', '=', 'ac2.produit_id')
                ->join('commandes as c', 'c.id', '=', 'ac1.commande_id')
                ->where('ac1.produit_id', $produitId)
                ->where('p.est_visible', true)
                ->whereNull('p.deleted_at')
                ->whereIn('c.statut', ['confirmee', 'en_preparation', 'prete', 'livree'])
                ->select('p.id', 'p.nom', 'p.slug', 'p.prix', DB::raw('COUNT(*) as frequence'))
                ->groupBy('p.id', 'p.nom', 'p.slug', 'p.prix')
                ->orderByDesc('frequence')
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }

    public function populairesDansCategorie(int $categorieId, int $exclureProduitId, int $limit = 6): array
    {
        return Cache::remember("reco:categorie:{$categorieId}:{$exclureProduitId}", 1800, function () use ($categorieId, $exclureProduitId, $limit) {
            return DB::table('produits')
                ->where('categorie_id', $categorieId)
                ->where('id', '!=', $exclureProduitId)
                ->where('est_visible', true)
                ->whereNull('deleted_at')
                ->orderByDesc('nombre_ventes')
                ->select('id', 'nom', 'slug', 'prix', 'prix_promo')
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }

    public function pourClient(int $clientId, int $limit = 6): array
    {
        return Cache::remember("reco:client:{$clientId}", 900, function () use ($clientId, $limit) {
            $categories = DB::table('commandes as c')
                ->join('articles_commande as ac', 'ac.commande_id', '=', 'c.id')
                ->join('produits as p', 'p.id', '=', 'ac.produit_id')
                ->where('c.client_id', $clientId)
                ->whereIn('c.statut', ['confirmee', 'livree'])
                ->pluck('p.categorie_id')
                ->unique()
                ->toArray();

            $dejaAchetes = DB::table('commandes as c')
                ->join('articles_commande as ac', 'ac.commande_id', '=', 'c.id')
                ->where('c.client_id', $clientId)
                ->pluck('ac.produit_id')
                ->toArray();

            if (empty($categories)) return [];

            return DB::table('produits')
                ->whereIn('categorie_id', $categories)
                ->whereNotIn('id', $dejaAchetes)
                ->where('est_visible', true)
                ->whereNull('deleted_at')
                ->orderByDesc('nombre_ventes')
                ->select('id', 'nom', 'slug', 'prix', 'prix_promo')
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }
}
```

### 2.5 Ajouter la route API

Dans `routes/api.php` :
```php
Route::get('/produits/{produit}/recommandations', [RecommandationController::class, 'index']);
```

Réponse JSON :
```json
{
  "achetes_ensemble": [...],
  "meme_categorie": [...],
  "pour_vous": [...]
}
```

---

## Phase 3 — Filtrage collaboratif avec Python (dès 500 clients)

Quand les requêtes SQL ne suffisent plus ou manquent de précision.

### 3.1 Principe

```
Client A a acheté : Robe Noir, Sac Marron, Ceinture Cognac
Client B a acheté : Robe Noir, Sac Marron, Collier Doré
→ Recommander à A : Collier Doré  (acheté par quelqu'un avec le même goût)
```

C'est le **filtrage collaboratif user-based** — même logique que Netflix/Spotify.

### 3.2 Stack Python recommandée

```
FastAPI (serveur HTTP léger)
├── Surprise (algorithme SVD/KNN)
│   ou LightFM (matrix factorization avec attributs produit)
├── PostgreSQL (même base, accès direct)
└── Redis (cache des scores calculés)
```

### 3.3 Structure du microservice

```
recommandation-service/
├── main.py               # FastAPI app
├── model.py              # Chargement et entraînement du modèle
├── train.py              # Script d'entraînement (cron quotidien)
├── requirements.txt
└── Dockerfile
```

`requirements.txt` :
```
fastapi==0.111.0
uvicorn==0.29.0
scikit-surprise==1.1.3
psycopg2-binary==2.9.9
redis==5.0.4
pandas==2.2.2
```

### 3.4 Exemple main.py

```python
from fastapi import FastAPI
from surprise import SVD, Dataset, Reader
from surprise.model_selection import train_test_split
import psycopg2
import pandas as pd
import redis
import json

app = FastAPI()
cache = redis.Redis(host='redis', decode_responses=True)

def charger_donnees():
    conn = psycopg2.connect("postgresql://user:pass@db/ndeya")
    df = pd.read_sql("""
        SELECT c.client_id AS user_id,
               ac.produit_id AS item_id,
               COUNT(*) AS rating
        FROM commandes c
        JOIN articles_commande ac ON ac.commande_id = c.id
        WHERE c.statut IN ('confirmee', 'livree')
        GROUP BY c.client_id, ac.produit_id
    """, conn)
    conn.close()
    return df

def entrainer_modele():
    df = charger_donnees()
    reader = Reader(rating_scale=(1, 10))
    data = Dataset.load_from_df(df[['user_id', 'item_id', 'rating']], reader)
    trainset = data.build_full_trainset()
    model = SVD(n_factors=50, n_epochs=20)
    model.fit(trainset)
    return model, list(df['item_id'].unique())

model, tous_les_produits = entrainer_modele()

@app.get("/recommandations/{client_id}")
def recommandations(client_id: int, limit: int = 6):
    cache_key = f"reco:ml:{client_id}"
    cached = cache.get(cache_key)
    if cached:
        return json.loads(cached)

    scores = [
        (produit_id, model.predict(client_id, produit_id).est)
        for produit_id in tous_les_produits
    ]
    scores.sort(key=lambda x: x[1], reverse=True)
    result = [{"produit_id": pid, "score": round(s, 3)} for pid, s in scores[:limit]]

    cache.setex(cache_key, 900, json.dumps(result))
    return result

@app.post("/retrain")
def retrain():
    global model, tous_les_produits
    model, tous_les_produits = entrainer_modele()
    return {"status": "ok"}
```

### 3.5 Appel depuis Laravel

```php
// Dans RecommandationService.php
public function pourClientML(int $clientId): array
{
    try {
        $response = Http::timeout(2)->get("http://recommandation-service:8000/recommandations/{$clientId}");
        if ($response->successful()) {
            $produitIds = collect($response->json())->pluck('produit_id');
            return Produit::whereIn('id', $produitIds)
                ->where('est_visible', true)
                ->get(['id', 'nom', 'slug', 'prix', 'prix_promo'])
                ->toArray();
        }
    } catch (\Exception $e) {
        // Fallback silencieux vers la recommandation SQL
        \Log::warning('ML service indisponible', ['error' => $e->getMessage()]);
    }
    return $this->pourClient($clientId); // fallback SQL
}
```

> Le fallback est important : si le service Python est down, Laravel continue à fonctionner.

---

## Phase 4 — Embeddings vectoriels avec pgvector (catalogue > 100 produits)

Approche moderne basée sur la similarité sémantique.

### 4.1 Principe

Chaque produit est représenté par un vecteur numérique (embedding) calculé à partir de sa description. Deux produits similaires = deux vecteurs proches dans l'espace mathématique.

```
"Robe midi bordeaux en soie" → [0.23, -0.87, 0.41, ...]  (1536 nombres)
"Robe courte rouge satinée"  → [0.21, -0.83, 0.38, ...]  (proche du précédent)
"Montre acier brossé"        → [-0.72, 0.11, -0.56, ...]  (très différent)
```

### 4.2 Installation pgvector

Sur le serveur PostgreSQL :
```sql
CREATE EXTENSION IF NOT EXISTS vector;
```

Migration Laravel :
```php
// Ajouter la colonne embedding à produits
DB::statement('ALTER TABLE produits ADD COLUMN embedding vector(1536)');
DB::statement('CREATE INDEX ON produits USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
```

### 4.3 Générer les embeddings (Python)

```python
import openai
import psycopg2

client = openai.OpenAI(api_key="sk-...")

def generer_embedding(texte: str) -> list:
    response = client.embeddings.create(
        input=texte,
        model="text-embedding-3-small"  # 1536 dimensions, peu coûteux
    )
    return response.data[0].embedding

def vectoriser_catalogue():
    conn = psycopg2.connect("postgresql://user:pass@db/ndeya")
    cur = conn.cursor()

    cur.execute("SELECT id, nom, description_courte, tags FROM produits WHERE embedding IS NULL AND est_visible = true")
    produits = cur.fetchall()

    for produit_id, nom, description, tags in produits:
        texte = f"{nom}. {description or ''}. {tags or ''}"
        embedding = generer_embedding(texte)
        cur.execute(
            "UPDATE produits SET embedding = %s WHERE id = %s",
            (embedding, produit_id)
        )
        conn.commit()
        print(f"Vectorisé: {nom}")

    conn.close()
```

Coût estimé : ~0.02$ pour 1000 produits avec `text-embedding-3-small`.

**Alternative gratuite :** modèle local `sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2`
(supporte le français, 384 dimensions, tourne sur CPU)

```python
from sentence_transformers import SentenceTransformer
model = SentenceTransformer('paraphrase-multilingual-MiniLM-L12-v2')
embedding = model.encode("Robe midi bordeaux en soie").tolist()
```

### 4.4 Requête "produits similaires" en PostgreSQL

```sql
SELECT id, nom, slug, prix,
       1 - (embedding <=> (SELECT embedding FROM produits WHERE id = :produit_id)) AS similarite
FROM produits
WHERE id != :produit_id
  AND est_visible = true
  AND deleted_at IS NULL
ORDER BY embedding <=> (SELECT embedding FROM produits WHERE id = :produit_id)
LIMIT 6;
```

### 4.5 Dans Laravel

```php
public function similaires(int $produitId, int $limit = 6): array
{
    return Cache::remember("reco:similaires:{$produitId}", 86400, function () use ($produitId, $limit) {
        return DB::select("
            SELECT id, nom, slug, prix, prix_promo,
                   1 - (embedding <=> (SELECT embedding FROM produits WHERE id = ?)) AS similarite
            FROM produits
            WHERE id != ?
              AND est_visible = true
              AND deleted_at IS NULL
              AND embedding IS NOT NULL
            ORDER BY embedding <=> (SELECT embedding FROM produits WHERE id = ?)
            LIMIT ?
        ", [$produitId, $produitId, $produitId, $limit]);
    });
}
```

---

## Résumé des phases

| Phase | Prérequis | Fiabilité | Effort |
|---|---|---|---|
| **1 — Tracking** | Rien | — | 2h |
| **2 — SQL** | 100+ commandes | 65% | 1 jour |
| **3 — Python/ML** | 500+ clients | 80% | 1 semaine |
| **4 — pgvector** | 100+ produits | 90% | 3 jours |

---

## Ordre recommandé

```
Maintenant
  └─ Ajouter table product_views + tracking dans ProductService

Dans 3 mois (si 100+ commandes)
  └─ Créer RecommandationService.php avec les 3 méthodes SQL
  └─ Afficher "Souvent achetés ensemble" sur ProductDetailPage

Dans 6 mois (si 500+ clients)
  └─ Monter le microservice Python sur Coolify (container séparé)
  └─ Ré-entraîner le modèle chaque nuit avec un cron

Dans 12 mois (si catalogue > 100 produits)
  └─ Installer pgvector
  └─ Vectoriser le catalogue (script Python, une seule fois)
  └─ Remplacer "même catégorie" par "produits similaires" sémantiques
```

---

## Notes importantes

- **Toujours prévoir un fallback SQL** si le service ML est down
- **Cache Redis obligatoire** sur les endpoints de reco (calcul coûteux)
- **RGPD** : le tracking de vues par utilisateur connecté = données personnelles. Mentionner dans la politique de confidentialité.
- **Ne pas sur-ingénier trop tôt** : Phase 2 SQL suffit jusqu'à 10 000 commandes/mois
- **Métriques à surveiller** : taux de clic sur les recos, taux de conversion reco → achat

---

*Document créé le 2026-05-24. Stack : Laravel 11, PostgreSQL 15, Redis, Coolify.*
