import { Head, Link, useHttp, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { refreshPage } from '@/lib/api';
import { update } from '@/routes/api/v1/me';
import { edit } from '@/routes/profile';
import type { Auth } from '@/types';
import { send } from '@/routes/verification';

type PageProps = {
    auth: Auth;
};

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage<PageProps>().props;
    const form = useHttp({
        name: auth.user.name,
        email: auth.user.email,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        void form.patch(update().url, {
            onSuccess: async () => {
                await refreshPage();
                toast.success('Profile updated.');
            },
        });
    };

    return (
        <>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Profile"
                    description="Update your name and email address"
                />

                <form onSubmit={submit} className="space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>

                        <Input
                            id="name"
                            className="mt-1 block w-full"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            name="name"
                            required
                            autoComplete="name"
                            placeholder="Full name"
                        />

                        <InputError
                            className="mt-2"
                            message={form.errors.name}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">Email address</Label>

                        <Input
                            id="email"
                            type="email"
                            className="mt-1 block w-full"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                            name="email"
                            required
                            autoComplete="username"
                            placeholder="Email address"
                        />

                        <InputError
                            className="mt-2"
                            message={form.errors.email}
                        />
                    </div>

                    {mustVerifyEmail &&
                        auth.user.email_verified_at === null && (
                            <div>
                                <p className="-mt-4 text-sm text-muted-foreground">
                                    Your email address is unverified.{' '}
                                    <Link
                                        href={send()}
                                        as="button"
                                        className="text-foreground underline decoration-line underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-line"
                                    >
                                        Click here to re-send the verification
                                        email.
                                    </Link>
                                </p>

                                {status === 'verification-link-sent' && (
                                    <div className="mt-2 text-sm font-medium text-ok">
                                        A new verification link has been sent to
                                        your email address.
                                    </div>
                                )}
                            </div>
                        )}

                    <div className="flex items-center gap-4">
                        <Button
                            variant="primary"
                            disabled={form.processing}
                            data-test="update-profile-button"
                        >
                            Save
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};
