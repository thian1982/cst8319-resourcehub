-- Demo data for a fresh LOCAL database only. Never run this on the DreamHost database.
-- Import after schema.sql. All three accounts use the password LocalDemoOnly-2026!
USE cst8319;
START TRANSACTION;

INSERT INTO categories (category_id, name, description) VALUES
(1, 'Projectors', 'Portable projectors for classes and meetings'),
(2, 'Laptops', 'Loaner laptops'),
(3, 'Cameras', 'Photo and video cameras'),
(4, 'Meeting Rooms', 'Bookable rooms'),
(5, 'Audio', 'Microphones and speakers');

-- Resource 1 must stay the projector, tests/database_checks.py looks for it.
INSERT INTO resources (resource_id, category_id, name, description, location, status) VALUES
(1, 1, 'Demo projector', 'Epson 1080p projector with HDMI cable and remote.', 'Room B-210', 'available'),
(2, 1, 'Mini projector', 'Small battery projector for presentations on the go.', 'Room B-210', 'maintenance'),
(3, 2, 'Dell Latitude laptop', '14 inch laptop with Office and a charger.', 'IT Desk', 'available'),
(4, 2, 'MacBook Air', '13 inch MacBook Air for media work.', 'IT Desk', 'available'),
(5, 3, 'Canon DSLR camera', 'Canon EOS with 18-55mm lens and memory card.', 'Media Lab', 'available'),
(6, 4, 'Meeting Room A', 'Seats 8, TV screen and whiteboard.', 'Building C, 1st floor', 'available'),
(7, 5, 'Wireless microphone set', 'Two handheld mics with receiver.', 'Media Lab', 'available'),
(8, 3, 'Old camcorder', 'Retired, kept for records.', 'Storage', 'inactive');

INSERT INTO users (user_id, full_name, email, password_hash, role) VALUES
(1, 'Demo User', 'user@example.test', '$2y$10$kvt4ohX06gFfM4gV0r9Qo.xz39E.29OwO8Whbv45ITW8J/oXxtIAC', 'user'),
(2, 'Demo Admin', 'admin@example.test', '$2y$10$kvt4ohX06gFfM4gV0r9Qo.xz39E.29OwO8Whbv45ITW8J/oXxtIAC', 'admin'),
(3, 'Sam Student', 'sam@example.test', '$2y$10$kvt4ohX06gFfM4gV0r9Qo.xz39E.29OwO8Whbv45ITW8J/oXxtIAC', 'user');

INSERT INTO maintenance_notes (resource_id, created_by_user_id, note, started_at) VALUES
(2, 2, 'Bulb is flickering, waiting on a replacement part.', NOW());

COMMIT;
