<?php
// includes/wasabi.php - Wasabi S3 uyumlu depolama

class Wasabi {
    private string $key;
    private string $secret;
    private string $bucket;
    private string $region;
    private string $endpoint;

    public function __construct() {
        $this->key      = get_setting('wasabi_key');
        $this->secret   = get_setting('wasabi_secret');
        $this->bucket   = get_setting('wasabi_bucket');
        $this->region   = get_setting('wasabi_region', 'eu-central-1');
        $this->endpoint = get_setting('wasabi_endpoint', 'https://s3.eu-central-1.wasabisys.com');
    }

    /**
     * Dosya yükle
     */
    public function upload(string $local_path, string $remote_key, string $content_type = 'video/mp4'): array {
        if (!file_exists($local_path)) {
            return ['success' => false, 'error' => 'Dosya bulunamadı'];
        }

        $date       = gmdate('Ymd');
        $datetime   = gmdate('Ymd\THis\Z');
        $payload    = file_get_contents($local_path);
        $payload_hash = hash('sha256', $payload);

        $canonical_headers = "content-type:{$content_type}\nhost:{$this->bucket}.s3.{$this->region}.wasabisys.com\nx-amz-content-sha256:{$payload_hash}\nx-amz-date:{$datetime}\n";
        $signed_headers    = "content-type;host;x-amz-content-sha256;x-amz-date";

        $canonical_request = implode("\n", [
            'PUT',
            '/' . ltrim($remote_key, '/'),
            '',
            $canonical_headers,
            $signed_headers,
            $payload_hash
        ]);

        $credential_scope = "{$date}/{$this->region}/s3/aws4_request";
        $string_to_sign   = implode("\n", [
            'AWS4-HMAC-SHA256',
            $datetime,
            $credential_scope,
            hash('sha256', $canonical_request)
        ]);

        $signing_key = $this->getSigningKey($date);
        $signature   = hash_hmac('sha256', $string_to_sign, $signing_key);

        $auth_header = "AWS4-HMAC-SHA256 Credential={$this->key}/{$credential_scope},SignedHeaders={$signed_headers},Signature={$signature}";

        $url = "https://{$this->bucket}.s3.{$this->region}.wasabisys.com/" . ltrim($remote_key, '/');
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: {$auth_header}",
                "Content-Type: {$content_type}",
                "x-amz-content-sha256: {$payload_hash}",
                "x-amz-date: {$datetime}",
                "Content-Length: " . strlen($payload),
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 300,
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            return ['success' => true, 'url' => $url];
        }
        return ['success' => false, 'error' => "HTTP {$http_code}: " . substr($response, 0, 200)];
    }

    /**
     * Dosya sil
     */
    public function delete(string $remote_key): bool {
        $date     = gmdate('Ymd');
        $datetime = gmdate('Ymd\THis\Z');
        $hash     = hash('sha256', '');

        $canonical_headers = "host:{$this->bucket}.s3.{$this->region}.wasabisys.com\nx-amz-content-sha256:{$hash}\nx-amz-date:{$datetime}\n";
        $signed_headers    = "host;x-amz-content-sha256;x-amz-date";

        $canonical_request = implode("\n", ['DELETE', '/' . ltrim($remote_key, '/'), '', $canonical_headers, $signed_headers, $hash]);
        $credential_scope  = "{$date}/{$this->region}/s3/aws4_request";
        $string_to_sign    = implode("\n", ['AWS4-HMAC-SHA256', $datetime, $credential_scope, hash('sha256', $canonical_request)]);

        $signature   = hash_hmac('sha256', $string_to_sign, $this->getSigningKey($date));
        $auth_header = "AWS4-HMAC-SHA256 Credential={$this->key}/{$credential_scope},SignedHeaders={$signed_headers},Signature={$signature}";

        $url = "https://{$this->bucket}.s3.{$this->region}.wasabisys.com/" . ltrim($remote_key, '/');
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Authorization: {$auth_header}", "x-amz-content-sha256: {$hash}", "x-amz-date: {$datetime}"],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code === 204;
    }

    private function getSigningKey(string $date): string {
        $k_date    = hash_hmac('sha256', $date, 'AWS4' . $this->secret, true);
        $k_region  = hash_hmac('sha256', $this->region, $k_date, true);
        $k_service = hash_hmac('sha256', 's3', $k_region, true);
        return hash_hmac('sha256', 'aws4_request', $k_service, true);
    }

    public function getPublicUrl(string $remote_key): string {
        return "https://{$this->bucket}.s3.{$this->region}.wasabisys.com/" . ltrim($remote_key, '/');
    }
}
