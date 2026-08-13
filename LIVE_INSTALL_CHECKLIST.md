# Canlı Kurulum Kontrol Listesi

## Sunucu hazırlığı

- PHP 8.3 veya üzeri ve gerekli PHP eklentileri etkin olmalı.
- MySQL 8 veya MariaDB 10.6+ veritabanı ile ayrı bir veritabanı kullanıcısı oluşturulmalı.
- Domain veya subdomain document root'u paketteki `santiye-kasa/public` klasörüne yöneltilmeli.
- HTTPS sertifikası etkinleştirilmeli.
- `storage` ve `bootstrap/cache` PHP kullanıcısı tarafından yazılabilir olmalı.

## İlk kurulum

1. Üretim ZIP paketini sunucuya yükleyip açın.
2. Tarayıcıdan `https://alan-adiniz.example/install` adresini açın.
3. MySQL bilgileri, gerçek uygulama adresi ve güçlü sistem yöneticisi parolasını girin.
4. Kurulum tamamlandıktan sonra giriş yapın ve şirket adını Sistem Yönetimi ekranından ayarlayın.
5. Personel ile şirket yöneticisi hesaplarını oluşturup başlangıç parolalarını değiştirin.
6. `/install` adresinin artık 404 döndürdüğünü doğrulayın.

## Yayın sonrası doğrulama

- `APP_ENV=production`, `APP_DEBUG=false` ve `SESSION_SECURE_COOKIE=true` olmalı.
- Gelir, normal gider, plakaya bağlı yakıt gideri ve bakım kaydı için birer deneme kaydı oluşturulmalı.
- Tanker yakıt alımının kasadan düştüğü, araç ikmalinin yalnızca tanker stokunu azalttığı ve araç maliyetinin son alış fiyatıyla hesaplandığı doğrulanmalı.
- Yüklenen deneme belgesinin yalnızca giriş yapıldıktan sonra indirilebildiği kontrol edilmeli.
- Personelin mali kayıt düzenleyemediği; şirket yöneticisinin gerekçeyle düzenleyebildiği doğrulanmalı.
- Sistem yöneticisi hesabının şirket yöneticisi tarafından değiştirilemediği doğrulanmalı.

## Yedekleme

- Her gün MySQL veritabanı ve `storage/app/private/documents` klasörü birlikte yedeklenmeli.
- Yedekler web kökünün dışında, erişim kontrollü ve tercihen şifreli tutulmalı.
- En az 14 günlük döngü ve ayda bir geri yükleme testi uygulanmalı.
- Güncellemeden önce ayrıca manuel tam yedek alınmalı.

Detaylı kurulum açıklamaları için `INSTALL.md`, cPanel için `CPANEL.md`, CWP için `CWP.md` ve güvenlik için `SECURITY.md` dosyalarını kullanın.
`360.natex.com.tr` için uçtan uca adımlar `DEPLOY_360_NATEX.md` dosyasındadır.
