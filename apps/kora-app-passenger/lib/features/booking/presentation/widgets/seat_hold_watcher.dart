import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/router/app_router.dart';
import '../bloc/seat_hold_bloc.dart';

/// Conduit à l'écran de fin de délai dès que le blocage expire.
///
/// Posé une fois autour du tunnel plutôt que dans chaque écran : le
/// décompte peut tomber à zéro sur le plan, sur le formulaire passagers ou
/// sur le récapitulatif, et la sortie est la même partout.
class SeatHoldWatcher extends StatelessWidget {
  const SeatHoldWatcher({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return BlocListener<SeatHoldBloc, SeatHoldState>(
      listenWhen: (previous, current) =>
          previous.status != current.status &&
          current.status == SeatHoldStatus.expired,
      listener: (context, state) => context.push(AppRouter.holdExpiredPath),
      child: child,
    );
  }
}
