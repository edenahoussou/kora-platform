import type { ParcelListItem } from '@/components/parcels/types';
import type {
    FinalizedParcel,
    ParcelAgencyPaymentAccount,
    ParcelHub,
    ParcelItinerary,
    ParcelMobileMoneyNetwork,
    ParcelQuoteBreakdown,
} from './types';

export interface ApiStop {
    uuid: string;
    name: string;
    city: string;
    country: string | null;
    stop_order: number;
    position: number;
    arrival_at: string | null;
    departure_at: string | null;
    timezone: string;
    is_origin: boolean;
    is_destination: boolean;
}

export interface ApiDeparture {
    uuid: string;
    line_code: string | null;
    line_name: string | null;
    departure_at: string;
    status: string;
    capacity_grams: number | null;
    reserved_grams: number;
    available_grams: number | null;
    stops: ApiStop[];
}

export interface ApiHub {
    uuid: string;
    enabled: boolean;
    supports_weighing: boolean;
    kind: string;
    agency_uuid: string | null;
    kora_point_uuid: string | null;
    stop_uuid: string | null;
    name: string | null;
    city: string | null;
    country: string | null;
    address: string | null;
    archived_at: string | null;
    deposit_allowed: boolean;
}

export interface ParcelConfiguration {
    currency: string;
    hubs: ApiHub[];
    corridors: Array<{
        uuid: string;
        origin_hub_uuid: string;
        origin_hub_kind: string;
        destination_hub_uuid: string;
        enabled: boolean;
    }>;
    options: {
        stops: Array<{
            uuid: string;
            name: string;
            city: string;
            country: string;
            stop_order: number;
            line_uuid: string | null;
            line_code: string | null;
            line_name: string | null;
        }>;
    };
    counter: {
        agency_uuid: string | null;
        agency_name: string | null;
        is_partner: boolean;
        hub_uuid: string | null;
    };
}

export interface ApiQuote {
    weight_grams: number;
    currency: string;
    base_amount: number;
    step_amount: number;
    step_grams: number;
    extra_steps: number;
    tiers: Array<{
        label?: string;
        from?: number;
        to?: number;
        amount: number;
    }>;
    total: number;
    net: number;
    tax: number;
    tax_rate: string;
    estimated: boolean;
}

export interface ParcelPaymentOptions {
    agency: { uuid: string; name: string } | null;
    hub: { uuid: string; kind: string; name: string | null };
    currency: string;
    is_partner: boolean;
    cash_session: {
        uuid: string;
        opened_at: string | null;
        cash_register: string | null;
    } | null;
    methods: Array<{
        code: 'cash' | 'mobile_money';
        label: string;
        available: boolean;
        accounts?: Array<{
            uuid: string;
            network: string;
            label: string | null;
        }>;
    }>;
}

export interface ParcelApiRecord {
    uuid: string;
    reference: string | null;
    status: string;
    sender_name: string;
    recipient_name: string;
    contents: string;
    weight_grams: number;
    quote: {
        uuid: string;
        total: number;
        currency: string;
        net: number;
        tax: number;
    } | null;
    can_label: boolean;
    can_pay: boolean;
    can_receipt: boolean;
}

export type ApiValidationErrors = Record<string, string[]>;

export class ApiError extends Error {
    constructor(
        message: string,
        public status: number,
        public errors: ApiValidationErrors = {},
    ) {
        super(message);
    }
}

const FIELD_LABELS: Record<string, string> = {
    deposit_hub_uuid: 'Lieu de dépôt',
    destination_hub_uuid: 'Destination',
    weight_grams: 'Poids',
    sender_name: 'Nom de l’expéditeur',
    sender_phone: 'Téléphone de l’expéditeur',
    recipient_name: 'Nom du destinataire',
    recipient_phone: 'Téléphone du destinataire',
    contents: 'Contenu déclaré',
    cash_session: 'Caisse',
    amount: 'Montant',
    method: 'Moyen de paiement',
    cash_received_amount: 'Montant reçu',
    agency_payment_account_uuid: 'Compte agence crédité',
    provider_reference: 'Référence Mobile Money',
    mobile_money_network: 'Réseau Mobile Money',
    idempotency_key: 'Référence de tentative',
};

const MESSAGE_TRANSLATIONS: Record<string, string> = {
    'The amount received is insufficient.': 'Le montant reçu est insuffisant.',
    'Open your cash register before collecting a payment.':
        'Votre caisse est fermée. Ouvrez-la avant d’encaisser.',
    'The amount must match the latest weighed quote.':
        'Le montant doit correspondre au dernier devis pesé.',
    'This parcel quote can no longer be collected.':
        'Ce devis colis ne peut plus être encaissé.',
    'A payment is already in progress.':
        'Un paiement est déjà en cours pour ce colis.',
    'This payment key has already been used.':
        'Cette tentative de paiement a déjà été utilisée.',
    'The selected payment provider is not available in this country.':
        'Le moyen de paiement choisi n’est pas disponible dans ce pays.',
    'The given data was invalid.':
        'Certaines informations sont invalides. Vérifiez les champs signalés.',
    'The parcel has no deposit hub.':
        'Ce colis n’a pas de guichet de dépôt exploitable.',
    'The parcel has no collectible quote.':
        'Ce colis n’a pas de devis encaissable.',
    'The open cash register does not belong to the parcel deposit agency.':
        'La caisse ouverte n’appartient pas à l’agence de dépôt de ce colis.',
    'Unauthenticated.':
        'Votre session a expiré. Reconnectez-vous pour continuer.',
    'Choose a Mobile Money network and enter the provider reference.':
        'Choisissez un réseau Mobile Money et saisissez la référence du transfert.',
    'The selected Mobile Money account is not active for this agency.':
        'Le compte Mobile Money sélectionné n’est pas actif pour cette agence.',
    'This Mobile Money reference has already been recorded.':
        'Cette référence Mobile Money a déjà été enregistrée.',
    'This cash register has no open ledger for the selected payment method.':
        'La caisse ouverte n’a pas de journal disponible pour ce moyen de paiement.',
    'Corridor has no line serving both stops in the required order.':
        'Aucun car ne relie ces deux arrêts dans cet ordre. Choisissez une autre destination ou un autre départ.',
    'No tariff grid is published for this deposit location kind.':
        'Aucune grille tarifaire n’est publiée pour ce type de guichet.',
    'The parcel deposit location kind is incomplete.':
        'Le type de guichet de dépôt est incomplet.',
};

function translateMessage(message: string): string {
    if (MESSAGE_TRANSLATIONS[message]) {
        return MESSAGE_TRANSLATIONS[message];
    }

    if (/The .+ field is required\./.test(message)) {
        return 'Ce champ est obligatoire.';
    }

    if (/The .+ field must be a valid phone number\./.test(message)) {
        return 'Numéro de téléphone invalide.';
    }

    if (/The .+ field must be a valid email address\./.test(message)) {
        return 'Adresse e-mail invalide.';
    }

    if (/The .+ field must be a string\./.test(message)) {
        return 'Ce champ doit être du texte.';
    }

    if (/The .+ field must be an integer\./.test(message)) {
        return 'Ce champ doit être un nombre entier.';
    }

    if (/The .+ field must be at least (.+)\./.test(message)) {
        return 'La valeur saisie est trop petite.';
    }

    if (/The .+ field may not be greater than (.+)\./.test(message)) {
        return 'La valeur saisie est trop grande.';
    }

    if (/The selected .+ is invalid\./.test(message)) {
        return 'La valeur sélectionnée est invalide.';
    }

    if (/The .+ has already been taken\./.test(message)) {
        return 'Cette valeur est déjà utilisée.';
    }

    return message;
}

export function describeApiError(error: unknown): string {
    if (!(error instanceof ApiError)) {
        return error instanceof Error
            ? error.message
            : 'Une erreur inattendue est survenue.';
    }

    const field = Object.keys(error.errors)[0];

    if (field) {
        const label = FIELD_LABELS[field] ?? field;
        const detail = translateMessage(
            error.errors[field]?.[0] ?? error.message,
        );

        return `${label} : ${detail}`;
    }

    if (error.status === 401) {
        return 'Votre session a expiré. Reconnectez-vous pour continuer.';
    }

    if (error.status === 403) {
        return 'Votre compte n’a pas les droits requis pour cette opération.';
    }

    if (error.status === 419) {
        return 'Votre session a expiré. Rechargez la page puis réessayez.';
    }

    return translateMessage(error.message);
}

async function request<T>(url: string, init: RequestInit = {}): Promise<T> {
    const xsrf = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf) } : {}),
            ...init.headers,
        },
        ...init,
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        const errors = (payload.errors ?? {}) as ApiValidationErrors;
        const firstError = Object.values(errors)[0]?.[0];

        throw new ApiError(
            firstError ??
                payload.message ??
                `La requête a échoué (${response.status}).`,
            response.status,
            errors,
        );
    }

    return payload as T;
}

export async function fetchConfiguration(): Promise<ParcelConfiguration> {
    const payload = await request<{ data: ParcelConfiguration }>(
        '/parcels/data/configuration',
    );

    return payload.data;
}

export async function fetchRouting(
    originHubUuid: string,
    destinationHubUuid: string,
): Promise<ApiDeparture[]> {
    const query = new URLSearchParams({
        origin_hub_uuid: originHubUuid,
        destination_hub_uuid: destinationHubUuid,
    });
    const payload = await request<{ data: { departures: ApiDeparture[] } }>(
        `/parcels/data/routing/departures?${query.toString()}`,
    );

    return payload.data.departures;
}

export async function fetchQuote(input: {
    deposit_hub_uuid: string;
    destination_hub_uuid: string;
    weight_grams: number;
}): Promise<ApiQuote> {
    const payload = await request<{ data: ApiQuote }>('/parcels/data/quote', {
        method: 'POST',
        body: JSON.stringify(input),
    });

    return payload.data;
}

export async function createParcel(input: {
    deposit_hub_uuid: string;
    destination_hub_uuid: string;
    weight_grams: number;
    sender_name: string;
    sender_phone: string;
    recipient_name: string;
    recipient_phone: string;
    contents: string;
    idempotency_key: string;
}): Promise<ParcelApiRecord> {
    const payload = await request<{ data: ParcelApiRecord }>('/parcels/data', {
        method: 'POST',
        body: JSON.stringify(input),
    });

    return payload.data;
}

export async function inspectParcel(
    parcelUuid: string,
    weightGrams: number,
): Promise<ParcelApiRecord> {
    const payload = await request<{ data: ParcelApiRecord }>(
        `/parcels/data/${parcelUuid}/inspect`,
        {
            method: 'POST',
            body: JSON.stringify({
                category: 'allowed',
                weight_grams: weightGrams,
            }),
        },
    );

    return payload.data;
}

export async function fetchPaymentOptions(
    parcelUuid: string,
): Promise<ParcelPaymentOptions> {
    const payload = await request<{ data: ParcelPaymentOptions }>(
        `/parcels/data/${parcelUuid}/payment-options`,
    );

    return payload.data;
}

export async function payParcel(
    parcelUuid: string,
    input: {
        idempotency_key: string;
        method: 'cash' | 'mobile_money';
        amount: number;
        mobile_money_network?: string;
        agency_payment_account_uuid?: string | null;
        provider_reference?: string | null;
        cash_received_amount?: number;
        payer_name?: string | null;
        payer_phone?: string | null;
    },
): Promise<{ data: Record<string, unknown> }> {
    return request<{ data: Record<string, unknown> }>(
        `/parcels/data/${parcelUuid}/pay`,
        {
            method: 'POST',
            body: JSON.stringify(input),
        },
    );
}

export async function fetchParcel(
    parcelUuid: string,
): Promise<ParcelApiRecord> {
    const payload = await request<{ data: ParcelApiRecord }>(
        `/parcels/data/${parcelUuid}`,
    );

    return payload.data;
}

export async function searchParcel(
    reference: string,
): Promise<ParcelApiRecord | null> {
    const query = new URLSearchParams({ reference });
    const payload = await request<{
        data: ParcelApiRecord[];
    }>(`/parcels/data?${query.toString()}`);

    return payload.data[0] ?? null;
}

export function apiHubToParcelHub(hub: ApiHub): ParcelHub {
    return {
        uuid: hub.uuid,
        code: `HUB-${hub.uuid.slice(0, 4).toUpperCase()}`,
        name: hub.name ?? hub.address ?? hub.city ?? 'Hub colis',
        city: hub.city ?? '',
        region: hub.country ?? '',
        type: hub.kind === 'partner_point' ? 'partner_point' : 'agency',
        address: hub.address ?? '',
        phone: '',
        isFrequent: hub.kind === 'agency',
    };
}

function dayOffset(iso: string): 0 | 1 {
    const departure = new Date(iso);
    const today = new Date();
    const start = new Date(
        today.getFullYear(),
        today.getMonth(),
        today.getDate(),
    );
    const day = new Date(
        departure.getFullYear(),
        departure.getMonth(),
        departure.getDate(),
    );

    return Math.floor((day.getTime() - start.getTime()) / 86_400_000) >= 1
        ? 1
        : 0;
}

export function departureToItinerary(
    departure: ApiDeparture,
    destinationUuid: string,
): ParcelItinerary {
    const first = departure.stops[0];
    const last = departure.stops[departure.stops.length - 1];
    const origin = departure.stops.find((stop) => stop.is_origin) ?? first;
    const destination =
        departure.stops.find((stop) => stop.is_destination) ?? last;
    const availableKg =
        departure.available_grams === null
            ? 9999
            : Math.floor(departure.available_grams / 1000);
    const offset = dayOffset(departure.departure_at);

    return {
        uuid: departure.uuid,
        dayOffset: offset,
        dayLabel: offset === 0 ? "Aujourd'hui" : 'Demain',
        originCity: origin?.city ?? '',
        destinationHubUuid: destinationUuid,
        destinationCity: destination?.city ?? '',
        legs: [
            {
                uuid: departure.uuid,
                departureRef: departure.line_code ?? 'BUS',
                lineCode: departure.line_code ?? 'BUS',
                originCity: origin?.city ?? '',
                originHubName: origin?.name ?? '',
                destinationCity: destination?.city ?? '',
                destinationHubName: destination?.name ?? '',
                departureTime: clockLabel(
                    origin?.departure_at ?? departure.departure_at,
                    origin?.timezone,
                ),
                arrivalTime: clockLabel(
                    destination?.arrival_at ?? departure.departure_at,
                    destination?.timezone,
                ),
                busRegistration: departure.line_name ?? '',
                busModel: '',
                driverName: '',
                availableCargoKg: availableKg,
                totalCargoKg:
                    departure.capacity_grams === null
                        ? 9999
                        : Math.floor(departure.capacity_grams / 1000),
                status:
                    departure.status === 'boarding'
                        ? 'boarding'
                        : departure.status === 'ready'
                          ? 'ready'
                          : 'scheduled',
            },
        ],
        stops: departure.stops,
        totalDurationLabel: durationLabel(origin, destination),
        minAvailableCargoKg: availableKg,
        isNextDeparture: false,
        tariffMultiplier: 1,
    };
}

function clockLabel(iso: string, timezone?: string): string {
    return new Intl.DateTimeFormat('fr-FR', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
        ...(timezone ? { timeZone: timezone } : {}),
    }).format(new Date(iso));
}

function durationLabel(
    origin: ApiStop | undefined,
    destination: ApiStop | undefined,
): string {
    const start = origin?.departure_at;
    const end = destination?.arrival_at;

    if (!start || !end) {
        return '';
    }

    const minutes = Math.max(
        0,
        Math.round(
            (new Date(end).getTime() - new Date(start).getTime()) / 60000,
        ),
    );

    if (minutes < 60) {
        return `${minutes} min`;
    }

    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    return rest === 0 ? `${hours} h` : `${hours} h ${rest}`;
}

export function quoteToBreakdown(quote: ApiQuote): ParcelQuoteBreakdown {
    return {
        basePrice: quote.base_amount,
        chargeableWeight: quote.weight_grams / 1000,
        weightSurcharge: quote.step_amount * quote.extra_steps,
        transferFee: 0,
        subtotalHt: quote.net,
        vat18: quote.tax,
        totalTtc: quote.total,
    };
}

export function paymentAccounts(
    options: ParcelPaymentOptions,
): ParcelAgencyPaymentAccount[] {
    return (
        options.methods.find((method) => method.code === 'mobile_money')
            ?.accounts ?? []
    ).map((account) => ({
        uuid: account.uuid,
        label: account.label ?? account.network,
        network: account.network as ParcelMobileMoneyNetwork,
        accountIdentifier: account.label ?? '',
    }));
}

export function buildFinalizedParcel(
    parcel: ParcelApiRecord,
    draft: FinalizedParcel['draft'],
    quote: ParcelQuoteBreakdown,
    agentName: string,
    agentSite: string,
): FinalizedParcel {
    return {
        parcelUuid: parcel.uuid,
        trackingNumber: parcel.reference ?? '',
        issuedAt: new Date().toLocaleString('fr-FR', {
            dateStyle: 'medium',
            timeStyle: 'short',
        }),
        draft,
        quote,
        receiptNumber: parcel.reference
            ? `CA-${parcel.reference.split('-').at(-1)}`
            : '',
        agentName,
        agentSite,
        labelUrl: `/parcels/${parcel.uuid}/label`,
        receiptUrl: `/parcels/data/${parcel.uuid}/receipt`,
        receiptParcel: parcel as unknown as ParcelListItem,
    };
}
