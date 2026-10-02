# GESTIMAINT — Application GMAO Industrielle

GESTIMAINT est une application de **Gestion de Maintenance Assistée par Ordinateur (GMAO)** moderne, intuitive et fluide, développée en **PHP 8 / MySQL (ou MariaDB)** sans système de login (accès direct et optimisé pour atelier/usine).

---

## 🚀 Fonctionnalités Clés

### 1. ⚙️ Parc d'équipements & Suivi du Cycle de Vie
- Suivi en temps réel de l'état de chaque machine :
  - **✅ En service / Réparé** (Opérationnel)
  - **🔧 En maintenance** (Intervention technique en cours)
  - **🚨 En panne** (Déclaration de panne avec saisie du motif et génération automatique d'un OT d'urgence)
- Basculement d'état en un clic depuis le tableau de bord ou la liste du parc.
- Ajout et suppression d'équipements avec sélection du département et dates préventives.

### 2. 🏢 Organisation par Départements & Rayons
- Classement et filtrage instantané par département (*Production & Usinage*, *Assemblage & Robotique*, *Conditionnement & Emballage*, *Énergie & Utilités*, *Logistique & Manutention*).
- Création dynamique de nouveaux départements personnalisés.

### 3. 📈 Statistiques Préventif vs Correctif & Motifs de Pannes
- Jauge proportionnelle bicolore comparant les maintenances **Préventives** (Vert clair) vs **Correctives / Pannes** (Rouge clair).
- Analyse de criticité et ratio d'efficacité de maintenance (*Objectif industriel > 70% préventif*).
- Répartition dynamique des **motifs récurrents de pannes** (*Surchauffe, Usure mécanique, Court-circuit, Fuite hydraulique, Défaut capteur*).
- Tableau des équipements critiques subissant le plus d'incidents.

### 4. 👷 Gestion des Techniciens & Équipes
- Annuaire complet des intervenants avec spécialités, équipes et évaluations moyennes.
- Ajout de nouveaux techniciens et suppression avec sécurisation des historiques.

### 5. 🔔 Centre de Notifications
- Alertes en temps réel sur les échéances de maintenance dépassées et déclarations de pannes critiques.
- Marquage automatique comme lu.

---

## 🎨 Design System
- **Palette** : Blanc pur lumineux, vert clair naturel rafraîchissant, et rouge clair réservé strictement et rarement aux urgences et alertes critiques.
- **Animations douces** : Transitions fluides, apparitions progressives, pulsations délicates sur les alertes et modales avec flou d'arrière-plan.
- **Architecture de fichiers épurée** : Seul `index.php` est situé à la racine du projet, toutes les autres pages sont organisées dans `pages/` et les composants partagés dans `includes/`.

---

## 📁 Arborescence du Projet

```text
GESTIMAINT/
├── index.php                      ← Tableau de bord principal (seul à la racine)
├── pages/
│   ├── equipements.php            ← Parc, départements & cycle de vie des machines
│   ├── maintenance.php            ← Ordres de travail & motifs d'intervention
│   ├── statistiques.php           ← Statistiques Préventif/Correctif & Motifs
│   ├── techniciens.php            ← Équipe technique (Ajout / Suppression)
│   └── notifications.php          ← Centre d'alertes & notifications
├── includes/
│   ├── db.php                     ← Connexion MySQL/MariaDB intelligente
│   ├── header.php                 ← Menu latéral & navigation dynamique
│   └── footer.php                 ← Fermeture du layout HTML
├── assets/
│   └── styles.css                 ← Design System & micro-animations
└── sql/
    └── schema.sql                 ← Schéma de base de données & données de test
```

---

## 🛠️ Installation & Démarrage (WAMP / Laragon / XAMPP)

1. **Cloner le projet** dans votre dossier web :
   ```bash
   git clone https://github.com/Martial-AI/Gestimaint.git
   ```
2. **Créer la base de données** :
   - Ouvrez phpMyAdmin (ex: `http://localhost/phpmyadmin/`).
   - Créez une base nommée **`gestimaint`** (encodage `utf8mb4_unicode_ci`).
   - Importez le fichier `sql/schema.sql`.
3. **Accéder à l'application** :
   - Ouvrez votre navigateur sur `http://localhost/Gestimaint/` (ou `http://localhost/GESTIMAINT_PROJECT/`).
   - La connexion à la base est automatique (gestion des ports 3307 MariaDB et 3306 MySQL).
