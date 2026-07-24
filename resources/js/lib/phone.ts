export function formatIndonesianPhoneNumber(
    phone: string | null,
): string | null {
    if (phone === null || !/^\+62[1-9]\d{7,12}$/.test(phone)) {
        return phone;
    }

    const nationalNumber = phone.slice(3);
    const prefixLength = nationalNumber.startsWith('8')
        ? 3
        : nationalNumber.length === 9
          ? 2
          : 3;
    const prefix = nationalNumber.slice(0, prefixLength);
    const remainder = nationalNumber.slice(prefixLength);
    const firstGroupLength = remainder.length % 4 || 4;
    const groups = [
        remainder.slice(0, firstGroupLength),
        ...(remainder.slice(firstGroupLength).match(/.{1,4}/g) ?? []),
    ].filter(Boolean);

    return `+62 ${[prefix, ...groups].join(' ')}`;
}
