export type AlertSeverity = 'info' | 'warning' | 'critical';

export type AlertItem = {
    id: number;
    message: string;
    title: string;
    severity: AlertSeverity;
    status: 'open' | 'acknowledged' | 'resolved';
    due_at: string | null;
    url: string | null;
    acknowledged_by: string | null;
    created_at: string;
    resolved_at: string | null;
};

export const severityLabels: Record<AlertSeverity, string> = {
    info: 'Information',
    warning: 'Warning',
    critical: 'Critical',
};

export const severityDot: Record<AlertSeverity, string> = {
    info: 'bg-info',
    warning: 'bg-warning',
    critical: 'bg-critical',
};
