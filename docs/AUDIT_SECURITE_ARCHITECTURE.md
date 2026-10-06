# Audit sécurité, architecture et produit — Salono (herycia-backend)

- **Date** : 1er octobre 2026
- **Périmètre** : dépôt `herycia-backend`, commit `9b07cae` (Laravel 12, JWT tymon, PostgreSQL, Redis, Paystack, Twilio)
- **Méthode** : relecture complète des routes, middlewares, actions, services, migrations et configuration ; scénarios d'attaque (pentest « boîte blanche ») déroulés sur le code ; exécution de la suite de tests (`php artisan test`) et de `composer audit`.
- **Hors périmètre** : infrastructure réelle (serveur web, Nginx, MinIO, Redis de production), frontend Vue, tests dynamiques contre un environnement déployé.

> Ce rapport ne contient pas de code. Chaque constat donne : la localisation, le scénario d'exploitation et la correction attendue.

---

## 1. Synthèse

| Gravité | Nombre | Exemples |
|---|---|---|
| 🔴 Critique | 5 | Secrets réels commités, réactivation d'un compte bloqué via OTP, abonnement gratuit via « simulate-payment », suppression automatique des comptes désactivés, absence totale de limitation de débit sur l'authentification |
| 🟠 Haute | 11 | Upload de logo → exécution de code possible, JWT non révoqués, suspension de salon sans effet, failles Paystack (course, pas de webhook), spam de réservation et SMS, fuite des messages d'exception, 47 vulnérabilités de dépendances |
| 🟡 Moyenne | 10 | Contrôle d'accès par rôle trop grossier, règles métier des paiements, suppression physique des données financières, proxies et CORS |
| 🔵 Architecture / dette | 11 | Code hérité d'un autre projet, doublons d'actions, gestion d'erreurs, absence de Policies et de verrous |
| 🟣 Produit | 8 | Essais illimités, admins sans email bloqués, pas d'annulation client, conformité données personnelles |

**État des tests** : 129 tests, 123 réussis, 4 échoués, 2 ignorés. Les 4 échecs (`AuthMeTest`, `SalonLoginTest` ×2, `SubscriptionMiddlewareTest`) viennent de l'extension PHP `imagick`, requise pour générer le QR code. Faute d'`imagick`, `/auth/login` et `/auth/me` répondent **401** au lieu de 500 (voir H6 et A8).

**Les 5 actions à faire avant toute mise en production :**
1. Révoquer le mot de passe d'application Gmail, changer le mot de passe PostgreSQL et l'`APP_KEY`, puis purger l'historique git (C1).
2. Supprimer ou neutraliser `resend-otp` / `verify-otp`, qui permettent de réactiver un compte bloqué (C2).
3. Passer `simulate_subscription_payments` à `false` par défaut et retirer la route en production (C3).
4. Corriger la commande `users:cleanup-unverified`, qui supprime les employés désactivés et les comptes bloqués (C4).
5. Ajouter une limitation de débit sur tous les endpoints publics et d'authentification (C5).

---

## 2. Vulnérabilités critiques 🔴

### C1 — Secrets réels commités dans `.env.example`
- **Où** : `.env.example` (lignes `APP_KEY`, `DB_PASSWORD`, `MAIL_USERNAME`/`MAIL_PASSWORD`, `SUPER_ADMIN_PASSWORD`). Les mêmes valeurs par défaut sont reprises dans `config/salono.php` (`super_admin.password = SuperAdmin123!`).
- **Constat** : le fichier contient un mot de passe d'application Gmail qui semble valide, un mot de passe PostgreSQL d'apparence réelle et une `APP_KEY`. Le mot de passe du super admin est prévisible. Le script `composer setup` copie `.env.example` vers `.env` : toute installation hérite donc de ces secrets.
- **Exploitation** : toute personne ayant accès au dépôt (fuite, ancien prestataire, dépôt rendu public) peut envoyer des emails au nom de la plateforme (phishing d'OTP crédible), et tenter le mot de passe DB et `SuperAdmin123!` sur la production. L'`APP_KEY` permet de déchiffrer ou forger les données chiffrées par Laravel.
- **Correction** :
  1. Révoquer **immédiatement** le mot de passe d'application Google et en générer un nouveau, stocké hors du dépôt.
  2. Changer le mot de passe PostgreSQL de tous les environnements qui l'ont utilisé, puis régénérer l'`APP_KEY` et le `JWT_SECRET`.
  3. Remplacer toutes les valeurs de `.env.example` par des valeurs factices et vides.
  4. Purger l'historique git (git-filter-repo ou BFG) et forcer la reclonage des postes.
  5. Supprimer les valeurs par défaut du super admin dans `config/salono.php` : le seeder doit refuser de s'exécuter sans mot de passe fourni et robuste.
  6. Activer le secret scanning GitHub et un hook pre-commit (gitleaks).

### C2 — Un compte bloqué ou désactivé peut se réactiver seul (contournement du blocage)
- **Où** : `ResendOtpAction`, `VerifyOtpAction`, routes publiques `POST /api/v1/auth/resend-otp` et `/verify-otp`.
- **Constat** : ces deux actions considèrent tout utilisateur `is_active = false` comme une « inscription en attente ». Depuis la refonte, l'inscription crée pourtant directement des comptes actifs. Les seuls comptes inactifs sont donc les **employés désactivés par le gérant** et les **utilisateurs bloqués par le super admin**.
- **Exploitation (pentest)** :
  1. Un employé licencié, désactivé par le gérant, appelle `resend-otp` avec son email.
  2. Il reçoit un OTP par email (et par SMS si activé).
  3. Il appelle `verify-otp` : le compte repasse à `is_active = true` et l'API lui renvoie **un JWT valide**.
  4. Il accède de nouveau aux clients, aux rendez-vous et aux paiements du salon. Un compte bloqué par le super admin peut faire la même chose.
- **Fuite associée** : la réponse de `resend-otp` renvoie l'**email et le téléphone** du compte ciblé à n'importe quel appelant anonyme, ce qui permet d'énumérer les comptes désactivés.
- **Correction** : supprimer ces deux routes, puisque le flux d'inscription par OTP n'est plus utilisé. Si une vérification de téléphone est réintroduite, utiliser un état dédié (`pending_verification` ou `phone_verified_at` nul) **distinct** de `is_active`, et ne jamais renvoyer de données personnelles dans la réponse.

### C3 — Abonnement payant gratuit via `simulate-payment`
- **Où** : `config/salono.php` (`simulate_subscription_payments` vaut **true par défaut**), `SimulateSubscriptionPaymentAction`, route `POST /api/v1/subscription/simulate-payment`.
- **Constat** : si la variable d'environnement est absente, la simulation est active. N'importe quel gérant authentifié peut alors activer ou renouveler n'importe quel plan (y compris le plus cher) sans payer, avec un `plan_code` arbitraire. Une ligne `subscription_payments` « Paid » est même créée, ce qui fausse le chiffre d'affaires du back-office.
- **Exploitation** : un appel répété fait avancer `ends_at` d'un mois à chaque fois (pendant la fenêtre de renouvellement), ce qui donne un abonnement illimité.
- **Correction** : mettre la valeur par défaut à `false`. N'enregistrer la route que lorsque l'environnement vaut `local` ou `testing`. Marquer ces paiements avec une méthode `simulation`, exclue des statistiques.

### C4 — La tâche planifiée supprime les employés désactivés et les comptes bloqués
- **Où** : `CleanupUnverifiedUsersCommand`, planifiée toutes les heures dans `routes/console.php`.
- **Constat** : la commande supprime **physiquement** tout utilisateur `is_active = false` créé il y a plus de 24 h. Cela vise exactement les employés désactivés (fonction « désactiver » du gérant) et les utilisateurs bloqués par le super admin.
- **Conséquences** :
  - un employé sans rendez-vous disparaît définitivement (impossible à réactiver, historique perdu) ;
  - un employé avec rendez-vous provoque une violation de clé étrangère (`appointments.user_id` en `restrictOnDelete`) : la requête de masse échoue, **toutes les heures**, et la purge ne fonctionne plus pour personne ;
  - la décision de blocage du super admin est effacée.
- **Correction** : supprimer cette commande (elle n'a plus de raison d'être, cf. C2) ou la restreindre à un état explicite « inscription non vérifiée ». Pour les désactivations, utiliser une suppression logique (soft delete), jamais une suppression physique.

### C5 — Aucune limitation de débit sur l'authentification et les endpoints publics
- **Où** : `routes/api/v1.php` et `routes/api.php`. Seul `/auth/register` a un `throttle`. Le groupe API n'a pas non plus de limiteur par défaut.
- **Endpoints exposés sans limite** : `/auth/login`, `/auth/forgot-password`, `/auth/verify-reset-code`, `/auth/reset-password`, `/auth/verify-otp`, `/auth/resend-otp`, `GET` et `POST /booking/{slug}`, `GET /rdv/{token}`, `/payment/callback`.
- **Exploitation** :
  - **Bourrage d'identifiants / force brute sur `/login`** : les identifiants sont des numéros ivoiriens (espace prévisible de 10 chiffres), le mot de passe minimal fait 8 caractères sans règle de complexité, et rien ne bloque les essais.
  - **OTP de réinitialisation** : 5 essais par code, mais le compteur `otp:registration:attempts:{email}` est **partagé** avec l'inscription et remis à zéro par d'autres flux. `forgot-password` n'a qu'une limite de 3 envois par 5 minutes : environ 36 envois par heure, donc 180 essais par heure sur un espace de 10⁶. La probabilité est faible mais non nulle, et l'attaque est automatisable sur de nombreux comptes.
  - **Spam d'emails et de SMS** via `forgot-password`, `resend-otp` et la réservation publique (voir H5).
- **Correction** : définir des limiteurs nommés, par IP **et** par identifiant :
  - login : 5 par minute et 20 par heure par identifiant, avec blocage progressif ;
  - OTP : 5 essais par code puis invalidation du code ;
  - forgot-password : 3 par heure ;
  - booking : 10 par heure par IP et 3 par jour par numéro.

  Séparer les compteurs par flux, comparer les codes en temps constant et ajouter un captcha (Turnstile ou hCaptcha) sur la réservation publique.

---

## 3. Vulnérabilités hautes 🟠

### H1 — Upload de logo : extension contrôlée par le client (risque de webshell)
- **Où** : `SalonLogoStorageService::upload` (même logique dans `MinioStorageService`).
- **Constat** : le nom du fichier stocké prend l'extension **fournie par le client** (`getClientOriginalExtension`) sur le disque `public`. Les règles `image` et `mimes` valident le **contenu**, pas le nom : un polyglotte JPEG/PHP nommé `logo.php` passe la validation et est stocké sous `logos/<uuid>.php`, sous `public/storage`.
- **Impact** : si le serveur web exécute PHP sous `/storage` (configuration par défaut de nombreux vhosts Nginx/Apache avec `try_files` vers PHP), cela permet une **exécution de code à distance** par n'importe quel gérant (compte gratuit en essai).
- **Correction** : générer l'extension à partir du type MIME détecté côté serveur (ou nom haché fourni par le framework). Ré-encoder l'image (suppression des métadonnées et des charges utiles). Interdire l'exécution PHP sur `/storage` au niveau du serveur web. Idéalement, stocker sur MinIO/S3 avec des URL signées.

### H2 — Les JWT ne sont jamais révoqués et l'état du compte n'est pas vérifié à chaque requête
- **Où** : `JwtMiddleware`, `BlockAdminUserAction`, `DeactivateSalonEmployeeAction`, `ResetPasswordAction`, `ResetAdminUserPasswordAction`.
- **Constat** : le middleware vérifie seulement la signature du jeton. Un utilisateur bloqué ou désactivé, ou dont le mot de passe a été réinitialisé (y compris après une compromission), **garde un accès complet** jusqu'à l'expiration du jeton (60 min par défaut, configurable).
- **Correction** : dans le middleware, refuser si `is_active = false` ou si le salon est inactif ou suspendu (cf. H3). Ajouter un claim de version de jeton (`token_version` ou `password_changed_at`) incrémenté à chaque blocage ou changement de mot de passe, et comparé à chaque requête. Le blacklist JWT est activé mais n'est utilisé qu'au logout.

### H3 — La suspension et la désactivation d'un salon n'ont aucun effet
- **Où** : `suspended_at` est écrit par le super admin mais **n'est lu nulle part** (hors filtres d'administration). `salons.is_active` n'est vérifié que par la réservation publique.
- **Impact** : un salon suspendu (impayé, fraude, abus) continue d'utiliser tout le back-office et de recevoir des réservations publiques. Un salon désactivé garde aussi le back-office.
- **Correction** : centraliser la règle « salon utilisable » (actif, non suspendu, abonnement valide) dans un seul service, appliqué par le middleware d'abonnement, par la réservation publique et par le login. Ajouter des tests.

### H4 — Paiement d'abonnement Paystack : course critique, pas de webhook, paiements encaissés mais rejetés
- **Où** : `HandlePaystackCallbackAction`, `SubscriptionBillingService::activateFromPayment`, `PaystackService`.
- **Constats** :
  1. **Pas de webhook signé** (`charge.success` vérifié par HMAC-SHA512 avec la clé secrète). L'activation dépend uniquement de la redirection navigateur `GET /payment/callback` : si l'utilisateur ferme l'onglet ou perd sa connexion, **le salon a payé mais n'est pas activé**.
  2. **Condition de course** : le statut `Success` est lu sans verrou. Deux appels simultanés au callback (double clic, rechargement, ou attaquant connaissant la référence, visible dans l'URL de retour) vérifient tous deux le paiement et appellent `activateFromPayment`. En renouvellement, le second appel **prolonge encore d'un mois** pour un seul paiement.
  3. **Paiement encaissé puis refusé** : `assertCanSubscribe` est réévalué **après** l'encaissement. Si deux transactions ont été initialisées (deux onglets), la seconde, pourtant payée, lève `SubscriptionAlreadyActive` et passe en `Failed`, sans remboursement ni alerte.
  4. Une erreur réseau temporaire lors de `verify` marque la transaction `Failed` (le statut est trompeur).
  5. Les appels HTTP n'ont ni timeout ni retry, et le secret est envoyé à une URL configurable (`PAYSTACK_PAYMENT_URL`).
- **Correction** : implémenter le webhook signé comme source de vérité, le callback ne servant qu'à l'affichage. Verrouiller la transaction (`SELECT … FOR UPDATE`) et la rendre idempotente avec une contrainte unique sur la référence dans `subscription_payments`. Créditer le paiement même si les règles ont changé, puis traiter l'écart (crédit de jours ou remboursement). Ajouter des timeouts. Distinguer « échec de vérification » de « paiement refusé ».

### H5 — Réservation publique : spam, fraude SMS, écrasement de données, double réservation
- **Où** : `CreatePublicBookingAction`, `AppointmentObserver`, routes `/booking/{slug}`.
- **Constats** :
  - **Aucune limite et aucun captcha** : un script peut remplir l'agenda de tous les coiffeurs d'un salon concurrent (déni de service métier). Il peut aussi générer des milliers de SMS Twilio vers des numéros arbitraires (« SMS pumping », coût direct pour la plateforme).
  - **Écrasement du nom d'un client existant** : toute personne qui connaît le numéro d'un client peut renommer sa fiche dans le CRM du salon (atteinte à l'intégrité, et contenu injecté ensuite dans les SMS).
  - **Double réservation** : la disponibilité est vérifiée hors transaction, sans verrou ni contrainte. Deux requêtes simultanées obtiennent le même créneau.
  - Pas de limite d'horizon (réservations en 2099 possibles), pas de contrôle des horaires d'ouverture à la création, statut `Confirmed` attribué d'office.
- **Correction** : limiteur et captcha. Ne jamais modifier une fiche client existante depuis le public (créer une demande ou ignorer le nom). Verrou pessimiste par coiffeur ou contrainte d'exclusion PostgreSQL sur les plages horaires. Horizon maximal configurable. Contrôle des horaires. Statut `Pending` si le salon veut valider. Plafond de SMS par salon et par jour.

### H6 — Les messages d'exception internes sont renvoyés aux clients
- **Où** : quasiment tous les contrôleurs (`'message' => $e->getMessage()`), y compris en HTTP 500 et sur les routes publiques. `JwtMiddleware` concatène aussi le message de l'exception. `.env.example` a `APP_DEBUG=true`.
- **Impact** : fuite de messages SQL (noms de tables et de colonnes, contraintes), de chemins, d'erreurs Paystack ou Twilio, d'erreurs de configuration (« Clé secrète Paystack non configurée », « imagick extension »). Les codes HTTP sont aussi incohérents : une erreur interne sur `/auth/me` ou `/auth/login` renvoie **401**, ce qui déconnecte le frontend à tort et masque la panne.
- **Correction** : gestionnaire d'exceptions global avec des exceptions métier typées (code et message traduits). Message générique et identifiant de corrélation pour toute erreur inattendue. `APP_DEBUG=false` en dehors du poste de développement.

### H7 — Le lien de suivi public expose les notes internes du rendez-vous
- **Où** : `PublicAppointmentTrackingResource` (champ `notes`).
- **Impact** : les notes saisies par le salon (remarques sur le client, impayés, etc.) sont lisibles par quiconque possède le lien SMS (lien transférable, prévisualisé par les messageries).
- **Correction** : retirer `notes` de la ressource publique. Si besoin, prévoir un champ distinct « message au client ».

### H8 — Journalisation de secrets et de données personnelles
- **Où** : `LoginAction` journalise le **JWT en clair** à chaque connexion. Plusieurs actions journalisent emails et téléphones (`ForgotPasswordAction`, `VerifyOtpAction`, `UserNotification`, etc.).
- **Impact** : toute personne ayant accès aux journaux (ou à leur agrégateur) peut usurper une session. Les données personnelles sont conservées sans politique de rétention.
- **Correction** : ne jamais journaliser de jetons, OTP ou mots de passe. Masquer emails et téléphones. Définir une rétention des journaux.

### H9 — Énumération des comptes
- **Où** :
  - `ForgotPasswordRequest` et `ResetPasswordRequest` utilisent la règle `exists:users,email`, avec le message « Aucun compte n'est associé à cet email ». L'action renvoie aussi des messages différents selon l'existence du compte.
  - `/login` distingue « Identifiants invalides » de « Votre compte est désactivé ».
  - `resend-otp` renvoie les coordonnées du compte (C2).
- **Correction** : réponse identique et délai constant, que le compte existe ou non. Retirer les règles `exists` des flux d'authentification.

### H10 — Documentation Swagger publique et cache de modèles sérialisés
- **Où** : `config/l5-swagger.php` (aucun middleware sur `api/documentation`), `storage/api-docs/api-docs.json` commité. `UserRepository::findById` met en cache Redis le modèle `User` complet (hash du mot de passe compris) pendant 1 h, et `.env.example` n'a pas de mot de passe Redis.
- **Impact** : cartographie complète de l'API offerte à un attaquant. Le cache devient **périmé** après `ResetPasswordAction`, qui modifie le modèle sans invalider le cache. Un Redis non authentifié permet de lire les hash, et de modifier les objets sérialisés, ce qui fait courir un risque de désérialisation.
- **Correction** : désactiver Swagger en production ou le protéger (authentification HTTP ou rôle super admin). Ne pas mettre d'entités sensibles en cache, ou seulement des données minimales. Centraliser l'invalidation. Activer un mot de passe Redis et TLS, et le réseau privé uniquement.

### H11 — Dépendances vulnérables
- **Constat (`composer audit`)** : **47 avis de sécurité sur 16 paquets**, dont :
  - `mtdowling/jmespath.php` (critique) ;
  - `laravel/framework` (haut et moyen) ;
  - `symfony/http-kernel` (haut) ;
  - `guzzlehttp/guzzle` (1 haut, 8 moyens) ;
  - `league/commonmark` (9 hauts) ;
  - `google/protobuf` (haut) ;
  - `aws/aws-sdk-php` (haut) ;
  - `symfony/mime` (haut).
- `league/flysystem-aws-s3-v3` est figé à la version exacte `3.0`, d'où des conflits de classes au chargement automatique.
- `kreait/firebase-php` (et sa chaîne protobuf/grpc), `laravel/sanctum` et `twilio/sdk` alourdissent la surface d'attaque ; Firebase et Sanctum ne sont pas utilisés.
- **Correction** : mettre à jour (`composer update` contrôlé), retirer les paquets inutilisés, corriger la contrainte flysystem en `^3.0`, ajouter `composer audit` et Dependabot à la CI.

---

## 4. Vulnérabilités moyennes 🟡

### M1 — Contrôle d'accès par rôle trop grossier
- Les coiffeurs (`stylist`) et réceptionnistes accèdent à **toute** la base clients (noms, téléphones, historique de paiements). Ils peuvent aussi lister, modifier le statut et **annuler n'importe quel rendez-vous**, ainsi que voir le tableau de bord du salon (chiffre d'affaires dans `overview`).
- Le rôle `manager` existe mais n'a aucun droit spécifique.
- Les FormRequest ont toutes `authorize(): true`, et le projet n'a aucune Policy Laravel.
- **Correction** : définir une matrice des droits (lecture, écriture et périmètre « mes rendez-vous » ou « tout le salon ») et l'implémenter avec des Policies et Gates testées.

### M2 — Politique de mot de passe incohérente et inscription sans vérification
- Inscription et création d'employé : 8 caractères minimum, sans autre règle. Réinitialisation : complexité exigée.
- L'inscription active immédiatement le compte **sans vérifier la possession du numéro** : n'importe qui peut réserver le numéro d'un tiers et créer des salons à volonté (essais gratuits illimités, cf. P1).
- **Correction** : une règle unique (longueur 10 ou plus, refus des mots de passe compromis). Vérification du téléphone par OTP (avec un état dédié, cf. C2) avant l'activation de l'essai.

### M3 — Règles métier des paiements en salon et des rendez-vous
- `paid_at` est libre : antidatage ou paiements futurs possibles, ce qui fausse le chiffre d'affaires.
- Le montant n'est pas comparé au prix de la prestation (pas d'alerte de remise).
- Le contrôle « déjà payé » se fait hors verrou, ce qui permet un double paiement concurrent.
- Les transitions de statut sont libres : Completed → Pending → Completed **incrémente `total_visits` à chaque passage**.
- La création de rendez-vous en back-office ne vérifie ni les conflits d'agenda ni la date (rendez-vous dans le passé possibles).
- **Correction** : machine à états explicite, contraintes d'unicité (un seul paiement validé par rendez-vous), verrous, bornes sur les dates, réutilisation de `StaffAvailabilityService` côté back-office.

### M4 — Suppression physique d'un salon et des données financières
- `DELETE /admin/salons/{id}` supprime le salon en cascade : clients, rendez-vous, **paiements, `subscription_payments`, `payment_transactions`**.
- **Impact** : perte des pièces comptables (obligation de conservation), plus de traçabilité Paystack, plus de recours en cas de litige.
- **Correction** : suppression logique et archivage. Les tables financières passent en `restrictOnDelete`, avec anonymisation des données personnelles plutôt que suppression.

### M5 — SMS envoyés avant la validation de la transaction, et rappels non maîtrisés
- `AppointmentObserver::created` envoie les jobs depuis l'intérieur de la transaction, sans `afterCommit` : un SMS peut partir pour un rendez-vous ensuite annulé par un rollback.
- Le rappel est planifié une seule fois à la création : rien ne le gère en cas de changement d'horaire. Le job relit le statut mais pas l'heure.
- Le nom du client, saisi publiquement, est injecté tel quel dans le SMS (contenu trompeur possible).
- **Correction** : envoyer les jobs après le commit, rendre le rappel idempotent et recalculé à chaque modification, et filtrer et tronquer le nom.

### M6 — Proxies de confiance non configurés
- Aucun `trustProxies` n'est configuré dans `bootstrap/app.php`. Derrière un load balancer ou Cloudflare, `request()->ip()` vaut l'IP du proxy.
- **Impact** : la limite d'inscription (5 par heure et par IP, doublée dans `SalonRegisterAction` et le `throttle`) devient **globale**. Cinq inscriptions par heure suffisent alors à bloquer toute la plateforme. Les futurs limiteurs ont le même problème.
- **Correction** : configurer les proxies de confiance, et non `*` sans réflexion.

### M7 — CORS par défaut
- Il n'y a pas de `config/cors.php` : le comportement par défaut de Laravel autorise toutes les origines sur `api/*`. Le risque est limité par l'usage de jetons Bearer, mais la configuration doit être explicite.
- **Correction** : restreindre aux domaines du frontend (`salono.ci`, back-office).

### M8 — Super administration
- Un super admin peut bloquer un autre super admin ou réinitialiser son mot de passe (le garde-fou de `BlockAdminUserAction` ne peut jamais se déclencher).
- Le mot de passe temporaire est renvoyé dans la réponse HTTP, sans obligation de le changer à la connexion suivante.
- Il n'y a ni double authentification (MFA) ni restriction d'IP pour les super admins.
- **Aucune action d'administration n'est auditée** : `AuditLogService` n'est appelé que dans `VerifyOtpAction`, qui est du code mort.
- **Correction** : MFA obligatoire, journal d'audit sur toute action d'administration (qui, quoi, avant/après, IP), changement de mot de passe forcé, protection du dernier super admin.

### M9 — Utilisation de `Redis::keys('*')`
- `RedisOtpService::getPendingUser` parcourt toutes les clés avec `KEYS` : l'opération est O(N) et bloque Redis. Elle est déclenchable par un appel public (code hérité).
- **Correction** : supprimer, ou indexer par téléphone.

### M10 — Normalisation des numéros
- `IvoryCoastPhone::normalize` **tronque silencieusement** au-delà de 13 chiffres et accepte des formes ambiguës (9 chiffres préfixés automatiquement). Deux saisies différentes peuvent donc tomber sur le même compte ou client.
- `PhoneNormalizer` n'est qu'un doublon.
- **Correction** : un seul composant, qui rejette au lieu de tronquer. Envisager libphonenumber.

---

## 5. Architecture et dette technique 🔵

| # | Constat | Correction recommandée |
|---|---|---|
| A1 | **Code hérité d'un autre projet** : `NotificationService` importe des modèles inexistants (`Agent`, `Incident`, `Communaute`, `Mission`, `DeviceToken`…). `FirebaseService` se trouve dans un dossier `Firebase 18-58-45-614` non conforme PSR-4 (ignoré par l'autoloader). Les disques MinIO portent les noms `profil_images` et `incidents`. Le texte parasite « #salle numérique de l'UNA » se trouve en fin de `VerifyEmailAction.php`. `bootstrap/app.php` importe `CheckAgentRole`, qui n'existe pas. `CheckUserRole` appelle `hasRole('user')`, ce qui lèverait une `ValueError`. La collection Postman contient des webhooks WhatsApp/Meta non implémentés. | Supprimer tout le code mort (liste en annexe B). Chaque classe restante doit être référencée et testée. |
| A2 | **Doublons d'actions** : façades `AdminSalonAction`, `AdminUserAction`… à côté d'actions unitaires `Admin/Salon/*`, dont plusieurs ne sont jamais appelées (`DeactivateAdminSalonAction`, `SuspendAdminSalonAction`, `ListAdminSalonsAction`, `CreateAdminPlanAction`). `GetAllUsersAction` et `GetUserAction` sont orphelines. | Choisir un seul motif (une action par cas d'usage) et supprimer l'autre. |
| A3 | **Gestion d'erreurs** : chaque contrôleur entoure son code d'un `try/catch (Exception)` générique, avec des codes HTTP arbitraires (400, 401, 404 ou 500 pour la même `RuntimeException`). | Exceptions métier typées + rendu centralisé dans `bootstrap/app.php`. Contrôleurs sans try/catch. |
| A4 | **Deux flux d'authentification superposés** : l'ancien flux OTP Redis (`pending_user:*`) et le nouveau (inscription directe). Le premier n'est plus cohérent et crée C2. `refresh()` existe dans le contrôleur mais **n'a pas de route** : les sessions expirent après 60 minutes sans renouvellement possible. | Un seul flux documenté. Route de rafraîchissement (ou refresh token rotatif stocké en cookie httpOnly pour le back-office web). |
| A5 | **Isolation multi-tenant manuelle** : chaque action doit penser à appeler `SalonContextService`. Le code actuel est correct sur ce point, sans IDOR inter-salons constatée, mais fragile : un oubli suffit pour créer une fuite. | Global scope `salon_id` sur les modèles du tenant, Policies, et tests automatisés « un salon A ne voit jamais B » sur chaque endpoint. |
| A6 | **Aucun verrou ni contrainte de concurrence** dans tout le code (aucun `lockForUpdate`) : paiements, créneaux et callbacks sont exposés aux courses. | Verrous pessimistes sur les opérations financières et d'agenda, contraintes d'unicité et d'exclusion en base. |
| A7 | **Le cache n'est appliqué qu'à `UserRepository::findById`**, avec une invalidation partielle (cf. H10). | Retirer ce cache ou l'encapsuler correctement. |
| A8 | **Dépendance cachée à `imagick`** pour le QR code : faute d'extension, la connexion et `/me` échouent (4 tests rouges). | Repli sur le rendu SVG/GD, ou extension documentée et vérifiée au démarrage. Le QR ne doit pas être généré dans la réponse de login. |
| A9 | **Logique de facturation dispersée** : prix figé à l'initialisation, plan relu au callback, règles dans trois classes. | Un agrégat « Facturation » unique avec des tests de bout en bout (initialisation → webhook → activation). |
| A10 | **Documentation éparpillée** : six fichiers `.md` à la racine, et `.gitignore` exclut `backend_spec_dispositif_securite.md`. | Regrouper sous `docs/` et versionner la spécification de sécurité. |
| A11 | **Tests** : bonne base de tests fonctionnels (123 réussis), mais aucun test sur les sujets de sécurité : compte bloqué, limitation de débit, isolation inter-tenant systématique, courses de paiement, suspension. Pas de CI visible. | Ajouter ces tests et une CI (tests, Pint, `composer audit`, analyse statique Larastan niveau 6+). |

---

## 6. Problèmes produit 🟣

| # | Constat | Recommandation |
|---|---|---|
| P1 | **Essais gratuits illimités** : un numéro non vérifié suffit pour créer un nouveau salon et obtenir 7 jours d'essai, sans limite par appareil ni par personne. | Vérification par OTP du téléphone, essai unique par numéro (et par numéro WhatsApp), détection des doublons. |
| P2 | **Un gérant sans email ne peut pas récupérer son compte** : l'inscription ne demande qu'un téléphone, mais « mot de passe oublié » fonctionne uniquement par email. | Réinitialisation par SMS/WhatsApp, ou email obligatoire et vérifié. |
| P3 | **Paiement Paystack non activé si l'utilisateur ferme le navigateur** (pas de webhook), et paiements encaissés mais refusés (H4). Impact direct sur la confiance et sur le support. | Webhook, page « paiement en cours de vérification », réconciliation planifiée des transactions en attente. |
| P4 | **Changement de plan sans prorata** : une montée de gamme en cours de période fait perdre les jours restants, et la descente est immédiate. | Définir la règle (prorata, crédit de jours ou changement à l'échéance) et l'afficher avant le paiement. |
| P5 | **Suspension de salon inopérante** (H3) : le super admin croit avoir agi alors que rien ne change. | Voir H3. Ajouter un message explicite côté salon (« compte suspendu, contactez… »). |
| P6 | **Le client ne peut ni annuler ni reporter** depuis son lien de suivi. Les réservations sont confirmées d'office, ce qui produit des absences non gérées. | Annulation et report via le lien (avec un délai minimum), option « validation manuelle » par salon. |
| P7 | **Conformité des données personnelles** (loi ivoirienne n° 2013-450, ARTCI) : le CRM stocke téléphones et historiques. Il manque le consentement aux SMS, la possibilité de désinscription (STOP), une durée de conservation, l'export et la suppression à la demande, ainsi que des journaux d'accès. | Politique de confidentialité, mention et consentement à la réservation, purge et anonymisation planifiées, désinscription SMS. |
| P8 | **Le chiffre d'affaires du back-office est faussé** par les paiements simulés (C3) et par l'antidatage libre (M3). | Exclure les simulations et verrouiller les dates. |

---

## 7. Plan de correction priorisé

### Phase 0 — Immédiat (avant tout déploiement, 1 à 2 jours)
1. C1 — rotation des secrets et purge de l'historique.
2. C2 — retrait de `resend-otp` et `verify-otp`.
3. C3 — simulation désactivée par défaut et route réservée au développement.
4. C4 — désactivation de `users:cleanup-unverified`.
5. H1 — extension dérivée du contenu et exécution PHP interdite sur `/storage`.
6. H8 — suppression du log du JWT.
7. H7 — retrait des `notes` de la ressource publique.

### Phase 1 — Sécurité applicative (1 à 2 semaines)
- C5 et M6 : limiteurs, proxies de confiance, captcha sur la réservation.
- H2 et H3 : vérification de l'état du compte et du salon à chaque requête, versionnement des jetons.
- H6 et A3 : gestionnaire d'exceptions global, `APP_DEBUG=false`.
- H9 : réponses uniformes sur les flux d'authentification.
- H10 : Swagger protégé, cache utilisateur retiré, Redis authentifié.
- H11 : mise à jour des dépendances et `composer audit` en CI.
- M7 et M8 : CORS explicite, MFA et audit pour les super admins.

### Phase 2 — Paiements et intégrité des données (2 à 3 semaines)
- H4 et P3 : webhook Paystack signé, idempotence, verrous, réconciliation.
- H5 : verrous et contraintes d'agenda, protection de la fiche client, plafonds de SMS.
- M3, M4 et A6 : machine à états des rendez-vous et paiements, suppression logique, contraintes d'unicité.
- M5 : jobs après commit, rappels idempotents.

### Phase 3 — Architecture et produit (continu)
- M1 et A5 : matrice des rôles, Policies, global scope tenant, tests inter-tenant.
- A1, A2, A4, A7 à A11 : nettoyage du code hérité, unification du flux d'authentification, route de rafraîchissement, CI.
- P1, P2, P4, P6, P7 : vérification du téléphone, récupération par SMS, règles de changement de plan, annulation client, conformité ARTCI.

---

## Annexe A — Scénarios de pentest à rejouer après correction

| # | Scénario | Résultat attendu après correction |
|---|---|---|
| T1 | Désactiver un employé, puis appeler `resend-otp` et `verify-otp` avec son email | 404 (route supprimée), aucun jeton délivré |
| T2 | Bloquer un utilisateur connecté, puis réutiliser son JWT | 401 immédiat |
| T3 | Suspendre un salon, puis appeler `/api/v1/clients` avec le jeton du gérant et `GET /booking/{slug}` | 403 « salon suspendu » sur les deux |
| T4 | `POST /subscription/simulate-payment` en environnement production | 404 |
| T5 | Envoyer un JPEG polyglotte nommé `x.php` sur `/salon/logo` | Fichier stocké en `.jpg` (ou ré-encodé), jamais exécuté |
| T6 | 50 requêtes `/auth/login` en 1 minute sur le même numéro | 429 à partir du seuil |
| T7 | 2 appels simultanés à `/payment/callback?reference=…` sur un renouvellement | Une seule ligne `subscription_payments`, une seule prolongation |
| T8 | 2 réservations publiques simultanées sur le même coiffeur et le même créneau | Une réussite, une réponse 409 |
| T9 | Réservation publique avec le numéro d'un client existant et un autre nom | Le nom de la fiche client reste inchangé |
| T10 | `GET /rdv/{token}` | Aucune note interne dans la réponse |
| T11 | `forgot-password` avec un email inexistant | Même réponse et même temps de réponse qu'un email existant |
| T12 | Exécuter `users:cleanup-unverified` avec des employés désactivés | Aucun employé supprimé |
| T13 | Provoquer une erreur SQL (par exemple un identifiant mal formé sur une route) | Message générique avec identifiant de corrélation, sans détail SQL |
| T14 | Jeton du salon A sur chaque ressource `/{id}` du salon B (clients, rendez-vous, employés, services, paiements) | 404 partout (aucune fuite constatée aujourd'hui ; test à automatiser) |
| T15 | `GET /api/documentation` en production | 401 ou 404 |

## Annexe B — Fichiers candidats à la suppression (code mort ou hérité)

- `app/Services/Notification/NotificationService.php`
- `app/Services/Firebase 18-58-45-614/` (dossier entier)
- `app/Services/Storage/MinioStorageService.php` et les disques `minio_*` de `config/filesystems.php` (sauf si MinIO est adopté pour H1)
- `app/Actions/Auth/VerifyEmailAction.php`, `ResendVerificationEmailAction.php`, `VerifyOtpAction.php`, `ResendOtpAction.php`
- Les méthodes de `RedisOtpService` liées aux « pending users »
- `app/Http/Middleware/CheckUserRole.php` et l'import `CheckAgentRole` dans `bootstrap/app.php`
- `app/Actions/User/GetAllUsersAction.php`, `GetUserAction.php`
- `app/Actions/Admin/Salon/{Deactivate,Suspend,ListAdminSalons}Action.php`, `Admin/Plan/CreateAdminPlanAction.php` (ou, à l'inverse, les façades `Admin*Action`)
- `app/Console/Commands/CleanupUnverifiedUsersCommand.php` et sa planification
- Payloads Postman `webhook/meta-*` et variables `whatsapp_*`
- Paquets `kreait/firebase-php` et `laravel/sanctum` s'ils restent inutilisés

---

## 8. Statut des corrections (6 octobre 2026)

Corrections appliquées sur la branche `claude/wizardly-archimedes-7r04g6`. Suite de tests : **146 tests verts** (dont 18 nouveaux tests de sécurité dans `tests/Feature/Security/SecurityHardeningTest.php`). `composer audit` : **47 avis → 1** (faible, `firebase/php-jwt`, via `kreait/firebase-php` inutilisé).

### Deux défauts découverts pendant la correction

- **Le middleware JWT du projet ne s'exécutait jamais.** L'alias `jwt.auth` déclaré dans `bootstrap/app.php` était écrasé par celui du paquet `tymon/jwt-auth`. Toutes les routes utilisaient donc le middleware générique du paquet. Il porte désormais l'alias `jwt.verified`.
- **`/auth/refresh` ne pouvait pas fonctionner** : le jeton était passé à `JWTAuth::refresh()` comme paramètre `$forceForever`. La route est désormais déclarée et corrigée.

### Tableau de statut

| # | Statut | Ce qui a été fait / ce qui reste |
|---|---|---|
| C1 | ⚠️ Partiel | `.env.example` nettoyé ; plus de mot de passe super admin par défaut ; le seeder exige 12 caractères. **Reste à faire par vous** : révoquer le mot de passe d'application Gmail, changer le mot de passe PostgreSQL, purger l'historique git. |
| C2 | ✅ Corrigé | Routes `verify-otp` et `resend-otp` retirées. |
| C3 | ✅ Corrigé | Simulation désactivée par défaut et refusée en production. |
| C4 | ✅ Corrigé | Commande de purge désactivée et retirée du planificateur. |
| C5 | ✅ Corrigé | Limiteurs nommés : login, mot de passe, refresh, réservation, lecture publique, callback, API authentifiée. Réponse 429 en JSON. Captcha non ajouté (côté front). |
| H1 | ✅ Corrigé | Extension déduite du MIME réel ; `.htaccess` qui interdit les scripts dans `storage/app/public`. **Nginx** : voir la checklist. |
| H2 | ✅ Corrigé | Chaque requête vérifie `is_active` ; jetons révoqués après un changement de mot de passe (`users.password_changed_at`) ; contrôle aussi au refresh. |
| H3 | ✅ Corrigé | Salon suspendu, désactivé ou supprimé : connexion refusée et 403 sur toutes les routes. |
| H4 | ✅ Corrigé | Webhook signé `POST /api/v1/payment/webhook`, confirmation verrouillée et idempotente, paiement toujours crédité, timeouts, réconciliation toutes les 15 min (`paystack:reconcile`). |
| H5 | ⚠️ Partiel | Verrou et revérification du créneau, horizon de 90 jours, fiche client jamais renommée, plafond SMS par salon. **Non fait** (choix produit) : captcha, contrôle des horaires d'ouverture, statut `pending`. |
| H6 | ✅ Corrigé | `Controller::safeMessage()` : messages métier conservés, erreurs techniques masquées hors debug. |
| H7 | ✅ Corrigé | `notes` retiré du suivi public. |
| H8 | ⚠️ Partiel | JWT et identifiants retirés des logs d'authentification. Quelques logs contiennent encore des emails (code hérité). |
| H9 | ✅ Corrigé | Mot de passe oublié : réponse identique, règles `exists` retirées, compteur d'essais dédié, comparaison en temps constant. |
| H10 | ✅ Corrigé | Swagger masqué hors local (`API_DOCS_ENABLED`) ; cache du modèle `User` retiré. **Reste à faire** : mot de passe Redis. |
| H11 | ✅ Corrigé | Mise à jour ciblée sans version majeure ; contrainte flysystem corrigée. |
| M1 | ⏸️ Décision produit | Droits des coiffeurs et réceptionnistes : à définir avant implémentation. |
| M2 | ⏸️ Décision produit | Politique de mot de passe et vérification du téléphone à l'inscription. |
| M3 | ✅ Corrigé | Bornes sur `paid_at`, verrou anti double paiement, rendez-vous terminé figé, contrôle de chevauchement en back-office. |
| M4 | ✅ Corrigé | Suppression logique des salons (`deleted_at`). |
| M5 | ✅ Corrigé | Jobs SMS envoyés après le commit, nom client assaini. |
| M6 | ✅ Corrigé | `TRUSTED_PROXIES`. |
| M7 | ✅ Corrigé | `config/cors.php` piloté par `CORS_ALLOWED_ORIGINS`. |
| M8 | ⚠️ Partiel | Journal d'audit de toutes les actions `/admin` ; impossible de réinitialiser le mot de passe d'un autre super admin ou de bloquer le dernier. **Non fait** : MFA, changement de mot de passe forcé. |
| M9 | ✅ Corrigé | Plus de `KEYS *`. |
| M10 | ⏸️ Non traité | Normalisation des téléphones inchangée (risque de régression sur les données existantes). |
| A1 | ⚠️ Partiel | Imports et alias morts retirés. **La suppression des fichiers hérités (annexe B) a été bloquée par la politique de la session** : à faire après votre validation. |
| A2, A5, A9, A10 | ⏸️ Non traité | Refactorisations sans impact sécurité immédiat. |
| A3 | ⚠️ Partiel | Messages filtrés centralement ; les try/catch des contrôleurs restent. |
| A4 | ✅ Corrigé | Flux OTP retiré, `/auth/refresh` opérationnel. |
| A6 | ⚠️ Partiel | Verrous sur paiements, créneaux et Paystack. |
| A7, A8 | ✅ Corrigé | Cache retiré ; QR en SVG sans `imagick`. |
| A11 | ✅ Corrigé | CI GitHub Actions (tests et `composer audit`), 18 tests de sécurité. |
| P3, P5 | ✅ Corrigé | Webhook et réconciliation ; suspension effective. |
| P1, P2, P4, P6, P7 | ⏸️ Décision produit | Voir section 6. |
| P8 | ⚠️ Partiel | Simulation désactivée ; antidatage borné à 1 an. |

### Checklist de déploiement

1. **Secrets** : révoquer le mot de passe Gmail exposé, changer le mot de passe PostgreSQL, définir `JWT_SECRET`, `SUPER_ADMIN_PASSWORD` (12 caractères ou plus) et `REDIS_PASSWORD`. Purger l'historique git (git-filter-repo), puis demander à chacun de recloner.
2. **Migrations** : `php artisan migrate` ajoute `users.password_changed_at` et `salons.deleted_at`.
3. **Variables d'environnement** : `APP_DEBUG=false`, `TRUSTED_PROXIES`, `CORS_ALLOWED_ORIGINS`, `API_DOCS_ENABLED=false`, `SIMULATE_SUBSCRIPTION_PAYMENTS=false`, `SMS_DAILY_LIMIT_PER_SALON`, `BOOKING_MAX_DAYS_AHEAD`.
4. **Paystack** : déclarer l'URL de webhook `https://<api>/api/v1/payment/webhook` dans le dashboard Paystack.
5. **Planificateur** : vérifier que `schedule:run` tourne (`supervisor/alpha-scheduler.conf`) pour `paystack:reconcile`.
6. **Nginx** : interdire l'exécution PHP sous `/storage/`, par exemple avec un bloc `location ^~ /storage/ { location ~ \.php$ { return 403; } }` placé avant le bloc PHP-FPM.
7. **Frontend** : appliquer les changements décrits dans `docs/BACKOFFICE_API_VUEJS.md` (route `/auth/refresh`, codes `error`, réponse 429, OTP retiré).
