export const createTabId = () => {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `tab-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
};

export const toDatetimeLocal = (value?: string | Date | null) => {
    const date = value ? new Date(value) : new Date();
    const year = date.getFullYear();
    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    const day = `${date.getDate()}`.padStart(2, '0');
    const hour = `${date.getHours()}`.padStart(2, '0');
    const minute = `${date.getMinutes()}`.padStart(2, '0');

    return `${year}-${month}-${day}T${hour}:${minute}`;
};

export const getNextTabLabel = (labels: string[]) => {
    const numbers = labels
        .map((label) => label.match(/\d+/)?.[0])
        .filter((value): value is string => Boolean(value))
        .map((value) => Number(value));

    const next = numbers.length ? Math.max(...numbers) + 1 : 1;
    return `Hóa đơn ${next}`;
};
