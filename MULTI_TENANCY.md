# Şantiye Takip — Çok Müşterili Mimari

## Karar

Veri tabanı kullanıcı başına değil, müşteri/şantiye başına ayrılmalıdır. Aynı müşterinin şirket yöneticisi ve personeli aynı operasyon verisini görür; farklı müşterilerin verileri fiziksel olarak farklı MySQL veritabanlarında tutulur.

Önerilen yapı:

```text
Platform veritabanı (merkezi)
├─ tenants                 müşteri/şantiye kataloğu
├─ tenant_domains          alan adı veya alt alan adı eşleşmeleri
├─ tenant_memberships      kullanıcı–müşteri yetkileri
└─ platform_audit_logs     sistem yöneticisi işlemleri

Müşteri veritabanı (her müşteri için ayrı)
├─ users
├─ transactions, fuel_entries, maintenance_entries
├─ tankers, tanker_movements, vehicles
├─ notifications, audit_logs, documents
└─ mevcut Şantiye Takip tablolarının tamamı
```

## Tenant çözümleme

1. İstek önce alan adından tenant'ı çözer: `acme.360.natex.com.tr`.
2. Alt alan adı kullanılamıyorsa giriş ekranında müşteri kodu kullanılabilir.
3. Tenant pasifse veya veritabanı bağlantısı doğrulanamıyorsa uygulama güvenli bir bakım ekranı gösterir.
4. Tenant belirlendikten sonra `TenantManager` yalnızca o tenant'ın bağlantısını runtime olarak açar.
5. Tenant isteği boyunca varsayılan Laravel bağlantısı tenant veritabanına yönlendirilir. Merkezi modeller açıkça `central` bağlantısını kullanır; böylece yanlış veritabanına sorgu gitmesi engellenir.

## Merkezi katalog alanları

`tenants` tablosu:

- `id`, `name`, `slug`, `status`
- `db_host`, `db_port`, `db_name`, `db_username`
- `db_password_encrypted`
- `app_url`, `created_at`, `updated_at`

Veritabanı parolası düz metin tutulmaz; Laravel application key ile şifrelenir. Yönetim paneli parolayı tekrar göstermez.

`tenant_memberships` tablosu:

- `tenant_id`, `username`, `role`, `is_active`
- Girişte tenant kodu/alan adı ile kullanıcı eşleştirilir.
- Asıl operasyon kullanıcı kaydı tenant veritabanındaki `users` tablosunda tutulmaya devam eder; mevcut foreign key'ler bozulmaz.

## Giriş ve mobil API

- Web girişine müşteri kodu seçeneği eklenir; alt alan adında bu alan otomatik doldurulur.
- Mobil login isteğine `tenant_slug` veya `tenant_code` eklenir.
- Captcha doğrulaması merkezi katmanda yapılır; parola doğrulamasından sonra tenant bağlantısı açılır.
- Mobil erişim token'ı tenant bağlamı taşır. Token başka bir tenant'ın endpoint'inde kullanılırsa 401 döner.
- Token, session, cache ve dosya yollarında tenant öneki kullanılır; müşteriler arasında oturum/cache karışması önlenir.

## Tenant oluşturma akışı

Sistem yöneticisi panelinden:

1. Müşteri adı, slug ve müşteri alan adı girilir.
2. Benzersiz MySQL veritabanı ve kullanıcı bilgileri tanımlanır.
3. Tenant veritabanı migration'ları çalıştırılır.
4. Varsayılan kategori, menü, tanker ve ilk yönetici hesabı oluşturulur.
5. Bağlantı testi yapılır.
6. Kullanıcıya giriş adresi ve ilk parola güvenli kanaldan teslim edilir.

İlk sürümde cPanel otomasyonu yoksa bu adımlar kontrollü bir provisioning ekranı ve cPanel'de manuel DB oluşturma ile yapılabilir. Daha sonra cPanel API ile otomatikleştirilebilir.

## Mevcut canlı verinin geçişi

Mevcut veritabanı ilk tenant olarak korunur; tablolar taşınmaz ve silinmez:

1. Mevcut veritabanı `default` tenant olarak merkezi kataloğa kaydedilir.
2. Merkezi platform veritabanı ayrı oluşturulur.
3. Mevcut `.env` bilgileri ilk tenant bağlantı bilgileri olarak şifreli kataloğa alınır.
4. Uygulama önce `TENANCY_ENABLED=false` ile doğrulanır.
5. Read-only sağlık ve bağlantı kontrolleri başarılı olunca tenant çözümleme etkinleştirilir.
6. Eski URL için geriye dönük tenant kodu/varsayılan tenant fallback'i yalnızca geçiş döneminde açık tutulur; kalıcı olarak kapatılır.

Bu yaklaşım mevcut kasa, yakıt, tanker, bakım, kullanıcı ve belge kayıtlarını yeniden yazmayı gerektirmez.

## Yedekleme ve izolasyon

- Her tenant için ayrı, isimlendirilmiş ve sıkıştırılmış SQL yedeği alınır.
- Yedek dosyası tenant klasörüne yazılır; geri yükleme tenant seçilmeden çalışmaz.
- Merkezi katalog ayrıca yedeklenir; katalog olmadan tenant bağlantı bilgileri kullanılamaz.
- Yedekler `.part` dosyasına yazılır, checksum oluşturulur ve yalnızca tamamlanan dosya görünür.
- Tenant silme fiziksel silme değil, önce `suspended`/`archived` durumudur. Fiziksel silme için ayrı ve çift onaylı bakım işlemi gerekir.

## Uygulama sırası

1. `config/tenancy.php`, merkezi connection ve tenant context servisleri.
2. Merkezi migration'lar: `tenants`, `tenant_domains`, `tenant_memberships`.
3. Web ve mobil `ResolveTenant` middleware'i.
4. Giriş ekranında tenant kodu/alt alan adı desteği.
5. Sistem yöneticisi tenant oluşturma, bağlantı testi ve pasifleştirme ekranları.
6. Per-tenant backup, migration runner ve sağlık kontrolü.
7. İzolasyon testleri: tenant A'nın transaction, fuel, documents, users ve API token'ı tenant B'de görünmemeli.
8. Staging'de veri kopyasıyla smoke test; ardından canlıda tek tenant geçişi.

## Güvenlik kuralları

- Tenant bağlantı bilgileri yalnızca merkezi sunucuda tutulur; mobil uygulamaya gönderilmez.
- Her API isteğinde tenant ve token birlikte doğrulanır.
- Merkezi yönetici tenant seçebilir; şirket yöneticisi yalnızca kendi tenant'ını görür.
- Tenant filtresi yerine fiziksel veritabanı ayrımı kullanıldığı için unutulmuş `where tenant_id` filtresi riski azaltılır.
- Tenant bağlantısı bulunamazsa uygulama varsayılan veritabanına sessizce düşmez.

Bu doküman mimari hazırlıktır. Mevcut tek tenant canlı verisine dokunmadan kodlama, staging geçişi ve ardından canlı aktivasyon ayrı adımlar halinde yapılmalıdır.
