-- =====================================================================
--  Courier Parcel Booking and Tracking Management System
--  Database: courier_management   (MySQL 5.7+/8.0 or MariaDB 10.4+)
--  Import this file in phpMyAdmin (Import tab) – it creates everything.
-- =====================================================================

DROP DATABASE IF EXISTS courier_management;
CREATE DATABASE courier_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE courier_management;

-- ---------------------------------------------------------------------
-- 1. USERS  (customers and admins/staff)
-- ---------------------------------------------------------------------
CREATE TABLE users (
    user_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL,
    phone         VARCHAR(15)  NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('customer','admin') NOT NULL DEFAULT 'customer',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_users_email UNIQUE (email)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. PARCELS  (one customer books many parcels)
-- ---------------------------------------------------------------------
CREATE TABLE parcels (
    parcel_id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tracking_id            CHAR(14)     NOT NULL,
    customer_id            INT UNSIGNED NOT NULL,
    sender_name            VARCHAR(100) NOT NULL,
    sender_phone           VARCHAR(15)  NOT NULL,
    sender_address         VARCHAR(255) NOT NULL,
    sender_city            VARCHAR(60)  NOT NULL,
    receiver_name          VARCHAR(100) NOT NULL,
    receiver_phone         VARCHAR(15)  NOT NULL,
    receiver_address       VARCHAR(255) NOT NULL,
    receiver_city          VARCHAR(60)  NOT NULL,
    parcel_type            ENUM('Document','Package','Electronics','Fragile','Other') NOT NULL,
    weight_kg              DECIMAL(6,2) NOT NULL,
    booking_date           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expected_delivery_date DATE         NOT NULL,
    delivery_charge        DECIMAL(10,2) NOT NULL,
    payment_status         ENUM('Pending','Paid','Refunded') NOT NULL DEFAULT 'Pending',
    current_status         ENUM('Booked','Picked Up','In Transit','Out for Delivery','Delivered','Failed','Returned') NOT NULL DEFAULT 'Booked',
    created_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_parcels_tracking UNIQUE (tracking_id),
    CONSTRAINT fk_parcels_customer FOREIGN KEY (customer_id) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_parcels_weight CHECK (weight_kg > 0 AND weight_kg <= 50),
    CONSTRAINT chk_parcels_charge CHECK (delivery_charge >= 0),
    INDEX idx_parcels_status (current_status),
    INDEX idx_parcels_booking (booking_date)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. TRACKING_HISTORY  (one parcel has many tracking events; never overwritten)
-- ---------------------------------------------------------------------
CREATE TABLE tracking_history (
    history_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parcel_id   INT UNSIGNED NOT NULL,
    status      ENUM('Booked','Picked Up','In Transit','Out for Delivery','Delivered','Failed','Returned') NOT NULL,
    location    VARCHAR(120) NOT NULL,
    remarks     VARCHAR(255) NULL,
    updated_by  INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_history_parcel FOREIGN KEY (parcel_id) REFERENCES parcels(parcel_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_history_user FOREIGN KEY (updated_by) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_history_parcel_time (parcel_id, created_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. PAYMENTS  (one parcel can have one or more payment records; simulated, no gateway)
-- ---------------------------------------------------------------------
CREATE TABLE payments (
    payment_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parcel_id       INT UNSIGNED NOT NULL,
    amount          DECIMAL(10,2) NOT NULL,
    payment_method  ENUM('Cash on Pickup','UPI','Card','Net Banking') NOT NULL,
    payment_status  ENUM('Pending','Paid','Failed','Refunded') NOT NULL DEFAULT 'Pending',
    transaction_ref VARCHAR(30) NOT NULL,
    payment_date    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_payments_txn UNIQUE (transaction_ref),
    CONSTRAINT fk_payments_parcel FOREIGN KEY (parcel_id) REFERENCES parcels(parcel_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_payments_amount CHECK (amount >= 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- VIEW: one row per parcel with customer name and latest location (uses JOIN + subquery)
-- ---------------------------------------------------------------------
CREATE VIEW v_parcel_overview AS
SELECT p.parcel_id, p.tracking_id, u.name AS customer_name, u.email AS customer_email,
       p.sender_city, p.receiver_name, p.receiver_city, p.parcel_type, p.weight_kg,
       p.delivery_charge, p.payment_status, p.current_status, p.booking_date, p.expected_delivery_date,
       (SELECT th.location FROM tracking_history th
         WHERE th.parcel_id = p.parcel_id
         ORDER BY th.created_at DESC, th.history_id DESC LIMIT 1) AS last_location
FROM parcels p
JOIN users u ON u.user_id = p.customer_id;

-- =====================================================================
--  SAMPLE DATA
--  Demo logins:  admin@courier.com / admin123     customer@courier.com / customer123
--  (all other sample customers also use the password customer123)
-- =====================================================================

INSERT INTO users (user_id, name, email, phone, password_hash, role, created_at) VALUES
(1, 'Admin User',      'admin@courier.com',    '9000000001', '$2y$10$C1Ur499KdaF9afCeqbBCbOZfDs.m9QRayv2qlaSmbedX3RIE3uGQu', 'admin',    '2026-09-01 09:00:00'),
(2, 'Rahul Sharma',    'customer@courier.com', '9876543210', '$2y$10$2fCH5UAODppCkuOY2Bk9wuDrUhAJz5bCNEHjIrPajRMNdWtVMeAw2', 'customer', '2026-09-05 10:15:00'),
(3, 'Priya Nair',      'priya@example.com',    '9876500011', '$2y$10$2fCH5UAODppCkuOY2Bk9wuDrUhAJz5bCNEHjIrPajRMNdWtVMeAw2', 'customer', '2026-09-08 12:30:00'),
(4, 'Arjun Mehta',     'arjun@example.com',    '9876500022', '$2y$10$2fCH5UAODppCkuOY2Bk9wuDrUhAJz5bCNEHjIrPajRMNdWtVMeAw2', 'customer', '2026-09-12 16:45:00'),
(5, 'Sneha Reddy',     'sneha@example.com',    '9876500033', '$2y$10$2fCH5UAODppCkuOY2Bk9wuDrUhAJz5bCNEHjIrPajRMNdWtVMeAw2', 'customer', '2026-09-15 11:05:00'),
(6, 'Operations Staff','staff@courier.com',    '9000000002', '$2y$10$C1Ur499KdaF9afCeqbBCbOZfDs.m9QRayv2qlaSmbedX3RIE3uGQu', 'admin',    '2026-09-01 09:30:00');

INSERT INTO parcels (parcel_id, tracking_id, customer_id, sender_name, sender_phone, sender_address, sender_city,
                     receiver_name, receiver_phone, receiver_address, receiver_city, parcel_type, weight_kg,
                     booking_date, expected_delivery_date, delivery_charge, payment_status, current_status, created_at) VALUES
(1,  'CR202609200001', 2, 'Rahul Sharma', '9876543210', 'Flat 302, Green Residency, Madhapur', 'Hyderabad', 'Ananya Iyer',   '9845012345', '14, 5th Cross, Indiranagar', 'Bengaluru', 'Document',    0.50, '2026-09-20 10:20:00', '2026-09-23', 60.00,  'Paid',     'Delivered',        '2026-09-20 10:20:00'),
(2,  'CR202609220001', 3, 'Priya Nair',   '9876500011', '22, Anna Salai, T. Nagar',            'Chennai',   'Vikram Rao',    '9912345678', 'Plot 7, Jubilee Hills',      'Hyderabad', 'Package',     2.00, '2026-09-22 14:05:00', '2026-09-26', 90.00,  'Paid',     'Delivered',        '2026-09-22 14:05:00'),
(3,  'CR202609250001', 2, 'Rahul Sharma', '9876543210', 'Flat 302, Green Residency, Madhapur', 'Hyderabad', 'Meera Kulkarni','9822098220', '9, FC Road, Shivajinagar',   'Pune',      'Electronics', 1.50, '2026-09-25 09:40:00', '2026-09-29', 110.00, 'Refunded', 'Returned',         '2026-09-25 09:40:00'),
(4,  'CR202609280001', 4, 'Arjun Mehta',  '9876500022', '501, Sea Breeze, Andheri West',       'Mumbai',    'Kavya Menon',   '9000011122', '3-4, Banjara Hills Road 12', 'Hyderabad', 'Fragile',     3.00, '2026-09-28 11:15:00', '2026-10-02', 150.00, 'Paid',     'Delivered',        '2026-09-28 11:15:00'),
(5,  'CR202610010001', 2, 'Rahul Sharma', '9876543210', 'Flat 302, Green Residency, Madhapur', 'Hyderabad', 'Lakshmi Narayan','9444012345','45, Besant Nagar 2nd Ave',   'Chennai',   'Package',     4.00, '2026-10-01 08:50:00', '2026-10-06', 130.00, 'Paid',     'Out for Delivery', '2026-10-01 08:50:00'),
(6,  'CR202610010002', 5, 'Sneha Reddy',  '9876500033', '18, Koramangala 4th Block',           'Bengaluru', 'Rohit Malhotra','9810098100', 'C-21, Lajpat Nagar II',      'New Delhi', 'Electronics', 2.50, '2026-10-01 15:30:00', '2026-10-07', 130.00, 'Paid',     'In Transit',       '2026-10-01 15:30:00'),
(7,  'CR202610020001', 3, 'Priya Nair',   '9876500011', '12, MG Road, Ernakulam',              'Kochi',     'Sanjay Gupta',  '9948012345', '8-2-293, Road No. 3',        'Hyderabad', 'Document',    0.30, '2026-10-02 10:10:00', '2026-10-06', 56.00,  'Paid',     'In Transit',       '2026-10-02 10:10:00'),
(8,  'CR202610030001', 4, 'Arjun Mehta',  '9876500022', '7, Kalyani Nagar',                    'Pune',      'Neha Joshi',    '9820012345', '11, Bandra Kurla Complex',   'Mumbai',    'Other',       5.00, '2026-10-03 12:00:00', '2026-10-07', 150.00, 'Pending',  'Picked Up',        '2026-10-03 12:00:00'),
(9,  'CR202610030002', 2, 'Rahul Sharma', '9876543210', 'Flat 302, Green Residency, Madhapur', 'Hyderabad', 'Deepak Varma',  '9849012345', '21, Beach Road, Siripuram',  'Visakhapatnam','Fragile',  1.00, '2026-10-03 17:25:00', '2026-10-08', 110.00, 'Paid',     'Picked Up',        '2026-10-03 17:25:00'),
(10, 'CR202610040001', 5, 'Sneha Reddy',  '9876500033', '18, Koramangala 4th Block',           'Bengaluru', 'Aditi Singh',   '9829012345', '56, C-Scheme, Ashok Marg',   'Jaipur',    'Package',     1.20, '2026-10-04 09:05:00', '2026-10-09', 74.00,  'Pending',  'Booked',           '2026-10-04 09:05:00'),
(11, 'CR202610040002', 3, 'Priya Nair',   '9876500011', '22, Anna Salai, T. Nagar',            'Chennai',   'Harish Kumar',  '9884012345', '10, Race Course Road',       'Coimbatore','Document',    0.20, '2026-10-04 13:40:00', '2026-10-07', 54.00,  'Paid',     'Booked',           '2026-10-04 13:40:00'),
(12, 'CR202610040003', 2, 'Rahul Sharma', '9876543210', 'Flat 302, Green Residency, Madhapur', 'Hyderabad', 'Suresh Babu',   '9866012345', '2-5-30, Hanamkonda',         'Warangal',  'Package',     2.00, '2026-10-04 16:00:00', '2026-10-06', 90.00,  'Pending',  'Failed',           '2026-10-04 16:00:00');

INSERT INTO tracking_history (parcel_id, status, location, remarks, updated_by, created_at) VALUES
-- Parcel 1: Hyderabad -> Bengaluru (Delivered)
(1, 'Booked',           'Hyderabad',               'Parcel booked online',                       2, '2026-09-20 10:20:00'),
(1, 'Picked Up',        'Hyderabad - Madhapur Hub', 'Picked up from sender',                      1, '2026-09-20 15:10:00'),
(1, 'In Transit',       'Hyderabad Sorting Centre', 'Dispatched to Bengaluru',                    1, '2026-09-21 06:30:00'),
(1, 'Out for Delivery', 'Bengaluru - Indiranagar',  'With delivery agent',                        6, '2026-09-22 09:15:00'),
(1, 'Delivered',        'Bengaluru',                'Delivered to Ananya Iyer',                   6, '2026-09-22 13:40:00'),
-- Parcel 2: Chennai -> Hyderabad (Delivered)
(2, 'Booked',           'Chennai',                  'Parcel booked online',                       3, '2026-09-22 14:05:00'),
(2, 'Picked Up',        'Chennai - T. Nagar Hub',   'Picked up from sender',                      1, '2026-09-22 18:00:00'),
(2, 'In Transit',       'Chennai Sorting Centre',   'Dispatched by road',                         1, '2026-09-23 07:00:00'),
(2, 'In Transit',       'Nellore Transit Hub',      'Arrived at transit hub',                     6, '2026-09-23 21:30:00'),
(2, 'Out for Delivery', 'Hyderabad - Jubilee Hills','With delivery agent',                        6, '2026-09-25 09:00:00'),
(2, 'Delivered',        'Hyderabad',                'Delivered, signed by Vikram Rao',            6, '2026-09-25 12:20:00'),
-- Parcel 3: Hyderabad -> Pune (Failed then Returned)
(3, 'Booked',           'Hyderabad',                'Parcel booked online',                       2, '2026-09-25 09:40:00'),
(3, 'Picked Up',        'Hyderabad - Madhapur Hub', 'Picked up from sender',                      1, '2026-09-25 14:00:00'),
(3, 'In Transit',       'Hyderabad Sorting Centre', 'Dispatched to Pune',                         1, '2026-09-26 05:45:00'),
(3, 'Out for Delivery', 'Pune - Shivajinagar',      'With delivery agent',                        6, '2026-09-27 10:00:00'),
(3, 'Failed',           'Pune - Shivajinagar',      'Receiver not available, premises locked',    6, '2026-09-27 17:30:00'),
(3, 'Returned',         'Hyderabad',                'Returned to sender after 2 failed attempts', 1, '2026-09-30 11:00:00'),
-- Parcel 4: Mumbai -> Hyderabad (Delivered)
(4, 'Booked',           'Mumbai',                   'Parcel booked online',                       4, '2026-09-28 11:15:00'),
(4, 'Picked Up',        'Mumbai - Andheri Hub',     'Fragile handling label applied',             1, '2026-09-28 16:20:00'),
(4, 'In Transit',       'Mumbai Air Cargo',         'Flown to Hyderabad',                         1, '2026-09-29 08:00:00'),
(4, 'Out for Delivery', 'Hyderabad - Banjara Hills','With delivery agent',                        6, '2026-10-01 09:30:00'),
(4, 'Delivered',        'Hyderabad',                'Delivered in good condition',                6, '2026-10-01 11:45:00'),
-- Parcel 5: Hyderabad -> Chennai (Out for Delivery)
(5, 'Booked',           'Hyderabad',                'Parcel booked online',                       2, '2026-10-01 08:50:00'),
(5, 'Picked Up',        'Hyderabad - Madhapur Hub', 'Picked up from sender',                      1, '2026-10-01 13:00:00'),
(5, 'In Transit',       'Hyderabad Sorting Centre', 'Dispatched to Chennai',                      1, '2026-10-02 06:00:00'),
(5, 'In Transit',       'Chennai Sorting Centre',   'Arrived at destination hub',                 6, '2026-10-04 07:30:00'),
(5, 'Out for Delivery', 'Chennai - Besant Nagar',   'With delivery agent, expected by 6 PM',      6, '2026-10-05 09:10:00'),
-- Parcel 6: Bengaluru -> New Delhi (In Transit)
(6, 'Booked',           'Bengaluru',                'Parcel booked online',                       5, '2026-10-01 15:30:00'),
(6, 'Picked Up',        'Bengaluru - Koramangala Hub','Picked up from sender',                    1, '2026-10-01 19:00:00'),
(6, 'In Transit',       'Bengaluru Air Cargo',      'Flight booked to Delhi',                     1, '2026-10-03 05:20:00'),
-- Parcel 7: Kochi -> Hyderabad (In Transit)
(7, 'Booked',           'Kochi',                    'Parcel booked online',                       3, '2026-10-02 10:10:00'),
(7, 'Picked Up',        'Kochi - Ernakulam Hub',    'Picked up from sender',                      6, '2026-10-02 15:45:00'),
(7, 'In Transit',       'Kochi Sorting Centre',     'Dispatched to Hyderabad',                    6, '2026-10-03 08:00:00'),
-- Parcel 8: Pune -> Mumbai (Picked Up, cash on pickup pending)
(8, 'Booked',           'Pune',                     'Parcel booked online',                       4, '2026-10-03 12:00:00'),
(8, 'Picked Up',        'Pune - Kalyani Nagar Hub', 'Picked up; cash to be collected on delivery',1, '2026-10-04 10:30:00'),
-- Parcel 9: Hyderabad -> Visakhapatnam (Picked Up)
(9, 'Booked',           'Hyderabad',                'Parcel booked online',                       2, '2026-10-03 17:25:00'),
(9, 'Picked Up',        'Hyderabad - Madhapur Hub', 'Fragile handling label applied',             1, '2026-10-04 11:00:00'),
-- Parcel 10, 11: Booked only
(10, 'Booked',          'Bengaluru',                'Parcel booked online',                       5, '2026-10-04 09:05:00'),
(11, 'Booked',          'Chennai',                  'Parcel booked online',                       3, '2026-10-04 13:40:00'),
-- Parcel 12: Hyderabad -> Warangal (Failed attempt, can be re-attempted)
(12, 'Booked',          'Hyderabad',                'Parcel booked online',                       2, '2026-10-04 16:00:00'),
(12, 'Picked Up',       'Hyderabad - Madhapur Hub', 'Picked up from sender',                      1, '2026-10-04 18:30:00'),
(12, 'In Transit',      'Hyderabad Sorting Centre', 'Dispatched to Warangal',                     1, '2026-10-05 05:00:00'),
(12, 'Out for Delivery','Warangal - Hanamkonda',    'With delivery agent',                        6, '2026-10-05 10:00:00'),
(12, 'Failed',          'Warangal - Hanamkonda',    'Incorrect address landmark, will re-attempt',6, '2026-10-05 14:15:00');

INSERT INTO payments (parcel_id, amount, payment_method, payment_status, transaction_ref, payment_date) VALUES
(1,  60.00,  'UPI',            'Paid',     'TXN20260920102001', '2026-09-20 10:20:00'),
(2,  90.00,  'Card',           'Paid',     'TXN20260922140502', '2026-09-22 14:05:00'),
(3,  110.00, 'UPI',            'Refunded', 'TXN20260925094003', '2026-09-25 09:40:00'),
(4,  150.00, 'Net Banking',    'Paid',     'TXN20260928111504', '2026-09-28 11:15:00'),
(5,  130.00, 'UPI',            'Paid',     'TXN20261001085005', '2026-10-01 08:50:00'),
(6,  130.00, 'Card',           'Paid',     'TXN20261001153006', '2026-10-01 15:30:00'),
(7,  56.00,  'UPI',            'Paid',     'TXN20261002101007', '2026-10-02 10:10:00'),
(8,  150.00, 'Cash on Pickup', 'Pending',  'COD20261003120008', '2026-10-03 12:00:00'),
(9,  110.00, 'Card',           'Paid',     'TXN20261003172509', '2026-10-03 17:25:00'),
(10, 74.00,  'Cash on Pickup', 'Pending',  'COD20261004090510', '2026-10-04 09:05:00'),
(11, 54.00,  'UPI',            'Paid',     'TXN20261004134011', '2026-10-04 13:40:00'),
(12, 90.00,  'Cash on Pickup', 'Pending',  'COD20261004160012', '2026-10-04 16:00:00');
