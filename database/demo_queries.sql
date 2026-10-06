-- =====================================================================
--  DBMS demonstration queries – run in phpMyAdmin > courier_management > SQL
--  (The application runs these same kinds of queries with prepared statements.)
-- =====================================================================
USE courier_management;

-- 1. Total number of customers (COUNT + WHERE)
SELECT COUNT(*) AS total_customers FROM users WHERE role = 'customer';

-- 2. Total parcels
SELECT COUNT(*) AS total_parcels FROM parcels;

-- 3. Parcels by status (GROUP BY)
SELECT current_status, COUNT(*) AS parcels
FROM parcels
GROUP BY current_status
ORDER BY parcels DESC;

-- 4. Recent bookings with customer name (INNER JOIN + ORDER BY + LIMIT)
SELECT p.tracking_id, u.name AS customer, p.receiver_name, p.receiver_city, p.booking_date, p.current_status
FROM parcels p
JOIN users u ON u.user_id = p.customer_id
ORDER BY p.booking_date DESC
LIMIT 5;

-- 5. Booking count per customer, including customers with no bookings (LEFT JOIN + GROUP BY)
SELECT u.name, u.email, COUNT(p.parcel_id) AS bookings, COALESCE(SUM(p.delivery_charge), 0) AS total_charges
FROM users u
LEFT JOIN parcels p ON p.customer_id = u.user_id
WHERE u.role = 'customer'
GROUP BY u.user_id, u.name, u.email
ORDER BY bookings DESC;

-- 6. Customers with more than one booking (GROUP BY + HAVING)
SELECT u.name, COUNT(*) AS bookings
FROM users u JOIN parcels p ON p.customer_id = u.user_id
GROUP BY u.user_id, u.name
HAVING COUNT(*) > 1;

-- 7. Delivered parcels with their delivery time (JOIN with tracking_history)
SELECT p.tracking_id, p.receiver_name, p.receiver_city, th.created_at AS delivered_at
FROM parcels p
JOIN tracking_history th ON th.parcel_id = p.parcel_id AND th.status = 'Delivered'
WHERE p.current_status = 'Delivered'
ORDER BY delivered_at DESC;

-- 8. Active parcels (IN list)
SELECT tracking_id, sender_city, receiver_city, current_status, expected_delivery_date
FROM parcels
WHERE current_status IN ('Booked', 'Picked Up', 'In Transit', 'Out for Delivery')
ORDER BY expected_delivery_date;

-- 9. Complete tracking history of one parcel (JOIN 3 tables; who updated it)
SELECT th.created_at, th.status, th.location, th.remarks, u.name AS updated_by
FROM tracking_history th
JOIN parcels p ON p.parcel_id = th.parcel_id
LEFT JOIN users u ON u.user_id = th.updated_by
WHERE p.tracking_id = 'CR202610010001'
ORDER BY th.created_at;

-- 10. Revenue by payment method (aggregate on payments)
SELECT payment_method, COUNT(*) AS payments, SUM(amount) AS amount
FROM payments
WHERE payment_status = 'Paid'
GROUP BY payment_method;

-- 11. Overdue parcels: expected date passed but not delivered
SELECT tracking_id, receiver_name, expected_delivery_date, current_status
FROM parcels
WHERE expected_delivery_date < CURDATE() AND current_status NOT IN ('Delivered', 'Returned');

-- 12. Using the view (JOIN + correlated subquery stored as a view)
SELECT tracking_id, customer_name, current_status, last_location FROM v_parcel_overview;

-- 13. What the admin "Update status" button does (TRANSACTION: both succeed or both fail)
-- START TRANSACTION;
-- UPDATE parcels SET current_status = 'In Transit' WHERE parcel_id = 10;
-- INSERT INTO tracking_history (parcel_id, status, location, remarks, updated_by)
--     VALUES (10, 'In Transit', 'Bengaluru Sorting Centre', 'Dispatched', 1);
-- COMMIT;
