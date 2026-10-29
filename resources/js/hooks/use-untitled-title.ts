import { usePage } from '@inertiajs/react';

export function useUntitledTitle(): string {
    return usePage<{ untitledTitle: string }>().props.untitledTitle;
}
