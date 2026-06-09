# Paystack — Paiements abonnement salon

> Intégration Paystack (mode test) pour les abonnements mensuels Basic / Pro.

**Note :** Le package `unicodeveloper/laravel-paystack` n'est pas compatible Laravel 12. Un client HTTP natif est utilisé (`App\Services\Paystack\PaystackService`).

---

## Plans

| Code | Nom | Prix |
|------|-----|------|
| `basic` | Basic | 5 000 XOF |
| `pro` | Pro | 15 000 XOF |

---

## Flux

```
Gérant choisit plan
  → POST /api/v1/subscription/initialize { plan_code }
  → Laravel crée payment_transactions (pending) + appelle Paystack
  → Réponse: authorization_url
  → window.location.href = authorization_url
  → Gérant paie (OM, Wave, carte, MTN MoMo via Paystack)
  → Paystack redirige GET /api/v1/payment/callback?reference=...
  → Laravel vérifie via API Paystack + active abonnement
  → Redirect → {FRONTEND_URL}/settings?payment=success|failed
```

---

## Variables `.env`

```env
PAYSTACK_PUBLIC_KEY=pk_test_xxx
PAYSTACK_SECRET_KEY=sk_test_xxx
PAYSTACK_PAYMENT_URL=https://api.paystack.co
PAYSTACK_MERCHANT_EMAIL=admin@salono.ci
FRONTEND_URL=http://localhost:5173
APP_URL=http://localhost:8000

SIMULATE_SUBSCRIPTION_PAYMENTS=false
```

`APP_URL` doit être accessible par Paystack pour le callback (ngrok en local).

---

## 1. Initialiser le paiement

```
POST /api/v1/subscription/initialize
Authorization: Bearer {token}
Role: admin
```

### Body

```json
{
  "plan_code": "pro"
}
```

### Réponse 200

```json
{
  "success": true,
  "message": "Paiement initialisé avec succès.",
  "data": {
    "authorization_url": "https://checkout.paystack.com/xxxxx",
    "access_code": "xxxxx",
    "reference": "SAL-XXXXXXXX",
    "amount": 15000,
    "currency": "XOF",
    "plan": {
      "code": "pro",
      "name": "Pro",
      "price_fcfa": 15000
    }
  }
}
```

### Front

```typescript
const { data } = await api.post('/subscription/initialize', { plan_code: 'pro' });
window.location.href = data.data.authorization_url;
```

### Erreurs

| HTTP | Cas |
|------|-----|
| 422 | Plan invalide / abonnement déjà actif |
| 502 | Erreur Paystack |

---

## 2. Callback Paystack

```
GET /api/v1/payment/callback?reference=SAL-XXXXXXXX
```

Route **publique** (pas de JWT). Paystack redirige le navigateur ici.

Le backend :
1. Charge `payment_transactions` par `reference`
2. Vérifie la transaction via `GET https://api.paystack.co/transaction/verify/{reference}`
3. Contrôle montant + devise
4. Met à jour **la même** ligne `subscriptions` (pas de doublon)
5. Crée `subscription_payments` (historique facturation)
6. Marque `payment_transactions.status = success`
7. Redirect :

```
{FRONTEND_URL}/settings?payment=success&reference=SAL-XXX
{FRONTEND_URL}/settings?payment=failed
```

### Front — page `/settings`

```typescript
const params = new URLSearchParams(window.location.search);
if (params.get('payment') === 'success') {
  toast.success('Abonnement activé !');
  await refreshSubscription();
}
if (params.get('payment') === 'failed') {
  toast.error('Paiement échoué. Réessayez.');
}
```

---

## 3. Règles métier (identiques simulate)

| Situation | Comportement |
|-----------|--------------|
| Trial → paiement | Active le plan choisi |
| Basic actif → repayer Basic | **Refusé** (422) si > 7j avant expiration |
| Basic actif → payer Pro | **OK** — upgrade sur même subscription |
| Renouvellement | OK dans les 7 derniers jours avant `ends_at` |

---

## Tables

### `payment_transactions` (flux Paystack)

| Colonne | Description |
|---------|-------------|
| `reference` | Référence unique Salono (`SAL-...`) |
| `paystack_ref` | Référence Paystack |
| `status` | `pending` / `success` / `failed` |
| `amount` | Montant XOF |
| `currency` | `XOF` |

### `subscription_payments` (historique comptable)

Créé après vérification réussie — visible côté super admin.

---

## Sécurité

- Vérification **serveur** via API Paystack (jamais faire confiance au seul redirect)
- Comparaison montant/devise avec la transaction locale
- Callback idempotent (rejeu = redirect success si déjà `success`)
- `reference` unique en base
- Metadata Paystack : `salon_id`, `plan_id`, `payment_transaction_id`

---

## Dev local

```bash
php artisan migrate
php artisan db:seed --class=PlanSeeder

# Exposer le back pour callback Paystack
ngrok http 8000
# Mettre APP_URL=https://xxx.ngrok.io dans .env
```

Cartes test Paystack : voir [Paystack test cards](https://paystack.com/docs/payments/test-payments).

---

## Migration depuis simulate

1. `SIMULATE_SUBSCRIPTION_PAYMENTS=false`
2. Remplacer l'appel `POST /subscription/simulate-payment` par `initialize` + redirect
3. Gérer `?payment=success|failed` sur `/settings`
