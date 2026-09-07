# Raja

Raja est une application interne de suivi des dossiers d’audition. Elle aide une équipe à visualiser les échéances, traiter les actions prioritaires et réduire les oublis de facturation. Elle complète le logiciel métier : Raja ne réalise ni la facturation, ni la télétransmission, ni la synchronisation automatique avec Cosium.

> Ce dépôt ne contient aucune donnée patient, sauvegarde, configuration locale ou identifiant réel.

## Fonctionnalités

- tableau de bord et priorités du jour ;
- fiches clients et plusieurs dossiers par client ;
- suivi ordonnance, mutuelle/CSS, prise en charge, montant et commentaires ;
- statuts métier : attente stock, dossier en cours, carte vitale à récupérer, à facturer, facturé, SAV, problématique et annulé ;
- échéances J+23 (carte vitale) et J+30 (facturation) ;
- gestion du SAV avec suspension des délais ;
- signalement, priorisation et résolution des problèmes ;
- comptes nominatifs avec rôles administrateur, patron et employé ;
- historique des actions ;
- import CSV avec prévisualisation, correspondance des colonnes et rapport d’import ;
- lecture de fiches PDF Cosium, avec OCR local si nécessaire ;
- récupération des dossiers Cosium et création optionnelle des dossiers AUDITION.

## Pré-requis

- Windows 10/11 ;
- [XAMPP](https://www.apachefriends.org/) avec PHP 8+ et MariaDB/MySQL ;
- un navigateur récent ;
- pour l’OCR des PDF scannés : Tesseract OCR et Poppler.

## Installation locale

1. Copiez le dossier dans `C:\xampp\htdocs\raja`.
2. Copiez `config/config.example.php` vers `config/config.php`.
3. Renseignez dans `config/config.php` les identifiants de la base MySQL locale. Ce fichier ne doit jamais être versionné.
4. Lancez Apache et MySQL depuis XAMPP.
5. Créez la base et les tables :

```powershell
C:\xampp\mysql\bin\mysql.exe -u VOTRE_UTILISATEUR -p -e "source C:/xampp/htdocs/raja/database/install.sql"
```

6. Créez le premier administrateur avec un mot de passe unique d’au moins 12 caractères :

```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\raja\scripts\create_admin.php admin@exemple.fr "UnMotDePasseLongEtUnique"
```

7. Ouvrez `http://localhost/raja` et connectez-vous.

`database/seed.sql` est volontairement vide : aucun compte avec mot de passe public n’est créé.

## Réseau local

Raja est prévu pour un réseau interne de confiance : gardez le PC hôte, Apache et MySQL actifs, autorisez Apache uniquement sur le réseau privé Windows, puis ouvrez `http://ADRESSE_IP_DU_PC/raja` depuis les autres postes.

Ne publiez pas Apache sur Internet. Utilisez des comptes nominatifs, des mots de passe uniques et des sauvegardes régulières de la base.

## OCR des PDF Cosium

Installez les lecteurs locaux :

```powershell
winget install --id UB-Mannheim.TesseractOCR --exact
winget install --id oschwartz10612.Poppler --exact
```

Raja détecte automatiquement les installations Windows et `winget`. Si nécessaire, renseignez les chemins complets des exécutables dans `config/config.php`.

Le PDF est traité localement, supprimé après lecture, puis ses informations sont proposées à validation avant création ou mise à jour du client.

## Import CSV

Exportez le fichier source en **CSV UTF-8** depuis Excel ou Google Sheets. L’import permet de prévisualiser les données, associer les colonnes, convertir les dates et conserver les alertes de qualité des données. Consultez `database/exemple_import.csv` pour le format attendu.

## Mise à jour quotidienne des statuts

Le tableau de bord recalcule les statuts à son ouverture. Pour une mise à jour quotidienne, créez une tâche planifiée Windows :

```text
Programme : C:\xampp\php\php.exe
Argument  : C:\xampp\htdocs\raja\cron\update_statuses.php
Fréquence : chaque matin avant l’ouverture
```

## Sécurité et confidentialité

- mots de passe hachés ;
- rôles et autorisations ;
- sessions et protections CSRF ;
- requêtes préparées PDO ;
- validation des saisies et des fichiers importés ;
- traitement PDF/OCR local ;
- historique des actions importantes.

Cette application est une V1 interne : elle n’est pas certifiée comme logiciel médical, n’a pas fait l’objet d’un audit de sécurité externe et ne remplace pas les obligations légales, les politiques de sauvegarde ni les règles de protection des données applicables à l’organisation utilisatrice.

## Vérifications

La logique J+23/J+30 peut être vérifiée après configuration de la base :

```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\raja\tests\smoke.php
```

Les tests HTTP complets nécessitent des comptes et un PDF de test fournis localement via variables d’environnement. Ils ne dépendent d’aucune donnée patient présente dans ce dépôt.

## Limites actuelles

- une seule boutique et un déploiement local XAMPP ;
- import CSV uniquement depuis l’interface ;
- aucune synchronisation avec Cosium ;
- aucune facturation, télétransmission, notification SMS ou e-mail ;
- aucune sauvegarde automatisée intégrée ;
- aucune exposition Internet prévue.

## Développement local

Avant toute contribution, assurez-vous que `config/config.php`, les sauvegardes SQL, les exports, les fichiers PDF, les tableurs et les données patients ne sont jamais ajoutés à Git.
