USE whiteglove;

START TRANSACTION;

SET @provider_elite := (
    SELECT id FROM users WHERE email = 'provider1@whiteglove.test' AND role = 'PROVIDER' LIMIT 1
);
SET @provider_skyline := (
    SELECT id FROM users WHERE email = 'provider2@whiteglove.test' AND role = 'PROVIDER' LIMIT 1
);
SET @provider_fallback := (
    SELECT id FROM users WHERE role = 'PROVIDER' ORDER BY id LIMIT 1
);
SET @provider_elite := COALESCE(@provider_elite, @provider_fallback);
SET @provider_skyline := COALESCE(@provider_skyline, @provider_fallback);

DELETE sa
FROM service_availability sa
INNER JOIN services s ON s.id = sa.service_id
WHERE s.title IN (
    'Palace Wedding Signature Collection',
    'Ultra Luxury Destination Wedding',
    'Celebrity Sangeet Live Experience',
    'Executive Global Summit Concierge',
    'Luxury Product Reveal Gala',
    'Platinum Anniversary Grand Soiree',
    'Grand Awards & Red Carpet Night',
    'High-Profile Investor Forum',
    'Royal Engagement Spectacle',
    'Luxury Waterfront Reception',
    'Bespoke Heritage Mehendi Festival',
    'Premium International Conference Design'
);

DELETE si
FROM service_images si
INNER JOIN services s ON s.id = si.service_id
WHERE s.title IN (
    'Palace Wedding Signature Collection',
    'Ultra Luxury Destination Wedding',
    'Celebrity Sangeet Live Experience',
    'Executive Global Summit Concierge',
    'Luxury Product Reveal Gala',
    'Platinum Anniversary Grand Soiree',
    'Grand Awards & Red Carpet Night',
    'High-Profile Investor Forum',
    'Royal Engagement Spectacle',
    'Luxury Waterfront Reception',
    'Bespoke Heritage Mehendi Festival',
    'Premium International Conference Design'
);

DELETE FROM services
WHERE title IN (
    'Palace Wedding Signature Collection',
    'Ultra Luxury Destination Wedding',
    'Celebrity Sangeet Live Experience',
    'Executive Global Summit Concierge',
    'Luxury Product Reveal Gala',
    'Platinum Anniversary Grand Soiree',
    'Grand Awards & Red Carpet Night',
    'High-Profile Investor Forum',
    'Royal Engagement Spectacle',
    'Luxury Waterfront Reception',
    'Bespoke Heritage Mehendi Festival',
    'Premium International Conference Design'
);

INSERT INTO services (provider_id, title, city, event_type, description, base_price, status) VALUES
(@provider_elite,   'Palace Wedding Signature Collection',      'Udaipur',   'Wedding',     'Premium palace wedding production with full hospitality, luxury decor concepts, and concierge-grade guest operations.', 950000.00, 'ACTIVE'),
(@provider_skyline, 'Ultra Luxury Destination Wedding',         'Goa',       'Wedding',     'End-to-end destination wedding execution with multi-day ceremony curation and premium vendor orchestration.',           1250000.00, 'ACTIVE'),
(@provider_elite,   'Celebrity Sangeet Live Experience',        'Mumbai',    'Wedding',     'Celebrity-style sangeet design with stage choreography, live acts, backstage and cue management.',                      780000.00, 'ACTIVE'),
(@provider_skyline, 'Executive Global Summit Concierge',        'Bengaluru', 'Corporate',   'C-suite summit concierge with premium registration, protocol desk, interpretation support, and executive lounges.',    890000.00, 'ACTIVE'),
(@provider_elite,   'Luxury Product Reveal Gala',               'Delhi',     'Corporate',   'Flagship product launch gala featuring cinematic reveal flows, media zone design, and VIP access control.',             840000.00, 'ACTIVE'),
(@provider_skyline, 'Platinum Anniversary Grand Soiree',        'Chennai',   'Anniversary', 'Luxury anniversary soirée with immersive storytelling, signature dining, and hospitality-led event direction.',          520000.00, 'ACTIVE'),
(@provider_elite,   'Grand Awards & Red Carpet Night',          'Hyderabad', 'Awards',      'Red-carpet awards execution with nominee logistics, scripted flow, and premium on-ground operations.',                  690000.00, 'ACTIVE'),
(@provider_skyline, 'High-Profile Investor Forum',              'Pune',      'Conference',  'Investor forum management with private networking lounges, investor journey mapping, and media-ready operations.',      610000.00, 'ACTIVE'),
(@provider_elite,   'Royal Engagement Spectacle',               'Jaipur',    'Engagement',  'Regal engagement celebration with bespoke decor language, premium entertainment, and curated guest experiences.',       560000.00, 'ACTIVE'),
(@provider_skyline, 'Luxury Waterfront Reception',              'Kochi',     'Reception',   'Waterfront reception concept with architectural lighting, luxury seating design, and precision timeline operations.',    640000.00, 'ACTIVE'),
(@provider_elite,   'Bespoke Heritage Mehendi Festival',        'Ahmedabad', 'Wedding',     'Heritage-themed mehendi festival with artisan zones, live folk experiences, and hospitality-focused guest flow.',       470000.00, 'ACTIVE'),
(@provider_skyline, 'Premium International Conference Design',  'Kolkata',   'Conference',  'International conference blueprint with delegate desk, multilingual session tracks, and premium stagecraft.',           730000.00, 'ACTIVE');

INSERT INTO service_images (service_id, image_url, sort_order)
SELECT s.id, x.image_url, x.sort_order
FROM services s
INNER JOIN (
    SELECT 'Palace Wedding Signature Collection' AS title, 'https://images.unsplash.com/photo-1519225421980-715cb0215aed?auto=format&fit=crop&w=1200&q=80' AS image_url, 0 AS sort_order UNION ALL
    SELECT 'Ultra Luxury Destination Wedding', 'https://images.unsplash.com/photo-1520854221256-17451cc331bf?auto=format&fit=crop&w=1200&q=80', 0 UNION ALL
    SELECT 'Celebrity Sangeet Live Experience', 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?auto=format&fit=crop&w=1200&q=80', 0 UNION ALL
    SELECT 'Executive Global Summit Concierge', 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?auto=format&fit=crop&w=1200&q=80', 0 UNION ALL
    SELECT 'Luxury Product Reveal Gala', 'https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&w=1200&q=80', 0 UNION ALL
    SELECT 'Platinum Anniversary Grand Soiree', 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=1200&q=80', 0 UNION ALL
    SELECT 'Grand Awards & Red Carpet Night', 'https://images.unsplash.com/photo-1519671482749-fd09be7ccebf?auto=format&fit=crop&w=1200&q=80', 0 UNION ALL
    SELECT 'High-Profile Investor Forum', 'https://images.unsplash.com/photo-1511578314322-379afb476865?auto=format&fit=crop&w=1200&q=80', 0 UNION ALL
    SELECT 'Royal Engagement Spectacle', 'https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&w=1200&q=80', 0 UNION ALL
    SELECT 'Luxury Waterfront Reception', 'https://images.unsplash.com/photo-1469371670807-013ccf25f16a?auto=format&fit=crop&w=1200&q=80', 0 UNION ALL
    SELECT 'Bespoke Heritage Mehendi Festival', 'https://images.unsplash.com/photo-1522673607200-164d1b6ce486?auto=format&fit=crop&w=1200&q=80', 0 UNION ALL
    SELECT 'Premium International Conference Design', 'https://images.unsplash.com/photo-1511578314322-379afb476865?auto=format&fit=crop&w=1200&q=80', 1
) x ON x.title = s.title;

INSERT IGNORE INTO service_availability (service_id, slot_date, slot_status)
SELECT s.id, a.slot_date, a.slot_status
FROM services s
INNER JOIN (
    SELECT 'Palace Wedding Signature Collection' AS title, '2026-06-21' AS slot_date, 'AVAILABLE' AS slot_status UNION ALL
    SELECT 'Palace Wedding Signature Collection', '2026-07-19', 'AVAILABLE' UNION ALL
    SELECT 'Ultra Luxury Destination Wedding', '2026-08-16', 'AVAILABLE' UNION ALL
    SELECT 'Ultra Luxury Destination Wedding', '2026-09-13', 'BLOCKED' UNION ALL
    SELECT 'Celebrity Sangeet Live Experience', '2026-06-28', 'AVAILABLE' UNION ALL
    SELECT 'Celebrity Sangeet Live Experience', '2026-07-26', 'AVAILABLE' UNION ALL
    SELECT 'Executive Global Summit Concierge', '2026-07-03', 'AVAILABLE' UNION ALL
    SELECT 'Executive Global Summit Concierge', '2026-08-07', 'AVAILABLE' UNION ALL
    SELECT 'Luxury Product Reveal Gala', '2026-06-30', 'AVAILABLE' UNION ALL
    SELECT 'Luxury Product Reveal Gala', '2026-07-31', 'BLOCKED' UNION ALL
    SELECT 'Platinum Anniversary Grand Soiree', '2026-07-12', 'AVAILABLE' UNION ALL
    SELECT 'Platinum Anniversary Grand Soiree', '2026-08-09', 'AVAILABLE' UNION ALL
    SELECT 'Grand Awards & Red Carpet Night', '2026-08-02', 'AVAILABLE' UNION ALL
    SELECT 'Grand Awards & Red Carpet Night', '2026-09-06', 'AVAILABLE' UNION ALL
    SELECT 'High-Profile Investor Forum', '2026-06-26', 'AVAILABLE' UNION ALL
    SELECT 'High-Profile Investor Forum', '2026-07-24', 'AVAILABLE' UNION ALL
    SELECT 'Royal Engagement Spectacle', '2026-07-05', 'AVAILABLE' UNION ALL
    SELECT 'Royal Engagement Spectacle', '2026-08-30', 'BLOCKED' UNION ALL
    SELECT 'Luxury Waterfront Reception', '2026-06-20', 'AVAILABLE' UNION ALL
    SELECT 'Luxury Waterfront Reception', '2026-07-18', 'AVAILABLE' UNION ALL
    SELECT 'Bespoke Heritage Mehendi Festival', '2026-06-14', 'AVAILABLE' UNION ALL
    SELECT 'Bespoke Heritage Mehendi Festival', '2026-07-11', 'AVAILABLE' UNION ALL
    SELECT 'Premium International Conference Design', '2026-08-21', 'AVAILABLE' UNION ALL
    SELECT 'Premium International Conference Design', '2026-09-18', 'AVAILABLE'
) a ON a.title = s.title;

COMMIT;

