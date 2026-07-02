export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string | null;
    is_active?: boolean;
    profile_photo_url?: string | null;
    initials?: string;
    department_id?: number | null;
    designation?: string | null;
    phone?: string | null;
    roles?: string[];
    permissions?: string[];
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    auth: {
        user: User | null;
    };
    flash?: {
        status?: string | null;
    };
    loginWorkSummary?: string | null;
    homeUrl?: string;
    ui?: {
        primaryButtonClass?: string;
    };
    notifications?: {
        unreadCount: number;
    };
};

export type Paginator<T> = {
    data: T[];
    links?: Array<{ url: string | null; label: string; active: boolean }>;
    total?: number;
};

export type BaseUser = { id: number; name: string; email?: string };
export type Department = { id: number; name: string };

export type Project = {
    id: number;
    title: string;
    description?: string | null;
    status?: string | null;
    priority?: string | null;
    start_date?: string | null;
    deadline?: string | null;
    department?: Department | null;
    creator?: BaseUser | null;
    active_primary_assignment?: { coordinator?: BaseUser | null } | null;
    activePrimaryAssignment?: { coordinator?: BaseUser | null } | null;
    assignments?: Assignment[];
};

export type Assignment = {
    id: number;
    coordinator?: BaseUser | null;
    subordinate?: BaseUser | null;
    assigner?: BaseUser | null;
    assigned_at?: string | null;
    revoked_at?: string | null;
};

export type Task = {
    id: number;
    title: string;
    description?: string | null;
    status?: string | null;
    priority?: string | null;
    deadline?: string | null;
    project?: Project | null;
    creator?: BaseUser | null;
    assigned_user?: BaseUser | null;
    assignedUser?: BaseUser | null;
    subtasks?: Subtask[];
    subtasks_count?: number;
};

export type Subtask = {
    id: number;
    title: string;
    description?: string | null;
    status?: string | null;
    priority?: string | null;
    deadline?: string | null;
    progress_note?: string | null;
    current_assigned_at?: string | null;
    project?: Project | null;
    task?: Task | null;
    creator?: BaseUser | null;
    assignments?: Assignment[];
};

export type RepositoryEntry = {
    id: number;
    title: string;
    type?: string | null;
    client_or_office?: string | null;
    status?: string | null;
    deadline?: string | null;
    description?: string | null;
    final_summary?: string | null;
    value_amount?: string | null;
    value_currency?: string | null;
    department?: Department | null;
    responsible_user?: BaseUser | null;
    responsibleUser?: BaseUser | null;
    creator?: BaseUser | null;
    project?: Project | null;
    updates?: Array<{ id: number; update_type?: string; note?: string; old_status?: string; new_status?: string; user?: BaseUser; created_at?: string }>;
    finalized_at?: string | null;
    finalized_by_name?: string | null;
    final_status_snapshot?: Record<string, unknown> | null;
};

