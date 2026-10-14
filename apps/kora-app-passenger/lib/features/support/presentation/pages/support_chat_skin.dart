/// L'habillage KORA du widget tawk.to.
///
/// La page du chat n'est pas à nous : c'est tawk.to qui l'envoie, avec ses
/// couleurs et sa mise en page. Posée telle quelle sous l'en-tête navy de
/// l'application, elle donne l'impression de deux univers collés — un
/// fond graphite, une carte blanche, un bouton presque noir.
///
/// Ce fichier rend la page aux couleurs de KORA, et retire sa barre du
/// bas.
///
/// **Ce n'est pas le réglage principal.** La couleur d'accent du widget se
/// règle dans le tableau de bord tawk.to (Administration → Chat Widget →
/// Appearance), et ce réglage-là vaut aussi pour le site OPS. Ce qui suit
/// ne couvre que ce que le tableau de bord ne propose pas.
///
/// ## Ce que coûte le masquage de la barre du bas
///
/// Elle portait deux onglets, « Accueil » et « Messages ». Le second
/// donnait accès aux conversations passées : un voyageur qui a écrit à
/// l'assistance ne peut plus, depuis cet écran, revenir lire la réponse
/// de l'agent. Il la recevra par e-mail, puisque c'est l'adresse qu'il
/// laisse dans le formulaire.
///
/// Elle portait aussi la mention « Powered by tawk.to ». La retirer est
/// une option payante chez eux : la masquer par ce biais sort du cadre de
/// leur offre gratuite. C'est une décision produit, prise en connaissance
/// de cause.
///
/// **Si tawk.to renomme ses classes**, les règles de couleur cessent
/// simplement de s'appliquer et la page reprend son apparence d'origine.
/// Le masquage, lui, échouerait dans l'autre sens : la barre
/// réapparaîtrait. D'où la recherche de la mention par son texte plutôt
/// que par une classe — un texte se renomme moins souvent qu'un nom de
/// classe généré.
library;

/// Navy KORA — `AppColors.kora`.
const _navy = '#001D4E';

/// Navy profond des cartes — `AppColors.surfaceDark`.
const _surface = '#00153E';

/// Or KORA — `AppColors.gold`.
const _gold = '#F6B000';

const _skin =
    '''
:root, html, body,
.tawk-home-bg, .tawk-main-panel, .tawk-chat-panel { background: $_navy !important; }

.tawk-form-input {
  background: $_surface !important;
  border: 1px solid rgba(255,255,255,0.10) !important;
  border-radius: 18px !important;
  box-shadow: none !important;
}
.tawk-header-text { color: rgba(255,255,255,0.72) !important; }

/* Le libellé flottant se peint en blanc dès que le champ est rempli —
   c'est ainsi que tawk.to « découpe » la bordure du champ pour y loger
   le texte. Sur fond clair ça ne se voit pas ; sur le nôtre, ça laissait
   un rectangle blanc avec le libellé invisible dedans. Il garde donc son
   rôle, dans la couleur de la carte. */
.tawk-form-label, .tawk-form-label.tawk-active {
  background: $_surface !important;
  color: rgba(255,255,255,0.72) !important;
}

.tawk-input, .tawk-textarea,
.tawk-form-input input, .tawk-form-input textarea {
  background: rgba(255,255,255,0.06) !important;
  border: 1px solid rgba(255,255,255,0.14) !important;
  border-radius: 14px !important;
  color: #FFFFFF !important;
}
.tawk-input::placeholder,
.tawk-textarea::placeholder { color: rgba(255,255,255,0.45) !important; }

.tawk-button-primary, button[type="submit"], .tawk-form-input button {
  background: $_gold !important;
  color: $_navy !important;
  border: none !important;
  border-radius: 999px !important;
  font-weight: 800 !important;
}
.tawk-button-primary svg, button[type="submit"] svg { fill: $_navy !important; }

/* La barre du bas disparaît : voir l'en-tête du fichier pour ce que ça
   coûte. `.tawk-footer` contient la barre de navigation, les deux
   partent donc ensemble. */
.tawk-footer, .tawk-bottom-navbar { display: none !important; }
''';

/// Le script qui pose [_skin] dans la page, et l'y maintient.
///
/// Deux difficultés, d'où ces précautions :
///
/// - **Le formulaire vit dans une `iframe`**, créée par tawk.to après le
///   chargement du document. Elle est de même origine, donc accessible,
///   mais elle n'existe pas encore quand `onPageFinished` se déclenche :
///   le script attend, comme pour l'identité du visiteur.
/// - **tawk.to redessine ses vues** en naviguant entre l'accueil et la
///   conversation. Un observateur remet la feuille en place si elle
///   disparaît, plutôt que de la poser une fois et d'espérer.
final supportChatSkinScript =
    '''
(function () {
  var css = ${_dartStringToJs(_skin)};
  var attempts = 0;

  function paint(doc) {
    if (!doc || !doc.head) { return false; }
    var tag = doc.getElementById('kora-skin');
    if (!tag) {
      tag = doc.createElement('style');
      tag.id = 'kora-skin';
      doc.head.appendChild(tag);
    }
    if (tag.textContent !== css) { tag.textContent = css; }
    return true;
  }

  function documents() {
    var found = [document];
    var frames = document.querySelectorAll('iframe');
    for (var i = 0; i < frames.length; i++) {
      try {
        if (frames[i].contentDocument) { found.push(frames[i].contentDocument); }
      } catch (e) { /* iframe d'une autre origine : rien à y faire */ }
    }
    return found;
  }

  /* La mention « Powered by tawk.to » n'arrive pas avec la page : elle
     s'ajoute plus tard, et sans classe stable. On la cherche donc par son
     texte, sur les éléments qui n'ont pas d'enfant — c'est-à-dire celui
     qui porte réellement le texte, pas ses conteneurs. */
  function hideBranding(doc) {
    if (!doc || !doc.body) { return; }
    var all = doc.body.querySelectorAll('*');
    for (var i = 0; i < all.length; i++) {
      var node = all[i];
      if (node.children.length) { continue; }
      var text = (node.textContent || '').toLowerCase();
      if (text.indexOf('tawk.to') === -1) { continue; }
      var target = node.parentElement || node;
      if (target.style.display !== 'none') { target.style.display = 'none'; }
    }
  }

  function apply() {
    var docs = documents();
    var painted = false;
    for (var i = 0; i < docs.length; i++) {
      if (paint(docs[i])) { painted = true; }
      hideBranding(docs[i]);
    }
    return painted && docs.length > 1;
  }

  function observe() {
    if (!window.MutationObserver) { return; }
    var docs = documents();
    for (var i = 0; i < docs.length; i++) {
      if (!docs[i].body) { continue; }
      new MutationObserver(function () { apply(); })
        .observe(docs[i].body, { childList: true, subtree: true });
    }
  }

  if (apply()) { observe(); return; }

  var timer = setInterval(function () {
    if (apply() || ++attempts > 40) {
      clearInterval(timer);
      observe();
    }
  }, 250);
})();
''';

/// Rend [value] sous forme de littéral JavaScript.
///
/// La feuille de style contient des apostrophes et des sauts de ligne :
/// l'insérer telle quelle dans le script casserait la chaîne.
String _dartStringToJs(String value) {
  final escaped = value
      .replaceAll(r'\', r'\\')
      .replaceAll("'", r"\'")
      .replaceAll('\n', r'\n');

  return "'$escaped'";
}
