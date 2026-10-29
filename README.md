# Mboa Drive API

API REST d'une application de covoiturage pour le Cameroun : les conducteurs publient des trajets entre villes (Douala, Yaoundé, Bafoussam…), les passagers réservent une place, paient et notent le trajet.

Ce dépôt contient le backend. Il est conçu pour être consommé par une application mobile.

## Fonctionnalités

- **Authentification** par jeton (Laravel Sanctum) : inscription, connexion, vérification du téléphone par OTP, réinitialisation du mot de passe
- **Véhicules** : ajout, photos, activation
- **Trajets** : publication, étapes intermédiaires, recherche, démarrage, fin, annulation, historique
- **Réservations** : demande, confirmation ou refus par le conducteur, prise en charge et dépose du passager
- **Paiements** : Mobile Money (MTN, Orange) ou espèces, historique et statut
- **Messagerie** entre conducteur et passager, avec compteur de messages non lus
- **Notes et avis** sur les conducteurs et les passagers
- **Notifications push** via Firebase Cloud Messaging
- **Tableaux de bord** conducteur et passager (statistiques, trajets, réservations)
- **Support** : signalement d'un utilisateur, retours

## Stack technique

| Couche | Technologie |
|---|---|
| Framework | Laravel 10 (PHP 8.1+) |
| Authentification | Laravel Sanctum |
| Rôles et permissions | spatie/laravel-permission |
| Cache et files d'attente | Redis (Predis) |
| Temps réel | Pusher |
| Notifications push | Firebase Cloud Messaging (kreait/firebase-php) |
| Stockage des images | AWS S3, Intervention Image |
| Documentation de l'API | Swagger / OpenAPI (L5-Swagger) |

## Installation

Prérequis : PHP 8.1+, Composer, MySQL ou PostgreSQL, Redis.

```bash
git clone https://github.com/BelCodeur2005/mboa-drive-api.git
cd mboa-drive-api
composer install
cp .env.example .env
php artisan key:generate
```

Renseigner la base de données dans `.env`, puis :

```bash
php artisan migrate
php artisan serve
```

L'API répond sur `http://localhost:8000/api/v1`.

## Documentation de l'API

La documentation Swagger est disponible sur `http://localhost:8000/api/documentation` une fois le serveur lancé.

Principaux groupes de routes (`/api/v1`) :

| Préfixe | Rôle |
|---|---|
| `/auth` | inscription, connexion, OTP, profil connecté |
| `/trips` | recherche (public) et gestion des trajets |
| `/bookings` | réservations côté passager et côté conducteur |
| `/vehicles` | véhicules du conducteur |
| `/payments` | paiement Mobile Money ou espèces |
| `/messages` | conversations |
| `/ratings` | notes et avis |
| `/notifications` | notifications de l'utilisateur |
| `/dashboard` | statistiques conducteur et passager |

## État du projet

Le projet est en cours de développement :

- le paiement Mobile Money (MTN, Orange) et l'envoi d'OTP par SMS sont **simulés**, l'intégration des API réelles est prévue
- les tests automatisés ne sont pas encore écrits
