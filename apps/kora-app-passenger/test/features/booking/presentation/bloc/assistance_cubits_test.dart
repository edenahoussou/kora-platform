// Les cubits de la réservation assistée : un seul envoi à la fois, une clé
// stable d'un essai à l'autre, et une liste de demandes qui suit les envois.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/assisted_booking.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/assistance_cubits.dart';

import '../../../../support/assistance_harness.dart';

void main() {
  late StubAssistedBookingRepository repository;

  setUp(() => repository = StubAssistedBookingRepository());

  group('envoi', () {
    late AssistanceRequestCubit cubit;

    setUp(() {
      cubit = AssistanceRequestCubit(repository);
      addTearDown(cubit.close);
    });

    Future<void> send() => cubit.send(
      reservationId: 'resa-1',
      contactName: 'Aïcha',
      contactPhone: '+2290197421860',
      record: (booking) => assistanceRecord(reservationId: 'resa-1'),
    );

    test('un double appui ne part qu’une fois', () async {
      await Future.wait([send(), send()]);

      expect(
        repository.calls.where((call) => call.startsWith('request')),
        hasLength(1),
      );
      expect(cubit.state.sent?.reference, 'KBR-7X2K9P4Q');
    });

    test(
      'une erreur réseau garde l’écran, et le nouvel essai la même clé',
      () async {
        repository.nextRequestFailures.add(
          const NetworkFailure('Pas de connexion internet.'),
        );

        await send();
        expect(cubit.state.sent, isNull);
        expect(cubit.state.isSending, isFalse);
        expect(cubit.state.failure, isA<NetworkFailure>());
        // Rien n'est gardé tant que le serveur n'a pas accepté.
        expect(repository.saved, isEmpty);

        await send();
        expect(repository.idempotencyKeys, [
          'kora-assist-resa-1',
          'kora-assist-resa-1',
        ]);
        expect(cubit.state.sent, isNotNull);
        expect(repository.saved.single.reference, 'KBR-7X2K9P4Q');
      },
    );

    test('le canal part sur WhatsApp, et suit le choix', () async {
      expect(cubit.state.channel, AssistanceChannel.whatsapp);

      cubit.chooseChannel(AssistanceChannel.call);
      await send();

      expect(
        repository.calls,
        contains('request:call:+2290197421860'),
      );
    });
  });

  group('demandes de l’onglet Voyages', () {
    test('les traces d’abord, puis leur statut', () async {
      repository.saved.add(assistanceRecord());
      repository.status = AssistanceStatus.expired;
      final cubit = AssistanceRequestsCubit(repository);
      addTearDown(cubit.close);

      await cubit.load();

      expect(cubit.state.records.single.reference, 'KBR-7X2K9P4Q');
      expect(cubit.state.statuses['hold-groupe-1'], AssistanceStatus.expired);
    });

    test('une demande envoyée ailleurs apparaît d’elle-même', () async {
      final cubit = AssistanceRequestsCubit(repository);
      addTearDown(cubit.close);
      await cubit.load();
      expect(cubit.state.records, isEmpty);

      await repository.remember(assistanceRecord());
      await cubit.stream.firstWhere((state) => state.records.isNotEmpty);

      expect(cubit.state.records.single.reference, 'KBR-7X2K9P4Q');
    });
  });

  group('suivi', () {
    test('sans trace locale, pas de reprise en ligne', () async {
      // Les sièges à reprendre ne sont connus que du téléphone qui a envoyé
      // la demande.
      final cubit = AssistanceStatusCubit(repository);
      addTearDown(cubit.close);

      await cubit.load('hold-groupe-1');

      expect(cubit.state.booking?.canResumeOnline, isTrue);
      expect(cubit.state.canResumeOnline, isFalse);
    });
  });
}
