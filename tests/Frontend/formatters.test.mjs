import assert from 'node:assert/strict';
import test from 'node:test';
import {
    formatEditableDecimal,
    formatMoneyCents,
    formatRupiah,
    moneyToSignedCents,
} from '../../resources/js/lib/money.ts';
import { formatPercentage } from '../../resources/js/lib/percentage.ts';
import { formatIndonesianPhoneNumber } from '../../resources/js/lib/phone.ts';

test('Rupiah presentation hides only redundant zero decimals', () => {
    assert.equal(formatRupiah('5500000.00'), 'Rp 5.500.000');
    assert.equal(formatRupiah('5500000.50'), 'Rp 5.500.000,50');
    assert.equal(formatRupiah('100.00'), 'Rp 100');
    assert.equal(formatRupiah('33.33'), 'Rp 33,33');
    assert.equal(formatRupiah('0.00'), 'Rp 0');
    assert.equal(formatRupiah('-33.33'), '-Rp 33,33');
});

test('Rupiah presentation preserves large exact decimal strings', () => {
    const value = '9999999999999999.99';
    const cents = moneyToSignedCents(value);

    assert.equal(cents, 999999999999999999n);
    assert.equal(formatMoneyCents(cents), value);
    assert.equal(formatRupiah(value), 'Rp 9.999.999.999.999.999,99');
});

test('editable decimal presentation removes only a redundant .00 suffix', () => {
    assert.equal(formatEditableDecimal('5500000.00'), '5500000');
    assert.equal(formatEditableDecimal('3900000.00'), '3900000');
    assert.equal(formatEditableDecimal('5500000.50'), '5500000.50');
    assert.equal(formatEditableDecimal('33.33'), '33.33');
    assert.equal(formatEditableDecimal('0.00'), '0');
    assert.equal(formatEditableDecimal('-50000.00'), '-50000');
    assert.equal(
        formatEditableDecimal('9999999999999999.99'),
        '9999999999999999.99',
    );
});

test('percentage presentation trims trailing zeroes without changing digits', () => {
    assert.equal(formatPercentage('70.0000'), '70%');
    assert.equal(formatPercentage('20.0000'), '20%');
    assert.equal(formatPercentage('33.3300'), '33,33%');
    assert.equal(formatPercentage('33.3333'), '33,3333%');
    assert.equal(formatPercentage('100.0000'), '100%');
});

test('phone presentation uses local digits and converts recognized legacy values', () => {
    assert.equal(formatIndonesianPhoneNumber('081234567890'), '081234567890');
    assert.equal(formatIndonesianPhoneNumber('0215550102'), '0215550102');
    assert.equal(formatIndonesianPhoneNumber('+6281234567890'), '081234567890');
    assert.equal(formatIndonesianPhoneNumber('+62215550102'), '0215550102');
});
