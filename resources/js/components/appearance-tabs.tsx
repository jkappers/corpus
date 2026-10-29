import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { HTMLAttributes } from 'react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export default function AppearanceToggleTab({
    className = '',
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    const { appearance, updateAppearance } = useAppearance();

    const tabs: { value: Appearance; icon: LucideIcon; label: string }[] = [
        { value: 'light', icon: Sun, label: 'Light' },
        { value: 'dark', icon: Moon, label: 'Dark' },
        { value: 'system', icon: Monitor, label: 'System' },
    ];

    return (
        <div
            role="group"
            aria-label="Appearance"
            className={cn('inline-flex gap-1', className)}
            {...props}
        >
            {tabs.map(({ value, icon: Icon, label }) => (
                <Button
                    key={value}
                    type="button"
                    aria-pressed={appearance === value}
                    onClick={() => updateAppearance(value)}
                >
                    <Icon />
                    {label}
                </Button>
            ))}
        </div>
    );
}
