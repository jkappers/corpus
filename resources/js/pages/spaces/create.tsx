import { Head, Link, router, useHttp } from "@inertiajs/react";
import type { FormEvent } from "react";
import { toast } from "sonner";
import { PageContainer, PageHeader } from "@/components/page-container";
import { SpaceFields } from "@/components/space-fields";
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from "@/components/ui/breadcrumb";
import { Button, buttonVariants } from "@/components/ui/button";
import { store } from "@/routes/api/v1/spaces";
import { edit, index } from "@/routes/spaces";
import type { SpaceVisibility } from "@/types";

export default function CreateSpace() {
    const form = useHttp<
        { name: string; visibility: SpaceVisibility },
        { data: { id: number } }
    >({
        name: "",
        visibility: "open",
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(store().url, {
            onSuccess: (response) => {
                toast.success("Space created.");
                router.visit(edit(response.data.id));
            },
        });
    };

    return (
        <>
            <Head title="New space" />
            <PageContainer>
                <Breadcrumb className="mb-2">
                    <BreadcrumbList className="gap-1.5 text-xs text-ink-2 sm:gap-1.5">
                        <BreadcrumbItem>
                            <BreadcrumbLink asChild className="hover:text-ink">
                                <Link href={index()}>Spaces</Link>
                            </BreadcrumbLink>
                        </BreadcrumbItem>
                        <BreadcrumbSeparator />
                        <BreadcrumbItem>
                            <BreadcrumbPage className="text-ink">
                                New space
                            </BreadcrumbPage>
                        </BreadcrumbItem>
                    </BreadcrumbList>
                </Breadcrumb>
                <PageHeader title="New space" />

                <form onSubmit={submit} className="max-w-xl space-y-4">
                    <SpaceFields
                        name={form.data.name}
                        visibility={form.data.visibility}
                        errors={form.errors}
                        onNameChange={(name) => form.setData("name", name)}
                        onVisibilityChange={(visibility) =>
                            form.setData("visibility", visibility)
                        }
                    />
                    <div className="flex items-center gap-2">
                        <Button
                            type="submit"
                            variant="primary"
                            disabled={form.processing}
                        >
                            Create space
                        </Button>
                        <Link
                            href={index()}
                            className={buttonVariants({ variant: "quiet" })}
                        >
                            Cancel
                        </Link>
                    </div>
                </form>
            </PageContainer>
        </>
    );
}
