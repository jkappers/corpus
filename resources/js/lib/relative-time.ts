const relativeFormatter = new Intl.RelativeTimeFormat(undefined, {
    numeric: "auto",
    style: "narrow",
});

export const absoluteFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
});

const UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
    ["day", 86_400_000],
    ["hour", 3_600_000],
    ["minute", 60_000],
];

export function relativeTime(iso: string): string {
    const diff = new Date(iso).getTime() - Date.now();

    for (const [unit, ms] of UNITS) {
        if (Math.abs(diff) >= ms) {
            return relativeFormatter.format(Math.round(diff / ms), unit);
        }
    }

    return "just now";
}
