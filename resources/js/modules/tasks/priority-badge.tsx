import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/lib/i18n';
import type { Priority } from './types';
import { priorityLabels } from './types';

/** Only high and urgent priorities are highlighted, to keep lists quiet. */
export default function PriorityBadge({ priority }: { priority: Priority }) {
    const { t } = useTranslation();

    if (priority === 'urgent') {
        return <Badge variant="destructive">{t(priorityLabels.urgent)}</Badge>;
    }

    if (priority === 'high') {
        return <Badge variant="outline">{t(priorityLabels.high)}</Badge>;
    }

    return null;
}
