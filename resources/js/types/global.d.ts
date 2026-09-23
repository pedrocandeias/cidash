import type { Auth } from '@/types/auth';
import type { FlashToast } from '@/types/ui';

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
            notifications: {
                unread: number;
                items: {
                    id: string;
                    data: {
                        message: string;
                        title: string;
                        by: string | null;
                        url: string;
                    };
                    read: boolean;
                    created_at: string;
                }[];
            } | null;
            options: Record<
                'event_type' | 'content_format',
                {
                    key: string;
                    label: string;
                    color: string | null;
                    active: boolean;
                }[]
            > | null;
            locale: string;
            translations: Record<string, string>;
            [key: string]: unknown;
        };
        flashDataType: {
            toast?: FlashToast;
            invitationLink?: string;
        };
    }
}
