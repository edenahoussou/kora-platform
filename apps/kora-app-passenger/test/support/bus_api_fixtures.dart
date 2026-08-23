// Les réponses de l'API Bus recopiées **telles quelles** depuis
// `docs/mintlify/guides/bus-search.mdx`.
//
// Partagées entre les tests des modèles et ceux du data source : les deux
// doivent vérifier la même réalité, et une réponse réécrite à la main
// dans chaque fichier finirait par diverger de la documentation.

/// La réponse de `GET /api/v1/bus/cities`, telle quelle.
const Map<String, dynamic> kCitiesResponse = {
  'data': [
    {'city': 'Cotonou', 'country': 'BJ', 'stops_count': 3},
    {'city': 'Abidjan', 'country': 'CI', 'stops_count': 2},
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

/// Un départ de `GET /api/v1/bus/departures`, tel quel.
const Map<String, dynamic> kDeparture = {
  'uuid': 'a3091549-9179-454e-8440-58179f2208df',
  'departure_date': '2026-08-20',
  'departure_time': '06:00',
  'estimated_arrival_at': '2026-08-20T14:00:00.000Z',
  'status': 'scheduled',
  'line': kLine,
  'bus': {
    'uuid': 'b4102650-a280-4655-9551-69280321aaef',
    'registration_number': 'BJ-2024-AB-1234',
    'brand': 'Mercedes',
    'model': 'O500',
    'seat_count': 49,
  },
  'origin_stop': kCotonouStop,
  'destination_stop': kAbidjanStop,
  'available_seats_count': 32,
  // Forme exacte renvoyée par la staging : deux montants, et aucun
  // libellé pré-formaté. Une fixture inventée avait laissé passer une
  // lecture sur `amount`, qui n'existe pas — l'app affichait 0 FCFA.
  'fare_preview': {
    'fare_uuid': 'ae8feee9-1a78-4745-83f1-f12817bbcb46',
    'base_amount': 15000,
    'final_amount': 15000,
    'currency': 'XOF',
    'is_quote': false,
  },
};
