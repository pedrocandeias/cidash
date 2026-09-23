import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            workspace: {
                id: number;
                name: string;
                role: 'member' | 'editor' | 'manager' | null;
            } | null;
            locale: string;
            translations: Record<string, string>;
            [key: string]: unknown;
        };
    }
}
