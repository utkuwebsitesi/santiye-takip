# 360.natex.com.tr Canlı Kurulum Rehberi

Bu rehber, üretim ZIP paketinin `https://360.natex.com.tr` adresine cPanel/CWP üzerinden ilk kez kurulması içindir.

## 1. Kurulumdan önce

1. Sunucuda PHP 8.3 veya üzerini seçin.
2. `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `json`, `tokenizer`, `xml`, `ctype` ve `curl` eklentilerini etkinleştirin.
3. Boş bir MySQL/MariaDB veritabanı ve yalnızca bu veritabanına yetkili ayrı bir kullanıcı oluşturun.
4. Veritabanı adı, kullanıcı adı ve parolayı güvenli bir yerde saklayın.
5. `360.natex.com.tr` için SSL sertifikasını etkinleştirin ve HTTP trafiğini HTTPS'e yönlendirin.

## 2. Dosyaları yükleme

1. `santiye-kasa-production-*.zip` paketini web hesabında ana alan adının dışındaki uygun bir uygulama klasörüne yükleyin.
2. ZIP'i açtığınızda oluşan `santiye-kasa` klasörünü koruyun.
3. Subdomain document root'unu kesinlikle uygulama köküne değil, şu klasöre yöneltin:

```text
/tam/sunucu/yolu/santiye-kasa/public
```

4. Tarayıcıda `https://360.natex.com.tr/composer.json` adresinin 404 verdiğini doğrulayın. Dosya indiriliyorsa document root yanlıştır ve kuruluma devam edilmemelidir.
5. `storage` ve `bootstrap/cache` klasörlerini PHP kullanıcısının yazabileceği izinlere getirin. Genellikle klasörler için `755` veya sunucu yapısına göre `775` yeterlidir; `777` kullanmayın.

## 3. Web kurulum sihirbazı

1. `https://360.natex.com.tr/install` adresini açın.
2. Uygulama adresine tam olarak `https://360.natex.com.tr` yazın.
3. Veritabanı sunucusu çoğu panelde `localhost`, port ise `3306` olur.
4. Oluşturduğunuz boş veritabanının adını, kullanıcısını ve parolasını girin.
5. Sistem yöneticisi için tahmin edilmesi zor, en az 12 karakterli; büyük harf, küçük harf ve rakam içeren benzersiz parola belirleyin.
6. Kurulumu tamamlayın. Sistem `.env`, uygulama anahtarı, veritabanı tabloları ve kurulum kilidini oluşturur.
7. Kurulumdan sonra `/install` adresinin 404 verdiğini doğrulayın.

## 4. İlk yapılandırma

1. Sistem yöneticisiyle giriş yapın.
2. Sistem Yönetimi ekranından yazılım ve şirket adını kontrol edin.
3. Bir şirket yöneticisi ve bir personel hesabı oluşturun; ilk girişte parolalarını değiştirmelerini sağlayın.
4. Araç ve makineleri ekleyin. Özel araçlarda “KM ve çalışma saati takibi yap” seçeneğini kapatın.
5. İlk kurulumda yalnızca Tanker 1, 0 litreyle gelir. Yeni tanker gerekirse **Tanker Stokları → Tankerleri Yönet** ekranından ekleyin. Ardından **Tanker Stokları → Tankere Yakıt Al** ekranından gerçek satın alımı girin.
6. Tanker alımının kasadan düştüğünü; tanker stokunun arttığını doğrulayın.
7. Araç ikmali girerek verilen litrenin tanker stokundan düştüğünü ve araç maliyetinin tankerin son alış fiyatıyla hesaplandığını doğrulayın.

## 5. Yayın sonrası kontrol

- Ana sayfa, giriş, tanker stokları, araç ikmali, bakım ve rapor ekranlarını açın.
- Normal gelir/gider kaydının kasayı etkilediğini kontrol edin.
- Tankere yakıt alımının kasadan yalnızca bir kez düştüğünü kontrol edin.
- Tankerden araca verilen yakıtın kasayı tekrar etkilemediğini kontrol edin.
- Stoktan fazla litre verilmesinin engellendiğini kontrol edin.
- Bakım tarihi/KM/saat uyarısının geçici gösterildiğini ve sağ üst bildirim geçmişinde kaldığını kontrol edin.
- Yüklenen belgeyi çıkış yaptıktan sonra doğrudan açamadığınızı doğrulayın.
- `.env`, `composer.json`, `database` ve `storage` yollarının web üzerinden erişilemediğini kontrol edin.

## 6. Yedekleme

Her gün aynı yedek setinde:

1. MySQL veritabanı dökümünü,
2. `storage/app/private/documents` klasörünü,
3. üretim `.env` dosyasını

yedekleyin. Yedekleri `public` klasörünün dışında, erişim kontrollü ve tercihen şifreli saklayın. En az 14 günlük döngü kullanın ve ayda bir ayrı ortamda geri yükleme testi yapın.

## 7. Güncelleme

1. Veritabanı ve özel belgelerin tam yedeğini alın.
2. Uygulamayı bakım moduna alın: `php artisan down`.
3. Yeni paket dosyalarını yükleyin; üretim `.env` dosyasını ve `storage/app/private/documents` içeriğini silmeyin.
4. Sunucu terminalinde çalıştırın:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php artisan up
```

5. Giriş, kasa, tanker ve bildirim kontrollerini yeniden uygulayın.
