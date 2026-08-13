import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:santiye_takip/app.dart';

void main() {
  testWidgets('Şantiye Takip giriş ekranı açılır', (WidgetTester tester) async {
    await tester.pumpWidget(
      const MaterialApp(home: Scaffold(body: BrandHeader())),
    );
    expect(find.text('Şantiye Takip'), findsOneWidget);
  });
}
