import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:widgetbook/widgetbook.dart';

import '../widgets/story_scaffold.dart';

/// Stories des composants métier KORA (Bus/Ride/Colis) : cartes de
/// départ, plan de sièges, ligne passager, ticket.
final businessComponentStories = WidgetbookFolder(
  name: 'Composants métier',
  children: [
    WidgetbookComponent(
      name: 'DepartureCard',
      useCases: [
        WidgetbookUseCase(
          name: 'Par défaut',
          builder: (context) => StoryScaffold(
            child: DepartureCard(
              departureTime: '14:30',
              origin: 'Cotonou',
              destination: 'Porto-Novo',
              price: '2 500 FCFA',
              seatsAvailable: 12,
              onTap: () {},
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'Sélectionnée',
          builder: (context) => StoryScaffold(
            child: DepartureCard(
              departureTime: '14:30',
              origin: 'Cotonou',
              destination: 'Porto-Novo',
              price: '2 500 FCFA',
              seatsAvailable: 12,
              isSelected: true,
              onTap: () {},
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'Sans quota de places (Ride)',
          builder: (context) => StoryScaffold(
            child: DepartureCard(
              departureTime: '09:15',
              origin: 'Abidjan',
              destination: 'Yamoussoukro',
              price: '8 000 FCFA',
              onTap: () {},
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'Liste',
          builder: (context) => StoryScaffold(
            child: Column(
              children: [
                DepartureCard(
                  departureTime: '08:00',
                  origin: 'Cotonou',
                  destination: 'Lomé',
                  price: '5 000 FCFA',
                  seatsAvailable: 4,
                  onTap: () {},
                ),
                const SizedBox(height: KoraSpacing.md),
                DepartureCard(
                  departureTime: '14:30',
                  origin: 'Cotonou',
                  destination: 'Lomé',
                  price: '5 000 FCFA',
                  seatsAvailable: 12,
                  isSelected: true,
                  onTap: () {},
                ),
                const SizedBox(height: KoraSpacing.md),
                DepartureCard(
                  departureTime: '19:45',
                  origin: 'Cotonou',
                  destination: 'Lomé',
                  price: '5 500 FCFA',
                  seatsAvailable: 1,
                  onTap: () {},
                ),
              ],
            ),
          ),
        ),
      ],
    ),
    WidgetbookComponent(
      name: 'SeatMap',
      useCases: [
        WidgetbookUseCase(
          name: 'Places mixtes',
          builder: (context) => StoryScaffold(
            child: SeatMap(
              seats: const [
                KoraSeat(label: '1A'),
                KoraSeat(label: '1B', status: KoraSeatStatus.occupied),
                KoraSeat(label: '1C', status: KoraSeatStatus.occupied),
                KoraSeat(label: '1D'),
                KoraSeat(label: '2A', status: KoraSeatStatus.selected),
                KoraSeat(label: '2B'),
                KoraSeat(label: '2C'),
                KoraSeat(label: '2D', status: KoraSeatStatus.disabled),
                KoraSeat(label: '3A'),
                KoraSeat(label: '3B'),
                KoraSeat(label: '3C', status: KoraSeatStatus.occupied),
                KoraSeat(label: '3D'),
              ],
              onSeatTap: (seat) {},
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'Toutes disponibles',
          builder: (context) => StoryScaffold(
            child: SeatMap(
              seats: List.generate(12, (i) => KoraSeat(label: '${(i ~/ 4) + 1}${'ABCD'[i % 4]}')),
              onSeatTap: (seat) {},
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'Bus complet',
          builder: (context) => StoryScaffold(
            child: SeatMap(
              seats: List.generate(
                12,
                (i) => KoraSeat(
                  label: '${(i ~/ 4) + 1}${'ABCD'[i % 4]}',
                  status: KoraSeatStatus.occupied,
                ),
              ),
            ),
          ),
        ),
      ],
    ),
    WidgetbookComponent(
      name: 'PassengerTile',
      useCases: [
        WidgetbookUseCase(
          name: 'Par défaut',
          builder: (context) => const StoryScaffold(
            child: PassengerTile(
              name: 'Awa Kouassi',
              subtitle: 'Siège 12A · +225 07 00 00 00',
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'Embarqué',
          builder: (context) => const StoryScaffold(
            child: PassengerTile(
              name: 'Awa Kouassi',
              subtitle: 'Siège 12A',
              statusLabel: 'Embarqué',
              statusVariant: KoraTagVariant.gold,
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'No-show',
          builder: (context) => const StoryScaffold(
            child: PassengerTile(
              name: 'Jean Dupont',
              subtitle: 'Siège 3C',
              statusLabel: 'No-show',
              statusVariant: KoraTagVariant.outline,
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'Liste',
          builder: (context) => StoryScaffold(
            child: Column(
              children: const [
                PassengerTile(
                  name: 'Awa Kouassi',
                  subtitle: 'Siège 12A',
                  statusLabel: 'Embarqué',
                  statusVariant: KoraTagVariant.gold,
                ),
                SizedBox(height: KoraSpacing.sm),
                PassengerTile(
                  name: 'Moussa Traoré',
                  subtitle: 'Siège 12B',
                  statusLabel: 'En attente',
                  statusVariant: KoraTagVariant.blue,
                ),
                SizedBox(height: KoraSpacing.sm),
                PassengerTile(
                  name: 'Jean Dupont',
                  subtitle: 'Siège 3C',
                  statusLabel: 'No-show',
                  statusVariant: KoraTagVariant.outline,
                ),
              ],
            ),
          ),
        ),
      ],
    ),
    WidgetbookComponent(
      name: 'TicketView',
      useCases: [
        WidgetbookUseCase(
          name: 'Par défaut',
          builder: (context) => const StoryScaffold(
            child: TicketView(
              passengerName: 'Awa Kouassi',
              origin: 'Cotonou',
              destination: 'Porto-Novo',
              departureTime: '14:30',
              date: '12 Août 2026',
              seatLabel: '12A',
              ticketCode: 'KRA-284910',
            ),
          ),
        ),
      ],
    ),
  ],
);
