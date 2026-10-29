import { Button } from '@/components/ui/button';
import {
    addEdge,
    Background,
    BackgroundVariant,
    ConnectionMode,
    Controls,
    MarkerType,
    ReactFlow,
    ReactFlowProvider,
    useEdgesState,
    useNodesState,
    useReactFlow,
} from '@xyflow/react';
import type { Connection, Edge, Viewport } from '@xyflow/react';
import '@xyflow/react/dist/style.css';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { MouseEvent as ReactMouseEvent } from 'react';
import { DEFAULT_SIZE, nodeTypes } from '@/components/board/nodes';
import type { ShapeKind, ShapeNode } from '@/components/board/nodes';
import { useAppearance } from '@/hooks/use-appearance';
import { newId } from '@/lib/id';

export type BoardData = {
    nodes: ShapeNode[];
    edges: Edge[];
    viewport: Viewport;
};

type Tool = 'select' | ShapeKind;

const TOOLS: { value: Tool; label: string }[] = [
    { value: 'select', label: 'Select' },
    { value: 'rect', label: 'Rectangle' },
    { value: 'ellipse', label: 'Ellipse' },
    { value: 'diamond', label: 'Diamond' },
    { value: 'text', label: 'Text' },
];

const EDGE_DEFAULTS = {
    type: 'smoothstep',
    markerEnd: { type: MarkerType.ArrowClosed },
} as const;

/** Persist only what is needed to rebuild the board — no selection/drag state. */
function serialize(nodes: ShapeNode[], edges: Edge[], viewport: Viewport) {
    return {
        nodes: nodes.map((n) => ({
            id: n.id,
            type: n.type,
            position: n.position,
            data: n.data,
            width: n.width ?? n.measured?.width,
            height: n.height ?? n.measured?.height,
        })),
        edges: edges.map((e) => ({
            id: e.id,
            source: e.source,
            target: e.target,
            sourceHandle: e.sourceHandle,
            targetHandle: e.targetHandle,
            type: e.type,
            markerEnd: e.markerEnd,
        })),
        viewport,
    };
}

function Canvas({
    initial,
    onChange,
    readOnly = false,
}: {
    initial: BoardData;
    onChange: (data: ReturnType<typeof serialize>) => void;
    readOnly?: boolean;
}) {
    const { appearance } = useAppearance();
    const { screenToFlowPosition, getViewport } = useReactFlow();
    const [nodes, setNodes, onNodesChange] = useNodesState<ShapeNode>(
        initial.nodes,
    );
    const [edges, setEdges, onEdgesChange] = useEdgesState<Edge>(initial.edges);
    const [tool, setTool] = useState<Tool>('select');
    const [viewport, setViewport] = useState<Viewport>(initial.viewport);
    const lastSaved = useRef(
        JSON.stringify(
            serialize(initial.nodes, initial.edges, initial.viewport),
        ),
    );

    useEffect(() => {
        const next = serialize(nodes, edges, viewport);
        const json = JSON.stringify(next);

        if (json !== lastSaved.current) {
            lastSaved.current = json;
            onChange(next);
        }
    }, [nodes, edges, viewport, onChange]);

    const onConnect = useCallback(
        (connection: Connection) =>
            setEdges((current) =>
                addEdge({ ...connection, ...EDGE_DEFAULTS }, current),
            ),
        [setEdges],
    );

    const onPaneClick = useCallback(
        (event: ReactMouseEvent) => {
            if (tool === 'select') {
                return;
            }

            const size = DEFAULT_SIZE[tool];
            const point = screenToFlowPosition({
                x: event.clientX,
                y: event.clientY,
            });

            setNodes((current) => [
                ...current.map((n) => ({ ...n, selected: false })),
                {
                    id: newId(),
                    type: tool,
                    position: {
                        x: point.x - size.width / 2,
                        y: point.y - size.height / 2,
                    },
                    ...size,
                    data: { label: tool === 'text' ? 'Text' : '' },
                    selected: true,
                },
            ]);
            setTool('select');
        },
        [tool, screenToFlowPosition, setNodes],
    );

    return (
        <div className="relative size-full">
            {!readOnly && (
                <div
                    role="toolbar"
                    aria-label="Shapes"
                    className="absolute top-3 left-1/2 z-10 flex -translate-x-1/2 gap-1 rounded-ctl border border-line bg-raised p-1"
                >
                    {TOOLS.map(({ value, label }) => (
                        <Button
                            key={value}
                            type="button"
                            variant="quiet"
                            size="sm"
                            aria-pressed={tool === value}
                            onClick={() => setTool(value)}
                        >
                            {label}
                        </Button>
                    ))}
                </div>
            )}

            <ReactFlow
                nodes={nodes}
                edges={edges}
                nodeTypes={nodeTypes}
                onNodesChange={onNodesChange}
                onEdgesChange={onEdgesChange}
                onConnect={onConnect}
                nodesDraggable={!readOnly}
                nodesConnectable={!readOnly}
                elementsSelectable={!readOnly}
                onPaneClick={onPaneClick}
                onMoveEnd={() => setViewport(getViewport())}
                connectionMode={ConnectionMode.Loose}
                defaultEdgeOptions={EDGE_DEFAULTS}
                defaultViewport={initial.viewport}
                colorMode={appearance}
                deleteKeyCode={readOnly ? null : ['Backspace', 'Delete']}
                proOptions={{ hideAttribution: true }}
                className={tool === 'select' ? '' : 'cursor-crosshair'}
            >
                <Background
                    variant={BackgroundVariant.Dots}
                    gap={20}
                    size={1}
                />
                <Controls showInteractive={false} />
            </ReactFlow>
        </div>
    );
}

export default function BoardEditor(props: {
    initial: BoardData;
    onChange: (data: ReturnType<typeof serialize>) => void;
    readOnly?: boolean;
}) {
    return (
        <ReactFlowProvider>
            <Canvas {...props} />
        </ReactFlowProvider>
    );
}
