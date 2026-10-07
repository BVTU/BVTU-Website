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

    /*
     * Shared links.
     *
     * The page used to mint one token per load and delete every earlier one, so
     * handing somebody a link and then reopening the page silently broke theirs.
     * A share is a separate row with a name on it and an hour it stops working,
     * so lending someone upload access for an afternoon does not mean lending
     * them a phone and an account.
     */
    $ok = true;
    foreach ([
        'label'        => "VARCHAR(120) NOT NULL DEFAULT ''",
        'ref'          => "VARCHAR(24) NOT NULL DEFAULT ''",
        'kind'         => "VARCHAR(16) NOT NULL DEFAULT 'self'",
        'expires_at'   => 'DATETIME DEFAULT NULL',
        'revoked_at'   => 'DATETIME DEFAULT NULL',
        'revoked_by'   => "VARCHAR(255) NOT NULL DEFAULT ''",
        'last_used_at' => 'DATETIME DEFAULT NULL',
        'uses'         => 'INT NOT NULL DEFAULT 0',
    ] as $col => $type) {
        try {
            $db->query("SELECT `$col` FROM onedrive_tokens LIMIT 1");
        } catch (\PDOException $e) {
            try {
                $db->exec("ALTER TABLE onedrive_tokens ADD COLUMN `$col` $type");
            } catch (\Exception $e2) {
                // Said out loud. Silently losing the column would turn every
                // later query into a fatal on the admin page instead.
                error_log('onedrive: could not add ' . $col . ' — ' . $e2->getMessage());
                $ok = false;
            }
        }
    }
    $GLOBALS['od_share_cols'] = $ok;
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

    // strncmp rather than str_starts_with — this host runs PHP 7.
    $url = strncmp($path, 'http', 4) === 0 ? $path : MS_GRAPH_BASE . $path;
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

/** Whether the share columns are actually there. A host whose DB user cannot
 *  ALTER keeps the old behaviour rather than a broken page. */
function odShareCols(): bool {
    odEnsureTables();
    return !empty($GLOBALS['od_share_cols']);
}

/** The admin's own QR, replaced each time they open the page. */
function odCreateUploadToken(string $email): string {
    odEnsureTables();
    $t = bin2hex(random_bytes(16));
    if (!odShareCols()) {           // pre-migration shape, behaves as it always did
        getDB()->prepare("DELETE FROM onedrive_tokens WHERE created_by=?")->execute([$email]);
        getDB()->prepare("INSERT INTO onedrive_tokens (token, created_by) VALUES (?,?)")
               ->execute([$t, $email]);
        return $t;
    }
    // Only this person's own token, not the shares they have handed out — that
    // DELETE used to take every row for the address with it.
    getDB()->prepare("DELETE FROM onedrive_tokens WHERE created_by=? AND kind='self'")
           ->execute([$email]);
    getDB()->prepare("INSERT INTO onedrive_tokens (token, created_by, kind) VALUES (?,?,'self')")
           ->execute([$t, $email]);
    return $t;
}

/**
 * A usable token, or null.
 *
 * Expiry and revocation are decided here rather than by a sweep, so a link stops
 * working at the hour it says it will even if nothing has run since.
 */
function odValidateUploadToken(string $token): ?array {
    odEnsureTables();
    if (!odShareCols()) {
        $s = getDB()->prepare("SELECT * FROM onedrive_tokens WHERE token=? LIMIT 1");
        $s->execute([$token]);
        return $s->fetch() ?: null;
    }
    // NOW(), not PHP's clock: db.php sets the session time zone on a best-effort
    // basis, and on a host where that fails a link compared in PHP would keep
    // working for hours after the admin list had already called it expired.
    $s = getDB()->prepare(
        "SELECT *, TIMESTAMPDIFF(SECOND, NOW(), expires_at) AS secs_left
         FROM onedrive_tokens
         WHERE token=? AND revoked_at IS NULL
           AND (expires_at IS NULL OR expires_at > NOW())
         LIMIT 1");
    $s->execute([$token]);
    return $s->fetch() ?: null;
}

/** Note that a share was used, for the list on the admin page. */
function odTouchToken(string $token): void {
    try {
        getDB()->prepare("UPDATE onedrive_tokens SET last_used_at=NOW(), uses=uses+1 WHERE token=?")
               ->execute([$token]);
    } catch (Exception $e) {}
}

/** Create a share. $hours is how long it lasts. */
function odCreateShare(string $label, int $hours, string $by): ?array {
    odEnsureTables();
    $label = trim($label);
    if ($label === '' || $hours < 1) return null;
    if ($hours > 720) $hours = 720;          // 30 days, an upper bound on "short"
    if (!odShareCols()) return null;
    $t   = bin2hex(random_bytes(16));
    $ref = bin2hex(random_bytes(8));
    try {
        getDB()->prepare(
            "INSERT INTO onedrive_tokens (token, created_by, kind, label, ref, expires_at)
             VALUES (?,?,'share',?,?, DATE_ADD(NOW(), INTERVAL ? HOUR))"
        )->execute([$t, $by, $label, $ref, $hours]);
    } catch (Exception $e) {
        error_log('odCreateShare: ' . $e->getMessage());
        return null;
    }
    return ['token' => $t, 'ref' => $ref, 'label' => $label, 'hours' => $hours];
}

/* Takes the row's ref, not its token: the Turn-off button lives in the page's
 * HTML, and putting the token there would reprint every live link on a screen
 * that tells you the link is shown only once. */
function odRevokeShare(string $ref, string $by): bool {
    odEnsureTables();
    if ($ref === '') return false;
    try {
        $st = getDB()->prepare(
            "UPDATE onedrive_tokens SET revoked_at=NOW(), revoked_by=?
             WHERE ref=? AND kind='share' AND revoked_at IS NULL");
        $st->execute([$by, $ref]);
        return $st->rowCount() > 0;
    } catch (Exception $e) {
        error_log('odRevokeShare: ' . $e->getMessage());
        return false;
    }
}

/** Shares worth showing: the live ones, and recently finished ones for the record. */
function odShares(): array {
    odEnsureTables();
    try {
        return getDB()->query(
            "SELECT *, TIMESTAMPDIFF(SECOND, NOW(), expires_at) AS secs_left
             FROM onedrive_tokens
             WHERE kind='share'
               AND ((revoked_at IS NULL AND expires_at > NOW())
                    OR COALESCE(revoked_at, expires_at) > DATE_SUB(NOW(), INTERVAL 7 DAY))
             ORDER BY created_at DESC"
        )->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/** Live, expired or revoked — said plainly, because a link that quietly stopped
 *  working is the thing this replaces. */
function odShareState(array $row): array {
    if (!empty($row['revoked_at'])) return ['state' => 'revoked', 'text' => 'Revoked'];
    // secs_left comes from the database, the same clock that decides whether the
    // link still opens, so the list cannot disagree with the lock.
    $left = array_key_exists('secs_left', $row) && $row['secs_left'] !== null
          ? (int)$row['secs_left']
          : strtotime((string)$row['expires_at']) - time();
    if ($left <= 0) return ['state' => 'expired', 'text' => 'Expired'];
    $mins = (int)ceil($left / 60);
    if ($mins < 60)  return ['state' => 'live', 'text' => $mins . ' min left'];
    $hours = (int)floor($mins / 60);
    if ($hours < 48) return ['state' => 'live', 'text' => $hours . ' hr left'];
    return ['state' => 'live', 'text' => (int)floor($hours / 24) . ' days left'];
}
