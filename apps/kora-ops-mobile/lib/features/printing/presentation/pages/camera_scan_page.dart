import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_spacing.dart';

/// Vue caméra plein écran pour le scan QR en mode téléphone — voir ticket
/// "Supporter le scan par caméra téléphone en alternative au P501".
/// Se ferme et renvoie (`Navigator.pop`) le contenu décodé du premier code
/// détecté, ou `null` si l'agent annule.
///
/// Ne connaît rien du contrat de boarding : c'est [PhoneCameraBarcodeScanner]
/// (la seule classe à pousser cet écran) qui traduit son résultat en
/// [BarcodeScanner.scan] — même contrat que l'adaptateur P501, voir sa
/// docstring.
class CameraScanPage extends StatefulWidget {
  const CameraScanPage({super.key});

  @override
  State<CameraScanPage> createState() => _CameraScanPageState();
}

class _CameraScanPageState extends State<CameraScanPage> {
  final MobileScannerController _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
  );

  // Un seul code retenu : la caméra continue de scanner en tâche de fond
  // tant que l'écran n'est pas encore fermé (latence de Navigator.pop),
  // il ne faut jamais pousser un deuxième résultat entre-temps.
  bool _handled = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _onDetect(BarcodeCapture capture) {
    if (_handled) return;

    String? value;
    for (final barcode in capture.barcodes) {
      final String? raw = barcode.rawValue;
      if (raw != null && raw.isNotEmpty) {
        value = raw;
        break;
      }
    }
    if (value == null) return;

    _handled = true;
    Navigator.of(context).pop(value);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        fit: StackFit.expand,
        children: <Widget>[
          MobileScanner(controller: _controller, onDetect: _onDetect),
          const _ViewfinderOverlay(),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: Row(
                children: <Widget>[
                  _RoundIconButton(
                    icon: Icons.close,
                    onPressed: () => Navigator.of(context).pop(),
                  ),
                  const Spacer(),
                  ValueListenableBuilder<MobileScannerState>(
                    valueListenable: _controller,
                    builder: (context, state, _) => _RoundIconButton(
                      icon: state.torchState == TorchState.on
                          ? Icons.flash_on
                          : Icons.flash_off,
                      onPressed: _controller.toggleTorch,
                    ),
                  ),
                ],
              ),
            ),
          ),
          const Positioned(
            bottom: AppSpacing.xl,
            left: AppSpacing.lg,
            right: AppSpacing.lg,
            child: Text(
              'Cadrez le QR code du billet',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.white, fontSize: 15),
            ),
          ),
        ],
      ),
    );
  }
}

class _ViewfinderOverlay extends StatelessWidget {
  const _ViewfinderOverlay();

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: Center(
        child: Container(
          width: 250,
          height: 250,
          decoration: BoxDecoration(
            border: Border.all(color: AppColors.gold, width: 3),
            borderRadius: BorderRadius.circular(16),
          ),
        ),
      ),
    );
  }
}

class _RoundIconButton extends StatelessWidget {
  const _RoundIconButton({required this.icon, required this.onPressed});

  final IconData icon;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.black.withValues(alpha: 0.45),
      shape: const CircleBorder(),
      child: IconButton(
        icon: Icon(icon, color: Colors.white),
        onPressed: onPressed,
      ),
    );
  }
}
