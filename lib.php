<?php
// Shared helpers for the portfolio and admin panel.
// Data is stored as JSON files in /data (protected by data/.htaccess).

const DATA_DIR   = __DIR__ . '/data';
const UPLOAD_DIR = __DIR__ . '/uploads';
const UPLOAD_URL = 'uploads/';
const MAX_UPLOAD_BYTES = 8 * 1024 * 1024; // 8 MB per image

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function load_json(string $name, $default) {
    $f = DATA_DIR . "/$name.json";
    if (!is_file($f)) return $default;
    $d = json_decode((string)file_get_contents($f), true);
    return is_array($d) ? $d : $default;
}

function save_json(string $name, $data): void {
    $f = DATA_DIR . "/$name.json";
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) throw new RuntimeException('Could not save. Please check the text you typed.');
    $tmp = $f . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false) throw new RuntimeException('Could not write to the data folder. Check folder permissions.');
    rename($tmp, $f);
}

function default_settings(): array {
    return [
        'email' => 'you@example.com',
        'phone' => '(209) 000-0000',
        'whatsapp' => '12090000000',
        'linkedin' => '',
        'projects_count' => '5+',
        'photo' => '',
        'web3forms_key' => '',
    ];
}

function get_settings(): array { return array_merge(default_settings(), load_json('settings', [])); }

function get_projects(): array {
    $p = load_json('projects', []);
    usort($p, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
    return $p;
}

function img_url(string $file): string { return $file === '' ? '' : UPLOAD_URL . rawurlencode($file); }

function safe_url(string $u): string {
    $u = trim($u);
    if ($u === '') return '';
    if (!preg_match('~^https?://~i', $u)) $u = 'https://' . $u;
    return filter_var($u, FILTER_VALIDATE_URL) ? $u : '';
}

/** Validates an uploaded image and stores it with a random name. Returns filename or throws. */
function store_upload(array $file): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Upload failed (error ' . $file['error'] . ').');
    if ($file['size'] > MAX_UPLOAD_BYTES) throw new RuntimeException('Image is larger than 8 MB.');
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if (!$info || !isset($types[$info[2]])) throw new RuntimeException('Only JPG, PNG, WEBP or GIF images are allowed.');
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    $name = bin2hex(random_bytes(8)) . '.' . $types[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $name)) throw new RuntimeException('Could not save the image.');
    return $name;
}

function delete_upload(string $name): void {
    if ($name !== '' && preg_match('~^[a-f0-9]{16}\.(jpg|png|webp|gif)$~', $name)) @unlink(UPLOAD_DIR . '/' . $name);
}
