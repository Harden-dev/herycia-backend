# Spécifications Backend — Mise à jour Mai 2026

**Date** : 1er mai 2026
**Responsable backend** : Banh Michel (Michen Harden)
**Responsable mobile** : Gaël Sassan
**Cheffe de projet / QA** : Koffi Rosine
**Validé par** : Issouf Baïkoro (Alpha Sécurité)

> Ce document liste **uniquement** ce qui doit être fait côté backend (API + base de données) pour livrer la mise à jour de mai 2026. Les actions opérationnelles (création de comptes, upload de photos, saisie de données par Rosine ou Michel) sont dans `actions_operationnelles_maj_mai_2026.md`.

---

## Contexte

Trois mises à jour demandées par le client :

1. **Inscription par numéro de téléphone** (résidents et commerces)
2. **Refonte des cartes profils d'agents** sur la page d'accueil (50 profils, scroll horizontal par catégorie, design fond noir + bande verte avec grade)
3. **Tarifs standardisés** par catégorie d'agent (12h / 24h)

---

## MAJ 1 — Inscription par numéro de téléphone

### 1.1 Objectif

Permettre aux résidents et aux commerces de créer un compte avec leur **numéro de téléphone** uniquement (sans email), parce que la majorité des commerces n'ont pas d'email mais utilisent leur numéro pour Wave et autres transactions.

### 1.2 État actuel

- L'endpoint `POST /auth/register` accepte déjà un champ `login` qui peut être un email **ou** un téléphone (l'app mobile envoie déjà ce champ correctement).
- L'endpoint `POST /auth/verify-otp` existe déjà.
- Le `UserModel` côté mobile supporte déjà `phone` et `is_phone_verified`.

### 1.3 À faire côté backend

#### A. Détection du canal (email vs téléphone)

Dans `POST /auth/register`, détecter automatiquement si `login` est :

- un **email** → flow OTP par email (déjà en place)
- un **numéro de téléphone** → flow OTP par **SMS**

**Format téléphone attendu** : E.164 (`+225XXXXXXXXXX` pour la Côte d'Ivoire). Normaliser à la réception.

#### B. Intégration SMS (à choisir)

Deux options à arbitrer avec Alpha selon le budget :

| Option | Coût | Recommandation |
|---|---|---|
| **Twilio** | ~15K FCFA / mois minimum + coût par SMS | Solution standard, robuste |
| **Pas d'OTP SMS au démarrage** | 0 | Inscription téléphone valide directement le compte. Vérification SMS branchée plus tard quand le volume justifie le coût |

> **Décision en attente du client** (Alpha mentionne 15K disponibles via "Stéphane"). Tant que la décision n'est pas prise, implémenter avec un **flag de configuration** `SMS_OTP_ENABLED` :
> - `true` → envoie SMS via Twilio
> - `false` → l'inscription par téléphone crée directement le compte avec `is_phone_verified=false`, l'utilisateur peut se connecter mais devra vérifier son numéro plus tard pour certaines actions sensibles

#### C. Endpoint `POST /auth/register` — réponse attendue

```json
// Cas email (existant, à conserver)
{
  "requires_otp_verification": true,
  "channel": "email",
  "expires_in": 300,
  "login": "user@example.com"
}

// Cas téléphone avec SMS activé
{
  "requires_otp_verification": true,
  "channel": "sms",
  "expires_in": 300,
  "login": "+2250707070707"
}

// Cas téléphone sans SMS (flag désactivé)
{
  "requires_otp_verification": false,
  "access_token": "...",
  "user": { ... }
}
```

#### D. Endpoint `POST /auth/verify-otp`

Doit accepter aussi bien un `login` email qu'un `login` téléphone. Une fois validé :
- `is_email_verified = true` si email
- `is_phone_verified = true` si téléphone

#### E. Endpoint `POST /auth/resend-otp`

Idem : selon le canal du `login`, renvoyer par email ou par SMS.

### 1.4 Google Sign-In (optionnel, à arbitrer)

Michel a confirmé pouvoir le gérer. À implémenter si Alpha valide :

- Nouvel endpoint : `POST /auth/google`
- Body : `{ "id_token": "<google_id_token>" }`
- Backend valide l'`id_token` auprès de Google, crée ou retrouve l'utilisateur via `email`, retourne `access_token` + `user`

> **Décision en attente du client.**

---

## MAJ 2 — Refonte cartes agents (page d'accueil)

### 2.1 Objectif

Remplacer la liste actuelle d'agents sur la page d'accueil par **9 sections horizontales scrollables**, une par catégorie d'agent. Chaque section affiche **10 agents** avec un nouveau design (fond noir + bande verte avec grade).

### 2.2 État actuel

- L'endpoint `GET /agents` existe avec pagination (15 par page) et filtres (`search`, `commune_id`).
- Le modèle `AgentModel` côté mobile a déjà :
  - `specialisations[]` avec `is_primary`, `pricing.price_12h`, `pricing.price_24h`
  - `primary_pricing`
  - `profile_picture_url`
- **Aucun champ `grade`** n'existe côté agent (référent / confirmé / ordinaire).
- **Aucun filtre par spécialisation** sur `GET /agents`.

### 2.3 À faire côté backend

#### A. Ajouter le champ `grade` sur la table `agents`

Migration à créer :

```sql
ALTER TABLE agents
ADD COLUMN grade VARCHAR(20) NOT NULL DEFAULT 'ordinaire';
-- Valeurs autorisées : 'ordinaire', 'confirme', 'referent'
```

Exposer ce champ dans toutes les réponses agent :

```json
{
  "id": "...",
  "user": { ... },
  "grade": "referent",
  "specialisations": [...],
  ...
}
```

#### B. Ajouter le filtre par spécialisation sur `GET /agents`

Nouveau paramètre optionnel : `specialisation_id`

```
GET /agents?specialisation_id=ssiap&per_page=10
```

Retourne les 10 agents (ou plus selon `per_page`) dont `specialisations[]` contient cette spécialisation marquée `is_primary=true`.

Les filtres existants (`search`, `commune_id`, pagination) doivent rester fonctionnels et combinables.

#### C. (Optionnel) Endpoint dédié pour la home

Si Michel préfère, on peut créer un endpoint unique qui renvoie tout en un seul appel pour optimiser le chargement de la home :

```
GET /agents/grouped-by-specialisation?per_category=10
```

Réponse :

```json
{
  "categories": [
    {
      "specialisation": {
        "id": "ssiap",
        "name": "Agent de sécurité incendie (SSIAP)",
        "pricing": { "price_12h": 10145, "price_24h": 20290 }
      },
      "agents": [ ... 10 agents ... ]
    },
    ...
  ]
}
```

> **Décision** : à voir avec Michel ce qui est le plus simple. L'option B (filtre simple) est suffisante côté front, l'option C est juste plus performante.

#### D. Champ `years_of_experience` (déjà dans la maquette)

Les cartes du PDF affichent "10 ans d'expérience" / "05 ans d'expérience". Vérifier que ce champ est exposé dans la réponse agent. Si non, l'ajouter :

```sql
ALTER TABLE agents
ADD COLUMN years_of_experience INT DEFAULT 0;
```

Et l'exposer en JSON : `"years_of_experience": 10`.

---

## MAJ 3 — Tarifs standardisés par catégorie

### 3.1 Objectif

Appliquer la grille tarifaire officielle Alph Sécurité sur les 9 catégories de spécialisation.

### 3.2 Grille tarifaire à appliquer

| Spécialisation | 12h (FCFA) | 24h (FCFA) |
|---|---|---|
| Agent d'accueil | 8 895 | 17 790 |
| Agent de sécurité de base (ADS) | 8 895 | 17 790 |
| Agent de sécurité incendie (SSIAP) | 10 145 | 20 290 |
| Agent de sécurité cynophile (maître-chien) | 12 645 | 25 290 |
| Agent de sécurité mobile | 12 040 | 24 080 |
| Agent de sécurité filtrage / contrôle accès | 11 395 | 22 790 |
| Agent de sécurité rapprochée | 16 395 | 32 790 |
| Opérateur vidéosurveillance (PC Sécurité) | 8 895 | 17 790 |
| Chef de poste / Superviseur | 11 395 | 22 790 |

### 3.3 À faire côté backend

#### A. Vérifier la table `specialisations`

L'endpoint `GET /reference/specialisations` doit retourner pour chaque catégorie :

```json
{
  "id": "ssiap",
  "name": "Agent de sécurité incendie (SSIAP)",
  "slug": "ssiap",
  "pricing": {
    "price_12h": 10145,
    "price_24h": 20290,
    "currency": "XOF"
  }
}
```

> Le modèle Flutter `SpecialisationPricingModel` lit déjà `price_12h`, `price_24h` et `currency`. **Pas de changement de contrat API** — il suffit de **renseigner les bonnes valeurs en base**.

#### B. Renseigner ou ajuster les 9 catégories en base

Si certaines spécialisations n'existent pas encore en base, les créer. Sinon, simplement mettre à jour `price_12h` et `price_24h` selon la grille ci-dessus.

> Cette action est **opérationnelle** — voir `actions_operationnelles_maj_mai_2026.md`.

#### C. Cohérence `agents.primary_pricing`

Quand on retourne un agent, `primary_pricing` doit être hérité automatiquement de la spécialisation primaire de l'agent (`specialisations[is_primary=true].pricing`).

Vérifier que la logique côté backend est bien : **un agent n'a pas de prix custom**, il hérite des tarifs standard de sa spécialisation primaire.

---

## Résumé des changements API

### Migrations DB

```sql
ALTER TABLE agents
  ADD COLUMN grade VARCHAR(20) NOT NULL DEFAULT 'ordinaire',
  ADD COLUMN years_of_experience INT DEFAULT 0;

-- Mise à jour des tarifs sur les 9 spécialisations
-- (voir grille tarifaire MAJ 3)
```

### Endpoints modifiés

| Endpoint | Modification |
|---|---|
| `POST /auth/register` | Détection auto email/téléphone, déclenchement SMS si téléphone et flag `SMS_OTP_ENABLED=true` |
| `POST /auth/verify-otp` | Supporter validation OTP SMS (en plus de l'email) |
| `POST /auth/resend-otp` | Renvoi via le bon canal selon le `login` |
| `GET /agents` | Nouveau filtre `specialisation_id` |
| `GET /agents/{id}` | Inclure `grade` et `years_of_experience` |
| `GET /reference/specialisations` | Inclure `pricing` à jour selon la grille |

### Endpoints nouveaux (optionnels)

| Endpoint | Usage |
|---|---|
| `POST /auth/google` | Si Google Sign-In validé |
| `GET /agents/grouped-by-specialisation` | Si optimisation home souhaitée |

### Configuration

| Variable | Valeur | Description |
|---|---|---|
| `SMS_OTP_ENABLED` | `true` / `false` | Active l'envoi de SMS OTP via Twilio |
| `TWILIO_ACCOUNT_SID` | secret | Si SMS activé |
| `TWILIO_AUTH_TOKEN` | secret | Si SMS activé |
| `TWILIO_FROM_NUMBER` | E.164 | Numéro émetteur |

---

## Points en attente d'arbitrage client

1. **OTP SMS** : on paye Twilio dès cette release ou on lance sans OTP SMS ?
2. **Google Sign-In** : oui / non sur cette release ?
3. **Endpoint groupé** ou filtre simple : choix de Michel selon sa préférence d'implémentation.

---

## Contrat de livraison

Une fois ces changements backend déployés sur l'environnement de **staging** :

- Mobile (Gaël) intègre les nouvelles UI déjà préparées
- Rosine valide bout-en-bout (création de compte par téléphone, affichage des cartes par catégorie, prix corrects)
- Bascule en production une fois validé
