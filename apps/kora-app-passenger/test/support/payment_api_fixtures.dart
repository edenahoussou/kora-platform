// La réponse de `GET /api/v1/payments/methods`, recopiée **telle quelle**
// depuis `docs/mintlify/reference/payments/methods.mdx`.
//
// Un jeu d'essai réécrit à la main ne vérifierait que la cohérence du code
// avec lui-même. Celui-ci le confronte au contrat publié.

const Map<String, dynamic> kPaymentMethodsResponse = {
  'data': {
    'country': 'BJ',
    'currency': 'XOF',
    'methods': [
      {
        'code': 'mobile_money',
        'label': 'Mobile Money',
        'enabled': true,
        'providers': [
          {
            'code': 'fedapay',
            'label': 'FedaPay',
            'enabled': true,
            'networks': ['mtn_momo', 'moov_flooz', 'celtiis_cash'],
            'requires': ['phone', 'mobile_money_network'],
            'interaction': 'push_or_redirect',
            'polling_interval_seconds': 10,
            'verification': 'server_verify',
          },
          {
            'code': 'mtn_momo',
            'label': 'MTN MoMo',
            'enabled': true,
            'networks': ['mtn_momo'],
            'requires': ['phone', 'mobile_money_network'],
            'interaction': 'push',
            'polling_interval_seconds': 10,
            'verification': 'server_verify',
          },
        ],
      },
      {
        'code': 'wallet',
        'label': 'Mini Wallet',
        'enabled': false,
        'availability': 'out_of_mvp',
        'providers': <dynamic>[],
      },
    ],
  },
};
