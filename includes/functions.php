<?php
/* ---------------- Output & URLs ---------------- */

/** Escape text for safe HTML output. */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Web path of the project folder, e.g. "/courier" (auto-detected). */
function app_path(): string
{
    static $path = null;
    if ($path === null) {
        $root = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
        $app  = str_replace('\\', '/', realpath(__DIR__ . '/..'));
        $path = ($root !== '' && stripos($app, $root) === 0) ? substr($app, strlen($root)) : '';
        $path = rtrim($path, '/');
    }
    return $path;
}

/** Link to a page inside the project, e.g. url('track.php'). */
function url(string $page = ''): string
{
    return app_path() . '/' . ltrim($page, '/');
}

/**
 * Absolute base URL used inside QR codes.
 * Uses QR_BASE_URL if set; otherwise the current host. If the current host is
 * localhost, the computer's LAN IP is used so a phone on the same Wi-Fi can open it.
 */
function public_base_url(): string
{
    if (QR_BASE_URL !== '') {
        return rtrim(QR_BASE_URL, '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $name   = strtolower(preg_replace('/:\d+$/', '', $host));
    if (in_array($name, ['localhost', '127.0.0.1', '[::1]'], true)) {
        $lan = lan_ip();
        if ($lan && filter_var($lan, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && strpos($lan, '127.') !== 0) {
            $port = preg_match('/:(\d+)$/', $host, $m) ? ':' . $m[1] : '';
            $host = $lan . $port;
        }
    }
    return $scheme . '://' . $host . app_path();
}

/**
 * This computer's LAN IP. Opening a UDP "connection" sends no packets but makes the OS
 * pick the network adapter used for the default route (usually Wi-Fi).
 */
function lan_ip(): string
{
    $s = @stream_socket_client('udp://8.8.8.8:53', $errno, $errstr, 1);
    if ($s) {
        $name = stream_socket_get_name($s, false);
        fclose($s);
        if ($name) {
            return preg_replace('/:\d+$/', '', $name);
        }
    }
    return gethostbyname(gethostname()); // fallback when there is no network route
}

/** The public URL encoded in a parcel's QR code. */
function tracking_url(string $trackingId): string
{
    return public_base_url() . '/track.php?tracking_id=' . urlencode($trackingId);
}

function redirect(string $page): void
{
    header('Location: ' . url($page));
    exit;
}

/* ---------------- Flash messages ---------------- */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message];
}

function render_flash(): string
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $icon = $f['type'] === 'success' ? 'check-circle' : ($f['type'] === 'danger' ? 'exclamation-octagon' : 'info-circle');
        $html .= '<div class="alert alert-' . e($f['type']) . ' alert-dismissible fade show d-flex align-items-center gap-2" role="alert">'
            . '<i class="bi bi-' . $icon . '"></i><div>' . e($f['msg']) . '</div>'
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

/* ---------------- CSRF ---------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_valid(): bool
{
    return isset($_POST['csrf']) && hash_equals(csrf_token(), (string)$_POST['csrf']);
}

/* ---------------- Auth ---------------- */

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function require_login(): void
{
    if (!current_user()) {
        flash('warning', 'Please log in to continue.');
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('login.php');
    }
}

function require_customer(): void
{
    require_login();
    if (is_admin()) {
        redirect('admin/index.php');
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        flash('danger', 'Access denied. That page is for administrators only.');
        redirect('dashboard.php');
    }
}

/* ---------------- Domain helpers ---------------- */

/** Delivery charge = base + weight × per-kg rate + parcel-type surcharge. */
function calculate_charge(float $weight, string $type): float
{
    $surcharge = TYPE_SURCHARGE[$type] ?? 0;
    return round(BASE_CHARGE + $weight * PER_KG_CHARGE + $surcharge, 2);
}

/**
 * Next tracking ID for today: CR + YYYYMMDD + 4-digit sequence, e.g. CR202610050001.
 * Must be called inside a transaction; FOR UPDATE locks the rows being read so two
 * simultaneous bookings cannot get the same number (the UNIQUE key is the final guard).
 */
function generate_tracking_id(mysqli $conn): string
{
    $prefix = 'CR' . date('Ymd');
    $like   = $prefix . '%';
    $stmt = $conn->prepare('SELECT MAX(tracking_id) AS last_id FROM parcels WHERE tracking_id LIKE ? FOR UPDATE');
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $last = $stmt->get_result()->fetch_assoc()['last_id'];
    $seq  = $last ? ((int)substr($last, -4)) + 1 : 1;
    return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
}

function valid_tracking_id(string $id): bool
{
    return (bool)preg_match('/^CR\d{12}$/', $id);
}

function status_class(string $status): string
{
    return 'status-' . strtolower(str_replace(' ', '-', $status));
}

function status_icon(string $status): string
{
    return [
        'Booked' => 'journal-check', 'Picked Up' => 'box-seam', 'In Transit' => 'truck',
        'Out for Delivery' => 'bicycle', 'Delivered' => 'house-check', 'Failed' => 'x-octagon',
        'Returned' => 'arrow-return-left',
    ][$status] ?? 'circle';
}

function status_badge(string $status): string
{
    return '<span class="status-badge ' . status_class($status) . '"><i class="bi bi-' . status_icon($status) . '"></i> ' . e($status) . '</span>';
}

function money($amount): string
{
    return '₹' . number_format((float)$amount, 2);
}

function fmt_date(?string $d, bool $withTime = false): string
{
    if (!$d) return '—';
    return date($withTime ? 'd M Y, h:i A' : 'd M Y', strtotime($d));
}

/** Normalise a phone number: keep digits and an optional leading +. */
function clean_phone(string $p): string
{
    $p = trim($p);
    return (str_starts_with($p, '+') ? '+' : '') . preg_replace('/\D/', '', $p);
}

function valid_phone(string $p): bool
{
    return (bool)preg_match('/^\+?\d{10,13}$/', $p);
}

/** Run a prepared SELECT and return all rows. */
function db_all(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/** Run a prepared SELECT and return the first row (or null). */
function db_one(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    return db_all($conn, $sql, $types, $params)[0] ?? null;
}
