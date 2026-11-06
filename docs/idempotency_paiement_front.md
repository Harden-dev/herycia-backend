# Idempotence du paiement d'abonnement (front)

GeniusPay ne protège pas contre les doublons : chaque appel à `POST /api/v1/subscription/initialize`
crée un nouveau paiement. Le back déduplique grâce à une **clé d'idempotence** fournie par le front.

## Règle

L'en-tête `Idempotency-Key` est **obligatoire** sur `POST /api/v1/subscription/initialize`.

- Format : 8 à 128 caractères `[A-Za-z0-9_-]` — utiliser `crypto.randomUUID()`.
- Une clé = **une tentative de paiement** (un salon, un plan).

| Situation | Clé à envoyer |
|---|---|
| Clic sur « Payer » (nouvelle tentative) | **Nouvelle** clé |
| Double clic, timeout, erreur réseau, retry automatique de la même tentative | **Même** clé |
| L'utilisateur change de plan | **Nouvelle** clé |
| Réponse 409 `idempotency_key_expired` (paiement échoué ou lien expiré) | **Nouvelle** clé |
| Réponse 409 `request_in_progress` | **Même** clé, réessayer après 1-2 s |
| Réponse 502 (GeniusPay indisponible) | **Même** clé possible pour réessayer |
| Réponse 409 `too_many_pending_payments` | Ne pas réessayer : reprendre un paiement en cours ou attendre l'expiration des liens |
| Réponse 429 (trop de tentatives) | Attendre (en-tête `Retry-After`), puis **même** clé |

## Exemple

```typescript
// src/api/subscription.ts
initialize: (planCode: PlanCode, idempotencyKey: string) =>
  api.post<ApiResponse<InitializeResponse>>(
    '/subscription/initialize',
    { plan_code: planCode },
    { headers: { 'Idempotency-Key': idempotencyKey } },
  ),
```

```typescript
// Composable : la clé vit le temps d'une tentative (plan + clic « Payer »).
let attempt: { planCode: PlanCode; key: string } | null = null;

async function pay(planCode: PlanCode) {
  if (attempt === null || attempt.planCode !== planCode) {
    attempt = { planCode, key: crypto.randomUUID() };
  }

  try {
    const { data } = await subscriptionApi.initialize(planCode, attempt.key);
    window.location.href = data.data.checkout_url;
  } catch (e) {
    const code = e.response?.data?.code;
    if (e.response?.status === 409 && code === 'idempotency_key_expired') {
      attempt = null; // le prochain clic générera une nouvelle clé
    }
    throw e;
  }
}
```

Désactiver le bouton pendant la requête reste recommandé ; la clé couvre les cas que l'UI ne peut pas
empêcher (retry réseau, requête rejouée).

## Réponses

| Code | Signification |
|---|---|
| 200 | Paiement initialisé. `data.idempotent_replay` vaut `true` (et en-tête `Idempotent-Replayed: true`) si la réponse est un rejeu : même `checkout_url`, même `reference`, aucun nouveau paiement créé. |
| 422 | En-tête absent/invalide (`errors.idempotency_key`), plan invalide, ou clé déjà utilisée pour un **autre plan**. |
| 409 `code: payment_already_completed` | Cette tentative est déjà payée : rafraîchir l'abonnement, ne pas repayer. |
| 409 `code: idempotency_key_expired` | Tentative échouée ou lien expiré : générer une nouvelle clé. |
| 409 `code: request_in_progress` | Une requête identique est en cours : réessayer avec la même clé. |
| 409 `code: too_many_pending_payments` | Le salon a déjà 3 liens de paiement en attente non expirés : aucun nouveau paiement n'est créé. Inviter l'utilisateur à terminer un paiement ouvert ou à réessayer après leur expiration (30 min par défaut). |
| 429 | Limite de débit dépassée : 5 initialisations par minute et 20 par heure pour un salon, 5 par minute pour un utilisateur. Respecter l'en-tête `Retry-After`. |
| 502 | Message générique « Service de paiement momentanément indisponible. » ; aucun paiement créé, la même clé peut être réutilisée. |

## Limites anti-abus

Elles protègent le compte marchand GeniusPay, partagé par tous les salons :

- débit : 5 requêtes `initialize` par minute et 20 par heure par salon, 5 par minute par utilisateur (429) ;
- au plus 3 paiements `pending` avec lien non expiré par salon (409 `too_many_pending_payments`).

Un rejeu avec une clé déjà utilisée compte dans la limite de débit mais pas dans le plafond de paiements en attente.
