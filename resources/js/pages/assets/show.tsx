import type { FormDataConvertible } from '@inertiajs/core';
import { Form, Head, router } from '@inertiajs/react';
import { Download, FileImage } from 'lucide-react';
import ActivityFeed from '@/components/core/activity-feed';
import type { ActivityItem } from '@/components/core/activity-feed';
import CommentsThread from '@/components/core/comments-thread';
import type { CommentItem } from '@/components/core/comments-thread';
import RelationsPanel from '@/components/core/relations-panel';
import type { RelationItem } from '@/components/core/relations-panel';
import TagInput from '@/components/core/tag-input';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/lib/i18n';
import { useOptions } from '@/lib/options';
import { destroy, index, update } from '@/routes/assets';
import type { AssetSummary } from '@/modules/assets/types';
import { kindLabels } from '@/modules/assets/types';

type Props = {
    asset: AssetSummary & {
        taken_on: string | null;
        original_name: string;
        mime_type: string | null;
        size: number;
        download_url: string;
    };
    recordId: string;
    comments: CommentItem[];
    activity: ActivityItem[];
    relations: RelationItem[];
    can: { delete: boolean };
};

const NONE = 'none';

const fieldLabels: Record<string, string> = {
    title: 'Title',
    caption: 'Caption',
    credit: 'Credit',
    category: 'Category',
    taken_on: 'Date',
};

const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

function humanSize(bytes: number): string {
    return bytes < 1024 * 1024
        ? `${Math.max(1, Math.round(bytes / 1024))} KB`
        : `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function ShowAsset({
    asset,
    recordId,
    comments,
    activity,
    relations,
    can,
}: Props) {
    const { t } = useTranslation();
    const categories = useOptions('asset_category');

    return (
        <>
            <Head title={asset.title} />

            <div className="grid gap-10 px-4 py-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div className="space-y-6">
                    <figure className="space-y-3">
                        <div className="flex max-h-[70vh] items-center justify-center overflow-hidden rounded-lg border bg-muted">
                            {asset.kind === 'video' ? (
                                <video
                                    src={asset.url}
                                    controls
                                    preload="metadata"
                                    className="max-h-[70vh] w-full"
                                />
                            ) : asset.previewable ? (
                                <img
                                    src={asset.url}
                                    alt={asset.caption ?? asset.title}
                                    className="max-h-[70vh] object-contain"
                                />
                            ) : (
                                <div className="flex flex-col items-center gap-2 p-16 text-sm text-muted-foreground">
                                    <FileImage className="size-12" />
                                    {t(
                                        'No preview for this file. Download it to open it.',
                                    )}
                                </div>
                            )}
                        </div>
                        {(asset.caption || asset.credit) && (
                            <figcaption className="text-sm">
                                {asset.caption}
                                {asset.credit && (
                                    <span className="text-muted-foreground">
                                        {asset.caption ? ' · ' : ''}
                                        {t('Credit: :name', {
                                            name: asset.credit,
                                        })}
                                    </span>
                                )}
                            </figcaption>
                        )}
                    </figure>

                    <div className="flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                        <Button asChild variant="outline" size="sm">
                            <a href={asset.download_url}>
                                <Download />
                                {t('Download')}
                            </a>
                        </Button>
                        <span>
                            {[
                                t(kindLabels[asset.kind]),
                                asset.width && asset.height
                                    ? `${asset.width} × ${asset.height} px`
                                    : null,
                                humanSize(asset.size),
                                asset.original_name,
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        </span>
                    </div>

                    <Form
                        {...update.form(asset.id)}
                        transform={(
                            data: Record<string, FormDataConvertible>,
                        ) => ({
                            ...data,
                            tags: data.tags ?? [],
                            category:
                                data.category === NONE ? null : data.category,
                        })}
                        options={{ preserveScroll: true }}
                        className="grid max-w-3xl gap-4"
                    >
                        {({ processing, errors, recentlySuccessful }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="title">{t('Title')}</Label>
                                    <Input
                                        id="title"
                                        name="title"
                                        required
                                        defaultValue={asset.title}
                                    />
                                    <InputError message={errors.title} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="caption">
                                        {t('Caption')}
                                    </Label>
                                    <textarea
                                        id="caption"
                                        name="caption"
                                        rows={3}
                                        defaultValue={asset.caption ?? ''}
                                        className={textareaClass}
                                        placeholder={t(
                                            'Who, what, where, when: the caption that goes with it.',
                                        )}
                                    />
                                    <InputError message={errors.caption} />
                                </div>
                                <div className="grid gap-4 sm:grid-cols-3">
                                    <div className="grid gap-2">
                                        <Label htmlFor="credit">
                                            {t('Credit')}
                                        </Label>
                                        <Input
                                            id="credit"
                                            name="credit"
                                            defaultValue={asset.credit ?? ''}
                                            placeholder={t(
                                                'Photographer or author',
                                            )}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="category">
                                            {t('Category')}
                                        </Label>
                                        <Select
                                            name="category"
                                            defaultValue={
                                                asset.category ?? NONE
                                            }
                                        >
                                            <SelectTrigger id="category">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={NONE}>
                                                    {t('No category')}
                                                </SelectItem>
                                                {categories.items
                                                    .filter(
                                                        (option) =>
                                                            option.active ||
                                                            option.key ===
                                                                asset.category,
                                                    )
                                                    .map((option) => (
                                                        <SelectItem
                                                            key={option.key}
                                                            value={option.key}
                                                        >
                                                            {t(option.label)}
                                                        </SelectItem>
                                                    ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="taken_on">
                                            {t('Date')}
                                        </Label>
                                        <Input
                                            id="taken_on"
                                            name="taken_on"
                                            type="date"
                                            defaultValue={asset.taken_on ?? ''}
                                        />
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="tags">{t('Tags')}</Label>
                                    <TagInput defaultValue={asset.tags} />
                                    <InputError message={errors.tags} />
                                </div>
                                <div className="flex items-center gap-4">
                                    <Button disabled={processing}>
                                        {t('Save')}
                                    </Button>
                                    {recentlySuccessful && (
                                        <span className="text-sm text-muted-foreground">
                                            {t('Saved.')}
                                        </span>
                                    )}
                                </div>
                            </>
                        )}
                    </Form>

                    <CommentsThread recordId={recordId} comments={comments} />
                </div>

                <aside className="space-y-10">
                    <RelationsPanel recordId={recordId} relations={relations} />
                    <ActivityFeed
                        activity={activity}
                        fieldLabels={fieldLabels}
                    />

                    {can.delete && (
                        <Dialog>
                            <DialogTrigger asChild>
                                <Button variant="outline" size="sm">
                                    {t('Delete asset')}
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>
                                    {t('Delete this asset?')}
                                </DialogTitle>
                                <DialogDescription>
                                    {t(
                                        'The file and its comments will be deleted. This cannot be undone.',
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
                                            router.delete(destroy(asset.id).url)
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

ShowAsset.layout = {
    breadcrumbs: [{ title: 'Assets', href: index() }],
};
