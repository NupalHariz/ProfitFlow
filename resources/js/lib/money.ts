import type { TransactionProductLookup } from '@/types';

const MAX_MONEY_CENTS = 999_999_999_999_999_999n;
const MAX_QUANTITY = 2_147_483_647n;

export type CompleteMoneyPreview = {
    state: 'complete';
    totalRevenue: string;
    totalProductionCost: string;
    netProfit: string;
};

export type MoneyPreview =
    | CompleteMoneyPreview
    | {
          state: 'incomplete' | 'invalid';
      };

export function moneyToCents(value: string): bigint | null {
    const match = /^(\d+)(?:\.(\d{1,2}))?$/.exec(value);

    if (!match) {
        return null;
    }

    const normalizedWhole = match[1].replace(/^0+(?=\d)/, '');

    if (normalizedWhole.length > 16) {
        return null;
    }

    const cents =
        BigInt(normalizedWhole) * 100n +
        BigInt((match[2] ?? '').padEnd(2, '0'));

    return cents <= MAX_MONEY_CENTS ? cents : null;
}

export function formatMoneyCents(cents: bigint): string {
    const negative = cents < 0n;
    const absolute = negative ? -cents : cents;
    const whole = absolute / 100n;
    const fraction = (absolute % 100n).toString().padStart(2, '0');

    return `${negative ? '-' : ''}${whole.toString()}.${fraction}`;
}

export function formatEditableDecimal(value: string): string {
    const match = /^(-?\d+)\.00$/.exec(value);

    return match ? match[1] : value;
}

export function formatRupiah(value: string): string {
    const cents = moneyToSignedCents(value);

    if (cents === null) {
        return value;
    }

    const normalized = formatMoneyCents(cents);
    const negative = normalized.startsWith('-');
    const unsigned = negative ? normalized.slice(1) : normalized;
    const [whole, fraction] = unsigned.split('.');
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    const displayedFraction = fraction === '00' ? '' : `,${fraction}`;

    return `${negative ? '-' : ''}Rp ${grouped}${displayedFraction}`;
}

export function calculateItemPreview(
    product: TransactionProductLookup | undefined,
    quantityValue: string,
    sellingPriceOverride: string,
): MoneyPreview {
    if (!product || quantityValue === '') {
        return { state: 'incomplete' };
    }

    const quantity = positiveQuantity(quantityValue);

    if (quantity === null) {
        return { state: 'invalid' };
    }

    const sellingPrice = moneyToCents(
        sellingPriceOverride === ''
            ? product.default_selling_price
            : sellingPriceOverride,
    );
    const productionCost = moneyToCents(product.default_production_cost);

    if (sellingPrice === null || productionCost === null) {
        return { state: 'invalid' };
    }

    const revenue = checkedMultiply(sellingPrice, quantity);
    const cost = checkedMultiply(productionCost, quantity);

    if (revenue === null || cost === null) {
        return { state: 'invalid' };
    }

    return {
        state: 'complete',
        totalRevenue: formatMoneyCents(revenue),
        totalProductionCost: formatMoneyCents(cost),
        netProfit: formatMoneyCents(revenue - cost),
    };
}

export function aggregateMoneyPreviews(previews: MoneyPreview[]): MoneyPreview {
    if (
        previews.length === 0 ||
        previews.some((preview) => preview.state === 'incomplete')
    ) {
        return { state: 'incomplete' };
    }

    if (previews.some((preview) => preview.state === 'invalid')) {
        return { state: 'invalid' };
    }

    let revenue = 0n;
    let cost = 0n;
    let profit = 0n;

    for (const preview of previews) {
        if (preview.state !== 'complete') {
            return { state: preview.state };
        }

        const itemRevenue = moneyToCents(preview.totalRevenue);
        const itemCost = moneyToCents(preview.totalProductionCost);
        const itemProfit = moneyToSignedCents(preview.netProfit);

        if (itemRevenue === null || itemCost === null || itemProfit === null) {
            return { state: 'invalid' };
        }

        revenue += itemRevenue;
        cost += itemCost;
        profit += itemProfit;

        if (
            revenue > MAX_MONEY_CENTS ||
            cost > MAX_MONEY_CENTS ||
            profit > MAX_MONEY_CENTS ||
            profit < -MAX_MONEY_CENTS
        ) {
            return { state: 'invalid' };
        }
    }

    return {
        state: 'complete',
        totalRevenue: formatMoneyCents(revenue),
        totalProductionCost: formatMoneyCents(cost),
        netProfit: formatMoneyCents(profit),
    };
}

export function moneyToSignedCents(value: string): bigint | null {
    const negative = value.startsWith('-');
    const unsigned = negative ? value.slice(1) : value;
    const cents = moneyToCents(unsigned);

    if (cents === null) {
        return null;
    }

    return negative ? -cents : cents;
}

function positiveQuantity(value: string): bigint | null {
    if (!/^\d+$/.test(value)) {
        return null;
    }

    const quantity = BigInt(value);

    return quantity > 0n && quantity <= MAX_QUANTITY ? quantity : null;
}

function checkedMultiply(cents: bigint, quantity: bigint): bigint | null {
    if (quantity !== 0n && cents > MAX_MONEY_CENTS / quantity) {
        return null;
    }

    return cents * quantity;
}
