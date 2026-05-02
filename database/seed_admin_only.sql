USE whiteglove;

-- Minimal account seed for quick bootstrap.
-- Admin:
--   admin@whiteglove.test / admin123
-- Client:
--   client@whiteglove.test / client123
-- Providers:
--   provider1@whiteglove.test / provider123
--   provider2@whiteglove.test / provider234

INSERT INTO users (name, email, password_hash, profile_image_url, role, is_active)
VALUES (
    'Super Admin',
    'admin@whiteglove.test',
    '$2y$10$C5hI/grBSIFrSCMx27c7.e0VPPUAyKO1ywoPMlP9N4teiTqh5OcCK',
    'https://images.unsplash.com/photo-1568602471122-7832951cc4c5?auto=format&fit=crop&w=400&q=80',
    'ADMIN',
    1
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    password_hash = VALUES(password_hash),
    profile_image_url = VALUES(profile_image_url),
    role = 'ADMIN',
    is_active = 1;

INSERT INTO users (name, email, password_hash, profile_image_url, role, is_active)
VALUES
(
    'Riya Client',
    'client@whiteglove.test',
    '$2y$10$rsu3gpl7e9ruo6/D2a8bdufkD5FvAAtLc066dLE6yS.SWZzID4cYq',
    'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=400&q=80',
    'CLIENT',
    1
),
(
    'Elite Events Team',
    'provider1@whiteglove.test',
    '$2y$10$bchxFXNHtbD0DjN8UoaYKOsfV4CsFbIUFKLZkUa7CAJn8Ad8vLCLK',
    'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=400&q=80',
    'PROVIDER',
    1
),
(
    'Skyline Celebrations',
    'provider2@whiteglove.test',
    '$2y$10$UMbqNLlGJTd99XGp7Hpbl.ANhnsH850jIiEJzTDCPSxqbLuOxYpEK',
    'https://images.unsplash.com/photo-1541534401786-2077eed87a72?auto=format&fit=crop&w=400&q=80',
    'PROVIDER',
    1
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    password_hash = VALUES(password_hash),
    profile_image_url = VALUES(profile_image_url),
    role = VALUES(role),
    is_active = 1;

SET @provider1_id := (
    SELECT id FROM users WHERE email = 'provider1@whiteglove.test' LIMIT 1
);
SET @provider2_id := (
    SELECT id FROM users WHERE email = 'provider2@whiteglove.test' LIMIT 1
);

INSERT INTO provider_profiles (user_id, business_name, city, description, profile_image_url, approval_status)
VALUES
(
    @provider1_id,
    'Elite Events Pvt Ltd',
    'Kolkata',
    'Premium wedding and corporate event execution.',
    'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=400&q=80',
    'APPROVED'
),
(
    @provider2_id,
    'Skyline Celebrations',
    'Bengaluru',
    'Corporate conference and social event planner.',
    'https://images.unsplash.com/photo-1541534401786-2077eed87a72?auto=format&fit=crop&w=400&q=80',
    'APPROVED'
)
ON DUPLICATE KEY UPDATE
    business_name = VALUES(business_name),
    city = VALUES(city),
    description = VALUES(description),
    profile_image_url = VALUES(profile_image_url),
    approval_status = VALUES(approval_status);
