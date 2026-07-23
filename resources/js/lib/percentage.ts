export const PERCENTAGE_TOTAL_UNITS = 1_000_000;

export function percentageToUnits(value: string): number | null {
    const normalized = value.trim();
    const match = /^(\d{1,3})(?:\.(\d{1,4}))?$/.exec(normalized);

    if (!match) {
        return null;
    }

    const wholeUnits = Number.parseInt(match[1], 10) * 10_000;
    const fractionalUnits = Number.parseInt(
        (match[2] ?? '').padEnd(4, '0'),
        10,
    );

    return wholeUnits + fractionalUnits;
}

export function formatPercentageUnits(units: number): string {
    const whole = Math.trunc(units / 10_000);
    const fraction = String(units % 10_000).padStart(4, '0');

    return `${whole}.${fraction}`;
}

export type PercentageTotal = {
    state: 'ready' | 'incomplete' | 'exceeds' | 'invalid';
    units: number | null;
    formatted: string | null;
};

export function percentageTotal(values: string[]): PercentageTotal {
    let totalUnits = 0;

    for (const value of values) {
        const units = percentageToUnits(value);

        if (units === null || units <= 0 || units > PERCENTAGE_TOTAL_UNITS) {
            return {
                state: 'invalid',
                units: null,
                formatted: null,
            };
        }

        totalUnits += units;
    }

    if (values.length === 0) {
        return {
            state: 'invalid',
            units: null,
            formatted: null,
        };
    }

    return {
        state:
            totalUnits === PERCENTAGE_TOTAL_UNITS
                ? 'ready'
                : totalUnits < PERCENTAGE_TOTAL_UNITS
                  ? 'incomplete'
                  : 'exceeds',
        units: totalUnits,
        formatted: formatPercentageUnits(totalUnits),
    };
}
