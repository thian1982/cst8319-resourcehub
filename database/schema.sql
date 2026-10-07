-- ============================================================
-- ResourceHub
-- schema.sql
-- CST8319 Software Development Project
-- ============================================================

USE cst8319;

SET NAMES utf8mb4;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS maintenance_notes;
DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS resources;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;


-- ============================================================
-- USERS
-- ============================================================

CREATE TABLE users (
                       user_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

                       full_name VARCHAR(120) NOT NULL,

                       email VARCHAR(255) NOT NULL,

                       password_hash VARCHAR(255) NOT NULL,

                       role ENUM(
        'user',
        'admin'
    ) NOT NULL DEFAULT 'user',

                       account_status ENUM(
        'active',
        'inactive'
    ) NOT NULL DEFAULT 'active',

                       created_at TIMESTAMP
                           NOT NULL DEFAULT CURRENT_TIMESTAMP,

                       updated_at TIMESTAMP
                           NOT NULL DEFAULT CURRENT_TIMESTAMP
                           ON UPDATE CURRENT_TIMESTAMP,

                       PRIMARY KEY (user_id),

                       UNIQUE KEY uq_users_email (email),

                       KEY idx_users_role_status (
        role,
        account_status
    )
)
    ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;


-- ============================================================
-- CATEGORIES
-- ============================================================

CREATE TABLE categories (
                            category_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

                            name VARCHAR(100) NOT NULL,

                            description VARCHAR(500) NULL,

                            created_at TIMESTAMP
                                              NOT NULL DEFAULT CURRENT_TIMESTAMP,

                            updated_at TIMESTAMP
                                              NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,

                            PRIMARY KEY (category_id),

                            UNIQUE KEY uq_categories_name (name)
)
    ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;


-- ============================================================
-- RESOURCES
-- ============================================================

CREATE TABLE resources (
                           resource_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

                           category_id BIGINT UNSIGNED NOT NULL,

                           name VARCHAR(150) NOT NULL,

                           description TEXT NULL,

                           location VARCHAR(150) NOT NULL,

                           status ENUM(
        'available',
        'maintenance',
        'inactive'
    ) NOT NULL DEFAULT 'available',

                           created_at TIMESTAMP
                               NOT NULL DEFAULT CURRENT_TIMESTAMP,

                           updated_at TIMESTAMP
                               NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,

                           PRIMARY KEY (resource_id),

                           CONSTRAINT fk_resources_category
                               FOREIGN KEY (category_id)
                                   REFERENCES categories(category_id)
                                   ON UPDATE CASCADE
                                   ON DELETE RESTRICT,

                           KEY idx_resources_category_status (
        category_id,
        status
    ),

                           KEY idx_resources_status_location (
        status,
        location
    ),

                           KEY idx_resources_name (
        name
    )
)
    ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;


-- ============================================================
-- RESERVATIONS
--
-- Conflict rule:
--
-- new_start < existing_end
-- AND
-- new_end > existing_start
-- ============================================================

CREATE TABLE reservations (
                              reservation_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

                              user_id BIGINT UNSIGNED NOT NULL,

                              resource_id BIGINT UNSIGNED NOT NULL,

                              start_datetime DATETIME NOT NULL,

                              end_datetime DATETIME NOT NULL,

                              status ENUM(
        'active',
        'cancelled',
        'completed'
    ) NOT NULL DEFAULT 'active',

                              cancelled_at DATETIME NULL,

                              created_at TIMESTAMP
                                                      NOT NULL DEFAULT CURRENT_TIMESTAMP,

                              updated_at TIMESTAMP
                                                      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,

                              PRIMARY KEY (reservation_id),

                              CONSTRAINT fk_reservations_user
                                  FOREIGN KEY (user_id)
                                      REFERENCES users(user_id)
                                      ON UPDATE CASCADE
                                      ON DELETE RESTRICT,

                              CONSTRAINT fk_reservations_resource
                                  FOREIGN KEY (resource_id)
                                      REFERENCES resources(resource_id)
                                      ON UPDATE CASCADE
                                      ON DELETE RESTRICT,

                              KEY idx_reservations_user_time (
        user_id,
        start_datetime
    ),

                              KEY idx_reservations_resource_status_time (
        resource_id,
        status,
        start_datetime,
        end_datetime
    ),

                              KEY idx_reservations_status_time (
        status,
        start_datetime
    )
)
    ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;


-- ============================================================
-- MAINTENANCE NOTES
-- ============================================================

CREATE TABLE maintenance_notes (
                                   maintenance_note_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

                                   resource_id BIGINT UNSIGNED NOT NULL,

                                   created_by_user_id BIGINT UNSIGNED NULL,

                                   note TEXT NOT NULL,

                                   started_at DATETIME NOT NULL,

                                   ended_at DATETIME NULL,

                                   created_at TIMESTAMP
                                       NOT NULL DEFAULT CURRENT_TIMESTAMP,

                                   PRIMARY KEY (maintenance_note_id),

                                   CONSTRAINT fk_maintenance_resource
                                       FOREIGN KEY (resource_id)
                                           REFERENCES resources(resource_id)
                                           ON UPDATE CASCADE
                                           ON DELETE RESTRICT,

                                   CONSTRAINT fk_maintenance_created_by
                                       FOREIGN KEY (created_by_user_id)
                                           REFERENCES users(user_id)
                                           ON UPDATE CASCADE
                                           ON DELETE SET NULL,

                                   KEY idx_maintenance_resource_dates (
        resource_id,
        started_at,
        ended_at
    ),

                                   KEY idx_maintenance_created_by (
        created_by_user_id
    )
)
    ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;