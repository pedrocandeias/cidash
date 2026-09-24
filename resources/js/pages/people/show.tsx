import { Form, Head, router } from '@inertiajs/react';
import { Download, ImagePlus, Pencil, Star, X } from 'lucide-react';
import { useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import ActivityFeed from '@/components/core/activity-feed';
import type { ActivityItem } from '@/components/core/activity-feed';
import AttachmentsPanel from '@/components/core/attachments-panel';
import type { AttachmentItem } from '@/components/core/attachments-panel';
import CommentsThread from '@/components/core/comments-thread';
import type { CommentItem } from '@/components/core/comments-thread';
import RelationsPanel from '@/components/core/relations-panel';
import type { RelationItem } from '@/components/core/relations-panel';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    formatDate,
    formatDateTime,
    localToday,
    useTranslation,
} from '@/lib/i18n';
import CopyProfileButton from '@/modules/people/copy-profile-button';
import { PersonPhoto } from '@/modules/people/person-card';
import PersonFields, {
    personFormTransform,
} from '@/modules/people/person-fields';
import type { PersonDetails } from '@/modules/people/types';
import { personFieldLabels, topics } from '@/modules/people/types';
import { destroy, index, obituaries, update } from '@/routes/people';
import * as photos from '@/routes/people/photos';

type Props = {
    person: PersonDetails;
    recordId: string;
    comments: CommentItem[];
    activity: ActivityItem[];
    relations: RelationItem[];
    attachments: AttachmentItem[];
    can: { delete: boolean };
};

/** A titled block of the profile. */
function Section({ title, children }: { title: string; children: ReactNode }) {
    const { t } = useTranslation();

    return (
        <section className="space-y-2">
            <h2 className="text-sm font-bold tracking-wide text-muted-foreground uppercase">
                {t(title)}
            </h2>
            {children}
        </section>
    );
}

function Gallery({ person }: { person: PersonDetails }) {
    const { t } = useTranslation();
    const input = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const options = { preserveScroll: true };

    const upload = (files: FileList | null) => {
        if (!files || files.length === 0) {
            return;
        }

        setUploading(true);
        router.post(
            photos.store(person.id).url,
            { photos: Array.from(files) },
            {
                ...options,
                forceFormData: true,
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ?? t('Something went wrong.'),
                    ),
                onFinish: () => {
                    setUploading(false);

                    if (input.current) {
                        input.current.value = '';
                    }
                },
            },
        );
    };

    return (
        <Section title="Photos">
            <div className="flex flex-wrap gap-3">
                {person.photos.map((photo) => (
                    <figure
                        key={photo.id}
                        className="group relative size-32 overflow-hidden rounded-md border"
                    >
                        <a href={photo.url} target="_blank" rel="noopener">
                            <img
                                src={photo.url}
                                alt={person.name}
                                className="size-full object-cover"
                            />
                        </a>
                        <div className="absolute inset-x-0 bottom-0 flex justify-end gap-1 bg-background/85 p-1 opacity-0 transition-opacity group-focus-within:opacity-100 group-hover:opacity-100">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-7"
                                aria-label={t('Use as main photo')}
                                title={t('Use as main photo')}
                                onClick={() =>
                                    router.post(
                                        photos.main(photo.id).url,
                                        {},
                                        options,
                                    )
                                }
                            >
                                <Star />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-7"
                                aria-label={t('Remove photo')}
                                title={t('Remove photo')}
                                onClick={() =>
                                    router.delete(
                                        photos.destroy(photo.id).url,
                                        options,
                                    )
                                }
                            >
                                <X />
                            </Button>
                        </div>
                    </figure>
                ))}
                <button
                    type="button"
                    disabled={uploading}
                    onClick={() => input.current?.click()}
                    className="flex size-32 flex-col items-center justify-center gap-1 rounded-md border border-dashed border-input text-xs text-muted-foreground hover:bg-muted"
                >
                    <ImagePlus className="size-5" />
                    {t(uploading ? 'Uploading…' : 'Add photos')}
                </button>
                <input
                    ref={input}
                    type="file"
                    accept="image/*"
                    multiple
                    className="hidden"
                    onChange={(event) => upload(event.target.files)}
                />
            </div>
        </Section>
    );
}

export default function ShowPerson({
    person,
    recordId,
    comments,
    activity,
    relations,
    attachments,
    can,
}: Props) {
    const { t, locale } = useTranslation();
    const [editing, setEditing] = useState(false);
    const personTopics = topics(person);
    const text = (value: string | null) =>
        value && <p className="whitespace-pre-line">{value}</p>;

    return (
        <>
            <Head title={person.name} />

            <div className="grid gap-10 px-4 py-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div className="space-y-8">
                    <header className="flex flex-wrap items-start gap-5">
                        <PersonPhoto person={person} size="size-28" />
                        <div className="min-w-0 flex-1 space-y-2">
                            <h1 className="text-2xl font-bold tracking-tight">
                                {person.name}
                            </h1>
                            <p className="text-muted-foreground">
                                {[person.academic_title, person.affiliation]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </p>
                            {(person.email || person.phone) && (
                                <p className="text-sm">
                                    {[person.email, person.phone]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </p>
                            )}
                            <div className="flex flex-wrap items-center gap-2 pt-1">
                                {person.deceased_on ? (
                                    <Badge variant="secondary">
                                        {t('Died on :date', {
                                            date: formatDate(
                                                person.deceased_on,
                                                locale,
                                            ),
                                        })}
                                    </Badge>
                                ) : (
                                    <CopyProfileButton person={person} />
                                )}
                                {person.cv && (
                                    <Button asChild variant="outline" size="sm">
                                        <a href={person.cv.url}>
                                            <Download />
                                            {t('CV')}
                                        </a>
                                    </Button>
                                )}
                                <Dialog
                                    open={editing}
                                    onOpenChange={setEditing}
                                >
                                    <DialogTrigger asChild>
                                        <Button variant="outline" size="sm">
                                            <Pencil />
                                            {t('Edit profile')}
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                                        <DialogTitle>
                                            {t('Edit profile')}
                                        </DialogTitle>
                                        <Form
                                            {...update.form(person.id)}
                                            transform={personFormTransform}
                                            options={{ preserveScroll: true }}
                                            onSuccess={() => setEditing(false)}
                                            className="space-y-6"
                                        >
                                            {({ processing, errors }) => (
                                                <>
                                                    <PersonFields
                                                        errors={errors}
                                                        defaults={person}
                                                    />
                                                    <Button
                                                        disabled={processing}
                                                    >
                                                        {t('Save')}
                                                    </Button>
                                                </>
                                            )}
                                        </Form>
                                    </DialogContent>
                                </Dialog>
                                {person.needs_review && !person.deceased_on && (
                                    <>
                                        <Badge variant="outline">
                                            {t('Bio to review')}
                                        </Badge>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                router.patch(
                                                    update(person.id).url,
                                                    {
                                                        last_reviewed_at:
                                                            localToday(),
                                                    },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {t('Mark as reviewed today')}
                                        </Button>
                                    </>
                                )}
                            </div>
                        </div>
                    </header>

                    {person.short_bio && (
                        <p className="max-w-prose text-lg">
                            {person.short_bio}
                        </p>
                    )}

                    {(person.areas.length > 0 || personTopics.length > 0) && (
                        <div className="grid gap-6 sm:grid-cols-2">
                            {person.areas.length > 0 && (
                                <Section title="Expertise areas">
                                    <div className="flex flex-wrap gap-1">
                                        {person.areas.map((area) => (
                                            <Badge
                                                key={area}
                                                variant="secondary"
                                            >
                                                {area}
                                            </Badge>
                                        ))}
                                    </div>
                                </Section>
                            )}
                            {personTopics.length > 0 && (
                                <Section title="Topics of interest">
                                    <div className="flex flex-wrap gap-1">
                                        {personTopics.map((topic) => (
                                            <Badge
                                                key={topic}
                                                variant="outline"
                                            >
                                                {topic}
                                            </Badge>
                                        ))}
                                    </div>
                                </Section>
                            )}
                        </div>
                    )}

                    {person.bio && (
                        <Section title="Bio">
                            <div className="max-w-prose">
                                {text(person.bio)}
                            </div>
                        </Section>
                    )}
                    {person.career && (
                        <Section title="Career">
                            <div className="max-w-prose">
                                {text(person.career)}
                            </div>
                        </Section>
                    )}

                    <Gallery person={person} />

                    {(person.languages || person.media_notes) && (
                        <div className="grid gap-6 sm:grid-cols-2">
                            {person.languages && (
                                <Section title="Languages">
                                    {text(person.languages)}
                                </Section>
                            )}
                            {person.media_notes && (
                                <Section title="Media experience and availability">
                                    {text(person.media_notes)}
                                </Section>
                            )}
                        </div>
                    )}

                    <Section title="Dead or Alive">
                        {person.obituary ? (
                            <div className="max-w-prose space-y-2 rounded-lg border p-4">
                                {text(person.obituary)}
                                {person.obituary_updated_at && (
                                    <p className="text-xs text-muted-foreground">
                                        {t('Obituary updated :date', {
                                            date: formatDateTime(
                                                person.obituary_updated_at,
                                                locale,
                                            ),
                                        })}
                                    </p>
                                )}
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                {t('No obituary prepared.')}{' '}
                                <button
                                    type="button"
                                    className="underline"
                                    onClick={() => setEditing(true)}
                                >
                                    {t('Prepare one')}
                                </button>
                            </p>
                        )}
                        <a
                            href={obituaries().url}
                            className="text-xs text-muted-foreground hover:text-foreground"
                        >
                            {t('All obituaries')} →
                        </a>
                    </Section>

                    <CommentsThread recordId={recordId} comments={comments} />
                </div>

                <aside className="space-y-10">
                    <AttachmentsPanel
                        recordId={recordId}
                        attachments={attachments}
                    />
                    <RelationsPanel recordId={recordId} relations={relations} />
                    <ActivityFeed
                        activity={activity}
                        fieldLabels={personFieldLabels}
                    />

                    {can.delete && (
                        <Dialog>
                            <DialogTrigger asChild>
                                <Button variant="outline" size="sm">
                                    {t('Delete profile')}
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>
                                    {t('Delete this profile?')}
                                </DialogTitle>
                                <DialogDescription>
                                    {t(
                                        'The profile, its photos, CV and comments will be deleted. This cannot be undone.',
                                    )}
                                </DialogDescription>
                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button variant="secondary">
                                            {t('Cancel')}
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        variant="destructive"
                                        onClick={() =>
                                            router.delete(
                                                destroy(person.id).url,
                                            )
                                        }
                                    >
                                        {t('Delete')}
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    )}
                </aside>
            </div>
        </>
    );
}

ShowPerson.layout = {
    breadcrumbs: [{ title: 'People of interest', href: index() }],
};
