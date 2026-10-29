import { usePage } from '@inertiajs/react';
import type { SharedEnums } from '@/types';

export function useEnums(): SharedEnums {
    return usePage<{ enums: SharedEnums }>().props.enums;
}
