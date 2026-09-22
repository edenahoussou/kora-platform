import 'package:flutter/material.dart';

/// Le pictogramme WhatsApp : la bulle verte et son combiné.
///
/// Dessiné ici plutôt que pris dans une police d'icônes : ni Material ni
/// le design system n'en portent, et une icône de messagerie générique ne
/// dit pas au voyageur quelle application va s'ouvrir. La couleur est
/// celle de la marque (`#25D366`), parce que c'est elle qui rend le
/// pictogramme reconnaissable au premier coup d'œil.
class WhatsAppGlyph extends StatelessWidget {
  const WhatsAppGlyph({super.key, this.size = 20});

  final double size;

  /// Le vert de WhatsApp.
  static const Color brand = Color(0xFF25D366);

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size,
      height: size,
      child: CustomPaint(
        painter: _BubblePainter(),
        child: Center(
          // Le combiné, légèrement incliné comme sur le logo.
          child: Transform.rotate(
            angle: 0.15,
            child: Icon(
              Icons.call_rounded,
              size: size * 0.5,
              color: Colors.white,
            ),
          ),
        ),
      ),
    );
  }
}

/// La bulle : un disque, et la pointe en bas à gauche.
class _BubblePainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = WhatsAppGlyph.brand
      ..isAntiAlias = true;
    final radius = size.width / 2;

    canvas.drawCircle(Offset(radius, radius), radius, paint);
    canvas.drawPath(
      Path()
        ..moveTo(size.width * 0.06, size.height)
        ..lineTo(size.width * 0.34, size.height * 0.72)
        ..lineTo(size.width * 0.34, size.height * 0.98)
        ..close(),
      paint,
    );
  }

  @override
  bool shouldRepaint(_BubblePainter oldDelegate) => false;
}
