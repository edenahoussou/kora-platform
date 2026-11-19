import assert from 'node:assert/strict';
import { Buffer } from 'node:buffer';
import { readFileSync } from 'node:fs';
import process from 'node:process';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(
    new URL(
        '../../resources/js/components/sale/sale-utils.ts',
        import.meta.url,
    ),
    'utf8',
);
const { outputText } = ts.transpileModule(source, {
    compilerOptions: {
        module: ts.ModuleKind.ESNext,
        target: ts.ScriptTarget.ES2022,
    },
});
const { formatIsoDate, formatIsoTime, formatStopSchedule } = await import(
    `data:text/javascript;base64,${Buffer.from(outputText).toString('base64')}`
);

test('keeps boarding and arrival dates at J+2 in each stop timezone', () => {
    const boarding = '2026-09-09T23:30:00+00:00';
    const arrival = '2026-09-10T03:15:00+01:00';

    for (const deviceTimezone of ['UTC', 'America/Los_Angeles', 'Asia/Tokyo']) {
        process.env.TZ = deviceTimezone;
        assert.equal(formatIsoDate(boarding, 'Africa/Accra'), '2026-09-09');
        assert.equal(formatIsoTime(boarding, 'Africa/Accra'), '23:30');
        assert.equal(formatIsoDate(arrival, 'Africa/Porto-Novo'), '2026-09-10');
        assert.equal(formatIsoTime(arrival, 'Africa/Porto-Novo'), '03:15');
        assert.match(
            formatStopSchedule(arrival, 'Africa/Porto-Novo'),
            /10 sept. 2026 à 03:15 \(Africa\/Porto-Novo\)/,
        );
    }
});

test('does not substitute a departure time for a missing stop schedule', () => {
    for (const missing of [null, undefined, '', 'invalid']) {
        assert.equal(formatStopSchedule(missing), 'Horaire non disponible');
    }
});

test('preserves a published local offset when timezone metadata is absent', () => {
    assert.equal(formatIsoTime('2026-09-10T03:15:00+01:00'), '03:15');
    assert.equal(formatIsoDate('2026-09-10T03:15:00+01:00'), '2026-09-10');
});

test('always includes the configured timezone in basket schedules', () => {
    assert.equal(
        formatStopSchedule('2026-11-24T08:00:00+01:00', 'Africa/Porto-Novo'),
        '24 nov. 2026 à 08:00 (Africa/Porto-Novo)',
    );
    assert.equal(
        formatStopSchedule('2026-11-24T16:00:00+01:00', 'Africa/Porto-Novo'),
        '24 nov. 2026 à 16:00 (Africa/Porto-Novo)',
    );
});
