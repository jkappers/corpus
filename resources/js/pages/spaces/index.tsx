import EmptyState from "@/components/empty-state";
import { PageContainer, PageHeader } from "@/components/page-container";
import { buttonVariants } from "@/components/ui/button";
import { Head, Link } from "@inertiajs/react";
import { Eye, Globe, Lock, Settings, Users } from "lucide-react";
import { useEnums } from "@/hooks/use-enums";
import { create, edit, show } from "@/routes/spaces";
import type { SpaceRole, SpaceVisibility } from "@/types";

type Row = {
    id: number;
    name: string;
    visibility: SpaceVisibility;
    is_personal: boolean;
    members_count: number;
    role: SpaceRole | null;
    can_update: boolean;
};

export function VisibilityIcon({
    visibility,
}: {
    visibility: SpaceVisibility;
}) {
    const Icon =
        visibility === "private"
            ? Lock
            : visibility === "visible"
              ? Eye
              : Globe;

    return <Icon className="size-3.5 text-ink-3" aria-hidden />;
}

export default function Spaces({
    space_list: spaces,
    can_create,
}: {
    space_list: Row[];
    can_create: boolean;
}) {
    const { spaceVisibility, spaceRole } = useEnums();
    const labelFor = (
        options: { value: string; label: string }[],
        value: string,
    ) => options.find((option) => option.value === value)?.label ?? value;

    return (
        <>
            <Head title="Spaces" />
            <PageContainer size="wide">
                <PageHeader
                    title="Spaces"
                    actions={
                        can_create && (
                            <Link
                                href={create()}
                                className={buttonVariants({
                                    variant: "primary",
                                })}
                            >
                                New space
                            </Link>
                        )
                    }
                />

                {spaces.length === 0 ? (
                    <EmptyState
                        title="No spaces to browse"
                        description={
                            can_create
                                ? "Create a space to get started."
                                : "Spaces you can access will appear here."
                        }
                    />
                ) : (
                    <div>
                        <div aria-hidden className="th hidden gap-x-3 md:flex">
                            <span className="flex-1 pl-6">Name</span>
                            <span className="w-24">Role</span>
                            <span className="w-14 text-right">Members</span>
                            <span className="w-7" />
                        </div>
                        <div role="list">
                            {spaces.map((space) => (
                                <div
                                    key={space.id}
                                    role="listitem"
                                    className="tr group grid grid-cols-[1fr_auto] gap-x-3 py-2 md:flex md:items-center md:py-1.5"
                                >
                                    <div className="flex min-w-0 items-center gap-2 md:flex-1">
                                        <span
                                            title={labelFor(
                                                spaceVisibility,
                                                space.visibility,
                                            )}
                                        >
                                            <VisibilityIcon
                                                visibility={space.visibility}
                                            />
                                            <span className="sr-only">
                                                {labelFor(
                                                    spaceVisibility,
                                                    space.visibility,
                                                )}
                                            </span>
                                        </span>
                                        <Link
                                            href={show(space.id)}
                                            className="truncate font-medium hover:underline"
                                        >
                                            {space.name}
                                        </Link>
                                    </div>
                                    <span className="col-span-2 row-start-2 mt-0.5 flex items-center gap-3 text-xs text-ink-3 md:contents">
                                        <span className="md:w-24">
                                            {space.role &&
                                                labelFor(spaceRole, space.role)}
                                        </span>
                                        <span
                                            className="flex items-center gap-1 tabular-nums md:w-14 md:justify-end"
                                            aria-label={`${space.members_count} members`}
                                        >
                                            <Users
                                                className="size-3.5"
                                                aria-hidden
                                            />
                                            {space.members_count}
                                        </span>
                                    </span>
                                    <span className="col-start-2 row-start-1 flex justify-end md:w-7">
                                        {space.can_update && (
                                            <Link
                                                href={edit(space.id)}
                                                aria-label={`${space.name} settings`}
                                                title="Settings"
                                                className={buttonVariants({
                                                    variant: "quiet",
                                                    size: "icon-sm",
                                                    className:
                                                        "text-ink-3 group-focus-within:opacity-100 hover:text-ink focus-visible:opacity-100 [@media(hover:hover)]:opacity-0 [@media(hover:hover)]:group-hover:opacity-100",
                                                })}
                                            >
                                                <Settings aria-hidden />
                                            </Link>
                                        )}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </PageContainer>
        </>
    );
}
