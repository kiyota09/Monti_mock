import { Config } from 'ziggy-js';

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    role?: string;
    position?: string;
    employee_id?: string | null;
    department?: string | null;
    department_name?: string | null;
    hrmDepartment?: { name?: string | null } | null;
    manufacturing_role?: string | null;
    supervisor_department?: string | null;
    is_manufacturing_supervisor?: boolean;
    profile_photo_path?: string | null;
    join_date?: string | null;
    is_active?: boolean;
    created_at?: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    ziggy: Config & { location: string };
};
