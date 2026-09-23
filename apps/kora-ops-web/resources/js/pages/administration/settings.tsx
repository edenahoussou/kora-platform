import { Head, router, useForm } from '@inertiajs/react';
import {
    ChevronDown,
    Clock3,
    Coins,
    FlaskConical,
    Globe2,
    IdCard,
    Languages,
    LockKeyhole,
    MessageCircle,
    Phone,
} from 'lucide-react';
import { useEffect, useMemo } from 'react';
import {
    SettingsField,
    SettingsHelpRail,
    SettingsPageHeader,
    SettingsProbeStatus,
    SettingsSaveBar,
    SettingsSecretField,
    SettingsPhoneField,
    SettingsSection,
    SettingsSelectField,
    SettingsTabs,
    SettingsToggle,
} from '@/components/administration/settings/settings-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import administration from '@/routes/administration';
import type {
    ConnectionProbeResult,
    OperationsSettingsValues,
    SettingsGroup,
    SettingsLineOption,
    SettingsPageProps,
} from '@/types/settings';

type FormScalar = string | number | boolean | null;

type FormValues = {
    values: Record<string, FormScalar | Record<string, number>>;
    clearSecrets: string[];
    confirmed: boolean;
};

type IntegrationFieldDefinition = {
    key: string;
    label: string;
    hint: string;
    type?: 'text' | 'number' | 'url';
    suffix?: string;
    secret?: boolean;
};

const sensitiveKeys = new Set([
    'emecfToken',
    'fedapaySecretKey',
    'fedapayWebhookSecret',
    'mtnMomoSubscriptionKey',
    'mtnMomoUserId',
    'mtnMomoApiKey',
    'mtnMomoWebhookSecret',
    'mapGoogleApiKey',
    'genukaToken',
    'genukaConnectionId',
    'wahaToken',
    'googleOauthClientSecret',
    'facebookOauthClientSecret',
    'tiktokOauthClientSecret',
]);

const integrationFields: Record<string, IntegrationFieldDefinition[]> = {
    emecf: [
        {
            key: 'emecfToken',
            label: 'Jeton e-MECeF',
            hint: 'Jeton d’accès fourni par la DGI.',
            secret: true,
        },
        {
            key: 'emecfDefaultIfu',
            label: 'IFU par défaut',
            hint: 'Identifiant fiscal utilisé pour les factures.',
            type: 'text',
        },
        {
            key: 'emecfTestInvoiceUrl',
            label: 'URL facturation test',
            hint: 'Endpoint de création en environnement test.',
            type: 'url',
        },
        {
            key: 'emecfTestInfoUrl',
            label: 'URL informations test',
            hint: 'Endpoint de lecture utilisé par le probe.',
            type: 'url',
        },
        {
            key: 'emecfProductionInvoiceUrl',
            label: 'URL facturation production',
            hint: 'Endpoint de création en production.',
            type: 'url',
        },
        {
            key: 'emecfProductionInfoUrl',
            label: 'URL informations production',
            hint: 'Endpoint de lecture en production.',
            type: 'url',
        },
        {
            key: 'emecfHttpTimeout',
            label: 'Délai HTTP',
            hint: 'Temps maximum d’une requête.',
            type: 'number',
            suffix: 's',
        },
        {
            key: 'emecfConnectTimeout',
            label: 'Délai de connexion',
            hint: 'Temps maximum pour établir la connexion.',
            type: 'number',
            suffix: 's',
        },
        {
            key: 'emecfRetry',
            label: 'Tentatives réseau',
            hint: 'Nombre de nouvelles tentatives.',
            type: 'number',
        },
        {
            key: 'emecfRetryDelay',
            label: 'Délai entre tentatives',
            hint: 'Pause avant une nouvelle tentative.',
            type: 'number',
            suffix: 'ms',
        },
        {
            key: 'emecfLogLevel',
            label: 'Niveau de journalisation',
            hint: 'Niveau minimum conservé par le module.',
        },
        {
            key: 'emecfInvoiceSubmissionTimeout',
            label: 'Délai d’envoi facture',
            hint: 'Temps maximum pour soumettre une facture.',
            type: 'number',
            suffix: 's',
        },
        {
            key: 'emecfFinalizationTimeout',
            label: 'Délai de finalisation',
            hint: 'Temps maximum pour confirmer une facture.',
            type: 'number',
            suffix: 's',
        },
        {
            key: 'emecfStatusCheckTimeout',
            label: 'Délai de statut',
            hint: 'Temps maximum pour lire un statut.',
            type: 'number',
            suffix: 's',
        },
        {
            key: 'emecfInfoRequestTimeout',
            label: 'Délai informations',
            hint: 'Temps maximum pour la lecture d’informations.',
            type: 'number',
            suffix: 's',
        },
        {
            key: 'emecfMaxRetryAttempts',
            label: 'Tentatives métier',
            hint: 'Nombre maximum de reprises fiscales.',
            type: 'number',
        },
        {
            key: 'emecfRetryBackoffMultiplier',
            label: 'Multiplicateur backoff',
            hint: 'Accélération progressive entre reprises.',
            type: 'number',
        },
    ],
    emecfAccount: [
        {
            key: 'emecfShopName',
            label: 'Nom de la structure',
            hint: 'Raison sociale affichée sur les factures normalisées.',
        },
        {
            key: 'emecfNim',
            label: 'NIM',
            hint: 'Identifiant du MECeF attribué par la DGI.',
        },
        {
            key: 'emecfAccountStatus',
            label: 'Statut du compte',
            hint: 'Statut déclaré par la DGI (Actif, Suspendu…).',
        },
        {
            key: 'emecfAddress1',
            label: 'Adresse 1',
            hint: 'Adresse principale enregistrée à la DGI.',
        },
        {
            key: 'emecfAddress2',
            label: 'Adresse 2',
            hint: 'Complément d’adresse éventuel.',
        },
        {
            key: 'emecfAddress3',
            label: 'Ville',
            hint: 'Ville d’enregistrement du MECeF.',
        },
        {
            key: 'emecfContact1',
            label: 'Téléphone',
            hint: 'Contact téléphonique déclaré à la DGI.',
        },
        {
            key: 'emecfContact2',
            label: 'Email',
            hint: 'Adresse email déclarée à la DGI.',
        },
        {
            key: 'emecfContact3',
            label: 'Contact secondaire',
            hint: 'Contact additionnel éventuel.',
        },
        {
            key: 'emecfApiVersion',
            label: 'Version API',
            hint: 'Version de l’API e-MECeF exposée au compte.',
        },
        {
            key: 'emecfTokenValidUntil',
            label: 'Jeton valide jusqu’au',
            hint: 'Date de fin de validité du jeton déclarée par la DGI.',
        },
    ],
    fedapay: [
        {
            key: 'fedapaySecretKey',
            label: 'Clé secrète',
            hint: 'Clé privée FedaPay, jamais affichée en clair.',
            secret: true,
        },
        {
            key: 'fedapayCallbackUrl',
            label: 'URL de callback',
            hint: 'URL de retour après paiement.',
            type: 'url',
        },
        {
            key: 'fedapayWebhookSecret',
            label: 'Secret webhook',
            hint: 'Secret de vérification des webhooks.',
            secret: true,
        },
        {
            key: 'fedapayCurrency',
            label: 'Devise',
            hint: 'Devise transmise à FedaPay.',
        },
        {
            key: 'fedapayDefaultPhoneCountry',
            label: 'Pays téléphone',
            hint: 'Code pays par défaut pour les numéros.',
        },
        {
            key: 'fedapayMtnMomoMode',
            label: 'Mode MTN MoMo',
            hint: 'Identifiant du canal FedaPay.',
        },
        {
            key: 'fedapayMoovFloozMode',
            label: 'Mode Moov Flooz',
            hint: 'Identifiant du canal FedaPay.',
        },
        {
            key: 'fedapayCeltiisCashMode',
            label: 'Mode Celtiis Cash',
            hint: 'Identifiant du canal FedaPay.',
        },
    ],
    mtnMomo: [
        {
            key: 'mtnMomoSubscriptionKey',
            label: 'Clé d’abonnement',
            hint: 'Clé API MTN MoMo.',
            secret: true,
        },
        {
            key: 'mtnMomoUserId',
            label: 'Identifiant API',
            hint: 'Identifiant OAuth MTN MoMo.',
            secret: true,
        },
        {
            key: 'mtnMomoApiKey',
            label: 'Clé API',
            hint: 'Clé utilisée pour obtenir le token.',
            secret: true,
        },
        {
            key: 'mtnMomoCallbackUrl',
            label: 'URL de callback',
            hint: 'URL de retour opérateur.',
            type: 'url',
        },
        {
            key: 'mtnMomoTargetEnvironment',
            label: 'Environnement cible',
            hint: 'Cible opérateur, par exemple mtnbenin.',
        },
        {
            key: 'mtnMomoSandboxCurrency',
            label: 'Devise sandbox',
            hint: 'Devise de l’environnement de test.',
        },
        {
            key: 'mtnMomoProductionCurrency',
            label: 'Devise production',
            hint: 'Devise de l’environnement réel.',
        },
        {
            key: 'mtnMomoWebhookSecret',
            label: 'Secret webhook',
            hint: 'Secret des notifications entrantes.',
            secret: true,
        },
    ],
    map: [
        {
            key: 'mapGoogleApiKey',
            label: 'Clé Google Maps',
            hint: 'Nécessaire uniquement si Google est sélectionné.',
            secret: true,
        },
        {
            key: 'mapOsmTileUrl',
            label: 'URL des tuiles OSM',
            hint: 'Modèle URL des tuiles OpenStreetMap.',
            type: 'url',
        },
        {
            key: 'mapOsmAttribution',
            label: 'Attribution OSM',
            hint: 'Mention affichée sur la carte.',
        },
    ],
    genuka: [
        {
            key: 'genukaUrl',
            label: 'URL Genuka',
            hint: 'Endpoint de l’intégration WhatsApp.',
            type: 'url',
        },
        {
            key: 'genukaToken',
            label: 'Jeton Genuka',
            hint: 'Jeton d’authentification Genuka.',
            secret: true,
        },
        {
            key: 'genukaConnectionId',
            label: 'Identifiant de connexion',
            hint: 'Connexion Genuka utilisée par KORA.',
            secret: true,
        },
    ],
    waha: [
        {
            key: 'wahaUrl',
            label: 'URL WAHA',
            hint: 'Endpoint du serveur WAHA.',
            type: 'url',
        },
        {
            key: 'wahaToken',
            label: 'Jeton WAHA',
            hint: 'Jeton d’authentification WAHA.',
            secret: true,
        },
        {
            key: 'wahaSession',
            label: 'Session WhatsApp',
            hint: 'Nom de la session WAHA active.',
        },
        {
            key: 'wahaNewContactDelaySeconds',
            label: 'Délai nouveau contact',
            hint: 'Pause avant le premier message.',
            type: 'number',
            suffix: 's',
        },
    ],
    googleOauth: [
        {
            key: 'googleOauthClientId',
            label: 'Client ID Google',
            hint: 'Identifiant OAuth Google.',
        },
        {
            key: 'googleOauthClientSecret',
            label: 'Client secret Google',
            hint: 'Secret OAuth Google.',
            secret: true,
        },
        {
            key: 'googleOauthRedirect',
            label: 'URL de redirection Google',
            hint: 'Callback OAuth déclaré chez Google.',
        },
    ],
    facebookOauth: [
        {
            key: 'facebookOauthClientId',
            label: 'Client ID Facebook',
            hint: 'Identifiant OAuth Facebook.',
        },
        {
            key: 'facebookOauthClientSecret',
            label: 'Client secret Facebook',
            hint: 'Secret OAuth Facebook.',
            secret: true,
        },
        {
            key: 'facebookOauthRedirect',
            label: 'URL de redirection Facebook',
            hint: 'Callback OAuth déclaré chez Facebook.',
        },
    ],
    tiktokOauth: [
        {
            key: 'tiktokOauthClientId',
            label: 'Client ID TikTok',
            hint: 'Identifiant OAuth TikTok.',
        },
        {
            key: 'tiktokOauthClientSecret',
            label: 'Client secret TikTok',
            hint: 'Secret OAuth TikTok.',
            secret: true,
        },
        {
            key: 'tiktokOauthRedirect',
            label: 'URL de redirection TikTok',
            hint: 'Callback OAuth déclaré chez TikTok.',
        },
    ],
};

const titles: Record<SettingsGroup, { title: string; description: string }> = {
    general: {
        title: 'Paramètres généraux',
        description:
            'Définissez l’identité visible de KORA, son contexte régional et les canaux de support proposés aux équipes.',
    },
    operations: {
        title: 'Paramètres d’exploitation',
        description:
            'Ajustez les règles de réservation, de départ, de recherche, de session OPS et d’alerte sans modifier le code.',
    },
    integrations: {
        title: 'Paramètres d’intégration',
        description:
            'Centralisez les fournisseurs connectés au MVP KORA BUS, leurs environnements et l’état de leurs credentials.',
    },
};

function initialFormValues(
    values: Record<string, unknown>,
    sensitiveProperties: string[],
): FormValues['values'] {
    const sensitive = new Set(sensitiveProperties);

    return Object.fromEntries(
        Object.entries(values).map(([key, value]) => [
            key,
            sensitive.has(key)
                ? ''
                : (value as FormScalar | Record<string, number>),
        ]),
    );
}

export default function SettingsPage({
    group,
    snapshot,
    permissions,
    lineOptions = [],
    probeResults = {},
}: SettingsPageProps) {
    const initialValues = useMemo(
        () =>
            initialFormValues(
                snapshot.values as Record<string, unknown>,
                snapshot.sensitiveProperties,
            ),
        [snapshot],
    );
    const form = useForm<FormValues>({
        values: initialValues,
        clearSecrets: [],
        confirmed: false,
    });

    useEffect(() => {
        const handleBeforeUnload = (event: BeforeUnloadEvent) => {
            if (!form.isDirty) {
                return;
            }

            event.preventDefault();
            event.returnValue = '';
        };

        window.addEventListener('beforeunload', handleBeforeUnload);

        return () =>
            window.removeEventListener('beforeunload', handleBeforeUnload);
    }, [form.isDirty]);

    const value = (
        key: string,
    ): FormScalar | Record<string, number> | undefined => form.data.values[key];
    const error = (key: string): string | undefined => {
        const errors = form.errors as Record<string, string | undefined>;

        return errors[key] ?? errors[`values.${key}`];
    };
    const setValue = (
        key: string,
        nextValue: FormScalar | Record<string, number>,
    ) => {
        form.setData('values', { ...form.data.values, [key]: nextValue });

        if (sensitiveKeys.has(key) && form.data.clearSecrets.includes(key)) {
            form.setData(
                'clearSecrets',
                form.data.clearSecrets.filter((secret) => secret !== key),
            );
        }
    };
    const setNumber = (key: string, nextValue: string) => {
        setValue(key, nextValue === '' ? '' : Number(nextValue));
    };
    const clearSecret = (key: string) => {
        if (
            !window.confirm(
                'Supprimer définitivement ce secret de la configuration ?',
            )
        ) {
            return;
        }

        form.setData('clearSecrets', [...form.data.clearSecrets, key]);
    };
    const reset = () => {
        form.setData('values', initialValues);
        form.setData('clearSecrets', []);
        form.setData('confirmed', false);
        form.clearErrors();
    };
    const updateUrl = {
        general: administration.settings.general.update.url(),
        operations: administration.settings.operations.update.url(),
        integrations: administration.settings.integrations.update.url(),
    }[group];
    const initialValue = (
        key: string,
    ): FormScalar | Record<string, number> | undefined => initialValues[key];
    const needsConfirmation =
        group === 'operations' &&
        [
            'saleCutoffMinutes',
            'saleCutoffOverrides',
            'paymentTimeoutMinutes',
            'staleDepartureHours',
            'maxDepartureDelayMinutes',
            'opsSessionTtlMinutes',
            'opsSessionMaxLifetimeMinutes',
            'opsSessionTouchIntervalSeconds',
        ].some(
            (key) =>
                JSON.stringify(value(key)) !==
                JSON.stringify(initialValue(key)),
        );
    const submit = () => {
        if (!form.isDirty || !permissions.canManage) {
            return;
        }

        let confirmed = false;

        if (needsConfirmation) {
            confirmed = window.confirm(
                'Ces paramètres peuvent modifier les ventes, les départs ou les sessions en cours. Enregistrer les changements ?',
            );

            if (!confirmed) {
                return;
            }
        }

        form.setData('confirmed', confirmed);
        form.patch(updateUrl, {
            preserveScroll: true,
            onSuccess: () => form.setData('confirmed', false),
        });
    };

    const field = (definition: IntegrationFieldDefinition) => {
        if (definition.secret) {
            return (
                <SettingsSecretField
                    key={definition.key}
                    label={definition.label}
                    hint={definition.hint}
                    value={String(value(definition.key) ?? '')}
                    configured={Boolean(
                        (snapshot.values as Record<string, unknown>)[
                        definition.key
                        ],
                    )}
                    disabled={!permissions.canManage}
                    cleared={form.data.clearSecrets.includes(definition.key)}
                    error={error(definition.key)}
                    onChange={(nextValue) =>
                        setValue(definition.key, nextValue)
                    }
                    onClear={() => clearSecret(definition.key)}
                />
            );
        }

        return (
            <SettingsField
                key={definition.key}
                label={definition.label}
                hint={definition.hint}
                value={
                    value(definition.key) as string | number | null | undefined
                }
                type={definition.type}
                suffix={definition.suffix}
                disabled={!permissions.canManage}
                error={error(definition.key)}
                onChange={(nextValue) =>
                    definition.type === 'number'
                        ? setNumber(definition.key, nextValue)
                        : setValue(definition.key, nextValue)
                }
            />
        );
    };

    const selectField = (
        key: string,
        label: string,
        hint: string,
        options: Array<{ value: string; label: string }>,
    ) => (
        <SettingsSelectField
            key={key}
            label={label}
            hint={hint}
            value={String(value(key) ?? '')}
            options={options}
            disabled={!permissions.canManage}
            error={error(key)}
            onChange={(nextValue) => setValue(key, nextValue)}
        />
    );

    const renderGeneral = () => {
        return (
            <div className="grid gap-4">
                <SettingsSection
                    title="Identité de l’application"
                    description="Cette identité apparaît dans les écrans OPS et les notifications générées par KORA."
                    className="cash-reveal cash-reveal-delay-2"
                >
                    <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_16rem]">
                        <SettingsField
                            label="Nom d’affichage"
                            hint="Nom visible par les équipes et les passagers."
                            icon={IdCard}
                            value={value('displayName') as string}
                            disabled={!permissions.canManage}
                            error={error('displayName')}
                            onChange={(nextValue) =>
                                setValue('displayName', nextValue)
                            }
                        />
                        <div className="flex flex-col justify-center rounded-xl border border-dashed border-border bg-background/50 px-4 py-3">
                            <span className="text-[9px] font-black tracking-[0.14em] text-muted-foreground uppercase">
                                Aperçu
                            </span>
                            <div className="mt-2.5 flex items-center gap-2.5">
                                <span className="grid size-7 shrink-0 place-items-center rounded-lg bg-kora text-[10px] font-black text-gold">
                                    K
                                </span>
                                <span className="truncate text-sm font-extrabold tracking-[-0.02em] text-foreground">
                                    {String(value('displayName') || 'KORA')}
                                </span>
                            </div>
                        </div>
                    </div>
                </SettingsSection>
                <SettingsSection
                    title="Langue et contexte régional"
                    description="Le fuseau est appliqué aux départs, aux dates métier et aux journaux runtime."
                    className="cash-reveal cash-reveal-delay-3"
                >
                    <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                        <SettingsField
                            label="Locale"
                            hint="Locale principale, par exemple fr."
                            icon={Languages}
                            value={value('locale') as string}
                            disabled={!permissions.canManage}
                            error={error('locale')}
                            onChange={(nextValue) =>
                                setValue('locale', nextValue)
                            }
                        />
                        <SettingsField
                            label="Locale de secours"
                            hint="Utilisée lorsqu’une traduction manque."
                            icon={Globe2}
                            value={value('fallbackLocale') as string}
                            disabled={!permissions.canManage}
                            error={error('fallbackLocale')}
                            onChange={(nextValue) =>
                                setValue('fallbackLocale', nextValue)
                            }
                        />
                        <SettingsField
                            label="Fuseau horaire"
                            hint="Valeur initiale : Africa/Porto-Novo."
                            icon={Clock3}
                            value={value('timezone') as string}
                            disabled={!permissions.canManage}
                            error={error('timezone')}
                            onChange={(nextValue) =>
                                setValue('timezone', nextValue)
                            }
                        />
                        <SettingsField
                            label="Devise"
                            hint="Devise par défaut des ventes et paiements."
                            icon={Coins}
                            value={value('currency') as string}
                            disabled={!permissions.canManage}
                            error={error('currency')}
                            onChange={(nextValue) =>
                                setValue('currency', nextValue.toUpperCase())
                            }
                        />
                    </div>
                </SettingsSection>
                <SettingsSection
                    title="Canaux de support"
                    description="Ces numéros sont réutilisables par les parcours de réservation et d’assistance."
                    className="cash-reveal cash-reveal-delay-4"
                >
                    <div className="grid gap-5 md:grid-cols-2">
                        <SettingsPhoneField
                            label="Téléphone support appel"
                            hint="Format international recommandé."
                            icon={Phone}
                            value={value('supportCallPhone') as string | null}
                            disabled={!permissions.canManage}
                            error={error('supportCallPhone')}
                            onChange={(nextValue) =>
                                setValue('supportCallPhone', nextValue)
                            }
                        />
                        <SettingsPhoneField
                            label="Téléphone support WhatsApp"
                            hint="Numéro utilisé pour les échanges WhatsApp."
                            icon={MessageCircle}
                            value={
                                value('supportWhatsappPhone') as string | null
                            }
                            disabled={!permissions.canManage}
                            error={error('supportWhatsappPhone')}
                            onChange={(nextValue) =>
                                setValue('supportWhatsappPhone', nextValue)
                            }
                        />
                    </div>
                </SettingsSection>
            </div>
        );
    };

    const renderOperations = () => {
        const operations = snapshot.values as OperationsSettingsValues;
        const overrides =
            (value('saleCutoffOverrides') as Record<string, number>) ?? {};

        return (
            <div className="cash-reveal cash-reveal-delay-2 grid gap-4">
                <SettingsSection
                    title="Réservation et vente"
                    description="Ces valeurs encadrent la durée de vie des devis et des paiements avant expiration."
                >
                    <div className="grid gap-5 md:grid-cols-3">
                        <SettingsField
                            label="Validité d’un devis"
                            hint="Temps laissé au passager pour confirmer."
                            value={value('quoteTtlMinutes') as number}
                            type="number"
                            suffix="min"
                            disabled={!permissions.canManage}
                            error={error('quoteTtlMinutes')}
                            onChange={(nextValue) =>
                                setNumber('quoteTtlMinutes', nextValue)
                            }
                        />
                        <SettingsField
                            label="Coupure avant départ"
                            hint="Délai global avant fermeture de la vente."
                            value={value('saleCutoffMinutes') as number}
                            type="number"
                            suffix="min"
                            disabled={!permissions.canManage}
                            error={error('saleCutoffMinutes')}
                            onChange={(nextValue) =>
                                setNumber('saleCutoffMinutes', nextValue)
                            }
                        />
                        <SettingsField
                            label="Expiration d’un paiement"
                            hint="Durée maximum d’une tentative de paiement."
                            value={value('paymentTimeoutMinutes') as number}
                            type="number"
                            suffix="min"
                            disabled={!permissions.canManage}
                            error={error('paymentTimeoutMinutes')}
                            onChange={(nextValue) =>
                                setNumber('paymentTimeoutMinutes', nextValue)
                            }
                        />
                    </div>
                </SettingsSection>
                <SettingsSection
                    title="Départs et correspondances"
                    description="Les marges permettent au moteur de recherche et au contrôle de sellability de rester alignés avec l’exploitation."
                >
                    <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                        <SettingsField
                            label="Seuil des départs périmés"
                            hint="Après ce délai, un départ est considéré obsolète."
                            value={value('staleDepartureHours') as number}
                            type="number"
                            suffix="h"
                            disabled={!permissions.canManage}
                            error={error('staleDepartureHours')}
                            onChange={(nextValue) =>
                                setNumber('staleDepartureHours', nextValue)
                            }
                        />
                        <SettingsField
                            label="Retard maximal autorisé"
                            hint="Retard maximum avant refus métier."
                            value={value('maxDepartureDelayMinutes') as number}
                            type="number"
                            suffix="min"
                            disabled={!permissions.canManage}
                            error={error('maxDepartureDelayMinutes')}
                            onChange={(nextValue) =>
                                setNumber('maxDepartureDelayMinutes', nextValue)
                            }
                        />
                        <SettingsField
                            label="Marge correspondance"
                            hint="Marge appliquée entre deux tronçons."
                            value={value('connectionMarginMinutes') as number}
                            type="number"
                            suffix="min"
                            disabled={!permissions.canManage}
                            error={error('connectionMarginMinutes')}
                            onChange={(nextValue) =>
                                setNumber('connectionMarginMinutes', nextValue)
                            }
                        />
                        <SettingsField
                            label="Marge aller-retour"
                            hint="Marge additionnelle pour un retour."
                            value={value('roundTripMarginMinutes') as number}
                            type="number"
                            suffix="min"
                            disabled={!permissions.canManage}
                            error={error('roundTripMarginMinutes')}
                            onChange={(nextValue) =>
                                setNumber('roundTripMarginMinutes', nextValue)
                            }
                        />
                    </div>
                </SettingsSection>
                <SettingsSection
                    title="Itinéraires et recherche"
                    description="Ces limites protègent la lisibilité des résultats et le temps de calcul du moteur de recherche."
                >
                    <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                        <SettingsField
                            label="Nombre maximum de tronçons"
                            hint="Limite d’une proposition d’itinéraire."
                            value={value('maxLegs') as number}
                            type="number"
                            suffix="tronçons"
                            disabled={!permissions.canManage}
                            error={error('maxLegs')}
                            onChange={(nextValue) =>
                                setNumber('maxLegs', nextValue)
                            }
                        />
                        <SettingsField
                            label="Résultats maximum"
                            hint="Nombre de résultats retournés."
                            value={value('maxResults') as number}
                            type="number"
                            suffix="résultats"
                            disabled={!permissions.canManage}
                            error={error('maxResults')}
                            onChange={(nextValue) =>
                                setNumber('maxResults', nextValue)
                            }
                        />
                        <SettingsField
                            label="Fenêtre de recherche"
                            hint="Nombre de jours recherchés par défaut."
                            value={value('defaultSearchDays') as number}
                            type="number"
                            suffix="jours"
                            disabled={!permissions.canManage}
                            error={error('defaultSearchDays')}
                            onChange={(nextValue) =>
                                setNumber('defaultSearchDays', nextValue)
                            }
                        />
                        <SettingsField
                            label="Attente maximum"
                            hint="Durée tolérée pour une correspondance."
                            value={value('maxWaitHours') as number}
                            type="number"
                            suffix="h"
                            disabled={!permissions.canManage}
                            error={error('maxWaitHours')}
                            onChange={(nextValue) =>
                                setNumber('maxWaitHours', nextValue)
                            }
                        />
                    </div>
                </SettingsSection>
                <SettingsSection
                    title="Sessions OPS Mobile et P501"
                    description="Les sessions existantes ne sont pas capturées : les nouveaux paramètres s’appliquent aux prochains renouvellements."
                >
                    <div className="grid gap-5 md:grid-cols-3">
                        <SettingsField
                            label="Durée de session"
                            hint="TTL d’une session active."
                            value={value('opsSessionTtlMinutes') as number}
                            type="number"
                            suffix="min"
                            disabled={!permissions.canManage}
                            error={error('opsSessionTtlMinutes')}
                            onChange={(nextValue) =>
                                setNumber('opsSessionTtlMinutes', nextValue)
                            }
                        />
                        <SettingsField
                            label="Durée de vie maximale"
                            hint="Plafond absolu de la session."
                            value={
                                value('opsSessionMaxLifetimeMinutes') as number
                            }
                            type="number"
                            suffix="min"
                            disabled={!permissions.canManage}
                            error={error('opsSessionMaxLifetimeMinutes')}
                            onChange={(nextValue) =>
                                setNumber(
                                    'opsSessionMaxLifetimeMinutes',
                                    nextValue,
                                )
                            }
                        />
                        <SettingsField
                            label="Mise à jour d’activité"
                            hint="Intervalle de touch de session."
                            value={
                                value(
                                    'opsSessionTouchIntervalSeconds',
                                ) as number
                            }
                            type="number"
                            suffix="s"
                            disabled={!permissions.canManage}
                            error={error('opsSessionTouchIntervalSeconds')}
                            onChange={(nextValue) =>
                                setNumber(
                                    'opsSessionTouchIntervalSeconds',
                                    nextValue,
                                )
                            }
                        />
                    </div>
                </SettingsSection>
                <SettingsSection
                    title="Alertes opérationnelles"
                    description="Contrôlez l’envoi des alertes et leur anti-duplication sans modifier les statuts internes."
                >
                    <div className="grid gap-5 md:grid-cols-3">
                        <SettingsToggle
                            label="Alertes email actives"
                            hint="Autoriser l’envoi des alertes opérationnelles."
                            checked={Boolean(value('notificationsMailEnabled'))}
                            disabled={!permissions.canManage}
                            onChange={(checked) =>
                                setValue('notificationsMailEnabled', checked)
                            }
                        />
                        <SettingsField
                            label="Tentatives email"
                            hint="Nombre de reprises d’envoi."
                            value={value('notificationsMailTries') as number}
                            type="number"
                            suffix="essais"
                            disabled={!permissions.canManage}
                            error={error('notificationsMailTries')}
                            onChange={(nextValue) =>
                                setNumber('notificationsMailTries', nextValue)
                            }
                        />
                        <SettingsField
                            label="Anti-duplication manifeste"
                            hint="Délai avant une nouvelle alerte identique."
                            value={
                                value('manifestStaleDebounceSeconds') as number
                            }
                            type="number"
                            suffix="s"
                            disabled={!permissions.canManage}
                            error={error('manifestStaleDebounceSeconds')}
                            onChange={(nextValue) =>
                                setNumber(
                                    'manifestStaleDebounceSeconds',
                                    nextValue,
                                )
                            }
                        />
                    </div>
                </SettingsSection>
                <details
                    className="group rounded-2xl border border-border bg-card shadow-sm"
                    open={false}
                >
                    <summary className="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 text-sm font-extrabold text-foreground">
                        Coupures avancées par ligne
                        <ChevronDown className="size-4 transition-transform group-open:rotate-180" />
                    </summary>
                    <div className="border-t border-border/70 p-5">
                        <p className="mb-4 max-w-2xl text-xs leading-5 text-muted-foreground">
                            Une ligne sans override reprend le délai global. Les
                            lignes inactives ne sont pas proposées.
                        </p>
                        <div className="grid gap-3">
                            {lineOptions.length === 0 && (
                                <p className="text-xs text-muted-foreground">
                                    Aucune ligne active à personnaliser.
                                </p>
                            )}
                            {lineOptions.map((line: SettingsLineOption) => (
                                <div
                                    key={line.uuid}
                                    className="grid gap-3 rounded-xl border border-border/70 bg-background/40 p-3 md:grid-cols-[minmax(0,1fr)_10rem] md:items-center"
                                >
                                    <div>
                                        <p className="text-sm font-semibold text-foreground">
                                            {line.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {line.code}
                                        </p>
                                    </div>
                                    <Input
                                        type="number"
                                        min={0}
                                        value={overrides[line.uuid] ?? ''}
                                        disabled={!permissions.canManage}
                                        placeholder={`${String(operations.saleCutoffMinutes)} min global`}
                                        onChange={(event) => {
                                            const nextOverrides = {
                                                ...overrides,
                                            };

                                            if (event.target.value === '') {
                                                delete nextOverrides[line.uuid];
                                            } else {
                                                nextOverrides[line.uuid] =
                                                    Number(event.target.value);
                                            }

                                            setValue(
                                                'saleCutoffOverrides',
                                                nextOverrides,
                                            );
                                        }}
                                        className="h-10 rounded-xl bg-background/70"
                                        aria-label={`Coupure pour ${line.name}`}
                                    />
                                </div>
                            ))}
                        </div>
                    </div>
                </details>
            </div>
        );
    };

    const renderIntegrationCard = (
        key: string,
        title: string,
        description: string,
        options?: {
            enabledKey?: string;
            environmentKey?: string;
            environmentLabel?: (
                value: FormScalar | Record<string, number> | undefined,
            ) => string;
            probeProvider?: string;
            result?: ConnectionProbeResult;
        },
    ) => (
        <SettingsSection
            title={title}
            description={description}
            className="h-fit"
            actions={
                <>
                    {options?.environmentKey && (
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-muted px-2.5 py-1 text-[9px] font-extrabold tracking-[0.08em] text-muted-foreground uppercase">
                            Env ·{' '}
                            {options.environmentLabel
                                ? options.environmentLabel(
                                    value(options.environmentKey),
                                )
                                : String(value(options.environmentKey))}
                        </span>
                    )}
                    {options?.probeProvider && permissions.canManage && (
                        <Button
                            type="button"
                            variant="kora-secondary"
                            size="kora"
                            onClick={() =>
                                router.post(
                                    administration.settings.integrations.test.url(
                                        {
                                            provider:
                                                options.probeProvider as string,
                                        },
                                    ),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <FlaskConical className="size-3.5" /> Tester la
                            connexion
                        </Button>
                    )}
                </>
            }
        >
            <div className="grid gap-4">
                {options?.enabledKey && (
                    <SettingsToggle
                        label="Intégration active"
                        hint="Conserver la configuration même lorsqu’elle est désactivée."
                        checked={Boolean(value(options.enabledKey))}
                        disabled={!permissions.canManage}
                        onChange={(checked) =>
                            setValue(options.enabledKey as string, checked)
                        }
                    />
                )}
                {options?.probeProvider && (
                    <div>
                        <SettingsProbeStatus result={options.result} />
                    </div>
                )}
                <div className="grid gap-5 md:grid-cols-2">
                    {(integrationFields[key] ?? []).map(field)}
                </div>
            </div>
        </SettingsSection>
    );

    const renderIntegrations = () => (
        <div className="cash-reveal cash-reveal-delay-2 grid gap-4">
            <div className="grid gap-4 xl:grid-cols-2">
                {renderIntegrationCard(
                    'emecf',
                    'e-MECeF',
                    'Fiscalisation des factures normalisées auprès de la DGI.',
                    {
                        environmentKey: 'emecfTestMode',
                        environmentLabel: (currentValue) =>
                            currentValue ? 'test' : 'production',
                        probeProvider: 'emecf',
                        result: probeResults.emecf,
                    },
                )}
                <div className="grid content-start gap-4">
                    <SettingsSection
                        title="Compte e-MECeF (DGI)"
                        description="Identité du contribuable synchronisée depuis l’API e-MECeF — affichée sur les factures normalisées. Les valeurs restent modifiables."
                    >
                        <div className="grid gap-5 md:grid-cols-2">
                            {(integrationFields.emecfAccount ?? []).map(field)}
                        </div>
                    </SettingsSection>
                    <SettingsSection
                        title="e-MECeF — comportement"
                        description="Les paramètres de conservation et de journalisation restent administrables sans exposer les réponses fiscales."
                    >
                        <div className="grid gap-5 md:grid-cols-2">
                            <SettingsToggle
                                label="Mode test"
                                hint="Utiliser l’environnement de test e-MECeF."
                                checked={Boolean(value('emecfTestMode'))}
                                disabled={!permissions.canManage}
                                onChange={(checked) =>
                                    setValue('emecfTestMode', checked)
                                }
                            />
                            <SettingsToggle
                                label="Sauvegarder les factures"
                                hint="Conserver un snapshot local après la réponse fournisseur."
                                checked={Boolean(value('emecfSaveInvoices'))}
                                disabled={!permissions.canManage}
                                onChange={(checked) =>
                                    setValue('emecfSaveInvoices', checked)
                                }
                            />
                            <SettingsToggle
                                label="Sauvegarder les logs"
                                hint="Conserver les journaux techniques selon le niveau choisi."
                                checked={Boolean(value('emecfSaveLogs'))}
                                disabled={!permissions.canManage}
                                onChange={(checked) =>
                                    setValue('emecfSaveLogs', checked)
                                }
                            />
                        </div>
                    </SettingsSection>
                </div>
            </div>
            <div className="grid gap-4 xl:grid-cols-2">
                {renderIntegrationCard(
                    'fedapay',
                    'FedaPay',
                    'Paiement en ligne et Mobile Money via FedaPay.',
                    {
                        enabledKey: 'fedapayEnabled',
                        environmentKey: 'fedapayEnvironment',
                        probeProvider: 'fedapay',
                        result: probeResults.fedapay,
                    },
                )}
                {renderIntegrationCard(
                    'mtnMomo',
                    'MTN MoMo',
                    'Paiement direct via le protocole Collection MTN MoMo.',
                    {
                        enabledKey: 'mtnMomoEnabled',
                        environmentKey: 'mtnMomoEnvironment',
                        probeProvider: 'mtn_momo',
                        result: probeResults.mtn_momo,
                    },
                )}
            </div>
            <div className="grid gap-4 xl:grid-cols-2">
                <SettingsSection
                    title="Cartographie"
                    description="Choisissez le fournisseur cartographique runtime et conservez les paramètres non secrets associés."
                >
                    <div className="grid gap-5 md:grid-cols-2">
                        {selectField(
                            'mapProvider',
                            'Fournisseur actif',
                            'Le choix openstreetmap conserve la configuration gratuite par défaut.',
                            [
                                {
                                    value: 'openstreetmap',
                                    label: 'OpenStreetMap',
                                },
                                { value: 'google', label: 'Google Maps' },
                            ],
                        )}
                        {integrationFields.map.slice(0, 1).map(field)}
                    </div>
                    <div className="grid gap-5 md:grid-cols-2">
                        {integrationFields.map.slice(1).map(field)}
                    </div>
                </SettingsSection>
                {renderIntegrationCard(
                    'genuka',
                    'Genuka',
                    'Envoi WhatsApp via la connexion Genuka configurée.',
                    { enabledKey: undefined },
                )}
            </div>
            <div className="grid gap-4 xl:grid-cols-2">
                {renderIntegrationCard(
                    'waha',
                    'WAHA',
                    'Session WhatsApp interne pour les notifications opérationnelles.',
                    {},
                )}
                <SettingsSection
                    title="OAuth social"
                    description="Les fournisseurs OAuth sont séparés pour éviter les confusions de redirect et de credentials."
                >
                    <div className="grid gap-5">
                        <details
                            className="group rounded-xl border border-border/70 p-4"
                            open
                        >
                            <summary className="flex cursor-pointer list-none items-center justify-between text-sm font-bold">
                                Google{' '}
                                <ChevronDown className="size-4 transition-transform group-open:rotate-180" />
                            </summary>
                            <div className="mt-4 grid gap-5">
                                {integrationFields.googleOauth.map(field)}
                            </div>
                        </details>
                        <details className="group rounded-xl border border-border/70 p-4">
                            <summary className="flex cursor-pointer list-none items-center justify-between text-sm font-bold">
                                Facebook{' '}
                                <ChevronDown className="size-4 transition-transform group-open:rotate-180" />
                            </summary>
                            <div className="mt-4 grid gap-5">
                                {integrationFields.facebookOauth.map(field)}
                            </div>
                        </details>
                        <details className="group rounded-xl border border-border/70 p-4">
                            <summary className="flex cursor-pointer list-none items-center justify-between text-sm font-bold">
                                TikTok{' '}
                                <ChevronDown className="size-4 transition-transform group-open:rotate-180" />
                            </summary>
                            <div className="mt-4 grid gap-5">
                                {integrationFields.tiktokOauth.map(field)}
                            </div>
                        </details>
                    </div>
                </SettingsSection>
            </div>
            <p className="flex items-center gap-2 text-xs leading-5 text-muted-foreground">
                <LockKeyhole className="size-4 shrink-0" /> Les secrets restent
                chiffrés en base et ne sont jamais renvoyés en clair.
            </p>
        </div>
    );

    return (
        <>
            <Head title={titles[group].title} />
            <main className="cash-page w-full max-w-full overflow-x-hidden">
                <SettingsPageHeader
                    group={group}
                    title={titles[group].title}
                    description={titles[group].description}
                />
                <div className="cash-reveal cash-reveal-delay-1 mb-5">
                    <SettingsTabs active={group} />
                </div>
                <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-start">
                    <div className="min-w-0">
                        {group === 'general' && renderGeneral()}
                        {group === 'operations' && renderOperations()}
                        {group === 'integrations' && renderIntegrations()}
                        <div className="mt-5">
                            <SettingsSaveBar
                                dirty={form.isDirty}
                                processing={form.processing}
                                canManage={permissions.canManage}
                                onReset={reset}
                                onSave={submit}
                            />
                        </div>
                    </div>
                    <SettingsHelpRail
                        tips={
                            group === 'general'
                                ? [
                                    {
                                        title: 'Nom et notifications',
                                        text: 'Le nom d’affichage est repris dans les messages et les documents générés par KORA.',
                                    },
                                    {
                                        title: 'Fuseau de référence',
                                        text: 'Conservez Africa/Porto-Novo pour garder les départs et les journaux alignés sur l’exploitation béninoise.',
                                    },
                                ]
                                : group === 'operations'
                                    ? [
                                        {
                                            title: 'Confirmez les impacts',
                                            text: 'Les délais de vente et de session peuvent modifier immédiatement les parcours en cours.',
                                        },
                                        {
                                            title: 'Préférez les overrides',
                                            text: 'Utilisez un override de ligne lorsque la règle globale ne décrit pas une exploitation particulière.',
                                        },
                                    ]
                                    : [
                                        {
                                            title: 'Testez la configuration enregistrée',
                                            text: 'Un probe utilise uniquement les valeurs déjà sauvegardées, jamais les champs encore en édition.',
                                        },
                                        {
                                            title: 'Secrets masqués',
                                            text: 'Laisser un secret vide le conserve. Une suppression exige une action explicite et auditée.',
                                        },
                                    ]
                        }
                    />
                </div>
            </main>
        </>
    );
}
