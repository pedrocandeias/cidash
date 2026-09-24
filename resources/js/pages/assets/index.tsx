import { Head, Link, router } from '@inertiajs/react';
import { Check, FileImage, Film, Shapes, Upload } from 'lucide-react';
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
import {
    destroy as removeFromCollection,
    store as addToCollection,
} from '@/routes/assets/collection';

type Filters = { q: string; category: string; kind: string; tag: string };

type Props = {
    assets: AssetSummary[];
    filters: Filters;
    /** Collections are the tags of the team's assets. */
    collections: { name: string; count: number }[];
};

const ALL = 'all';

const kindIcons = { image: FileImage, video: Film, graphic: Shapes };

/**
 * A tile of the gallery: the thumbnail (or the video's first frame), title and category.
 * While selecting, a click picks the asset instead of opening it.
 */
function Tile({
    asset,
    selected,
    onToggle,
}: {
    asset: AssetSummary;
    selected: boolean;
    onToggle: (() => void) | null;
}) {
    const { t } = useTranslation();
    const categories = useOptions('asset_category');
    const Icon = kindIcons[asset.kind];
    const className = cn(
        'group relative flex flex-col overflow-hidden rounded-lg border bg-card text-left hover:border-primary',
        selected && 'border-primary ring-2 ring-primary',
    );
    const content = (
        <>
            {onToggle && (
                <span
                    className={cn(
                        'absolute top-2 left-2 z-10 flex size-6 items-center justify-center rounded-sm border bg-background',
                        selected &&
                            'border-primary bg-primary text-primary-foreground',
                    )}
                >
                    {selected && <Check className="size-4" />}
                </span>
            )}
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
        </>
    );

    return onToggle ? (
        <button
            type="button"
            className={className}
            aria-pressed={selected}
            onClick={onToggle}
        >
            {content}
        </button>
    ) : (
        <Link href={show(asset.id)} className={className}>
            {content}
        </Link>
    );
}

export default function Assets({ assets, filters, collections }: Props) {
    const { t } = useTranslation();
    const categories = useOptions('asset_category');
    const [query, setQuery] = useState(filters.q);
    const [dragging, setDragging] = useState(false);
    const [uploading, setUploading] = useState(false);
    const input = useRef<HTMLInputElement>(null);
    const [selecting, setSelecting] = useState(false);
    const [selected, setSelected] = useState<string[]>([]);
    const [collection, setCollection] = useState('');

    const stopSelecting = () => {
        setSelecting(false);
        setSelected([]);
        setCollection('');
    };

    const toggle = (id: string) =>
        setSelected(
            selected.includes(id)
                ? selected.filter((other) => other !== id)
                : [...selected, id],
        );

    const collect = (name: string, remove = false) => {
        const route = remove ? removeFromCollection() : addToCollection();
        router.visit(route.url, {
            method: route.method,
            data: { name, assets: selected },
            preserveScroll: true,
            onSuccess: stopSelecting,
            onError: (errors) =>
                toast.error(
                    Object.values(errors)[0] ?? t('Something went wrong.'),
                ),
        });
    };

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
                // Files uploaded while a collection is open join it.
                ...(filters.tag ? { tags: [filters.tag] } : {}),
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
                    <div className="flex gap-2">
                        {assets.length > 0 && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    selecting
                                        ? stopSelecting()
                                        : setSelecting(true)
                                }
                            >
                                {t(selecting ? 'Cancel' : 'Select')}
                            </Button>
                        )}
                        <Button
                            disabled={uploading}
                            onClick={() => input.current?.click()}
                        >
                            <Upload />
                            {t(uploading ? 'Uploading…' : 'Upload files')}
                        </Button>
                    </div>
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
                    <Button variant="outline">{t('Search')}</Button>
                </form>

                {collections.length > 0 && (
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-sm font-bold">
                            {t('Collections')}
                        </span>
                        {[{ name: '', count: null }, ...collections].map(
                            (item) => (
                                <Button
                                    key={item.name}
                                    size="sm"
                                    variant={
                                        filters.tag === item.name
                                            ? 'default'
                                            : 'outline'
                                    }
                                    aria-pressed={filters.tag === item.name}
                                    onClick={() => apply({ tag: item.name })}
                                >
                                    {item.name || t('All assets')}
                                    {item.count !== null && (
                                        <span className="opacity-70">
                                            {item.count}
                                        </span>
                                    )}
                                </Button>
                            ),
                        )}
                    </div>
                )}

                {selecting && (
                    <form
                        className="flex flex-wrap items-center gap-2 rounded-lg border bg-muted/50 p-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            collect(collection.trim());
                        }}
                    >
                        <span className="text-sm">
                            {selected.length === 0
                                ? t('Click the assets to select them.')
                                : t(':count selected', {
                                      count: selected.length,
                                  })}
                        </span>
                        <Input
                            value={collection}
                            onChange={(event) =>
                                setCollection(event.target.value)
                            }
                            list="asset-collections"
                            placeholder={t('Collection name')}
                            aria-label={t('Collection name')}
                            className="w-56 max-w-full"
                        />
                        <datalist id="asset-collections">
                            {collections.map((item) => (
                                <option key={item.name} value={item.name} />
                            ))}
                        </datalist>
                        <Button
                            size="sm"
                            disabled={
                                selected.length === 0 ||
                                collection.trim() === ''
                            }
                        >
                            {t('Add to collection')}
                        </Button>
                        {filters.tag && (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={selected.length === 0}
                                onClick={() => collect(filters.tag, true)}
                            >
                                {t('Remove from :name', { name: filters.tag })}
                            </Button>
                        )}
                    </form>
                )}

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
                            <Tile
                                key={asset.id}
                                asset={asset}
                                selected={selected.includes(asset.id)}
                                onToggle={
                                    selecting ? () => toggle(asset.id) : null
                                }
                            />
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
