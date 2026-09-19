import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../app/config/app_config.dart';
import '../../../../app/config/terminal_mode.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../core/helpers/responsive.dart';
import '../../../../core/widgets/kora_button.dart';
import '../../../../injection/injection.dart';
import '../../../auth/presentation/cubit/auth_cubit.dart';
import '../../domain/repositories/terminal_repository.dart';
import '../cubit/terminal_cubit.dart';

/// Écran affiché après la connexion tant qu'aucun terminal n'est lié à cet
/// appareil (voir `AuthGuard`, MEZ-91). Aucune route de retour : tant que
/// la liaison n'est pas faite, aucun écran métier n'est utilisable (le
/// scan, le manifeste, la sync en dépendent tous) — seule échappatoire, se
/// déconnecter pour reprendre sur un autre compte.
///
/// En mode P501 (`AppConfig.terminalMode`), l'agence communique le numéro
/// de série à saisir manuellement. En mode téléphone, il n'y a rien à
/// saisir : la liaison se fait automatiquement avec l'identifiant
/// matériel de l'appareil dès l'arrivée sur cet écran (voir
/// `DeviceInfoDataSource.phoneTerminalSerial`, affiché ici pour être
/// communiqué à l'agence tant qu'un enregistrement serveur n'existe pas)
/// — voir ticket "Supporter le scan par caméra téléphone en alternative
/// au P501".
class TerminalSetupPage extends StatefulWidget {
  const TerminalSetupPage({super.key});

  @override
  State<TerminalSetupPage> createState() => _TerminalSetupPageState();
}

class _TerminalSetupPageState extends State<TerminalSetupPage> {
  final TextEditingController _serialController = TextEditingController();
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();

  /// Mis en cache après le premier calcul — un identifiant matériel stable
  /// (voir `DeviceInfoDataSource.phoneTerminalSerial`), inutile de le
  /// recalculer à chaque `_linkPhoneIfNeeded` (ex. sur "Réessayer").
  String? _phoneSerial;

  bool get _isPhoneMode =>
      AppConfig.instance.terminalMode == TerminalMode.phone;

  @override
  void initState() {
    super.initState();
    if (_isPhoneMode) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _linkPhoneIfNeeded());
    }
  }

  @override
  void dispose() {
    _serialController.dispose();
    super.dispose();
  }

  Future<void> _linkPhoneIfNeeded() async {
    final TerminalState current = context.read<TerminalCubit>().state;
    if (current is TerminalLinking || current is TerminalLinked) return;

    final String serial =
        _phoneSerial ?? await getIt<TerminalRepository>().phoneSerialNumber();
    if (!mounted) return;
    setState(() => _phoneSerial = serial);
    await context.read<TerminalCubit>().link(serial);
  }

  Future<void> _copySerial() async {
    final String? serial = _phoneSerial;
    if (serial == null) return;
    await Clipboard.setData(ClipboardData(text: serial));
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Numéro de série copié.')),
    );
  }

  String? _validateSerial(String? value) {
    final String serial = value?.trim() ?? '';
    return serial.isEmpty ? 'Numéro de série requis.' : null;
  }

  void _submit() {
    if (!_formKey.currentState!.validate()) return;
    context.read<TerminalCubit>().link(_serialController.text.trim());
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: BoxConstraints(
              maxWidth: Responsive.value<double>(context,
                  mobile: double.infinity, tablet: 480),
            ),
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: BlocBuilder<TerminalCubit, TerminalState>(
                builder: (context, state) => _isPhoneMode
                    ? _buildPhoneMode(context, state)
                    : _buildP501Form(context, state),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildPhoneMode(BuildContext context, TerminalState state) {
    final bool isLinking = state is TerminalLinking || state is TerminalInitial;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Text('Liaison du téléphone',
            style: Theme.of(context).textTheme.headlineSmall),
        const SizedBox(height: AppSpacing.sm),
        Text(
          isLinking
              ? 'Liaison de ce téléphone en cours...'
              : 'Ce téléphone n’est pas encore enregistré comme terminal. '
                  'Communiquez le numéro de série ci-dessous à votre agence '
                  'pour qu’il soit enregistré et affecté.',
          style: Theme.of(context).textTheme.bodyMedium,
        ),
        const SizedBox(height: AppSpacing.lg),
        if (_phoneSerial != null) ...<Widget>[
          _SerialCard(serial: _phoneSerial!, onCopy: _copySerial),
          const SizedBox(height: AppSpacing.lg),
        ],
        if (isLinking)
          const Center(child: CircularProgressIndicator())
        else ...<Widget>[
          if (state case TerminalUnknown(:final message)) _ErrorBanner(message),
          if (state case TerminalNotUsable(:final message))
            _ErrorBanner(message),
          if (state case TerminalError(:final message)) _ErrorBanner(message),
          const SizedBox(height: AppSpacing.md),
          KoraButton(
            label: 'Réessayer',
            onPressed: _linkPhoneIfNeeded,
          ),
        ],
        const SizedBox(height: AppSpacing.sm),
        KoraButton(
          label: 'Se déconnecter',
          variant: KoraButtonVariant.ghost,
          onPressed:
              isLinking ? null : () => context.read<AuthCubit>().logout(),
        ),
      ],
    );
  }

  Widget _buildP501Form(BuildContext context, TerminalState state) {
    final bool isLinking = state is TerminalLinking;

    return Form(
      key: _formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Text('Terminal P501',
              style: Theme.of(context).textTheme.headlineSmall),
          const SizedBox(height: AppSpacing.sm),
          Text(
            'Saisissez le numéro de série du terminal P501 '
            'utilisé pour cette tournée — il figure au dos '
            'de l’appareil.',
            style: Theme.of(context).textTheme.bodyMedium,
          ),
          const SizedBox(height: AppSpacing.lg),
          Text('Numéro de série',
              style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: AppSpacing.sm),
          TextFormField(
            controller: _serialController,
            textCapitalization: TextCapitalization.characters,
            autofillHints: const <String>[],
            decoration: const InputDecoration(hintText: 'P501-000123'),
            validator: _validateSerial,
            enabled: !isLinking,
            onFieldSubmitted: (_) => _submit(),
          ),
          if (state case TerminalUnknown(:final message)) _ErrorBanner(message),
          if (state case TerminalNotUsable(:final message))
            _ErrorBanner(message),
          if (state case TerminalError(:final message)) _ErrorBanner(message),
          const SizedBox(height: AppSpacing.md),
          KoraButton(
            label: 'Lier ce terminal',
            isLoading: isLinking,
            onPressed: _submit,
          ),
          const SizedBox(height: AppSpacing.sm),
          KoraButton(
            label: 'Se déconnecter',
            variant: KoraButtonVariant.ghost,
            onPressed:
                isLinking ? null : () => context.read<AuthCubit>().logout(),
          ),
        ],
      ),
    );
  }
}

/// Numéro de série de ce téléphone (voir `DeviceInfoDataSource.
/// phoneTerminalSerial`) — affiché pour que l'agent puisse le communiquer
/// à son agence en vue de l'enregistrement côté serveur (`POST
/// /terminals`, `type: phone`), tant qu'aucun écran d'administration
/// (MEZ-99) ne le fait à sa place.
class _SerialCard extends StatelessWidget {
  const _SerialCard({required this.serial, required this.onCopy});

  final String serial;
  final VoidCallback onCopy;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: context.koraColors.surface,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: <Widget>[
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text('Numéro de série de ce téléphone',
                    style: Theme.of(context).textTheme.labelMedium),
                const SizedBox(height: AppSpacing.xxs),
                SelectableText(
                  serial,
                  style: Theme.of(context)
                      .textTheme
                      .titleMedium
                      ?.copyWith(fontFamily: 'monospace'),
                ),
              ],
            ),
          ),
          IconButton(
            icon: const Icon(Icons.copy_outlined),
            tooltip: 'Copier',
            onPressed: onCopy,
          ),
        ],
      ),
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner(this.message);

  final String message;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: AppSpacing.sm),
      child: Text(
        message,
        style: Theme.of(context)
            .textTheme
            .bodySmall
            ?.copyWith(color: Theme.of(context).colorScheme.error),
      ),
    );
  }
}
