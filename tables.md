# 📊 Modèle de Données - Salon Bichette Thomas

---

# 🧠 PRINCIPES

- Réservation possible SANS compte
- `clients` ≠ `users`
- Workflow simple pour gérante (3 actions)
- Fidélité automatique configurable
- Paiement intégré dans le flow

---

# 🔐 TABLES LARAVEL (NE PAS MODIFIER)

users
password_reset_tokens
sessions
cache
cache_locks
jobs
job_batches
failed_jobs
personal_access_tokens
migrations

---

# 👥 ROLES

roles
- id
- nom (admin, gerante)
- description
- created_at
- updated_at

Relation:
roles 1 ─── * users

---

# 👤 CLIENTS

clients
- id
- user_id (nullable)
- nom
- prenom
- telephone (unique)
- email (nullable)
- source (en_ligne, physique)
- nombre_reservations_terminees (default 0)
- fidelite_disponible (boolean)
- est_blackliste
- created_at
- updated_at

Relation:
clients 1 ─── * reservations

---

# 💇‍♀️ COIFFEUSES (PASSIF)

coiffeuses
- id
- nom
- prenom
- telephone
- pourcentage_commission
- actif
- created_at
- updated_at

---

# 💇‍♀️ COIFFURES

categories_coiffures
- id
- nom
- description
- created_at
- updated_at

coiffures
- id
- categorie_coiffure_id
- nom
- description
- image
- actif
- created_at
- updated_at

Relation:
categories_coiffures 1 ─── * coiffures

---

variantes_coiffures
- id
- coiffure_id
- nom (court, long…)
- prix
- duree_minutes
- created_at
- updated_at

---

options_coiffures
- id
- nom
- prix
- created_at
- updated_at

---

coiffure_option
- id
- coiffure_id
- option_coiffure_id

Relation:
coiffures * ─── * options_coiffures

---

# 📅 RESERVATIONS

reservations
- id
- client_id
- coiffeuse_id (nullable)
- date_reservation
- heure_debut
- heure_fin

# gestion réel
- heure_arrivee (nullable)
- minutes_retard (default 0)

# statut simplifié
- statut

# paiement
- montant_total
- montant_acompte
- montant_restant

# type
- type (en_ligne, physique, domicile)

- created_at
- updated_at

---

# STATUT RESERVATION

en_attente
confirmee
acompte_paye
client_arrive
en_cours
terminee
absent
annulee

---

details_reservations
- id
- reservation_id
- coiffure_id
- variante_id
- prix
- created_at
- updated_at

---

options_reservations
- id
- detail_reservation_id
- option_id
- prix
- created_at
- updated_at

---

# 💰 PAIEMENTS

paiements
- id
- reservation_id
- type (acompte, reste, complet)
- methode (cash, wave, orange_money)
- montant
- statut
- created_at
- updated_at

---

recus
- id
- paiement_id
- numero_recu
- montant_total
- montant_paye
- montant_restant
- envoye_whatsapp
- created_at
- updated_at

---

# 💼 CAISSE

caisses
- id
- date
- solde_ouverture
- solde_fermeture
- created_at
- updated_at

---

mouvements_caisses
- id
- caisse_id
- type (entree, sortie)
- montant
- description
- created_at
- updated_at

---

# 💸 DEPENSES

depenses
- id
- titre
- montant
- date_depense
- created_at
- updated_at

---

# 📊 COMMISSIONS

commissions_coiffeuses
- id
- coiffeuse_id
- reservation_id
- montant_commission
- statut
- created_at
- updated_at

---

# 🎁 FIDELITE (CONFIG ADMIN)

regles_fidelite
- id
- nom
- nombre_reservations_requis (ex: 9)
- type_recompense (pourcentage, montant)
- valeur_recompense
- actif
- created_at
- updated_at

---

points_fidelite
- id
- client_id
- reservation_id
- points
- type (gain, utilisation)
- created_at
- updated_at

---

# 🎟 PROMOTIONS

codes_promo
- id
- code
- type_reduction
- valeur
- actif
- created_at
- updated_at

---

utilisations_codes_promo
- id
- code_promo_id
- client_id
- reservation_id
- created_at
- updated_at

---

# 📩 WHATSAPP

messages_whatsapp
- id
- client_id
- reservation_id
- type_message
- statut
- created_at
- updated_at

---

# 📸 PHOTOS

photos_prestations
- id
- reservation_id
- type (avant, apres)
- url
- created_at
- updated_at

---

# 🚫 BLACKLIST

liste_noire_clients
- id
- client_id
- raison
- actif
- created_at
- updated_at

---

# 📈 ANALYTICS

evenements_analytics
- id
- type
- valeur
- created_at

---

# ⚙️ PARAMETRES

parametres_systeme
- id
- cle
- valeur
- created_at
- updated_at