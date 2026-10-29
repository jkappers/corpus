export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    is_admin: boolean;
    is_owner: boolean;
    role: 'owner' | 'admin' | 'member';
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};

export type SpaceVisibility = 'open' | 'visible' | 'private';

export type SpaceRole = 'manager' | 'editor' | 'viewer';

export type Space = {
    id: number;
    name: string;
    visibility: SpaceVisibility;
    is_personal: boolean;
    is_mine: boolean;
    owner_name: string | null;
    role: SpaceRole | null;
    can_edit: boolean;
    can_manage: boolean;
};

export type EnumOption<T extends string = string> = {
    value: T;
    label: string;
    description: string;
};

export type SharedEnums = {
    spaceVisibility: EnumOption<SpaceVisibility>[];
    spaceRole: EnumOption<SpaceRole>[];
    auditEvent: { value: string; label: string }[];
    userRole: { value: 'member' | 'admin'; label: string }[];
};
