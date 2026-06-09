# Inscription Salon — Design System Backend

> Document de référence complet pour l'implémentation du module d'inscription.
> Chaque étape est détaillée : validation, logique métier, réponses API, erreurs.

---

## Sommaire

1. [Vue d'ensemble du flux](#1-vue-densemble-du-flux)
2. [Endpoint](#2-endpoint)
3. [Champs du formulaire](#3-champs-du-formulaire)
4. [Règles de validation](#4-règles-de-validation)
5. [Génération du slug](#5-génération-du-slug)
6. [Logique métier — ce qui est créé en base](#6-logique-métier--ce-qui-est-créé-en-base)
7. [Réponse succès](#7-réponse-succès)
8. [Réponses erreur](#8-réponses-erreur)
9. [Événements déclenchés](#9-événements-déclenchés)
10. [Sécurité et contraintes](#10-sécurité-et-contraintes)
11. [Résumé des tables touchées](#11-résumé-des-tables-touchées)

---

## 1. Vue d'ensemble du flux

```
Client soumet le formulaire
        │
        ▼
Validation des champs (Laravel FormRequest)
        │
        ├── Échec → 422 Unprocessable Entity + erreurs par champ
        │
        ▼
Génération du slug unique depuis le nom du salon
        │
        ▼
Transaction DB : création salon + user admin + subscription trial
        │
        ├── Échec DB → 500 + rollback complet
        │
        ▼
Génération du token Sanctum
        │
        ▼
201 Created → { token, salon, user, subscription }
```

---

## 2. Endpoint

```
POST /api/auth/register
Content-Type: application/json
Authorization: aucune (route publique)
```

---

## 3. Champs du formulaire

### 3.1 Champs obligatoires

| Champ | Type | Exemple | Destination en base |
|---|---|---|---|
| `salon_name` | string | "Salon Koffi Cocody" | `salons.name` |
| `city` | string (enum) | "Abidjan" | `salons.city` |
| `whatsapp_number` | string | "2250708112233" | `salons.whatsapp_number` |
| `admin_name` | string | "Koffi Atta" | `users.name` |
| `phone` | string | "2250708112233" | `users.phone` (login) |
| `password` | string | "monmotdepasse8" | `users.password` (hashé) |
| `password_confirmation` | string | "monMotdepasse8" | validation uniquement, non stocké |

### 3.2 Champs optionnels

Aucun champ optionnel à l'inscription. Tout le reste (adresse complète, logo, horaires, email) est renseigné après dans les paramètres du salon.

### 3.3 Champs générés automatiquement (non soumis par le client)

| Champ | Généré comment |
|---|---|
| `salons.slug` | Depuis `salon_name` (voir section 5) |
| `salons.is_active` | `true` par défaut |
| `users.role` | `admin` forcé |
| `users.salon_id` | ID du salon créé dans la même transaction |
| `users.is_active` | `true` par défaut |
| `subscriptions.status` | `trial` forcé |
| `subscriptions.is_trial` | `true` forcé |
| `subscriptions.plan_id` | ID du plan `free` (récupéré depuis `plans` table) |
| `subscriptions.trial_ends_at` | `null` (trial illimité jusqu'à upgrade) |
| `subscriptions.started_at` | `null` (sera rempli au premier vrai paiement) |
| `subscriptions.ends_at` | `null` (idem) |

---

## 4. Règles de validation

### 4.1 `salon_name`

```
required
string
min: 2
max: 100
```

- Trim automatique des espaces avant/après
- Caractères autorisés : lettres, chiffres, espaces, tirets, apostrophes
- Pas de validation d'unicité sur le nom (deux salons peuvent avoir le même nom)
- Le slug généré depuis ce champ est lui unique (voir section 5)

---

### 4.2 `city`

```
required
string
in: Abidjan, Bouaké, Yamoussoukro, Daloa, San-Pédro, Korhogo, Man, Gagnoa, Abengourou, Divo
```

- Valeur libre en V1 si la liste est trop restrictive, mais idéalement une enum pour forcer la cohérence
- Stockée telle quelle dans `salons.city`

---

### 4.3 `whatsapp_number`

```
required
string
regex: /^225[0-9]{10}$/
unique: salons,whatsapp_number
```

- Format attendu : indicatif Côte d'Ivoire `225` + 10 chiffres → 13 chiffres au total
- Exemple valide : `2250708112233`
- Exemple invalide : `08112233` / `+2250708112233` (le `+` est retiré côté frontend avant envoi)
- Unicité stricte : un numéro WhatsApp ne peut être utilisé que par un seul salon
- Message d'erreur si doublon : `"Ce numéro WhatsApp est déjà associé à un salon."`

---

### 4.4 `admin_name`

```
required
string
min: 2
max: 100
```

- Trim automatique
- Pas de contrainte de format particulier

---

### 4.5 `phone`

```
required
string
regex: /^225[0-9]{10}$/
unique: users,phone
```

- Même format que `whatsapp_number` : `225` + 10 chiffres
- C'est **l'identifiant de connexion** du gérant (pas d'email)
- Unicité globale sur la table `users` (un numéro = un seul compte sur toute la plateforme)
- Message d'erreur si doublon : `"Ce numéro de téléphone est déjà utilisé."`
- `phone` et `whatsapp_number` peuvent être identiques (le gérant utilise son propre WhatsApp pour le salon)

---

### 4.6 `password`

```
required
string
min: 8
max: 255
confirmed (doit correspondre à password_confirmation)
```

- Hashé avec `bcrypt` via `Hash::make()` avant stockage
- Jamais stocké en clair
- Pas de règle de complexité forcée en V1 (majuscule, chiffre...) pour ne pas bloquer les gérants peu tech

---

### 4.7 `password_confirmation`

```
required
string
doit être identique à password
```

- Non stocké en base
- Utilisé uniquement pour la validation Laravel `confirmed`

---

## 5. Génération du slug

Le slug est l'identifiant public unique du salon. Il est utilisé dans le lien de réservation WhatsApp :

```
https://tondomaine.com/api/booking/{slug}
```

### 5.1 Algorithme de génération

```
Étape 1 : Prendre salon_name
           "Salon Koffi Cocody"

Étape 2 : Mettre en minuscules
           "salon koffi cocody"

Étape 3 : Remplacer les caractères accentués
           é → e, è → e, ê → e, à → a, ô → o, û → u, ç → c, etc.
           "salon koffi cocody"

Étape 4 : Remplacer espaces et caractères spéciaux par des tirets
           "salon-koffi-cocody"

Étape 5 : Supprimer les tirets multiples consécutifs
           "salon-koffi-cocody" (inchangé ici)

Étape 6 : Vérifier l'unicité dans salons.slug
           → Si unique : utiliser tel quel
           → Si doublon : ajouter un suffixe numérique
                "salon-koffi-cocody-2"
                "salon-koffi-cocody-3"
                etc.
```

### 5.2 Exemples

| salon_name soumis | slug généré |
|---|---|
| "Salon Koffi Cocody" | `salon-koffi-cocody` |
| "Barber's Shop Plateau" | `barbers-shop-plateau` |
| "Coiffure Élégance" | `coiffure-elegance` |
| "Chez Fatou & Co" | `chez-fatou-co` |
| "Salon Koffi Cocody" (doublon) | `salon-koffi-cocody-2` |

### 5.3 Implémentation Laravel recommandée

```php
use Illuminate\Support\Str;

private function generateSlug(string $salonName): string
{
    $base = Str::slug($salonName); // gère accents + espaces + casse
    $slug = $base;
    $counter = 2;

    while (Salon::where('slug', $slug)->exists()) {
        $slug = $base . '-' . $counter;
        $counter++;
    }

    return $slug;
}
```

---

## 6. Logique métier — ce qui est créé en base

Tout est créé dans **une seule transaction DB**. Si une étape échoue, tout est rollback.

```php
DB::transaction(function () use ($validated) {

    // Étape 1 : Créer le salon
    $salon = Salon::create([
        'name'             => $validated['salon_name'],
        'slug'             => $this->generateSlug($validated['salon_name']),
        'whatsapp_number'  => $validated['whatsapp_number'],
        'city'             => $validated['city'],
        'is_active'        => true,
    ]);

    // Étape 2 : Créer l'utilisateur admin
    $user = User::create([
        'salon_id'  => $salon->id,
        'name'      => $validated['admin_name'],
        'phone'     => $validated['phone'],
        'password'  => Hash::make($validated['password']),
        'role'      => 'admin',
        'is_active' => true,
    ]);

    // Étape 3 : Récupérer le plan free
    $freePlan = Plan::where('code', 'free')->firstOrFail();

    // Étape 4 : Créer la subscription trial
    $subscription = Subscription::create([
        'salon_id'       => $salon->id,
        'plan_id'        => $freePlan->id,
        'status'         => 'trial',
        'is_trial'       => true,
        'trial_ends_at'  => null, // trial illimité jusqu'à upgrade
        'started_at'     => null,
        'ends_at'        => null,
    ]);

    // Étape 5 : Générer le token Sanctum
    $token = $user->createToken('auth_token')->plainTextToken;

    return [$salon, $user, $subscription, $token];
});
```

---

## 7. Réponse succès

**HTTP 201 Created**

```json
{
  "message": "Inscription réussie.",
  "token": "1|aBcDeFgHiJkLmNoPqRsTuVwXyZ...",
  "token_type": "Bearer",
  "salon": {
    "id": "uuid",
    "name": "Salon Koffi Cocody",
    "slug": "salon-koffi-cocody",
    "whatsapp_number": "2250708112233",
    "city": "Abidjan",
    "is_active": true,
    "booking_link": "https://tondomaine.com/api/booking/salon-koffi-cocody",
    "created_at": "2025-05-23T10:00:00Z"
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
      "code": "free",
      "name": "Gratuit",
      "price_fcfa": 0
    },
    "trial_ends_at": null,
    "ends_at": null
  }
}
```

> Le frontend stocke `token` dans le store (Pinia/Vuex) et redirige vers `/dashboard`.
> Le `booking_link` est affiché immédiatement dans les paramètres du salon.

---

## 8. Réponses erreur

### 8.1 Validation échouée — 422 Unprocessable Entity

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "salon_name": ["Le nom du salon est obligatoire."],
    "phone": ["Ce numéro de téléphone est déjà utilisé."],
    "whatsapp_number": ["Le format du numéro WhatsApp est invalide."],
    "password": ["Le mot de passe doit contenir au moins 8 caractères."]
  }
}
```

Chaque champ retourne un tableau d'erreurs. Le frontend affiche les erreurs sous chaque input correspondant.

### 8.2 Erreur serveur — 500 Internal Server Error

```json
{
  "message": "Une erreur est survenue lors de la création du compte. Veuillez réessayer."
}
```

Déclenché si la transaction DB échoue. Le rollback garantit qu'aucune donnée partielle n'est enregistrée.

### 8.3 Plan free introuvable — 500

Si le plan `free` n'existe pas en base (données manquantes), la transaction échoue avec un message explicite en log :

```
PlanNotFoundException: Le plan 'free' est introuvable en base de données.
```

> Prévention : le seeder des plans doit toujours être exécuté avant le premier déploiement.

---

## 9. Événements déclenchés

Après la transaction réussie, les événements suivants sont dispatchés :

| Événement | Rôle |
|---|---|
| `SalonRegistered` | Log interne, stats d'inscription |
| `WelcomeMessageQueued` | Envoi d'un message WhatsApp de bienvenue au gérant |

### Message WhatsApp de bienvenue (envoyé sur `whatsapp_number`)

```
Bienvenue sur [Nom plateforme] !

Votre salon *{salon_name}* est créé avec succès.

Votre lien de réservation WhatsApp :
https://tondomaine.com/api/booking/{slug}

Partagez ce lien à vos clients pour qu'ils réservent directement.

Connectez-vous sur : https://tondomaine.com
```

---

## 10. Sécurité et contraintes

| Contrainte | Détail |
|---|---|
| Rate limiting | 5 tentatives d'inscription par IP par heure |
| Pas d'email requis | L'inscription ne dépend pas d'une vérification email |
| Password hashé | `bcrypt` via `Hash::make()`, jamais en clair |
| Token Sanctum | Durée de vie : pas d'expiration en V1 (révocation manuelle au logout) |
| Unicité `phone` | Un numéro = un seul compte sur toute la plateforme |
| Unicité `whatsapp_number` | Un numéro WhatsApp = un seul salon |
| Unicité `slug` | Gérée par l'algorithme de suffixe numérique |
| Transaction atomique | Rollback complet si une étape échoue |
| Route publique | Aucun token requis sur `POST /api/auth/register` |

---

## 11. Résumé des tables touchées

| Table | Opération | Champs remplis |
|---|---|---|
| `salons` | INSERT | `id`, `name`, `slug`, `whatsapp_number`, `city`, `is_active`, `created_at` |
| `users` | INSERT | `id`, `salon_id`, `name`, `phone`, `password`, `role`, `is_active`, `created_at` |
| `subscriptions` | INSERT | `id`, `salon_id`, `plan_id`, `status`, `is_trial`, `trial_ends_at`, `started_at`, `ends_at`, `created_at` |
| `personal_access_tokens` | INSERT | Token Sanctum généré pour la session |

**Tables lues (non modifiées) :**

| Table | Pourquoi |
|---|---|
| `salons` | Vérifier unicité `whatsapp_number` et `slug` |
| `users` | Vérifier unicité `phone` |
| `plans` | Récupérer l'ID du plan `free` |

---

*Fin du document — inscription_salon.md — v1.0*
