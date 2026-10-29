import { Head, useHttp } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import { SectionHeading } from '@/components/page-container';
import { SpaceFields } from '@/components/space-fields';
import { Button } from '@/components/ui/button';
import { refreshPage } from '@/lib/api';
import { update } from '@/routes/api/v1/spaces';
import type { SpaceVisibility } from '@/types';

type Space = {
    id: number;
    name: string;
    visibility: SpaceVisibility;
    visibility_locked_reason: string | null;
    can_update_visibility: boolean;
};

export default function General({ space }: { space: Space }) {
    const locked =
        space.visibility_locked_reason !== null || !space.can_update_visibility;
    const form = useHttp({
        name: space.name,
        visibility: space.visibility,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.patch(update(space.id).url, {
            onSuccess: () => {
                toast.success('Space updated.');
                refreshPage();
            },
        });
    };

    return (
        <>
            <Head title={`${space.name} settings`} />
            <section>
                <SectionHeading>General</SectionHeading>
                <form onSubmit={submit} className="space-y-4">
                    <SpaceFields
                        name={form.data.name}
                        visibility={form.data.visibility}
                        errors={form.errors}
                        locked={locked}
                        lockedReason={space.visibility_locked_reason}
                        onNameChange={(name) => form.setData('name', name)}
                        onVisibilityChange={(visibility) =>
                            form.setData('visibility', visibility)
                        }
                    />

                    <Button
                        type="submit"
                        variant="primary"
                        disabled={form.processing || !form.isDirty}
                    >
                        Save
                    </Button>
                </form>
            </section>
        </>
    );
}
