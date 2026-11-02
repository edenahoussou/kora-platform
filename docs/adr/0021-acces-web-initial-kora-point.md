# Accès web initial KORA POINT

## Statut

Accepté — novembre 2026

## Contexte

Les utilisateurs d'un point autonome ou d'une organisation partenaire doivent se connecter à KORA OPS Web. L'ancien parcours OTP ne leur fournit pas un mot de passe web, et imposait de créer le membre avant de revenir dans la liste Utilisateurs pour réinitialiser son compte. Il faut remettre les premiers identifiants au même moment que la création, sans casser le parcours d'inscription des autres rôles.

## Décisions

### Conditions et allocations des organisations

Une organisation KORA POINT doit avoir une commission, un plafond global et un calendrier de reversement avant d'accueillir des Points. La commission saisie en pourcentage est convertie en points de base. Un Point rattaché hérite de la commission et du calendrier de reversement de son organisation et ne peut pas les remplacer localement. Son plafond est une allocation positive. La somme des allocations des Points non clôturés, ainsi que celles des Points clôturés dont l'encours reste à solder, ne peut pas dépasser le plafond global de l'organisation.

Une suspension ne libère pas l'allocation. La clôture la libère uniquement après solde de l'encours. Une allocation ne peut pas être inférieure à l'encours courant. Le plafond global ne peut pas passer sous les allocations réservées. Un Point ne peut changer d'organisation qu'après règlement de son encours et de ses opérations en attente ; le pays doit correspondre et le plafond disponible de l'organisation cible doit couvrir l'allocation. Le délai de grâce est un réglage global KORA, non une condition spécifique à un Point ou à une organisation.

Les marchés activés sont BJ, TG et CI, tous en XOF. Le Ghana est différé jusqu'à la prise en charge explicite de GHS. Le pays d'un Point rattaché est celui de son organisation. Les documents légaux sont gérés sur le profil de l'organisation ; un Point autonome conserve le téléversement de ses documents.

### Mot de passe provisoire web pour les trois rôles KORA POINT

Les rôles agent, superviseur et trésorier KORA POINT reçoivent un mot de passe provisoire généré par le serveur. L'OTP d'inscription n'est pas émis pour ces rôles. Les autres rôles conservent leur parcours OTP actuel.

L'e-mail est obligatoire à la création d'un agent de point autonome et de tout membre d'organisation. Si l'adresse est déjà utilisée, la création est bloquée et l'administrateur doit affecter le compte existant depuis Utilisateurs. Cette opération ne change pas le mot de passe du compte existant.

### E-mail prioritaire, SMS en repli

Le serveur envoie d'abord un e-mail détaillé avec le nom, le rôle, le point ou l'organisation, l'adresse de connexion, le mot de passe provisoire, le lien KORA OPS Web, l'échéance de 72 heures et l'obligation de changer le mot de passe à la première connexion. Si le fournisseur e-mail échoue et qu'un numéro et le service SMS sont configurés, le même contenu est tenté par SMS. Le SMS n'est jamais envoyé en parallèle ni après un e-mail accepté.

### Échéance, états et rotation

L'échéance de 72 heures commence après l'acceptation d'une livraison. Tant que l'envoi échoue, l'invitation reste `pending_delivery`; après succès elle passe à `sent`, puis à `expired` lorsque le délai est dépassé. Le changement du mot de passe provisoire la marque `used`. Un nouvel envoi individuel depuis Utilisateurs marque l'accès précédent `replaced`, génère un nouveau mot de passe et recommence le délai à l'acceptation du nouvel envoi. Une invitation n'enregistre jamais le mot de passe en clair. Le mot de passe n'est pas écrit dans les journaux; le travail asynchrone qui le transporte est chiffré.

Si les deux canaux échouent, la création du point, de l'organisation et du compte est conservée. L'accès reste en attente et peut être renvoyé depuis Utilisateurs; l'écran OPS n'affiche jamais le mot de passe.

### Comptes historiques

La migration n'envoie aucun message aux comptes historiques. Un administrateur peut émettre un accès à un compte KORA POINT individuellement depuis Utilisateurs; son mot de passe est alors remplacé et l'accès provisoire suit les mêmes règles.

## Conséquences

- Le formulaire de point autonome demande un e-mail; celui des membres d'organisation garde l'e-mail obligatoire.
- La création générique depuis Utilisateurs permet aussi de rattacher un superviseur ou un trésorier à une organisation, afin que le message d'accès nomme son périmètre.
- L'état et le canal de livraison sont consultables dans Utilisateurs, qui est également le seul écran de renvoi.
- Ce dispositif concerne l'accès web initial KORA POINT. L'activation mobile des passagers demeure distincte.
