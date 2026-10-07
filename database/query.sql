-- ============================================================
-- ResourceHub
-- query.sql
-- ============================================================

USE cst8319;


-- ============================================================
-- QUERY 1
-- Table counts
-- ============================================================

SELECT 'users' AS table_name, COUNT(*) AS row_count
FROM users

UNION ALL

SELECT 'categories', COUNT(*)
FROM categories

UNION ALL

SELECT 'resources', COUNT(*)
FROM resources

UNION ALL

SELECT 'reservations', COUNT(*)
FROM reservations

UNION ALL

SELECT 'maintenance_notes', COUNT(*)
FROM maintenance_notes;


-- ============================================================
-- QUERY 2
-- Users by role and account status
-- ============================================================

SELECT
    role,
    account_status,
    COUNT(*) AS number_of_users

FROM users

GROUP BY
    role,
    account_status

ORDER BY
    role,
    account_status;


-- ============================================================
-- QUERY 3
-- Complete resource catalogue
-- ============================================================

SELECT
    r.resource_id,
    r.name AS resource_name,
    c.name AS category_name,
    r.location,
    r.status

FROM resources AS r

         JOIN categories AS c
              ON r.category_id = c.category_id

ORDER BY
    c.name,
    r.name;


-- ============================================================
-- QUERY 4
-- Resources by category and status
-- ============================================================

SELECT
    c.name AS category_name,
    r.status,
    COUNT(*) AS resource_count

FROM resources AS r

         JOIN categories AS c
              ON r.category_id = c.category_id

GROUP BY
    c.category_id,
    c.name,
    r.status

ORDER BY
    c.name,
    r.status;


-- ============================================================
-- QUERY 5
-- Available laptops
-- ============================================================

SELECT
    r.resource_id,
    r.name,
    r.location,
    r.status

FROM resources AS r

         JOIN categories AS c
              ON r.category_id = c.category_id

WHERE
    c.name = 'Laptops'
  AND r.status = 'available'

ORDER BY
    r.name;


-- ============================================================
-- QUERY 6
-- Reservation details
-- ============================================================

SELECT
    res.reservation_id,

    u.full_name AS reserved_by,

    r.name AS resource_name,

    c.name AS category_name,

    res.start_datetime,

    res.end_datetime,

    res.status

FROM reservations AS res

         JOIN users AS u
              ON res.user_id = u.user_id

         JOIN resources AS r
              ON res.resource_id = r.resource_id

         JOIN categories AS c
              ON r.category_id = c.category_id

ORDER BY
    res.start_datetime,
    res.reservation_id;


-- ============================================================
-- QUERY 7
-- Reservations by user
-- ============================================================

SELECT
    u.user_id,

    u.full_name,

    SUM(
            CASE
                WHEN res.status = 'active'
                    THEN 1
                ELSE 0
                END
    ) AS active_reservations,

    SUM(
            CASE
                WHEN res.status = 'completed'
                    THEN 1
                ELSE 0
                END
    ) AS completed_reservations,

    SUM(
            CASE
                WHEN res.status = 'cancelled'
                    THEN 1
                ELSE 0
                END
    ) AS cancelled_reservations,

    COUNT(res.reservation_id) AS total_reservations

FROM users AS u

         LEFT JOIN reservations AS res
                   ON u.user_id = res.user_id

GROUP BY
    u.user_id,
    u.full_name

ORDER BY
    total_reservations DESC,
    u.full_name;


-- ============================================================
-- QUERY 8
-- Reservation status
-- ============================================================

SELECT
    status,
    COUNT(*) AS reservation_count

FROM reservations

GROUP BY status

ORDER BY status;


-- ============================================================
-- QUERY 9
-- Upcoming active reservations
-- ============================================================

SELECT
    res.reservation_id,

    u.full_name,

    r.name AS resource_name,

    res.start_datetime,

    res.end_datetime

FROM reservations AS res

         JOIN users AS u
              ON res.user_id = u.user_id

         JOIN resources AS r
              ON res.resource_id = r.resource_id

WHERE
    res.status = 'active'

  AND res.start_datetime >=
      '2026-10-02 00:00:00'

ORDER BY
    res.start_datetime;


-- ============================================================
-- QUERY 10
-- Maintenance history
-- ============================================================

SELECT
    mn.maintenance_note_id,

    r.name AS resource_name,

    r.status AS current_resource_status,

    COALESCE(
            u.full_name,
            '(deleted user)'
    ) AS recorded_by,

    mn.note,

    mn.started_at,

    mn.ended_at,

    CASE
        WHEN mn.ended_at IS NULL
            THEN 'open'
        ELSE
            'closed'
        END AS maintenance_state

FROM maintenance_notes AS mn

         JOIN resources AS r
              ON mn.resource_id = r.resource_id

         LEFT JOIN users AS u
                   ON mn.created_by_user_id = u.user_id

ORDER BY
    mn.started_at DESC;


-- ============================================================
-- QUERY 11
-- Dashboard summary
-- ============================================================

SELECT

    (
        SELECT COUNT(*)
        FROM users
        WHERE account_status = 'active'
    ) AS active_users,

    (
        SELECT COUNT(*)
        FROM resources
    ) AS total_resources,

    (
        SELECT COUNT(*)
        FROM resources
        WHERE status = 'available'
    ) AS available_resources,

    (
        SELECT COUNT(*)
        FROM resources
        WHERE status = 'maintenance'
    ) AS maintenance_resources,

    (
        SELECT COUNT(*)
        FROM resources
        WHERE status = 'inactive'
    ) AS inactive_resources,

    (
        SELECT COUNT(*)
        FROM reservations
        WHERE status = 'active'
    ) AS active_reservations;


-- ============================================================
-- QUERY 12
-- Conflict detection
-- ============================================================

SET @resource_id = 1;

SET @new_start =
    '2026-10-05 10:00:00';

SET @new_end =
    '2026-10-05 11:30:00';


SELECT
    reservation_id,
    resource_id,
    start_datetime,
    end_datetime,
    status

FROM reservations

WHERE
    resource_id = @resource_id

  AND status = 'active'

  AND @new_start < end_datetime

  AND @new_end > start_datetime;


-- ============================================================
-- QUERY 13
-- Available resources for requested time
-- ============================================================

SET @requested_start =
    '2026-10-05 10:00:00';

SET @requested_end =
    '2026-10-05 11:30:00';


SELECT
    r.resource_id,

    r.name,

    c.name AS category_name,

    r.location

FROM resources AS r

         JOIN categories AS c
              ON r.category_id = c.category_id

WHERE
    r.status = 'available'

  AND NOT EXISTS
    (
        SELECT 1

        FROM reservations AS res

        WHERE
            res.resource_id = r.resource_id

          AND res.status = 'active'

          AND @requested_start < res.end_datetime

          AND @requested_end > res.start_datetime
    )

ORDER BY
    c.name,
    r.name;


-- ============================================================
-- QUERY 14
-- Referential integrity
-- ============================================================

SELECT

    (
        SELECT COUNT(*)

        FROM resources AS r

                 LEFT JOIN categories AS c
                           ON r.category_id = c.category_id

        WHERE c.category_id IS NULL
    ) AS resources_without_category,


    (
        SELECT COUNT(*)

        FROM reservations AS res

                 LEFT JOIN users AS u
                           ON res.user_id = u.user_id

        WHERE u.user_id IS NULL
    ) AS reservations_without_user,


    (
        SELECT COUNT(*)

        FROM reservations AS res

                 LEFT JOIN resources AS r
                           ON res.resource_id = r.resource_id

        WHERE r.resource_id IS NULL
    ) AS reservations_without_resource,


    (
        SELECT COUNT(*)

        FROM maintenance_notes AS mn

                 LEFT JOIN resources AS r
                           ON mn.resource_id = r.resource_id

        WHERE r.resource_id IS NULL
    ) AS maintenance_without_resource;