USE whiteglove;

-- Minimal account seed for quick bootstrap.
-- Admin:
--   admin@whiteglove.test / admin123
-- Providers:
--   provider1@whiteglove.test / provider123
--   provider2@whiteglove.test / provider234

INSERT INTO users (name, email, password_hash, role, is_active)
VALUES (
    'Super Admin',
    'admin@whiteglove.test',
    '$2y$10$C5hI/grBSIFrSCMx27c7.e0VPPUAyKO1ywoPMlP9N4teiTqh5OcCK',
    'ADMIN',
    1
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    password_hash = VALUES(password_hash),
    role = 'ADMIN',
    is_active = 1;

INSERT INTO users (name, email, password_hash, role, is_active)
VALUES
(
    'Elite Events Team',
    'provider1@whiteglove.test',
    '$2y$10$bchxFXNHtbD0DjN8UoaYKOsfV4CsFbIUFKLZkUa7CAJn8Ad8vLCLK',
    'PROVIDER',
    1
),
(
    'Skyline Celebrations',
    'provider2@whiteglove.test',
    '$2y$10$UMbqNLlGJTd99XGp7Hpbl.ANhnsH850jIiEJzTDCPSxqbLuOxYpEK',
    'PROVIDER',
    1
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    password_hash = VALUES(password_hash),
    role = 'PROVIDER',
    is_active = 1;
