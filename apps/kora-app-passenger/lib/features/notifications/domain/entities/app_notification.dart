import 'package:equatable/equatable.dart';
import 'package:flutter/material.dart';

/// Ce qu'annonce une notification, et donc son pictogramme.
///
/// La maquette dessine un symbole par nature d'événement ; les regrouper
/// en catégories évite de trimballer une icône dans les données.
enum NotificationKind {
  /// Billet confirmé, réservation validée.
  ticket(Icons.confirmation_number_rounded),

  /// Rappel avant un départ.
  reminder(Icons.schedule_rounded),

  /// Retard, incident de circulation.
  delay(Icons.error_outline_rounded),

  /// Mouvement sur le Wallet.
  wallet(Icons.account_balance_wallet_rounded),

  /// Étape d'un colis.
  parcel(Icons.inventory_2_rounded),

  /// Départ annulé par l'opérateur.
  cancelled(Icons.close_rounded);

  const NotificationKind(this.icon);

  final IconData icon;
}

/// Une ligne du centre de notifications — écran `notifications` (HOME-02).
class AppNotification extends Equatable {
  const AppNotification({
    required this.id,
    required this.kind,
    required this.title,
    required this.text,
    required this.receivedAt,
    this.isUnread = false,
    this.resourceUuid,
  });

  final String id;
  final NotificationKind kind;
  final String title;
  final String text;

  /// Horodatage réel : l'écran en tire lui-même « Il y a 2 min » ou
  /// « Lun. », plutôt que de figer une formulation dans les données.
  final DateTime receivedAt;

  /// Pastille dorée à droite du titre, tant qu'elle n'est pas lue.
  final bool isUnread;

  /// La réservation dont parle la notification, quand elle en désigne une.
  ///
  /// C'est elle qui rend le toucher utile : sans cet identifiant, ouvrir
  /// une notification ne peut mener qu'à la liste des voyages, puisque
  /// rien ne dit duquel il s'agit. `null` pour les messages qui ne visent
  /// aucune réservation en particulier.
  final String? resourceUuid;

  AppNotification markRead() => AppNotification(
    id: id,
    kind: kind,
    title: title,
    text: text,
    receivedAt: receivedAt,
    resourceUuid: resourceUuid,
  );

  @override
  List<Object?> get props => [
    id,
    kind,
    title,
    text,
    receivedAt,
    isUnread,
    resourceUuid,
  ];
}
