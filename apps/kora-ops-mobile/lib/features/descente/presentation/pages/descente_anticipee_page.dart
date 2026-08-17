import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../core/widgets/app_state_placeholder.dart';
import '../../../../core/widgets/kora_card.dart';
import '../../../../core/widgets/kora_skeleton.dart';
import '../../../../core/widgets/main_app_bar.dart';
import '../../../../core/widgets/seat_map.dart';
import '../../../ops/data/mock_ops_data_source.dart';
import '../../../ops/domain/entities/depart.dart';
import '../../../ops/domain/entities/seat.dart';
import '../../../ops/presentation/cubit/selected_depart_cubit.dart';
import '../../../synchro/presentation/widgets/sync_status_banner.dart';

/// Onglet Descente anticipée : sièges libérés sur les segments suivants du
/// départ courant, sous forme de plan de bus (`SeatMap`).
class DescenteAnticipeePage extends StatefulWidget {
  const DescenteAnticipeePage({super.key});

  @override
  State<DescenteAnticipeePage> createState() => _DescenteAnticipeePageState();
}

class _DescenteAnticipeePageState extends State<DescenteAnticipeePage> {
  bool _loading = true;
  List<Seat> _seats = <Seat>[];

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _load();
  }

  Future<void> _load() async {
    final Depart? depart = context.read<SelectedDepartCubit>().state;
    if (depart == null) {
      setState(() => _loading = false);
      return;
    }
    setState(() => _loading = true);
    await Future<void>.delayed(const Duration(milliseconds: 600));
    if (!mounted) return;
    setState(() {
      _seats = MockOpsDataSource.seatsFor(depart.id);
      _loading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    final Depart? depart = context.watch<SelectedDepartCubit>().state;
    final List<Seat> liberes = _seats
        .where((Seat s) =>
            s.libereAPartirDe != null || s.statut == SeatStatus.libere)
        .toList();

    return Scaffold(
      appBar: const MainAppBar(eyebrow: 'Gestion', title: 'Descente anticipée'),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              const Align(
                  alignment: Alignment.centerLeft, child: SyncStatusBanner()),
              const SizedBox(height: AppSpacing.sm),
              Expanded(
                child: depart == null
                    ? const AppStatePlaceholder.empty(
                        message:
                            'Sélectionnez un départ à gérer pour voir les places libérées.',
                      )
                    : _loading
                        ? const SkeletonCard(height: 260)
                        : RefreshIndicator(
                            onRefresh: _load,
                            child: ListView(
                              children: <Widget>[
                                Text(depart.route,
                                    style: Theme.of(context)
                                        .textTheme
                                        .titleMedium),
                                const SizedBox(height: AppSpacing.md),
                                KoraCard(child: SeatMap(seats: _seats)),
                                const SizedBox(height: AppSpacing.lg),
                                Text(
                                  '${liberes.length} place(s) concernée(s) par une descente anticipée',
                                  style: Theme.of(context).textTheme.bodyMedium,
                                ),
                              ],
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
