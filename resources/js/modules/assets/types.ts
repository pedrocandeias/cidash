export type AssetSummary = {
    id: string;
    title: string;
    kind: 'image' | 'video' | 'graphic';
    category: string | null;
    caption: string | null;
    credit: string | null;
    width: number | null;
    height: number | null;
    tags: string[];
    url: string;
    thumbnail_url: string;
    previewable: boolean;
};

export const kindLabels: Record<AssetSummary['kind'], string> = {
    image: 'Image',
    video: 'Video',
    graphic: 'Graphic',
};
