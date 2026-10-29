import { useCallback, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type ConfirmOptions = {
    title: string;
    description?: string;
    confirmLabel: string;
    destructive?: boolean;
};

/**
 * Promise-based replacement for window.confirm().
 * Render `dialog` once in the component, then `await confirm({...})`.
 */
export function useConfirm() {
    const [options, setOptions] = useState<ConfirmOptions | null>(null);
    const resolver = useRef<((value: boolean) => void) | null>(null);

    const confirm = useCallback(
        (next: ConfirmOptions) =>
            new Promise<boolean>((resolve) => {
                resolver.current = resolve;
                setOptions(next);
            }),
        [],
    );

    const settle = (value: boolean) => {
        resolver.current?.(value);
        resolver.current = null;
        setOptions(null);
    };

    const dialog = (
        <Dialog
            open={options !== null}
            onOpenChange={(open) => {
                if (!open) {
                    settle(false);
                }
            }}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{options?.title}</DialogTitle>
                    {options?.description && (
                        <DialogDescription>
                            {options.description}
                        </DialogDescription>
                    )}
                </DialogHeader>
                <DialogFooter>
                    <Button type="button" onClick={() => settle(false)}>
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant={options?.destructive ? 'danger' : 'primary'}
                        onClick={() => settle(true)}
                    >
                        {options?.confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );

    return { confirm, dialog };
}
