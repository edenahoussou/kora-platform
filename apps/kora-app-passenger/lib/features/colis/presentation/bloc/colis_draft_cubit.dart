import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../auth/domain/entities/phone_country.dart';
import '../../data/datasources/colis_network_mock.dart';

/// Où en est le dossier d'envoi, du brouillon au paiement.
///
/// La distinction porte tout le ticket : un **pré-enregistrement** n'a pas
/// de référence et son prix n'est qu'indicatif ; un envoi **payé** porte
/// une référence officielle, émise après la pesée au hub. Les confondre
/// ferait croire au voyageur qu'il a une place réservée en soute.
enum ColisDraftStage {
  /// En cours de saisie dans l'app.
  draft,

  /// Envoyé au système d'escale, en attente de dépôt physique.
  preregistered,

  /// Pesé au guichet : le devis est arrêté sur le poids réel.
  weighed,

  /// Réglé : la référence officielle existe.
  paid,
}

/// Ce que contient le colis, tel que la maquette le fait déclarer.
///
/// Un choix fermé plutôt qu'un texte libre : c'est lui qui décide du
/// contrôle au guichet, et « autre » oblige à préciser dans la
/// description.
enum ColisNature { clothing, documents, electronics, other }

/// Ce qu'un règlement laisse derrière lui.
///
/// Le reçu doit pouvoir répondre, des mois plus tard : combien, par quel
/// moyen, à quelle heure, sous quel identifiant. Recalculer ces valeurs
/// à l'affichage les ferait dépendre de l'état courant du brouillon,
/// alors qu'elles sont figées au paiement.
class ColisSettlement extends Equatable {
  const ColisSettlement({
    required this.amount,
    required this.method,
    required this.transactionId,
    required this.settledAt,
  });

  /// Le montant réellement encaissé, part Wallet comprise.
  final int amount;

  /// Par quoi le voyageur a payé, tel qu'on le lui a annoncé.
  final String method;

  final String transactionId;
  final DateTime settledAt;

  @override
  List<Object?> get props => [amount, method, transactionId, settledAt];
}

/// Le brouillon d'envoi, porté d'un écran à l'autre du parcours.
class ColisDraft extends Equatable {
  const ColisDraft({
    this.stage = ColisDraftStage.draft,
    this.originHubId,
    this.destinationHubId,
    this.nature = ColisNature.documents,
    this.content = '',
    this.declaredValue = 0,
    this.declaredWeightKg = 3,
    this.senderName = '',
    this.senderPhone = '',
    this.recipientName = '',
    this.recipientPhone = '',
    this.recipientCountry = PhoneCountry.benin,
    this.certifiedWeightKg,
    this.reference,
    this.editing = false,
    this.certified = false,
    this.settlement,
  });

  final ColisDraftStage stage;

  final String? originHubId;
  final String? destinationHubId;

  /// La catégorie déclarée : elle commande l'inspection au guichet.
  final ColisNature nature;

  /// La description du contenu, telle que le guichet la contrôlera.
  final String content;

  /// La valeur annoncée par l'expéditeur, en francs.
  ///
  /// Elle ne change pas le tarif — l'assurance de base est offerte — mais
  /// elle fixe ce que KORA reconnaît en cas de litige, et le guichet la
  /// recopie sur le bordereau.
  final int declaredValue;

  /// Le poids annoncé par le voyageur, avant toute balance.
  final double declaredWeightKg;

  final String senderName;
  final String senderPhone;
  final String recipientName;
  final String recipientPhone;

  /// L'indicatif du destinataire, choisi dans la même feuille qu'à
  /// l'inscription. Un colis se retire souvent pour un proche joignable
  /// hors du Bénin : le numéro ne vaut rien sans son pays.
  final PhoneCountry recipientCountry;

  /// Le poids lu sur la balance du hub. `null` tant que rien n'est pesé —
  /// et c'est bien ce `null` qui interdit d'afficher un prix définitif.
  final double? certifiedWeightKg;

  /// `KC-AAAAMMJJ-NNNNN`, émise au paiement et pas avant.
  final String? reference;

  /// Le voyageur corrige une étape depuis le récapitulatif.
  ///
  /// Sans ce drapeau, « Modifier » renvoyait bien à l'étape, mais
  /// « Continuer » repartait vers la suivante : il fallait refaire tout
  /// le parcours pour une adresse changée.
  final bool editing;

  /// La certification sur l'honneur : aucun article interdit, et
  /// l'inspection physique acceptée.
  ///
  /// Elle n'est pas décorative. L'agent d'escale ouvre les colis au
  /// guichet, et c'est cette case qui fait du refus une conséquence
  /// annoncée plutôt qu'une mauvaise surprise.
  final bool certified;

  /// Ce que le paiement a laissé derrière lui : combien, par quoi,
  /// quand, et sous quel identifiant.
  ///
  /// Nul tant que rien n'est réglé. Le reçu en vit entièrement — un
  /// montant réaffiché de mémoire ne vaudrait rien devant un litige.
  final ColisSettlement? settlement;

  /// Le numéro du destinataire tel qu'on le dicte : indicatif compris.
  String get recipientPhoneFull => recipientPhone.trim().isEmpty
      ? ''
      : '${recipientCountry.code} ${recipientPhone.trim()}';

  ColisHub? get originHub =>
      originHubId == null ? null : colisHubById(originHubId!);
  ColisHub? get destinationHub =>
      destinationHubId == null ? null : colisHubById(destinationHubId!);

  /// Le poids qui fait foi pour le prix : la balance si elle a parlé,
  /// la déclaration sinon.
  double get billableWeightKg => certifiedWeightKg ?? declaredWeightKg;

  /// L'écart constaté au guichet, en kilogrammes. Nul avant la pesée.
  double get weightGapKg =>
      certifiedWeightKg == null ? 0 : certifiedWeightKg! - declaredWeightKg;

  bool get hasRoute => originHub != null && destinationHub != null;

  /// Le corridor composé est-il desservi ? Un hub de départ et un hub
  /// d'arrivée ne font pas une liaison (ADR 0028).
  bool get isRouteServed =>
      hasRoute &&
      originHub!.city != destinationHub!.city &&
      colisCorridorExists(originHub!.city, destinationHub!.city);

  /// Une description, si courte soit-elle.
  ///
  /// Le seuil était de dix caractères : « 2 pagnes » n'y suffisait pas,
  /// et le bouton restait gris sans dire pourquoi. Le guichet contrôle
  /// de toute façon le colis ouvert ; c'est lui qui tranche, pas un
  /// compteur de lettres.
  bool get isContentComplete => content.trim().isNotEmpty && certified;

  bool get areContactsComplete =>
      senderName.trim().isNotEmpty &&
      recipientName.trim().isNotEmpty &&
      recipientPhone.trim().length >= 8;

  /// Le transport pour le poids qui fait foi, hors scellé.
  int get transportFee => !hasRoute
      ? 0
      : colisTransportFee(
          originCity: originHub!.city,
          destinationCity: destinationHub!.city,
          weightKg: billableWeightKg,
        );

  int get total => transportFee == 0 ? 0 : transportFee + kColisSealFee;

  /// La fourchette annoncée au pré-enregistrement.
  ///
  /// Un prix exact serait un mensonge : la balance du hub n'a pas encore
  /// parlé, et une tranche de poids franchie change le tarif.
  (int, int) get indicativeRange {
    if (!hasRoute) return (0, 0);

    final low = colisTotalFee(
      originCity: originHub!.city,
      destinationCity: destinationHub!.city,
      weightKg: declaredWeightKg,
    );
    final high = colisTotalFee(
      originCity: originHub!.city,
      destinationCity: destinationHub!.city,
      weightKg: declaredWeightKg + kColisWeightStepKg,
    );

    return (low, high == low ? low + 500 : high);
  }

  ColisDraft copyWith({
    ColisDraftStage? stage,
    String? originHubId,
    String? destinationHubId,
    ColisNature? nature,
    String? content,
    int? declaredValue,
    double? declaredWeightKg,
    String? senderName,
    String? senderPhone,
    String? recipientName,
    String? recipientPhone,
    PhoneCountry? recipientCountry,
    double? certifiedWeightKg,
    String? reference,
    bool? editing,
    bool? certified,
    ColisSettlement? settlement,
  }) {
    return ColisDraft(
      stage: stage ?? this.stage,
      originHubId: originHubId ?? this.originHubId,
      destinationHubId: destinationHubId ?? this.destinationHubId,
      nature: nature ?? this.nature,
      content: content ?? this.content,
      declaredValue: declaredValue ?? this.declaredValue,
      declaredWeightKg: declaredWeightKg ?? this.declaredWeightKg,
      senderName: senderName ?? this.senderName,
      senderPhone: senderPhone ?? this.senderPhone,
      recipientName: recipientName ?? this.recipientName,
      recipientPhone: recipientPhone ?? this.recipientPhone,
      recipientCountry: recipientCountry ?? this.recipientCountry,
      certifiedWeightKg: certifiedWeightKg ?? this.certifiedWeightKg,
      reference: reference ?? this.reference,
      editing: editing ?? this.editing,
      certified: certified ?? this.certified,
      settlement: settlement ?? this.settlement,
    );
  }

  @override
  List<Object?> get props => [
    stage,
    originHubId,
    destinationHubId,
    nature,
    content,
    declaredValue,
    declaredWeightKg,
    senderName,
    senderPhone,
    recipientName,
    recipientPhone,
    recipientCountry,
    certifiedWeightKg,
    reference,
    editing,
    certified,
    settlement,
  ];
}

/// Le brouillon d'envoi, d'un écran à l'autre.
///
/// Un cubit plutôt qu'un `extra` de `go_router` : le voyageur doit pouvoir
/// revenir corriger une étape sans perdre les suivantes, et c'est un
/// critère d'acceptation du ticket.
class ColisDraftCubit extends Cubit<ColisDraft> {
  ColisDraftCubit() : super(const ColisDraft());

  /// Repart d'une feuille blanche, en gardant l'identité du déposant :
  /// c'est le compte connecté, il ne change pas d'un envoi à l'autre.
  void start({required String senderName, required String senderPhone}) =>
      emit(ColisDraft(senderName: senderName, senderPhone: senderPhone));

  void chooseOrigin(String hubId) => emit(state.copyWith(originHubId: hubId));

  void chooseDestination(String hubId) =>
      emit(state.copyWith(destinationHubId: hubId));

  /// Échange départ et arrivée — le retour est le cas le plus courant.
  void swapHubs() => emit(
    ColisDraft(
      stage: state.stage,
      originHubId: state.destinationHubId,
      destinationHubId: state.originHubId,
      nature: state.nature,
      content: state.content,
      declaredValue: state.declaredValue,
      declaredWeightKg: state.declaredWeightKg,
      senderName: state.senderName,
      senderPhone: state.senderPhone,
      recipientName: state.recipientName,
      recipientPhone: state.recipientPhone,
      recipientCountry: state.recipientCountry,
      editing: state.editing,
      certified: state.certified,
      settlement: state.settlement,
    ),
  );

  void describe(String content) => emit(state.copyWith(content: content));

  void certify(bool certified) => emit(state.copyWith(certified: certified));

  void chooseNature(ColisNature nature) => emit(state.copyWith(nature: nature));

  /// La valeur déclarée, bornée au plafond que le runbook interdit de
  /// dépasser en espèces transportées.
  void setDeclaredValue(int value) =>
      emit(state.copyWith(declaredValue: value.clamp(0, 500000)));

  /// Le poids déclaré, borné au maximum du runbook et jamais négatif.
  void setDeclaredWeight(double kg) => emit(
    state.copyWith(
      declaredWeightKg: kg.clamp(kColisWeightStepKg, kColisMaxWeightKg),
    ),
  );

  void stepWeight(int steps) =>
      setDeclaredWeight(state.declaredWeightKg + steps * kColisWeightStepKg);

  void setRecipient({String? name, String? phone}) =>
      emit(state.copyWith(recipientName: name, recipientPhone: phone));

  void setRecipientCountry(PhoneCountry country) =>
      emit(state.copyWith(recipientCountry: country));

  /// Le voyageur part corriger une étape depuis le récapitulatif ; il y
  /// reviendra dès qu'il aura validé.
  void editFromSummary() => emit(state.copyWith(editing: true));

  void doneEditing() => emit(state.copyWith(editing: false));

  void setSenderName(String name) => emit(state.copyWith(senderName: name));

  /// Le dossier part au système d'escale : toujours sans référence.
  void preregister() =>
      emit(state.copyWith(stage: ColisDraftStage.preregistered));

  /// Le guichet a pesé : le prix cesse d'être indicatif.
  void confirmWeight(double certifiedKg) => emit(
    state.copyWith(
      stage: ColisDraftStage.weighed,
      certifiedWeightKg: certifiedKg,
    ),
  );

  /// Le paiement passe, et la référence officielle naît à cet instant.
  void settle(String reference, {ColisSettlement? settlement}) => emit(
    state.copyWith(
      stage: ColisDraftStage.paid,
      reference: reference,
      settlement: settlement,
    ),
  );
}
