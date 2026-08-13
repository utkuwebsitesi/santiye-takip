# Şantiye Takip mobil API ve mağaza yayını

Mobil uygulama canlıdaki veritabanına doğrudan bağlanmaz; Laravel'in `/api/v1/mobile` API'sini kullanır. Bu nedenle mobil uygulamadan önce aşağıdaki Laravel dosyaları canlı koda aktarılmalıdır:

- `routes/api.php`
- `app/Http/Controllers/Api/`
- `app/Http/Middleware/AuthenticateMobileToken.php`
- `app/Http/Middleware/EnsureMobileAdmin.php`
- `app/Http/Middleware/EnsureMobileSuperAdmin.php`
- `app/Models/MobileAccessToken.php`
- `config/mobile.php`
- `database/migrations/2026_08_03_000200_create_mobile_access_tokens_table.php`
- `bootstrap/app.php`

Bu aktarım mevcut kasa, yakıt, tanker, bakım ve kullanıcı kayıtlarını silmez. Migration yalnızca mobil token tablosunu ekler. cPanel'de SSH/Terminal yoksa migration'ı çalıştırabilen hosting desteği istenmeli; uygulama kurulumu sırasında Laravel'in migration çalıştırma adımı kullanılabilir.

## Canlı kontrol

Yayın öncesinde şu iki adres 200 dönmelidir:

- `https://360.natex.com.tr/health`
- `https://360.natex.com.tr/api/v1/mobile/auth/challenge`

`auth/challenge` 404 dönüyorsa mobil API dosyaları canlıya aktarılmamıştır; AAB/IPA doğru olsa bile mobil giriş çalışmaz.

## Veri güvenliği

Mobil kayıtlar sunucuda tutulur. Token cihazın Android Keystore/iOS Keychain alanında saklanır; uygulama boşta kalma süresi dolduğunda yerel token silinir ve sunucu oturumu kapatılır. Kasa, tanker, yakıt ve bakım işlemlerinin stok/kasa hesapları Laravel tarafında transaction içinde yapılır.

## Google Play

1. `mobile/android/key.properties.example` dosyasını `key.properties` olarak kopyalayın ve Play App Signing upload JKS bilgilerini girin.
2. `mobile` klasöründe `flutter build appbundle --release --dart-define=API_BASE_URL=https://360.natex.com.tr/api/v1/mobile` çalıştırın.
3. `build/app/outputs/bundle/release/app-release.aab` dosyasını Play Console'a yükleyin.
4. Mağaza açıklaması, gizlilik politikası URL'si, uygulama erişim bilgileri ve ekran görüntülerini doldurun.

## Apple App Store

Windows'ta IPA imzalanamaz. macOS üzerinde `mobile/ios/Runner.xcworkspace` açılıp Apple Developer Team, Bundle Identifier ve signing seçilmelidir. Sonrasında Xcode Archive > Distribute App ile TestFlight/App Store'a gönderilir. `API_BASE_URL` canlı HTTPS adresi olarak kalmalıdır.
