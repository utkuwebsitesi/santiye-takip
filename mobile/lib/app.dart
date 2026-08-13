import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import 'core/api_client.dart';

const navy = Color(0xff0b1f3a);
const gold = Color(0xfff2b84b);
const surface = Color(0xfff5f7fb);
const muted = Color(0xff64748b);

void runSantiyeTakip() {
  Intl.defaultLocale = 'tr_TR';
  runApp(const SantiyeTakipApp());
}

class SantiyeTakipApp extends StatefulWidget {
  const SantiyeTakipApp({super.key});

  @override
  State<SantiyeTakipApp> createState() => _SantiyeTakipAppState();
}

class _SantiyeTakipAppState extends State<SantiyeTakipApp> {
  final api = ApiClient();
  final store = const SecureSessionStore();
  MobileSession? session;
  bool starting = true;

  @override
  void initState() {
    super.initState();
    restoreSession();
  }

  Future<void> restoreSession() async {
    final saved = await store.read();
    if (saved != null) {
      api.token = saved.token;
      try {
        final response = await api.get('/auth/me');
        session = MobileSession(
          token: saved.token,
          user: mapOf(response['user']),
          idleMinutes: intOf(
            response['idle_timeout_minutes'],
            saved.idleMinutes,
          ),
        );
        await store.write(session!);
      } on ApiException {
        await store.clear();
        api.token = null;
      }
    }
    if (mounted) setState(() => starting = false);
  }

  Future<void> signedIn(MobileSession value) async {
    api.token = value.token;
    await store.write(value);
    if (mounted) setState(() => session = value);
  }

  Future<void> signOut({bool notifyServer = true}) async {
    final oldToken = api.token;
    api.token = null;
    await store.clear();
    if (mounted) setState(() => session = null);
    if (notifyServer && oldToken != null) {
      api.token = oldToken;
      try {
        await api.post('/auth/logout');
      } catch (_) {
        // Yerel anahtar silindi; sunucu tokeni kısa sürede zaten kapanır.
      } finally {
        api.token = null;
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = ThemeData(
      useMaterial3: true,
      colorScheme: ColorScheme.fromSeed(seedColor: navy),
      scaffoldBackgroundColor: surface,
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.white,
        foregroundColor: navy,
        surfaceTintColor: Colors.white,
      ),
      inputDecorationTheme: const InputDecorationTheme(
        border: OutlineInputBorder(),
      ),
      cardTheme: CardThemeData(
        color: Colors.white,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: const BorderSide(color: Color(0xffdfe6ef)),
        ),
      ),
    );
    return MaterialApp(
      title: 'Şantiye Takip',
      debugShowCheckedModeBanner: false,
      theme: theme,
      home: starting
          ? const LoadingScreen()
          : session == null
          ? LoginPage(api: api, onSignedIn: signedIn)
          : MobileShell(api: api, session: session!, onSignOut: signOut),
    );
  }
}

class LoadingScreen extends StatelessWidget {
  const LoadingScreen({super.key});

  @override
  Widget build(BuildContext context) =>
      const Scaffold(body: Center(child: CircularProgressIndicator()));
}

class LoginPage extends StatefulWidget {
  const LoginPage({super.key, required this.api, required this.onSignedIn});
  final ApiClient api;
  final Future<void> Function(MobileSession) onSignedIn;

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final form = GlobalKey<FormState>();
  final username = TextEditingController();
  final password = TextEditingController();
  final captcha = TextEditingController();
  String? question;
  String? captchaToken;
  String? error;
  bool busy = false;

  @override
  void initState() {
    super.initState();
    refreshCaptcha();
  }

  Future<void> refreshCaptcha() async {
    try {
      final response = await widget.api.get(
        '/auth/challenge',
        authenticated: false,
      );
      final data = mapOf(response['data']);
      if (mounted) {
        setState(() {
          question = stringOf(data['question']);
          captchaToken = stringOf(data['token']);
          captcha.clear();
          error = null;
        });
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => error = e.message);
    }
  }

  Future<void> login() async {
    if (!(form.currentState?.validate() ?? false) || captchaToken == null) {
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final response = await widget.api.post(
        '/auth/login',
        authenticated: false,
        body: {
          'username': username.text.trim(),
          'password': password.text,
          'captcha': captcha.text.trim(),
          'captcha_token': captchaToken,
          'device_name': 'Şantiye Takip Mobil',
        },
      );
      await widget.onSignedIn(
        MobileSession(
          token: stringOf(response['token']),
          user: mapOf(response['user']),
          idleMinutes: intOf(response['idle_timeout_minutes'], 15),
        ),
      );
    } on ApiException catch (e) {
      if (mounted) setState(() => error = e.message);
      await refreshCaptcha();
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  void dispose() {
    username.dispose();
    password.dispose();
    captcha.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    body: SafeArea(
      child: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(22),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 440),
            child: Card(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Form(
                  key: form,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      const BrandHeader(centered: true),
                      const SizedBox(height: 28),
                      Text(
                        'Güvenli giriş',
                        style: Theme.of(context).textTheme.headlineSmall
                            ?.copyWith(
                              fontWeight: FontWeight.w900,
                              color: navy,
                            ),
                      ),
                      const SizedBox(height: 18),
                      TextFormField(
                        controller: username,
                        decoration: const InputDecoration(
                          labelText: 'Kullanıcı adı',
                          prefixIcon: Icon(Icons.person_outline),
                        ),
                        validator: requiredText,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: password,
                        obscureText: true,
                        decoration: const InputDecoration(
                          labelText: 'Şifre',
                          prefixIcon: Icon(Icons.lock_outline),
                        ),
                        validator: requiredText,
                      ),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: TextFormField(
                              controller: captcha,
                              keyboardType: TextInputType.number,
                              decoration: InputDecoration(
                                labelText: question ?? 'Doğrulama yükleniyor',
                              ),
                              validator: requiredText,
                            ),
                          ),
                          IconButton(
                            onPressed: busy ? null : refreshCaptcha,
                            icon: const Icon(Icons.refresh),
                            tooltip: 'Yeni soru',
                          ),
                        ],
                      ),
                      if (error != null)
                        Padding(
                          padding: const EdgeInsets.only(top: 10),
                          child: Text(
                            error!,
                            style: const TextStyle(color: Colors.red),
                          ),
                        ),
                      const SizedBox(height: 18),
                      FilledButton.icon(
                        onPressed: busy ? null : login,
                        icon: busy
                            ? const SizedBox.square(
                                dimension: 18,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: navy,
                                ),
                              )
                            : const Icon(Icons.login),
                        label: const Text('Güvenli Giriş'),
                        style: goldButtonStyle,
                      ),
                      const SizedBox(height: 12),
                      const Text(
                        'Oturum güvenli olarak saklanır ve uzun süre işlem yapılmadığında otomatik kapanır.',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: muted, fontSize: 12),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    ),
  );
}

class MobileShell extends StatefulWidget {
  const MobileShell({
    super.key,
    required this.api,
    required this.session,
    required this.onSignOut,
  });
  final ApiClient api;
  final MobileSession session;
  final Future<void> Function({bool notifyServer}) onSignOut;

  @override
  State<MobileShell> createState() => _MobileShellState();
}

class _MobileShellState extends State<MobileShell> with WidgetsBindingObserver {
  int selected = 0;
  bool loading = true;
  Map<String, dynamic> bootstrap = {};
  DateTime lastActivity = DateTime.now();
  Timer? idleTimer;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    load();
    armIdleTimer();
  }

  Future<void> load() async {
    if (mounted) setState(() => loading = true);
    try {
      bootstrap = await widget.api.get('/bootstrap');
    } on ApiException catch (e) {
      if (e.statusCode == 401) {
        await widget.onSignOut(notifyServer: false);
        return;
      }
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  void touch() {
    lastActivity = DateTime.now();
    armIdleTimer();
  }

  void armIdleTimer() {
    idleTimer?.cancel();
    idleTimer = Timer(
      Duration(minutes: widget.session.idleMinutes),
      () => widget.onSignOut(notifyServer: true),
    );
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed &&
        DateTime.now().difference(lastActivity).inMinutes >=
            widget.session.idleMinutes) {
      widget.onSignOut(notifyServer: true);
    }
    if (state == AppLifecycleState.detached) {
      widget.onSignOut(notifyServer: true);
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    idleTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final root = mapOf(bootstrap['data']);
    final lookups = mapOf(root['lookups']);
    final dashboard = mapOf(root['dashboard']);
    final admin = widget.session.user['is_admin'] == true;
    final superAdmin = widget.session.user['is_super_admin'] == true;
    final pages = [
      DashboardPage(data: dashboard, onRefresh: load),
      TransactionPage(
        api: widget.api,
        categories: mapsOf(lookups['categories']),
        isAdmin: admin,
        onChanged: load,
      ),
      FuelPage(
        api: widget.api,
        vehicles: mapsOf(lookups['vehicles']),
        tankers: mapsOf(lookups['tankers']),
        isAdmin: admin,
        onChanged: load,
      ),
      MaintenancePage(
        api: widget.api,
        vehicles: mapsOf(lookups['vehicles']),
        isAdmin: admin,
        onChanged: load,
      ),
      MorePage(
        api: widget.api,
        session: widget.session,
        onChanged: load,
        onSignOut: widget.onSignOut,
        isSuperAdmin: superAdmin,
      ),
    ];
    return Listener(
      behavior: HitTestBehavior.translucent,
      onPointerDown: (_) => touch(),
      onPointerMove: (_) => touch(),
      child: Scaffold(
        appBar: AppBar(
          title: const BrandHeader(compact: true),
          actions: [
            IconButton(
              onPressed: load,
              icon: const Icon(Icons.refresh),
              tooltip: 'Yenile',
            ),
          ],
        ),
        body: loading
            ? const Center(child: CircularProgressIndicator())
            : IndexedStack(index: selected, children: pages),
        bottomNavigationBar: NavigationBar(
          selectedIndex: selected,
          onDestinationSelected: (value) {
            touch();
            setState(() => selected = value);
          },
          destinations: const [
            NavigationDestination(
              icon: Icon(Icons.space_dashboard_outlined),
              selectedIcon: Icon(Icons.space_dashboard),
              label: 'Genel',
            ),
            NavigationDestination(
              icon: Icon(Icons.receipt_long_outlined),
              selectedIcon: Icon(Icons.receipt_long),
              label: 'Kasa',
            ),
            NavigationDestination(
              icon: Icon(Icons.local_gas_station_outlined),
              selectedIcon: Icon(Icons.local_gas_station),
              label: 'Yakıt',
            ),
            NavigationDestination(
              icon: Icon(Icons.build_outlined),
              selectedIcon: Icon(Icons.build),
              label: 'Bakım',
            ),
            NavigationDestination(icon: Icon(Icons.more_horiz), label: 'Diğer'),
          ],
        ),
      ),
    );
  }
}

class DashboardPage extends StatelessWidget {
  const DashboardPage({super.key, required this.data, required this.onRefresh});
  final Map<String, dynamic> data;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    final metrics = mapOf(data['metrics']);
    final tankers = mapsOf(data['tankers']);
    final fuel = mapsOf(data['recent_fuel']);
    final alerts = mapsOf(data['maintenance_alerts']);
    final cards = [
      MetricCard(
        title: 'Toplam Kasa Bakiyesi',
        value: currency(numOf(metrics['cash_balance'])),
        icon: Icons.account_balance_wallet_outlined,
        color: const Color(0xff309a61),
      ),
      MetricCard(
        title: 'Bugünkü Harcama',
        value: currency(numOf(metrics['today_expense'])),
        icon: Icons.trending_down,
        color: const Color(0xff377fca),
      ),
      MetricCard(
        title: 'Toplam Yakıt Tüketimi',
        value: '${number(numOf(metrics['fuel_liters']), decimals: 0)} L',
        icon: Icons.local_gas_station_outlined,
        color: const Color(0xffef9d17),
      ),
      MetricCard(
        title: 'Aktif Araç & Makine',
        value: intOf(metrics['active_vehicle_count']).toString(),
        icon: Icons.precision_manufacturing_outlined,
        color: const Color(0xff8450ba),
      ),
    ];
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const PageTitle(
            'Genel Durum',
            'Şantiyenizin finansı, yakıtı ve filosu tek ekranda.',
          ),
          const SizedBox(height: 12),
          LayoutBuilder(
            builder: (context, constraints) => GridView.count(
              crossAxisCount: constraints.maxWidth >= 620 ? 4 : 2,
              childAspectRatio: constraints.maxWidth >= 620 ? 1.4 : 1.1,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 10,
              crossAxisSpacing: 10,
              children: cards,
            ),
          ),
          const SizedBox(height: 16),
          SectionCard(
            title: 'Tanker Stok Durumu',
            icon: Icons.local_shipping_outlined,
            child: tankers.isEmpty
                ? const EmptyState('Aktif tanker bulunmuyor.')
                : Column(
                    children: tankers
                        .map((t) => TankerLine(tanker: t))
                        .toList(),
                  ),
          ),
          const SizedBox(height: 14),
          SectionCard(
            title: 'Son Yakıt Dağıtımları',
            icon: Icons.local_gas_station_outlined,
            child: fuel.isEmpty
                ? const EmptyState('Henüz yakıt kaydı yok.')
                : Column(
                    children: fuel
                        .take(5)
                        .map(
                          (entry) => ListTile(
                            contentPadding: EdgeInsets.zero,
                            leading: const CircleAvatar(
                              backgroundColor: Color(0xfffff3d8),
                              child: Icon(
                                Icons.local_gas_station,
                                color: Color(0xffd98500),
                              ),
                            ),
                            title: Text(
                              stringOf(mapOf(entry['vehicle'])['display_name']),
                            ),
                            subtitle: Text(
                              '${stringOf(entry['fuel_date'])} · ${stringOf(mapOf(entry['tanker'])['name'])}',
                            ),
                            trailing: Text(
                              '${number(numOf(entry['liters']), decimals: 0)} L',
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                        )
                        .toList(),
                  ),
          ),
          const SizedBox(height: 14),
          SectionCard(
            title: 'Bakım Hatırlatmaları',
            icon: Icons.warning_amber_rounded,
            child: alerts.isEmpty
                ? const EmptyState('Yaklaşan veya geciken bakım bulunmuyor.')
                : Column(
                    children: alerts
                        .map(
                          (item) => ListTile(
                            contentPadding: EdgeInsets.zero,
                            leading: const Icon(
                              Icons.warning_amber_rounded,
                              color: Colors.orange,
                            ),
                            title: Text(
                              '${stringOf(mapOf(item['vehicle'])['display_name'])} · ${stringOf(item['maintenance_type'])}',
                            ),
                            subtitle: Text(listOf(item['reasons']).join(' · ')),
                          ),
                        )
                        .toList(),
                  ),
          ),
        ],
      ),
    );
  }
}

class TankerLine extends StatelessWidget {
  const TankerLine({super.key, required this.tanker});
  final Map<String, dynamic> tanker;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 7),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                stringOf(tanker['name']),
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
            Text(
              '${number(numOf(tanker['stock_liters']), decimals: 0)} L',
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
          ],
        ),
        const SizedBox(height: 6),
        LinearProgressIndicator(
          value: (numOf(tanker['stock_liters']) / 20000).clamp(0, 1),
          minHeight: 7,
          borderRadius: BorderRadius.circular(8),
          color: gold,
          backgroundColor: const Color(0xffe6eaf1),
        ),
      ],
    ),
  );
}

class TransactionPage extends StatefulWidget {
  const TransactionPage({
    super.key,
    required this.api,
    required this.categories,
    required this.isAdmin,
    required this.onChanged,
  });
  final ApiClient api;
  final List<Map<String, dynamic>> categories;
  final bool isAdmin;
  final Future<void> Function() onChanged;

  @override
  State<TransactionPage> createState() => _TransactionPageState();
}

class _TransactionPageState extends State<TransactionPage> {
  List<Map<String, dynamic>> items = [];
  bool loading = true;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final response = await widget.api.get('/transactions?per_page=40');
      if (mounted) {
        setState(() {
          items = mapsOf(response['data']);
          loading = false;
        });
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => loading = false);
        showMessage(context, e.message, error: true);
      }
    }
  }

  Future<void> remove(Map<String, dynamic> item) async {
    final reason = await reasonDialog(
      context,
      title: 'Kasa hareketini sil',
      hint: 'Silme gerekçesi',
    );
    if (reason == null) return;
    try {
      await widget.api.delete(
        '/transactions/${item['id']}',
        body: {'reason': reason},
      );
      await load();
      await widget.onChanged();
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    floatingActionButton: FloatingActionButton.extended(
      backgroundColor: gold,
      foregroundColor: navy,
      icon: const Icon(Icons.add),
      label: const Text('Yeni hareket'),
      onPressed: () async {
        final saved = await Navigator.of(context).push<bool>(
          MaterialPageRoute(
            builder: (_) => TransactionFormPage(
              api: widget.api,
              categories: widget.categories,
            ),
          ),
        );
        if (saved == true) {
          await load();
          await widget.onChanged();
        }
      },
    ),
    body: RefreshIndicator(
      onRefresh: load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const PageTitle(
            'Kasa Hareketleri',
            'Gelir ve giderleri güvenle kaydedin.',
          ),
          const SizedBox(height: 10),
          if (loading)
            const Center(
              child: Padding(
                padding: EdgeInsets.all(32),
                child: CircularProgressIndicator(),
              ),
            )
          else if (items.isEmpty)
            const EmptyState('Henüz kasa hareketi yok.')
          else
            ...items.map((item) {
              final income = stringOf(item['type']) == 'income';
              return Card(
                margin: const EdgeInsets.only(bottom: 10),
                child: ListTile(
                  leading: CircleAvatar(
                    backgroundColor: income
                        ? const Color(0xffe2f5e9)
                        : const Color(0xffffe8e8),
                    child: Icon(
                      income ? Icons.south_west : Icons.north_east,
                      color: income ? Colors.green : Colors.red,
                    ),
                  ),
                  title: Text(
                    stringOf(item['description']),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  subtitle: Text(
                    '${stringOf(item['occurred_on'])} · ${stringOf(item['category'])}',
                  ),
                  trailing: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        currency(numOf(item['amount'])),
                        style: TextStyle(
                          fontWeight: FontWeight.w800,
                          color: income ? Colors.green : Colors.red,
                        ),
                      ),
                      if (widget.isAdmin)
                        PopupMenuButton<String>(
                          onSelected: (value) {
                            if (value == 'delete') remove(item);
                          },
                          itemBuilder: (_) => const [
                            PopupMenuItem(value: 'delete', child: Text('Sil')),
                          ],
                        ),
                    ],
                  ),
                ),
              );
            }),
        ],
      ),
    ),
  );
}

class TransactionFormPage extends StatefulWidget {
  const TransactionFormPage({
    super.key,
    required this.api,
    required this.categories,
  });
  final ApiClient api;
  final List<Map<String, dynamic>> categories;

  @override
  State<TransactionFormPage> createState() => _TransactionFormPageState();
}

class _TransactionFormPageState extends State<TransactionFormPage> {
  final form = GlobalKey<FormState>();
  final description = TextEditingController();
  final amount = TextEditingController();
  String type = 'expense';
  String? category;
  DateTime date = DateTime.now();
  bool busy = false;

  List<Map<String, dynamic>> get categories => widget.categories
      .where((item) => stringOf(item['type']) == type)
      .toList();

  @override
  void initState() {
    super.initState();
    category = categories.isEmpty ? null : stringOf(categories.first['name']);
  }

  @override
  void dispose() {
    description.dispose();
    amount.dispose();
    super.dispose();
  }

  Future<void> save() async {
    if (!(form.currentState?.validate() ?? false)) return;
    setState(() => busy = true);
    try {
      await widget.api.post(
        '/transactions',
        body: {
          'type': type,
          'category': category,
          'description': description.text.trim(),
          'amount': optionalNumber(amount.text),
          'occurred_on': dateValue(date),
        },
      );
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Yeni Kasa Hareketi')),
    body: Form(
      key: form,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          SegmentedButton<String>(
            segments: const [
              ButtonSegment(
                value: 'income',
                label: Text('Gelir'),
                icon: Icon(Icons.south_west),
              ),
              ButtonSegment(
                value: 'expense',
                label: Text('Gider'),
                icon: Icon(Icons.north_east),
              ),
            ],
            selected: {type},
            onSelectionChanged: (value) => setState(() {
              type = value.first;
              category = categories.isEmpty
                  ? null
                  : stringOf(categories.first['name']);
            }),
          ),
          const SizedBox(height: 16),
          DropdownButtonFormField<String>(
            initialValue: category,
            decoration: const InputDecoration(labelText: 'Kategori'),
            items: categories
                .map(
                  (item) => DropdownMenuItem(
                    value: stringOf(item['name']),
                    child: Text(stringOf(item['name'])),
                  ),
                )
                .toList(),
            onChanged: (value) => setState(() => category = value),
            validator: (value) => value == null ? 'Kategori seçin' : null,
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: description,
            decoration: const InputDecoration(labelText: 'Açıklama'),
            validator: requiredText,
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: amount,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(labelText: 'Tutar (₺)'),
            validator: numberText,
          ),
          const SizedBox(height: 14),
          DatePickerField(
            value: date,
            onChanged: (value) => setState(() => date = value),
          ),
          const SizedBox(height: 26),
          FilledButton(
            onPressed: busy ? null : save,
            style: goldButtonStyle,
            child: Text(busy ? 'Kaydediliyor...' : 'Kaydet ve Kilitle'),
          ),
        ],
      ),
    ),
  );
}

class FuelPage extends StatefulWidget {
  const FuelPage({
    super.key,
    required this.api,
    required this.vehicles,
    required this.tankers,
    required this.isAdmin,
    required this.onChanged,
  });
  final ApiClient api;
  final List<Map<String, dynamic>> vehicles;
  final List<Map<String, dynamic>> tankers;
  final bool isAdmin;
  final Future<void> Function() onChanged;

  @override
  State<FuelPage> createState() => _FuelPageState();
}

class _FuelPageState extends State<FuelPage> {
  List<Map<String, dynamic>> items = [];
  bool loading = true;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final response = await widget.api.get('/fuel?per_page=40');
      if (mounted) {
        setState(() {
          items = mapsOf(response['data']);
          loading = false;
        });
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => loading = false);
        showMessage(context, e.message, error: true);
      }
    }
  }

  Future<void> remove(Map<String, dynamic> item) async {
    final reason = await reasonDialog(
      context,
      title: 'Yakıt kaydını sil',
      hint: 'Silme gerekçesi',
    );
    if (reason == null) return;
    try {
      await widget.api.delete('/fuel/${item['id']}', body: {'reason': reason});
      await load();
      await widget.onChanged();
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: gold,
        foregroundColor: navy,
        icon: const Icon(Icons.add),
        label: const Text('Yakıt kaydı'),
        onPressed: () async {
          final saved = await Navigator.of(context).push<bool>(
            MaterialPageRoute(
              builder: (_) => FuelFormPage(
                api: widget.api,
                vehicles: widget.vehicles,
                tankers: widget.tankers,
              ),
            ),
          );
          if (saved == true) {
            await load();
            await widget.onChanged();
          }
        },
      ),
      body: RefreshIndicator(
        onRefresh: load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            const PageTitle(
              'Yakıt Takibi',
              'Araç ve makinelere tankerlerden verilen yakıt.',
            ),
            const SizedBox(height: 10),
            if (loading)
              const Center(
                child: Padding(
                  padding: EdgeInsets.all(32),
                  child: CircularProgressIndicator(),
                ),
              )
            else if (items.isEmpty)
              const EmptyState('Henüz yakıt kaydı yok.')
            else
              ...items.map(
                (item) => Card(
                  margin: const EdgeInsets.only(bottom: 10),
                  child: ListTile(
                    leading: const CircleAvatar(
                      backgroundColor: Color(0xfffff3d8),
                      child: Icon(
                        Icons.local_gas_station,
                        color: Color(0xffd98500),
                      ),
                    ),
                    title: Text(
                      stringOf(mapOf(item['vehicle'])['display_name']),
                    ),
                    subtitle: Text(
                      '${stringOf(item['fuel_date'])} · ${stringOf(mapOf(item['tanker'])['name'])}',
                    ),
                    trailing: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            Text(
                              '${number(numOf(item['liters']), decimals: 0)} L',
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            Text(
                              currency(numOf(item['total_amount'])),
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                          ],
                        ),
                        if (widget.isAdmin)
                          PopupMenuButton<String>(
                            onSelected: (value) {
                              if (value == 'delete') remove(item);
                            },
                            itemBuilder: (_) => const [
                              PopupMenuItem(
                                value: 'delete',
                                child: Text('Sil'),
                              ),
                            ],
                          ),
                      ],
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class FuelFormPage extends StatefulWidget {
  const FuelFormPage({
    super.key,
    required this.api,
    required this.vehicles,
    required this.tankers,
  });
  final ApiClient api;
  final List<Map<String, dynamic>> vehicles;
  final List<Map<String, dynamic>> tankers;

  @override
  State<FuelFormPage> createState() => _FuelFormPageState();
}

class _FuelFormPageState extends State<FuelFormPage> {
  final form = GlobalKey<FormState>();
  final liters = TextEditingController();
  final station = TextEditingController();
  final notes = TextEditingController();
  final meter = TextEditingController();
  final hours = TextEditingController();
  int? vehicleId;
  int? tankerId;
  DateTime date = DateTime.now();
  bool busy = false;

  @override
  void initState() {
    super.initState();
    vehicleId = widget.vehicles.isEmpty
        ? null
        : intOf(widget.vehicles.first['id']);
    tankerId = widget.tankers.isEmpty
        ? null
        : intOf(widget.tankers.first['id']);
  }

  @override
  void dispose() {
    liters.dispose();
    station.dispose();
    notes.dispose();
    meter.dispose();
    hours.dispose();
    super.dispose();
  }

  Map<String, dynamic>? get vehicle =>
      firstMatching(widget.vehicles, (item) => intOf(item['id']) == vehicleId);

  Future<void> save() async {
    if (!(form.currentState?.validate() ?? false) ||
        vehicleId == null ||
        tankerId == null) {
      return;
    }
    setState(() => busy = true);
    try {
      await widget.api.post(
        '/fuel',
        body: {
          'vehicle_id': vehicleId,
          'tanker_id': tankerId,
          'fuel_date': dateValue(date),
          'liters': optionalNumber(liters.text),
          'station': nullIfEmpty(station.text),
          'notes': nullIfEmpty(notes.text),
          'meter_value': isTracking(vehicle)
              ? optionalNumber(meter.text)
              : null,
          'operating_hours': isTracking(vehicle)
              ? optionalNumber(hours.text)
              : null,
        },
      );
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final tracks = isTracking(vehicle);
    return Scaffold(
      appBar: AppBar(title: const Text('Yakıt Kaydı')),
      body: Form(
        key: form,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            DropdownButtonFormField<int>(
              initialValue: vehicleId,
              decoration: const InputDecoration(labelText: 'Araç veya makine'),
              items: widget.vehicles
                  .map(
                    (v) => DropdownMenuItem(
                      value: intOf(v['id']),
                      child: Text(stringOf(v['display_name'])),
                    ),
                  )
                  .toList(),
              onChanged: (value) => setState(() => vehicleId = value),
              validator: (value) => value == null ? 'Araç seçin' : null,
            ),
            const SizedBox(height: 14),
            DropdownButtonFormField<int>(
              initialValue: tankerId,
              decoration: const InputDecoration(
                labelText: 'Yakıtın verildiği tanker',
              ),
              items: widget.tankers
                  .map(
                    (t) => DropdownMenuItem(
                      value: intOf(t['id']),
                      child: Text(
                        '${stringOf(t['name'])} · ${number(numOf(t['stock_liters']), decimals: 0)} L',
                      ),
                    ),
                  )
                  .toList(),
              onChanged: (value) => setState(() => tankerId = value),
              validator: (value) => value == null ? 'Tanker seçin' : null,
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: liters,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              decoration: const InputDecoration(
                labelText: 'Tankerden verilen litre',
              ),
              validator: numberText,
            ),
            const SizedBox(height: 14),
            DatePickerField(
              value: date,
              onChanged: (value) => setState(() => date = value),
            ),
            if (tracks) ...[
              const SizedBox(height: 14),
              TextFormField(
                controller: meter,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: const InputDecoration(
                  labelText: 'Kilometre (isteğe bağlı)',
                ),
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: hours,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: const InputDecoration(
                  labelText: 'Çalışma saati (isteğe bağlı)',
                ),
              ),
            ],
            const SizedBox(height: 14),
            TextFormField(
              controller: station,
              decoration: const InputDecoration(
                labelText: 'Akaryakıt istasyonu (isteğe bağlı)',
              ),
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: notes,
              maxLines: 3,
              decoration: const InputDecoration(
                labelText: 'Yakıt notu (isteğe bağlı)',
              ),
            ),
            const SizedBox(height: 25),
            FilledButton(
              onPressed: busy ? null : save,
              style: goldButtonStyle,
              child: Text(busy ? 'Kaydediliyor...' : 'Yakıtı Kaydet'),
            ),
          ],
        ),
      ),
    );
  }
}

class MaintenancePage extends StatefulWidget {
  const MaintenancePage({
    super.key,
    required this.api,
    required this.vehicles,
    required this.isAdmin,
    required this.onChanged,
  });
  final ApiClient api;
  final List<Map<String, dynamic>> vehicles;
  final bool isAdmin;
  final Future<void> Function() onChanged;

  @override
  State<MaintenancePage> createState() => _MaintenancePageState();
}

class _MaintenancePageState extends State<MaintenancePage> {
  List<Map<String, dynamic>> items = [];
  bool loading = true;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final response = await widget.api.get('/maintenance?per_page=40');
      if (mounted) {
        setState(() {
          items = mapsOf(response['data']);
          loading = false;
        });
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => loading = false);
        showMessage(context, e.message, error: true);
      }
    }
  }

  Future<void> remove(Map<String, dynamic> item) async {
    final reason = await reasonDialog(
      context,
      title: 'Bakım kaydını sil',
      hint: 'Silme gerekçesi',
    );
    if (reason == null) return;
    try {
      await widget.api.delete(
        '/maintenance/${item['id']}',
        body: {'reason': reason},
      );
      await load();
      await widget.onChanged();
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: gold,
        foregroundColor: navy,
        onPressed: () async {
          final saved = await Navigator.of(context).push<bool>(
            MaterialPageRoute(
              builder: (_) => MaintenanceFormPage(
                api: widget.api,
                vehicles: widget.vehicles,
              ),
            ),
          );
          if (saved == true) {
            await load();
            await widget.onChanged();
          }
        },
        icon: const Icon(Icons.add),
        label: const Text('Yeni bakım'),
      ),
      body: RefreshIndicator(
        onRefresh: load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            const PageTitle(
              'Bakım / Onarım',
              'Bakım geçmişi, maliyetler ve hatırlatmalar.',
            ),
            const SizedBox(height: 10),
            if (loading)
              const Center(
                child: Padding(
                  padding: EdgeInsets.all(32),
                  child: CircularProgressIndicator(),
                ),
              )
            else if (items.isEmpty)
              const EmptyState('Henüz bakım kaydı yok.')
            else
              ...items.map(
                (item) => Card(
                  margin: const EdgeInsets.only(bottom: 10),
                  child: ListTile(
                    leading: const CircleAvatar(
                      backgroundColor: Color(0xfffff3d8),
                      child: Icon(Icons.build, color: Color(0xffd98500)),
                    ),
                    title: Text(stringOf(item['maintenance_type'])),
                    subtitle: Text(
                      '${stringOf(mapOf(item['vehicle'])['display_name'])}\n${stringOf(item['maintenance_date'])}',
                      maxLines: 2,
                    ),
                    isThreeLine: true,
                    trailing: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          currency(numOf(item['cost'])),
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                        if (widget.isAdmin)
                          PopupMenuButton<String>(
                            onSelected: (value) {
                              if (value == 'delete') remove(item);
                            },
                            itemBuilder: (_) => const [
                              PopupMenuItem(
                                value: 'delete',
                                child: Text('Sil'),
                              ),
                            ],
                          ),
                      ],
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class MaintenanceFormPage extends StatefulWidget {
  const MaintenanceFormPage({
    super.key,
    required this.api,
    required this.vehicles,
  });
  final ApiClient api;
  final List<Map<String, dynamic>> vehicles;

  @override
  State<MaintenanceFormPage> createState() => _MaintenanceFormPageState();
}

class _MaintenanceFormPageState extends State<MaintenanceFormPage> {
  final form = GlobalKey<FormState>();
  final type = TextEditingController();
  final provider = TextEditingController();
  final cost = TextEditingController();
  final description = TextEditingController();
  final meter = TextEditingController();
  final hours = TextEditingController();
  final nextMeter = TextEditingController();
  final nextHours = TextEditingController();
  int? vehicleId;
  DateTime date = DateTime.now();
  DateTime? nextDate;
  bool cashExpense = true;
  bool busy = false;

  @override
  void initState() {
    super.initState();
    vehicleId = widget.vehicles.isEmpty
        ? null
        : intOf(widget.vehicles.first['id']);
  }

  @override
  void dispose() {
    type.dispose();
    provider.dispose();
    cost.dispose();
    description.dispose();
    meter.dispose();
    hours.dispose();
    nextMeter.dispose();
    nextHours.dispose();
    super.dispose();
  }

  Map<String, dynamic>? get vehicle =>
      firstMatching(widget.vehicles, (item) => intOf(item['id']) == vehicleId);

  Future<void> save() async {
    if (!(form.currentState?.validate() ?? false) || vehicleId == null) return;
    setState(() => busy = true);
    try {
      await widget.api.post(
        '/maintenance',
        body: {
          'vehicle_id': vehicleId,
          'maintenance_date': dateValue(date),
          'maintenance_type': type.text.trim(),
          'service_provider': nullIfEmpty(provider.text),
          'cost': optionalNumber(cost.text),
          'meter_value': isTracking(vehicle)
              ? optionalNumber(meter.text)
              : null,
          'operating_hours': isTracking(vehicle)
              ? optionalNumber(hours.text)
              : null,
          'next_maintenance_date': nextDate == null
              ? null
              : dateValue(nextDate!),
          'next_meter_value': isTracking(vehicle)
              ? optionalNumber(nextMeter.text)
              : null,
          'next_operating_hours': isTracking(vehicle)
              ? optionalNumber(nextHours.text)
              : null,
          'description': description.text.trim(),
          'record_as_expense': cashExpense,
        },
      );
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final tracks = isTracking(vehicle);
    return Scaffold(
      appBar: AppBar(title: const Text('Yeni Bakım / Onarım')),
      body: Form(
        key: form,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            DropdownButtonFormField<int>(
              initialValue: vehicleId,
              decoration: const InputDecoration(labelText: 'Araç veya makine'),
              items: widget.vehicles
                  .map(
                    (v) => DropdownMenuItem(
                      value: intOf(v['id']),
                      child: Text(stringOf(v['display_name'])),
                    ),
                  )
                  .toList(),
              onChanged: (value) => setState(() => vehicleId = value),
              validator: (value) => value == null ? 'Araç seçin' : null,
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: type,
              decoration: const InputDecoration(
                labelText: 'İşlem türü (örn. Yağ / Filtre)',
              ),
              validator: requiredText,
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: provider,
              decoration: const InputDecoration(
                labelText: 'Servis sağlayıcı (isteğe bağlı)',
              ),
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: cost,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              decoration: const InputDecoration(labelText: 'Maliyet (₺)'),
              validator: numberText,
            ),
            const SizedBox(height: 14),
            DatePickerField(
              value: date,
              onChanged: (value) => setState(() => date = value),
            ),
            if (tracks) ...[
              const SizedBox(height: 14),
              TextFormField(
                controller: meter,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: const InputDecoration(
                  labelText: 'Bakım anı kilometre (isteğe bağlı)',
                ),
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: hours,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: const InputDecoration(
                  labelText: 'Bakım anı çalışma saati (isteğe bağlı)',
                ),
              ),
            ],
            const SizedBox(height: 14),
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Sonraki bakım tarihi'),
              subtitle: Text(
                nextDate == null
                    ? 'Belirtilmedi'
                    : DateFormat('dd.MM.yyyy').format(nextDate!),
              ),
              trailing: IconButton(
                onPressed: () async {
                  final value = await showDatePicker(
                    context: context,
                    firstDate: date,
                    lastDate: DateTime(2100),
                    initialDate: nextDate ?? date,
                    locale: const Locale('tr', 'TR'),
                  );
                  if (value != null) setState(() => nextDate = value);
                },
                icon: const Icon(Icons.calendar_today_outlined),
              ),
            ),
            if (tracks) ...[
              TextFormField(
                controller: nextMeter,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: const InputDecoration(
                  labelText: 'Sonraki bakım kilometresi (isteğe bağlı)',
                ),
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: nextHours,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: const InputDecoration(
                  labelText: 'Sonraki bakım çalışma saati (isteğe bağlı)',
                ),
              ),
            ],
            const SizedBox(height: 14),
            TextFormField(
              controller: description,
              maxLines: 3,
              decoration: const InputDecoration(labelText: 'Açıklama'),
              validator: requiredText,
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Kasaya gider olarak kaydet'),
              subtitle: const Text('Maliyet kasa bakiyesinden düşer.'),
              value: cashExpense,
              onChanged: (value) => setState(() => cashExpense = value),
            ),
            const SizedBox(height: 25),
            FilledButton(
              onPressed: busy ? null : save,
              style: goldButtonStyle,
              child: Text(busy ? 'Kaydediliyor...' : 'Bakımı Kaydet'),
            ),
          ],
        ),
      ),
    );
  }
}

class MorePage extends StatelessWidget {
  const MorePage({
    super.key,
    required this.api,
    required this.session,
    required this.onChanged,
    required this.onSignOut,
    required this.isSuperAdmin,
  });
  final ApiClient api;
  final MobileSession session;
  final Future<void> Function() onChanged;
  final Future<void> Function({bool notifyServer}) onSignOut;
  final bool isSuperAdmin;

  @override
  Widget build(BuildContext context) {
    final isAdmin = session.user['is_admin'] == true;
    final entries = <Widget>[
      SectionCard(
        title: 'Raporlar',
        icon: Icons.assessment_outlined,
        child: ListTile(
          contentPadding: EdgeInsets.zero,
          leading: const Icon(Icons.bar_chart),
          title: const Text('Kasa ve yakıt raporları'),
          subtitle: const Text('Dönemsel toplamlar ve tüketim verimliliği'),
          trailing: const Icon(Icons.chevron_right),
          onTap: () => Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => ReportsPage(api: api)),
          ),
        ),
      ),
      const SizedBox(height: 14),
      SectionCard(
        title: 'Bildirimler',
        icon: Icons.notifications_outlined,
        child: ListTile(
          contentPadding: EdgeInsets.zero,
          leading: const Icon(Icons.notifications),
          title: const Text('Bakım ve sistem bildirimleri'),
          trailing: const Icon(Icons.chevron_right),
          onTap: () => Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => NotificationsPage(api: api)),
          ),
        ),
      ),
    ];
    if (isAdmin) {
      entries.addAll([
        const SizedBox(height: 14),
        SectionCard(
          title: 'Yönetim',
          icon: Icons.admin_panel_settings_outlined,
          child: Column(
            children: [
              AdminLink(
                icon: Icons.local_shipping_outlined,
                title: 'Tanker stok yönetimi',
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => TankerPage(api: api, onChanged: onChanged),
                  ),
                ),
              ),
              AdminLink(
                icon: Icons.precision_manufacturing_outlined,
                title: 'Araç ve makineler',
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => VehiclePage(api: api, onChanged: onChanged),
                  ),
                ),
              ),
              AdminLink(
                icon: Icons.people_outline,
                title: 'Kullanıcı yönetimi',
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) =>
                        UsersPage(api: api, currentUser: session.user),
                  ),
                ),
              ),
              AdminLink(
                icon: Icons.history,
                title: 'İşlem geçmişi',
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => AuditPage(api: api)),
                ),
              ),
            ],
          ),
        ),
      ]);
    }
    if (isSuperAdmin) {
      entries.addAll([
        const SizedBox(height: 14),
        SectionCard(
          title: 'Sistem',
          icon: Icons.settings_outlined,
          child: AdminLink(
            icon: Icons.settings,
            title: 'Sistem ayarları, kategoriler ve yedek',
            onTap: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => SystemPage(api: api)),
            ),
          ),
        ),
      ]);
    }
    entries.addAll([
      const SizedBox(height: 14),
      SectionCard(
        title: 'Hesabım',
        icon: Icons.person_outline,
        child: Column(
          children: [
            AdminLink(
              icon: Icons.key_outlined,
              title: 'Parolamı değiştir',
              onTap: () => Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => PasswordPage(api: api)),
              ),
            ),
            AdminLink(
              icon: Icons.logout,
              title: 'Güvenli çıkış',
              color: Colors.red,
              onTap: () => onSignOut(notifyServer: true),
            ),
          ],
        ),
      ),
    ]);
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        PageTitle(
          'Diğer İşlemler',
          '${stringOf(session.user['name'])} · ${roleLabel(stringOf(session.user['role']))}',
        ),
        const SizedBox(height: 10),
        ...entries,
      ],
    );
  }
}

class AdminLink extends StatelessWidget {
  const AdminLink({
    super.key,
    required this.icon,
    required this.title,
    required this.onTap,
    this.color,
  });
  final IconData icon;
  final String title;
  final VoidCallback onTap;
  final Color? color;

  @override
  Widget build(BuildContext context) => ListTile(
    contentPadding: EdgeInsets.zero,
    leading: Icon(icon, color: color),
    title: Text(title, style: TextStyle(color: color)),
    trailing: const Icon(Icons.chevron_right),
    onTap: onTap,
  );
}

class TankerPage extends StatefulWidget {
  const TankerPage({super.key, required this.api, required this.onChanged});
  final ApiClient api;
  final Future<void> Function() onChanged;

  @override
  State<TankerPage> createState() => _TankerPageState();
}

class _TankerPageState extends State<TankerPage> {
  List<Map<String, dynamic>> items = [];
  bool loading = true;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final response = await widget.api.get('/tankers');
      if (mounted) {
        setState(() {
          items = mapsOf(response['data']);
          loading = false;
        });
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => loading = false);
        showMessage(context, e.message, error: true);
      }
    }
  }

  Future<void> add() async {
    final controller = TextEditingController();
    final name = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Yeni Tanker'),
        content: TextField(
          controller: controller,
          decoration: const InputDecoration(labelText: 'Tanker adı'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: const Text('Vazgeç'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, controller.text),
            style: goldButtonStyle,
            child: const Text('Ekle'),
          ),
        ],
      ),
    );
    controller.dispose();
    if (name == null || name.trim().isEmpty) return;
    try {
      await widget.api.post('/tankers', body: {'name': name.trim()});
      await load();
      await widget.onChanged();
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    }
  }

  Future<void> purchase() async {
    final saved = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => TankerPurchasePage(api: widget.api, tankers: items),
      ),
    );
    if (saved == true) {
      await load();
      await widget.onChanged();
    }
  }

  Future<void> remove(Map<String, dynamic> item) async {
    try {
      await widget.api.delete('/tankers/${item['id']}');
      await load();
      await widget.onChanged();
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Tanker Stokları')),
      floatingActionButton: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          FloatingActionButton.extended(
            heroTag: 'add-tanker',
            backgroundColor: Colors.white,
            foregroundColor: navy,
            onPressed: add,
            icon: const Icon(Icons.add),
            label: const Text('Tanker ekle'),
          ),
          const SizedBox(height: 10),
          FloatingActionButton.extended(
            heroTag: 'purchase-tanker',
            backgroundColor: gold,
            foregroundColor: navy,
            onPressed: purchase,
            icon: const Icon(Icons.add_shopping_cart),
            label: const Text('Yakıt alımı'),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            const PageTitle(
              'Tanker Stok Yönetimi',
              'Tanker alımları kasadan düşer; araç dağıtımları stoktan düşer.',
            ),
            const SizedBox(height: 12),
            if (loading)
              const Center(
                child: Padding(
                  padding: EdgeInsets.all(32),
                  child: CircularProgressIndicator(),
                ),
              )
            else if (items.isEmpty)
              const EmptyState('Tanker bulunmuyor.')
            else
              ...items.map(
                (t) => Card(
                  margin: const EdgeInsets.only(bottom: 10),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                stringOf(t['name']),
                                style: Theme.of(context).textTheme.titleMedium
                                    ?.copyWith(fontWeight: FontWeight.w800),
                              ),
                            ),
                            PopupMenuButton<String>(
                              onSelected: (value) {
                                if (value == 'delete') remove(t);
                              },
                              itemBuilder: (_) => const [
                                PopupMenuItem(
                                  value: 'delete',
                                  child: Text('Sil'),
                                ),
                              ],
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Text(
                          '${number(numOf(t['stock_liters']), decimals: 0)} L mevcut stok',
                          style: const TextStyle(
                            fontSize: 22,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        Text(
                          'Son alış fiyatı: ${currency(numOf(t['last_unit_cost']))}/L',
                        ),
                        const SizedBox(height: 8),
                        LinearProgressIndicator(
                          value: (numOf(t['stock_liters']) / 20000).clamp(0, 1),
                          color: gold,
                          backgroundColor: const Color(0xffe6eaf1),
                          borderRadius: BorderRadius.circular(8),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class TankerPurchasePage extends StatefulWidget {
  const TankerPurchasePage({
    super.key,
    required this.api,
    required this.tankers,
  });
  final ApiClient api;
  final List<Map<String, dynamic>> tankers;

  @override
  State<TankerPurchasePage> createState() => _TankerPurchasePageState();
}

class _TankerPurchasePageState extends State<TankerPurchasePage> {
  final form = GlobalKey<FormState>();
  final liters = TextEditingController();
  final price = TextEditingController();
  final supplier = TextEditingController();
  int? tankerId;
  DateTime date = DateTime.now();
  bool busy = false;

  @override
  void initState() {
    super.initState();
    tankerId = widget.tankers.isEmpty
        ? null
        : intOf(widget.tankers.first['id']);
  }

  @override
  void dispose() {
    liters.dispose();
    price.dispose();
    supplier.dispose();
    super.dispose();
  }

  Future<void> save() async {
    if (!(form.currentState?.validate() ?? false) || tankerId == null) return;
    setState(() => busy = true);
    try {
      await widget.api.post(
        '/tanker-purchases',
        body: {
          'tanker_id': tankerId,
          'movement_date': dateValue(date),
          'liters': optionalNumber(liters.text),
          'unit_cost': optionalNumber(price.text),
          'supplier': nullIfEmpty(supplier.text),
        },
      );
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Tankere Yakıt Alımı')),
      body: Form(
        key: form,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            DropdownButtonFormField<int>(
              initialValue: tankerId,
              decoration: const InputDecoration(labelText: 'Tanker'),
              items: widget.tankers
                  .map(
                    (t) => DropdownMenuItem(
                      value: intOf(t['id']),
                      child: Text(stringOf(t['name'])),
                    ),
                  )
                  .toList(),
              onChanged: (value) => setState(() => tankerId = value),
              validator: (value) => value == null ? 'Tanker seçin' : null,
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: liters,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              decoration: const InputDecoration(labelText: 'Alınan litre'),
              validator: numberText,
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: price,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              decoration: const InputDecoration(labelText: 'Birim fiyat (₺/L)'),
              validator: numberText,
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: supplier,
              decoration: const InputDecoration(
                labelText: 'Tedarikçi (isteğe bağlı)',
              ),
            ),
            const SizedBox(height: 14),
            DatePickerField(
              value: date,
              onChanged: (value) => setState(() => date = value),
            ),
            const SizedBox(height: 25),
            FilledButton(
              onPressed: busy ? null : save,
              style: goldButtonStyle,
              child: Text(busy ? 'Kaydediliyor...' : 'Alımı Kaydet'),
            ),
          ],
        ),
      ),
    );
  }
}

class VehiclePage extends StatefulWidget {
  const VehiclePage({super.key, required this.api, required this.onChanged});
  final ApiClient api;
  final Future<void> Function() onChanged;

  @override
  State<VehiclePage> createState() => _VehiclePageState();
}

class _VehiclePageState extends State<VehiclePage> {
  List<Map<String, dynamic>> items = [];
  bool loading = true;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final response = await widget.api.get('/vehicles');
      if (mounted) {
        setState(() {
          items = mapsOf(response['data']);
          loading = false;
        });
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => loading = false);
        showMessage(context, e.message, error: true);
      }
    }
  }

  Future<void> add() async {
    final saved = await Navigator.push<bool>(
      context,
      MaterialPageRoute(builder: (_) => VehicleFormPage(api: widget.api)),
    );
    if (saved == true) {
      await load();
      await widget.onChanged();
    }
  }

  Future<void> remove(Map<String, dynamic> item) async {
    try {
      await widget.api.delete('/vehicles/${item['id']}');
      await load();
      await widget.onChanged();
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Araç ve Makineler')),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: gold,
        foregroundColor: navy,
        onPressed: add,
        icon: const Icon(Icons.add),
        label: const Text('Yeni tanım'),
      ),
      body: RefreshIndicator(
        onRefresh: load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            const PageTitle(
              'Araç ve Makineler',
              'Filo tanımları ve sayaç takibi.',
            ),
            const SizedBox(height: 10),
            if (loading)
              const Center(
                child: Padding(
                  padding: EdgeInsets.all(32),
                  child: CircularProgressIndicator(),
                ),
              )
            else
              ...items.map(
                (v) => Card(
                  margin: const EdgeInsets.only(bottom: 10),
                  child: ListTile(
                    leading: CircleAvatar(
                      child: Icon(
                        stringOf(v['type']) == 'vehicle'
                            ? Icons.local_shipping_outlined
                            : Icons.precision_manufacturing_outlined,
                      ),
                    ),
                    title: Text(stringOf(v['display_name'])),
                    subtitle: Text(
                      isTracking(v)
                          ? 'KM ve çalışma saati takipli'
                          : 'Sayaç takibi kapalı',
                    ),
                    trailing: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Chip(
                          label: Text(
                            v['is_active'] == true ? 'Aktif' : 'Pasif',
                          ),
                        ),
                        PopupMenuButton<String>(
                          onSelected: (value) {
                            if (value == 'delete') remove(v);
                          },
                          itemBuilder: (_) => const [
                            PopupMenuItem(value: 'delete', child: Text('Sil')),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class VehicleFormPage extends StatefulWidget {
  const VehicleFormPage({super.key, required this.api});
  final ApiClient api;

  @override
  State<VehicleFormPage> createState() => _VehicleFormPageState();
}

class _VehicleFormPageState extends State<VehicleFormPage> {
  final form = GlobalKey<FormState>();
  final name = TextEditingController();
  final plate = TextEditingController();
  final code = TextEditingController();
  String type = 'vehicle';
  bool tracks = true;
  bool busy = false;

  @override
  void dispose() {
    name.dispose();
    plate.dispose();
    code.dispose();
    super.dispose();
  }

  Future<void> save() async {
    if (!(form.currentState?.validate() ?? false)) return;
    setState(() => busy = true);
    try {
      await widget.api.post(
        '/vehicles',
        body: {
          'type': type,
          'name': name.text.trim(),
          'plate': type == 'vehicle' ? plate.text.trim() : null,
          'code': type == 'machine' ? code.text.trim() : null,
          'tracks_meters': tracks,
          'is_active': true,
        },
      );
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Yeni Araç / Makine')),
      body: Form(
        key: form,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            SegmentedButton<String>(
              segments: const [
                ButtonSegment(value: 'vehicle', label: Text('Araç')),
                ButtonSegment(value: 'machine', label: Text('Makine')),
              ],
              selected: {type},
              onSelectionChanged: (value) => setState(() => type = value.first),
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: name,
              decoration: const InputDecoration(labelText: 'Adı'),
              validator: requiredText,
            ),
            const SizedBox(height: 14),
            if (type == 'vehicle')
              TextFormField(
                controller: plate,
                decoration: const InputDecoration(labelText: 'Plaka'),
                validator: requiredText,
              )
            else
              TextFormField(
                controller: code,
                decoration: const InputDecoration(labelText: 'Makine kodu'),
                validator: requiredText,
              ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('KM ve çalışma saati takibi'),
              value: tracks,
              onChanged: (value) => setState(() => tracks = value),
            ),
            const SizedBox(height: 20),
            FilledButton(
              onPressed: busy ? null : save,
              style: goldButtonStyle,
              child: Text(busy ? 'Kaydediliyor...' : 'Tanımı Kaydet'),
            ),
          ],
        ),
      ),
    );
  }
}

class ReportsPage extends StatefulWidget {
  const ReportsPage({super.key, required this.api});
  final ApiClient api;

  @override
  State<ReportsPage> createState() => _ReportsPageState();
}

class _ReportsPageState extends State<ReportsPage> {
  Map<String, dynamic>? data;
  DateTime? from;
  DateTime? to;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final query = <String, String>{
        if (from != null) 'from': dateValue(from!),
        if (to != null) 'to': dateValue(to!),
      };
      final path = query.isEmpty
          ? '/reports'
          : '/reports?${query.entries.map((e) => '${e.key}=${Uri.encodeQueryComponent(e.value)}').join('&')}';
      final response = await widget.api.get(path);
      if (mounted) setState(() => data = mapOf(response['data']));
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final cash = mapOf(data?['cash']);
    final fuel = mapOf(data?['fuel']);
    return Scaffold(
      appBar: AppBar(title: const Text('Raporlar')),
      body: RefreshIndicator(
        onRefresh: load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            const PageTitle(
              'Kasa ve Yakıt Raporları',
              'Dönem seçin; kasa, yakıt ve verimlilik özetini görün.',
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: DateFilterField(
                    label: 'Başlangıç',
                    value: from,
                    onChanged: (value) => setState(() => from = value),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: DateFilterField(
                    label: 'Bitiş',
                    value: to,
                    onChanged: (value) => setState(() => to = value),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            FilledButton.icon(
              onPressed: load,
              icon: const Icon(Icons.filter_alt_outlined),
              label: const Text('Raporu yenile'),
              style: goldButtonStyle,
            ),
            const SizedBox(height: 14),
            if (data == null)
              const Center(
                child: Padding(
                  padding: EdgeInsets.all(32),
                  child: CircularProgressIndicator(),
                ),
              )
            else ...[
              SectionCard(
                title: 'Kasa Özeti',
                icon: Icons.account_balance_wallet_outlined,
                child: Column(
                  children: [
                    _summaryRow('Gelir', currency(numOf(cash['income']))),
                    _summaryRow('Gider', currency(numOf(cash['expense']))),
                    _summaryRow(
                      'Net',
                      currency(numOf(cash['net'])),
                      bold: true,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              SectionCard(
                title: 'Yakıt Özeti',
                icon: Icons.local_gas_station_outlined,
                child: Column(
                  children: [
                    _summaryRow(
                      'Toplam litre',
                      '${number(numOf(fuel['liters']), decimals: 0)} L',
                    ),
                    _summaryRow(
                      'Araçlara maliyet',
                      currency(numOf(fuel['amount'])),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              SectionCard(
                title: 'Araç tüketim verimliliği',
                icon: Icons.speed_outlined,
                child: _efficiency(fuel['efficiency']),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _efficiency(dynamic value) {
    final rows = mapsOf(value);
    if (rows.isEmpty) return const EmptyState('Sayaç ve yakıt verisi yok.');
    return Column(
      children: rows.map((row) {
        final vehicle = mapOf(row['vehicle']);
        return ListTile(
          contentPadding: EdgeInsets.zero,
          title: Text(stringOf(vehicle['display_name'])),
          subtitle: Text(
            'Litre: ${number(numOf(row['liters']), decimals: 1)} · KM başı: ${number(numOf(row['liters_per_km']), decimals: 3)}',
          ),
          trailing: Text(
            '${number(numOf(row['liters_per_hour']), decimals: 2)} L/saat',
          ),
        );
      }).toList(),
    );
  }

  Widget _summaryRow(String label, String value, {bool bold = false}) =>
      Padding(
        padding: const EdgeInsets.symmetric(vertical: 8),
        child: Row(
          children: [
            Expanded(
              child: Text(
                label,
                style: TextStyle(fontWeight: bold ? FontWeight.w800 : null),
              ),
            ),
            Text(
              value,
              style: TextStyle(fontWeight: bold ? FontWeight.w800 : null),
            ),
          ],
        ),
      );
}

class NotificationsPage extends StatefulWidget {
  const NotificationsPage({super.key, required this.api});
  final ApiClient api;

  @override
  State<NotificationsPage> createState() => _NotificationsPageState();
}

class _NotificationsPageState extends State<NotificationsPage> {
  List<Map<String, dynamic>> items = [];
  bool loading = true;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final response = await widget.api.get('/notifications?per_page=50');
      if (mounted) {
        setState(() {
          items = mapsOf(response['data']);
          loading = false;
        });
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => loading = false);
        showMessage(context, e.message, error: true);
      }
    }
  }

  Future<void> readAll() async {
    try {
      await widget.api.post('/notifications/read-all');
      await load();
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Bildirimler'),
        actions: [
          TextButton(onPressed: readAll, child: const Text('Tümünü oku')),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (loading)
              const Center(
                child: Padding(
                  padding: EdgeInsets.all(32),
                  child: CircularProgressIndicator(),
                ),
              )
            else if (items.isEmpty)
              const EmptyState('Bildirim bulunmuyor.')
            else
              ...items.map(
                (item) => Card(
                  margin: const EdgeInsets.only(bottom: 10),
                  child: ListTile(
                    leading: Icon(
                      item['read_at'] == null
                          ? Icons.notifications_active
                          : Icons.notifications_none,
                      color: item['read_at'] == null ? gold : muted,
                    ),
                    title: Text(stringOf(item['title'])),
                    subtitle: Text(stringOf(item['message'])),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class PasswordPage extends StatefulWidget {
  const PasswordPage({super.key, required this.api});
  final ApiClient api;

  @override
  State<PasswordPage> createState() => _PasswordPageState();
}

class _PasswordPageState extends State<PasswordPage> {
  final form = GlobalKey<FormState>();
  final current = TextEditingController();
  final password = TextEditingController();
  final confirm = TextEditingController();
  bool busy = false;

  @override
  void dispose() {
    current.dispose();
    password.dispose();
    confirm.dispose();
    super.dispose();
  }

  Future<void> save() async {
    if (!(form.currentState?.validate() ?? false)) return;
    setState(() => busy = true);
    try {
      await widget.api.put(
        '/password',
        body: {
          'current_password': current.text,
          'password': password.text,
          'password_confirmation': confirm.text,
        },
      );
      if (mounted) {
        showMessage(context, 'Parolanız değiştirildi.');
        Navigator.pop(context);
      }
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Parolamı Değiştir')),
      body: Form(
        key: form,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            TextFormField(
              controller: current,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Mevcut parola'),
              validator: requiredText,
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: password,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Yeni parola'),
              validator: (value) =>
                  (value ?? '').length < 10 ? 'En az 10 karakter girin' : null,
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: confirm,
              obscureText: true,
              decoration: const InputDecoration(
                labelText: 'Yeni parola tekrarı',
              ),
              validator: (value) =>
                  value != password.text ? 'Parolalar eşleşmiyor' : null,
            ),
            const SizedBox(height: 24),
            FilledButton(
              onPressed: busy ? null : save,
              style: goldButtonStyle,
              child: Text(busy ? 'Kaydediliyor...' : 'Parolayı Değiştir'),
            ),
          ],
        ),
      ),
    );
  }
}

class UsersPage extends StatefulWidget {
  const UsersPage({super.key, required this.api, required this.currentUser});
  final ApiClient api;
  final Map<String, dynamic> currentUser;

  @override
  State<UsersPage> createState() => _UsersPageState();
}

class _UsersPageState extends State<UsersPage> {
  List<Map<String, dynamic>> items = [];
  bool loading = true;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final response = await widget.api.get('/users');
      if (mounted) {
        setState(() {
          items = mapsOf(response['data']);
          loading = false;
        });
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => loading = false);
        showMessage(context, e.message, error: true);
      }
    }
  }

  Future<void> add() async {
    final saved = await Navigator.push<bool>(
      context,
      MaterialPageRoute(builder: (_) => UserFormPage(api: widget.api)),
    );
    if (saved == true) await load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Kullanıcı Yönetimi')),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: gold,
        foregroundColor: navy,
        onPressed: add,
        icon: const Icon(Icons.person_add),
        label: const Text('Yeni kullanıcı'),
      ),
      body: RefreshIndicator(
        onRefresh: load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            const PageTitle(
              'Kullanıcılar',
              'Yetki ve aktiflik durumlarını yönetin.',
            ),
            const SizedBox(height: 10),
            if (loading)
              const Center(
                child: Padding(
                  padding: EdgeInsets.all(32),
                  child: CircularProgressIndicator(),
                ),
              )
            else
              ...items.map(
                (user) => Card(
                  margin: const EdgeInsets.only(bottom: 10),
                  child: ListTile(
                    leading: CircleAvatar(
                      child: Text(
                        stringOf(user['name']).isEmpty
                            ? '?'
                            : stringOf(user['name'])[0].toUpperCase(),
                      ),
                    ),
                    title: Text(stringOf(user['name'])),
                    subtitle: Text(
                      '@${stringOf(user['username'])} · ${roleLabel(stringOf(user['role']))}',
                    ),
                    trailing: Chip(
                      label: Text(
                        user['is_active'] == true ? 'Aktif' : 'Pasif',
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class UserFormPage extends StatefulWidget {
  const UserFormPage({super.key, required this.api});
  final ApiClient api;

  @override
  State<UserFormPage> createState() => _UserFormPageState();
}

class _UserFormPageState extends State<UserFormPage> {
  final form = GlobalKey<FormState>();
  final name = TextEditingController();
  final username = TextEditingController();
  final password = TextEditingController();
  final confirm = TextEditingController();
  String role = 'personnel';
  bool active = true;
  bool busy = false;

  @override
  void dispose() {
    name.dispose();
    username.dispose();
    password.dispose();
    confirm.dispose();
    super.dispose();
  }

  Future<void> save() async {
    if (!(form.currentState?.validate() ?? false)) return;
    setState(() => busy = true);
    try {
      await widget.api.post(
        '/users',
        body: {
          'name': name.text.trim(),
          'username': username.text.trim(),
          'role': role,
          'is_active': active,
          'password': password.text,
          'password_confirmation': confirm.text,
        },
      );
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Yeni Kullanıcı')),
      body: Form(
        key: form,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            TextFormField(
              controller: name,
              decoration: const InputDecoration(labelText: 'Ad soyad'),
              validator: requiredText,
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: username,
              decoration: const InputDecoration(labelText: 'Kullanıcı adı'),
              validator: requiredText,
            ),
            const SizedBox(height: 14),
            DropdownButtonFormField<String>(
              initialValue: role,
              decoration: const InputDecoration(labelText: 'Rol'),
              items: const [
                DropdownMenuItem(value: 'personnel', child: Text('Personel')),
                DropdownMenuItem(value: 'admin', child: Text('Yönetici')),
                DropdownMenuItem(
                  value: 'super_admin',
                  child: Text('Sistem yöneticisi'),
                ),
              ],
              onChanged: (value) => setState(() => role = value ?? role),
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: password,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Geçici parola'),
              validator: (value) =>
                  (value ?? '').length < 10 ? 'En az 10 karakter girin' : null,
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: confirm,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Parola tekrarı'),
              validator: (value) =>
                  value != password.text ? 'Parolalar eşleşmiyor' : null,
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Aktif kullanıcı'),
              value: active,
              onChanged: (value) => setState(() => active = value),
            ),
            const SizedBox(height: 20),
            FilledButton(
              onPressed: busy ? null : save,
              style: goldButtonStyle,
              child: Text(busy ? 'Kaydediliyor...' : 'Kullanıcıyı Kaydet'),
            ),
          ],
        ),
      ),
    );
  }
}

class AuditPage extends StatefulWidget {
  const AuditPage({super.key, required this.api});
  final ApiClient api;

  @override
  State<AuditPage> createState() => _AuditPageState();
}

class _AuditPageState extends State<AuditPage> {
  List<Map<String, dynamic>> items = [];
  bool loading = true;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final response = await widget.api.get('/audit?per_page=60');
      if (mounted) {
        setState(() {
          items = mapsOf(response['data']);
          loading = false;
        });
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => loading = false);
        showMessage(context, e.message, error: true);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('İşlem Geçmişi')),
      body: RefreshIndicator(
        onRefresh: load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (loading)
              const Center(
                child: Padding(
                  padding: EdgeInsets.all(32),
                  child: CircularProgressIndicator(),
                ),
              )
            else if (items.isEmpty)
              const EmptyState('İşlem geçmişi boş.')
            else
              ...items.map(
                (item) => Card(
                  margin: const EdgeInsets.only(bottom: 10),
                  child: ListTile(
                    leading: const Icon(Icons.history, color: muted),
                    title: Text(stringOf(item['reason'])),
                    subtitle: Text(
                      '${stringOf(item['event'])} · ${stringOf(item['user'])}\n${stringOf(item['created_at'])}',
                    ),
                    isThreeLine: true,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class SystemPage extends StatefulWidget {
  const SystemPage({super.key, required this.api});
  final ApiClient api;

  @override
  State<SystemPage> createState() => _SystemPageState();
}

class _SystemPageState extends State<SystemPage> {
  Map<String, dynamic> data = {};
  final softwareName = TextEditingController();
  final tagline = TextEditingController();
  final company = TextEditingController();
  final category = TextEditingController();
  String categoryType = 'expense';
  bool loading = true;

  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    softwareName.dispose();
    tagline.dispose();
    company.dispose();
    category.dispose();
    super.dispose();
  }

  Future<void> load() async {
    try {
      final response = await widget.api.get('/system');
      final value = mapOf(response['data']);
      final settings = mapOf(value['settings']);
      if (mounted) {
        setState(() {
          data = value;
          softwareName.text = stringOf(settings['software_name']);
          tagline.text = stringOf(settings['software_tagline']);
          company.text = stringOf(settings['company_name']);
          loading = false;
        });
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => loading = false);
        showMessage(context, e.message, error: true);
      }
    }
  }

  Future<void> saveSettings() async {
    try {
      await widget.api.put(
        '/system/settings',
        body: {
          'software_name': softwareName.text.trim(),
          'software_tagline': tagline.text.trim(),
          'company_name': company.text.trim(),
        },
      );
      if (mounted) showMessage(context, 'Sistem ayarları güncellendi.');
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    }
  }

  Future<void> addCategory() async {
    if (category.text.trim().isEmpty) return;
    try {
      await widget.api.post(
        '/system/categories',
        body: {'type': categoryType, 'name': category.text.trim()},
      );
      category.clear();
      await load();
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    }
  }

  Future<void> backup() async {
    try {
      final response = await widget.api.post('/system/backups');
      if (mounted) showMessage(context, stringOf(response['message']));
      await load();
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final categories = mapsOf(data['categories']);
    final backups = mapsOf(data['backups']);
    return Scaffold(
      appBar: AppBar(title: const Text('Sistem Yönetimi')),
      body: RefreshIndicator(
        onRefresh: load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (loading)
              const Center(
                child: Padding(
                  padding: EdgeInsets.all(32),
                  child: CircularProgressIndicator(),
                ),
              )
            else ...[
              SectionCard(
                title: 'Genel ayarlar',
                icon: Icons.settings_outlined,
                child: Column(
                  children: [
                    TextField(
                      controller: softwareName,
                      decoration: const InputDecoration(
                        labelText: 'Yazılım adı',
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: tagline,
                      decoration: const InputDecoration(
                        labelText: 'Alt başlık',
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: company,
                      decoration: const InputDecoration(
                        labelText: 'Şirket adı',
                      ),
                    ),
                    const SizedBox(height: 12),
                    FilledButton(
                      onPressed: saveSettings,
                      style: goldButtonStyle,
                      child: const Text('Ayarları kaydet'),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              SectionCard(
                title: 'Kategori ekle',
                icon: Icons.category_outlined,
                child: Column(
                  children: [
                    DropdownButtonFormField<String>(
                      initialValue: categoryType,
                      decoration: const InputDecoration(labelText: 'Tür'),
                      items: const [
                        DropdownMenuItem(value: 'income', child: Text('Gelir')),
                        DropdownMenuItem(
                          value: 'expense',
                          child: Text('Gider'),
                        ),
                      ],
                      onChanged: (value) =>
                          setState(() => categoryType = value ?? categoryType),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: category,
                      decoration: const InputDecoration(
                        labelText: 'Kategori adı',
                      ),
                    ),
                    const SizedBox(height: 12),
                    FilledButton(
                      onPressed: addCategory,
                      style: goldButtonStyle,
                      child: const Text('Kategori ekle'),
                    ),
                    const Divider(height: 24),
                    ...categories.map(
                      (item) => ListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text(stringOf(item['name'])),
                        subtitle: Text(stringOf(item['type'])),
                        trailing: Text(
                          item['is_active'] == true ? 'Aktif' : 'Pasif',
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              SectionCard(
                title: 'Yedekleme',
                icon: Icons.backup_outlined,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    FilledButton.icon(
                      onPressed: backup,
                      style: goldButtonStyle,
                      icon: const Icon(Icons.backup),
                      label: const Text('Şimdi yedek al'),
                    ),
                    ...backups.map(
                      (item) => ListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text(stringOf(item['filename'])),
                        subtitle: Text('${stringOf(item['size'])} bayt'),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class BrandHeader extends StatelessWidget {
  const BrandHeader({super.key, this.centered = false, this.compact = false});
  final bool centered;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final mark = Container(
      width: compact ? 34 : 60,
      height: compact ? 34 : 60,
      decoration: BoxDecoration(
        color: gold,
        borderRadius: BorderRadius.circular(compact ? 10 : 18),
      ),
      child: Icon(Icons.route_rounded, color: navy, size: compact ? 22 : 38),
    );
    final words = Column(
      crossAxisAlignment: centered
          ? CrossAxisAlignment.center
          : CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          'Şantiye Takip',
          style: TextStyle(
            fontSize: compact ? 20 : 28,
            fontWeight: FontWeight.w900,
            color: navy,
          ),
        ),
        if (!compact)
          const Text(
            'Kasa, Yakıt ve Filo Yönetimi',
            style: TextStyle(color: muted),
          ),
      ],
    );
    return centered
        ? Column(children: [mark, const SizedBox(height: 12), words])
        : Row(
            mainAxisSize: MainAxisSize.min,
            children: [mark, const SizedBox(width: 10), words],
          );
  }
}

class PageTitle extends StatelessWidget {
  const PageTitle(this.title, this.subtitle, {super.key});
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        title,
        style: Theme.of(context).textTheme.headlineSmall?.copyWith(
          fontWeight: FontWeight.w900,
          color: navy,
        ),
      ),
      const SizedBox(height: 4),
      Text(
        subtitle,
        style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: muted),
      ),
    ],
  );
}

class MetricCard extends StatelessWidget {
  const MetricCard({
    super.key,
    required this.title,
    required this.value,
    required this.icon,
    required this.color,
  });
  final String title;
  final String value;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) => Card(
    clipBehavior: Clip.antiAlias,
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 14,
                backgroundColor: color.withValues(alpha: .12),
                child: Icon(icon, size: 16, color: color),
              ),
              const SizedBox(width: 7),
              Expanded(
                child: Text(
                  title.toUpperCase(),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 11,
                    color: color,
                  ),
                ),
              ),
            ],
          ),
          const Spacer(),
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontSize: 21,
              fontWeight: FontWeight.w900,
              color: navy,
            ),
          ),
          const SizedBox(height: 5),
          Container(
            height: 4,
            decoration: BoxDecoration(
              color: color,
              borderRadius: BorderRadius.circular(5),
            ),
          ),
        ],
      ),
    ),
  );
}

class SectionCard extends StatelessWidget {
  const SectionCard({
    super.key,
    required this.title,
    required this.icon,
    required this.child,
  });
  final String title;
  final IconData icon;
  final Widget child;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: gold),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  title,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const Divider(height: 24),
          child,
        ],
      ),
    ),
  );
}

class EmptyState extends StatelessWidget {
  const EmptyState(this.text, {super.key});
  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.all(24),
    child: Center(
      child: Text(
        text,
        textAlign: TextAlign.center,
        style: const TextStyle(color: muted),
      ),
    ),
  );
}

class DatePickerField extends StatelessWidget {
  const DatePickerField({
    super.key,
    required this.value,
    required this.onChanged,
  });
  final DateTime value;
  final ValueChanged<DateTime> onChanged;

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: () async {
      final selected = await showDatePicker(
        context: context,
        firstDate: DateTime(2020),
        lastDate: DateTime(2100),
        initialDate: value,
        locale: const Locale('tr', 'TR'),
      );
      if (selected != null) onChanged(selected);
    },
    child: InputDecorator(
      decoration: const InputDecoration(
        labelText: 'Tarih',
        suffixIcon: Icon(Icons.calendar_today_outlined),
      ),
      child: Text(DateFormat('dd.MM.yyyy').format(value)),
    ),
  );
}

class DateFilterField extends StatelessWidget {
  const DateFilterField({
    super.key,
    required this.label,
    required this.value,
    required this.onChanged,
  });
  final String label;
  final DateTime? value;
  final ValueChanged<DateTime?> onChanged;

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: () async {
      final selected = await showDatePicker(
        context: context,
        firstDate: DateTime(2020),
        lastDate: DateTime(2100),
        initialDate: value ?? DateTime.now(),
        locale: const Locale('tr', 'TR'),
      );
      onChanged(selected);
    },
    child: InputDecorator(
      decoration: InputDecoration(
        labelText: label,
        suffixIcon: const Icon(Icons.calendar_today_outlined),
      ),
      child: Text(
        value == null ? 'Seçin' : DateFormat('dd.MM.yyyy').format(value!),
      ),
    ),
  );
}

ButtonStyle get goldButtonStyle => FilledButton.styleFrom(
  backgroundColor: gold,
  foregroundColor: navy,
  padding: const EdgeInsets.symmetric(vertical: 15),
  textStyle: const TextStyle(fontWeight: FontWeight.w800),
);
String? requiredText(String? value) =>
    (value ?? '').trim().isEmpty ? 'Bu alan zorunludur.' : null;
String? numberText(String? value) =>
    optionalNumber(value) == null ? 'Geçerli bir sayı girin.' : null;
String dateValue(DateTime date) => DateFormat('yyyy-MM-dd').format(date);
String currency(num value) => NumberFormat.currency(
  locale: 'tr_TR',
  symbol: '₺',
  decimalDigits: 2,
).format(value);
String number(num value, {int decimals = 2}) =>
    NumberFormat.decimalPatternDigits(
      locale: 'tr_TR',
      decimalDigits: decimals,
    ).format(value);
double numOf(dynamic value) => value is num
    ? value.toDouble()
    : double.tryParse(value?.toString().replaceAll(',', '.') ?? '') ?? 0;
int intOf(dynamic value, [int fallback = 0]) =>
    value is int ? value : int.tryParse(value?.toString() ?? '') ?? fallback;
String stringOf(dynamic value) => value?.toString() ?? '';
String? nullIfEmpty(String value) => value.trim().isEmpty ? null : value.trim();
double? optionalNumber(String? value) => value == null || value.trim().isEmpty
    ? null
    : double.tryParse(value.trim().replaceAll(',', '.'));
Map<String, dynamic> mapOf(dynamic value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
List<Map<String, dynamic>> mapsOf(dynamic value) => value is List
    ? value
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList()
    : <Map<String, dynamic>>[];
List<String> listOf(dynamic value) =>
    value is List ? value.map(stringOf).toList() : <String>[];
bool isTracking(Map<String, dynamic>? vehicle) =>
    vehicle?['tracks_meters'] == true;
String roleLabel(String role) => switch (role) {
  'super_admin' => 'Sistem yöneticisi',
  'admin' => 'Yönetici',
  _ => 'Personel',
};
void showMessage(BuildContext context, String message, {bool error = false}) =>
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: error ? Colors.red.shade700 : Colors.green.shade700,
      ),
    );

Future<String?> reasonDialog(
  BuildContext context, {
  required String title,
  required String hint,
}) async {
  final controller = TextEditingController();
  final result = await showDialog<String>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      title: Text(title),
      content: TextField(
        controller: controller,
        maxLines: 3,
        decoration: InputDecoration(labelText: hint),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(dialogContext),
          child: const Text('Vazgeç'),
        ),
        FilledButton(
          onPressed: () => Navigator.pop(dialogContext, controller.text.trim()),
          style: goldButtonStyle,
          child: const Text('Onayla'),
        ),
      ],
    ),
  );
  controller.dispose();
  if (result == null || result.trim().length < 5) {
    if (context.mounted && result != null) {
      showMessage(context, 'En az 5 karakter gerekçe girin.', error: true);
    }
    return null;
  }
  return result;
}

T? firstMatching<T>(Iterable<T> values, bool Function(T) test) {
  for (final value in values) {
    if (test(value)) return value;
  }
  return null;
}
