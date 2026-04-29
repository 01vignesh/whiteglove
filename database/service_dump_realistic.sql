USE whiteglove;

START TRANSACTION;

-- Resolve provider IDs dynamically (works even if IDs differ across environments).
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

-- Optional cleanup for idempotent re-runs (only removes titles in this dump).
DELETE sa
FROM service_availability sa
INNER JOIN services s ON s.id = sa.service_id
WHERE s.title IN (
    'Grand Heritage Wedding Experience',
    'Sunset Beach Wedding Planner',
    'Intimate Temple Wedding Setup',
    'Luxury Engagement Evening',
    'Corporate Townhall Production',
    'Annual Tech Summit Management',
    'Product Launch Premiere',
    'Startup Demo Day Operations',
    'Premium Birthday Theme Party',
    'Kids Adventure Birthday Bash',
    'Silver Jubilee Celebration Package',
    'Destination Anniversary Planner',
    'College Fest Stage & Crowd Management',
    'Cultural Night Production Suite',
    'Conference Registration & Logistics Desk',
    'Premium Decor + Catering Combo',
    'Micro Wedding Essentials Pack',
    'Sangeet & Mehendi Celebration',
    'Community Awards Night Setup',
    'Luxury Baby Shower Curation'
);

DELETE si
FROM service_images si
INNER JOIN services s ON s.id = si.service_id
WHERE s.title IN (
    'Grand Heritage Wedding Experience',
    'Sunset Beach Wedding Planner',
    'Intimate Temple Wedding Setup',
    'Luxury Engagement Evening',
    'Corporate Townhall Production',
    'Annual Tech Summit Management',
    'Product Launch Premiere',
    'Startup Demo Day Operations',
    'Premium Birthday Theme Party',
    'Kids Adventure Birthday Bash',
    'Silver Jubilee Celebration Package',
    'Destination Anniversary Planner',
    'College Fest Stage & Crowd Management',
    'Cultural Night Production Suite',
    'Conference Registration & Logistics Desk',
    'Premium Decor + Catering Combo',
    'Micro Wedding Essentials Pack',
    'Sangeet & Mehendi Celebration',
    'Community Awards Night Setup',
    'Luxury Baby Shower Curation'
);

DELETE FROM services
WHERE title IN (
    'Grand Heritage Wedding Experience',
    'Sunset Beach Wedding Planner',
    'Intimate Temple Wedding Setup',
    'Luxury Engagement Evening',
    'Corporate Townhall Production',
    'Annual Tech Summit Management',
    'Product Launch Premiere',
    'Startup Demo Day Operations',
    'Premium Birthday Theme Party',
    'Kids Adventure Birthday Bash',
    'Silver Jubilee Celebration Package',
    'Destination Anniversary Planner',
    'College Fest Stage & Crowd Management',
    'Cultural Night Production Suite',
    'Conference Registration & Logistics Desk',
    'Premium Decor + Catering Combo',
    'Micro Wedding Essentials Pack',
    'Sangeet & Mehendi Celebration',
    'Community Awards Night Setup',
    'Luxury Baby Shower Curation'
);

INSERT INTO services (provider_id, title, city, event_type, description, base_price, status) VALUES
(@provider_elite,   'Grand Heritage Wedding Experience',      'Jaipur',     'Wedding',      'End-to-end heritage wedding production with decor, artist management, guest logistics, and ceremony coordination.', 480000.00, 'ACTIVE'),
(@provider_skyline, 'Sunset Beach Wedding Planner',           'Goa',        'Wedding',      'Beachside wedding planning with sunset-stage setup, hospitality desk, and weather fallback planning.',               520000.00, 'ACTIVE'),
(@provider_elite,   'Intimate Temple Wedding Setup',          'Udupi',      'Wedding',      'Traditional wedding planning for close families with ritual flow support and compact decor design.',                 185000.00, 'ACTIVE'),
(@provider_skyline, 'Luxury Engagement Evening',              'Bengaluru',  'Engagement',   'Stylish engagement evening with LED backdrop, entry choreography, and curated dinner ambience.',                    210000.00, 'ACTIVE'),
(@provider_elite,   'Corporate Townhall Production',          'Mumbai',     'Corporate',    'Corporate townhall package covering stage design, audio-visual integration, and guest movement control.',           265000.00, 'ACTIVE'),
(@provider_skyline, 'Annual Tech Summit Management',          'Hyderabad',  'Corporate',    'Multi-track summit operations including speaker lounge, registration desk, and technical runbooks.',                340000.00, 'ACTIVE'),
(@provider_elite,   'Product Launch Premiere',                'Delhi',      'Corporate',    'High-impact product launch with reveal moments, press zone setup, and live-stream support.',                        390000.00, 'ACTIVE'),
(@provider_skyline, 'Startup Demo Day Operations',            'Pune',       'Corporate',    'Demo day planning with startup stalls, investor seating map, and stage rotation timelines.',                         145000.00, 'ACTIVE'),
(@provider_elite,   'Premium Birthday Theme Party',           'Bengaluru',  'Birthday',     'Premium birthday concept execution with themed decor, activity stations, and entertainment handling.',              95000.00,  'ACTIVE'),
(@provider_skyline, 'Kids Adventure Birthday Bash',           'Mysuru',     'Birthday',     'Kids adventure birthday format with safety-first game zones and age-specific flow management.',                      78000.00,  'ACTIVE'),
(@provider_elite,   'Silver Jubilee Celebration Package',     'Chennai',    'Anniversary',  'Anniversary celebration package with couple spotlight moments, memory wall, and dinner management.',                165000.00, 'ACTIVE'),
(@provider_skyline, 'Destination Anniversary Planner',        'Coorg',      'Anniversary',  'Destination anniversary planning with stay coordination, scenic decor, and guest itinerary support.',               245000.00, 'ACTIVE'),
(@provider_elite,   'College Fest Stage & Crowd Management',  'Manipal',    'College Fest', 'Campus fest event operations including stage turnaround, artist coordination, and crowd zoning.',                    310000.00, 'ACTIVE'),
(@provider_skyline, 'Cultural Night Production Suite',        'Kolkata',    'College Fest', 'Cultural-night production with sequential performances, lighting cues, and backstage control.',                      225000.00, 'ACTIVE'),
(@provider_elite,   'Conference Registration & Logistics Desk','Kochi',     'Conference',   'Conference logistics unit focused on registration, badges, seating plans, and help-desk operations.',               130000.00, 'ACTIVE'),
(@provider_skyline, 'Premium Decor + Catering Combo',         'Mangalore',  'Wedding',      'Integrated decor and catering coordination with tasting workflow and vendor harmonization.',                          275000.00, 'ACTIVE'),
(@provider_elite,   'Micro Wedding Essentials Pack',          'Manipal',    'Wedding',      'Budget-conscious micro wedding package for intimate gatherings with efficient planning support.',                    120000.00, 'ACTIVE'),
(@provider_skyline, 'Sangeet & Mehendi Celebration',          'Surat',      'Wedding',      'Sangeet and mehendi setup with choreography flow, thematic lounge, and artist slot planning.',                      230000.00, 'ACTIVE'),
(@provider_elite,   'Community Awards Night Setup',           'Ahmedabad',  'Awards',       'Awards-night execution with nominee handling, sequence scripting, and stage transition operations.',                205000.00, 'ACTIVE'),
(@provider_skyline, 'Luxury Baby Shower Curation',            'Bengaluru',  'Baby Shower',  'Elegant baby-shower planning with pastel decor direction, games, and guest engagement curation.',                  88000.00,  'ACTIVE');

INSERT INTO service_images (service_id, image_url, sort_order)
SELECT s.id, x.image_url, x.sort_order
FROM services s
INNER JOIN (
    SELECT 'Grand Heritage Wedding Experience' AS title, 'https://images.unsplash.com/photo-1511795409834-ef04bbd61622?auto=format&fit=crop&w=1200&q=80' AS image_url, 0 AS sort_order UNION ALL
    SELECT 'Grand Heritage Wedding Experience', 'https://images.unsplash.com/photo-1522673607200-164d1b6ce486?auto=format&fit=crop&w=1200&q=80', 1 UNION ALL
    SELECT 'Sunset Beach Wedding Planner', 'https://images.unsplash.com/photo-1520854221256-17451cc331bf?auto=format&fit=crop&w=1200&q=80', 0 UNION ALL
    SELECT 'Intimate Temple Wedding Setup', 'https://images.unsplash.com/photo-1460978812857-470ed1c77af0?auto=format&fit=crop&w=1200&q=80', 0 UNION ALL
    SELECT 'Luxury Engagement Evening', 'https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&w=1200&q=80', 0 UNION ALL
    SELECT 'Corporate Townhall Production', 'https://images.unsplash.com/photo-1511578314322-379afb476865?auto=format&fit=crop&w=1200&q=80', 0 UNION ALL
    SELECT 'Annual Tech Summit Management', 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?auto=format&fit=crop&w=1200&q=80', 0 UNION ALL
    SELECT 'Product Launch Premiere', 'https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&w=1200&q=80', 0 UNION ALL
    SELECT 'Startup Demo Day Operations', 'https://images.unsplash.com/photo-1521737604893-d14cc237f11d?auto=format&fit=crop&w=1200&q=80', 0 UNION ALL
    SELECT 'Premium Birthday Theme Party', 'https://images.unsplash.com/photo-1464349095431-e9a21285b5f3?auto=format&fit=crop&w=1200&q=80', 0 UNION ALL
    SELECT 'Kids Adventure Birthday Bash', 'https://images.unsplash.com/photo-1503454537195-1dcabb73ffb9?auto=format&fit=crop&w=1200&q=80', 0 UNION ALL
    SELECT 'Silver Jubilee Celebration Package', 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?auto=format&fit=crop&w=1200&q=80', 0 UNION ALL
    SELECT 'Destination Anniversary Planner', 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=1200&q=80', 0 UNION ALL
    SELECT 'College Fest Stage & Crowd Management', 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?auto=format&fit=crop&w=1200&q=80', 0 UNION ALL
    SELECT 'Cultural Night Production Suite', 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?auto=format&fit=crop&w=1200&q=80', 0 UNION ALL
    SELECT 'Conference Registration & Logistics Desk', 'https://images.unsplash.com/photo-1511578314322-379afb476865?auto=format&fit=crop&w=1200&q=80', 0 UNION ALL
    SELECT 'Premium Decor + Catering Combo', 'https://images.unsplash.com/photo-1469371670807-013ccf25f16a?auto=format&fit=crop&w=1200&q=80', 0 UNION ALL
    SELECT 'Micro Wedding Essentials Pack', 'https://images.unsplash.com/photo-1529636798458-92182e662485?auto=format&fit=crop&w=1200&q=80', 0 UNION ALL
    SELECT 'Sangeet & Mehendi Celebration', 'https://images.unsplash.com/photo-1519225421980-715cb0215aed?auto=format&fit=crop&w=1200&q=80', 0 UNION ALL
    SELECT 'Community Awards Night Setup', 'https://images.unsplash.com/photo-1519671482749-fd09be7ccebf?auto=format&fit=crop&w=1200&q=80', 0 UNION ALL
    SELECT 'Luxury Baby Shower Curation', 'https://images.unsplash.com/photo-1530103862676-de8c9debad1d?auto=format&fit=crop&w=1200&q=80', 0
) x ON x.title = s.title;

INSERT IGNORE INTO service_availability (service_id, slot_date, slot_status)
SELECT s.id, a.slot_date, a.slot_status
FROM services s
INNER JOIN (
    SELECT 'Grand Heritage Wedding Experience' AS title, '2026-07-12' AS slot_date, 'AVAILABLE' AS slot_status UNION ALL
    SELECT 'Grand Heritage Wedding Experience', '2026-08-09', 'AVAILABLE' UNION ALL
    SELECT 'Grand Heritage Wedding Experience', '2026-09-20', 'BLOCKED' UNION ALL
    SELECT 'Sunset Beach Wedding Planner', '2026-07-18', 'AVAILABLE' UNION ALL
    SELECT 'Sunset Beach Wedding Planner', '2026-08-22', 'AVAILABLE' UNION ALL
    SELECT 'Intimate Temple Wedding Setup', '2026-06-14', 'AVAILABLE' UNION ALL
    SELECT 'Intimate Temple Wedding Setup', '2026-07-05', 'AVAILABLE' UNION ALL
    SELECT 'Luxury Engagement Evening', '2026-06-27', 'AVAILABLE' UNION ALL
    SELECT 'Luxury Engagement Evening', '2026-07-25', 'BLOCKED' UNION ALL
    SELECT 'Corporate Townhall Production', '2026-06-30', 'AVAILABLE' UNION ALL
    SELECT 'Corporate Townhall Production', '2026-07-30', 'AVAILABLE' UNION ALL
    SELECT 'Annual Tech Summit Management', '2026-08-14', 'AVAILABLE' UNION ALL
    SELECT 'Annual Tech Summit Management', '2026-09-11', 'AVAILABLE' UNION ALL
    SELECT 'Product Launch Premiere', '2026-07-21', 'AVAILABLE' UNION ALL
    SELECT 'Product Launch Premiere', '2026-08-18', 'BLOCKED' UNION ALL
    SELECT 'Startup Demo Day Operations', '2026-06-20', 'AVAILABLE' UNION ALL
    SELECT 'Startup Demo Day Operations', '2026-07-11', 'AVAILABLE' UNION ALL
    SELECT 'Premium Birthday Theme Party', '2026-05-30', 'AVAILABLE' UNION ALL
    SELECT 'Premium Birthday Theme Party', '2026-06-13', 'AVAILABLE' UNION ALL
    SELECT 'Kids Adventure Birthday Bash', '2026-06-06', 'AVAILABLE' UNION ALL
    SELECT 'Kids Adventure Birthday Bash', '2026-06-21', 'BLOCKED' UNION ALL
    SELECT 'Silver Jubilee Celebration Package', '2026-07-03', 'AVAILABLE' UNION ALL
    SELECT 'Silver Jubilee Celebration Package', '2026-07-24', 'AVAILABLE' UNION ALL
    SELECT 'Destination Anniversary Planner', '2026-08-02', 'AVAILABLE' UNION ALL
    SELECT 'Destination Anniversary Planner', '2026-08-29', 'AVAILABLE' UNION ALL
    SELECT 'College Fest Stage & Crowd Management', '2026-09-05', 'AVAILABLE' UNION ALL
    SELECT 'College Fest Stage & Crowd Management', '2026-10-02', 'AVAILABLE' UNION ALL
    SELECT 'Cultural Night Production Suite', '2026-07-17', 'AVAILABLE' UNION ALL
    SELECT 'Cultural Night Production Suite', '2026-08-07', 'BLOCKED' UNION ALL
    SELECT 'Conference Registration & Logistics Desk', '2026-06-11', 'AVAILABLE' UNION ALL
    SELECT 'Conference Registration & Logistics Desk', '2026-06-25', 'AVAILABLE' UNION ALL
    SELECT 'Premium Decor + Catering Combo', '2026-07-09', 'AVAILABLE' UNION ALL
    SELECT 'Premium Decor + Catering Combo', '2026-08-01', 'AVAILABLE' UNION ALL
    SELECT 'Micro Wedding Essentials Pack', '2026-05-23', 'AVAILABLE' UNION ALL
    SELECT 'Micro Wedding Essentials Pack', '2026-06-19', 'AVAILABLE' UNION ALL
    SELECT 'Sangeet & Mehendi Celebration', '2026-07-04', 'AVAILABLE' UNION ALL
    SELECT 'Sangeet & Mehendi Celebration', '2026-07-31', 'AVAILABLE' UNION ALL
    SELECT 'Community Awards Night Setup', '2026-08-15', 'AVAILABLE' UNION ALL
    SELECT 'Community Awards Night Setup', '2026-09-12', 'BLOCKED' UNION ALL
    SELECT 'Luxury Baby Shower Curation', '2026-06-28', 'AVAILABLE' UNION ALL
    SELECT 'Luxury Baby Shower Curation', '2026-07-19', 'AVAILABLE'
) a ON a.title = s.title;

COMMIT;

