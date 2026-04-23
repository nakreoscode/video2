<?php
// includes/mailer.php

class Mailer {
    private string $from_name;
    private string $from_email;

    public function __construct() {
        $this->from_name  = get_setting('site_title', 'NakreosStream');
        $this->from_email = get_setting('mail_from', 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    }

    private function send(string $to, string $subject, string $html): bool {
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$this->from_name} <{$this->from_email}>\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        return mail($to, $subject, $html, $headers);
    }

    private function template(string $title, string $body): string {
        $site = get_setting('site_title', 'NakreosStream');
        return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>body{font-family:Arial,sans-serif;background:#0f0f0f;color:#e5e7eb;margin:0;padding:20px}
.box{max-width:520px;margin:0 auto;background:#1a1a1a;border-radius:12px;overflow:hidden}
.hd{background:linear-gradient(135deg,#ef4444,#dc2626);padding:24px;text-align:center}
.hd h1{color:#fff;margin:0;font-size:22px}
.bd{padding:32px}.bd h2{color:#f87171;margin-top:0}
.btn{display:inline-block;background:#ef4444;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:bold;margin:16px 0}
.ft{text-align:center;padding:16px;color:#6b7280;font-size:13px;border-top:1px solid #333}
</style></head><body>
<div class="box">
<div class="hd"><h1>🎬 {$site}</h1></div>
<div class="bd"><h2>{$title}</h2>{$body}</div>
<div class="ft">&copy; {$site} — Tüm hakları saklıdır.</div>
</div></body></html>
HTML;
    }

    public function welcome(string $to, string $username): bool {
        $site  = get_setting('site_title', 'NakreosStream');
        $body  = "<p>Merhaba <strong>{$username}</strong>,</p>
                  <p>{$site}'e hoş geldiniz! Hesabınız başarıyla oluşturuldu.</p>
                  <p>Hemen giriş yaparak platformumuzu keşfedebilirsiniz.</p>
                  <a href='https://{$_SERVER['HTTP_HOST']}/login.php' class='btn'>Giriş Yap</a>";
        return $this->send($to, "{$site}'e Hoş Geldiniz!", $this->template("Hesabınız Oluşturuldu", $body));
    }

    public function passwordReset(string $to, string $token): bool {
        $url  = "https://{$_SERVER['HTTP_HOST']}/reset-password.php?token={$token}";
        $body = "<p>Şifre sıfırlama talebinde bulundunuz.</p>
                 <p>Aşağıdaki butona tıklayarak yeni şifrenizi belirleyebilirsiniz. <strong>Bu bağlantı 1 saat geçerlidir.</strong></p>
                 <a href='{$url}' class='btn'>Şifremi Sıfırla</a>
                 <p style='color:#9ca3af;font-size:12px'>Bu işlemi siz yapmadıysanız bu e-postayı görmezden gelebilirsiniz.</p>";
        return $this->send($to, "Şifre Sıfırlama", $this->template("Şifrenizi Sıfırlayın", $body));
    }

    public function paymentApproved(string $to, string $username, float $amount): bool {
        $site  = get_setting('site_title', 'NakreosStream');
        $body  = "<p>Merhaba <strong>{$username}</strong>,</p>
                  <p><strong>" . number_format($amount, 2) . " TL</strong> tutarındaki ödemeniz onaylanmıştır.</p>
                  <p>Pro üyeliğiniz aktif edilmiştir. İyi seyirler!</p>
                  <a href='https://{$_SERVER['HTTP_HOST']}/dashboard.php' class='btn'>Dashboard'a Git</a>";
        return $this->send($to, "Ödemeniz Onaylandı – Pro Üyelik Aktif", $this->template("Ödeme Onaylandı 🎉", $body));
    }

    public function twoFactorCode(string $to, string $code): bool {
        $body  = "<p>İki faktörlü doğrulama kodunuz:</p>
                  <div style='font-size:36px;font-weight:bold;color:#ef4444;letter-spacing:8px;text-align:center;margin:24px 0'>{$code}</div>
                  <p style='color:#9ca3af;font-size:12px'>Bu kod 10 dakika geçerlidir. Paylaşmayın.</p>";
        return $this->send($to, "2FA Doğrulama Kodu", $this->template("Giriş Doğrulama Kodu", $body));
    }
}
