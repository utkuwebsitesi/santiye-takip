# Değişiklik Günlüğü

## 2026-08-03

- Gösterge paneli afişteki açık kart, renk, küçük başlık ve çizgi grafik diline yaklaştırıldı; tanker görseli şeffaf arka planlı sürüme geçirildi.
- Temiz kurulumda yalnızca Tanker 1 varsayılan olarak oluşturulacak şekilde sadeleştirildi.
- Yöneticiye tanker ekleme, ad değiştirme ve kullanılmayan sıfır stoklu tankeri pasife alma ekranı eklendi; aktif tankerler gösterge, stok ve rapor özetine otomatik bağlandı.

## 2026-07-30

- Laravel uyumlu güvenli `.gitignore` ve Composer kilit dosyası eklendi.
- Araç plakası/makine kodu koşullu doğrulama ve normalizasyonu eklendi.
- Devreden, aylık net ve gerçek toplam kasa bakiyesi ayrıştırıldı.
- Güvenli üretim admin seed davranışı ve kullanıcı/parola yönetimi eklendi.
- Pasif mevcut oturumlar ve son aktif yönetici koruması eklendi.
- Controller kontrollü özel belge/fiş yükleme ve indirme eklendi.
- Aktif araç ve kronolojik önceki/sonraki sayaç kontrolü eklendi.
- Audit olayları oluşturma, kullanıcı ve parola işlemlerini kapsayacak şekilde genişletildi.
- Filtrelenebilir kasa/yakıt raporları, günlük/aylık toplam ve tüketim verimliliği eklendi.
- Tek kullanımlık `/install` web sihirbazı ve CWP/cPanel belgeleri eklendi.
- Mobil menü, formlar, boş/silinmiş kayıt durumları iyileştirildi.
- Personel, şirket yöneticisi ve sistem yöneticisi rolleri ayrıştırıldı.
- Sistem yöneticisine özel yazılım/şirket ayarları, kategori ve menü bölümü yönetimi eklendi.
- Kategori alanı yazıp silmeyi gerektirmeyen, işlem türüne göre filtrelenen gerçek seçim kutusuna dönüştürüldü.
- Araç/makine bakım ve onarım geçmişi, sonraki bakım uyarısı, özel servis belgesi ve isteğe bağlı kasa gideri bağlantısı eklendi.
- Giriş ekranına süreli, tek kullanımlık matematik CAPTCHA doğrulaması eklendi.
- Araç ve makinelerde kilometre ile çalışma saati aynı anda ve birbirinden bağımsız takip edilebilir hale getirildi.
- Bakım tarihi veya KM/çalışma saati eşiği dolduğunda tüm girişli ekranlarda sağ alt bakım uyarısı eklendi.
- Araç ve makine yakıt giderleri, yakıt maliyet raporlarında korunarak ana kasa bakiyesi ve kasa hareketlerinden ayrıldı; mevcut yakıt kayıtları da geriye dönük dönüştürüldü.
- Tanker 1, Tanker 2 ve Mobil Tanker için litre stoku, son alış fiyatına göre maliyet, kasadan düşen yakıt alımı ve stoktan araç/makine ikmali akışı eklendi.
- Bakım uyarıları sekiz saniyelik geçici bildirime dönüştürüldü; sağ üst bildirim zili, okunmamış sayacı ve kalıcı bildirim geçmişi eklendi.
- LiteSpeed/cPanel temiz kurulumunda `/install` yönlendirmesi için `.htaccess` ve `.env` oluşmadan önce güvenli geçici uygulama anahtarı desteği eklendi.
- Üretim ZIP girdileri Linux/cPanel üzerinde gerçek klasörlere açılacak biçimde ileri eğik çizgili yollarla paketlenmeye başlandı.
- Yarım kalan web kurulumunda oturum ve önbelleğin henüz oluşmamış veritabanı tablolarına bağlanması engellendi; kurucu tekrar açılabilir hale getirildi.
- Web kurucusuna gerek kalmadan phpMyAdmin ile etkinleştirme için MySQL şeması, boş veritabanı alanlı `.env` ve tek kullanımlık yönetici bilgileri üreten manuel kurulum paketi eklendi.
- Eski MySQL/MariaDB cPanel sunucularındaki 1000 bayt indeks sınırı için varsayılan metin indeksleri 191 karaktere indirildi; manuel SQL yarım kalan tabloları güvenle yeniden oluşturacak şekilde güncellendi.
- Üretim bağımlılıkları PHP 8.3 platformuna kilitlendi; PHP 8.3 sunucuda Laravel başlamadan oluşan Composer `>=8.4.1` 500 hatası giderildi.
- cPanel için sağlanan üretim ortam dosyasındaki veritabanı alanlarını doğrulayıp eksik `APP_KEY` değerini güvenli biçimde tamamlayan ortam sonlandırma aracı eklendi.
- Giriş CAPTCHA'sı oturumdaki değişken soru yerine şifreli, 10 dakika geçerli ve tek kullanımlık form belirtecine taşındı; sayfa yenileme ve çoklu sekme kaynaklı yanlış doğrulamalar giderildi.
- Mobil giriş ekranı güvenli alan boşlukları, tam genişlikte kart, 48-50 piksel dokunma alanları, 16 piksel giriş yazıları ve kısa ekran uyarlamasıyla yenilendi.
- iPhone Safari mobil menüsü dinamik ekran yüksekliğine sabitlendi; yönetici/parola ve çıkış alanı görünür alt bölümde tutulurken menü bağlantıları bağımsız kaydırılabilir hale getirildi.
- Kalıcı “beni hatırla” oturumu kaldırıldı; tarayıcı kapanışında sona eren çerez ve 15 dakikalık hareketsizlik sonrası zorunlu otomatik çıkış eklendi.
- Giriş kartına masaüstü ve mobil görünümle uyumlu, sade bir UTKUWEB geliştirici imzası eklendi.
- UTKUWEB geliştirici imzası, markanın şeffaf arka planlı özgün logosuyla güncellendi.
- Giriş, yönetim ekranları ve statik dosyalarda tarayıcı önbelleği kapatıldı; canlı güncellemelerin eski ekranlarla karışması engellendi.
- Sayaç istisnası alanına, yalnızca KM veya çalışma saati önceki değerden düşük olduğunda doldurulacağını açıklayan kısa bilgi notu eklendi.
- Düzeltme ve silme geçmişindeki teknik JSON görünümü; Türkçe kayıt, işlem ve alan adlarıyla “önce / sonra” karşılaştırmasına dönüştürüldü.
- Üst çubuğa Ankara için beş günlük hava tahmini ile TCMB USD/EUR satış kurlarını gösteren kompakt bilgi alanı eklendi.
- Mobil üst çubuğa bugünün hava ikonunu ve sıcaklığını gösteren; dokunulduğunda beş günlük tahmin, USD/EUR kurları ve güncelleme saatlerini açan kompakt bilgi rozeti eklendi.
- iPhone üst çubuğunda sayfa başlığı ve tarih kaldırıldı; menü, kompakt “Ankara · hava · sıcaklık” rozeti ve bildirim zili tek satıra yerleştirildi.
- Mobil üst çubuktaki boş alana, aktif sayfanın adını gösteren ince altın vurgulu ve uzun metinleri otomatik kısaltan kompakt başlık kutusu eklendi.
- Mobil sayfa başlığı ve hava rozeti; aynı yükseklik, köşe, çerçeve, nötr degrade ve gölge değerleriyle simetrik, daha kurumsal bir görünüme getirildi.
- Mobil sayfa başlığındaki buton görünümü kaldırıldı; hava kartıyla aynı dikey ölçüde, ince altın çizgili sade tipografik başlığa dönüştürüldü.
- LiteSpeed statik dosya önbelleğine karşı CSS adresine dosya değişiklik zamanından üretilen otomatik sürüm eklendi; yeni tasarımın iPhone Safari’ye hemen ulaşması sağlandı.
- cPanel dosya yolu/izin kısıtlarında 500 hatasına yol açabilen çalışma anı `filemtime` sorgusu kaldırıldı; CSS önbellek kırma güvenli sabit sürüm etiketiyle sürdürüldü.
