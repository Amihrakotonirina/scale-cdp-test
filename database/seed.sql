USE cdp_test;

-- Just a small deterministic dataset for demonstrating profiles and segmentation.
-- Need to recreate the Docker volume if want to recreate the seed (docker compose down -v).
-- dO not use in prod

INSERT IGNORE INTO customers (id, email, name, created_at, updated_at) VALUES
(1, 'mirana@example.com', 'Mirana Rakotonirina', '2026-04-01 09:00:00.000000', '2026-04-10 12:00:00.000000'),
(2, 'anarim@example.com', 'Anarim Rinarakoto', '2026-04-02 10:00:00.000000', '2026-04-11 15:30:00.000000'),
(3, 'mika@example.com', 'Mika Rabe', '2026-04-03 11:00:00.000000', '2026-04-12 08:15:00.000000'),
(4, 'akim@example.com', 'Akim Erab', '2026-04-04 12:00:00.000000', '2026-04-04 12:00:00.000000');

INSERT IGNORE INTO events (id, customer_id, event_name, occurred_at, received_at) VALUES
(1001, 1, 'purchase', '2026-04-10 12:00:00.000000', '2026-04-10 12:00:01.000000'),
(1002, 1, 'page_view', '2026-04-10 12:05:00.000000', '2026-04-10 12:05:01.000000'),
(1003, 1, 'purchase', '2026-04-15 16:20:00.000000', '2026-04-15 16:20:01.000000'),
(1004, 2, 'purchase', '2026-04-11 15:30:00.000000', '2026-04-11 15:30:01.000000'),
(1005, 2, 'newsletter_signup', '2026-04-12 08:00:00.000000', '2026-04-12 08:00:01.000000'),
(1006, 3, 'purchase', '2026-04-12 08:15:00.000000', '2026-04-12 08:15:01.000000'),
(1007, 3, 'page_view', '2026-04-13 14:00:00.000000', '2026-04-13 14:00:01.000000');

INSERT IGNORE INTO event_properties
(event_id, property_name, value_type, value_number, value_string, value_boolean) VALUES
(1001, 'amount', 'number', 120.00, NULL, NULL),
(1001, 'product', 'string', NULL, 'Shoes', NULL),
(1002, 'page', 'string', NULL, '/products/shoes', NULL),
(1003, 'amount', 'number', 75.50, NULL, NULL),
(1003, 'product', 'string', NULL, 'Socks', NULL),
(1004, 'amount', 'number', 240.00, NULL, NULL),
(1004, 'product', 'string', NULL, 'Backpack', NULL),
(1005, 'source', 'string', NULL, 'website', NULL),
(1006, 'amount', 'number', 45.00, NULL, NULL),
(1006, 'product', 'string', NULL, 'Cap', NULL),
(1007, 'page', 'string', NULL, '/offers', NULL);
