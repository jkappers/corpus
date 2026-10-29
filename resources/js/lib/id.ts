/** Unique id for client-created objects. randomUUID only exists in secure contexts (HTTPS/localhost). */
export function newId(): string {
    if (typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    const bytes = crypto.getRandomValues(new Uint8Array(16));

    return Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
}
