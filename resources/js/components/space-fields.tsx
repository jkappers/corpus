import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { useEnums } from '@/hooks/use-enums';
import type { SpaceVisibility } from '@/types';

export function SpaceFields({
    name,
    visibility,
    errors,
    locked = false,
    lockedReason,
    onNameChange,
    onVisibilityChange,
}: {
    name: string;
    visibility: SpaceVisibility;
    errors: { name?: string; visibility?: string };
    locked?: boolean;
    lockedReason?: string | null;
    onNameChange: (name: string) => void;
    onVisibilityChange: (visibility: SpaceVisibility) => void;
}) {
    const { spaceVisibility } = useEnums();

    return (
        <>
            <div>
                <label className="label" htmlFor="space-name">
                    Name
                </label>
                <Input
                    id="space-name"
                    value={name}
                    aria-invalid={errors.name ? true : undefined}
                    onChange={(e) => onNameChange(e.target.value)}
                />
                <InputError
                    role="alert"
                    className="mt-1"
                    message={errors.name}
                />
            </div>

            <fieldset className="space-y-1.5" disabled={locked}>
                <legend className="label">Privacy</legend>
                {spaceVisibility.map((option) => (
                    <label
                        key={option.value}
                        className="flex items-start gap-2 text-sm"
                    >
                        <input
                            type="radio"
                            name="visibility"
                            className="mt-1"
                            checked={visibility === option.value}
                            onChange={() => onVisibilityChange(option.value)}
                        />
                        <span>
                            {option.label}
                            <span className="block text-xs text-ink-3">
                                {option.description}
                            </span>
                        </span>
                    </label>
                ))}
                {locked && (
                    <p className="text-xs text-ink-3">
                        {lockedReason ?? 'Only admins can change visibility.'}
                    </p>
                )}
                <InputError role="alert" message={errors.visibility} />
            </fieldset>
        </>
    );
}
