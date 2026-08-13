# Kurulum

## Gereksinimler

PHP 8.3+, MySQL 8 veya MariaDB 10.6+, Composer 2; PDO MySQL, Mbstring, OpenSSL, Fileinfo, JSON, Tokenizer ve XML eklentileri. `storage/` ile `bootstrap/cache/` web kullanıcısı tarafından yazılabilir olmalıdır.

## Web installer

1. Dağıtım paketini yükleyin ve document root’u `public/` klasörüne yöneltin.
2. HTTPS’i etkinleştirin ve tarayıcıdan `/install` yolunu açın.
3. Gereksinimleri, MySQL bilgilerini, site URL’sini ve güçlü ilk yönetici parolasını girin.
4. Mevcut `.env` varsa açık onay sonrasında zaman damgalı yedek alınır.
5. Bağlantı testinden sonra `.env`, `APP_KEY`, migration’lar, ilk yönetici ve `storage/app/installed.lock` oluşturulur.

Kilit oluşturulduktan sonra `/install` 404 döndürür. Kilidi URL parametresiyle aşma yolu yoktur. Yeniden kurulum gerekiyorsa önce veritabanı ve belgeler yedeklenmeli; kilit yalnızca sunucu dosya sistemi erişimiyle kaldırılmalıdır.

## Manuel kurulum

`.env.example` dosyasını `.env` olarak kopyalayın; MySQL, URL ve güçlü `ADMIN_*` değerlerini girin. Ardından:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan view:cache
```

`storage:link` gerekli değildir. Zamanlanmış görev bulunmadığından cron zorunlu değildir.

## Güncelleme

Veritabanı ile `storage/app/private/documents` yedeğini alın, bakım modunu açın, yeni dosyaları yükleyin, `composer install --no-dev`, `php artisan migrate --force`, cache temizleme/yeniden oluşturma ve test/smoke kontrolü yapın.
