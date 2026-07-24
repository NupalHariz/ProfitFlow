export function formatIndonesianPhoneNumber(
    phone: string | null,
): string | null {
    if (phone === null) {
        return phone;
    }

    if (/^0[1-9]\d{7,12}$/.test(phone)) {
        return phone;
    }

    if (/^\+62[1-9]\d{7,12}$/.test(phone)) {
        return `0${phone.slice(3)}`;
    }

    return phone;
}
