# Şantiye Takip mobil uygulaması

Bu klasör, Şantiye Takip Laravel uygulamasının Android ve iOS istemcisidir. Uygulama veriyi yerel olarak çoğaltmaz; tüm kayıtlar canlı Laravel mobil API'sinden okunur ve güvenli token ile kaydedilir.

## Kapsam

- Captcha'lı giriş, güvenli token saklama, otomatik boşta kalma çıkışı
- Gösterge paneli, kasa hareketi ekleme ve yönetici silme
- Tankerden araca yakıt verme; litre, tanker stoğu, birim maliyet, kilometre ve çalışma saati takibi
- Tanker yakıt alımı; yakıt bedelinin kasadan düşmesi
- Araç/makine tanımı; sayaç takibini açıp kapatma
- Bakım/onarım, sonraki bakım tarihi ve sayaç uyarıları
- Kasa-yakıt raporları ve tarih filtresi
- Geçici ve geçmiş bildirimler
- Yönetici: tanker, araç/makine, kullanıcı ve işlem geçmişi yönetimi
- Sistem yöneticisi: yazılım/şirket ayarları, kategori ekleme ve veritabanı yedeği

## API adresi

Varsayılan canlı adres:

`https://360.natex.com.tr/api/v1/mobile`

Başka bir sunucu için derleme sırasında `--dart-define=API_BASE_URL=...` verin. API'nin HTTPS üzerinde ve mobil API route'larının canlı sürümde bulunması gerekir.

## Yerel doğrulama

```powershell
flutter pub get
flutter analyze
flutter test
flutter build apk --release --dart-define=API_BASE_URL=https://360.natex.com.tr/api/v1/mobile
flutter build appbundle --release --dart-define=API_BASE_URL=https://360.natex.com.tr/api/v1/mobile
```

Çıktılar:

- `build/app/outputs/flutter-apk/app-release.apk`
- `build/app/outputs/bundle/release/app-release.aab`

## Google Play imzası

Play Console için uygulama imzalama anahtarı gerekir. `android/key.properties.example` dosyasını `android/key.properties` olarak kopyalayıp kendi JKS bilgilerinizi girin. `key.properties` repoya eklenmemelidir. Sonra AAB'yi yeniden üretin. Uygulama kimliği `com.utkuweb.santiye_takip` olarak sabittir; Play Console'da yeni uygulamayı bu kimlikle açın.

## App Store

iOS release arşivi macOS + Xcode üzerinde alınmalıdır:

```bash
flutter pub get
flutter build ios --release --dart-define=API_BASE_URL=https://360.natex.com.tr/api/v1/mobile
```

Xcode'da `mobile/ios/Runner.xcworkspace` açıp Apple Developer Team, Bundle Identifier, signing ve App Store Connect kaydını seçin; ardından Archive > Distribute App ile yükleyin. Bundle Identifier mevcut projede `com.utkuweb.santiyeTakip` olarak tanımlıdır; yayın hesabınıza göre tek bir kimlikte sabitleyin.

## Canlıya çıkmadan önce

1. Canlı Laravel dosyaları ve veritabanı yedeğini alın.
2. `https://360.natex.com.tr/health` ve `https://360.natex.com.tr/api/v1/mobile/auth/challenge` adreslerini kontrol edin.
3. Android AAB ile giriş, kasa, yakıt, tanker alımı, bakım, rapor ve yönetici yetkilerini gerçek test hesabıyla deneyin.
4. App Store/Play Store gizlilik formunda kullanıcı hesabı, finans kayıtları, bakım-yakıt verileri ve sunucuya aktarılan tanımlayıcıları belirtin.
5. API adresi değişirse yeni sürüm yayınlamadan önce `API_BASE_URL` ile yeniden derleyin.
