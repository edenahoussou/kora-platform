import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/features/app_update/data/models/app_update_dtos.dart';
import 'package:kora_ops_mobile/features/app_update/domain/entities/app_update_info.dart';
import 'package:kora_ops_mobile/features/app_update/domain/entities/app_version.dart';

Map<String, dynamic> _block({bool mandatory = true}) => <String, dynamic>{
      'app': 'ops_mobile',
      'status': mandatory ? 'update_required' : 'update_available',
      'latest_version': '1.4.0',
      'latest_build': 42,
      'mandatory': mandatory,
      'minimum_version': mandatory ? '1.3.0' : null,
      'minimum_build': mandatory ? 35 : null,
      'release_uuid': '6f0c1d3e-7c1b-4a8a-9d6e-2b1a0c9f8e71',
      'release_notes': 'Correctifs',
      'download_url':
          'https://ops.koraexpress.app/downloads/apps/ops-mobile/releases/6f0c',
      'download_page_url':
          'https://ops.koraexpress.app/downloads/apps/ops-mobile',
      'sha256': 'ABCDEF',
      'size_bytes': 31457280,
      'published_at': '2026-10-25T09:00:00.000Z',
    };

void main() {
  group('AppUpdateDtos.fromOpsResponse (handshake/sync/activity)', () {
    test('lit data.update et normalise le sha256 en minuscules', () {
      final result = AppUpdateDtos.fromOpsResponse(<String, dynamic>{
        'data': <String, dynamic>{'uuid': 't-1', 'update': _block()},
      });

      expect(result.present, isTrue);
      expect(result.info?.latestVersion, '1.4.0');
      expect(result.info?.latestBuild, 42);
      expect(result.info?.sha256, 'abcdef');
      expect(result.info?.serverMandatory, isTrue);
    });

    test('distingue un bloc null (aucune release) d’un bloc absent', () {
      final explicitNull = AppUpdateDtos.fromOpsResponse(<String, dynamic>{
        'data': <String, dynamic>{'update': null},
      });
      final absent = AppUpdateDtos.fromOpsResponse(<String, dynamic>{
        'data': <String, dynamic>{'uuid': 't-1'},
      });

      expect(explicitNull.present, isTrue);
      expect(explicitNull.info, isNull);
      expect(absent.present, isFalse);
    });

    test('ignore un bloc incomplet sans lever d’exception', () {
      final result = AppUpdateDtos.fromOpsResponse(<String, dynamic>{
        'data': <String, dynamic>{
          'update': <String, dynamic>{'latest_version': '1.4.0'},
        },
      });

      expect(result.present, isTrue);
      expect(result.info, isNull);
    });
  });

  test('fromCheckResponse lit le bloc à la racine de data', () {
    expect(
      AppUpdateDtos.fromCheckResponse(<String, dynamic>{'data': _block()})
          ?.releaseUuid,
      '6f0c1d3e-7c1b-4a8a-9d6e-2b1a0c9f8e71',
    );
    expect(
      AppUpdateDtos.fromCheckResponse(<String, dynamic>{'data': null}),
      isNull,
    );
  });

  test('toBlock/fromBlock font un aller-retour sans perte (cache local)', () {
    final AppUpdateInfo info = AppUpdateDtos.fromBlock(_block())!;

    expect(AppUpdateDtos.fromBlock(AppUpdateDtos.toBlock(info)), info);
  });

  group('AppUpdateInfo — décision locale', () {
    final AppUpdateInfo mandatory = AppUpdateDtos.fromBlock(_block())!;
    final AppUpdateInfo optional =
        AppUpdateDtos.fromBlock(_block(mandatory: false))!;

    test('bloque sous le seuil minimal, propose seulement au-delà', () {
      expect(mandatory.isMandatoryFor(AppVersion.tryParse('1.2.0+30')), isTrue);
      expect(
        mandatory.isMandatoryFor(AppVersion.tryParse('1.3.0+35')),
        isFalse,
      );
      expect(mandatory.isAvailableFor(AppVersion.tryParse('1.3.0+35')), isTrue);
    });

    test(
        'ne bloque jamais une version déjà à jour, même si le serveur '
        's’appuie sur une version remontée plus ancienne', () {
      expect(
        mandatory.isMandatoryFor(AppVersion.tryParse('1.4.0+42')),
        isFalse,
      );
      expect(
        mandatory.isAvailableFor(AppVersion.tryParse('1.4.0+42')),
        isFalse,
      );
    });

    test('une mise à jour non obligatoire est seulement proposée', () {
      expect(optional.isAvailableFor(AppVersion.tryParse('1.0.0+1')), isTrue);
      expect(optional.isMandatoryFor(AppVersion.tryParse('1.0.0+1')), isFalse);
    });
  });
}
