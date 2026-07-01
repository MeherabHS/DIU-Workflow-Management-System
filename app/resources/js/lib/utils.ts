export function cn(...classes: Array<string | false | null | undefined>) {
    return classes.filter(Boolean).join(' ');
}

export function humanize(value?: string | null) {
    if (!value) return 'Not set';
    return value.replace(/_/g, ' ').replace(/-/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

export function dateText(value?: string | null) {
    if (!value) return 'Not set';
    return String(value).slice(0, 10);
}

export function initials(name?: string | null) {
    if (!name) return 'U';
    return name
        .split(' ')
        .filter(Boolean)
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
}
