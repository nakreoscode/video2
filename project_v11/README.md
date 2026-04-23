# 🎬 NakreosStream

**Saf PHP + MySQL + Tailwind CSS** ile yazılmış tam özellikli video arama & yayın platformu.

---

## 🚀 Hızlı Kurulum

### 1. Dosyaları Sunucuya Yükle
Tüm dosyaları web sunucunuzun kök dizinine (`public_html` veya `www`) yükleyin.

### 2. Veritabanı Oluştur
MySQL'de yeni bir veritabanı oluşturun:
```sql
CREATE DATABASE nakreosstream CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Kurulum Sihirbazını Çalıştır
Tarayıcıdan açın: `https://yourdomain.com/install/`

Adımları tamamladıktan sonra `/install/` klasörünü **silin**.

### 4. Admin Paneline Giriş
`https://yourdomain.com/admin/`

Varsayılan: `admin` / `admin123` — **Mutlaka değiştirin!**

---

## ⚙️ Sunucu Gereksinimleri

| Gereksinim | Versiyon |
|---|---|
| PHP | 8.0+ |
| MySQL / MariaDB | 5.7+ / 10.3+ |
| PDO MySQL extension | ✓ |
| cURL extension | ✓ |
| JSON extension | ✓ |
| Apache mod_rewrite | ✓ |

---

## 🔑 API Anahtarları Kurulumu

Admin panelinden **Platformlar** bölümüne gidin:

### YouTube Data API v3
1. [Google Cloud Console](https://console.cloud.google.com) → API & Services
2. YouTube Data API v3'ü etkinleştirin
3. Credentials → API Key oluşturun

### Dailymotion API
1. [Dailymotion Developer](https://developer.dailymotion.com) hesabı açın
2. Application oluşturun → API Key alın

### Vimeo API
1. [developer.vimeo.com](https://developer.vimeo.com) → My Apps
2. Personal Access Token oluşturun (okuma izni)

### Twitch API
1. [dev.twitch.tv](https://dev.twitch.tv/console) → Console
2. Client ID + Client Secret alın

### Unsplash API
1. [unsplash.com/developers](https://unsplash.com/developers) → Your Applications
2. Access Key kopyalayın

### Pixabay API
1. [pixabay.com/api/docs](https://pixabay.com/api/docs/) → API Key

---

## ☁️ Wasabi Cloud Storage

1. [wasabi.com](https://wasabi.com) → Sign Up
2. Bucket oluşturun (ör. `nakreos-videos`)
3. Access Key + Secret Key oluşturun
4. Admin → Depolama Ayarları'na girin

---

## 📱 SMS (İletimerkezi)

1. [iletimerkezi.com](https://iletimerkezi.com) hesabı açın
2. Admin → SMS Ayarları'na kullanıcı adı/şifre girin

---

## 💳 Ödeme Sistemi

### PayTR
1. [paytr.com](https://paytr.com) → Merchant hesabı
2. Merchant ID, Key, Salt → Admin → Genel Ayarlar

### Shopier
1. [shopier.com](https://shopier.com) → API entegrasyonu
2. API Key + Secret → Admin → Genel Ayarlar

---

## 📁 Dosya Yapısı

```
nakreosstream/
├── admin/              # Yönetim paneli
├── ajax/               # AJAX endpoint'leri
├── api/                # REST API (v1)
├── assets/             # CSS, JS, görseller
│   └── img/avatars/    # Kullanıcı avatarları
│   └── img/thumbs/     # Video thumbnail'ları
├── database/           # SQL şema
├── includes/           # PHP sınıfları ve partial'lar
├── install/            # Kurulum sihirbazı (kurulumdan sonra sil!)
├── languages/          # Dil dosyaları (tr, en)
├── index.php           # Ana sayfa
├── login.php           # Giriş
├── register.php        # Kayıt
├── watch.php           # Video izleme
├── dashboard.php       # Kullanıcı paneli
├── profile.php         # Profil sayfası
├── upload.php          # Video yükleme
├── playlist.php        # Playlist yönetimi
├── checkout.php        # Pro üyelik ödemesi
├── category.php        # Kategori sayfası
├── sitemap.php         # XML Sitemap
├── rss.php             # RSS Feed
├── manifest.json       # PWA manifest
├── sw.js               # Service Worker
├── .htaccess           # URL yönlendirme + güvenlik
└── config.php          # DB + Admin yapılandırma (install sonrası oluşur)
```

---

## 🔒 Güvenlik

- Tüm SQL sorguları PDO prepared statements
- CSRF token koruması tüm formlarda
- XSS koruması (htmlspecialchars)
- Güvenli şifre hashleme (bcrypt)
- Session güvenliği (regenerate_id)
- `.htaccess` ile hassas dosyalara erişim engeli

---

## 🌐 REST API Kullanımı

API key'inizi Dashboard → API sekmesinden alın.

```bash
# Video arama
GET /api/v1.php?action=search&q=müzik&api_key=YOUR_KEY

# Trend videolar
GET /api/v1.php?action=trending&api_key=YOUR_KEY

# Kullanıcı bilgisi
GET /api/v1.php?action=user&api_key=YOUR_KEY

# Kaydedilen videolar
GET /api/v1.php?action=saved&api_key=YOUR_KEY
```

---

## 📄 Lisans

Bu proje özel kullanım içindir. Ticari kullanım için lütfen iletişime geçin.

---

**NakreosStream** — Tüm videoları tek platformda 🎬
