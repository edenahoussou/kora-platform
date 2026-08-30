import { QRCodeSVG } from 'qrcode.react';

export function receiptQrValue(payload: {
    reservationCode: string;
    reservationUuid: string;
    agencyCode: string | null;
    confirmedAt: string | null;
    agentUuid: string | null;
}): string {
    return JSON.stringify({
        v: 1,
        r: payload.reservationUuid,
        c: payload.reservationCode,
        a: payload.agencyCode,
        d: payload.confirmedAt,
        g: payload.agentUuid,
    });
}

export function ReceiptQr({ value }: { value: string }) {
    return (
        <QRCodeSVG
            className="receipt-qr"
            value={value}
            size={132}
            level="M"
            includeMargin
            bgColor="#FFFFFF"
            fgColor="#001D4E"
            role="img"
            aria-label="QR code de confirmation de la réservation"
        />
    );
}
