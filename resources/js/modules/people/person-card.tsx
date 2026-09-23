import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/lib/i18n';
import { show } from '@/routes/people';
import CopyProfileButton from './copy-profile-button';
import type { PersonSummary } from './types';

export function PersonPhoto({
    person,
    size = 'size-14',
}: {
    person: PersonSummary;
    size?: string;
}) {
    const initials = person.name
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('');

    return person.photo_url ? (
        <img
            src={person.photo_url}
            alt={person.name}
            className={`${size} shrink-0 rounded-full object-cover`}
        />
    ) : (
        <span
            className={`${size} flex shrink-0 items-center justify-center rounded-full bg-muted text-sm font-medium`}
            aria-hidden
        >
            {initials}
        </span>
    );
}

export default function PersonCard({ person }: { person: PersonSummary }) {
    const { t } = useTranslation();

    return (
        <article className="flex flex-col gap-3 rounded-lg border p-4">
            <div className="flex gap-3">
                <PersonPhoto person={person} />
                <div className="min-w-0">
                    <Link
                        href={show(person.id)}
                        className="font-medium hover:underline"
                    >
                        {person.name}
                    </Link>
                    <p className="text-sm text-muted-foreground">
                        {[person.academic_title, person.affiliation]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                </div>
            </div>
            {person.short_bio && (
                <p className="line-clamp-3 text-sm">{person.short_bio}</p>
            )}
            <div className="flex flex-wrap gap-1">
                {person.areas.map((area) => (
                    <Badge key={area} variant="secondary">
                        {area}
                    </Badge>
                ))}
                {person.needs_review && (
                    <Badge variant="outline">{t('Bio to review')}</Badge>
                )}
            </div>
            <div className="mt-auto">
                <CopyProfileButton person={person} />
            </div>
        </article>
    );
}
