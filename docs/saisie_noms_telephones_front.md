# Saisie des noms et des numéros (front)

Les noms saisis (gérant, salon, prestation, coiffeur, client) peuvent être insérés dans des SMS envoyés
avec l'expéditeur de la plateforme. Pour éviter l'hameçonnage (pentest P1) et le bombardement de SMS
(pentest P2), le back applique des règles plus strictes depuis le 2026-11-06. Le front doit les
reprendre dans ses validations pour afficher l'erreur avant l'envoi.

## Noms : règles et champs concernés

| Type | Champs | Caractères autorisés |
|---|---|---|
| Nom de personne | `admin_name` (inscription), `name` (employé : création et modification), `name` (client back-office : création et modification), `client_name` (réservation publique), `name` (sans rendez-vous : accueil QR et back-office) | lettres (accents compris), espace, `-`, `'`, `’`, `.` ; commence par une lettre |
| Nom de salon ou de prestation | `salon_name` (inscription), `name` (salon : modification par le gérant et par le super-admin), `name` (prestation : création et modification) | en plus : chiffres et `,` `&` `(` `)` `+` ; commence par une lettre ou un chiffre |

Dans tous les cas, ces textes sont refusés :
- un saut de ligne, une tabulation ou un emoji ;
- un lien (`http://`, `https://`, `www.`) ou un domaine (`wave-ci.com`, `evil . example`) ;
- 8 chiffres ou plus à la suite, avec ou sans séparateur (`07 00 00 00 00`).

Longueur inchangée : 2 à 100 caractères.

Erreur renvoyée (422, format habituel `errors.<champ>`) :
- « Ce nom contient des caractères non autorisés : … » ;
- « Ce nom ne doit contenir ni lien, ni adresse web, ni numéro de téléphone. »

Changements visibles par rapport à avant :
- `salon_name` acceptait déjà lettres, chiffres, espaces, `-` et `'` ; il accepte maintenant aussi `.` `,` `&` `(` `)` `+`, mais plus les sauts de ligne ni les tabulations ;
- les autres champs n'avaient aucune règle de caractères : un nom comme `Coiffure/Barbe` ou `Soin 🎁` est maintenant refusé (`Coiffure - Barbe`, `Coupe + barbe` passent).

Expression régulière équivalente côté front (le back fait foi) :

```typescript
// Nom de personne
const PERSON_NAME = /^[\p{L}\p{M}][\p{L}\p{M} \-'’.]*$/u
// Nom de salon ou de prestation
const LABEL_NAME = /^[\p{L}\p{M}\p{N}][\p{L}\p{M}\p{N} \-'’.,&()+]*$/u
// Refus supplémentaires
const HAS_LINK_OR_PHONE = /(\b[a-z][a-z0-9+.\-]*:\/\/|\bwww\s*\.)|\+?\d(?:[\s.\-]*\d){7,}|[\p{L}\p{N}_\-]+(?:\.|\s+\.\s*)\p{L}{2,}/iu
```

Les données déjà en base ne sont pas modifiées. Elles sont nettoyées au moment de l'envoi du SMS
(`App\Support\SmsText::clean`), qui retire liens, domaines, numéros, emojis et sauts de ligne et tronque à 40 caractères.

## Numéros de téléphone (Côte d'Ivoire)

Formats acceptés (inchangés) : `2250707070707`, `+225 07 07 07 07 07`, `0707070707`.

Nouveautés :
- les deux chiffres après `225` doivent être `01`, `05`, `07` (mobiles) ou `21`, `25`, `27` (fixes) ;
- un numéro avec des chiffres en trop (`22507070707070`) est **refusé** (422). Avant, il était tronqué
  sans le dire, ce qui permettait de contourner la limite de réservations par numéro.

La limite « 5 réservations publiques par jour et par numéro » compte désormais toutes les écritures
d'un même numéro ensemble. Au-delà : 429 (`too_many_requests`).

## SMS de bienvenue

Le numéro du gérant n'est pas vérifié à l'inscription (OTP retiré, audit C2). Le SMS de bienvenue ne contient
donc plus aucun texte saisi : ni le nom du gérant ni celui du salon. Il contient seulement un message fixe et le lien de
réservation du salon. Au plus un SMS de bienvenue par numéro, et 20 par heure au total (`SMS_WELCOME_HOURLY_LIMIT`).
Aucun impact sur l'API d'inscription.
