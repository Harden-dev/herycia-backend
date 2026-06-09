# Super Admin — Guide frontend

> Documentation d’implémentation du back-office plateforme Salono.  
> Base URL API : `{API_URL}/api/v1`

---

## Sommaire

1. [Sidebar — structure des pages](#1-sidebar--structure-des-pages)
2. [Authentification](#2-authentification)
3. [Conventions API](#3-conventions-api)
4. [Dashboard](#4-dashboard)
5. [Salons](#5-salons)
6. [Plans (catalogue offres)](#6-plans-catalogue-offres)
7. [Souscriptions](#7-souscriptions)
8. [Paiements SaaS (facturation)](#8-paiements-saas-facturation)
9. [Utilisateurs](#9-utilisateurs)
10. [Énumérations](#10-énumérations)
11. [Mapping pages → endpoints](#11-mapping-pages--endpoints)

---

## 1. Sidebar — structure des pages

```
┌─────────────────────────┐
│  SALONO Super Admin     │
├─────────────────────────┤
│ 📊 Dashboard            │  → KPIs + répartition villes + derniers paiements
│ 🏪 Salons               │  → Liste + détail + actions (modifier/suspendre…)
│ 📦 Plans                │  → Catalogue offres (Starter/Pro/Premium…)
│ 🔄 Souscriptions        │  → Instances salon ↔ plan (gestion commerciale)
│ 💰 Paiements            │  → Facturation SaaS (Wave, Orange Money…)
│ 👥 Utilisateurs         │  → Tous les users plateforme
└─────────────────────────┘
```

| Item sidebar | Route front suggérée | Rôle |
|--------------|---------------------|------|
| Dashboard | `/admin` ou `/admin/dashboard` | Vue globale plateforme |
| Salons | `/admin/salons` | Gestion des salons inscrits |
| Plans | `/admin/plans` | CRUD offres commerciales |
| Souscriptions | `/admin/subscriptions` | Suivi abonnements actifs/expirés |
| Paiements | `/admin/billing-payments` | Encaissements abonnements SaaS |
| Utilisateurs | `/admin/users` | Propriétaires, employés, super admins |

**Header / profil** : `GET /admin/me` (nom, rôle, déconnexion via `POST /auth/logout`).

> ⚠️ Ne pas confondre **Paiements admin** (`/admin/billing-payments`) avec les paiements salon RDV (`/api/v1/payments`) utilisés par les salons pour encaisser les clients.

---

## 2. Authentification

### Login (partagé)

```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "login": "2250700000000",
  "password": "SuperAdmin123!"
}
```

Réponse :

```json
{
  "success": true,
  "data": {
    "access_token": "eyJ...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "user": {
      "role": "super_admin"
    }
  }
}
```

### Garde front

1. Stocker `access_token`
2. Vérifier `user.role === "super_admin"` après login
3. Sinon → refuser l’accès au layout admin
4. Toutes les requêtes admin :

```http
Authorization: Bearer {access_token}
```

### Profil super admin

```http
GET /api/v1/admin/me
```

```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "name": "Super Admin",
    "phone": "2250700000000",
    "email": "superadmin@salono.ci",
    "role": "super_admin",
    "is_active": true,
    "created_at": "2026-06-05T12:00:00+00:00"
  }
}
```

### Erreurs auth admin

| Status | Cas |
|--------|-----|
| `401` | Token absent / expiré |
| `403` | Utilisateur connecté mais pas `super_admin` |

---

## 3. Conventions API

### Enveloppe standard

```json
{
  "success": true,
  "message": "Message lisible",
  "data": { }
}
```

### Listes paginées

```json
{
  "success": true,
  "message": "...",
  "data": [ ],
  "pagination": {
    "total_rows": 120,
    "per_page": 15,
    "current_page": 1,
    "last_page": 8
  }
}
```

Query commun : `?page=1&per_page=15`

### Erreurs

| Status | Format |
|--------|--------|
| `422` | `{ success, message, errors: { champ: ["..."] } }` |
| `400` | `{ success: false, message: "..." }` |
| `404` | `{ success: false, message: "..." }` |

---

## 4. Dashboard

Page unique avec **2 appels** au chargement.

### 4.1 Vue d’ensemble (KPIs)

```http
GET /api/v1/admin/stats/overview
```

**Cartes à afficher :**

| Champ API | Label UI |
|-----------|----------|
| `salons_count` | Nombre de salons |
| `users_count` | Nombre d'utilisateurs |
| `mrr` | Revenus mensuels (MRR) en FCFA |
| `new_salons_this_month` | Nouveaux salons ce mois |
| `expired_subscriptions_count` | Abonnements expirés |
| `appointments_count` | Rendez-vous générés (plateforme) |

**Exemple réponse :**

```json
{
  "success": true,
  "data": {
    "salons_count": 152,
    "users_count": 480,
    "mrr": 1250000,
    "new_salons_this_month": 18,
    "expired_subscriptions_count": 7,
    "appointments_count": 3420,
    "recent_payments": [
      {
        "id": "uuid",
        "amount": 50000,
        "method": "wave",
        "status": "paid",
        "reference": "WAVE-123",
        "paid_at": "2026-06-01T10:00:00+00:00",
        "salon": { "id": "uuid", "name": "Salon X", "city": "Abidjan" },
        "plan": { "id": "uuid", "name": "Pro", "code": "PRO" }
      }
    ]
  }
}
```

Bloc **« Derniers paiements »** sur le dashboard = `data.recent_payments` (5 derniers paiements SaaS payés).

### 4.2 Répartition par ville

```http
GET /api/v1/admin/stats/salons-by-city
```

```json
{
  "success": true,
  "data": {
    "Abidjan": 120,
    "Bouaké": 20,
    "Yamoussoukro": 12
  }
}
```

**UI** : graphique barres ou liste `Ville → Nombre`.

---

## 5. Salons

### 5.1 Liste

```http
GET /api/v1/admin/salons
```

**Query params :**

| Param | Type | Description |
|-------|------|-------------|
| `page` | int | Page (défaut 1) |
| `per_page` | int | Taille page (défaut 15) |
| `search` | string | Nom, slug ou WhatsApp |
| `city` | string | Ville exacte (`Abidjan`, `Bouaké`…) |
| `plan_id` | uuid | Filtrer salons ayant ce plan |
| `is_active` | bool | `true` / `false` |
| `is_suspended` | bool | `true` / `false` |

**Colonnes tableau :**

| Colonne | Champ |
|---------|-------|
| Nom | `name` |
| Ville | `city` |
| Plan | `plan_name` (`plan_code`) |
| Expiration | `subscription_ends_at` |
| Statut | dérivé de `is_active` + `is_suspended` |
| Date création | `created_at` |
| Actions | Voir / Modifier / Suspendre / Désactiver / Supprimer |

**Logique statut UI :**

```
is_suspended = true  → "Suspendu" (badge orange)
is_active = false    → "Désactivé" (badge rouge)
sinon                → "Actif" (badge vert)
```

**Item `data[]` :**

```json
{
  "id": "uuid",
  "name": "Salon Koffi",
  "slug": "salon-koffi-cocody",
  "city": "Abidjan",
  "phone": "2250708112233",
  "whatsapp_number": "2250708112233",
  "is_active": true,
  "is_suspended": false,
  "plan_name": "Gratuit",
  "plan_code": "FREE",
  "subscription_status": "trial",
  "subscription_ends_at": "2026-07-01",
  "created_at": "2026-05-01T08:00:00+00:00"
}
```

### 5.2 Détail

```http
GET /api/v1/admin/salons/{id}
```

Même shape qu’un item liste.

### 5.3 Modifier

```http
PUT /api/v1/admin/salons/{id}
Content-Type: application/json
```

Body (tous optionnels, au moins 1 champ) :

```json
{
  "name": "Salon Renommé",
  "phone": "0748754918",
  "whatsapp_number": "2250708112233",
  "city": "Abidjan",
  "address": "Cocody, Rue des jardins"
}
```

Villes autorisées : voir [§10 SalonCity](#10-énumérations).

### 5.4 Suspendre

```http
PATCH /api/v1/admin/salons/{id}/suspend
```

Met `suspended_at = now()`. Le salon reste en base mais est marqué suspendu.

### 5.5 Désactiver

```http
PATCH /api/v1/admin/salons/{id}/deactivate
```

Met `is_active = false`.

### 5.6 Supprimer

```http
DELETE /api/v1/admin/salons/{id}
```

Suppression définitive (cascade users liés). **Confirmation modale obligatoire.**

```json
{
  "success": true,
  "message": "Salon supprimé avec succès",
  "data": null
}
```

---

## 6. Plans (catalogue offres)

Catalogue des **offres** (Starter, Pro, Premium…). Distinct des souscriptions.

### 6.1 Liste

```http
GET /api/v1/admin/plans
```

| Query | Description |
|-------|-------------|
| `page`, `per_page` | Pagination |
| `include_archived` | `true` = archivés seulement, `false` = actifs seulement, absent = tous |

**Colonnes :**

| Colonne | Champ |
|---------|-------|
| Nom | `name` |
| Code | `code` |
| Prix | `price_fcfa` |
| Employés max | `max_employees` |
| Services max | `max_services` |
| Statut | `is_archived` |
| Actions | Voir / Modifier / Archiver |

### 6.2 Créer

```http
POST /api/v1/admin/plans
```

```json
{
  "name": "Starter",
  "code": "starter",
  "price_fcfa": 5000,
  "max_employees": 5,
  "max_services": 15,
  "has_online_booking": true,
  "has_analytics": false,
  "has_multi_branch": false
}
```

> Le `code` est **normalisé en MAJUSCULES** côté API (`starter` → `STARTER`).

Réponse : `201 Created`

### 6.3 Détail

```http
GET /api/v1/admin/plans/{id}
```

### 6.4 Modifier

```http
PUT /api/v1/admin/plans/{id}
```

Mêmes champs que création, tous optionnels.

### 6.5 Archiver

```http
PATCH /api/v1/admin/plans/{id}/archive
```

Met `is_archived = true`. Plan plus proposé aux nouvelles souscriptions.

---

## 7. Souscriptions

Instances **salon ↔ plan** (gestion commerciale).

### 7.1 Liste

```http
GET /api/v1/admin/subscriptions
```

| Query | Description |
|-------|-------------|
| `page`, `per_page` | Pagination |
| `search` | Nom ou slug salon |
| `plan_id` | UUID plan |
| `status` | `trial`, `active`, `past_due`, `cancelled`, `expired` |
| `salon_id` | UUID salon |

**Colonnes :**

| Colonne | Source |
|---------|--------|
| Salon | `salon.name` |
| Plan | `plan.name` |
| Début | `started_at` |
| Expiration | `ends_at` ou `trial_ends_at` si `is_trial` |
| Montant | `plan.price_fcfa` |
| Statut | `status` |

**Item :**

```json
{
  "id": "uuid",
  "salon_id": "uuid",
  "plan_id": "uuid",
  "status": "active",
  "started_at": "2026-05-01",
  "ends_at": "2026-06-01",
  "trial_ends_at": null,
  "is_trial": false,
  "created_at": "2026-05-01T08:00:00+00:00",
  "salon": {
    "id": "uuid",
    "name": "Salon X",
    "slug": "salon-x",
    "city": "Abidjan"
  },
  "plan": {
    "id": "uuid",
    "name": "Pro",
    "code": "PRO",
    "price_fcfa": 10000
  }
}
```

### 7.2 Détail

```http
GET /api/v1/admin/subscriptions/{id}
```

### 7.3 Modifier (prolonger, changer plan…)

```http
PUT /api/v1/admin/subscriptions/{id}
```

```json
{
  "plan_id": "uuid-nouveau-plan",
  "status": "active",
  "started_at": "2026-06-01",
  "ends_at": "2026-07-01",
  "trial_ends_at": null,
  "is_trial": false
}
```

Tous les champs sont optionnels.

### 7.4 Annuler

```http
PATCH /api/v1/admin/subscriptions/{id}/cancel
```

Met `status = cancelled`.

---

## 8. Paiements SaaS (facturation)

Paiements d’**abonnement plateforme** (Wave, Orange Money…).  
Table backend : `subscription_payments` (≠ `payments` RDV clients).

### 8.1 Liste

```http
GET /api/v1/admin/billing-payments
```

| Query | Description |
|-------|-------------|
| `page`, `per_page` | Pagination |
| `from` | Date début `YYYY-MM-DD` (filtre `paid_at`) |
| `to` | Date fin `YYYY-MM-DD` |
| `plan_id` | UUID plan |
| `status` | `pending`, `paid`, `failed`, `refunded` |
| `salon_id` | UUID salon |

**Colonnes :**

| Colonne | Champ |
|---------|-------|
| Salon | `salon.name` |
| Montant | `amount` (FCFA) |
| Méthode | `method` |
| Date | `paid_at` |
| Statut | `status` |
| Référence | `reference` |

**Exemple ligne :**

```
Salon X | Wave | 50 000 FCFA | 2026-06-01
Salon Y | Orange Money | 25 000 FCFA | 2026-06-02
```

```json
{
  "id": "uuid",
  "amount": 50000,
  "method": "wave",
  "status": "paid",
  "reference": "WAVE-ABC123",
  "period_start": "2026-06-01",
  "period_end": "2026-07-01",
  "paid_at": "2026-06-01T14:30:00+00:00",
  "created_at": "2026-06-01T14:30:00+00:00",
  "salon": { "id": "uuid", "name": "Salon X", "city": "Abidjan" },
  "plan": { "id": "uuid", "name": "Pro", "code": "PRO" },
  "subscription": { "id": "uuid", "status": "active" }
}
```

### 8.2 Détail

```http
GET /api/v1/admin/billing-payments/{id}
```

---

## 9. Utilisateurs

Tous les utilisateurs de la plateforme (propriétaires, employés, super admins).

### 9.1 Liste

```http
GET /api/v1/admin/users
```

| Query | Description |
|-------|-------------|
| `page`, `per_page` | Pagination |
| `search` | Nom, email, téléphone |
| `is_active` | `true` / `false` |
| `user_type` | `owner` \| `employee` \| `super_admin` |

**Filtres sidebar/tabs :**

| Tab UI | `user_type` |
|--------|-------------|
| Propriétaires | `owner` |
| Employés | `employee` |
| Super admins | `super_admin` |

**Colonnes :**

| Colonne | Champ |
|---------|-------|
| Nom | `name` |
| Téléphone | `phone` |
| Email | `email` |
| Rôle | `role` |
| Salon | `salon.name` (null si super admin) |
| Statut | `is_active` |
| Actions | Consulter / Bloquer / Reset MDP |

**Rôles `role` :**

| Valeur | Libellé |
|--------|---------|
| `super_admin` | Super admin |
| `admin` | Propriétaire salon |
| `manager` | Manager |
| `stylist` | Styliste |
| `receptionist` | Réceptionniste |

### 9.2 Détail

```http
GET /api/v1/admin/users/{id}
```

### 9.3 Bloquer / Débloquer (toggle)

```http
PATCH /api/v1/admin/users/{id}/block
```

Bascule `is_active`. Message dynamique :

- actif → bloqué : `"Utilisateur bloqué avec succès"`
- inactif → débloqué : `"Utilisateur débloqué avec succès"`

**Restrictions :**
- Impossible de bloquer son propre compte
- Impossible de bloquer un super admin (sauf par un super admin)

### 9.4 Réinitialiser mot de passe

```http
PATCH /api/v1/admin/users/{id}/reset-password
Content-Type: application/json
```

**Option A — mot de passe auto-généré :**

```json
{}
```

**Option B — mot de passe imposé :**

```json
{
  "new_password": "NouveauMdp123"
}
```

Réponse si auto-généré :

```json
{
  "success": true,
  "data": {
    "user": { "id": "...", "name": "...", "role": "admin" },
    "generated_password": "xK9mP2nQ4rTv"
  }
}
```

> Afficher `generated_password` **une seule fois** dans une modale (copier / communiquer au client).

---

## 10. Énumérations

### Rôles utilisateur (`role`)

`super_admin`, `admin`, `manager`, `stylist`, `receptionist`

### Type filtre users (`user_type`)

`owner`, `employee`, `super_admin`

### Statuts abonnement (`status`)

`trial`, `active`, `past_due`, `cancelled`, `expired`

### Méthodes paiement SaaS (`method`)

`wave`, `orange_money`, `mobile_money`, `card`, `cash`

### Statuts paiement SaaS (`status`)

`pending`, `paid`, `failed`, `refunded`

### Villes salon (`city`)

`Abidjan`, `Bouaké`, `Yamoussoukro`, `Daloa`, `San-Pédro`, `Korhogo`, `Man`, `Gagnoa`, `Abengourou`, `Divo`

---

## 11. Mapping pages → endpoints

| Page | Méthode | Endpoint |
|------|---------|----------|
| Login | POST | `/auth/login` |
| Layout init | GET | `/admin/me` |
| **Dashboard** | GET | `/admin/stats/overview` |
| Dashboard villes | GET | `/admin/stats/salons-by-city` |
| **Salons liste** | GET | `/admin/salons` |
| Salon détail | GET | `/admin/salons/{id}` |
| Salon modifier | PUT | `/admin/salons/{id}` |
| Salon suspendre | PATCH | `/admin/salons/{id}/suspend` |
| Salon désactiver | PATCH | `/admin/salons/{id}/deactivate` |
| Salon supprimer | DELETE | `/admin/salons/{id}` |
| **Plans liste** | GET | `/admin/plans` |
| Plan créer | POST | `/admin/plans` |
| Plan détail | GET | `/admin/plans/{id}` |
| Plan modifier | PUT | `/admin/plans/{id}` |
| Plan archiver | PATCH | `/admin/plans/{id}/archive` |
| **Souscriptions liste** | GET | `/admin/subscriptions` |
| Souscription détail | GET | `/admin/subscriptions/{id}` |
| Souscription modifier | PUT | `/admin/subscriptions/{id}` |
| Souscription annuler | PATCH | `/admin/subscriptions/{id}/cancel` |
| **Paiements liste** | GET | `/admin/billing-payments` |
| Paiement détail | GET | `/admin/billing-payments/{id}` |
| **Users liste** | GET | `/admin/users` |
| User détail | GET | `/admin/users/{id}` |
| User bloquer | PATCH | `/admin/users/{id}/block` |
| User reset MDP | PATCH | `/admin/users/{id}/reset-password` |
| Logout | POST | `/auth/logout` |

**Total : 24 endpoints admin** (+ login/logout partagés).

---

## Compte de test (dev)

Après `php artisan db:seed` :

| Champ | Valeur |
|-------|--------|
| login (phone) | `2250700000000` |
| password | `SuperAdmin123!` |
| role attendu | `super_admin` |

---

## Notes implémentation front

1. **Layout guard** : route `/admin/*` → vérifier token + `role === super_admin`
2. **Interceptor HTTP** : ajouter `Authorization: Bearer` sur tout `/admin/*`
3. **Pagination** : réutiliser un composant commun (`pagination.total_rows`, etc.)
4. **Toasts** : afficher `message` de l’API sur succès/erreur
5. **Erreurs 422** : mapper `errors` sous les champs formulaire
6. **Montants** : toujours en **FCFA** entiers (`amount`, `price_fcfa`, `mrr`)
7. **Dates** : `created_at` / `paid_at` en ISO8601 ; `started_at` / `ends_at` en `YYYY-MM-DD`
