-- ============================================================
-- ResourceHub
-- data.sql
--
-- Run AFTER schema.sql
--
-- Test password:
-- ResourceHub123!
-- ============================================================

USE cst8319;

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE maintenance_notes;
TRUNCATE TABLE reservations;
TRUNCATE TABLE resources;
TRUNCATE TABLE categories;
TRUNCATE TABLE users;

SET FOREIGN_KEY_CHECKS = 1;


-- ============================================================
-- USERS
-- ============================================================

INSERT INTO users
(
    user_id,
    full_name,
    email,
    password_hash,
    role,
    account_status
)
VALUES

    (
        1,
        'ResourceHub Administrator',
        'admin@resourcehub.test',
        '$2y$12$ln88NoRPEJgL203a77Jp/u4viM.x9l7XD3o0zejeBSkVbq5x4rkSC',
        'admin',
        'active'
    ),

    (
        2,
        'Thian Zanniat Laizo',
        'thian@resourcehub.test',
        '$2y$12$9eNcXXCBacgjbY7GQaUeeOcUFx5w4UY2K/Rk47CdQR54YbMfeCq7W',
        'user',
        'active'
    ),

    (
        3,
        'Jasper Godse',
        'jasper@resourcehub.test',
        '$2y$12$HCx6DMigtNoyvK9gv4qqcOH9..S66PwWJt2GpEC7XlH3qop76V1KW',
        'user',
        'active'
    ),

    (
        4,
        'Nicholas Yu',
        'nicholas@resourcehub.test',
        '$2y$12$XC4/i0jONHNwfOdc.IHsxuOSpNyV.G5WatzINECJGrj1w1Hcs7VOW',
        'user',
        'active'
    ),

    (
        5,
        'Hassan Abou-Abbas',
        'hassan@resourcehub.test',
        '$2y$12$R4n2LsOhZk662Bul5plWr.8t3WifIRaaZ2X0H3bgbXNzH0cgCFJ3e',
        'user',
        'active'
    ),

    (
        6,
        'Jonathan Rock',
        'jonathan@resourcehub.test',
        '$2y$12$PuWb2.eIx2dKzTdJio3Eqe0N1JZ5FObWjlzcaFnjE9ofoeqtrK0fa',
        'user',
        'inactive'
    );


-- ============================================================
-- CATEGORIES
-- ============================================================

INSERT INTO categories
(
    category_id,
    name,
    description
)
VALUES

    (1, 'Laptops',
     'Portable computers available for short-term reservation.'),

    (2, 'Cameras',
     'Still and video cameras used for media projects.'),

    (3, 'Projectors',
     'Presentation projectors and related display equipment.'),

    (4, 'Meeting Rooms',
     'Shared rooms that can be reserved for meetings or group work.'),

    (5, 'Audio / Visual',
     'Audio recording and general audiovisual equipment.'),

    (6, 'Tools',
     'Shared technical and workshop tools.');


-- ============================================================
-- RESOURCES
-- ============================================================

INSERT INTO resources
(
    resource_id,
    category_id,
    name,
    description,
    location,
    status
)
VALUES

    (
        1,
        1,
        'Dell Latitude Laptop 01',
        'Windows laptop for general coursework and presentations.',
        'Equipment Room A',
        'available'
    ),

    (
        2,
        1,
        'Dell Latitude Laptop 02',
        'Windows laptop for general coursework and presentations.',
        'Equipment Room A',
        'available'
    ),

    (
        3,
        1,
        'MacBook Pro 01',
        'Mac laptop for media and development work.',
        'Equipment Room A',
        'inactive'
    ),

    (
        4,
        2,
        'Canon EOS Camera 01',
        'DSLR camera with standard lens kit.',
        'Media Room',
        'available'
    ),

    (
        5,
        2,
        'Sony Video Camera 01',
        'Video camera with battery and carrying case.',
        'Media Room',
        'maintenance'
    ),

    (
        6,
        3,
        'Epson Projector 01',
        'Portable projector with HDMI cable.',
        'Equipment Room B',
        'available'
    ),

    (
        7,
        3,
        'BenQ Projector 01',
        'Meeting-room projector with HDMI input.',
        'Equipment Room B',
        'available'
    ),

    (
        8,
        4,
        'Meeting Room A',
        'Small meeting room with seating for six.',
        'Second Floor',
        'available'
    ),

    (
        9,
        4,
        'Meeting Room B',
        'Meeting room with display and seating for ten.',
        'Second Floor',
        'maintenance'
    ),

    (
        10,
        5,
        'Zoom H6 Recorder',
        'Portable multitrack audio recorder.',
        'Media Room',
        'available'
    ),

    (
        11,
        5,
        'Tripod Kit 01',
        'Adjustable tripod for camera or video equipment.',
        'Media Room',
        'available'
    ),

    (
        12,
        6,
        'Cordless Drill 01',
        'Cordless drill with battery and charger.',
        'Workshop',
        'inactive'
    );


-- ============================================================
-- RESERVATIONS
-- ============================================================

INSERT INTO reservations
(
    reservation_id,
    user_id,
    resource_id,
    start_datetime,
    end_datetime,
    status,
    cancelled_at
)
VALUES

    (
        1,
        2,
        1,
        '2026-10-05 09:00:00',
        '2026-10-05 12:00:00',
        'active',
        NULL
    ),

    (
        2,
        3,
        1,
        '2026-10-05 13:00:00',
        '2026-10-05 16:00:00',
        'active',
        NULL
    ),

    (
        3,
        4,
        4,
        '2026-10-06 14:00:00',
        '2026-10-06 17:00:00',
        'active',
        NULL
    ),

    (
        4,
        5,
        6,
        '2026-10-07 10:00:00',
        '2026-10-07 12:00:00',
        'active',
        NULL
    ),

    (
        5,
        2,
        8,
        '2026-10-08 15:00:00',
        '2026-10-08 17:00:00',
        'active',
        NULL
    ),

    (
        6,
        3,
        10,
        '2026-09-20 09:00:00',
        '2026-09-20 11:00:00',
        'completed',
        NULL
    ),

    (
        7,
        4,
        7,
        '2026-09-22 13:00:00',
        '2026-09-22 15:00:00',
        'completed',
        NULL
    ),

    (
        8,
        5,
        1,
        '2026-10-05 10:00:00',
        '2026-10-05 11:00:00',
        'cancelled',
        '2026-10-01 09:15:00'
    ),

    (
        9,
        3,
        11,
        '2026-10-12 08:30:00',
        '2026-10-12 12:30:00',
        'active',
        NULL
    ),

    (
        10,
        2,
        4,
        '2026-10-14 10:00:00',
        '2026-10-14 12:00:00',
        'cancelled',
        '2026-10-02 08:00:00'
    );


-- ============================================================
-- MAINTENANCE NOTES
-- ============================================================

INSERT INTO maintenance_notes
(
    maintenance_note_id,
    resource_id,
    created_by_user_id,
    note,
    started_at,
    ended_at
)
VALUES

    (
        1,
        5,
        1,
        'Battery is not holding charge. Remove from booking until replacement battery is installed.',
        '2026-10-01 08:30:00',
        NULL
    ),

    (
        2,
        9,
        1,
        'Display connection is intermittent. Room unavailable while cable and wall plate are tested.',
        '2026-10-02 09:00:00',
        NULL
    ),

    (
        3,
        6,
        1,
        'HDMI cable replaced and projector tested successfully.',
        '2026-09-15 13:00:00',
        '2026-09-16 10:00:00'
    );