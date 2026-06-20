# marketisleri.com - AI Destekli Broşür & Fiyat Analiz Platformu

marketisleri.com, Türkiye'nin önde gelen süpermarketlerinin (BİM, A101, ŞOK, Migros, Tarım Kredi vb.) haftalık aktüel ürün kataloglarını otomatik olarak tarayan, **Google Gemini 1.5 Flash Vision AI** kullanarak broşür sayfalarındaki ürün bilgilerini ve koordinatlarını çıkaran ve bu verileri interaktif, aranabilir ve karşılaştırılabilir bir veritabanına dönüştüren yenilikçi bir web uygulamasıdır.

> [!NOTE]
> Bu proje, **Kominike Digital** tarafından tasarlanmış ve geliştirilmiştir.

---

## 🚀 Temel Özellikler

### 1. Yapay Zekâ Destekli Broşür Analizi (Gemini Vision)
- **Otomatik OCR ve Ürün Çıkarma:** Broşür görselleri Google Gemini 1.5 Flash API'sine beslenerek ürün adları, fiyatları, eski fiyatları, birimleri ve görsel üzerindeki koordinat yüzdeleri (`x`, `y`, `w`, `h`) JSON formatında çıkarılır.
- **İnteraktif Hotspot (Sıcak Nokta) Görünümü:** Sayfa üzerindeki ürünlerin üzerine gelindiğinde (veya mobil cihazlarda tıklandığında) ürün bilgileri şık bir çerçeve içinde vurgulanır. Tıklandığında ise detay modalı açılır.

### 2. Otomatik Market Scraper (Örümcek) Sistemi
- **Çoklu Market Entegrasyonu:** BİM, A101, ŞOK, Migros ve Tarım Kredi Kooperatif Marketlerinin güncel kataloglarını otomatik olarak çeken entegre scraper'lar.
- **Akakçe Çoklu Kategori Aktarıcısı:** Akakçe üzerindeki Süpermarket, Elektronik, Kozmetik, Ev & Yaşam, Yapı Market vb. kategorilerdeki tüm broşürleri tek tıkla veya zamanlanmış görevlerle sisteme aktaran gelişmiş örümcek.

### 3. Kullanıcı Deneyimi & Akıllı Alışveriş Araçları
- **Ürün Arama Motoru:** Aktif tüm broşürlerdeki binlerce ürün arasında anlık arama yapabilme.
- **Fiyat Alarmı (Price Alerts):** Kullanıcıların belirlediği kelimeler veya ürünler hedef fiyatın altına düştüğünde otomatik e-posta bildirimi gönderilir.
- **E-Bülten Aboneliği:** Yeni broşürler eklendiğinde abonelere otomatik bilgilendirme gönderilir.
- **Mobil Uyumlu Fiyat Gösterimi:** Mobilde ekran karmaşasını önlemek için ürün fiyat rozetleri sadece tıklandığında/aktif olduğunda gösterilir.

### 4. Yönetim Paneli (`/admin`)
- **Dashboard:** Aktif, süresi dolmuş veya analiz bekleyen broşürlerin genel görünümü.
- **Sihirli Broşür Ekle:** PDF veya tek/çoklu görsel yükleyerek manuel broşür ekleme ve Gemini AI analiz kuyruğuna gönderme.
- **Scraper & Cron Kontrol Paneli:** Örümceklerin durumlarını ve CSS seçicilerini panelden yönetebilme.
- **Aboneler ve Fiyat Alarmları:** Bültene kayıtlı e-postaları ve kurulan fiyat alarmlarını izleme.

---

## 🛠️ Teknoloji Yığını

- **Backend:** PHP 8.x
- **Veritabanı:** SQLite (Yerel Geliştirme) / MySQL (Canlı Sunucu) - *PDO altyapısı ile dinamik geçiş.*
- **Frontend:** HTML5, Vanilla JavaScript, Tailwind CSS (Performans için optimize edilmiş `uploads/tailwind.min.css`)
- **AI API:** Google Gemini 1.5 Flash API
- **İkonlar:** Google Material Symbols Outlined
- **Sunucu / Dağıtım:** Apache, `.htaccess` (URL yeniden yazma kuralları)

---

## 💻 Yerel Kurulum Adımları

Yerel ortamınızda projeyi çalıştırmak için aşağıdaki adımları takip edin:

### 1. Dosyaları Klonlayın
```bash
git clone https://github.com/KominikeDigital/marketisleri.com.git
cd marketisleri.com
```

### 2. Yapılandırma Dosyasını Oluşturun
Proje kök dizininde `config.local.php` adında bir dosya oluşturarak yerel veritabanı ve Gemini API anahtarınızı girin:
```php
<?php
// config.local.php
define('DB_DRIVER', 'sqlite'); // SQLite kullanımı için
define('GEMINI_API_KEY', 'YOUR_GEMINI_API_KEY_HERE');
define('DEVELOPMENT_MODE', true);
```
*(Gerekli diğer genel ayarlar `config.php` içerisinde tanımlanmıştır ve veritabanı tabloları ilk çalıştırmada otomatik olarak oluşturulur.)*

### 3. Blog Yazılarını Yükleyin (Seeder)
Sistemde hazır 100 adet yüksek kaliteli ve SEO uyumlu blog yazısının yer alması için seeder betiğini çalıştırın:
```bash
php scratch/seed_blog.php
```
*(Bu komut `lib/default_blogs.php` içerisindeki 100 özgün tasarruf ve alışveriş makalesini SQLite veritabanına yükler).*

### 4. Yerel Sunucuyu Başlatın
PHP yerleşik sunucusunu kullanarak projeyi ayağa kaldırın:
```bash
php -S localhost:8000
```
Artık tarayıcınızdan `http://localhost:8000` adresine girerek projeyi test edebilirsiniz. Admin paneline erişmek için `http://localhost:8000/admin` adresini ziyaret edebilirsiniz.

---

## 📡 Canlı Sunucu Senkronizasyonu (Direct Git Bypass Sync)

Projeyi paylaşımlı hosting (cPanel) gibi SSH veya standart Git CLI erişimi bulunmayan ortamlara kolayca dağıtmak için özel bir senkronizasyon aracı eklenmiştir.

> [!WARNING]
> Bu araç güvenliğiniz için yalnızca belirli IP adreslerinden veya yetkili oturumlardan çalıştırılmalıdır.

- **Dosya:** `git_bypass_sync.php`
- **Çalışma Prensibi:** GitHub raw deposundaki güncel dosyaları tarar ve canlı sunucudaki dosyalar ile karşılaştırarak yalnızca değişen dosyaları indirir.
- **Kullanım:** Tarayıcıdan `https://marketisleri.com/git_bypass_sync.php` adresi ziyaret edilerek veya sunucu üzerinden doğrudan çalıştırılarak güncellemeler canlıya alınır.

---

## ⏰ Otomasyon ve Zamanlanmış Görevler (Cron Jobs)

Katalogların otomatik olarak çekilmesi ve Gemini AI analizi için sunucunuzda aşağıdaki Cron görevlerini tanımlamanız önerilir:

### 1. Market Kataloglarını Otomatik Çekme (Scraper)
Haftalık katalogların güncellenmesi için günde 2 kez çalışması önerilir:
```bash
# Her gün 08:00 ve 20:00'de scraper'ı çalıştırır
0 8,20 * * * php /home/kullanici/public_html/admin/auto_scraper.php > /dev/null 2>&1
```

### 2. Yapay Zekâ Analiz Kuyruğunu Çalıştırma
Analiz bekleyen broşür sayfalarının Gemini API üzerinden işlenmesi için her 5 dakikada bir çalıştırılması önerilir:
```bash
# Her 5 dakikada bir kuyruktaki sayfaları analiz eder
*/5 * * * * php /home/kullanici/public_html/admin/analyze_brochures.php --cli > /dev/null 2>&1
```

---

## 🔍 SEO & Site Haritası (Sitemap)

Sistemde SEO uyumluluğunu maksimumda tutmak için otomatik sitemap oluşturucu mevcuttur:
- **Site Haritası URL'si:** `https://marketisleri.com/sitemap.php`
- **Kapsam:** Statik sayfalar, aktif tüm marketler, eklenen broşür detay sayfaları (`viewer.php`) ve dinamik olarak eklenen 100 blog yazısının detay sayfaları otomatik olarak güncel tarihleriyle site haritasına eklenir.

---

## 🔒 Güvenlik

- `.htaccess` ile `database.db`, konfigürasyon dosyaları, scraper klasörleri ve SQL dökümlerine dışarıdan erişim engellenmiştir.
- Tüm SQL sorgularında SQL Injection önlemi olarak **PDO Prepared Statements** kullanılmıştır.
- Yönetim paneli oturum kontrolleri `admin/login.php` üzerinden güvenli bir şekilde yapılmaktadır.

---
*Geliştirici Notu: marketisleri.com bir Kominike Creative Digital projesidir. Tüm hakları saklıdır.*
