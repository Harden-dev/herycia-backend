# Salono — API Backoffice (Vue.js)

Documentation d'intégration frontend pour le **tableau de bord salon** (gérant, manager, coiffeur, réceptionniste).

> **Scope** : routes `/api/v1/*` authentifiées JWT.  
> Les pages publiques client (`/booking/{slug}`, `/rdv/{token}`) consomment `/api/booking/*` et `/api/rdv/*` — hors scope backoffice.

---

## Configuration

| Variable | Exemple | Usage |
|----------|---------|-------|
| `VITE_API_URL` | `https://api.salono.ci` | Base URL API |
| `FRONTEND_URL` | `https://salono.ci` | Liens publics (`booking_link`) |

**Prefix backoffice** : `{VITE_API_URL}/api/v1`

---

## Conventions globales

### En-tête d'authentification

```http
Authorization: Bearer {access_token}
Content-Type: application/json
Accept: application/json
```

Exception : upload logo → `multipart/form-data`.

### Enveloppe de réponse

Succès :

```json
{
  "success": true,
  "message": "Message lisible",
  "data": { ... }
}
```

Liste paginée :

```json
{
  "success": true,
  "message": "...",
  "data": [ ... ],
  "pagination": {
    "total_rows": 42,
    "per_page": 15,
    "current_page": 1,
    "last_page": 3
  }
}
```

Erreur validation (422) :

```json
{
  "success": false,
  "message": "Les données fournies sont invalides.",
  "errors": {
    "phone": ["Ce numéro de téléphone est déjà utilisé."]
  }
}
```

Erreur métier / auth :

```json
{
  "success": false,
  "message": "Identifiants invalides"
}
```

Erreur abonnement (403) :

```json
{
  "success": false,
  "message": "Votre abonnement a expiré...",
  "code": "subscription_expired"
}
```

Codes `code` possibles : `subscription_missing`, `subscription_expired`, `subscription_feature_denied`.

### Scope salon (important)

**Aucun `salon_id` à envoyer.** Le salon est toujours déduit du JWT (`user.salon_id`). Toutes les listes / CRUD sont automatiquement filtrés sur le salon connecté.

### Téléphones (Côte d'Ivoire)

Formats acceptés côté front : `0748754918`, `+2250748754918`, `2250748754918`.  
Stockage API : `2250748754918` (13 chiffres).

### Dates

- Query `date`, `from`, `to` : `YYYY-MM-DD`
- Datetimes (`scheduled_at`, etc.) : ISO 8601 (`2026-06-03T10:00:00+00:00`)

### UUID

Tous les IDs sont des UUID v4.

---

## Rôles & permissions

| Rôle | Valeur | Accès |
|------|--------|-------|
| Admin (gérant) | `admin` | Tout le backoffice |
| Manager | `manager` | RDV, clients, services (lecture), pas gestion employés/salon |
| Coiffeur | `stylist` | Idem manager |
| Réceptionniste | `receptionist` | Idem + **paiements** (liste + création) |

Middlewares :
- `jwt.auth` — token requis
- `subscription.active` — abonnement trial/active requis (routes métier)
- `role.admin` — admin uniquement
- `role.salon:admin,receptionist` — paiements

---

## Enums

### `SalonStaffRole`
`admin` | `manager` | `stylist` | `receptionist`

> À la création d'employé, seuls `manager`, `stylist`, `receptionist` sont assignables (pas `admin`).

### `AppointmentStatus`
`pending` | `confirmed` | `in_progress` | `completed` | `cancelled` | `no_show`

- Création backoffice → statut initial `pending`
- Réservation publique → `confirmed`
- RDV annulé (`DELETE`) → `cancelled` (soft, pas de suppression)

### `PaymentMethod`
`cash` | `mobile_money` | `card`

### `PaymentStatus`
`pending` | `paid` | `failed` | `refunded`

### `SubscriptionStatus`
`trial` | `active` | `past_due` | `cancelled` | `expired`

### `SalonCity` (inscription / mise à jour salon)
`Abidjan` | `Bouaké` | `Yamoussoukro` | `Daloa` | `San-Pédro` | `Korhogo` | `Man` | `Gagnoa` | `Abengourou` | `Divo`

---

# 1. Authentification

## POST `/auth/register` — Inscription salon

**Auth** : non  
**Throttle** : 5 req / 60 min

### Body

| Champ | Type | Requis | Règles |
|-------|------|--------|--------|
| `salon_name` | string | oui | 2–100 car. |
| `city` | string | oui | enum `SalonCity` |
| `whatsapp_number` | string | oui | tel CI, unique |
| `admin_name` | string | oui | 2–100 car. |
| `phone` | string | oui | tel CI, unique (login gérant) |
| `password` | string | oui | min 8, `confirmed` |
| `password_confirmation` | string | oui | |

```json
{
  "salon_name": "Salon Koffi Cocody",
  "city": "Abidjan",
  "whatsapp_number": "2250708112233",
  "admin_name": "Koffi Traoré",
  "phone": "2250708112233",
  "password": "monmotdepasse",
  "password_confirmation": "monmotdepasse"
}
```

### Réponse 201 — `data`

| Champ | Description |
|-------|-------------|
| `access_token` | JWT |
| `token_type` | `"Bearer"` |
| `expires_in` | secondes |
| `expires_at` | ISO 8601 |
| `salon` | voir objet Salon |
| `user` | `{ id, name, phone, role, is_active }` |
| `subscription` | voir objet Subscription |

**Action front** : stocker `access_token`, rediriger dashboard, afficher `salon.booking_link`.

---

## POST `/auth/login` — Connexion

### Body

| Champ | Type | Requis |
|-------|------|--------|
| `login` | string | oui — email ou téléphone |
| `password` | string | oui — min 8 |

```json
{
  "login": "0748754918",
  "password": "monmotdepasse"
}
```

### Réponse 200 — `data`

| Champ | Description |
|-------|-------------|
| `user.id` | UUID |
| `user.name` | string |
| `user.email` | string \| null |
| `user.phone` | string |
| `user.role` | `SalonStaffRole` |
| `user.is_active` | boolean |
| `user.salon` | `{ id, name, slug, booking_link }` \| null |
| `user.subscription` | Subscription \| null |
| `access_token` | JWT |
| `token_type` | `"Bearer"` |
| `expires_in` | number |
| `expires_at` | ISO 8601 |

---

## GET `/auth/me` — Profil connecté

**Auth** : JWT (sans middleware subscription)

### Réponse 200 — `data`

```json
{
  "user": {
    "id": "uuid",
    "name": "Koffi",
    "email": null,
    "phone": "2250708112233",
    "role": "admin",
    "is_active": true,
    "created_at": "2026-06-03T10:00:00+00:00"
  },
  "salon": { /* SalonResource */ },
  "subscription": { /* SubscriptionResource */ }
}
```

---

## POST `/auth/logout`

**Auth** : JWT

Réponse 200 : `{ "success": true, "message": "Déconnexion réussie" }`

---

## Mot de passe oublié

| Méthode | Route | Body |
|---------|-------|------|
| POST | `/auth/forgot-password` | `{ "email": "gerant@example.com" }` |
| POST | `/auth/verify-reset-code` | `{ "email", "code" }` |
| POST | `/auth/reset-password` | `{ "email", "token", "password", "password_confirmation" }` |

> Nécessite un email sur le compte user.

---

## POST `/auth/refresh` — Rafraîchir la session

En-tête `Authorization: Bearer <token>` (même expiré, dans la fenêtre de rafraîchissement de 14 jours).

Réponse 200 — `data` : `{ "access_token", "token_type": "Bearer", "expires_in" }`. Remplacer le jeton stocké.
Réponse 401 : compte bloqué, mot de passe changé ou salon suspendu → déconnecter.

> **Mot de passe oublié** : la réponse est toujours `200` avec le même message, que l'email existe ou non. Afficher « Si cet email existe, un code a été envoyé ».

## OTP — retiré

`/auth/verify-otp` et `/auth/resend-otp` ont été supprimés (audit de sécurité C2) : l'inscription crée des comptes actifs.

---

# 2. Salon (admin)

## GET `/salon` — Détail salon

**Rôle** : `admin`

### Réponse `data`

| Champ | Type | Colonne UI suggérée |
|-------|------|---------------------|
| `id` | uuid | — |
| `name` | string | Nom |
| `slug` | string | Slug (interne) |
| `phone` | string \| null | Téléphone salon |
| `whatsapp_number` | string | WhatsApp |
| `city` | string | Ville |
| `address` | string \| null | Adresse |
| `logo_url` | string \| null | Logo |
| `is_active` | boolean | Actif |
| `booking_link` | string | **Lien à partager** (`https://salono.ci/booking/{slug}`) |
| `created_at` | ISO 8601 | Créé le |
| `subscription` | object | Plan actuel |

---

## PUT `/salon` — Modifier salon

**Rôle** : `admin`

### Body (tous optionnels)

| Champ | Type | Règles |
|-------|------|--------|
| `name` | string | 2–100 |
| `phone` | string \| null | tel CI |
| `whatsapp_number` | string | tel CI, unique |
| `city` | string | enum SalonCity |
| `address` | string \| null | max 255 |

---

## POST `/salon/logo` — Upload logo

**Rôle** : `admin`  
**Content-Type** : `multipart/form-data`

| Champ | Type | Règles |
|-------|------|--------|
| `logo` | file | jpeg/png/jpg/webp, max 2 Mo |

Réponse : même structure que GET `/salon`.

---

# 3. Employés (admin)

## GET `/users` — Liste

**Rôle** : `admin`

### Query

| Param | Type | Défaut | Description |
|-------|------|--------|-------------|
| `page` | int | 1 | |
| `per_page` | int | 15 | max 100 |
| `search` | string | — | nom / téléphone |
| `is_active` | bool | — | filtre actifs/inactifs |

### Colonnes tableau

| Colonne | Champ API |
|---------|-----------|
| Nom | `name` |
| Téléphone | `phone` |
| Email | `email` |
| Rôle | `role` |
| Actif | `is_active` |
| Créé le | `created_at` |
| Actions | — |

### Objet item `data[]`

```json
{
  "id": "uuid",
  "name": "Aya",
  "phone": "2250708223344",
  "email": null,
  "role": "stylist",
  "is_active": true,
  "created_at": "2026-06-03T10:00:00+00:00"
}
```

---

## POST `/users` — Créer employé

### Body

| Champ | Type | Requis |
|-------|------|--------|
| `name` | string | oui |
| `phone` | string | oui, unique |
| `email` | string | non, unique si fourni |
| `password` | string | oui, min 8, confirmed |
| `password_confirmation` | string | oui |
| `role` | string | oui — `manager` \| `stylist` \| `receptionist` |

---

## GET `/users/{id}` — Détail

Même objet que liste.

---

## PUT `/users/{id}` — Modifier

Body partiel : `name`, `phone`, `email`, `password` + `password_confirmation`, `role`.

---

## DELETE `/users/{id}` — Désactiver

Soft delete → `is_active: false`. Impossible de désactiver un `admin`.

---

# 4. Prestations / Services

## GET `/services` — Liste

**Rôle** : tous (subscription active)

### Query

`page`, `per_page`, `search`, `is_active`

### Colonnes tableau

| Colonne | Champ |
|---------|-------|
| Nom | `name` |
| Durée | `duration_min` (minutes) |
| Prix | `price` (FCFA, entier) |
| Actif | `is_active` |

```json
{
  "id": "uuid",
  "name": "Coupe homme",
  "duration_min": 30,
  "price": 2000,
  "is_active": true
}
```

---

## POST `/services` — Créer

**Rôle** : `admin`

| Champ | Type | Règles |
|-------|------|--------|
| `name` | string | 2–100 |
| `duration_min` | int | 5–480 |
| `price` | int | ≥ 0 (FCFA) |

---

## PUT `/services/{id}` — Modifier

**Rôle** : `admin` — mêmes champs, tous optionnels.

---

## DELETE `/services/{id}` — Désactiver

**Rôle** : `admin` — met `is_active: false` (pas de suppression physique).

---

# 5. Clients

## GET `/clients` — Liste

### Query

`page`, `per_page`, `search` (nom / téléphone)

### Colonnes tableau

| Colonne | Champ |
|---------|-------|
| Nom | `name` |
| Téléphone | `phone` |
| WhatsApp | `whatsapp_id` |
| Visites | `total_visits` |
| Dernière visite | `last_visit_at` |

```json
{
  "id": "uuid",
  "name": "Client Koffi",
  "phone": "2250708445566",
  "whatsapp_id": "2250708445566",
  "total_visits": 3,
  "last_visit_at": "2026-05-20T14:00:00+00:00"
}
```

---

## POST `/clients` — Créer

| Champ | Type | Requis |
|-------|------|--------|
| `name` | string | oui |
| `phone` | string | oui, unique **par salon** |
| `whatsapp_id` | string | non |

---

## GET `/clients/{id}` — Fiche client

`data` = client + historique :

```json
{
  "id": "uuid",
  "name": "...",
  "phone": "...",
  "whatsapp_id": "...",
  "total_visits": 3,
  "last_visit_at": "...",
  "appointments": [
    {
      "id": "uuid",
      "scheduled_at": "...",
      "status": "completed",
      "notes": null,
      "service": { "id", "name" },
      "staff": { "id", "name" },
      "created_at": "..."
    }
  ],
  "payments": [
    {
      "id": "uuid",
      "appointment_id": "uuid",
      "amount": 2000,
      "method": "cash",
      "status": "paid",
      "mobile_money_ref": null,
      "paid_at": "..."
    }
  ]
}
```

---

## PUT `/clients/{id}` — Modifier

Body partiel : `name`, `phone`, `whatsapp_id`.

---

# 6. Rendez-vous

## GET `/appointments` — Agenda

### Query

| Param | Type | Description |
|-------|------|-------------|
| `date` | `YYYY-MM-DD` | Jour unique (défaut : aujourd'hui) |
| `from` + `to` | `YYYY-MM-DD` | Plage (prioritaire sur `date`) |
| `user_id` | uuid | Filtrer par coiffeur |

> `from`/`to` doivent être envoyés ensemble.

### Colonnes tableau / calendrier

| Colonne | Champ |
|---------|-------|
| Date/heure | `scheduled_at` |
| Client | `client.name` / `client.phone` |
| Coiffeur | `staff.name` |
| Prestation | `service.name` |
| Durée | `service.duration_min` |
| Prix | `service.price` |
| Statut | `status` |
| Notes | `notes` |

```json
{
  "id": "uuid",
  "scheduled_at": "2026-06-03T10:00:00+00:00",
  "status": "pending",
  "notes": null,
  "created_at": "2026-06-02T08:00:00+00:00",
  "client": { "id": "uuid", "name": "...", "phone": "..." },
  "staff": { "id": "uuid", "name": "Koffi" },
  "service": { "id": "uuid", "name": "Coupe homme", "duration_min": 30, "price": 2000 }
}
```

---

## POST `/appointments` — Créer RDV

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `client_id` | uuid | oui | Client du salon |
| `user_id` | uuid | oui | Coiffeur (`stylist`) |
| `service_id` | uuid | oui | Prestation active |
| `scheduled_at` | datetime | oui | Date/heure future |
| `notes` | string | non | max 1000 |

```json
{
  "client_id": "uuid-client",
  "user_id": "uuid-stylist",
  "service_id": "uuid-service",
  "scheduled_at": "2026-06-10T14:30:00+00:00",
  "notes": "Première visite"
}
```

- Statut initial : **`pending`**
- Un SMS avec lien de suivi est envoyé au client (`/rdv/{tracking_token}`) si `SMS_NOTIFICATIONS_ENABLED=true`

**Selects front** : charger clients (`GET /clients`), coiffeurs (`GET /users?is_active=1` filtrer `role=stylist`), services (`GET /services?is_active=1`).

---

## GET `/appointments/{id}` — Détail

Comme liste + `payments[]` + `client.whatsapp_id` + `staff.role`.

---

## PUT `/appointments/{id}/status` — Changer statut

```json
{ "status": "confirmed" }
```

Valeurs : enum `AppointmentStatus`.

Règles :
- Impossible si déjà `cancelled`
- Passage à `completed` → incrémente `client.total_visits` + met à jour `last_visit_at`

---

## DELETE `/appointments/{id}` — Annuler

Pas de body. Passe le statut à `cancelled`.  
Erreur si déjà `cancelled` ou `completed`.

---

# 7. Paiements

## GET `/payments` — Historique

**Rôle** : `admin` ou `receptionist`

### Query

| Param | Type |
|-------|------|
| `page`, `per_page` | pagination |
| `date` | jour unique |
| `from` + `to` | plage |
| `method` | `cash` \| `mobile_money` \| `card` |
| `status` | enum PaymentStatus |

### Colonnes tableau

| Colonne | Champ |
|---------|-------|
| Montant | `amount` (FCFA) |
| Méthode | `method` |
| Statut | `status` |
| Client | `client.name` |
| RDV | `appointment.scheduled_at` |
| Prestation | `appointment.service.name` |
| Réf. MM | `mobile_money_ref` |
| Payé le | `paid_at` |

---

## POST `/payments` — Enregistrer paiement

| Champ | Type | Requis |
|-------|------|--------|
| `appointment_id` | uuid | oui |
| `amount` | int | oui, ≥ 1 (FCFA) |
| `method` | string | oui |
| `mobile_money_ref` | string | requis si `method=mobile_money` |
| `paid_at` | datetime | non (défaut: now) |

---

## GET `/payments/summary` — Résumé caisse

**Rôle** : `admin`

### Réponse `data`

```json
{
  "day":   { "from": "2026-06-03", "to": "2026-06-03", "total": 15000, "count": 5 },
  "week":  { "from": "2026-06-02", "to": "2026-06-08", "total": 42000, "count": 12 },
  "month": { "from": "2026-06-01", "to": "2026-06-30", "total": 120000, "count": 45 }
}
```

Parfait pour widgets dashboard (jour / semaine / mois).

---

# 8. Objets réutilisables

## Salon (compact)

```json
{
  "id": "uuid",
  "name": "Salon Koffi",
  "slug": "salon-koffi-cocody",
  "whatsapp_number": "2250708112233",
  "city": "Abidjan",
  "is_active": true,
  "booking_link": "https://salono.ci/booking/salon-koffi-cocody",
  "created_at": "..."
}
```

## Subscription

```json
{
  "id": "uuid",
  "status": "trial",
  "is_trial": true,
  "plan": {
    "code": "free",
    "name": "Gratuit",
    "price_fcfa": 0
  },
  "trial_ends_at": "2026-07-01",
  "ends_at": null
}
```

---

# 9. Flux Vue.js recommandés

## Bootstrap app

```
1. login/register → stocker access_token (Pinia + localStorage)
2. GET /auth/me → hydrater user, salon, subscription
3. Si subscription.status ∉ [trial, active] → écran renouvellement
4. Router guards par role (admin vs receptionist vs stylist)
```

## Axios interceptor

```js
api.interceptors.request.use((config) => {
  const token = authStore.token
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use(
  (r) => r,
  async (err) => {
    const status = err.response?.status
    const error = err.response?.data?.error

    // Jeton expiré : une tentative de rafraîchissement, puis rejouer la requête
    if (status === 401 && error === 'token_expired' && !err.config._retried) {
      err.config._retried = true
      const ok = await authStore.refresh() // POST /auth/refresh
      if (ok) return api(err.config)
    }

    // account_disabled | token_revoked | token_invalid | … → déconnexion
    if (status === 401) authStore.logout()

    // Salon suspendu ou désactivé par Salono
    if (status === 403 && ['salon_suspended', 'salon_inactive'].includes(error)) {
      authStore.logout({ reason: err.response.data.message })
    }

    if (err.response?.data?.code?.startsWith('subscription_')) {
      router.push('/abonnement')
    }

    // Limitation de débit : afficher le message, réessayer après l'en-tête Retry-After
    if (status === 429) toast.warning(err.response.data.message)

    return Promise.reject(err)
  }
)
```

## Codes d'erreur d'authentification (`error`)

| HTTP | `error` | Action front |
|------|---------|--------------|
| 401 | `token_expired` | `POST /auth/refresh` puis rejouer la requête |
| 401 | `token_invalid`, `token_error`, `unauthorized` | Déconnexion |
| 401 | `account_disabled` | Déconnexion + message « compte désactivé » |
| 401 | `token_revoked` | Déconnexion (mot de passe changé) |
| 403 | `salon_suspended`, `salon_inactive` | Déconnexion + message de `message` |
| 429 | `too_many_requests` | Message, réessayer plus tard (`Retry-After`) |

> Les erreurs serveur inattendues renvoient désormais « Une erreur interne est survenue. Veuillez réessayer. » au lieu du détail technique.

## Pages → endpoints

| Page Vue | Endpoints |
|----------|-----------|
| Login | `POST /auth/login` |
| Inscription | `POST /auth/register` |
| Dashboard | `GET /payments/summary`, `GET /appointments?date=today` |
| Agenda | `GET /appointments?date=` ou `from/to`, `user_id` |
| Nouveau RDV | `POST /appointments` + selects clients/users/services |
| Clients | `GET /clients`, `POST /clients`, `GET /clients/{id}` |
| Prestations | `GET /services`, CRUD admin |
| Équipe | `GET /users`, CRUD admin |
| Caisse | `GET /payments`, `POST /payments` |
| Paramètres salon | `GET /salon`, `PUT /salon`, `POST /salon/logo` |

---

# 10. Récap endpoints

| Méthode | Route | Auth | Rôle |
|---------|-------|------|------|
| POST | `/auth/register` | — | — |
| POST | `/auth/login` | — | — |
| POST | `/auth/refresh` | JWT (même expiré) | tous |
| GET | `/auth/me` | JWT | tous |
| POST | `/auth/logout` | JWT | tous |
| POST | `/auth/forgot-password` | — | — |
| POST | `/auth/verify-reset-code` | — | — |
| POST | `/auth/reset-password` | — | — |
| GET | `/salon` | JWT+sub | admin |
| PUT | `/salon` | JWT+sub | admin |
| POST | `/salon/logo` | JWT+sub | admin |
| GET | `/users` | JWT+sub | admin |
| POST | `/users` | JWT+sub | admin |
| GET | `/users/{id}` | JWT+sub | admin |
| PUT | `/users/{id}` | JWT+sub | admin |
| DELETE | `/users/{id}` | JWT+sub | admin |
| GET | `/services` | JWT+sub | tous |
| POST | `/services` | JWT+sub | admin |
| PUT | `/services/{id}` | JWT+sub | admin |
| DELETE | `/services/{id}` | JWT+sub | admin |
| GET | `/clients` | JWT+sub | tous |
| POST | `/clients` | JWT+sub | tous |
| GET | `/clients/{id}` | JWT+sub | tous |
| PUT | `/clients/{id}` | JWT+sub | tous |
| GET | `/appointments` | JWT+sub | tous |
| POST | `/appointments` | JWT+sub | tous |
| GET | `/appointments/{id}` | JWT+sub | tous |
| PUT | `/appointments/{id}/status` | JWT+sub | tous |
| DELETE | `/appointments/{id}` | JWT+sub | tous |
| GET | `/payments` | JWT+sub | admin, receptionist |
| POST | `/payments` | JWT+sub | admin, receptionist |
| GET | `/payments/summary` | JWT+sub | admin |

`JWT+sub` = JWT + abonnement actif.

---

# 11. Pages publiques (référence rapide)

Pour le site client (autre app Vue ou routes publiques) :

| Route front | API |
|-------------|-----|
| `/booking/{slug}` | `GET /api/booking/{slug}` — infos salon + services + coiffeurs |
| `/booking/{slug}` (form) | `POST /api/booking/{slug}` — création RDV client |
| `/rdv/{token}` | `GET /api/rdv/{tracking_token}` — suivi statut RDV |

Pas de JWT. Lien booking = `salon.booking_link` retourné par l'API backoffice.

---

*Généré depuis le backend Salono — aligné sur `routes/api/v1.php` et les FormRequest/Resources Laravel.*
