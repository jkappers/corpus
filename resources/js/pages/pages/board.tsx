import { Head } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import BoardEditor from '@/components/board/board-editor';
import type { BoardData } from '@/components/board/board-editor';
import ItemLocation from '@/components/item-location';
import VersionHistory from '@/components/version-history';
import { useAutosave } from '@/hooks/use-autosave';
import { useDiscardUntouched } from '@/hooks/use-discard-untouched';
import { useUntitledTitle } from '@/hooks/use-untitled-title';
import type { SaveStatus } from '@/hooks/use-autosave';
import { update } from '@/routes/api/v1/boards';

type Board = {
    id: number;
    space_id: number;
    title: string;
    board: BoardData | null;
    updated_at: string;
};

const STATUS_TEXT: Record<SaveStatus, string> = {
    idle: '',
    pending: 'Unsaved',
    saving: 'Saving…',
    saved: 'Saved',
    error: 'Not saved — will retry on next edit',
    conflict: 'Changed elsewhere — reload to get the latest',
};

const EMPTY: BoardData = {
    nodes: [],
    edges: [],
    viewport: { x: 0, y: 0, zoom: 1 },
};

function BoardBody({ page, canEdit }: { page: Board; canEdit: boolean }) {
    const untitledTitle = useUntitledTitle();
    const [title, setTitle] = useState(page.title);
    const { save, flush, rebase, status } = useAutosave(
        update(page.id).url,
        page.updated_at,
    );
    const touch = useDiscardUntouched('board', page.id);
    const unsaved =
        status === 'pending' ||
        status === 'saving' ||
        status === 'error' ||
        status === 'conflict';

    const onChange = useCallback(
        (board: object) => {
            const { nodes, edges } = board as Partial<BoardData>;

            // Panning an empty canvas also reports a change; only content counts as an edit.
            if (nodes?.length || edges?.length) {
                touch();
            }

            save({ board });
        },
        [save, touch],
    );

    return (
        <>
            <Head title={title || untitledTitle} />
            <div className="flex h-[calc(100dvh-48px)] flex-col md:h-dvh">
                <div className="flex items-center justify-between gap-3 border-b border-rule px-4 py-2 md:px-6">
                    <div className="flex min-w-0 flex-1 items-center gap-1">
                        <ItemLocation
                            type="board"
                            id={page.id}
                            spaceId={page.space_id}
                            canMove={canEdit && !unsaved}
                            onMoved={rebase}
                        />
                        <input
                            value={title}
                            readOnly={!canEdit}
                            placeholder={untitledTitle}
                            aria-label="Title"
                            autoFocus={canEdit && page.title === untitledTitle}
                            onFocus={(e) => {
                                if (title === untitledTitle) {
                                    e.currentTarget.select();
                                }
                            }}
                            className="min-w-0 flex-1 bg-transparent text-lg font-semibold tracking-tight outline-none placeholder:text-ink-3"
                            onChange={(e) => {
                                setTitle(e.target.value);
                                touch();
                                save({ title: e.target.value });
                            }}
                            onBlur={flush}
                        />
                    </div>
                    <VersionHistory
                        type="board"
                        id={page.id}
                        canRestore={canEdit}
                        disabled={unsaved}
                    />
                    <span
                        role="status"
                        className={`text-xs ${status === 'error' || status === 'conflict' ? 'text-bad-ink' : 'text-ink-3'}`}
                    >
                        {STATUS_TEXT[status]}
                    </span>
                </div>
                <div className="min-h-0 flex-1">
                    <BoardEditor
                        initial={page.board ?? EMPTY}
                        onChange={onChange}
                        readOnly={!canEdit}
                    />
                </div>
            </div>
        </>
    );
}

export default function BoardPage({
    page,
    can_edit,
}: {
    page: Board;
    can_edit: boolean;
}) {
    return <BoardBody key={page.id} page={page} canEdit={can_edit} />;
}
