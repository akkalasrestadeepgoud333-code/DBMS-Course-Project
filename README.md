# SwiftCourier: Courier Parcel Booking and Tracking Management System

This is a DBMS project built with **PHP + MySQL + HTML/CSS + vanilla JavaScript** and Bootstrap 5 from a CDN. Everything it shows is live data from MySQL:

- Registration and login create and check real rows.
- Booking inserts a parcel, its first tracking event and a payment row in one transaction.
- Every status change adds a new `tracking_history` row; old rows are never overwritten.
- Each parcel's QR code opens its public tracking page.

---

## 1. How to run (XAMPP)

1. **Copy the folder.** Put the `courier` folder in `C:\xampp\htdocs\`, so you have `C:\xampp\htdocs\courier\index.php`. (WAMP: use `C:\wamp64\www\courier\`.)
2. **Start the servers.** Open the XAMPP Control Panel and click **Start** for **Apache** and **MySQL**.
3. **Import the database.**
   1. Open <http://localhost/phpmyadmin>.
   2. Click **Import**, then **Choose File**, and pick `courier/database/courier_management.sql`.
   3. Click **Import** (or **Go**).

   This creates the `courier_management` database, its 4 tables, the view and the sample data. Importing again resets everything to the sample data.
4. **Open the app** at <http://localhost/courier/>.

### Database settings (`config/config.php`)
| Setting | Default | Notes |
|---|---|---|
| `DB_HOST` | `127.0.0.1` | |
| `DB_USER` / `DB_PASS` | `root` / *(empty)* | XAMPP defaults |
| `DB_PORTS` | `[3306, 3307]` | Tried in order. XAMPP normally uses 3306; some installs use 3307. |

If you set a MySQL root password, put it in `DB_PASS`.

## 2. Demo accounts
| Role | Email | Password |
|---|---|---|
| Admin | `admin@courier.com` | `admin123` |
| Customer | `customer@courier.com` | `customer123` |

Other sample accounts are `staff@courier.com` (admin, password `admin123`), plus `priya@example.com`, `arjun@example.com` and `sneha@example.com` (customers, password `customer123`). All passwords are stored as bcrypt hashes made with `password_hash()`.

Sample tracking IDs to try: `CR202610010001` (Out for Delivery), `CR202609200001` (Delivered), `CR202609250001` (Returned), `CR202610040003` (Failed).

## 3. QR code demonstration (scan with a phone)

Each parcel's QR code holds the URL of its public tracking page, for example `http://192.168.1.10/courier/track.php?tracking_id=CR202610050001`. Scanning it opens `track.php`, which reads the parcel and its full timeline from MySQL. No login is needed.

**Setting the address:**
- **Automatic (default).** `QR_BASE_URL` is `''`. When you open the site as `localhost`, the app swaps in your computer's Wi-Fi IP, so a phone can open the link. The URL is printed under every QR code, so you can check it.
- **Manual.** If the IP is wrong (VPN, several network adapters), set it in `config/config.php`:
  ```php
  define('QR_BASE_URL', 'http://192.168.1.10/courier');
  ```
  To find your IP, run `ipconfig` in Command Prompt and use the **IPv4 Address** under *Wireless LAN adapter Wi-Fi*.

**Before the demo:**
1. Connect the phone and the laptop to the **same Wi-Fi**.
2. When Windows Firewall asks about **Apache HTTP Server**, allow it on **Private networks**. If you missed the prompt, go to Windows Security, then Firewall, then *Allow an app*, and tick `httpd.exe`.
3. Test on the phone's browser by opening `http://<your-ip>/courier/track.php`. If that page loads, the QR will work.

On each QR you can choose **View** (`qr.php`, full size), **Download** (PNG) or **Print** (a label with only the QR card).

The QR codes are drawn in the browser by `assets/js/qrcode.min.js`, a local copy of the MIT-licensed *qrcode-generator* library. There is no external QR API.

## 4. Full demo flow
1. Log in as **customer** and click **Book a Parcel**. Fill in the form; the charge updates as you type. Click **Confirm Booking**.
2. The confirmation page shows the new tracking ID (e.g. `CR202610060001`), the status **Booked**, the charge and the QR code.
3. Log out and log in as **admin**. The new parcel is at the top of **Recent bookings**. Click **Manage**.
4. Choose a new status (e.g. *Picked Up*), enter a location and remarks, and click **Save update**. This updates `parcels.current_status` and inserts a `tracking_history` row in one transaction.
5. Open the public tracking page, or scan the QR with your phone. It shows the new status and the full timeline.
6. In phpMyAdmin, run `SELECT * FROM tracking_history WHERE parcel_id = …` to show the stored history.
7. **Delete with CASCADE.** On the admin parcel page, click **Delete parcel**. MySQL also removes that parcel's `tracking_history` and `payments` rows (`ON DELETE CASCADE`), and the message shows how many.
8. **Delete with RESTRICT.** On **Customers**, try to delete Rahul Sharma. MySQL refuses with error 1451 because he still has parcels (`ON DELETE RESTRICT`). A customer with no parcels deletes normally.

**Status rules:** the admin can only pick a valid next status.

| Current status | Allowed next status |
|---|---|
| Booked | Picked Up, Failed |
| Picked Up | In Transit, Failed |
| In Transit | In Transit (next hub), Out for Delivery, Failed |
| Out for Delivery | Delivered, Failed |
| Failed | In Transit, Out for Delivery, Returned |
| Delivered, Returned | none (final) |

**Charge formula:** ₹50 base + ₹20 per kg + type surcharge (Electronics +₹30, Fragile +₹40). The server always recalculates the charge; the browser preview is only a preview. Example: Fragile, 2.5 kg = 50 + 50 + 40 = **₹140**.

**Payment is simulated** (no real gateway). UPI, Card or Net Banking are marked *Paid* straight away with a generated `TXN…` reference. Cash on Pickup stays *Pending* and is marked *Paid* automatically when the admin sets the parcel to *Delivered*.

---

## 5. Database design (for the viva)

**Database:** `courier_management` (InnoDB, utf8mb4)

```
users (1) ──────< (many) parcels (1) ──────< (many) tracking_history
  ^                          │
  └── tracking_history.updated_by (which user made the update)
                             └──────< (many) payments
```

| Table | Purpose | Key columns |
|---|---|---|
| `users` | Customers and admins | **PK** `user_id`; **UNIQUE** `email`; `password_hash`; `role` ENUM('customer','admin'); `created_at` |
| `parcels` | One row per booking | **PK** `parcel_id`; **UNIQUE** `tracking_id`; **FK** `customer_id` → `users`; sender and receiver details; `parcel_type`; `weight_kg` (CHECK 0–50); `booking_date`; `expected_delivery_date`; `delivery_charge`; `payment_status`; `current_status` |
| `tracking_history` | Every status event, never overwritten | **PK** `history_id`; **FK** `parcel_id` → `parcels` (ON DELETE CASCADE); `status`; `location`; `remarks`; **FK** `updated_by` → `users` (ON DELETE SET NULL); `created_at` |
| `payments` | Simulated payment records | **PK** `payment_id`; **FK** `parcel_id` → `parcels`; `amount`; `payment_method`; `payment_status`; **UNIQUE** `transaction_ref`; `payment_date` |
| `v_parcel_overview` (view) | Parcel, customer name and latest location | JOIN + correlated subquery |

**Relationships**
- `users` 1 : N `parcels`. A customer can book many parcels.
- `parcels` 1 : N `tracking_history`. Each update adds a row, so the timeline is the parcel's full audit trail.
- `parcels` 1 : N `payments`. Usually one row per parcel, but the design allows retries or refunds.
- `users` 1 : N `tracking_history` through `updated_by`, which records who made each update.

**Normalisation (3NF)**
- Customer details live only in `users`; parcels refer to them by `customer_id`.
- Status events are not columns of `parcels`; they are rows in their own table.
- The current location is not stored in `parcels`; it is read from the latest `tracking_history` row.
- The QR code is not stored; it is generated from `tracking_id`.
- `parcels.current_status` and `parcels.payment_status` are deliberate summary copies, kept for fast filtering and dashboard counts. They are always updated in the same transaction as `tracking_history` or `payments`, so they cannot go out of sync.

**Constraints:** primary keys, foreign keys with ON DELETE rules, UNIQUE (email, tracking_id, transaction_ref), NOT NULL, ENUM domains, CHECK (weight, amount), and indexes on status, booking date and (parcel_id, created_at).

**Tracking ID:** `CR` + `YYYYMMDD` + a 4-digit sequence for that day, e.g. `CR202610050001`. It is generated inside the booking transaction with `SELECT MAX(tracking_id) … FOR UPDATE`, which locks the rows so two bookings at the same moment cannot get the same number. The UNIQUE key is the final guarantee.

**SQL concepts shown in the app**
- The admin dashboard uses `COUNT`, `SUM`, `GROUP BY`, `HAVING`, `JOIN`, `LEFT JOIN`, `ORDER BY` and `LIMIT`. Click **"SQL queries powering this dashboard"** at the bottom to show them.
- The customers page uses `LEFT JOIN … GROUP BY` for booking counts.
- Search uses `LIKE` across joined tables.
- Booking and status updates use transactions (`begin_transaction` / `commit` / `rollback`).
- `database/demo_queries.sql` has 13 ready-to-run queries: total customers, total parcels, parcels by status, recent bookings, booking count per customer, delivered parcels, active parcels, tracking history for one parcel, revenue, overdue parcels, the view, and the transaction.

## 6. Security
- **SQL injection:** every query uses prepared statements (`mysqli` + `bind_param`).
- **Passwords:** `password_hash()` / `password_verify()`, and the session ID is regenerated on login.
- **Access control:** PHP sessions with role checks. `require_customer()` and `require_admin()` are in `includes/functions.php`. Customers can only open their own parcels.
- **CSRF:** a token on every form, and on the logout link.
- **Output:** all output is escaped with `htmlspecialchars` (the `e()` helper).
- **Errors:** they are logged, never shown to users (`DEBUG = false` in config). Users see a friendly message instead.
- **Folders:** `config/`, `includes/` and `database/` are blocked from direct web access by `.htaccess`.

## 7. Folder structure
```
courier/
├── index.php            Landing page (hero, tracking search, how it works)
├── login.php / register.php / logout.php
├── dashboard.php        Customer dashboard
├── book_parcel.php      Booking form (transaction: parcel + history + payment)
├── my_parcels.php       Customer parcel list (search + filter)
├── parcel_details.php   Customer parcel view + booking confirmation + QR
├── track.php            PUBLIC tracking page (QR target, mobile-first)
├── qr.php               Full-size QR: view / download / print
├── admin/
│   ├── index.php        Admin dashboard (SQL statistics)
│   ├── parcels.php      Search / filter / paginate all parcels
│   ├── parcel.php       Parcel details + status update + full history
│   └── customers.php    Customers with booking counts
├── config/config.php    Settings (DB, QR base URL, charges, statuses)
├── config/db.php        MySQL connection
├── includes/            init, functions (helpers + auth), header, footer, timeline, qr_card
├── assets/css/style.css
├── assets/js/app.js     QR rendering, download, live charge preview
├── assets/js/qrcode.min.js   Local QR library (MIT)
└── database/
    ├── courier_management.sql   Full schema + sample data (import this)
    └── demo_queries.sql         Viva demonstration queries
```

## 8. Troubleshooting
| Problem | Fix |
|---|---|
| "Database not available" | Start MySQL in XAMPP, import the SQL file, and check `DB_PORTS` and `DB_PASS`. |
| Phone can't open the QR link | Use the same Wi-Fi, allow Apache through the firewall, and set `QR_BASE_URL` to your IPv4 address. |
| Page has no styling | Bootstrap and the icons load from a CDN, so the laptop and phone need internet access. |
| Apache won't start (port 80 busy) | Close Skype/IIS, or change Apache's port and include it in `QR_BASE_URL` (e.g. `http://192.168.1.10:8080/courier`). |

## 9. Project documents
| File | Contents |
|---|---|
| [`report/SwiftCourier_DBMS_PBL_Report.pdf`](report/SwiftCourier_DBMS_PBL_Report.pdf) | Project report (Woxsen PBL format) |
| [`presentation/SwiftCourier_DBMS_Presentation.pptx`](presentation/SwiftCourier_DBMS_Presentation.pptx) | Presentation slides |
| [`screenshots/`](screenshots/) | Screenshots of the running application |
| [`database/er_diagram.png`](database/er_diagram.png) | ER diagram (Chen notation) |

**Team:** A Srestadeep (25WU0101001), Abhigyan Gogoi (25WU0101004), Jogi Dhanush (25WU0101053)
**Course:** Database Management System (25TU03MJM0), Woxsen University, guided by Dr. Kiran Mayee Adavala
