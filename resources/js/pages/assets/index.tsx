import { Head, Link, router } from '@inertiajs/react';
import { FileImage, Film, Shapes, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import type { DragEvent } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/lib/i18n';
import { useOptions } from '@/lib/options';
import { cn } from '@/lib/utils';
import type { AssetSummary } from '@/modules/assets/types';
import { kindLabels } from '@/modules/assets/types';
import { index, show, store } from '@/routes/assets';

type Filters = { q: string; category: string; kind: string; tag: string };

type Props = {
    assets: AssetSummary[];
    filters: Filters;
    tags: string[];
};

const ALL = 'all';

const kindIcons = { image: FileImage, video: Film, graphic: Shapes };

/** A tile of the gallery: the thumbnail (or the video's first frame), title and category. */
function Tile({ asset }: { asset: AssetSummary }) {
    const { t } = useTranslation();
    const categories = useOptions('asset_category');
    const Icon = kindIcons[asset.kind];

    return (
        <Link
            href={show(asset.id)}
            className="group flex flex-col overflow-hidden rounded-lg border bg-card hover:border-primary"
        >
            <div className="flex aspect-[4/3] items-center justify-center overflow-hidden bg-muted">
                {asset.kind === 'video' ? (
                    <video
                        src={`${asset.url}#t=0.5`}
                        preload="metadata"
                        muted
                        className="size-full object-cover"
                    />
                ) : !asset.previewable ? (
                    <Icon className="size-10 text-muted-foreground" />
                ) : (
                    <img
                        src={asset.thumbnail_url}
                        alt={asset.caption ?? asset.title}
                        loading="lazy"
                        className={cn(
                            'size-full',
                            // Graphics and logos are shown whole; photos fill the tile.
                            asset.kind === 'graphic' ||
                                asset.category === 'logos'
                                ? 'object-contain p-4'
                                : 'object-cover',
                        )}
                    />
                )}
            </div>
            <div className="space-y-0.5 p-3">
                <p className="truncate text-sm font-bold group-hover:underline">
                    {asset.title}
                </p>
                <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <Icon className="size-3.5" />
                    {t(kindLabels[asset.kind])}
                    {asset.category &&
                        ` · ${t(categories.label(asset.category))}`}
                </p>
            </div>
        </Link>
    );
}

export default function Assets({ assets, filters, tags }: Props) {
    const { t } = useTranslation();
    const categories = useOptions('asset_category');
    const [query, setQuery] = useState(filters.q);
    const [dragging, setDragging] = useState(false);
    const [uploading, setUploading] = useState(false);
    const input = useRef<HTMLInputElement>(null);

    const apply = (next: Partial<Filters>) => {
        const merged = { ...filters, q: query, ...next };
        router.get(
            index().url,
            Object.fromEntries(
                Object.entries(merged).filter(
                    ([, value]) => value !== '' && value !== ALL,
                ),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const upload = (files: FileList | File[] | null) => {
        if (!files || files.length === 0) {
            return;
        }

        setUploading(true);
        router.post(
            store().url,
            {
                files: Array.from(files),
                ...(filters.category ? { category: filters.category } : {}),
            },
            {
                forceFormData: true,
                preserveScroll: true,
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

    const drop = (event: DragEvent) => {
        event.preventDefault();
        setDragging(false);
        upload(event.dataTransfer.files);
    };

    return (
        <>
            <Head title={t('Assets')} />

            <div
                className="space-y-6 px-4 py-6"
                onDragOver={(event) => {
                    event.preventDefault();
                    setDragging(true);
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={drop}
            >
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={t('Assets')}
                        description={t(
                            'Images, videos and graphics of the team, with captions, categories and tags.',
                        )}
                    />
                    <Button
                        disabled={uploading}
                        onClick={() => input.current?.click()}
                    >
                        <Upload />
                        {t(uploading ? 'Uploading…' : 'Upload files')}
                    </Button>
                    <input
                        ref={input}
                        type="file"
                        multiple
                        accept="image/*,video/*,.svg,.eps,.ai,.pdf"
                        className="hidden"
                        onChange={(event) => upload(event.target.files)}
                    />
                </div>

                <form
                    className="flex flex-wrap gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        apply({ q: query });
                    }}
                >
                    <Input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder={t('Search by title, caption, credit…')}
                        aria-label={t('Search')}
                        className="w-72 max-w-full"
                    />
                    <Select
                        value={filters.category || ALL}
                        onValueChange={(category) => apply({ category })}
                    >
                        <SelectTrigger
                            className="w-48"
                            aria-label={t('Category')}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>
                                {t('All categories')}
                            </SelectItem>
                            {categories.items.map((option) => (
                                <SelectItem key={option.key} value={option.key}>
                                    {t(option.label)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.kind || ALL}
                        onValueChange={(kind) => apply({ kind })}
                    >
                        <SelectTrigger className="w-40" aria-label={t('Type')}>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>
                                {t('All types')}
                            </SelectItem>
                            {(
                                Object.keys(
                                    kindLabels,
                                ) as AssetSummary['kind'][]
                            ).map((kind) => (
                                <SelectItem key={kind} value={kind}>
                                    {t(kindLabels[kind])}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {tags.length > 0 && (
                        <Select
                            value={filters.tag || ALL}
                            onValueChange={(tag) => apply({ tag })}
                        >
                            <SelectTrigger
                                className="w-44"
                                aria-label={t('Tag')}
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>
                                    {t('Any tag')}
                                </SelectItem>
                                {tags.map((tag) => (
                                    <SelectItem key={tag} value={tag}>
                                        {tag}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                    <Button variant="outline">{t('Search')}</Button>
                </form>

                {dragging && (
                    <div className="rounded-lg border-2 border-dashed border-primary bg-muted/50 p-8 text-center text-sm">
                        {t('Drop the files to upload them.')}
                    </div>
                )}

                {assets.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {filters.q ||
                        filters.category ||
                        filters.kind ||
                        filters.tag
                            ? t('Nothing matches this search.')
                            : t(
                                  'No assets yet. Upload files or drop them on this page.',
                              )}
                    </p>
                ) : (
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-6">
                        {assets.map((asset) => (
                            <Tile key={asset.id} asset={asset} />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

Assets.layout = {
    breadcrumbs: [{ title: 'Assets', href: index() }],
};
