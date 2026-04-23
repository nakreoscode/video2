<?php
// includes/sms.php - İletimerkezi SMS API

class SMS {
    private string $username;
    private string $password;
    private string $sender;
    private string $api_url = 'https://api.iletimerkezi.com/v1/send-sms/';

    public function __construct(string $username, string $password, string $sender) {
        $this->username = $username;
        $this->password = $password;
        $this->sender   = $sender;
    }

    public static function instance(): self {
        return new self(
            get_setting('sms_username'),
            get_setting('sms_password'),
            get_setting('sms_sender', 'NakreosStr')
        );
    }

    public function send(string $phone, string $message): array {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 10) $phone = '90' . $phone;
        if (strlen($phone) === 11 && $phone[0] === '0') $phone = '9' . substr($phone, 1);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<request>
  <authentication>
    <username>' . htmlspecialchars($this->username) . '</username>
    <password>' . htmlspecialchars($this->password) . '</password>
  </authentication>
  <order>
    <sender>' . htmlspecialchars($this->sender) . '</sender>
    <sendDateTime></sendDateTime>
    <message>
      <text><![CDATA[' . $message . ']]></text>
      <receipents>
        <number>' . $phone . '</number>
      </receipents>
    </message>
  </order>
</request>';

        $ch = curl_init($this->api_url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $xml,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: text/xml; charset=UTF-8'],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err) return ['success' => false, 'error' => $err];

        $xml_res = simplexml_load_string($response);
        if ($xml_res && (int)$xml_res->status->code === 200) {
            return ['success' => true];
        }
        return ['success' => false, 'error' => (string)($xml_res->status->message ?? 'Bilinmeyen hata')];
    }

    /**
     * OTP kodu oluştur ve gönder
     */
    public function sendOTP(int $user_id, string $phone, string $type = 'login'): array {
        global $pdo;
        $code    = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', time() + 300); // 5 dakika

        $pdo->prepare("INSERT INTO sms_verifications(user_id,phone,code,type,expires_at) VALUES(?,?,?,?,?)")
            ->execute([$user_id, $phone, $code, $type, $expires]);

        $msg    = "NakreosStream doğrulama kodunuz: $code (5 dakika geçerlidir)";
        $result = $this->send($phone, $msg);
        return array_merge($result, ['code' => $code]);
    }

    /**
     * OTP kodu doğrula
     */
    public static function verifyOTP(int $user_id, string $code, string $type = 'login'): bool {
        global $pdo;
        $st = $pdo->prepare("SELECT id FROM sms_verifications WHERE user_id=? AND code=? AND type=? AND used=0 AND expires_at>NOW() LIMIT 1");
        $st->execute([$user_id, $code, $type]);
        $row = $st->fetch();
        if ($row) {
            $pdo->prepare("UPDATE sms_verifications SET used=1 WHERE id=?")->execute([$row['id']]);
            return true;
        }
        return false;
    }
}
