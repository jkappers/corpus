import { Handle, NodeResizer, Position, useReactFlow } from '@xyflow/react';
import type { Node, NodeProps } from '@xyflow/react';
import { useEffect, useRef, useState } from 'react';

export type ShapeKind = 'rect' | 'ellipse' | 'diamond' | 'text';

export type ShapeData = { label: string };
export type ShapeNode = Node<ShapeData, ShapeKind>;

const SIDES = [Position.Top, Position.Right, Position.Bottom, Position.Left];

function Outline({ kind }: { kind: Exclude<ShapeKind, 'text'> }) {
    const common = {
        fill: 'var(--raised)',
        stroke: 'var(--edge)',
        strokeWidth: 1.5,
        vectorEffect: 'non-scaling-stroke' as const,
    };

    return (
        <svg
            className="pointer-events-none absolute inset-0 size-full overflow-visible"
            viewBox="0 0 100 100"
            preserveAspectRatio="none"
            aria-hidden
        >
            {kind === 'rect' && (
                <rect x="0" y="0" width="100" height="100" {...common} />
            )}
            {kind === 'ellipse' && (
                <ellipse cx="50" cy="50" rx="50" ry="50" {...common} />
            )}
            {kind === 'diamond' && (
                <polygon points="50,0 100,50 50,100 0,50" {...common} />
            )}
        </svg>
    );
}

function BoardShape({ id, data, selected, type }: NodeProps<ShapeNode>) {
    const { updateNodeData } = useReactFlow();
    const [editing, setEditing] = useState(false);
    const area = useRef<HTMLTextAreaElement>(null);
    const kind = type as ShapeKind;

    useEffect(() => {
        if (editing) {
            area.current?.focus();
            area.current?.select();
        }
    }, [editing]);

    return (
        <div
            className={`relative size-full ${selected && kind === 'text' ? 'outline outline-1 outline-line outline-dashed' : ''}`}
            onDoubleClick={() => setEditing(true)}
        >
            <NodeResizer
                isVisible={selected}
                minWidth={kind === 'text' ? 40 : 60}
                minHeight={kind === 'text' ? 24 : 40}
                color="var(--accent-blue)"
                handleStyle={{ width: 8, height: 8, borderRadius: 2 }}
            />
            {kind !== 'text' && <Outline kind={kind} />}

            <div className="absolute inset-0 flex items-center justify-center p-2 text-center">
                {editing ? (
                    <textarea
                        ref={area}
                        defaultValue={data.label}
                        aria-label="Label"
                        className="nodrag nopan size-full resize-none bg-transparent text-center text-sm text-ink outline-none"
                        onBlur={(e) => {
                            updateNodeData(id, { label: e.target.value });
                            setEditing(false);
                        }}
                        onKeyDown={(e) => {
                            if (e.key === 'Escape') {
                                e.currentTarget.blur();
                            }
                        }}
                    />
                ) : (
                    <span className="text-sm break-words whitespace-pre-wrap text-ink">
                        {data.label}
                    </span>
                )}
            </div>

            {SIDES.map((position) => (
                <Handle
                    key={position}
                    id={position}
                    type="source"
                    position={position}
                    className="board-handle"
                />
            ))}
        </div>
    );
}

export const nodeTypes = {
    rect: BoardShape,
    ellipse: BoardShape,
    diamond: BoardShape,
    text: BoardShape,
};

export const DEFAULT_SIZE: Record<
    ShapeKind,
    { width: number; height: number }
> = {
    rect: { width: 140, height: 80 },
    ellipse: { width: 140, height: 80 },
    diamond: { width: 140, height: 100 },
    text: { width: 140, height: 32 },
};
