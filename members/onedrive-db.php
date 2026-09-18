<?php
/**
 * onedrive-db.php — OneDrive connection and Microsoft Graph helpers.
 *
 * Photograph something on your phone, choose a OneDrive folder, and it lands
 * there directly. Reuses the QR-and-token capture pattern from the BCTF tool;
 * the new part is the Graph connection.
 *
 * Setup lives in config.php (server-only, never committed):
 *   define('MS_CLIENT_ID',     '...');   // Azure app registration
 *   define('MS_CLIENT_SECRET', '...');
 *   define('MS_TENANT',        'consumers');  // 'consumers' for a personal
 *                                             // Microsoft account, 'common' for both
 */
require_once __DIR__ . '/db.php';

define('MS_AUTH_BASE',  'https://login.microsoftonline.com');
define('MS_GRAPH_BASE', 'https://graph.microsoft.com/v1.0');
define('MS_SCOPES',     'offline_access Files.ReadWrite User.Read');

function odEnsureTables(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $db = getDB();

    // One connection for the whole site — the President's OneDrive.
    $db->exec("CREATE TABLE IF NOT EXISTS onedrive_account (
        id             TINYINT PRIMARY KEY DEFAULT 1,
        account_name   VARCHAR(255),
        access_token   TEXT,
        refresh_token  TEXT,
        expires_at     DATETIME,
        connected_by   VARCHAR(255),
        connected_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_error     VARCHAR(500) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS onedrive_uploads (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        file_name   VARCHAR(255) NOT NULL,
        folder_path VARCHAR(500) NOT NULL,
        web_url     VARCHAR(1000),
        size_bytes  INT,
        uploaded_by VARCHAR(255) NOT NULL,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS onedrive_tokens (
        token      CHAR(32) PRIMARY KEY,
        created_by VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function odIsConfigured(): bool {
    return defined('MS_CLIENT_ID') && MS_CLIENT_ID
        && defined('MS_CLIENT_SECRET') && MS_CLIENT_SECRET;
}

function odTenant(): string {
    return defined('MS_TENANT') && MS_TENANT ? MS_TENANT : 'consumers';
}

function odRedirectUri(): string {
    $host = $_SERVER['HTTP_HOST'] ?? 'bvtu.ca';
    return 'https://' . $host . '/members/onedrive-connect.php';
}

function odGetAccount(): ?array {
    odEnsureTables();
    $s = getDB()->query("SELECT * FROM onedrive_account WHERE id=1");
    return $s->fetch() ?: null;
}

function odSaveTokens(array $tok, string $by, string $accountName = ''): void {
    odEnsureTables();
    $expires = date('Y-m-d H:i:s', time() + (int)($tok['expires_in'] ?? 3600) - 60);
    $existing = odGetAccount();
    $refresh  = $tok['refresh_token'] ?? ($existing['refresh_token'] ?? '');

    if ($existing) {
        getDB()->prepare(
            "UPDATE onedrive_account
             SET access_token=?, refresh_token=?, expires_at=?, last_error=NULL"
            . ($accountName ? ", account_name=?" : "") . " WHERE id=1"
        )->execute($accountName
            ? [$tok['access_token'], $refresh, $expires, $accountName]
            : [$tok['access_token'], $refresh, $expires]);
    } else {
        getDB()->prepare(
            "INSERT INTO onedrive_account (id, account_name, access_token, refresh_token, expires_at, connected_by)
             VALUES (1,?,?,?,?,?)"
        )->execute([$accountName, $tok['access_token'], $refresh, $expires, $by]);
    }
}

function odRecordError(string $msg): void {
    odEnsureTables();
    getDB()->prepare("UPDATE onedrive_account SET last_error=? WHERE id=1")
           ->execute([substr($msg, 0, 500)]);
}

function odDisconnect(): void {
    odEnsureTables();
    getDB()->exec("DELETE FROM onedrive_account WHERE id=1");
}

/** POST to the Microsoft token endpoint. */
function odTokenRequest(array $fields): array {
    $ch = curl_init(MS_AUTH_BASE . '/' . odTenant() . '/oauth2/v2.0/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_TIMEOUT        => 30,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($body ?: '', true) ?: [];
    if ($code !== 200 || empty($data['access_token'])) {
        return ['error' => $data['error_description'] ?? ('Token request failed (HTTP ' . $code . ')')];
    }
    return $data;
}

/**
 * A usable access token, refreshing if it has expired.
 * Returns '' when the connection is gone — callers must handle that, since a
 * lapsed refresh token is the normal way this stops working.
 */
function odAccessToken(): string {
    $acct = odGetAccount();
    if (!$acct || empty($acct['refresh_token'])) return '';

    if (!empty($acct['access_token']) && strtotime($acct['expires_at']) > time()) {
        return $acct['access_token'];
    }

    $tok = odTokenRequest([
        'client_id'     => MS_CLIENT_ID,
        'client_secret' => MS_CLIENT_SECRET,
        'refresh_token' => $acct['refresh_token'],
        'grant_type'    => 'refresh_token',
        'scope'         => MS_SCOPES,
        'redirect_uri'  => odRedirectUri(),
    ]);
    if (!empty($tok['error'])) {
        odRecordError('Reconnect needed: ' . $tok['error']);
        return '';
    }
    odSaveTokens($tok, $acct['connected_by'] ?? '');
    return $tok['access_token'];
}

/** GET/POST against Microsoft Graph. Returns [httpCode, decodedBody]. */
function odGraph(string $method, string $path, array $opts = []): array {
    // Upload-session URLs are pre-authenticated and Microsoft's docs say not to
    // send a bearer token with them; doing so can be rejected outright.
    $needsAuth = empty($opts['noAuth']);

    $token = '';
    if ($needsAuth) {
        $token = odAccessToken();
        if (!$token) return [401, ['error' => ['message' => 'OneDrive is not connected.']]];
    }

    $url = str_starts_with($path, 'http') ? $path : MS_GRAPH_BASE . $path;
    $ch  = curl_init($url);
    $headers = $needsAuth ? ['Authorization: Bearer ' . $token] : [];

    if (isset($opts['json'])) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($opts['json']));
    } elseif (isset($opts['body'])) {
        $headers[] = 'Content-Type: ' . ($opts['contentType'] ?? 'application/octet-stream');
        if (isset($opts['contentRange'])) $headers[] = 'Content-Range: ' . $opts['contentRange'];
        curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['body']);
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 120,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, json_decode($body ?: '', true) ?: []];
}

/** Child folders of a drive item, for the picker. */
function odListFolders(string $itemId = 'root'): array {
    $path = $itemId === 'root'
        ? '/me/drive/root/children?$select=id,name,folder,parentReference&$top=200'
        : '/me/drive/items/' . rawurlencode($itemId) . '/children?$select=id,name,folder,parentReference&$top=200';
    [$code, $data] = odGraph('GET', $path);
    if ($code !== 200) return [];
    return array_values(array_filter($data['value'] ?? [], function ($i) {
        return isset($i['folder']);
    }));
}

function odLogUpload(string $name, string $folderPath, string $webUrl, int $size, string $by): void {
    odEnsureTables();
    getDB()->prepare(
        "INSERT INTO onedrive_uploads (file_name, folder_path, web_url, size_bytes, uploaded_by)
         VALUES (?,?,?,?,?)"
    )->execute([$name, $folderPath, $webUrl, $size, $by]);
}

function odRecentUploads(int $limit = 25): array {
    odEnsureTables();
    $s = getDB()->prepare("SELECT * FROM onedrive_uploads ORDER BY created_at DESC LIMIT " . (int)$limit);
    $s->execute();
    return $s->fetchAll();
}

function odCreateUploadToken(string $email): string {
    odEnsureTables();
    getDB()->prepare("DELETE FROM onedrive_tokens WHERE created_by=?")->execute([$email]);
    $t = bin2hex(random_bytes(16));
    getDB()->prepare("INSERT INTO onedrive_tokens (token, created_by) VALUES (?,?)")->execute([$t, $email]);
    return $t;
}

function odValidateUploadToken(string $token): ?array {
    odEnsureTables();
    $s = getDB()->prepare("SELECT * FROM onedrive_tokens WHERE token=? LIMIT 1");
    $s->execute([$token]);
    return $s->fetch() ?: null;
}
