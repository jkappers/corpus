export type NotationReply = {
    id: number;
    notation_id: number;
    body: string;
    created_at: string;
    can_delete: boolean;
    author: { id: number; name: string };
};

export type Notation = {
    id: number;
    page_id: number;
    quote: string;
    from: number;
    to: number;
    body: string;
    completed_at: string | null;
    is_completed: boolean;
    is_self_assigned: boolean;
    can_complete: boolean;
    can_delete: boolean;
    can_reply: boolean;
    created_at: string;
    author: { id: number; name: string };
    assignee: { id: number; name: string };
    resolved_by: { id: number; name: string } | null;
    replies: NotationReply[];
};

export type Assignee = { id: number; name: string };

export type Selection = { quote: string; from: number; to: number };
