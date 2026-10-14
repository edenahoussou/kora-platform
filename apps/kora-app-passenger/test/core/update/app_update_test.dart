// La mise à jour, remise à Google Play.
//
// Il ne reste qu'un arbitrage à tester : imposer ou proposer. Il se lit
// dans la priorité choisie au moment de publier, et le reste — ce qui est
// installé, ce qui est publié — appartient à Play.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/update/app_update.dart';
import 'package:kora_app_passenger/core/update/update_gateway.dart';

class FakeGateway implements UpdateGateway {
  FakeGateway({this.supported = true, this.update});

  bool supported;
  PlayUpdate? update;

  var immediates = 0;
  var flexibles = 0;
  var asked = 0;

  @override
  bool get isSupported => supported;

  @override
  Future<PlayUpdate?> pending() async {
    asked++;
    return supported ? update : null;
  }

  @override
  Future<void> startImmediate() async => immediates++;

  @override
  Future<void> startFlexible() async => flexibles++;
}

PlayUpdate update({
  int priority = 0,
  bool immediate = true,
  bool flexible = true,
}) => PlayUpdate(
  immediateAllowed: immediate,
  flexibleAllowed: flexible,
  priority: priority,
);

void main() {
  late FakeGateway gateway;

  setUp(() => gateway = FakeGateway());

  test('sans mise à jour en attente, il ne se passe rien', () async {
    await AppUpdate(gateway).run();

    expect(gateway.immediates, 0);
    expect(gateway.flexibles, 0);
  });

  test('une priorité basse se propose sans interrompre', () async {
    gateway.update = update(priority: 2);

    await AppUpdate(gateway).run();

    // « Flexible » : le téléchargement se fait en tâche de fond, le
    // voyageur continue à réserver pendant ce temps.
    expect(gateway.flexibles, 1);
    expect(gateway.immediates, 0);
  });

  test('une priorité haute s’impose', () async {
    gateway.update = update(priority: 4);

    await AppUpdate(gateway).run();

    expect(gateway.immediates, 1);
    expect(gateway.flexibles, 0);
  });

  test('une priorité haute que Play refuse d’imposer se propose', () async {
    // Play peut interdire le mode immédiat selon l'écart de versions.
    // Mieux vaut alors proposer que ne rien faire.
    gateway.update = update(priority: 5, immediate: false);

    await AppUpdate(gateway).run();

    expect(gateway.immediates, 0);
    expect(gateway.flexibles, 1);
  });

  test('si Play n’autorise aucun mode, on n’insiste pas', () async {
    gateway.update = update(priority: 5, immediate: false, flexible: false);

    await AppUpdate(gateway).run();

    expect(gateway.immediates, 0);
    expect(gateway.flexibles, 0);
  });

  test('hors Android, rien n’est tenté', () async {
    // Apple ne publie aucune API de mise à jour : il n'y a pas de demi-
    // mesure à inventer ici.
    gateway.supported = false;
    gateway.update = update(priority: 5);

    await AppUpdate(gateway).run();

    expect(gateway.immediates, 0);
    expect(gateway.flexibles, 0);
  });
}
