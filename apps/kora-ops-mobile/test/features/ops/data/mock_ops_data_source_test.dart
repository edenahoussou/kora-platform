import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/features/ops/data/mock_ops_data_source.dart';
import 'package:kora_ops_mobile/features/ops/domain/entities/passenger.dart';

void main() {
  group('MockOpsDataSource', () {
    test('expose au moins un départ mocké', () {
      expect(MockOpsDataSource.departs, isNotEmpty);
    });

    test('passengersFor retourne autant de passagers que placesOccupees', () {
      final depart = MockOpsDataSource.departs.first;
      final passengers = MockOpsDataSource.passengersFor(depart.id);

      expect(passengers.length, depart.placesOccupees);
    });

    test('passengersFor est déterministe pour un même départ (seed stable)',
        () {
      final depart = MockOpsDataSource.departs.first;
      final first = MockOpsDataSource.passengersFor(depart.id);
      final second = MockOpsDataSource.passengersFor(depart.id);

      expect(
          first.map((Passenger p) => p.id), second.map((Passenger p) => p.id));
    });

    test('seatsFor retourne un siège par passager', () {
      final depart = MockOpsDataSource.departs.first;
      final seats = MockOpsDataSource.seatsFor(depart.id);
      final passengers = MockOpsDataSource.passengersFor(depart.id);

      expect(seats.length, passengers.length);
    });
  });
}
