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
              departureTime: '07:30',
              arrivalTime: '17:15',
              origin: 'Cotonou',
              destination: 'Malanville',
              duration: '≈ 9 h 45',
              price: '12 500 FCFA',
              seatsAvailable: 12,
              onTap: () {},
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'Sélectionnée',
          builder: (context) => StoryScaffold(
            child: DepartureCard(
              departureTime: '07:30',
              arrivalTime: '17:15',
              origin: 'Cotonou',
              destination: 'Malanville',
              duration: '≈ 9 h 45',
              price: '12 500 FCFA',
              seatsAvailable: 12,
              isSelected: true,
              onTap: () {},
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'Complet',
          builder: (context) => const StoryScaffold(
            child: DepartureCard(
              departureTime: '22:30',
              arrivalTime: '08:15',
              origin: 'Cotonou',
              destination: 'Malanville',
              duration: '≈ 9 h 45',
              price: '12 500 FCFA',
              isSoldOut: true,
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'Sans quota de places (Ride)',
          builder: (context) => StoryScaffold(
            child: DepartureCard(
              departureTime: '09:15',
              arrivalTime: '09:52',
              origin: 'Abidjan',
              destination: 'Yamoussoukro',
              duration: '≈ 37 min',
              price: '8 000 FCFA',
              onTap: () {},
            ),
          ),
        ),
        // Reproduit l'écran "results" du prototype (`js/app.js`, L531-543) :
        // 3 départs disponibles + un segment complet dans la même liste.
        WidgetbookUseCase(
          name: '3 départs disponibles (écran résultats)',
          builder: (context) => StoryScaffold(
            child: Column(
              children: [
                DepartureCard(
                  departureTime: '07:30',
                  arrivalTime: '17:15',
                  origin: 'Cotonou',
                  destination: 'Malanville',
                  duration: '≈ 9 h 45',
                  price: '12 500 FCFA',
                  seatsAvailable: 12,
                  onTap: () {},
                ),
                const SizedBox(height: KoraSpacing.md),
                DepartureCard(
                  departureTime: '09:00',
                  arrivalTime: '18:55',
                  origin: 'Cotonou',
                  destination: 'Malanville',
                  duration: '≈ 9 h 55',
                  price: '12 500 FCFA',
                  seatsAvailable: 5,
                  onTap: () {},
                ),
                const SizedBox(height: KoraSpacing.md),
                DepartureCard(
                  departureTime: '20:00',
                  arrivalTime: '05:45',
                  origin: 'Cotonou',
                  destination: 'Malanville',
                  duration: '≈ 9 h 45',
                  price: '14 500 FCFA',
                  seatsAvailable: 22,
                  onTap: () {},
                ),
                const SizedBox(height: KoraSpacing.md),
                ConnectionResultCard(
                  departureTime: '06:45',
                  arrivalTime: '17:40',
                  routeLabel: 'Cotonou → Bohicon → Natitingou',
                  noteLabel: 'Changement de bus à Bohicon · attente 35 min',
                  segmentsLabel: '2 segments inclus',
                  price: '14 500 FCFA',
                  onTap: () {},
                ),
                const SizedBox(height: KoraSpacing.md),
                const DepartureCard(
                  departureTime: '22:30',
                  arrivalTime: '08:15',
                  origin: 'Cotonou',
                  destination: 'Malanville',
                  duration: '≈ 9 h 45',
                  price: '12 500 FCFA',
                  isSoldOut: true,
                ),
              ],
            ),
          ),
        ),
      ],
    ),
    WidgetbookComponent(
      name: 'HomeSkeleton',
      useCases: [
        WidgetbookUseCase(
          name: 'Accueil en chargement',
          builder: (context) => const StoryScaffold(
            padding: EdgeInsets.all(18),
            child: HomeSkeleton(),
          ),
        ),
        // Quand on sait déjà qu'aucun billet n'est à venir, esquisser une
        // carte « Prochain voyage » promettrait un contenu qui ne
        // viendra pas.
        WidgetbookUseCase(
          name: 'Sans prochain voyage',
          builder: (context) => const StoryScaffold(
            padding: EdgeInsets.all(18),
            child: HomeSkeleton(showNextTrip: false),
          ),
        ),
      ],
    ),
    // Le pendant en chargement de « 3 départs disponibles » ci-dessus :
    // les deux stories se comparent à l'écran, c'est ce qui permet de
    // voir si le fantôme tombe bien en face du contenu réel.
    WidgetbookComponent(
      name: 'DeparturesSkeleton',
      useCases: [
        WidgetbookUseCase(
          name: 'Écran résultats en chargement',
          builder: (context) =>
              const StoryScaffold(child: DeparturesSkeleton()),
        ),
        WidgetbookUseCase(
          name: 'Liste longue (5 départs)',
          builder: (context) =>
              const StoryScaffold(child: DeparturesSkeleton(count: 5)),
        ),
        WidgetbookUseCase(
          name: 'Une carte seule',
          builder: (context) =>
              const StoryScaffold(child: DepartureCardSkeleton()),
        ),
        // Fantôme puis carte réelle, l'un au-dessus de l'autre : chaque
        // bloc doit se retrouver à la hauteur de l'information qu'il
        // remplace, sinon la liste sursaute au remplissage.
        WidgetbookUseCase(
          name: 'Fantôme et carte réelle superposés',
          builder: (context) => StoryScaffold(
            child: Column(
              children: [
                const DepartureCardSkeleton(),
                const SizedBox(height: KoraSpacing.md),
                DepartureCard(
                  departureTime: '07:30',
                  arrivalTime: '17:15',
                  origin: 'Cotonou',
                  destination: 'Malanville',
                  duration: '≈ 9 h 45',
                  price: '12 500 FCFA',
                  seatsAvailable: 12,
                  onTap: () {},
                ),
              ],
            ),
          ),
        ),
      ],
    ),
    WidgetbookComponent(
      name: 'ConnectionResultCard',
      useCases: [
        WidgetbookUseCase(
          name: 'Par défaut',
          builder: (context) => StoryScaffold(
            child: ConnectionResultCard(
              departureTime: '06:45',
              arrivalTime: '17:40',
              routeLabel: 'Cotonou → Bohicon → Natitingou',
              noteLabel: 'Changement de bus à Bohicon · attente 35 min',
              segmentsLabel: '2 segments inclus',
              price: '14 500 FCFA',
              onTap: () {},
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'Deux correspondances',
          builder: (context) => StoryScaffold(
            child: ConnectionResultCard(
              departureTime: '05:30',
              arrivalTime: '20:10',
              routeLabel: 'Cotonou → Bohicon → Parakou → Kandi',
              noteLabel:
                  'Changements à Bohicon et Parakou · attente totale 55 min',
              segmentsLabel: '3 segments inclus',
              price: '17 000 FCFA',
              connectionsLabel: '2 correspondances',
              onTap: () {},
            ),
          ),
        ),
      ],
    ),
    WidgetbookComponent(
      name: 'SeatMap',
      useCases: [
        // Reproduit la logique de génération de l'écran `seats` du
        // prototype (`js/app.js`, L1660) : rangée 1 entièrement bloquée,
        // sièges payés/embarqués/réservés dispersés sur les autres
        // rangées — les 6 états sont ainsi tous représentés.
        WidgetbookUseCase(
          name: 'Places mixtes (6 états)',
          builder: (context) => StoryScaffold(
            child: SeatMap(
              seats: const [
                KoraSeat(label: '1A', status: KoraSeatStatus.blocked),
                KoraSeat(label: '1B', status: KoraSeatStatus.blocked),
                KoraSeat(label: '1C', status: KoraSeatStatus.blocked),
                KoraSeat(label: '1D', status: KoraSeatStatus.blocked),
                KoraSeat(label: '2A'),
                KoraSeat(label: '2B', status: KoraSeatStatus.selected),
                KoraSeat(
                  label: '2C',
                  status: KoraSeatStatus.held,
                  timerLabel: '06:18',
                ),
                KoraSeat(label: '2D'),
                KoraSeat(label: '3A', status: KoraSeatStatus.paid),
                KoraSeat(label: '3B'),
                KoraSeat(label: '3C', status: KoraSeatStatus.embarked),
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
              seats: List.generate(
                12,
                (i) => KoraSeat(label: '${(i ~/ 4) + 1}${'ABCD'[i % 4]}'),
              ),
              onSeatTap: (seat) {},
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'Bus complet (payé)',
          builder: (context) => StoryScaffold(
            child: SeatMap(
              seats: List.generate(
                12,
                (i) => KoraSeat(
                  label: '${(i ~/ 4) + 1}${'ABCD'[i % 4]}',
                  status: KoraSeatStatus.paid,
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
          name: 'Confirmé',
          builder: (context) => const StoryScaffold(
            child: TicketView(
              passengerName: 'A. HOUNKPÈ',
              originCode: 'CTN',
              origin: 'Cotonou',
              destinationCode: 'MLV',
              destination: 'Malanville',
              date: '04 AOÛ',
              departureTime: '07:30',
              seatLabel: '4A',
              ticketCode: 'KE-20260804-78342',
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'Annulé',
          builder: (context) => const StoryScaffold(
            child: TicketView(
              passengerName: 'A. HOUNKPÈ',
              originCode: 'CTN',
              origin: 'Cotonou',
              destinationCode: 'MLV',
              destination: 'Malanville',
              date: '04 AOÛ',
              departureTime: '07:30',
              seatLabel: '4A',
              ticketCode: 'KE-20260804-78342',
              isCancelled: true,
            ),
          ),
        ),
      ],
    ),
  ],
);
