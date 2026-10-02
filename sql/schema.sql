-- ============================================================
-- GESTIMAINT - Schema de base de donnees MySQL / MariaDB
-- GMAO (Gestion de Maintenance Assistee par Ordinateur)
-- Encodage : UTF-8 (utf8mb4_unicode_ci)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `gestimaint` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `gestimaint`;

-- Table : Roles
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table : Equipes
CREATE TABLE IF NOT EXISTS `teams` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table : Departements / Rayons
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
    `phone` VARCHAR(30) NULL,
    `hourly_rate` DECIMAL(8,2) DEFAULT 0.00,
    `active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_user_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table : Localisations / Zones
CREATE TABLE IF NOT EXISTS `locations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `building` VARCHAR(100) NOT NULL,
    `zone` VARCHAR(100) NOT NULL,
    `subzone` VARCHAR(100) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table : Cycles de maintenance preventive
CREATE TABLE IF NOT EXISTS `cycles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `interval_days` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table : Equipements industriels
CREATE TABLE IF NOT EXISTS `equipment` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `serial_number` VARCHAR(100) NOT NULL UNIQUE,
    `model` VARCHAR(100) NULL,
    `designation` VARCHAR(200) NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `manufacturer` VARCHAR(100) NULL,
    `status` ENUM('en_service', 'en_maintenance', 'en_panne', 'reforme') DEFAULT 'en_service',
    `failure_reason` VARCHAR(255) NULL,
    `location_id` INT NULL,
    `department_id` INT NULL,
    `cycle_id` INT NULL,
    `purchase_date` DATE NULL,
    `warranty_until` DATE NULL,
    `active` TINYINT(1) DEFAULT 1,
    `last_maintenance_at` DATE NULL,
    `next_maintenance_at` DATE NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_equip_location`   FOREIGN KEY (`location_id`)   REFERENCES `locations`   (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_equip_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_equip_cycle`      FOREIGN KEY (`cycle_id`)      REFERENCES `cycles`      (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table : Ordres de travail (OT)
CREATE TABLE IF NOT EXISTS `work_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `reference` VARCHAR(50) NOT NULL UNIQUE,
    `equipment_id` INT NOT NULL,
    `kind` ENUM('preventive', 'corrective', 'ameliorative') DEFAULT 'corrective',
    `type` ENUM('preventive', 'corrective', 'ameliorative') DEFAULT 'corrective',
    `priority` ENUM('faible', 'moyenne', 'haute', 'urgente') DEFAULT 'moyenne',
    `status` ENUM('ouverte', 'planifiee', 'en_cours', 'en_attente_pieces', 'terminee', 'annulee') DEFAULT 'ouverte',
    `description` TEXT NOT NULL,
    `failure_reason` VARCHAR(255) NULL,
    `created_by` INT NULL,
    `planned_at` DATE NULL,
    `started_at` DATETIME NULL,
    `completed_at` DATETIME NULL,
    `duration_hours` DECIMAL(5,2) DEFAULT 0.00,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_wo_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_wo_creator`   FOREIGN KEY (`created_by`)   REFERENCES `users`     (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table associative : Techniciens assignes aux ordres de travail
CREATE TABLE IF NOT EXISTS `work_order_technicians` (
    `work_order_id` INT NOT NULL,
    `technician_id` INT NOT NULL,
    `assigned_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`work_order_id`, `technician_id`),
    CONSTRAINT `fk_wot_wo` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_wot_tech` FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table : Evaluations des techniciens
CREATE TABLE IF NOT EXISTS `technician_evaluations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `technician_id` INT NOT NULL,
    `evaluator_id` INT NULL,
    `work_order_id` INT NULL,
    `rating` DECIMAL(3,2) NOT NULL,
    `comment` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_eval_tech` FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table : Notifications
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('info', 'warning', 'alert', 'success') DEFAULT 'info',
    `trigger_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `read_at` DATETIME NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table : Journal d'audit
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(50) NOT NULL,
    `entity_id` INT NOT NULL,
    `details` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DONNEES DE DEMONSTRATION (Seed data)

INSERT INTO `roles` (`id`, `code`, `name`) VALUES
(1, 'RESPONSABLE_GMAO', 'Responsable GMAO'),
(2, 'CHEF_EQUIPE',      'Chef d\'equipe Maintenance'),
(3, 'TECHNICIEN',       'Technicien de Maintenance'),
(4, 'OPERATEUR',        'Operateur de Production')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

INSERT INTO `teams` (`id`, `name`, `description`) VALUES
(1, 'Equipe Mecanique',     'Interventions mecaniques lourdes et usinage'),
(2, 'Equipe Electrique',    'Automates, armoires electriques et capteurs'),
(3, 'Equipe Fluides',       'Hydraulique, pneumatique et refrigeration')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

INSERT INTO `departments` (`id`, `name`, `code`, `description`) VALUES
(1, 'Production et Usinage',      'PROD', 'Lignes d\'usinage CNC et presses'),
(2, 'Assemblage et Robotique',    'ASSEM', 'Cellules de soudure et robots polyarticules'),
(3, 'Conditionnement et Emballage','COND', 'Lignes de mise en carton et palettisation'),
(4, 'Energie et Utilites',        'ENERG', 'Centrales d\'air comprime et sous-stations'),
(5, 'Logistique et Manutention',  'LOG',   'Convoyeurs a rouleaux et transstockeurs')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

INSERT INTO `locations` (`id`, `building`, `zone`, `subzone`) VALUES
(1, 'Batiment A - Production', 'Atelier Usinage', 'Ilot CNC 1-4'),
(2, 'Batiment A - Production', 'Atelier Presses', 'Ligne 200T'),
(3, 'Batiment B - Assemblage', 'Zone Robotique',  'Cellule Kuka A'),
(4, 'Batiment C - Utilites',   'Salle Compresseurs', 'Station Atlas Copco')
ON DUPLICATE KEY UPDATE `building`=VALUES(`building`);

INSERT INTO `users` (`id`, `role_id`, `team_id`, `first_name`, `last_name`, `email`, `specialty`, `phone`, `hourly_rate`, `active`) VALUES
(1, 1, NULL, 'Thomas',    'Dubois',   't.dubois@gestimaint.local',   'Responsable GMAO',               '06.11.22.33.44', 45.00, 1),
(2, 3, 2,    'Alexandre', 'Bernard',  'a.bernard@gestimaint.local',  'Electricite et Automate',        '06.22.33.44.55', 32.00, 1),
(3, 3, 3,    'Sophie',    'Laurent',  's.laurent@gestimaint.local',  'Hydraulique haute pression',     '06.33.44.55.66', 34.00, 1),
(4, 3, 1,    'Karim',     'Bensaid',  'k.bensaid@gestimaint.local',  'Mecanique et Usinage',           '06.44.55.66.77', 31.00, 1),
(5, 3, 2,    'Lucas',     'Mercier',  'l.mercier@gestimaint.local',  'Electrotechnique et Capteurs',   '06.55.66.77.88', 29.50, 1)
ON DUPLICATE KEY UPDATE `email`=VALUES(`email`);

INSERT INTO `cycles` (`id`, `name`, `interval_days`) VALUES
(1, 'Hebdomadaire (7j)', 7),
(2, 'Mensuel (30j)', 30),
(3, 'Trimestriel (90j)', 90),
(4, 'Annuel (365j)', 365)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

INSERT INTO `equipment` (`id`, `serial_number`, `model`, `designation`, `category`, `manufacturer`, `status`, `failure_reason`, `location_id`, `department_id`, `cycle_id`, `active`, `next_maintenance_at`) VALUES
(1, 'EQ-2024-001', 'CX-4000', 'Compresseur d\'air rotatif principal', 'Compresseurs', 'Atlas Copco', 'en_service', NULL, 4, 4, 2, 1, DATE_ADD(CURDATE(), INTERVAL 5 DAY)),
(2, 'EQ-2024-002', 'ROBO-WELD-9', 'Cellule de soudure robotisee', 'Robotique', 'Kuka', 'en_service', NULL, 1, 2, 1, 1, DATE_SUB(CURDATE(), INTERVAL 2 DAY)),
(3, 'EQ-2024-003', 'CONV-HEAVY-12', 'Convoyeur a bande renforcee Ligne A', 'Convoyeurs', 'Interroll', 'en_maintenance', NULL, 1, 5, 3, 1, DATE_ADD(CURDATE(), INTERVAL 12 DAY)),
(4, 'EQ-2024-004', 'CNC-MILL-5X', 'Fraiseuse 5 axes haute precision', 'Usinage', 'DMG Mori', 'en_panne', 'Surchauffe broche axe Z', 2, 1, 2, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
(5, 'EQ-2024-005', 'HYDR-PRESS-200T', 'Presse hydraulique 200 Tonnes', 'Presses', 'Schuler', 'en_service', NULL, 2, 1, 3, 1, DATE_ADD(CURDATE(), INTERVAL 18 DAY)),
(6, 'EQ-2024-006', 'WRAP-FAST-X', 'Banderoleuse automatique palettes', 'Conditionnement', 'Robopac', 'en_service', NULL, 3, 3, 2, 1, DATE_ADD(CURDATE(), INTERVAL 8 DAY)),
(7, 'EQ-2024-007', 'CHILL-AIR-50', 'Groupe froid industriel 50kW', 'Climatisation', 'Carrier', 'en_service', NULL, 4, 4, 4, 1, DATE_ADD(CURDATE(), INTERVAL 45 DAY))
ON DUPLICATE KEY UPDATE `designation`=VALUES(`designation`);

INSERT INTO `work_orders` (`id`, `reference`, `equipment_id`, `kind`, `type`, `priority`, `status`, `description`, `failure_reason`, `planned_at`) VALUES
(1, 'OT-2024-101', 4, 'corrective', 'corrective', 'urgente', 'en_cours', 'Arret d\'urgence suite a alarme broche axe Z en surchauffe.', 'Surchauffe broche axe Z', CURDATE()),
(2, 'OT-2024-102', 2, 'preventive', 'preventive', 'haute', 'ouverte', 'Controle des servomoteurs et recalibration du bras articule.', 'Calibration preventive', DATE_SUB(CURDATE(), INTERVAL 2 DAY)),
(3, 'OT-2024-103', 3, 'ameliorative', 'ameliorative', 'moyenne', 'en_cours', 'Remplacement des rouleaux de guidage et tensionnement.', 'Usure mecanique des rouleaux', DATE_ADD(CURDATE(), INTERVAL 1 DAY)),
(4, 'OT-2024-104', 1, 'preventive', 'preventive', 'faible', 'ouverte', 'Vidange huile de compression et remplacement filtre d\'aspiration.', 'Entretien periodique filtres', DATE_ADD(CURDATE(), INTERVAL 5 DAY)),
(5, 'OT-2024-105', 5, 'preventive', 'preventive', 'moyenne', 'terminee', 'Controle de l\'etancheite des verins et test de pression hydraulique.', 'Fuite hydraulique verin', DATE_SUB(CURDATE(), INTERVAL 5 DAY)),
(6, 'OT-2024-090', 1, 'preventive', 'preventive', 'moyenne', 'terminee', 'Remplacement filtre a huile', 'Maintenance programmee 500h', DATE_SUB(CURDATE(), INTERVAL 25 DAY)),
(7, 'OT-2024-091', 2, 'corrective', 'corrective', 'urgente', 'terminee', 'Court-circuit capteur inductif', 'Court-circuit electrique', DATE_SUB(CURDATE(), INTERVAL 20 DAY)),
(8, 'OT-2024-092', 3, 'corrective', 'corrective', 'haute', 'terminee', 'Blocage courroie de transmission', 'Bourrage matiere / blocage', DATE_SUB(CURDATE(), INTERVAL 16 DAY)),
(9, 'OT-2024-093', 4, 'corrective', 'corrective', 'haute', 'terminee', 'Erreur variateur de vitesse', 'Defaut electronique / automate', DATE_SUB(CURDATE(), INTERVAL 12 DAY)),
(10, 'OT-2024-094', 5, 'preventive', 'preventive', 'faible', 'terminee', 'Graissage centralise trimestriel', 'Controle periodique', DATE_SUB(CURDATE(), INTERVAL 10 DAY)),
(11, 'OT-2024-095', 6, 'corrective', 'corrective', 'moyenne', 'terminee', 'Rupture cellule optique fin de course', 'Defaut capteur', DATE_SUB(CURDATE(), INTERVAL 7 DAY)),
(12, 'OT-2024-096', 7, 'preventive', 'preventive', 'moyenne', 'terminee', 'Nettoyage condenseur et test fluide', 'Entretien periodique', DATE_SUB(CURDATE(), INTERVAL 4 DAY))
ON DUPLICATE KEY UPDATE `reference`=VALUES(`reference`);

INSERT INTO `work_order_technicians` (`work_order_id`, `technician_id`) VALUES
(1, 4), (1, 2), (2, 2), (3, 4), (4, 3), (5, 3)
ON DUPLICATE KEY UPDATE `work_order_id`=VALUES(`work_order_id`);

INSERT INTO `technician_evaluations` (`technician_id`, `rating`, `comment`) VALUES
(2, 4.8, 'Excellente reactivite lors de la remise en service du robot.'),
(3, 4.9, 'Diagnostic hydraulique tres precis et securite irreprochable.'),
(4, 4.5, 'Travail mecanique soigne et rapide.'),
(5, 4.2, 'Bonne autonomie sur la maintenance preventive.')
ON DUPLICATE KEY UPDATE `rating`=VALUES(`rating`);

INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`, `trigger_at`, `read_at`) VALUES
(1, 'Alerte panne critique', 'La Fraiseuse 5 axes CNC-MILL-5X est declaree en panne urgente : Surchauffe broche axe Z.', 'alert', DATE_SUB(NOW(), INTERVAL 35 MINUTE), NULL),
(1, 'Maintenance en retard', 'La cellule de soudure ROBO-WELD-9 a depasse son echeance de 2 jours.', 'warning', DATE_SUB(NOW(), INTERVAL 2 HOUR), NULL),
(1, 'Ordre de travail acheve', 'L\'intervention sur la Presse hydraulique 200T a ete validee avec succes.', 'info', DATE_SUB(NOW(), INTERVAL 1 DAY), NOW()),
(1, 'Rappel preventif', 'Compresseur d\'air rotatif : revision prevue dans 5 jours.', 'info', DATE_SUB(NOW(), INTERVAL 2 DAY), NOW())
ON DUPLICATE KEY UPDATE `title`=VALUES(`title`);