-- ============================================================
-- GESTIMAINT — Schéma de base de données MySQL
-- GMAO (Gestion de Maintenance Assistée par Ordinateur)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `gestimaint` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `gestimaint`;

-- Table : Rôles
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table : Équipes
CREATE TABLE IF NOT EXISTS `teams` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table : Départements / Rayons
CREATE TABLE IF NOT EXISTS `departments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `code` VARCHAR(50) NULL,
    `description` VARCHAR(255) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table : Utilisateurs / Techniciens
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT NOT NULL,
    `team_id` INT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NULL UNIQUE,
    `specialty` VARCHAR(100) NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_users_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table : Emplacements / Localisations
CREATE TABLE IF NOT EXISTS `locations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `site` VARCHAR(100) NOT NULL,
    `zone` VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table : Cycles de maintenance préventive
CREATE TABLE IF NOT EXISTS `cycles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `interval_days` INT NOT NULL DEFAULT 30
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE VIEW `maintenance_cycles` AS SELECT * FROM `cycles`;

-- Table : Équipements industriels
CREATE TABLE IF NOT EXISTS `equipment` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `serial_number` VARCHAR(100) NOT NULL UNIQUE,
    `model` VARCHAR(150) NOT NULL,
    `designation` VARCHAR(200) NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `manufacturer` VARCHAR(150) NULL,
    `status` ENUM('en_service', 'en_panne', 'en_maintenance', 'reforme') NOT NULL DEFAULT 'en_service',
    `failure_reason` VARCHAR(255) NULL,
    `location_id` INT NULL,
    `department_id` INT NULL,
    `cycle_id` INT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `next_maintenance_at` DATE NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_equip_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_equip_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_equip_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `cycles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table : Ordres de travail (Work orders)
CREATE TABLE IF NOT EXISTS `work_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `reference` VARCHAR(50) NOT NULL UNIQUE,
    `equipment_id` INT NOT NULL,
    `kind` ENUM('preventive', 'corrective', 'ameliorative') NOT NULL DEFAULT 'preventive',
    `priority` ENUM('faible', 'moyenne', 'haute', 'urgente', 'critique') NOT NULL DEFAULT 'moyenne',
    `status` ENUM('ouverte', 'en_cours', 'terminee', 'annulee') NOT NULL DEFAULT 'ouverte',
    `description` TEXT NULL,
    `failure_reason` VARCHAR(255) NULL,
    `planned_at` DATE NULL,
    `completed_at` DATETIME NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_wo_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table associative : Techniciens assignés aux ordres de travail
CREATE TABLE IF NOT EXISTS `work_order_technicians` (
    `work_order_id` INT NOT NULL,
    `technician_id` INT NOT NULL,
    PRIMARY KEY (`work_order_id`, `technician_id`),
    CONSTRAINT `fk_wot_wo` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_wot_tech` FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table : Évaluations des techniciens
CREATE TABLE IF NOT EXISTS `technician_evaluations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `technician_id` INT NOT NULL,
    `rating` DECIMAL(2,1) NOT NULL DEFAULT 5.0,
    `comment` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_eval_tech` FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table : Notifications
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('alert', 'warning', 'info') NOT NULL DEFAULT 'info',
    `trigger_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `read_at` DATETIME NULL,
    CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table : Journal d'audit (Audit log)
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `action` VARCHAR(50) NOT NULL,
    `entity_type` VARCHAR(50) NOT NULL,
    `entity_id` INT NULL,
    `details` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DONNÉES DE DÉMONSTRATION (Seed data)
-- ============================================================

INSERT INTO `roles` (`id`, `code`, `name`) VALUES
(1, 'admin', 'Administrateur'),
(2, 'technician', 'Technicien de Maintenance'),
(3, 'operator', 'Opérateur de Production')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

INSERT INTO `teams` (`id`, `name`, `description`) VALUES
(1, 'Équipe Électromécanique', 'Interventions électriques et automatismes'),
(2, 'Équipe Hydraulique & Pneumatique', 'Circuits fluides, vérins et compresseurs'),
(3, 'Équipe Mécanique Lourde', 'Lignes d\'usinage, convoyeurs et broyeurs')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

INSERT INTO `departments` (`id`, `name`, `code`, `description`) VALUES
(1, 'Production & Usinage', 'USIN', 'Ateliers de fraisage, tournage et découpe'),
(2, 'Assemblage & Robotique', 'ROBOT', 'Lignes automatisées et cellules robotisées'),
(3, 'Conditionnement & Emballage', 'EMB', 'Lignes de palettisation et banderoleuses'),
(4, 'Énergie & Utilités', 'UTIL', 'Chaufferie, compresseurs et groupes froids'),
(5, 'Logistique & Manutention', 'LOG', 'Convoyeurs centraux et quais de chargement')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `description`=VALUES(`description`);

INSERT INTO `users` (`id`, `role_id`, `team_id`, `first_name`, `last_name`, `email`, `specialty`, `active`) VALUES
(1, 1, 1, 'Thomas', 'Dubois', 't.dubois@gestimaint.local', 'Responsable GMAO', 1),
(2, 2, 1, 'Alexandre', 'Bernard', 'a.bernard@gestimaint.local', 'Électricité & Automate', 1),
(3, 2, 2, 'Sophie', 'Laurent', 's.laurent@gestimaint.local', 'Hydraulique haute pression', 1),
(4, 2, 3, 'Karim', 'Bensaid', 'k.bensaid@gestimaint.local', 'Mécanique & Usinage', 1),
(5, 2, 1, 'Lucas', 'Mercier', 'l.mercier@gestimaint.local', 'Électrotechnique & Capteurs', 1),
(6, 2, 2, 'Élodie', 'Roux', 'e.roux@gestimaint.local', 'Pneumatique & Climatisation', 1)
ON DUPLICATE KEY UPDATE `first_name`=VALUES(`first_name`);

INSERT INTO `locations` (`id`, `site`, `zone`) VALUES
(1, 'Usine Nord', 'Ligne Assemblage A'),
(2, 'Usine Nord', 'Atelier Usinage B'),
(3, 'Usine Sud', 'Zone Conditionnement'),
(4, 'Usine Sud', 'Chaufferie & Utilités')
ON DUPLICATE KEY UPDATE `site`=VALUES(`site`);

INSERT INTO `cycles` (`id`, `name`, `interval_days`) VALUES
(1, 'Hebdomadaire (7j)', 7),
(2, 'Mensuel (30j)', 30),
(3, 'Trimestriel (90j)', 90),
(4, 'Annuel (365j)', 365)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

INSERT INTO `equipment` (`id`, `serial_number`, `model`, `designation`, `category`, `manufacturer`, `status`, `failure_reason`, `location_id`, `department_id`, `cycle_id`, `active`, `next_maintenance_at`) VALUES
(1, 'EQ-2024-001', 'CX-4000', 'Compresseur d\'air rotatif principal', 'Compresseurs', 'Atlas Copco', 'en_service', NULL, 4, 4, 2, 1, DATE_ADD(CURDATE(), INTERVAL 5 DAY)),
(2, 'EQ-2024-002', 'ROBO-WELD-9', 'Cellule de soudure robotisée', 'Robotique', 'Kuka', 'en_service', NULL, 1, 2, 1, 1, DATE_SUB(CURDATE(), INTERVAL 2 DAY)),
(3, 'EQ-2024-003', 'CONV-HEAVY-12', 'Convoyeur à bande renforcée Ligne A', 'Convoyeurs', 'Interroll', 'en_maintenance', NULL, 1, 5, 3, 1, DATE_ADD(CURDATE(), INTERVAL 12 DAY)),
(4, 'EQ-2024-004', 'CNC-MILL-5X', 'Fraiseuse 5 axes haute précision', 'Usinage', 'DMG Mori', 'en_panne', 'Surchauffe broche axe Z', 2, 1, 2, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
(5, 'EQ-2024-005', 'HYDR-PRESS-200T', 'Presse hydraulique 200 Tonnes', 'Presses', 'Schuler', 'en_service', NULL, 2, 1, 3, 1, DATE_ADD(CURDATE(), INTERVAL 18 DAY)),
(6, 'EQ-2024-006', 'WRAP-FAST-X', 'Banderoleuse automatique palettes', 'Conditionnement', 'Robopac', 'en_service', NULL, 3, 3, 2, 1, DATE_ADD(CURDATE(), INTERVAL 8 DAY)),
(7, 'EQ-2024-007', 'CHILL-AIR-50', 'Groupe froid industriel 50kW', 'Climatisation', 'Carrier', 'en_service', NULL, 4, 4, 4, 1, DATE_ADD(CURDATE(), INTERVAL 45 DAY))
ON DUPLICATE KEY UPDATE `designation`=VALUES(`designation`);

INSERT INTO `work_orders` (`id`, `reference`, `equipment_id`, `kind`, `priority`, `status`, `description`, `failure_reason`, `planned_at`) VALUES
(1, 'OT-2024-101', 4, 'corrective', 'urgente', 'en_cours', 'Arrêt d\'urgence suite à alarme broche axe Z en surchauffe.', 'Surchauffe broche axe Z', CURDATE()),
(2, 'OT-2024-102', 2, 'preventive', 'haute', 'ouverte', 'Contrôle des servomoteurs et recalibration du bras articulé.', 'Calibration préventive', DATE_SUB(CURDATE(), INTERVAL 2 DAY)),
(3, 'OT-2024-103', 3, 'ameliorative', 'moyenne', 'en_cours', 'Remplacement des rouleaux de guidage et tensionnement.', 'Usure mécanique des rouleaux', DATE_ADD(CURDATE(), INTERVAL 1 DAY)),
(4, 'OT-2024-104', 1, 'preventive', 'faible', 'ouverte', 'Vidange huile de compression et remplacement filtre d\'aspiration.', 'Entretien périodique filtres', DATE_ADD(CURDATE(), INTERVAL 5 DAY)),
(5, 'OT-2024-105', 5, 'preventive', 'moyenne', 'terminee', 'Contrôle de l\'étanchéité des vérins et test de pression hydraulique.', 'Fuite hydraulique vérin', DATE_SUB(CURDATE(), INTERVAL 5 DAY)),
(6, 'OT-2024-090', 1, 'preventive', 'moyenne', 'terminee', 'Remplacement filtre à huile', 'Maintenance programmée 500h', DATE_SUB(CURDATE(), INTERVAL 25 DAY)),
(7, 'OT-2024-091', 2, 'corrective', 'urgente', 'terminee', 'Court-circuit capteur inductif', 'Court-circuit électrique', DATE_SUB(CURDATE(), INTERVAL 20 DAY)),
(8, 'OT-2024-092', 3, 'corrective', 'haute', 'terminee', 'Blocage courroie de transmission', 'Bourrage matière / blocage', DATE_SUB(CURDATE(), INTERVAL 16 DAY)),
(9, 'OT-2024-093', 4, 'corrective', 'haute', 'terminee', 'Erreur variateur de vitesse', 'Défaut électronique / automate', DATE_SUB(CURDATE(), INTERVAL 12 DAY)),
(10, 'OT-2024-094', 5, 'preventive', 'faible', 'terminee', 'Graissage centralisé trimestriel', 'Contrôle périodique', DATE_SUB(CURDATE(), INTERVAL 10 DAY)),
(11, 'OT-2024-095', 6, 'corrective', 'moyenne', 'terminee', 'Rupture cellule optique fin de course', 'Défaut capteur', DATE_SUB(CURDATE(), INTERVAL 7 DAY)),
(12, 'OT-2024-096', 7, 'preventive', 'moyenne', 'terminee', 'Nettoyage condenseur et test fluide', 'Entretien périodique', DATE_SUB(CURDATE(), INTERVAL 4 DAY))
ON DUPLICATE KEY UPDATE `reference`=VALUES(`reference`);

INSERT INTO `work_order_technicians` (`work_order_id`, `technician_id`) VALUES
(1, 4),
(1, 2),
(2, 2),
(3, 4),
(4, 3),
(5, 3)
ON DUPLICATE KEY UPDATE `work_order_id`=VALUES(`work_order_id`);

INSERT INTO `technician_evaluations` (`technician_id`, `rating`, `comment`) VALUES
(2, 4.8, 'Excellente réactivité lors de la remise en service du robot.'),
(3, 4.9, 'Diagnostic hydraulique très précis et sécurité irréprochable.'),
(4, 4.5, 'Travail mécanique soigné et rapide.'),
(5, 4.2, 'Bonne autonomie sur la maintenance préventive.'),
(6, 4.7, 'Très bonne gestion des filtres pneumatiques.')
ON DUPLICATE KEY UPDATE `rating`=VALUES(`rating`);

INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`, `trigger_at`, `read_at`) VALUES
(1, 'Alerte panne critique', 'La Fraiseuse 5 axes CNC-MILL-5X est déclarée en panne urgente : Surchauffe broche axe Z.', 'alert', DATE_SUB(NOW(), INTERVAL 35 MINUTE), NULL),
(1, 'Maintenance en retard', 'La cellule de soudure ROBO-WELD-9 a dépassé son échéance de 2 jours.', 'warning', DATE_SUB(NOW(), INTERVAL 2 HOUR), NULL),
(1, 'Ordre de travail achevé', 'L\'intervention sur la Presse hydraulique 200T a été validée avec succès.', 'info', DATE_SUB(NOW(), INTERVAL 1 DAY), NOW()),
(1, 'Rappel préventif', 'Compresseur d\'air rotatif : révision prévue dans 5 jours.', 'info', DATE_SUB(NOW(), INTERVAL 2 DAY), NOW())
ON DUPLICATE KEY UPDATE `title`=VALUES(`title`);
