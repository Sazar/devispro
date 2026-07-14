# DevisPro — Générateur de devis pour artisans (PHP)

Version PHP de DevisPro, compatible avec tout hébergement mutualisé (OVH, Hostinger, 1&1, etc.).

## Prérequis

- PHP 7.4+ (avec extension PDO SQLite)
- Serveur web (Apache, Nginx, ou serveur intégré PHP)

## Lancement en local

### Option 1 — Serveur PHP intégré (le plus simple)

```bash
cd devispro_php
php -S localhost:8000
```

Ouvrez http://localhost:8000 dans votre navigateur.

### Option 2 — XAMPP / WAMP / MAMP

1. Copiez le dossier `devispro_php` dans `htdocs` (XAMPP) ou `www` (WAMP)
2. Accédez à http://localhost/devispro_php

## Déploiement en ligne

1. Uploadez tous les fichiers sur votre hébergement via FTP
2. Assurez-vous que le dossier `data/` est writable (chmod 755)
3. Accédez à votre domaine

## Fonctionnalités

- Inscription / Connexion
- Dashboard avec statistiques
- Création de devis (lignes dynamiques)
- Prévisualisation en temps réel
- Génération PDF (impression navigateur)
- Système de crédits (3 gratuits + packs payants)
- Suivi des statuts (brouillon → accepté)
- Duplication de devis

## Configuration Stripe (paiement)

Pour activer les vrais paiements :

1. Créez un compte sur [stripe.com](https://stripe.com)
2. Remplacez le contenu de `payment.php` par l'intégration Stripe Checkout
3. Ajoutez vos clés API dans les variables d'environnement ou directement dans le fichier

## Structure

```
devispro_php/
├── includes/
│   ├── config.php      # Config BDD, fonctions utilitaires
│   ├── header.php      # Template header (sidebar + nav)
│   └── footer.php      # Template footer
├── assets/
│   └── css/
│       └── style.css   # Styles
├── data/               # Base SQLite (créée auto)
├── register.php        # Inscription
├── login.php           # Connexion
├── logout.php          # Déconnexion
├── dashboard.php       # Tableau de bord
├── new-devis.php       # Créer un devis
├── view-devis.php      # Voir un devis
├── pdf.php             # Génération PDF
├── profile.php         # Profil utilisateur
└── payment.php         # Paiement (simulation)
```

## Notes

- La base de données SQLite est stockée dans `data/devispro.db`
- Les PDF sont générés via l'impression navigateur (bouton "Imprimer / Enregistrer en PDF")
- Pour une génération PDF serveur, installez [DomPDF](https://github.com/dompdf/dompdf)
