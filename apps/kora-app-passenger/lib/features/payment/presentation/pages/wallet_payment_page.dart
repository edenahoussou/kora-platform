import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../../core/widgets/section_title.dart';
import '../../../booking/domain/entities/price.dart';
import '../../../booking/presentation/bloc/seat_hold_bloc.dart';
import '../../../booking/presentation/widgets/hold_countdown.dart';
import '../../../wallet/domain/entities/wallet_account.dart';
import '../../../wallet/domain/repositories/wallet_repository.dart';
import '../../domain/entities/payment_catalog.dart';
import '../bloc/payment_flow_bloc.dart';
import 'payment_args.dart';
import 'payment_flow_args.dart';
import 'payment_flow_scope.dart';
import 'payment_method_page.dart';

/// Écran `wallet-payment` de la maquette : payer tout ou partie du billet
/// avec le solde et les points.
///
/// Le Wallet couvre rarement la totalité : l'écran calcule donc en
/// permanence ce qu'il restera à régler en Mobile Money, et n'envoie vers
/// ce complément que s'il est non nul. Cacher ce reste aurait fait
/// découvrir un second paiement au moment de valider.
///
/// **Le solde et les points viennent du serveur**, et c'est lui qui
/// débite. L'écran affichait « 18 750 FCFA » et « 2 400 points » écrits
/// en dur, puis — quand le Wallet couvrait tout — poussait vers l'écran
/// de succès **sans appeler l'API** : il déclarait un billet payé que
/// personne n'avait réglé et qu'aucun serveur n'avait émis. Rien ici ne
/// conclut plus un paiement ; on envoie la répartition, le serveur
/// tranche, et les écrans d'issue suivent son verdict.
class WalletPaymentPage extends StatefulWidget {
  const WalletPaymentPage({super.key, this.args, this.account});

  final PaymentArgs? args;

  /// Le Wallet déjà lu, pour les tests. En vrai l'écran le lit lui-même.
  final WalletAccount? account;

  /// Le pas du curseur de points, et celui des boutons `−` / `+` du
  /// crédit — repris de la maquette.
  static const int pointsStep = 100;
  static const int creditStep = 1000;

  @override
  State<WalletPaymentPage> createState() => _WalletPaymentPageState();
}

class _WalletPaymentPageState extends State<WalletPaymentPage> {
  int _credit = 0;
  int _points = 0;

  WalletAccount? _account;
  bool _loading = true;
  String? _loadError;

  /// Vrai seulement quand **cet écran** a lancé un paiement.
  ///
  /// Il ne le fait que dans un cas : le solde couvre tout le billet.
  /// Dès qu'il reste un complément, l'écran passe la main au choix du
  /// moyen et ne demande rien au serveur — mais il reste vivant sous la
  /// pile. Sans ce drapeau, son écoute réagissait au paiement lancé par
  /// l'écran du dessus : la feuille de l'encaisseur s'ouvrait, et le
  /// suivi se posait **par-dessus** elle. Le voyageur voyait « paiement
  /// en cours de vérification » sans avoir rien saisi, et ne retrouvait
  /// la feuille qu'en revenant en arrière.
  var _requested = false;

  @override
  void initState() {
    super.initState();

    final given = widget.account;
    if (given != null) {
      _account = given;
      _loading = false;
      return;
    }

    unawaited(_load());
  }

  /// Lit le Wallet avant de laisser répartir quoi que ce soit.
  ///
  /// Sans lui, l'écran ne connaît ni le solde, ni les points, ni le
  /// minimum d'utilisation : il ne peut proposer aucune répartition que
  /// le serveur accepterait.
  Future<void> _load() async {
    // Sans source de Wallet, l'écran se tait plutôt que de lever : une
    // dépendance absente est un écran qui s'excuse, pas un plantage.
    if (!getIt.isRegistered<WalletRepository>()) {
      setState(() => _loading = false);
      return;
    }

    final result = await getIt<WalletRepository>().fetchAccount();
    if (!mounted) return;

    result.fold(
      (failure) => setState(() {
        _loading = false;
        _loadError = failure.message;
      }),
      (account) => setState(() {
        _loading = false;
        _account = account;
        _credit = 0;
        _points = 0;
      }),
    );
  }

  /// Le solde en francs, zéro tant que le serveur n'a pas répondu.
  int get _balance => _account?.cash.amount ?? 0;

  /// Les points utilisables : ceux du compte, et jamais en dessous du
  /// minimum que le serveur impose.
  int get _availablePoints => _account?.points ?? 0;

  int get _minPoints => _account?.rules.pointsMinSpend ?? 0;

  bool get _cashUsable => _account?.rules.cashEnabled ?? false;
  bool get _pointsUsable => _account?.rules.pointsEnabled ?? false;

  /// Ce que le Wallet et les points couvrent, plafonné au prix du billet :
  /// verser plus que le total n'aurait aucun sens, et le complément
  /// deviendrait négatif.
  int get _total => widget.args?.total.amount ?? 0;

  /// Ce que les points valent en francs, au taux du serveur.
  int get _pointsValue => _account?.rules.valueOf(_points).amount ?? 0;

  int get _covered {
    final used = _credit + _pointsValue;
    return used > _total ? _total : used;
  }

  int get _remainder => _total - _covered;

  /// Le crédit ne dépasse ni le solde, ni ce qu'il reste à payer une fois
  /// les points déduits.
  int get _maxCredit {
    if (!_cashUsable) return 0;

    final room = _total - _pointsValue;
    final ceiling = room < 0 ? 0 : room;

    return ceiling > _balance ? _balance : ceiling;
  }

  /// Le plus grand nombre de points utilisable : ceux qu'on a, et pas
  /// plus que ce que le billet coûte.
  int get _maxPoints {
    if (!_pointsUsable) return 0;

    final value = _account?.rules.pointValue ?? 1;
    final affordable = value <= 0 ? 0 : (_total / value).floor();

    return affordable > _availablePoints ? _availablePoints : affordable;
  }

  /// Vrai quand les points choisis respectent le seuil du serveur : en
  /// dessous il refuse la demande entière.
  bool get _pointsAllowed => _points == 0 || _points >= _minPoints;

  void _shiftCredit(int delta) {
    setState(() {
      final next = _credit + delta;
      _credit = next.clamp(0, _maxCredit);
    });
  }

  void _confirm() {
    final args = widget.args;
    if (args == null) return;

    // Un reste à payer bascule sur le choix du moyen, comme la
    // maquette : la répartition voyage avec, pour que le serveur
    // reçoive les trois parts dans la même demande.
    if (_remainder > 0) {
      context.push(
        AppRouter.paymentMethodPath,
        extra: PaymentArgs(
          total: Price(_remainder, currency: args.total.currency),
          phone: args.phone,
          departure: args.departure,
          seatLabel: args.seatLabel,
          walletAmount: _credit > 0 ? _credit : null,
          pointsAmount: _points > 0 ? _points : null,
        ),
      );
      return;
    }

    // Le Wallet solde le billet : on le demande au serveur, et c'est
    // lui qui débite puis confirme. L'écran ne déclare rien.
    final hold = context.read<SeatHoldBloc>().state.hold;
    if (hold == null) {
      context.go(AppRouter.holdExpiredPath);
      return;
    }

    _requested = true;

    context.read<PaymentFlowBloc>().add(
      PaymentFlowStarted(
        reservationId: hold.id,
        method: PaymentCatalog.walletCode,
        // Pas d'encaisseur : le solde ne passe par aucune passerelle.
        provider: '',
        walletAmount: _credit > 0 ? _credit : null,
        pointsAmount: _points > 0 ? _points : null,
      ),
    );
  }

  /// Ce que devient l'écran une fois le serveur décidé.
  void _onFlowChanged(BuildContext context, PaymentFlowState flow) {
    final args = widget.args;
    if (args == null) return;

    // Le paiement repris au lancement peut être celui d'une autre
    // réservation : il ne pilote pas cet écran-ci.
    if (!paymentFlowMatchesHold(context, flow, args)) return;

    // Et même pour **cette** réservation : un paiement lancé ailleurs —
    // le complément Mobile Money, la carte — ne regarde pas cet écran.
    if (!_requested) return;

    final outcome = PaymentFlowArgs(
      total: args.total,
      providerLabel: context.l10n.paymentMethodWallet,
      reference: flow.attempt?.reference,
      departure: args.departure,
      seatLabel: args.seatLabel,
    );

    if (flow.hasFailed) {
      context.push(AppRouter.paymentFailedPath, extra: outcome);
      return;
    }

    // Un débit de solde est immédiat côté serveur : il n'y a pas
    // d'opérateur à attendre. Un `pending` voudrait dire que le serveur
    // attend autre chose — on le montre plutôt que de l'ignorer.
    if (flow.isPending) {
      context.push(AppRouter.paymentPendingPath, extra: outcome);
      return;
    }

    if (flow.isConfirmed) {
      context.push(AppRouter.paymentSuccessPath, extra: outcome);
    }
  }

  @override
  Widget build(BuildContext context) {
    final args = widget.args;

    if (args == null) return const PaymentMissingArgs();

    return BlocListener<PaymentFlowBloc, PaymentFlowState>(
      listener: _onFlowChanged,
      child: _scaffold(context, args),
    );
  }

  /// Ce qui s'affiche à la place des contrôles tant qu'on ne sait pas de
  /// quoi dispose le voyageur.
  ///
  /// Un curseur et un compteur posés sur un solde inconnu laisseraient
  /// répartir une somme que le serveur refusera.
  Widget? _notReady(BuildContext context) {
    if (_loading) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 40),
        child: Center(child: KoraLoadingIndicator()),
      );
    }

    if (_account != null) return null;

    final l10n = context.l10n;

    return KoraCard(
      variant: KoraCardVariant.mist,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            _loadError ?? l10n.paymentWalletUnavailable,
            style: AppTextStyles.body(context.koraColors.textMuted),
          ),
          const SizedBox(height: AppSpacing.sm),
          KoraButton(
            label: l10n.actionRetry,
            variant: KoraButtonVariant.ghost,
            onPressed: () {
              setState(() {
                _loading = true;
                _loadError = null;
              });
              unawaited(_load());
            },
          ),
        ],
      ),
    );
  }

  Widget _scaffold(BuildContext context, PaymentArgs args) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                eyebrow: l10n.paymentWalletEyebrow,
                title: l10n.paymentWalletTitle,
              ),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    const HoldCountdown(margin: EdgeInsets.only(bottom: 11)),
                    if (_notReady(context) case final blocked?) ...[
                      blocked,
                      const SizedBox(height: AppSpacing.xl),
                    ] else ...[
                      _BalanceCard(balance: Price(_balance)),
                      SectionTitle(title: l10n.paymentWalletAmountTitle),
                      _CreditStepper(
                        label: l10n.paymentWalletAmountLabel,
                        value: _credit,
                        currency: args.total.currency,
                        maximum: _maxCredit,
                        onChanged: (value) => setState(
                          () => _credit = value.clamp(0, _maxCredit),
                        ),
                        onMinus: _credit > 0
                            ? () => _shiftCredit(-WalletPaymentPage.creditStep)
                            : null,
                        onPlus: _credit < _maxCredit
                            ? () => _shiftCredit(WalletPaymentPage.creditStep)
                            : null,
                      ),
                      if (!_cashUsable) ...[
                        const SizedBox(height: 6),
                        Text(
                          l10n.paymentWalletCashClosed,
                          style: AppTextStyles.caption(koraColors.textMuted),
                        ),
                      ],
                      const SizedBox(height: AppSpacing.md),
                      _PointsSlider(
                        points: _points,
                        maximum: _maxPoints,
                        onChanged: (value) => setState(() {
                          _points = value;
                          // Le crédit peut devenir excédentaire quand les
                          // points montent : on le ramène dans les clous.
                          if (_credit > _maxCredit) _credit = _maxCredit;
                        }),
                      ),
                      if (!_pointsAllowed) ...[
                        const SizedBox(height: 6),
                        Text(
                          l10n.paymentWalletPointsMinimum('$_minPoints'),
                          style: AppTextStyles.caption(AppColors.danger),
                        ),
                      ],
                      const SizedBox(height: AppSpacing.md),
                      KoraCard(
                        child: Column(
                          children: [
                            _BreakdownRow(
                              label: l10n.paymentWalletTicketTotal,
                              value: args.total.formatted,
                            ),
                            const Divider(height: 26),
                            _BreakdownRow(
                              label: l10n.paymentWalletCredit,
                              value:
                                  '− ${Price(_covered, currency: args.total.currency).formatted}',
                            ),
                            const Divider(height: 26),
                            _BreakdownRow(
                              label: l10n.paymentWalletRemainder,
                              value: Price(
                                _remainder,
                                currency: args.total.currency,
                              ).formatted,
                              highlight: true,
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: AppSpacing.md),
                      KoraCard(
                        variant: KoraCardVariant.mist,
                        child: Text(
                          l10n.paymentWalletNotice,
                          style: AppTextStyles.body(koraColors.textMuted),
                        ),
                      ),
                      const SizedBox(height: AppSpacing.xl),
                    ],
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                child: BlocBuilder<PaymentFlowBloc, PaymentFlowState>(
                  builder: (context, flow) => KoraButton(
                    label: l10n.paymentWalletConfirm,
                    isLoading: flow.isStarting,
                    // Rien à confirmer tant que le Wallet n'est pas lu,
                    // qu'il ne couvre rien, ou que les points sont sous
                    // le seuil du serveur.
                    onPressed:
                        _account != null &&
                            _covered > 0 &&
                            _pointsAllowed &&
                            !flow.isStarting
                        ? _confirm
                        : null,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// `.wallet-card` : le solde, en grand, sur le navy KORA.
class _BalanceCard extends StatelessWidget {
  const _BalanceCard({required this.balance});

  final Price balance;

  @override
  Widget build(BuildContext context) {
    return KoraCard(
      variant: KoraCardVariant.navy,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            context.l10n.paymentWalletBalance,
            style: TextStyle(
              color: AppColors.white.withValues(alpha: 0.7),
              fontSize: 12,
            ),
          ),
          const SizedBox(height: 12),
          Text(
            balance.formatted,
            style: const TextStyle(
              color: AppColors.white,
              fontSize: 28,
              fontWeight: FontWeight.w900,
              letterSpacing: -1,
            ),
          ),
        ],
      ),
    );
  }
}

/// `.wallet-amount-control` : le montant encadré de ses deux boutons.
/// Le montant du Wallet à engager : deux boutons, et la saisie directe.
///
/// Les pas de 1 000 F vont vite pour une somme ronde, mais pas pour
/// « 2 350 », ni pour vider un solde à 43 600 : le voyageur tapait
/// quarante fois sur `+`. Le champ se saisit donc aussi au clavier, et
/// ce qu'il porte pilote le détail du bas — total, part Wallet, reste à
/// payer — comme le font les deux boutons.
///
/// Le montant est borné à [maximum] : au-delà, la répartition ne
/// tomberait pas juste et le serveur refuserait la demande. Plutôt que
/// de laisser taper une somme qui sera rejetée, le champ s'arrête au
/// plafond.
class _CreditStepper extends StatefulWidget {
  const _CreditStepper({
    required this.label,
    required this.value,
    required this.currency,
    required this.maximum,
    required this.onChanged,
    required this.onMinus,
    required this.onPlus,
  });

  final String label;
  final int value;
  final String currency;

  /// Le plus grand montant engageable : le solde, moins ce que les
  /// points couvrent déjà.
  final int maximum;

  final ValueChanged<int> onChanged;
  final VoidCallback? onMinus;
  final VoidCallback? onPlus;

  @override
  State<_CreditStepper> createState() => _CreditStepperState();
}

class _CreditStepperState extends State<_CreditStepper> {
  late final TextEditingController _controller = TextEditingController(
    text: Price.group(widget.value),
  );
  final FocusNode _focus = FocusNode();

  @override
  void initState() {
    super.initState();
    _focus.addListener(() {
      if (_focus.hasFocus) {
        // Toucher le champ en sélectionne tout le contenu : la frappe
        // remplace le montant au lieu de s'y insérer. Sans ça, un champ
        // à « 0 » touché puis tapé « 1250 » donnait 12 500 — le curseur
        // s'était posé avant le zéro.
        _controller.selection = TextSelection(
          baseOffset: 0,
          extentOffset: _controller.text.length,
        );

        return;
      }

      // Un champ vidé puis quitté ne doit pas rester vide : il vaut
      // zéro, et c'est zéro qu'il faut lire.
      if (_controller.text.isEmpty) {
        _controller.text = Price.group(widget.value);
      }
    });
  }

  @override
  void didUpdateWidget(_CreditStepper old) {
    super.didUpdateWidget(old);

    // Les boutons, et le plafond qui descend quand les points montent,
    // changent le montant sans passer par le champ. On le resynchronise
    // — mais seulement quand il dit autre chose, sinon le curseur
    // sauterait à chaque frappe.
    if (widget.value != _read(_controller.text)) _write(widget.value);
  }

  @override
  void dispose() {
    _controller.dispose();
    _focus.dispose();
    super.dispose();
  }

  /// Le montant que porte le champ, séparateurs de milliers ignorés.
  int _read(String text) =>
      int.tryParse(text.replaceAll(RegExp('[^0-9]'), '')) ?? 0;

  void _write(int amount) {
    final text = Price.group(amount);
    _controller.value = TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(offset: text.length),
    );
  }

  void _onChanged(String text) {
    // Champ vidé : on laisse le voyageur retaper sans lui remettre un
    // « 0 » sous les doigts. Le détail du bas, lui, compte zéro.
    if (text.isEmpty) {
      widget.onChanged(0);
      return;
    }

    final amount = _read(text).clamp(0, widget.maximum);
    if (Price.group(amount) != text) _write(amount);
    if (amount != widget.value) widget.onChanged(amount);
  }

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return KoraCard(
      child: Row(
        children: [
          _StepButton(icon: Icons.remove_rounded, onTap: widget.onMinus),
          Expanded(
            child: Column(
              children: [
                Text(
                  widget.label,
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
                const SizedBox(height: 2),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    // Le champ se cale sur son contenu pour rester collé
                    // à sa devise : en pleine largeur, tout l'espace
                    // libre les séparerait.
                    //
                    // Sans fond ni bordure, il se lit comme le montant
                    // qu'il remplace — le thème KORA habille ses champs
                    // d'un fond arrondi, qui ferait ici une pastille au
                    // milieu d'une carte qui en est déjà une.
                    Flexible(
                      child: IntrinsicWidth(
                        child: TextField(
                          controller: _controller,
                          focusNode: _focus,
                          keyboardType: TextInputType.number,
                          textAlign: TextAlign.center,
                          style: AppTextStyles.titleMedium(
                            context.koraTitleColor,
                          ),
                          decoration: const InputDecoration(
                            isDense: true,
                            filled: false,
                            border: InputBorder.none,
                            enabledBorder: InputBorder.none,
                            focusedBorder: InputBorder.none,
                            contentPadding: EdgeInsets.zero,
                          ),
                          onChanged: _onChanged,
                        ),
                      ),
                    ),
                    const SizedBox(width: 4),
                    Text(
                      widget.currency,
                      style: AppTextStyles.titleMedium(context.koraTitleColor),
                    ),
                  ],
                ),
              ],
            ),
          ),
          _StepButton(icon: Icons.add_rounded, onTap: widget.onPlus),
        ],
      ),
    );
  }
}

class _StepButton extends StatelessWidget {
  const _StepButton({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final enabled = onTap != null;

    return Material(
      color: enabled ? AppColors.gold : koraColors.background,
      shape: const CircleBorder(),
      child: InkWell(
        onTap: onTap,
        customBorder: const CircleBorder(),
        child: SizedBox(
          width: 38,
          height: 38,
          child: Icon(
            icon,
            size: 20,
            color: enabled ? AppColors.kora : koraColors.textMuted,
          ),
        ),
      ),
    );
  }
}

/// `.range-field` : les points fidélité, au curseur.
class _PointsSlider extends StatelessWidget {
  const _PointsSlider({
    required this.points,
    required this.maximum,
    required this.onChanged,
  });

  final int points;

  /// Ce que le compte permet vraiment : les points détenus, plafonnés
  /// au prix du billet. Un curseur qui monterait plus haut proposerait
  /// de dépenser des points qu'on n'a pas.
  final int maximum;

  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return KoraCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              // `Expanded` des deux côtés : « Points fidélité utilisés »
              // et son compteur ne tiennent pas ensemble sur une ligne
              // étroite, et un `spaceBetween` déborderait au lieu de
              // rendre la main.
              Expanded(
                child: Text(
                  l10n.paymentWalletPoints,
                  style: AppTextStyles.body(koraColors.textMuted),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Text(
                l10n.paymentWalletPointsValue(points),
                style: AppTextStyles.titleMedium(context.koraTitleColor),
              ),
            ],
          ),
          // Aucun point à dépenser : le curseur serait un leurre.
          if (maximum <= 0)
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(
                l10n.paymentWalletPointsNone,
                style: AppTextStyles.caption(koraColors.textMuted),
              ),
            )
          else
            Slider(
              value: points.clamp(0, maximum).toDouble(),
              max: maximum.toDouble(),
              divisions: (maximum / WalletPaymentPage.pointsStep).ceil().clamp(
                1,
                maximum,
              ),
              activeColor: AppColors.gold,
              onChanged: (value) => onChanged(value.round()),
            ),
        ],
      ),
    );
  }
}

class _BreakdownRow extends StatelessWidget {
  const _BreakdownRow({
    required this.label,
    required this.value,
    this.highlight = false,
  });

  final String label;
  final String value;

  /// Le complément à régler ailleurs, en bleu : c'est la seule ligne qui
  /// engage encore le voyageur.
  final bool highlight;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Expanded(
            child: Text(label, style: AppTextStyles.body(koraColors.textMuted)),
          ),
          const SizedBox(width: AppSpacing.sm),
          Text(
            value,
            style: AppTextStyles.titleMedium(
              highlight ? AppColors.digital : context.koraTitleColor,
            ),
          ),
        ],
      ),
    );
  }
}
