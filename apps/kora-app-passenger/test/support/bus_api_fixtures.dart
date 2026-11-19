// Les réponses de l'API Bus recopiées **telles quelles** depuis
// `docs/mintlify/guides/bus-search.mdx`.
//
// Partagées entre les tests des modèles et ceux du data source : les deux
// doivent vérifier la même réalité, et une réponse réécrite à la main
// dans chaque fichier finirait par diverger de la documentation.

/// La réponse de `GET /api/v1/bus/cities`, telle quelle.
const Map<String, dynamic> kCitiesResponse = {
  'data': [
    {
      'city': 'Cotonou',
      'country': 'BJ',
      'stops_count': 3,
      'physical_stops': [kCotonouPhysicalStop],
    },
    {
      'city': 'Abidjan',
      'country': 'CI',
      'stops_count': 2,
      'physical_stops': [kAbidjanPhysicalStop],
    },
    {'city': 'Bohicon', 'country': 'BJ', 'stops_count': 1},
  ],
};

const Map<String, dynamic> kCotonouStop = {
  'uuid': '6b72fe4a-5b40-307c-bc24-f79acf9a1bb9',
  'name': 'Gare de Cotonou',
  'city': 'Cotonou',
  'country': 'BJ',
  'stop_order': 1,
  'km_from_origin': 0.0,
};

const Map<String, dynamic> kBohiconStop = {
  'uuid': '7c83ef5b-6c51-418d-cd35-8abd0bab2cc0',
  'name': 'Gare de Bohicon',
  'city': 'Bohicon',
  'country': 'BJ',
  'stop_order': 2,
  'km_from_origin': 120.0,
};

const Map<String, dynamic> kAbidjanStop = {
  'uuid': '8d94f06c-7d62-529e-de46-9bce1cbc3dd1',
  'name': "Gare d'Abidjan",
  'city': 'Abidjan',
  'country': 'CI',
  'stop_order': 3,
  'km_from_origin': 1050.5,
};

const Map<String, dynamic> kLine = {
  'uuid': '6ff8f7f6-1eb3-3525-be4a-3932c805afed',
  'code': 'COT-ABJ',
  'name': 'Cotonou — Abidjan',
  'countries_crossed': ['BJ', 'CI'],
  'distance_km': 1050.5,
  'estimated_duration_minutes': 480,
  'status': 'active',
};

/// Une ville de `GET /api/v1/bus/cities` avec ses arrêts physiques.
///
/// C'est `physical_stops[].uuid` — et non l'arrêt de ligne porté par un
/// itinéraire — que `bus/itineraries` attend en paramètre : les deux tables
/// sont distinctes côté serveur, et confondre les identifiants rend un 422.
const Map<String, dynamic> kCotonouPhysicalStop = {
  'uuid': '74703fce-3358-429d-9994-6b323664a419',
  'name': 'Cotonou',
};

const Map<String, dynamic> kAbidjanPhysicalStop = {
  'uuid': 'f2b528f4-3c99-461f-ac4e-a10f0721b388',
  'name': 'Abidjan',
};

/// Un itinéraire direct de `GET /api/v1/bus/itineraries`, recopié de la
/// staging le 8 septembre 2026 (Cotonou → Abidjan).
///
/// Deux détails que seule la vraie réponse apprend, et qu'une fixture
/// écrite de mémoire aurait manqués : `line` **ne porte pas** de
/// `distance_km`, et l'arrêt d'un tronçon a un `uuid` différent de l'arrêt
/// physique interrogé par la recherche.
const Map<String, dynamic> kItinerary = {
  'connections_count': 0,
  'total_amount': 31600,
  'currency': 'XOF',
  'departure_at': '2026-09-09T06:00:00+00:00',
  'arrival_at': '2026-09-10T00:30:00+00:00',
  'connection_margin_minutes': 45,
  'legs': [kItineraryLeg],
};

const Map<String, dynamic> kItineraryLeg = {
  'leg_order': 1,
  'departure_uuid': '56f674c2-7795-4427-9839-ff5de7345ade',
  'line': {
    'uuid': '6c10d2f6-a930-42b8-8464-97bbec4411a9',
    'code': 'L1',
    'name': 'Corridor',
    'estimated_duration_minutes': 1110,
    'is_international': true,
  },
  'bus': {
    'uuid': 'd0dd8f1a-cf65-4cf7-8bdd-156d4ff6678d',
    'registration_number': 'KORA-L1-001',
    'brand': 'KORA',
    'model': 'Coach 55',
    'seat_count': 55,
    'amenities': [
      {'code': 'wifi', 'label': 'Wi-Fi à bord', 'icon': 'wifi'},
      {
        'code': 'air_conditioning',
        'label': 'Climatisation',
        'icon': 'snowflake',
      },
      {
        'code': 'reclining_seats',
        'label': 'Sièges inclinables',
        'icon': 'armchair',
      },
    ],
  },
  'origin_stop': {
    'uuid': 'd27a305c-4a4b-405c-9ba6-0371e69024dd',
    'name': 'Cotonou',
    'city': 'Cotonou',
    'country': 'BJ',
    'stop_order': 1,
  },
  'destination_stop': {
    'uuid': '6b0c1666-6eaa-4b67-a216-df1667ae6be1',
    'name': 'Abidjan',
    'city': 'Abidjan',
    'country': 'CI',
    'stop_order': 8,
  },
  'amount': 31600,
  'available_seats_count': 32,
  'service_class_code': 'confort',
  'service_class_name': 'KORA Confort',
  'departure_at': '2026-09-09T06:00:00+00:00',
  'arrival_at': '2026-09-10T00:30:00+00:00',
};

/// Un itinéraire **à correspondance** de `GET /api/v1/bus/itineraries` :
/// Porto-Novo → Parakou, en changeant de bus à Cotonou.
///
/// C'est l'exemple du ticket : aucune ligne ne relie les deux villes, le
/// serveur compose donc le voyage avec le Porto-Novo–Lomé jusqu'à
/// Cotonou, puis le Cotonou–Parakou. Une heure d'attente sépare les deux
/// bus, et c'est le second qui limite les places.
const Map<String, dynamic> kConnectedItinerary = {
  'connections_count': 1,
  'total_amount': 12000,
  'currency': 'XOF',
  'departure_at': '2026-09-09T06:00:00+00:00',
  'arrival_at': '2026-09-09T16:00:00+00:00',
  'connection_margin_minutes': 45,
  'legs': [kPortoNovoCotonouLeg, kCotonouParakouLeg],
};

const Map<String, dynamic> kPortoNovoCotonouLeg = {
  'leg_order': 1,
  'departure_uuid': 'a1111111-1111-4111-8111-111111111111',
  'line': {
    'uuid': 'l1111111-1111-4111-8111-111111111111',
    'code': 'L1',
    'name': 'Porto-Novo - Lomé',
    'estimated_duration_minutes': 60,
    'is_international': true,
  },
  'bus': {
    'uuid': 'b1111111-1111-4111-8111-111111111111',
    'brand': 'KORA',
    'model': 'Coach 55',
    'seat_count': 55,
    'amenities': [
      {'code': 'wifi', 'label': 'Wi-Fi à bord', 'icon': 'wifi'},
    ],
  },
  'origin_stop': {
    'uuid': 's1111111-1111-4111-8111-111111111111',
    'name': 'Porto-Novo',
    'city': 'Porto-Novo',
    'country': 'BJ',
    'stop_order': 1,
  },
  'destination_stop': {
    'uuid': 's2222222-2222-4222-8222-222222222222',
    'name': 'Cotonou',
    'city': 'Cotonou',
    'country': 'BJ',
    'stop_order': 2,
  },
  'amount': 2000,
  'available_seats_count': 12,
  'service_class_code': 'classique',
  'departure_at': '2026-09-09T06:00:00+00:00',
  'arrival_at': '2026-09-09T07:00:00+00:00',
};

const Map<String, dynamic> kCotonouParakouLeg = {
  'leg_order': 2,
  'departure_uuid': 'a2222222-2222-4222-8222-222222222222',
  'line': {
    'uuid': 'l2222222-2222-4222-8222-222222222222',
    'code': 'L2',
    'name': 'Cotonou - Parakou',
    'estimated_duration_minutes': 480,
    'is_international': false,
  },
  'bus': {
    'uuid': 'b2222222-2222-4222-8222-222222222222',
    'brand': 'KORA',
    'model': 'Coach 45',
    'seat_count': 45,
    'amenities': [
      {'code': 'air_conditioning', 'label': 'Climatisation', 'icon': 'snow'},
    ],
  },
  'origin_stop': {
    'uuid': 's2222222-2222-4222-8222-222222222222',
    'name': 'Cotonou',
    'city': 'Cotonou',
    'country': 'BJ',
    'stop_order': 1,
  },
  'destination_stop': {
    'uuid': 's3333333-3333-4333-8333-333333333333',
    'name': 'Parakou',
    'city': 'Parakou',
    'country': 'BJ',
    'stop_order': 6,
  },
  'amount': 10000,
  'available_seats_count': 4,
  'service_class_code': 'confort',
  'departure_at': '2026-09-09T08:00:00+00:00',
  'arrival_at': '2026-09-09T16:00:00+00:00',
};
