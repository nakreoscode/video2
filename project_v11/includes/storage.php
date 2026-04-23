<?php
/**
 * NakreosStream - Çoklu Depolama Yöneticisi
 * Desteklenen: local, wasabi, idrive
 */
class Storage {
    private string $type;
    private array  $cfg;

    public function __construct() {
        $this->type = get_setting('storage_type', 'local');
        $this->cfg = [
            'wasabi' => [
                'key'      => get_setting('wasabi_key'),
                'secret'   => get_setting('wasabi_secret'),
                'bucket'   => get_setting('wasabi_bucket'),
                'region'   => get_setting('wasabi_region', 'eu-central-1'),
                'endpoint' => get_setting('wasabi_endpoint', 'https://s3.eu-central-1.wasabisys.com'),
                'url_style'=> 'virtual', // bucket.endpoint
            ],
            'idrive' => [
                'key'      => get_setting('idrive_key'),
                'secret'   => get_setting('idrive_secret'),
                'bucket'   => get_setting('idrive_bucket'),
                'region'   => get_setting('idrive_region', 'us-east-1'),
                'endpoint' => get_setting('idrive_endpoint', 'https://s3.idrivecloud.io'),
                'url_style'=> 'path', // endpoint/bucket
            ],
        ];
    }

    public function getType(): string { return $this->type; }

    /** Dosya yükle — otomatik sağlayıcı seçer */
    public function upload(string $tmp_path, string $remote_key, string $mime = 'video/mp4'): array {
        return match($this->type) {
            'wasabi' => $this->uploadS3('wasabi', $tmp_path, $remote_key, $mime),
            'idrive'  => $this->uploadS3('idrive',  $tmp_path, $remote_key, $mime),
            default   => $this->uploadLocal($tmp_path, $remote_key),
        };
    }

    /** Dosya sil */
    public function delete(string $file_path): bool {
        if ($this->type === 'local') {
            $full = __DIR__ . '/../' . ltrim($file_path, '/');
            return file_exists($full) && unlink($full);
        }
        $cfg = $this->cfg[$this->type] ?? [];
        return $this->s3Delete($this->type, $cfg, $file_path);
    }

    /** Public URL döndür */
    public function getUrl(string $remote_key): string {
        if ($this->type === 'local') return '/' . ltrim($remote_key, '/');
        $cfg = $this->cfg[$this->type];
        $ep  = rtrim($cfg['endpoint'], '/');
        $bkt = $cfg['bucket'];
        $key = ltrim($remote_key, '/');
        if (($cfg['url_style'] ?? 'path') === 'virtual') {
            // https://bucket.s3.region.wasabisys.com/key
            $host = parse_url($ep, PHP_URL_HOST);
            $scheme = parse_url($ep, PHP_URL_SCHEME);
            return "{$scheme}://{$bkt}.{$host}/{$key}";
        }
        return "{$ep}/{$bkt}/{$key}";
    }

    // ── S3 uyumlu yükleme ────────────────────────────────────────────
    private function uploadS3(string $provider, string $tmp, string $key, string $mime): array {
        $cfg = $this->cfg[$provider];
        if (!$cfg['key'] || !$cfg['secret'] || !$cfg['bucket']) {
            error_log("Storage: {$provider} config eksik, yerel kayda düşülüyor");
            return $this->uploadLocal($tmp, $key);
        }

        $key     = ltrim($key, '/');
        $content = file_get_contents($tmp);
        $clen    = strlen($content);
        $phash   = hash('sha256', $content);
        $dt      = gmdate('Ymd\THis\Z');
        $d       = gmdate('Ymd');
        $region  = $cfg['region'];
        $ep      = rtrim($cfg['endpoint'], '/');
        $bkt     = $cfg['bucket'];

        // URL oluştur
        if (($cfg['url_style'] ?? 'path') === 'virtual') {
            $host    = $bkt . '.' . parse_url($ep, PHP_URL_HOST);
            $url     = parse_url($ep, PHP_URL_SCHEME) . '://' . $host . '/' . $key;
            $can_uri = '/' . $key;
        } else {
            $host    = parse_url($ep, PHP_URL_HOST);
            $url     = $ep . '/' . $bkt . '/' . $key;
            $can_uri = '/' . $bkt . '/' . $key;
        }

        $can_headers = "content-length:{$clen}\ncontent-type:{$mime}\nhost:{$host}\nx-amz-content-sha256:{$phash}\nx-amz-date:{$dt}\n";
        $signed_h    = 'content-length;content-type;host;x-amz-content-sha256;x-amz-date';
        $can_req     = "PUT\n{$can_uri}\n\n{$can_headers}\n{$signed_h}\n{$phash}";

        $scope = "{$d}/{$region}/s3/aws4_request";
        $sts   = "AWS4-HMAC-SHA256\n{$dt}\n{$scope}\n" . hash('sha256', $can_req);
        $sk    = hash_hmac('sha256', 'aws4_request',
                    hash_hmac('sha256', 's3',
                        hash_hmac('sha256', $region,
                            hash_hmac('sha256', $d, 'AWS4' . $cfg['secret'], true), true), true), true);
        $sig   = hash_hmac('sha256', $sts, $sk);
        $auth  = "AWS4-HMAC-SHA256 Credential={$cfg['key']}/{$scope},SignedHeaders={$signed_h},Signature={$sig}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_POSTFIELDS     => $content,
            CURLOPT_HTTPHEADER     => [
                "Authorization: {$auth}",
                "Content-Type: {$mime}",
                "Content-Length: {$clen}",
                "x-amz-content-sha256: {$phash}",
                "x-amz-date: {$dt}",
            ],
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200) {
            $pub_url = $this->getUrl($key);
            return ['success' => true, 'url' => $pub_url, 'storage' => $provider];
        }
        error_log("Storage {$provider} hata {$code}: " . substr($res, 0, 200));
        return $this->uploadLocal($tmp, $key); // fallback
    }

    private function s3Delete(string $provider, array $cfg, string $file_url): bool {
        // URL'den key çıkart
        $bkt = $cfg['bucket'];
        $key = ltrim(str_replace([$cfg['endpoint'].'/'.$bkt, $cfg['endpoint']], '', $file_url), '/');
        // basit DELETE isteği
        $dt = gmdate('Ymd\THis\Z'); $d = gmdate('Ymd'); $region = $cfg['region'];
        $ep = rtrim($cfg['endpoint'], '/');
        if (($cfg['url_style'] ?? 'path') === 'virtual') {
            $host = $bkt . '.' . parse_url($ep, PHP_URL_HOST);
            $url  = parse_url($ep, PHP_URL_SCHEME) . '://' . $host . '/' . $key;
            $can_uri = '/' . $key;
        } else {
            $host = parse_url($ep, PHP_URL_HOST);
            $url  = $ep . '/' . $bkt . '/' . $key;
            $can_uri = '/' . $bkt . '/' . $key;
        }
        $phash = hash('sha256', '');
        $can_headers = "host:{$host}\nx-amz-content-sha256:{$phash}\nx-amz-date:{$dt}\n";
        $signed_h = 'host;x-amz-content-sha256;x-amz-date';
        $can_req = "DELETE\n{$can_uri}\n\n{$can_headers}\n{$signed_h}\n{$phash}";
        $scope = "{$d}/{$region}/s3/aws4_request";
        $sts   = "AWS4-HMAC-SHA256\n{$dt}\n{$scope}\n" . hash('sha256', $can_req);
        $sk    = hash_hmac('sha256','aws4_request',hash_hmac('sha256','s3',hash_hmac('sha256',$region,hash_hmac('sha256',$d,'AWS4'.$cfg['secret'],true),true),true),true);
        $sig   = hash_hmac('sha256', $sts, $sk);
        $auth  = "AWS4-HMAC-SHA256 Credential={$cfg['key']}/{$scope},SignedHeaders={$signed_h},Signature={$sig}";
        $ch = curl_init($url);
        curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>'DELETE',CURLOPT_RETURNTRANSFER=>true,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_TIMEOUT=>30,CURLOPT_HTTPHEADER=>["Authorization:{$auth}","x-amz-content-sha256:{$phash}","x-amz-date:{$dt}"]]);
        curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
        return $code === 204;
    }

    private function uploadLocal(string $tmp, string $key): array {
        $dir  = __DIR__ . '/../assets/videos/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $fname = basename($key);
        $dest  = $dir . $fname;
        // move_uploaded_file sadece HTTP upload için çalışır
        // rename ve copy her ikisi için
        if (is_uploaded_file($tmp)) {
            $ok = move_uploaded_file($tmp, $dest);
        } else {
            $ok = rename($tmp, $dest);
            if (!$ok) $ok = copy($tmp, $dest);
        }
        if ($ok) {
            chmod($dest, 0644);
            return ['success' => true, 'url' => '/assets/videos/' . $fname, 'storage' => 'local'];
        }
        return ['success' => false, 'url' => '', 'storage' => 'local', 'error' => 'Dosya kopyalanamadı: '.$dest];
    }
}
