import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter_networking/flutter_networking.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

class _MockConnectivity extends Mock implements Connectivity {}

void main() {
  group('NetworkInfoImpl', () {
    late _MockConnectivity connectivity;
    late NetworkInfoImpl networkInfo;

    setUp(() {
      connectivity = _MockConnectivity();
      networkInfo = NetworkInfoImpl(connectivity);
    });

    test(
      'isConnected est vrai quand une interface autre que none est active',
      () async {
        when(
          () => connectivity.checkConnectivity(),
        ).thenAnswer((_) async => [ConnectivityResult.wifi]);

        expect(await networkInfo.isConnected, isTrue);
      },
    );

    test(
      'isConnected est faux quand seule ConnectivityResult.none est renvoyé',
      () async {
        when(
          () => connectivity.checkConnectivity(),
        ).thenAnswer((_) async => [ConnectivityResult.none]);

        expect(await networkInfo.isConnected, isFalse);
      },
    );
  });
}
