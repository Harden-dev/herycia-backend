# Plans & Abonnements — Guide Frontend

> Document de référence pour intégrer la page pricing, l'inscription avec plan choisi, et la simulation de paiement (sans agrégateur Wave/OM pour l'instant).

---

## Sommaire

1. [Vue d'ensemble](#1-vue-densemble)
2. [Plans disponibles](#2-plans-disponibles)
3. [Page pricing — charger les offres](#3-page-pricing--charger-les-offres)
4. [Clic sur un plan → inscription](#4-clic-sur-un-plan--inscription)
5. [Inscription avec plan](#5-inscription-avec-plan)
6. [Après inscription — état trial](#6-après-inscription--état-trial)
7. [Simulation paiement (front + back)](#7-simulation-paiement-front--back)
8. [Consulter l'abonnement courant](#8-consulter-labonnement-courant)
9. [Features par plan & garde-fous UI](#9-features-par-plan--garde-fous-ui)
10. [Endpoints dashboard Pro](#10-endpoints-dashboard-pro)
11. [Codes erreur à gérer](#11-codes-erreur-à-gérer)
12. [Checklist intégration](#12-checklist-intégration)

---

## 1. Vue d'ensemble

```
┌─────────────────┐     GET /plans      ┌──────────────┐
│  Page Pricing   │ ──────────────────► │   Backend    │
└────────┬────────┘                     └──────────────┘
         │ clic "Basic"
         ▼
┌─────────────────┐   stocke plan_code   ┌──────────────┐
│ Page Inscription│ ◄── "basic" ──────── │ localStorage │
└────────┬────────┘   ou ?plan=basic     │ / state Vue  │
         │ POST /auth/register          └──────────────┘
         │   + plan_code
         ▼
┌─────────────────┐   subscription       ┌──────────────┐
│   Dashboard     │ ◄── trial + plan ── │   Backend    │
└────────┬────────┘                     └──────────────┘
         │ (optionnel) écran paiement simulé
         │ POST /subscription/simulate-payment
         ▼
┌─────────────────┐   subscription       ┌──────────────┐
│   Dashboard     │ ◄── active ──────── │   Backend    │
└─────────────────┘                     └──────────────┘
```

**Lien salon ↔ plan** : une ligne dans `subscriptions` (`salon_id` + `plan_id`). Le front n'envoie jamais `salon_id` pour choisir le plan — c'est le token JWT qui identifie le salon après inscription.

---

## 2. Plans disponibles

| Code | Nom | Prix | Employés | Analytics |
|------|-----|------|----------|-----------|
| `basic` | Basic | 5 000 F /mois | 5 max (admin inclus) | Non |
| `pro` | Pro | 15 000 F /mois | Illimité (`null`) | Oui |

**Codes acceptés à l'inscription** : uniquement `basic` et `pro`.

Le plan `free` n'existe plus dans le catalogue public.

---

## 3. Page pricing — charger les offres

### Endpoint

```
GET /api/v1/plans
Authorization: aucune (public)
```

### Réponse 200

```json
{
  "success": true,
  "message": "Plans récupérés avec succès.",
  "data": [
    {
      "id": "uuid-basic",
      "code": "basic",
      "name": "Basic",
      "tagline": "Petit salon en croissance",
      "price_fcfa": 5000,
      "price_label": "5 000 F /mois",
      "max_employees": 5,
      "max_employees_label": "5 membres du personnel",
      "max_services": 50,
      "has_online_booking": true,
      "has_analytics": false,
      "features": [
        { "key": "single_salon", "label": "1 salon", "included": true },
        { "key": "staff", "label": "5 membres du personnel", "included": true },
        { "key": "agenda", "label": "Agenda + rendez-vous", "included": true },
        { "key": "clients", "label": "Gestion des clients", "included": true },
        { "key": "public_booking", "label": "Formulaire public de RDV", "included": true },
        { "key": "live_tracking", "label": "Suivi RDV client en temps réel", "included": true },
        { "key": "payments", "label": "Paiements & caisse", "included": true },
        { "key": "revenue_tracking", "label": "Suivi des recettes", "included": false },
        { "key": "analytics", "label": "Analytiques avancées", "included": false },
        { "key": "activity_history", "label": "Historique activité complet", "included": false }
      ]
    },
    {
      "id": "uuid-pro",
      "code": "pro",
      "name": "Pro",
      "tagline": "Salon structuré ou multi-équipe",
      "price_fcfa": 15000,
      "price_label": "15 000 F /mois",
      "max_employees": null,
      "max_employees_label": "Membres du personnel illimités",
      "has_analytics": true,
      "features": [
        { "key": "all_basic", "label": "Tout Basic inclus", "included": true },
        { "key": "staff", "label": "Membres du personnel illimités", "included": true },
        { "key": "revenue_tracking", "label": "Suivi des recettes", "included": true },
        { "key": "analytics", "label": "Analytiques avancées", "included": true },
        { "key": "activity_history", "label": "Historique activité complet", "included": true }
      ]
    }
  ]
}
```

### Rendu UI suggéré

- Boucler sur `data[]`
- Afficher `name`, `tagline`, `price_label`
- Liste à puces : `features.filter(f => f.included).map(f => f.label)`
- Bouton CTA : **"S'inscrire"** → redirige vers `/register?plan={code}`

---

## 4. Clic sur un plan → inscription

Quand l'utilisateur clique **Basic** ou **Pro** :

```typescript
// Exemple Vue / React
function onSelectPlan(planCode: 'basic' | 'pro') {
  // Option A — query param (recommandé, partageable)
  router.push(`/register?plan=${planCode}`);

  // Option B — state global / localStorage (backup si refresh)
  localStorage.setItem('selected_plan_code', planCode);
}
```

**À l'ouverture du formulaire d'inscription** :

```typescript
const planCode =
  route.query.plan as string ||
  localStorage.getItem('selected_plan_code') ||
  'basic'; // fallback si accès direct /register

// Valider
if (!['basic', 'pro'].includes(planCode)) {
  planCode = 'basic';
}
```

Afficher un récap visuel : *"Vous vous inscrivez sur le plan Basic — 5 000 F /mois"* (données depuis `GET /plans` ou constantes).

---

## 5. Inscription avec plan

### Endpoint

```
POST /api/v1/auth/register
Content-Type: application/json
Authorization: aucune
```

### Body

| Champ | Type | Obligatoire | Exemple |
|-------|------|-------------|---------|
| `salon_name` | string | oui | `"Salon Koffi Cocody"` |
| `city` | string (enum) | oui | `"Abidjan"` |
| `whatsapp_number` | string | oui | `"2250708112233"` |
| `admin_name` | string | oui | `"Koffi Atta"` |
| `phone` | string | oui | `"2250708112233"` |
| `password` | string | oui | min 8 caractères |
| `password_confirmation` | string | oui | identique à `password` |
| **`plan_code`** | string | **oui** | `"basic"` ou `"pro"` |

### Exemple complet

```json
{
  "salon_name": "Salon Koffi Cocody",
  "city": "Abidjan",
  "whatsapp_number": "2250708112233",
  "admin_name": "Koffi Atta",
  "phone": "2250708112233",
  "password": "monmotdepasse8",
  "password_confirmation": "monmotdepasse8",
  "plan_code": "basic"
}
```

### Réponse 201

```json
{
  "success": true,
  "message": "Inscription réussie.",
  "data": {
    "access_token": "eyJ...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "expires_at": "2026-06-05T18:00:00+00:00",
    "salon": {
      "id": "uuid",
      "name": "Salon Koffi Cocody",
      "slug": "salon-koffi-cocody",
      "booking_link": "https://salono.ci/booking/salon-koffi-cocody",
      "booking_qr_code": "data:image/png;base64,..."
    },
    "user": {
      "id": "uuid",
      "name": "Koffi Atta",
      "phone": "2250708112233",
      "role": "admin",
      "is_active": true
    },
    "subscription": {
      "id": "uuid",
      "status": "trial",
      "is_trial": true,
      "plan": {
        "code": "basic",
        "name": "Basic",
        "price_fcfa": 5000,
        "max_employees": 5,
        "max_services": 50,
        "has_online_booking": true,
        "has_analytics": false
      },
      "trial_ends_at": "2026-06-12",
      "ends_at": null
    }
  }
}
```

### Erreurs 422

```json
{
  "success": false,
  "message": "Les données fournies sont invalides.",
  "errors": {
    "plan_code": ["Le plan sélectionné est invalide."]
  }
}
```

---

## 6. Après inscription — état trial (7 jours)

À l'inscription, le salon est créé avec :

| Champ subscription | Valeur |
|---|---|
| `status` | `trial` |
| `is_trial` | `true` |
| `plan.code` | celui choisi (`basic` ou `pro`) |
| `trial_ends_at` | **date d'inscription + 7 jours** (inclus, fin de journée) |
| `ends_at` | `null` (rempli après premier paiement) |

**Durée** : 7 jours calendaires à compter de la date d'inscription (`SUBSCRIPTION_TRIAL_DAYS=7` côté back).

**Pendant le trial** : accès complet aux limites du plan choisi (ex. Basic = max 5 employés).

**Après `trial_ends_at`** (si pas encore payé) :
- API protégées → `403` avec `code: "subscription_expired"`
- Le front doit bloquer l'app et forcer l'écran paiement

```typescript
const isTrialExpired =
  subscription.status === 'trial' &&
  subscription.trial_ends_at &&
  new Date(subscription.trial_ends_at) < new Date();

const trialDaysLeft = subscription.trial_ends_at
  ? Math.ceil((new Date(subscription.trial_ends_at) - new Date()) / 86400000)
  : 0;

// Bannière : "Essai Basic — 3 jours restants"
```

Stocker le token :

```typescript
localStorage.setItem('access_token', data.access_token);
localStorage.removeItem('selected_plan_code'); // nettoyage
router.push('/onboarding'); // ou dashboard
```

---

## 7. Simulation paiement (front + back)

Pas d'agrégateur Wave/OM pour l'instant. Le front **simule l'UX** ; le back **active réellement** l'abonnement.

### Flow UX front (à implémenter)

```
1. User trial sur dashboard
2. Bannière : "Activez votre abonnement Basic — 5 000 F/mois"
3. Clic → modal/page choix moyen de paiement (UI fake)
   - Wave
   - Orange Money
4. Animation "Paiement en cours..." (2-3 sec, 100% front)
5. Appel API simulate-payment
6. Succès → toast + refresh abonnement (status = active)
```

### Endpoint backend

```
POST /api/v1/subscription/simulate-payment
Authorization: Bearer {token}
Role requis: admin
```

### Body

```json
{
  "method": "wave",
  "plan_code": "pro"
}
```

| Champ | Obligatoire | Description |
|-------|-------------|-------------|
| `method` | oui | Moyen de paiement simulé |
| `plan_code` | non | `basic` ou `pro` — **requis pour upgrade/changement de plan** |

### Règles métier (important)

| Situation | Comportement back |
|-----------|-------------------|
| Trial → premier paiement | Active le plan (`plan_code` ou plan actuel) |
| Basic actif → repayer Basic | **422 refusé** si `ends_at` > 7 jours |
| Basic actif → payer Pro | **OK** — met à jour le **même** abonnement (`plan_id` → pro) |
| Basic actif, expire dans ≤ 7j → renouveler Basic | **OK** — prolonge à partir de `ends_at` |

**Un salon = une ligne `subscriptions`** (jamais de 2e abonnement créé au paiement).

### Erreur 422 — déjà actif

```json
{
  "success": false,
  "message": "Votre abonnement est déjà actif jusqu'au 2026-07-04."
}
```

### UI front recommandée

```typescript
// Ne pas proposer "Souscrire Basic" si déjà Basic actif
if (sub.status === 'active' && sub.plan.code === 'basic') {
  showUpgradeToPro(); // pas re-subscribe basic
}

// Upgrade Pro
await api.post('/subscription/simulate-payment', {
  method: 'wave',
  plan_code: 'pro',
});
```

Valeurs `method` acceptées :

| Valeur | Label UI |
|--------|----------|
| `wave` | Wave |
| `orange_money` | Orange Money |
| `mobile_money` | Mobile Money |
| `card` | Carte bancaire |
| `cash` | Espèces |

### Réponse 200

```json
{
  "success": true,
  "message": "Paiement simulé avec succès. Abonnement activé.",
  "data": {
    "id": "uuid",
    "status": "active",
    "is_trial": false,
    "plan": {
      "code": "basic",
      "name": "Basic",
      "price_fcfa": 5000,
      "max_employees": 5,
      "has_analytics": false
    },
    "ends_at": "2026-07-04"
  }
}
```

### Exemple front complet

```typescript
async function simulatePayment(method: 'wave' | 'orange_money') {
  // 1. UX simulée côté front
  showPaymentLoader('Connexion à ' + method + '...');
  await sleep(2000);
  showPaymentLoader('Validation du paiement...');
  await sleep(1500);

  // 2. Appel réel backend
  const res = await api.post('/subscription/simulate-payment', { method });
  if (res.data.success) {
    showToast('Abonnement activé jusqu\'au ' + res.data.data.ends_at);
    await refreshSubscription();
  }
}
```

### Quand afficher l'écran paiement ?

```typescript
const needsPayment =
  subscription.status === 'trial' ||
  (subscription.status === 'active' && isExpiringSoon(subscription.ends_at));

// isExpiringSoon = ends_at dans les 7 prochains jours
```

### Désactivation en production

Quand Wave/OM seront branchés, le back désactivera cet endpoint via `.env` :

```
SIMULATE_SUBSCRIPTION_PAYMENTS=false
```

Le front devra alors remplacer l'appel par le futur flow checkout réel.

---

## 8. Consulter l'abonnement courant

### Endpoint

```
GET /api/v1/subscription
Authorization: Bearer {token}
Role: admin
```

### Réponse 200

```json
{
  "success": true,
  "data": {
    "subscription": {
      "id": "uuid",
      "status": "trial",
      "is_trial": true,
      "plan": {
        "code": "basic",
        "name": "Basic",
        "price_fcfa": 5000,
        "max_employees": 5,
        "has_analytics": false
      },
      "ends_at": null
    },
    "usage": {
      "active_employees": 2,
      "max_employees": 5,
      "max_employees_label": "5 membres du personnel",
      "employees_remaining": 3
    },
    "features": [
      { "key": "analytics", "label": "Analytiques avancées", "included": false }
    ]
  }
}
```

**Pro** : `max_employees` et `employees_remaining` = `null` (illimité).

Aussi disponible via `GET /api/v1/auth/me` → `data.user.subscription` (moins détaillé, sans `usage`).

---

## 9. Features par plan & garde-fous UI

### Limites backend (erreurs à anticiper)

| Action | Basic | Pro | Erreur back |
|--------|-------|---------|-------------|
| Ajouter employé | max 5 | illimité | `"Limite d'employés atteinte pour votre plan."` |
| Ajouter prestation | max 50 | max 200 | `"Limite de prestations atteinte pour votre plan."` |
| Formulaire public RDV | oui | oui | `subscription_feature_denied` si désactivé |

### Garde-fous UI recommandés

```typescript
// Employés
if (usage.employees_remaining === 0) {
  disableAddEmployeeButton();
  showUpgradeBanner('Passez Pro pour des employés illimités');
}

// Analytics / recettes / activité
if (!subscription.plan.has_analytics) {
  hideSidebarItems(['/dashboard/revenue', '/dashboard/analytics', '/dashboard/activity']);
  // ou afficher version "locked" avec CTA upgrade Pro
}
```

### Matrice sidebar

| Route front | API back | Basic | Pro |
|-------------|----------|-------|---------|
| Dashboard overview | `GET /dashboard/overview` | ✅ | ✅ |
| Stats clients | `GET /dashboard/clients-stats` | 🔒 | ✅ |
| Stats recettes | `GET /dashboard/revenue-stats` | 🔒 | ✅ |
| Historique activité | `GET /dashboard/activity` | 🔒 | ✅ |
| Employés | `GET /users` | ✅ (max 5) | ✅ |
| RDV / Agenda | `GET /appointments` | ✅ | ✅ |
| Clients | `GET /clients` | ✅ | ✅ |
| Paiements caisse | `GET /payments` | ✅ | ✅ |
| Formulaire public | lien booking | ✅ | ✅ |

---

## 10. Endpoints dashboard Pro

Protégés par `has_analytics = true` (Pro uniquement).

```
GET /api/v1/dashboard/clients-stats?period=month
GET /api/v1/dashboard/revenue-stats?period=month
GET /api/v1/dashboard/activity?limit=20
```

### Réponse 403 (Basic)

```json
{
  "success": false,
  "message": "Cette fonctionnalité n'est pas incluse dans votre plan actuel. Veuillez mettre à niveau votre abonnement.",
  "code": "subscription_feature_denied"
}
```

**Comportement front** : intercepter `code === 'subscription_feature_denied'` → modal upgrade Pro.

---

## 11. Codes erreur à gérer

| `code` | HTTP | Signification | Action front |
|--------|------|---------------|--------------|
| `subscription_feature_denied` | 403 | Feature pas dans le plan | Modal upgrade |
| `subscription_expired` | 403 | Abonnement expiré | Redirect paiement |
| `subscription_missing` | 403 | Pas d'abonnement | Support / réinscription |

---

## 12. Checklist intégration

- [ ] Page pricing : `GET /api/v1/plans`, afficher cards Basic / Pro
- [ ] Clic plan → `/register?plan=basic|pro`
- [ ] Formulaire inscription envoie `plan_code`
- [ ] Récap plan visible avant submit
- [ ] Après 201 : stocker token, lire `subscription.plan.code`
- [ ] Bannière trial + CTA "Activer mon abonnement"
- [ ] Modal paiement simulé (Wave / OM) — UX 100% front
- [ ] Après simulation : `POST /subscription/simulate-payment`
- [ ] `GET /subscription` pour barre usage employés
- [ ] Masquer / locker routes analytics si `!plan.has_analytics`
- [ ] Gérer `subscription_feature_denied` sur dashboard pro
- [ ] Page upgrade : comparatif Basic → Pro

---

## Migration / seed côté back (dev)

```bash
php artisan migrate
php artisan db:seed --class=PlanSeeder
```

Les plans `basic` et `pro` seront créés/mis à jour en base.
